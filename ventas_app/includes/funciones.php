<?php
/**
 * Funciones que usan todas las páginas. Cada página empieza con:
 *     require_once 'includes/funciones.php';
 * y eso también abre la sesión, carga config.php, la conexión (db.php) y el login (auth.php).
 *
 * Secciones:
 *   1. Sesión y archivos que se cargan
 *   2. Listas fijas del negocio (iguales a los CHECK de PostgreSQL)
 *   3. Formato y salida segura en HTML
 *   4. Formularios: leer datos, validar y botones repetidos
 *   5. Errores de PostgreSQL traducidos a mensajes claros
 *   6. Ventas: consulta con totales, pagos y stock
 *   7. Imágenes de artículos
 */

/* ============================================================================
 * 1. SESIÓN Y ARCHIVOS QUE SE CARGAN
 * ============================================================================ */

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $es_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// Respaldo por si el PHP del computador no tiene la extensión mbstring (acentos y ñ).
if (!function_exists('mb_strlen')) {
    function mb_strlen($texto, $codificacion = null) { return preg_match_all('/./us', (string)$texto); }
    function mb_strtolower($texto, $codificacion = null) { return strtolower((string)$texto); }
    function mb_strtoupper($texto, $codificacion = null) { return strtoupper((string)$texto); }
    function mb_substr($texto, $inicio, $largo = null, $codificacion = null) {
        preg_match_all('/./us', (string)$texto, $letras);
        return implode('', array_slice($letras[0], $inicio, $largo));
    }
}

/* ============================================================================
 * 2. LISTAS FIJAS DEL NEGOCIO (iguales a los CHECK de PostgreSQL)
 * ============================================================================ */

$ESTADOS_VENTA = array('Pendiente', 'Pagada', 'Enviada', 'Entregada', 'Cancelada');
$TIPOS_PAGO    = array('PSE', 'Tarjeta Visa', 'Tarjeta Mastercard', 'Efecty', 'Transferencia Bancaria');
$ESTADOS_PAGO  = array('Pendiente', 'Aprobado', 'Rechazado');

// A qué estados puede pasar una venta desde cada estado. Entregada y Cancelada son finales.
$TRANSICIONES_VENTA = array(
    'Pendiente' => array('Pagada', 'Cancelada'),
    'Pagada'    => array('Enviada', 'Cancelada'),
    'Enviada'   => array('Entregada'),
    'Entregada' => array(),
    'Cancelada' => array(),
);

// Estados de venta en los que todavía se pueden registrar, editar o borrar pagos.
$VENTA_ACEPTA_PAGOS = array('Pendiente', 'Pagada');

/* ============================================================================
 * 3. FORMATO Y SALIDA SEGURA EN HTML
 * ============================================================================ */

