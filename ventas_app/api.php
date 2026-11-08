<?php
/**
 * Estado del sistema en JSON (conexión, registros por tabla y últimas ventas).
 * Solo para el administrador: muestra datos de clientes, así que pide iniciar sesión.
 */

require_once 'includes/funciones.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$conteos = array();
foreach (array('ciudad', 'cliente', 'categoria', 'articulo', 'venta', 'detalle_venta', 'forma_pago') as $tabla) {
    $conteos[$tabla] = (int)valor("SELECT COUNT(*) FROM $tabla");
}

echo json_encode(array(
    'status'              => 'ok',
    'proyecto'            => 'Proyecto #13: Sistema de Ventas por Internet (Cód. 5465)',
    'php_version'         => PHP_VERSION,
    'database'            => 'Conectado exitosamente a PostgreSQL',
    'host'                => DB_HOST,
    'port'                => (int)DB_PORT,
    'dbname'              => DB_NAME,
    'registros_por_tabla' => $conteos,
    'ultimas_5_ventas'    => filas(sql_ventas() . ' ORDER BY v.id_venta DESC LIMIT 5'),
    'postgres_version'    => valor('SELECT version()'),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
