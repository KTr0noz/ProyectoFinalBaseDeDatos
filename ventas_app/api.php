<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/api.php
 * DESCRIPCIÓN:
 *   Endpoint JSON de estado (Healthcheck y resumen de las 7 tablas en PostgreSQL).
 *   Reutiliza la misma conexión PDO de `includes/funciones.php`.
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/includes/funciones.php';

    $version = valor('SELECT version()');

    $tablas  = array('ciudad', 'cliente', 'categoria', 'articulo', 'venta', 'detalle_venta', 'forma_pago');
    $conteos = array();
    foreach ($tablas as $tabla) {
        $conteos[$tabla] = (int)valor("SELECT COUNT(*) FROM {$tabla}");
    }

    $ultimasVentas = filas(sql_ventas() . ' ORDER BY v.id_venta DESC LIMIT 5');

    echo json_encode(array(
        'status'              => 'ok',
        'proyecto'            => 'Proyecto #13: Sistema de Ventas por Internet (Cód. 5465)',
        'php_version'         => PHP_VERSION,
        'database'            => 'Conectado exitosamente a PostgreSQL en Railway',
        'host'                => DB_HOST,
        'port'                => (int)DB_PORT,
        'dbname'              => DB_NAME,
        'registros_por_tabla' => $conteos,
        'ultimas_5_ventas'    => $ultimasVentas,
        'postgres_version'    => $version,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'status'      => 'error',
        'php_version' => PHP_VERSION,
        'message'     => 'Error al conectar a PostgreSQL: ' . $e->getMessage(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
