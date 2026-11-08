<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/imagen.php
 * DESCRIPCIÓN:
 *   Entrega la imagen de un artículo guardada en articulo_imagen (BYTEA).
 *   Uso: <img src="imagen.php?id=5&v=...">. Solo con sesión iniciada.
 * ============================================================================
 */

require_once 'includes/funciones.php';
require_login();

$imagen = fila(
    'SELECT tipo, datos FROM articulo_imagen WHERE id_articulo = ?',
    array((int)get('id'))
);

if (!$imagen) {
    http_response_code(404);
    exit;
}

// pdo_pgsql devuelve BYTEA como un flujo (stream): se lee completo.
$datos = is_resource($imagen['datos']) ? stream_get_contents($imagen['datos']) : $imagen['datos'];

// require_login() envía "no-cache"; la imagen sí se puede guardar en el navegador,
// porque la URL cambia (&v=) cada vez que se reemplaza.
header_remove('Pragma');
header_remove('Expires');
header('Cache-Control: private, max-age=604800');
header('Content-Type: ' . $imagen['tipo']);
header('Content-Length: ' . strlen($datos));
header('X-Content-Type-Options: nosniff');
echo $datos;
