<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/config.php
 * DESCRIPCIÓN:
 *   Configuración central de la conexión a PostgreSQL y parámetros globales.
 *   Soporta tanto despliegue en la nube (Railway mediante DATABASE_URL) como
 *   ejecución local.
 * ============================================================================
 */

// 1. URL de conexión a PostgreSQL en Railway (usada por defecto si no se define otra)
$urlPorDefecto = 'postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway';
$urlEntorno    = getenv('DATABASE_URL');

// Si existe DATABASE_URL válida en el entorno la usamos; de lo contrario, usamos la de Railway
if (!$urlEntorno || strpos($urlEntorno, '@host:') !== false) {
    $databaseUrl = $urlPorDefecto;
} else {
    $databaseUrl = $urlEntorno;
}

// 2. Descomponer la URL (postgresql://usuario:clave@servidor:puerto/base_de_datos)
$partesConexion = parse_url($databaseUrl);

define('DB_HOST', $partesConexion['host'] ?? 'ballast.proxy.rlwy.net');
define('DB_PORT', (string)($partesConexion['port'] ?? '49477'));
define('DB_NAME', ltrim($partesConexion['path'] ?? 'railway', '/'));
define('DB_USER', $partesConexion['user'] ?? 'postgres');
define('DB_PASS', $partesConexion['pass'] ?? 'bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ');

// 3. Umbral de inventario para alertas de stock bajo (<= 5 unidades)
define('STOCK_BAJO', 5);

// 4. Zona horaria oficial de Colombia
date_default_timezone_set('America/Bogota');
