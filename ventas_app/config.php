<?php
/**
 * A qué base de datos se conecta la página.
 *
 * Usa la variable de entorno DATABASE_URL (Railway la define). Si no existe, usa la
 * base de Railway escrita abajo. Para correrla en un computador con la base local:
 *     DATABASE_URL=postgresql://app_ventas:ventas2026@localhost:5432/proyecto php -S localhost:8000
 */

$url_por_defecto = 'postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway';
$url = getenv('DATABASE_URL');

// "@host:" aparece en la URL de ejemplo sin completar: en ese caso también se usa la de Railway.
if (!$url || strpos($url, '@host:') !== false) {
    $url = $url_por_defecto;
}

// postgresql://usuario:clave@servidor:puerto/base  ->  sus partes
$partes = parse_url($url);
define('DB_HOST', $partes['host']);
define('DB_PORT', (string)($partes['port'] ?? 5432));
define('DB_NAME', ltrim($partes['path'], '/'));
define('DB_USER', $partes['user']);
define('DB_PASS', $partes['pass'] ?? '');

// Desde cuántas unidades un artículo se marca con "stock bajo".
define('STOCK_BAJO', 5);

date_default_timezone_set('America/Bogota');
