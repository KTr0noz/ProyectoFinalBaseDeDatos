<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/includes/funciones.php
 * DESCRIPCIÓN:
 *   Funciones compartidas por toda la aplicación:
 *     1. Respaldo de funciones multibyte (mbstring).
 *     2. Constantes de negocio y estados permitidos (alineados con los CHECK).
 *     3. Utilidades de formato y salida segura (prevención de XSS).
 *     4. Validación de formularios del lado del servidor.
 *     5. Traducción de errores SQLSTATE de PostgreSQL a mensajes en español.
 *     6. Sincronización automática del estado de la venta y consulta 3FN.
 * ============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    if (PHP_SAPI !== 'cli') {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $es_https,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        session_start();
    }
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/* ============================================================================
 * 1. RESPALDO MULTIBYTE (por si el entorno PHP no tiene la extensión mbstring)
 * ============================================================================ */

if (!function_exists('mb_strlen')) {
    function mb_strlen($texto, $codificacion = null)
    {
        return preg_match_all('/./us', (string)$texto);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($texto, $codificacion = null)
    {
        return strtolower((string)$texto);
    }
}

/* ============================================================================
 * 2. REGLAS Y ESTADOS DEL NEGOCIO (idénticos a los CHECK de PostgreSQL)
 * ============================================================================ */

$ESTADOS_VENTA = array(
    'Pendiente',
    'Pagada',
    'Enviada',
    'Entregada',
    'Cancelada',
);

$TIPOS_PAGO = array(
    'PSE',
    'Tarjeta Visa',
    'Tarjeta Mastercard',
    'Efecty',
    'Transferencia Bancaria',
);

$ESTADOS_PAGO = array(
    'Pendiente',
    'Aprobado',
    'Rechazado',
);

/**
 * Transiciones de estado permitidas en una orden de venta:
 *   - Pendiente -> Pagada o Cancelada
 *   - Pagada    -> Enviada o Cancelada
 *   - Enviada   -> Entregada
 *   - Entregada y Cancelada son estados finales.
 */
$TRANSICIONES_VENTA = array(
    'Pendiente' => array('Pagada', 'Cancelada'),
    'Pagada'    => array('Enviada', 'Cancelada'),
    'Enviada'   => array('Entregada'),
    'Entregada' => array(),
    'Cancelada' => array(),
);

// Estados en los cuales una venta permite registrar, editar o eliminar pagos
$VENTA_ACEPTA_PAGOS = array('Pendiente', 'Pagada');

/* ============================================================================
 * 3. UTILIDADES DE SALIDA, FORMATO Y NAVEGACIÓN
 * ============================================================================ */

/** Escapa texto para imprimirlo de forma segura en HTML (evita XSS). */
function h($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Convierte un texto en literal JSON seguro para atributos HTML (ej. confirm). */
function js($texto)
{
    return h(json_encode((string)$texto, JSON_UNESCAPED_UNICODE));
}

/** Formatea un número en pesos colombianos: $2.350.000 */
function dinero($valor)
{
    return '$' . number_format((float)$valor, 0, ',', '.');
}

/** Convierte una fecha AAAA-MM-DD al formato legible DD/MM/AAAA. */
function fecha($valor)
{
    $d = DateTime::createFromFormat('Y-m-d', substr((string)$valor, 0, 10));
    return $d ? $d->format('d/m/Y') : h($valor);
}

/** Redirige a otra URL y detiene la ejecución del script. */
function redirigir($url)
{
    header('Location: ' . $url);
    exit;
}

/** Guarda un aviso temporal en sesión para mostrarlo en la siguiente pantalla. */
function flash($tipo, $mensaje)
{
    $_SESSION['flash'][] = array($tipo, $mensaje);
}

/** Imprime y limpia los avisos temporales guardados en la sesión. */
function mostrar_flash()
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $m) {
        echo '<div class="alerta alerta-' . h($m[0]) . '">' . h($m[1]) . '</div>';
    }
    unset($_SESSION['flash']);
}

/** Muestra una lista de errores de validación de un formulario. */
function mostrar_errores($errores)
{
    if (!$errores) {
        return;
    }
    echo '<div class="alerta alerta-error"><b>Revisa los datos:</b><ul>';
    foreach ($errores as $e) {
        echo '<li>' . h($e) . '</li>';
    }
    echo '</ul></div>';
}