/** Escapa un texto antes de imprimirlo en HTML (evita inyectar código). */
function h($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** 2350000 -> "$2.350.000" */
function dinero($valor)
{
    return '$' . number_format((float)$valor, 0, ',', '.');
}

/** "2026-11-08" -> "08/11/2026" */
function fecha($valor)
{
    $d = DateTime::createFromFormat('Y-m-d', substr((string)$valor, 0, 10));
    return $d ? $d->format('d/m/Y') : h($valor);
}

/** Color de la etiqueta de un estado de venta o de pago. */
function clase_estado($estado)
{
    $colores = array(
        'Pendiente' => 'gris',  'Pagada'    => 'azul',  'Enviada' => 'morado',
        'Entregada' => 'verde', 'Cancelada' => 'rojo',
        'Aprobado'  => 'verde', 'Rechazado' => 'rojo',
    );
    return $colores[$estado] ?? 'gris';
}

/** Etiqueta de color con el estado: <span class="etiqueta verde">Entregada</span> */
function etiqueta_estado($estado)
{
    return '<span class="etiqueta ' . clase_estado($estado) . '">' . h($estado) . '</span>';
}

/** Va a otra página y termina este script. */
function redirigir($url)
{
    header('Location: ' . $url);
    exit;
}

/** Guarda un aviso para mostrarlo en la próxima página ($tipo: ok, error o info). */
function flash($tipo, $mensaje)
{
    $_SESSION['flash'][] = array($tipo, $mensaje);
}

/** Imprime los avisos guardados con flash() y los borra. */
function mostrar_flash()
{
    foreach ($_SESSION['flash'] ?? array() as $aviso) {
        echo '<div class="alerta alerta-' . h($aviso[0]) . '">' . h($aviso[1]) . '</div>';
    }
    unset($_SESSION['flash']);
}

/** Imprime la lista de errores de un formulario. */
function mostrar_errores($errores)
{
    if (!$errores) {
        return;
    }
    echo '<div class="alerta alerta-error"><b>Revisa los datos:</b><ul>';
    foreach ($errores as $error) {
        echo '<li>' . h($error) . '</li>';
    }
    echo '</ul></div>';
}

/* ============================================================================
 * 4. FORMULARIOS: LEER DATOS, VALIDAR Y BOTONES REPETIDOS
 * ============================================================================ */

/** Campo enviado por POST, sin espacios a los lados. */
function post($clave, $defecto = '')
{
    return isset($_POST[$clave]) ? trim((string)$_POST[$clave]) : $defecto;
}

/** Parámetro de la dirección (GET), sin espacios a los lados. */
function get($clave, $defecto = '')
{
    return isset($_GET[$clave]) ? trim((string)$_GET[$clave]) : $defecto;
}

/** Texto vacío -> NULL, para columnas opcionales como la descripción. */
function nulo_si_vacio($texto)
{
    return $texto === '' ? null : $texto;
}

/**
 * Revisa el token CSRF de un formulario enviado por POST.
 * Si no es válido (formulario viejo o de otra página), vuelve a $volver con un aviso.
 */
function exigir_csrf($volver)
{
    if (!csrf_validar()) {
        flash('error', 'La sesión del formulario expiró. Intente nuevamente.');
        redirigir($volver);
    }
}

/** <option> para un <select>, a partir de un arreglo valor => texto. */
function opciones($opciones, $seleccionado)
{
    $html = '';
    foreach ($opciones as $valor => $texto) {
        $marcado = (string)$valor === (string)$seleccionado ? ' selected' : '';
        $html .= '<option value="' . h($valor) . '"' . $marcado . '>' . h($texto) . '</option>';
    }
    return $html;
}

/** <option> cuando el valor y el texto son iguales (por ejemplo, los estados). */
function opciones_simples($lista, $seleccionado)
{
    return opciones(array_combine($lista, $lista), $seleccionado);
}

/** Arreglo id => texto para un <select>, desde una consulta de dos columnas. */
function lista_opciones($sql)
{
    $lista = array();
    foreach (filas($sql) as $fila) {
        list($id, $texto) = array_values($fila);
        $lista[$id] = $texto;
    }
    return $lista;
}

function lista_ciudades()
{
    return lista_opciones("SELECT id_ciudad, nombre || ' (' || departamento || ')' FROM ciudad ORDER BY nombre");
}

function lista_categorias()
{
    return lista_opciones('SELECT id_categoria, nombre FROM categoria ORDER BY nombre');
}

/**
 * Formulario de un solo botón que envía un POST y pide confirmación antes.
 *   $campos:   campos ocultos, por ejemplo array('accion' => 'eliminar', 'id_ciudad' => 3)
 *   $pregunta: texto de la confirmación ("¿Eliminar la ciudad Bogotá?")
 * La confirmación la muestra js/app.js con el atributo data-confirmar.
 */
function boton_post($campos, $pregunta, $texto = 'Eliminar', $clase = 'boton peligro chico', $destino = '')
{
    $html = '<form method="post"' . ($destino !== '' ? ' action="' . h($destino) . '"' : '')
          . ' data-confirmar="' . h($pregunta) . '">' . csrf_campo();
    foreach ($campos as $nombre => $valor) {
        $html .= '<input type="hidden" name="' . h($nombre) . '" value="' . h($valor) . '">';
    }
    return $html . '<button class="' . h($clase) . '">' . h($texto) . '</button></form>';
}

/**
 * Borra un registro (DELETE con un id), deja el aviso de éxito o el error
 * traducido (por ejemplo, ON DELETE RESTRICT) y vuelve a la lista.
 */
function eliminar_y_volver($sql, $id, $mensaje_ok, $volver)
{
    try {
        consulta($sql, array($id));
        flash('ok', $mensaje_ok);
    } catch (PDOException $e) {
        flash('error', mensaje_error_bd($e));
    }
    redirigir($volver);
}

/** Busca un registro para editarlo; si no existe, vuelve a la lista con un aviso. */
function buscar_o_volver($sql, $id, $mensaje, $volver)
{
    $registro = fila($sql, array($id));
    if (!$registro) {
        flash('error', $mensaje);
        redirigir($volver);
    }
    return $registro;
}

/**
 * Ejecuta varias consultas como una sola transacción: o se guardan todas o ninguna.
 * Si algo falla, deshace los cambios y vuelve a lanzar el error.
 */
function en_transaccion($pasos)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $resultado = $pasos();
        $pdo->commit();
        return $resultado;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// --- Validaciones. Cada una agrega el problema a $errores si el dato no sirve. ---

/** Texto obligatorio (u opcional) con un máximo de caracteres. */
function validar_texto($valor, $campo, $max, &$errores, $obligatorio = true)
{
    if ($valor === '') {
        if ($obligatorio) {
            $errores[] = "$campo es obligatorio.";
        }
    } elseif (mb_strlen($valor, 'UTF-8') > $max) {
        $errores[] = "$campo no puede tener más de $max caracteres.";
    }
}

/** Texto que debe cumplir una expresión regular. */
function validar_patron($valor, $campo, $patron, $explicacion, &$errores)
{
    if ($valor !== '' && !preg_match($patron, $valor)) {
        $errores[] = "$campo: $explicacion";
    }
}

/** Número entero sin signo entre $minimo y $maximo. */
function validar_entero($valor, $campo, &$errores, $minimo = 1, $maximo = 2147483647)
{
    if ($valor === '') {
        $errores[] = "$campo es obligatorio.";
    } elseif (!ctype_digit($valor)) {
        $errores[] = "$campo debe ser un número entero sin signos ni decimales (no se aceptan negativos).";
    } elseif ((float)$valor < $minimo) {
        $errores[] = "$campo debe ser mayor o igual a $minimo.";
    } elseif ((float)$valor > $maximo) {
        $errores[] = "$campo no puede ser mayor que " . number_format($maximo, 0, ',', '.') . '.';
    }
}

/** Valor en pesos mayor que 0, con máximo 2 decimales (cabe en NUMERIC(12,2)). */
function validar_dinero($valor, $campo, &$errores)
{
    if ($valor === '') {
        $errores[] = "$campo es obligatorio.";
    } elseif (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $valor)) {
        $errores[] = "$campo debe ser un valor positivo con máximo 2 decimales (use punto, sin separador de miles).";
    } elseif ((float)$valor <= 0) {
        $errores[] = "$campo debe ser mayor que 0.";
    }
}

