<?php
/*
 * Funciones comunes: salida segura, mensajes, validación de datos de entrada
 * y traducción de los errores de PostgreSQL a mensajes entendibles.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/*
 * Respaldo por si PHP no tiene la extensión mbstring (php-mbstring).
 * Sin ella, mb_strlen() no existe y cada formulario de guardar daba error 500.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen($texto, $codificacion = null)
    {
        return preg_match_all('/./us', (string)$texto);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($texto, $codificacion = null)
    {
        return strtolower((string)$texto); // suficiente para correos (ASCII)
    }
}

/* ---------- Listas cerradas (las mismas de los CHECK de la base) ---------- */
$ESTADOS_VENTA = array('Pendiente', 'Pagada', 'Enviada', 'Entregada', 'Cancelada');
$TIPOS_PAGO    = array('PSE', 'Tarjeta Visa', 'Tarjeta Mastercard', 'Efecty', 'Transferencia Bancaria');
$ESTADOS_PAGO  = array('Pendiente', 'Aprobado', 'Rechazado');

/*
 * Cambios de estado que se permiten hacer a mano en una venta.
 * Pendiente <-> Pagada también cambia sola según los pagos aprobados.
 * Entregada y Cancelada son estados finales.
 */
$TRANSICIONES_VENTA = array(
    'Pendiente' => array('Pagada', 'Cancelada'),
    'Pagada'    => array('Enviada', 'Cancelada'),
    'Enviada'   => array('Entregada'),
    'Entregada' => array(),
    'Cancelada' => array(),
);

/* Solo se registran, editan o eliminan pagos de ventas en estos estados */
$VENTA_ACEPTA_PAGOS = array('Pendiente', 'Pagada');

/* ---------- Utilidades de salida ---------- */

/** Escapa texto para mostrarlo en HTML (evita inyección de HTML/JS). */
function h($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Texto seguro para usar dentro de JavaScript en un atributo HTML (ej. confirm). */
function js($texto)
{
    return h(json_encode((string)$texto, JSON_UNESCAPED_UNICODE));
}

/** Formatea un valor en pesos colombianos: $2.350.000 */
function dinero($valor)
{
    return '$' . number_format((float)$valor, 0, ',', '.');
}

/** Formatea una fecha AAAA-MM-DD como DD/MM/AAAA. */
function fecha($valor)
{
    $d = DateTime::createFromFormat('Y-m-d', substr((string)$valor, 0, 10));
    return $d ? $d->format('d/m/Y') : h($valor);
}

function redirigir($url)
{
    header('Location: ' . $url);
    exit;
}

/** Guarda un mensaje para mostrarlo en la siguiente página. Tipo: ok | error | info */
function flash($tipo, $mensaje)
{
    $_SESSION['flash'][] = array($tipo, $mensaje);
}

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

function post($clave, $defecto = '')
{
    return isset($_POST[$clave]) ? trim((string)$_POST[$clave]) : $defecto;
}

function get($clave, $defecto = '')
{
    return isset($_GET[$clave]) ? trim((string)$_GET[$clave]) : $defecto;
}

/** Genera las <option> de un <select>. $opciones = array(valor => texto) */
function opciones($opciones, $seleccionado)
{
    $html = '';
    foreach ($opciones as $valor => $texto) {
        $sel = ((string)$valor === (string)$seleccionado) ? ' selected' : '';
        $html .= '<option value="' . h($valor) . '"' . $sel . '>' . h($texto) . '</option>';
    }
    return $html;
}

/** Igual que opciones() pero cuando valor y texto son lo mismo. */
function opciones_simples($lista, $seleccionado)
{
    return opciones(array_combine($lista, $lista), $seleccionado);
}

/** Clase CSS para la etiqueta de un estado. */
function clase_estado($estado)
{
    $mapa = array(
        'Pendiente' => 'gris', 'Pagada' => 'azul', 'Enviada' => 'morado',
        'Entregada' => 'verde', 'Cancelada' => 'rojo',
        'Aprobado' => 'verde', 'Rechazado' => 'rojo',
    );
    return isset($mapa[$estado]) ? $mapa[$estado] : 'gris';
}

function etiqueta_estado($estado)
{
    return '<span class="etiqueta ' . clase_estado($estado) . '">' . h($estado) . '</span>';
}

/* ---------- Validación de datos de entrada ---------- */

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

function validar_patron($valor, $campo, $patron, $explicacion, &$errores)
{
    if ($valor !== '' && !preg_match($patron, $valor)) {
        $errores[] = "$campo: $explicacion";
    }
}

/** Entero dentro de un rango (por defecto, positivo y que quepa en INT4). */
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

/** Valor en dinero: positivo, máximo 2 decimales y 10 dígitos enteros (NUMERIC(12,2)). */
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

function validar_fecha($valor, $campo, &$errores)
{
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    if ($valor === '' || !$d || $d->format('Y-m-d') !== $valor) {
        $errores[] = "$campo no es una fecha válida.";
    }
}

function validar_email($valor, $campo, &$errores)
{
    if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "$campo no es un correo válido.";
    }
}

