<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/db.php';

    $version = $pdo->query('SELECT version()')->fetchColumn();

    $tablas = ['ciudad', 'cliente', 'categoria', 'articulo', 'venta', 'detalle_venta', 'forma_pago'];
    $conteos = [];
    foreach ($tablas as $tabla) {
        $conteos[$tabla] = (int) $pdo->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
    }

    $ultimasVentas = $pdo->query("
        SELECT
            v.id_venta,
            v.fecha,
            c.nombre || ' ' || c.apellido AS cliente,
            ciu.nombre AS ciudad,
            v.estado AS estado_venta,
            SUM(dv.cantidad * a.precio) AS total_calculado,
            fp.tipo AS forma_pago,
            fp.estado AS estado_pago
        FROM venta v
        INNER JOIN cliente c ON v.id_cliente = c.id_cliente
        INNER JOIN ciudad ciu ON c.id_ciudad = ciu.id_ciudad
        INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
        INNER JOIN articulo a ON dv.id_articulo = a.id_articulo
        LEFT JOIN forma_pago fp ON v.id_venta = fp.id_venta
        GROUP BY v.id_venta, v.fecha, c.nombre, c.apellido, ciu.nombre, v.estado, fp.tipo, fp.estado
        ORDER BY v.id_venta DESC
        LIMIT 5
    ")->fetchAll();

    echo json_encode([
        'status' => 'ok',
        'proyecto' => 'Proyecto #13: Sistema de Ventas por Internet (Cód. 5465)',
        'php_version' => PHP_VERSION,
        'database' => 'Conectado exitosamente a PostgreSQL en Railway',
        'host' => $host,
        'port' => $port,
        'dbname' => $db,
        'registros_por_tabla' => $conteos,
        'ultimas_5_ventas' => $ultimasVentas,
        'postgres_version' => $version
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'php_version' => PHP_VERSION,
        'message' => 'Error al conectar a PostgreSQL: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