/** Fecha real con formato AAAA-MM-DD. */
function validar_fecha($valor, $campo, &$errores)
{
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$d || $d->format('Y-m-d') !== $valor) {
        $errores[] = "$campo no es una fecha válida.";
    }
}

function validar_email($valor, $campo, &$errores)
{
    if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "$campo no es un correo válido.";
    }
}

/** El valor debe ser uno de la lista (por ejemplo, un estado permitido). */
function validar_opcion($valor, $campo, $lista, &$errores)
{
    if (!in_array($valor, $lista, true)) {
        $errores[] = "$campo no es una opción válida.";
    }
}

/* ============================================================================
 * 5. ERRORES DE POSTGRESQL TRADUCIDOS A MENSAJES CLAROS
 * ============================================================================ */

/**
 * Convierte un error de PostgreSQL en un mensaje para el usuario.
 * Se guía por el código SQLSTATE y por el nombre de la restricción que falló.
 */
function mensaje_error_bd(PDOException $e)
{
    $codigo = $e->errorInfo[0] ?? '';
    $texto  = $e->errorInfo[2] ?? $e->getMessage();

    // UNIQUE, PRIMARY KEY y CHECK, por nombre de restricción
    $restricciones = array(
        'pk_cliente'                 => 'Ya existe un cliente con esa cédula.',
        'ck_cliente_id'              => 'La cédula debe ser mayor que 0.',
        'uq_cliente_email'           => 'Ya existe un cliente registrado con ese correo.',
        'uq_categoria_nombre'        => 'Ya existe una categoría con ese nombre.',
        'ck_articulo_precio'         => 'El precio debe ser mayor que 0.',
        'ck_articulo_stock'          => 'El stock no puede ser negativo.',
        'pk_detalle_venta'           => 'Ese artículo ya está en la venta. Si necesita más unidades, quite la línea y agréguela con la cantidad total.',
        'ck_detalle_cantidad'        => 'La cantidad debe ser mayor que 0.',
        'ck_venta_estado'            => 'El estado de la venta no es válido.',
        'ck_forma_pago_tipo'         => 'El medio de pago no es válido.',
        'ck_forma_pago_monto'        => 'El monto del pago debe ser mayor que 0.',
        'ck_forma_pago_estado'       => 'El estado del pago no es válido.',
        'ck_cliente_rol'             => 'El rol del cliente no es válido.',
        'ck_cliente_admin_con_clave' => 'Una cuenta de administrador debe tener contraseña.',
        'ck_articulo_imagen_tipo'    => 'La imagen debe ser JPG, PNG o WEBP.',
        'ck_articulo_imagen_tamano'  => 'La imagen no puede pesar más de 2 MB.',
    );

    // Llaves foráneas con ON DELETE RESTRICT: no se puede borrar algo que otra tabla usa
    $al_borrar = array(
        'fk_cliente_ciudad'     => 'No se puede eliminar la ciudad porque tiene clientes registrados.',
        'fk_articulo_categoria' => 'No se puede eliminar la categoría porque tiene artículos.',
        'fk_venta_cliente'      => 'No se puede eliminar el cliente porque tiene ventas registradas.',
        'fk_detalle_articulo'   => 'No se puede eliminar el artículo porque ya fue vendido.',
        'fk_forma_pago_venta'   => 'No se puede eliminar la venta porque tiene pagos registrados. Elimine primero los pagos.',
    );

    switch ($codigo) {
        case 'P0001': // RAISE EXCEPTION de un disparador: se muestra su mensaje tal cual
            return preg_match('/ERROR:\s*(.+?)(\n|CONTEXT:|$)/s', $texto, $m) ? trim($m[1]) : $texto;

        case '23503': // llave foránea
        case '23001':
            foreach ($al_borrar as $nombre => $mensaje) {
                if (strpos($texto, $nombre) !== false && stripos($texto, 'update or delete') !== false) {
                    return $mensaje;
                }
            }
            return 'El registro relacionado no existe o está en uso por otra tabla.';

        case '23505': // UNIQUE o PRIMARY KEY repetida
        case '23514': // CHECK
            foreach ($restricciones as $nombre => $mensaje) {
                if (strpos($texto, $nombre) !== false) {
                    return $mensaje;
                }
            }
            return $codigo === '23505' ? 'Ese valor ya está registrado.' : 'Un dato no cumple las reglas de la base de datos.';

        case '23502': // NOT NULL
            return 'Falta un dato obligatorio.';

        case '42501': // el rol de PostgreSQL no tiene permiso
            error_log('Permiso denegado en PostgreSQL [42501]: ' . $texto);
            return 'No tiene permisos para realizar esta operación.';
    }

    return 'Error de la base de datos: ' . $texto;
}

