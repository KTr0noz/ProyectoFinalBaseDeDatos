<?php
/**
 * Parte de arriba de las páginas del cliente: <head> y barra con el menú de la tienda.
 * Antes de incluirlo, la página define $titulo y $activo.
 */

$menu = array(
    'tienda.php'      => 'Catálogo',
    'carrito.php'     => 'Carrito',
    'mis_compras.php' => 'Mis compras',
    'mis_datos.php'   => 'Mis datos',
);
$usuario          = usuario_actual();
$unidades_carrito = array_sum(array_map('intval', $_SESSION['carrito'] ?? array()));

// Nombre del artículo recién agregado: la ventanita "¿Quieres ir al carrito?" sale una sola vez.
$aviso_carrito = (string)($_SESSION['aviso_carrito'] ?? '');
unset($_SESSION['aviso_carrito']);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Tienda · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/app.js" defer></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app-tienda">
    <header class="barra-tienda">
        <div class="barra-tienda-interior">
            <a class="marca" href="tienda.php">
                <span class="logo">VI</span>
                Ventas por Internet
            </a>

            <nav class="nav-tienda" aria-label="Menú de tienda">
                <?php foreach ($menu as $archivo => $texto): ?>
                    <a href="<?= $archivo ?>"<?= $activo === $archivo ? ' class="activo" aria-current="page"' : '' ?>>
                        <?= $texto ?><?php if ($archivo === 'carrito.php' && $unidades_carrito > 0): ?> <span class="contador-carrito"><?= $unidades_carrito ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="usuario-tienda">
                <span class="nombre-usuario-tienda"><?= h($usuario['nombre'] . ' ' . $usuario['apellido']) ?></span>
                <form method="post" action="salir.php">
                    <?= csrf_campo() ?>
                    <button class="boton-riel-salir">Salir</button>
                </form>
            </div>
        </div>
    </header>

    <div class="columna">
        <main class="contenido contenido-tienda" id="contenido">
            <?php mostrar_flash(); ?>

            <?php if ($aviso_carrito !== ''): ?>
                <div class="aviso-carrito" id="aviso-carrito" role="status">
                    <button type="button" class="aviso-carrito-cerrar" data-cerrar-aviso aria-label="Cerrar">×</button>
                    <p class="aviso-carrito-titulo">¿Quieres ir al carrito?</p>
                    <p class="aviso-carrito-texto"><?= h($aviso_carrito) ?> se agregó al carrito.</p>
                    <a class="boton chico" href="carrito.php">Ir al carrito</a>
                </div>
            <?php endif; ?>