/** Obtiene un campo enviado por POST limpio de espacios extremos. */
function post($clave, $defecto = '')
{
    return isset($_POST[$clave]) ? trim((string)$_POST[$clave]) : $defecto;
}

/** Obtiene un parámetro enviado por GET limpio de espacios extremos. */
function get($clave, $defecto = '')
{
    return isset($_GET[$clave]) ? trim((string)$_GET[$clave]) : $defecto;
}

/** Genera las etiquetas <option> a partir de un arreglo clave => texto. */
function opciones($opciones, $seleccionado)
{
    $html = '';
    foreach ($opciones as $valor => $texto) {
        $sel = ((string)$valor === (string)$seleccionado) ? ' selected' : '';
        $html .= '<option value="' . h($valor) . '"' . $sel . '>' . h($texto) . '</option>';
    }
    return $html;
}

/** Genera las etiquetas <option> cuando el valor y el texto visible son iguales. */
function opciones_simples($lista, $seleccionado)
{
    return opciones(array_combine($lista, $lista), $seleccionado);
}

/** Devuelve el color semántico CSS asociado a un estado de venta o pago. */
function clase_estado($estado)
{
    $mapa = array(
        'Pendiente' => 'gris',
        'Pagada'    => 'azul',
        'Enviada'   => 'morado',
        'Entregada' => 'verde',
        'Cancelada' => 'rojo',
        'Aprobado'  => 'verde',
        'Rechazado' => 'rojo',
    );
    return isset($mapa[$estado]) ? $mapa[$estado] : 'gris';
}

/** Devuelve la píldora HTML coloreada para un estado de venta o pago. */
function etiqueta_estado($estado)
{
    return '<span class="etiqueta ' . clase_estado($estado) . '">' . h($estado) . '</span>';
}

/* ============================================================================
 * 4. VALIDACIONES DE DATOS DE ENTRADA
 * ============================================================================ */

/** Valida que un texto sea obligatorio (si aplica) y no supere la longitud máxima. */
function validar_texto($valor, $campo, $max, &$errores, $obligatorio = true)
{
    if ($valor === '') {
        if ($obligatorio) {
            $errores[] = "$campo es obligatorio.";
        }
        return;
    }
    if (mb_strlen($valor, 'UTF-8') > $max) {
        $errores[] = "$campo no puede tener más de $max caracteres.";
    }
}

/** Valida un texto contra una expresión regular. */
function validar_patron($valor, $campo, $patron, $explicacion, &$errores)
{
    if ($valor !== '' && !preg_match($patron, $valor)) {
        $errores[] = "$campo: $explicacion";
    }
}

/** Valida que un valor sea un número entero no negativo dentro del rango permitido. */
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

/** Valida un importe monetario positivo compatible con NUMERIC(12,2). */
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

/** Valida una fecha en formato ISO (AAAA-MM-DD). */
function validar_fecha($valor, $campo, &$errores)
{
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    if ($valor === '' || !$d || $d->format('Y-m-d') !== $valor) {
        $errores[] = "$campo no es una fecha válida.";
    }
}

/** Valida el formato de una dirección de correo electrónico. */
function validar_email($valor, $campo, &$errores)
{
    if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "$campo no es un correo válido.";
    }
}

/** Valida que un valor pertenezca a una lista cerrada de opciones permitidas. */
function validar_opcion($valor, $campo, $lista, &$errores)
{
    if (!in_array($valor, $lista, true)) {
        $errores[] = "$campo no es una opción válida.";
    }
}

/* ============================================================================
 * 5. TRADUCCIÓN DE ERRORES DE POSTGRESQL A MENSAJES ENTENDIBLES
 * ============================================================================ */

/**
 * Convierte una excepción PDOException de PostgreSQL en un mensaje claro para el usuario,
 * identificando el código SQLSTATE, los triggers (P0001) y las restricciones violadas.
 */
