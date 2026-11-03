<?php
/**
 * Archivo de conexión a PostgreSQL (Railway)
 */

$defaultRailwayUrl = 'postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway';
$envUrl = getenv('DATABASE_URL');

// Si no hay variable de entorno o se dejó el texto de ejemplo "@host:5432", usar la URL real de Railway
if (!$envUrl || strpos($envUrl, '@host:') !== false) {
    $databaseUrl = $defaultRailwayUrl;
} else {
    $databaseUrl = $envUrl;
}

$dbParts = parse_url($databaseUrl);
$host = $dbParts['host'] ?? 'ballast.proxy.rlwy.net';
$port = $dbParts['port'] ?? 49477;
$user = $dbParts['user'] ?? 'postgres';
$pass = $dbParts['pass'] ?? 'bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ';
$db   = ltrim($dbParts['path'] ?? 'railway', '/');

$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5
]);
