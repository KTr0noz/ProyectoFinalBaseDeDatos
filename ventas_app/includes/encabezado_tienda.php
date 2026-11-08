<?php

$usuario_tienda = usuario_actual();
$unidades_carrito = 0;
if (!empty($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $cant_item) {
        $unidades_carrito += (int)$cant_item;
    }
}

// Nombre del artículo recién agregado: se muestra una sola vez en la ventanita de abajo a la derecha.
$aviso_carrito = isset($_SESSION['aviso_carrito']) ? (string)$_SESSION['aviso_carrito'] : '';
unset($_SESSION['aviso_carrito']);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Tienda · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/tienda.js" defer></script>
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
                <a href="tienda.php"<?= $activo === 'tienda.php' ? ' class="activo" aria-current="page"' : '' ?>>Catálogo</a>
                <a href="carrito.php"<?= $activo === 'carrito.php' ? ' class="activo" aria-current="page"' : '' ?>>
                    Carrito<?php if ($unidades_carrito > 0): ?> <span class="contador-carrito"><?= $unidades_carrito ?></span><?php endif; ?>
                </a>
                <a href="mis_compras.php"<?= $activo === 'mis_compras.php' ? ' class="activo" aria-current="page"' : '' ?>>Mis compras</a>
                <a href="mis_datos.php"<?= $activo === 'mis_datos.php' ? ' class="activo" aria-current="page"' : '' ?>>Mis datos</a>
            </nav>

            <?php if ($usuario_tienda): ?>
                <div class="usuario-tienda">
                    <span class="nombre-usuario-tienda"><?= h($usuario_tienda['nombre'] . ' ' . $usuario_tienda['apellido']) ?></span>
                    <form method="post" action="salir.php">
                        <?= csrf_campo() ?>
                        <button class="boton-riel-salir">Salir</button>
                    </form>
                </div>
            <?php endif; ?>
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