/* ============================================================================
 * 6. VENTAS: CONSULTA CON TOTALES, PAGOS Y STOCK
 * ============================================================================ */

/**
 * Consulta base de ventas. El total NO se guarda en la tabla venta (3FN):
 * se calcula sumando cantidad * precio de detalle_venta. "pagado" suma solo los pagos Aprobados.
 * Se le agrega WHERE / ORDER BY según la página.
 */
function sql_ventas()
{
    return "SELECT
                v.id_venta,
                v.fecha,
                v.estado,
                v.direccion_envio,
                v.id_cliente,
                c.nombre || ' ' || c.apellido AS cliente,
                ci.nombre                     AS ciudad,
                COALESCE(t.total, 0)          AS total,
                COALESCE(t.unidades, 0)       AS unidades,
                COALESCE(p.pagado, 0)         AS pagado
            FROM venta v
            JOIN cliente c ON c.id_cliente = v.id_cliente
            JOIN ciudad ci ON ci.id_ciudad = c.id_ciudad
            LEFT JOIN (
                SELECT dv.id_venta,
                       SUM(dv.cantidad * a.precio) AS total,
                       SUM(dv.cantidad)            AS unidades
                FROM detalle_venta dv
                JOIN articulo a ON a.id_articulo = dv.id_articulo
                GROUP BY dv.id_venta
            ) t ON t.id_venta = v.id_venta
            LEFT JOIN (
                SELECT id_venta, SUM(monto) AS pagado
                FROM forma_pago
                WHERE estado = 'Aprobado'
                GROUP BY id_venta
            ) p ON p.id_venta = v.id_venta";
}