function validar_opcion($valor, $campo, $lista, &$errores)
{
    if (!in_array($valor, $lista, true)) {
        $errores[] = "$campo no es una opción válida.";
    }
}

/* ---------- Errores de PostgreSQL ---------- */

/**
 * Convierte una excepción de PostgreSQL en un mensaje claro.
 * Usa el SQLSTATE y el nombre de la restricción que falló.
 */
function mensaje_error_bd(PDOException $e)
{
    $estado = isset($e->errorInfo[0]) ? $e->errorInfo[0] : '';
    $texto  = isset($e->errorInfo[2]) ? $e->errorInfo[2] : $e->getMessage();

    // Mensajes por nombre de restricción (CHECK y UNIQUE)
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
        'ck_forma_pago_tipo'   => 'El medio de pago no es válido.',
        'ck_forma_pago_monto'  => 'El monto del pago debe ser mayor que 0.',
        'ck_forma_pago_estado' => 'El estado del pago no es válido.',
    );

    // Mensajes de llaves foráneas al intentar borrar el registro padre (ON DELETE RESTRICT)
    $al_borrar = array(
        'fk_cliente_ciudad'     => 'No se puede eliminar la ciudad porque tiene clientes registrados.',
        'fk_articulo_categoria' => 'No se puede eliminar la categoría porque tiene artículos.',
        'fk_venta_cliente'      => 'No se puede eliminar el cliente porque tiene ventas registradas.',
        'fk_detalle_articulo'   => 'No se puede eliminar el artículo porque ya fue vendido.',
        'fk_forma_pago_venta'   => 'No se puede eliminar la venta porque tiene pagos registrados. Elimine primero los pagos.',
    );

    // P0001 = RAISE EXCEPTION de un disparador: se muestra su propio mensaje
    if ($estado === 'P0001') {
        if (preg_match('/ERROR:\s*(.+?)(\n|CONTEXT:|$)/s', $texto, $m)) {
            return trim($m[1]);
        }
        return $texto;
    }

    // Violación de llave foránea. PostgreSQL 18 usa 23001 (restrict_violation)
    // para ON DELETE RESTRICT; las versiones anteriores usaban 23503.
    if ($estado === '23503' || $estado === '23001') {
        foreach ($al_borrar as $nombre => $mensaje) {
            if (strpos($texto, $nombre) !== false && stripos($texto, 'update or delete') !== false) {
                return $mensaje;
            }
        }
        return 'El registro relacionado no existe o está en uso por otra tabla.';
    }

    if ($estado === '23505' || $estado === '23514') { // UNIQUE / CHECK
        foreach ($restricciones as $nombre => $mensaje) {
            if (strpos($texto, $nombre) !== false) {
                return $mensaje;
            }
        }
        return $estado === '23505' ? 'Ese valor ya está registrado.' : 'Un dato no cumple las reglas de la base de datos.';
    }

    if ($estado === '23502') {
        return 'Falta un dato obligatorio.';
    }

    return 'Error de la base de datos: ' . $texto;
}

/* ---------- Seguimiento de ventas y pagos ---------- */

/**
 * Suma de los pagos que no están rechazados (aprobados + pendientes) de una venta.
 * Se usa para no dejar pagar más del total. $excluir_pago: el pago que se está editando.
 */
function pagos_comprometidos($id_venta, $excluir_pago = 0)
{
    return (float)valor("SELECT COALESCE(SUM(monto), 0) FROM forma_pago
                         WHERE id_venta = ? AND estado <> 'Rechazado' AND id_pago <> ?",
                        array((int)$id_venta, (int)$excluir_pago));
}

/**
 * Deja el estado de la venta de acuerdo con sus pagos aprobados:
 * - Pendiente y totalmente pagada  -> Pagada
 * - Pagada pero ya no cubre el total (se borró o rechazó un pago) -> Pendiente
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

/* ---------- Consultas reutilizables ---------- */

/**
 * SQL de ventas con su total calculado (no se guarda en la tabla, por 3FN),
 * las unidades vendidas y lo pagado con pagos aprobados.
 */
function sql_ventas()
{
    return "SELECT v.id_venta, v.fecha, v.estado, v.direccion_envio, v.id_cliente,
                   c.nombre || ' ' || c.apellido AS cliente, ci.nombre AS ciudad,
                   COALESCE(t.total, 0)    AS total,
                   COALESCE(t.unidades, 0) AS unidades,
                   COALESCE(p.pagado, 0)   AS pagado
            FROM venta v
            JOIN cliente c ON c.id_cliente = v.id_cliente
            JOIN ciudad ci ON ci.id_ciudad = c.id_ciudad
            LEFT JOIN (SELECT dv.id_venta,
                              SUM(dv.cantidad * a.precio) AS total,
                              SUM(dv.cantidad)            AS unidades
                       FROM detalle_venta dv
                       JOIN articulo a ON a.id_articulo = dv.id_articulo
                       GROUP BY dv.id_venta) t ON t.id_venta = v.id_venta
            LEFT JOIN (SELECT id_venta, SUM(monto) AS pagado
                       FROM forma_pago
                       WHERE estado = 'Aprobado'
                       GROUP BY id_venta) p ON p.id_venta = v.id_venta";
}