function mensaje_error_bd(PDOException $e)
{
    $estado = isset($e->errorInfo[0]) ? $e->errorInfo[0] : '';
    $texto  = isset($e->errorInfo[2]) ? $e->errorInfo[2] : $e->getMessage();

    // Mensajes específicos para restricciones CHECK, PRIMARY KEY y UNIQUE
    $restricciones = array(
        'pk_cliente'           => 'Ya existe un cliente con esa cédula.',
        'ck_cliente_id'        => 'La cédula debe ser mayor que 0.',
        'uq_cliente_email'     => 'Ya existe un cliente registrado con ese correo.',
        'uq_categoria_nombre'  => 'Ya existe una categoría con ese nombre.',
        'ck_articulo_precio'   => 'El precio debe ser mayor que 0.',
        'ck_articulo_stock'    => 'El stock no puede ser negativo.',
        'pk_detalle_venta'     => 'Ese artículo ya está en la venta. Si necesita más unidades, quite la línea y agréguela con la cantidad total.',
        'ck_detalle_cantidad'  => 'La cantidad debe ser mayor que 0.',
        'ck_venta_estado'      => 'El estado de la venta no es válido.',
        'ck_forma_pago_tipo'         => 'El medio de pago no es válido.',
        'ck_forma_pago_monto'        => 'El monto del pago debe ser mayor que 0.',
        'ck_forma_pago_estado'       => 'El estado del pago no es válido.',
        'ck_cliente_rol'             => 'El rol del cliente no es válido.',
        'ck_cliente_admin_con_clave' => 'Una cuenta de administrador debe tener contraseña.',
        'ck_articulo_imagen_tipo'    => 'La imagen debe ser JPG, PNG o WEBP.',
        'ck_articulo_imagen_tamano'  => 'La imagen no puede pesar más de 2 MB.',
    );

    // Mensajes específicos cuando ON DELETE RESTRICT impide borrar un registro en uso
    $al_borrar = array(
        'fk_cliente_ciudad'     => 'No se puede eliminar la ciudad porque tiene clientes registrados.',
        'fk_articulo_categoria' => 'No se puede eliminar la categoría porque tiene artículos.',
        'fk_venta_cliente'      => 'No se puede eliminar el cliente porque tiene ventas registradas.',
        'fk_detalle_articulo'   => 'No se puede eliminar el artículo porque ya fue vendido.',
        'fk_forma_pago_venta'   => 'No se puede eliminar la venta porque tiene pagos registrados. Elimine primero los pagos.',
    );

    // P0001 = Excepción lanzada por un TRIGGER (ej. trg_descontar_stock_venta o trg_validar_fecha_pago)
    if ($estado === 'P0001') {
        if (preg_match('/ERROR:\s*(.+?)(\n|CONTEXT:|$)/s', $texto, $m)) {
            return trim($m[1]);
        }
        return $texto;
    }

    // 23503 / 23001 = Violación de llave foránea (ON DELETE RESTRICT)
    if ($estado === '23503' || $estado === '23001') {
        foreach ($al_borrar as $nombre => $mensaje) {
            if (strpos($texto, $nombre) !== false && stripos($texto, 'update or delete') !== false) {
                return $mensaje;
            }
        }
        return 'El registro relacionado no existe o está en uso por otra tabla.';
    }

    // 23505 = Violación UNIQUE / PK | 23514 = Violación CHECK
    if ($estado === '23505' || $estado === '23514') {
        foreach ($restricciones as $nombre => $mensaje) {
            if (strpos($texto, $nombre) !== false) {
                return $mensaje;
            }
        }
        return $estado === '23505'
            ? 'Ese valor ya está registrado.'
            : 'Un dato no cumple las reglas de la base de datos.';
    }

    // 23502 = Violación NOT NULL
    if ($estado === '23502') {
        return 'Falta un dato obligatorio.';
    }

    if ($estado === '42501') {
        error_log('Permiso denegado en PostgreSQL [42501]: ' . $texto);
        return 'No tiene permisos para realizar esta operación.';
    }

    return 'Error de la base de datos: ' . $texto;
}

/* ============================================================================
 * 6. LÓGICA DE SEGUIMIENTO DE VENTAS Y CONSULTA NORMALIZADA (3FN)
 * ============================================================================ */

/**
 * Calcula la suma de los pagos no rechazados (Aprobado + Pendiente) de una venta.
 * Se utiliza para evitar que se registren pagos por encima del valor total del pedido.
 */
function pagos_comprometidos($id_venta, $excluir_pago = 0)
{
    return (float)valor(
        "SELECT COALESCE(SUM(monto), 0)
         FROM forma_pago
         WHERE id_venta = ?
           AND estado <> 'Rechazado'
           AND id_pago <> ?",
        array((int)$id_venta, (int)$excluir_pago)
    );
}