/** Una venta con sus totales, o false si no existe. */
function buscar_venta($id_venta)
{
    return fila(sql_ventas() . ' WHERE v.id_venta = ?', array((int)$id_venta));
}

/** ¿Los pagos aprobados ya cubren el total? (una venta sin artículos nunca está pagada) */
function venta_pagada($venta)
{
    return $venta['total'] > 0 && (float)$venta['pagado'] >= (float)$venta['total'];
}

/** Artículos de una venta con su subtotal. */
function lineas_venta($id_venta)
{
    return filas(
        'SELECT d.id_articulo, d.cantidad, a.nombre, a.precio, (d.cantidad * a.precio) AS subtotal
         FROM detalle_venta d
         JOIN articulo a ON a.id_articulo = d.id_articulo
         WHERE d.id_venta = ?
         ORDER BY a.nombre',
        array((int)$id_venta)
    );
}

/** Pagos de una venta, del más antiguo al más nuevo. */
function pagos_venta($id_venta)
{
    return filas('SELECT * FROM forma_pago WHERE id_venta = ? ORDER BY fecha_pago, id_pago', array((int)$id_venta));
}

/**
 * Suma de los pagos que no están Rechazados (Aprobados + Pendientes).
 * Sirve para no aceptar pagos por encima del total. $excluir_pago: el pago que se está editando.
 */
function pagos_comprometidos($id_venta, $excluir_pago = 0)
{
    return (float)valor(
        "SELECT COALESCE(SUM(monto), 0)
         FROM forma_pago
         WHERE id_venta = ? AND estado <> 'Rechazado' AND id_pago <> ?",
        array((int)$id_venta, (int)$excluir_pago)
    );
}

/**
 * Cambia sola la venta entre Pendiente y Pagada según sus pagos aprobados:
 *   Pendiente y ya está cubierta  -> Pagada
 *   Pagada y ya no está cubierta  -> Pendiente (por ejemplo, al borrar un pago)
 */
