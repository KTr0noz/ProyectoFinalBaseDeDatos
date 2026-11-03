<?php
/* Encabezado común. Antes de incluirlo, defina $titulo y $activo. */
$menu = array(
    'index.php'      => array('texto' => 'Dashboard',  'icono' => '📊'),
    'ventas.php'     => array('texto' => 'Ventas',     'icono' => '🛒'),
    'pagos.php'      => array('texto' => 'Pagos',      'icono' => '💳'),
    'articulos.php'  => array('texto' => 'Artículos',  'icono' => '📦'),
    'categorias.php' => array('texto' => 'Categorías', 'icono' => '🏷️'),
    'clientes.php'   => array('texto' => 'Clientes',   'icono' => '👥'),
    'ciudades.php'   => array('texto' => 'Ciudades',   'icono' => '📍'),
);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Ventas por Internet (Cód. 5465)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header class="barra">
    <div class="barra-interior">
        <a class="marca" href="index.php">
            <span class="logo">VI</span>
            <span class="marca-textos">
                <span class="marca-titulo">Ventas por Internet</span>
                <span class="marca-subtitulo">Proyecto #13 · Cód. 5465</span>
            </span>
        </a>
        <nav>
            <?php foreach ($menu as $archivo => $item): ?>
                <a href="<?= $archivo ?>" class="<?= $activo === $archivo ? 'activo' : '' ?>">
                    <span class="nav-icono"><?= $item['icono'] ?></span>
                    <span><?= $item['texto'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="barra-estado">
            <a href="api.php" target="_blank" class="badge-db" title="Ver estado JSON de la API y PostgreSQL">
                <span class="punto-vivo"></span>
                <span>PostgreSQL Railway</span>
            </a>
        </div>
    </div>
</header>
<main class="contenido">
<?php mostrar_flash(); ?>
