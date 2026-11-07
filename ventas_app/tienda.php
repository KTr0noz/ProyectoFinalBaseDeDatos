<?php

require_once 'includes/funciones.php';
require_cliente();

$q   = get('q');
$cat = get('categoria');

$categorias = array();
foreach (filas('SELECT id_categoria, nombre FROM categoria ORDER BY nombre') as $c) {
    $categorias[$c['id_categoria']] = $c['nombre'];
}

$sql = 'SELECT
            a.id_articulo,
            a.nombre,
            a.descripcion,
            a.precio,
            a.stock,
            a.id_categoria,
            c.nombre AS categoria
        FROM articulo a
        JOIN categoria c ON c.id_categoria = a.id_categoria
        WHERE a.stock > 0
          AND (a.nombre ILIKE ? OR COALESCE(a.descripcion, \'\') ILIKE ?)';

$params = array("%$q%", "%$q%");

if ($cat !== '') {
    $sql .= ' AND a.id_categoria = ?';
    $params[] = (int)$cat;
}

$articulos = filas($sql . ' ORDER BY a.nombre', $params);

$filtros_actuales = array_filter(array('q' => $q, 'categoria' => $cat), 'strlen');
$volver = 'tienda.php' . ($filtros_actuales ? '?' . http_build_query($filtros_actuales) : '');

$titulo = 'Tienda';
$activo = 'tienda.php';
include 'includes/encabezado_tienda.php';
?>

<div class="encabezado">
    <div>
        <h1>Catálogo de artículos</h1>
        <p class="subtitulo">Artículos con disponibilidad inmediata para despacho.</p>
    </div>
</div>

<form class="filtros" method="get" action="tienda.php">
    <input name="q" placeholder="Buscar artículo por nombre o descripción" value="<?= h($q) ?>">
    <select name="categoria">
        <option value="">Todas las categorías</option>
        <?= opciones($categorias, $cat) ?>
    </select>
    <button class="boton secundario">Filtrar</button>
    <?php if ($q !== '' || $cat !== ''): ?>
        <a class="boton secundario" href="tienda.php">Limpiar</a>
    <?php endif; ?>
</form>

<?php if ($articulos): ?>
    <div class="grilla-tienda">
        <?php foreach ($articulos as $a): ?>
            <article class="tarjeta-articulo">
                <div class="tarjeta-articulo-cuerpo">
                    <div class="etq"><?= h($a['categoria']) ?></div>
                    <h2 class="tarjeta-articulo-titulo">
                        <a href="articulo.php?id=<?= $a['id_articulo'] ?>"><?= h($a['nombre']) ?></a>
                    </h2>
                    <?php if (!empty($a['descripcion'])): ?>
                        <p class="tarjeta-articulo-desc"><?= h($a['descripcion']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="tarjeta-articulo-pie">
                    <div class="precio-tienda"><?= dinero($a['precio']) ?></div>

                    <div class="disponibilidad-tienda">
                        <?php if ($a['stock'] <= STOCK_BAJO): ?>
                            <span class="stock-bajo">
                                ¡Pocas unidades! Quedan <?= $a['stock'] ?>
                            </span>
                        <?php else: ?>
                            <span class="ayuda">Disponibles: <?= $a['stock'] ?> unidades</span>
                        <?php endif; ?>
                    </div>

                    <div class="acciones-tarjeta">
                        <a class="boton secundario chico" href="articulo.php?id=<?= $a['id_articulo'] ?>">Ver ficha</a>
                        <form method="post" action="carrito.php">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="id_articulo" value="<?= $a['id_articulo'] ?>">
                            <input type="hidden" name="cantidad" value="1">
                            <input type="hidden" name="volver" value="<?= h($volver) ?>">
                            <button class="boton chico">Agregar al carrito</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="vacio-tienda">
        No hay artículos disponibles que coincidan con tu búsqueda.
    </div>
<?php endif; ?>

<?php include 'includes/pie.php'; ?>