/**
 * Sincroniza automáticamente el estado de la venta según sus pagos aprobados:
 *   - Si está 'Pendiente' y los pagos aprobados cubren el total -> pasa a 'Pagada'.
 *   - Si está 'Pagada' y al borrar/rechazar un pago deja de cubrir el total -> vuelve a 'Pendiente'.
 */
function sincronizar_estado_venta($id_venta)
{
    $v = fila(sql_ventas() . ' WHERE v.id_venta = ?', array((int)$id_venta));
    if (!$v) {
        return;
    }

    $pagada = $v['total'] > 0 && (float)$v['pagado'] >= (float)$v['total'];

    if ($v['estado'] === 'Pendiente' && $pagada) {
        consulta("UPDATE venta SET estado = 'Pagada' WHERE id_venta = ?", array((int)$id_venta));
        flash('info', 'La venta #' . $v['id_venta'] . ' quedó totalmente pagada y pasó a estado Pagada.');
    } elseif ($v['estado'] === 'Pagada' && !$pagada) {
        consulta("UPDATE venta SET estado = 'Pendiente' WHERE id_venta = ?", array((int)$id_venta));
        flash('info', 'La venta #' . $v['id_venta'] . ' ya no está totalmente pagada y volvió a estado Pendiente.');
    }
}

/**
 * Consulta base de ventas bajo Tercera Forma Normal (3FN):
 * El total de la venta NO se almacena estáticamente en la tabla `venta`;
 * se calcula dinámicamente multiplicando `cantidad * precio` desde `detalle_venta` y `articulo`.
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
                SELECT
                    dv.id_venta,
                    SUM(dv.cantidad * a.precio) AS total,
                    SUM(dv.cantidad)            AS unidades
                FROM detalle_venta dv
                JOIN articulo a ON a.id_articulo = dv.id_articulo
                GROUP BY dv.id_venta
            ) t ON t.id_venta = v.id_venta
            LEFT JOIN (
                SELECT
                    id_venta,
                    SUM(monto) AS pagado
                FROM forma_pago
                WHERE estado = 'Aprobado'
                GROUP BY id_venta
            ) p ON p.id_venta = v.id_venta";
}

/* ============================================================================
 * IMÁGENES DE ARTÍCULOS (tabla articulo_imagen)
 * ============================================================================ */

define('IMAGEN_MAX_BYTES', 2 * 1024 * 1024);

/**
 * Revisa el archivo subido en $_FILES[$campo].
 * Devuelve array('tipo' => mime, 'datos' => bytes), null si no se subió nada,
 * o agrega el problema a $errores y devuelve null.
 */
function leer_imagen_subida($campo, &$errores)
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $archivo = $_FILES[$campo];

    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE
        || $archivo['size'] > IMAGEN_MAX_BYTES) {
        $errores[] = 'La imagen no puede pesar más de 2 MB.';
        return null;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        $errores[] = 'No se pudo subir la imagen. Intente de nuevo.';
        return null;
    }

    // getimagesize lee el contenido real del archivo, no solo su extensión.
    $info = @getimagesize($archivo['tmp_name']);
    $permitidos = array('image/jpeg', 'image/png', 'image/webp');
    if (!$info || !in_array($info['mime'], $permitidos, true)) {
        $errores[] = 'La imagen debe ser JPG, PNG o WEBP.';
        return null;
    }

    return array(
        'tipo'  => $info['mime'],
        'datos' => file_get_contents($archivo['tmp_name']),
    );
}

/**
 * Crea o reemplaza la imagen de un artículo.
 * BYTEA necesita PDO::PARAM_LOB para enviar los bytes sin alterarlos.
 */
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
 * Pinta la imagen del artículo, o un recuadro con la inicial si no tiene.
 * $version es articulo_imagen.actualizada: cambia la URL cuando se reemplaza la foto.
 */
function imagen_articulo($id_articulo, $version, $nombre, $clase = 'imagen-articulo')
{
    if ($version === null || $version === '') {
        $inicial = mb_strtoupper(mb_substr(trim((string)$nombre), 0, 1, 'UTF-8'), 'UTF-8');
        return '<div class="' . h($clase) . ' sin-imagen" aria-hidden="true"><span>' . h($inicial) . '</span></div>';
    }

    $url = 'imagen.php?id=' . (int)$id_articulo . '&v=' . rawurlencode(strtotime($version));
    return '<img class="' . h($clase) . '" src="' . h($url) . '" alt="' . h($nombre) . '" loading="lazy">';
}
