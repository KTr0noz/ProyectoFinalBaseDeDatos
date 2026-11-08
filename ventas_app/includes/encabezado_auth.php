<?php
/**
 * Parte de arriba de las páginas sin sesión (login.php y registro.php).
 * Variables: $titulo y, opcional, $enlace_auth = array('archivo.php', 'Texto') para la esquina derecha.
 */
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="cuerpo-auth">
<a class="saltar" href="#contenido">Saltar al contenido</a>

<header class="barra-auth">
    <div class="barra-auth-interior">
        <a class="marca marca-oscura" href="login.php">
            <span class="logo">VI</span>
            Ventas por Internet
        </a>
        <?php if (!empty($enlace_auth)): ?>
            <a class="enlace-auth" href="<?= h($enlace_auth[0]) ?>"><?= h($enlace_auth[1]) ?></a>
        <?php endif; ?>
    </div>
</header>
