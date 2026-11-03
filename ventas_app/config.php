<?php
/*
 * Configuración de la conexión a PostgreSQL (Railway / Docker).
 * Toma DATABASE_URL del entorno o usa por defecto la instancia de Railway.
 */
$defaultRailwayUrl = 'postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway';
$envUrl = getenv('DATABASE_URL');

if (!$envUrl || strpos($envUrl, '@host:') !== false) {
    $databaseUrl = $defaultRailwayUrl;
} else {
    $databaseUrl = $envUrl;
}

$dbParts = parse_url($databaseUrl);

define('DB_HOST', $dbParts['host'] ?? 'ballast.proxy.rlwy.net');
define('DB_PORT', (string)($dbParts['port'] ?? '49477'));
define('DB_NAME', ltrim($dbParts['path'] ?? 'railway', '/'));
define('DB_USER', $dbParts['user'] ?? 'postgres');
define('DB_PASS', $dbParts['pass'] ?? 'bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ');

// Unidades a partir de las cuales un artículo se considera con stock bajo
define('STOCK_BAJO', 5);

date_default_timezone_set('America/Bogota');
