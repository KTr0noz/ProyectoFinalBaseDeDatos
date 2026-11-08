<?php
/**
 * Parte de arriba de las páginas del administrador: <head> y menú lateral.
 * Antes de incluirlo, la página define $titulo (pestaña del navegador) y $activo (archivo del menú resaltado).
 */

$menu = array(
    'Operación'  => array('index.php' => 'Inicio', 'ventas.php' => 'Ventas', 'pagos.php' => 'Pagos'),
    'Catálogo'   => array('articulos.php' => 'Artículos', 'categorias.php' => 'Categorías'),
    'Directorio' => array('clientes.php' => 'Clientes', 'ciudades.php' => 'Ciudades'),
);
$usuario = usuario_actual();
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/app.js" defer></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>

<div class="app">
    <aside class="riel">
        <a class="marca" href="index.php">
            <span class="logo">VI</span>
            Ventas por Internet
        </a>

        <nav aria-label="Menú principal">
            <?php foreach ($menu as $grupo => $paginas): ?>
                <div class="riel-grupo">
                    <h2><?= h($grupo) ?></h2>
                    <?php foreach ($paginas as $archivo => $texto): ?>
                        <a href="<?= $archivo ?>"<?= $activo === $archivo ? ' class="activo" aria-current="page"' : '' ?>><?= h($texto) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="riel-sesion">
            <div class="riel-usuario">
                <span class="riel-nombre"><?= h($usuario['nombre'] . ' ' . $usuario['apellido']) ?></span>
                <span class="riel-correo"><?= h($usuario['email']) ?></span>
            </div>
            <form method="post" action="salir.php">
                <?= csrf_campo() ?>
                <button class="boton-riel-salir">Salir</button>
            </form>
        </div>
    </aside>

    <div class="columna">
        <main class="contenido" id="contenido">
            <?php mostrar_flash(); ?>