function sincronizar_estado_venta($id_venta)
{
    $venta = buscar_venta($id_venta);
    if (!$venta) {
        return;
    }

    $pagada = venta_pagada($venta);

    if ($venta['estado'] === 'Pendiente' && $pagada) {
        consulta("UPDATE venta SET estado = 'Pagada' WHERE id_venta = ?", array($venta['id_venta']));
        flash('info', 'La venta #' . $venta['id_venta'] . ' quedó totalmente pagada y pasó a estado Pagada.');
    } elseif ($venta['estado'] === 'Pagada' && !$pagada) {
        consulta("UPDATE venta SET estado = 'Pendiente' WHERE id_venta = ?", array($venta['id_venta']));
        flash('info', 'La venta #' . $venta['id_venta'] . ' ya no está totalmente pagada y volvió a estado Pendiente.');
    }
}

/**
 * Devuelve al inventario las unidades de una venta (al cancelarla o eliminarla).
 * Una venta Cancelada ya devolvió su stock, así que no lo devuelve dos veces.
 */
function devolver_stock_venta($id_venta)
{
    consulta(
        "UPDATE articulo a
         SET stock = a.stock + d.cantidad
         FROM detalle_venta d
         JOIN venta v ON v.id_venta = d.id_venta
         WHERE d.id_venta = ?
           AND d.id_articulo = a.id_articulo
           AND v.estado <> 'Cancelada'",
        array((int)$id_venta)
    );
}

/* ============================================================================
 * 7. IMÁGENES DE ARTÍCULOS (tabla articulo_imagen)
 * ============================================================================ */

define('IMAGEN_MAX_BYTES', 2 * 1024 * 1024);

/**
 * Revisa la imagen subida en $_FILES[$campo].
 * Devuelve array('tipo' => ..., 'datos' => ...), o null si no se subió nada o no sirve
 * (en ese caso agrega el problema a $errores).
 */
function leer_imagen_subida($campo, &$errores)
{
    $archivo = $_FILES[$campo] ?? null;
    if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (in_array($archivo['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)
        || $archivo['size'] > IMAGEN_MAX_BYTES) {
        $errores[] = 'La imagen no puede pesar más de 2 MB.';
        return null;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        $errores[] = 'No se pudo subir la imagen. Intente de nuevo.';
        return null;
    }

    // getimagesize revisa el contenido real del archivo, no solo la extensión del nombre.
    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !in_array($info['mime'], array('image/jpeg', 'image/png', 'image/webp'), true)) {
        $errores[] = 'La imagen debe ser JPG, PNG o WEBP.';
        return null;
    }

    return array('tipo' => $info['mime'], 'datos' => file_get_contents($archivo['tmp_name']));
}

/** Crea o reemplaza la imagen de un artículo. BYTEA necesita PARAM_LOB para no alterar los bytes. */
function guardar_imagen_articulo($id_articulo, $imagen)
{
    $sentencia = db()->prepare(
        'INSERT INTO articulo_imagen (id_articulo, tipo, datos)
         VALUES (:id, :tipo, :datos)
         ON CONFLICT (id_articulo) DO UPDATE
         SET tipo = EXCLUDED.tipo, datos = EXCLUDED.datos, actualizada = CURRENT_TIMESTAMP'
    );
    $sentencia->bindValue(':id', (int)$id_articulo, PDO::PARAM_INT);
    $sentencia->bindValue(':tipo', $imagen['tipo']);
    $sentencia->bindValue(':datos', $imagen['datos'], PDO::PARAM_LOB);
    $sentencia->execute();
}

/**
 * <img> del artículo, o un recuadro con su inicial si no tiene foto.
 * $version es articulo_imagen.actualizada: al cambiar la foto cambia la URL y el navegador la recarga.
 */
function imagen_articulo($id_articulo, $version, $nombre, $clase = 'imagen-articulo')
{
    if ($version === null || $version === '') {
        $inicial = mb_strtoupper(mb_substr(trim((string)$nombre), 0, 1, 'UTF-8'), 'UTF-8');
        return '<div class="' . h($clase) . ' sin-imagen" aria-hidden="true"><span>' . h($inicial) . '</span></div>';
    }

    $url = 'imagen.php?id=' . (int)$id_articulo . '&v=' . strtotime($version);
    return '<img class="' . h($clase) . '" src="' . h($url) . '" alt="' . h($nombre) . '" loading="lazy">';
}
