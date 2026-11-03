<?php
/* Encabezado común. Antes de incluirlo, defina $titulo y $activo. */
$menu = array(
    'index.php'      => 'Inicio',
    'ventas.php'     => 'Ventas',
    'pagos.php'      => 'Pagos',
    'articulos.php'  => 'Artículos',
    'categorias.php' => 'Categorías',
    'clientes.php'   => 'Clientes',
    'ciudades.php'   => 'Ciudades',
);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Ventas por Internet</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header class="barra">
    <a class="marca" href="index.php"><span class="logo">VI</span> Ventas por Internet</a>
    <nav>
        <?php foreach ($menu as $archivo => $texto): ?>
            <a href="<?= $archivo ?>" class="<?= $activo === $archivo ? 'activo' : '' ?>"><?= $texto ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main class="contenido">
<?php mostrar_flash(); ?>
