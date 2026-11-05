<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/includes/encabezado.php
 * DESCRIPCIÓN:
 *   Estructura HTML superior y menú lateral de navegación agrupado por módulos.
 *   Variables requeridas antes de incluirlo: $titulo y $activo.
 * ============================================================================
 */

$menu = array(
    'Operación' => array(
        'index.php'  => 'Inicio',
        'ventas.php' => 'Ventas',
        'pagos.php'  => 'Pagos',
    ),
    'Catálogo' => array(
        'articulos.php'  => 'Artículos',
        'categorias.php' => 'Categorías',
    ),
    'Directorio' => array(
        'clientes.php' => 'Clientes',
        'ciudades.php' => 'Ciudades',
    ),
);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
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
                        <a href="<?= $archivo ?>"<?= $activo === $archivo ? ' class="activo" aria-current="page"' : '' ?>>
                            <?= h($texto) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <?php $usuario_sesion = usuario_actual(); if ($usuario_sesion): ?>
            <div class="riel-sesion">
                <div class="riel-usuario">
                    <span class="riel-nombre"><?= h($usuario_sesion['nombre'] . ' ' . $usuario_sesion['apellido']) ?></span>
                    <span class="riel-correo"><?= h($usuario_sesion['email']) ?></span>
                </div>
                <form method="post" action="salir.php">
                    <?= csrf_campo() ?>
                    <button class="boton-riel-salir">Salir</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>

    <div class="columna">
        <main class="contenido" id="contenido">
            <?php mostrar_flash(); ?>
