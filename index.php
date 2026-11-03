<?php
require_once 'includes/funciones.php';

// Indicadores generales
$clientes   = valor('SELECT COUNT(*) FROM cliente');
$articulos  = valor('SELECT COUNT(*) FROM articulo');
$ciudades   = valor('SELECT COUNT(*) FROM ciudad');
$ventas     = valor('SELECT COUNT(*) FROM venta');
$pendientes = valor("SELECT COUNT(*) FROM venta WHERE estado = 'Pendiente'");
$entregadas = valor("SELECT COUNT(*) FROM venta WHERE estado = 'Entregada'");
$recaudado  = valor("SELECT COALESCE(SUM(monto), 0) FROM forma_pago WHERE estado = 'Aprobado'");

$ultimas = filas(sql_ventas() . ' ORDER BY v.fecha DESC, v.id_venta DESC LIMIT 8');

$stock_bajo = filas(
    'SELECT a.id_articulo, a.nombre, a.stock, a.precio, c.nombre AS categoria
     FROM articulo a JOIN categoria c ON c.id_categoria = a.id_categoria
     WHERE a.stock <= ? ORDER BY a.stock, a.nombre',
    array(STOCK_BAJO)
);

// Recaudo por medio de pago (consulta 4.3 del proyecto)
$pagos_por_tipo = filas(
    "SELECT tipo, COUNT(id_pago) AS transacciones, SUM(monto) AS total
     FROM forma_pago
     WHERE estado = 'Aprobado'
     GROUP BY tipo
     ORDER BY total DESC"
);

// Top artículos más vendidos
$top_articulos = filas(
    'SELECT a.id_articulo, a.nombre, c.nombre AS categoria, a.precio, a.stock,
            SUM(dv.cantidad) AS unidades_vendidas,
            SUM(dv.cantidad * a.precio) AS ingresos
     FROM detalle_venta dv
     JOIN articulo a ON a.id_articulo = dv.id_articulo
     JOIN categoria c ON c.id_categoria = a.id_categoria
     JOIN venta v ON v.id_venta = dv.id_venta
     WHERE v.estado <> \'Cancelada\'
     GROUP BY a.id_articulo, a.nombre, c.nombre, a.precio, a.stock
     ORDER BY unidades_vendidas DESC, ingresos DESC
     LIMIT 5'
);

$titulo = 'Dashboard';
$activo = 'index.php';
include 'includes/encabezado.php';
?>

<div class="hero-banner">
    <div>
        <span class="hero-etiqueta">Proyecto #13 · Tercera Forma Normal (3FN)</span>
        <h1>Sistema de Ventas por Internet</h1>
        <p>Gestión transaccional en PostgreSQL con control automático de inventario mediante triggers, cálculo dinámico de totales y validación de medios de pago.</p>
    </div>
    <div class="hero-acciones">
        <a href="ventas.php?accion=nueva" class="boton-blanco">+ Registrar Venta</a>
        <a href="articulos.php?accion=nuevo" class="boton-cristal">+ Nuevo Artículo</a>
        <a href="clientes.php?accion=nuevo" class="boton-cristal">+ Nuevo Cliente</a>
    </div>
</div>

<div class="indicadores">
    <div class="indicador kpi-verde">
        <div class="indicador-cabecera">
            <span class="nombre">Total Recaudado (Aprobado)</span>
            <span class="indicador-icono">💰</span>
        </div>
        <div class="numero"><?= dinero($recaudado) ?></div>
        <div class="ayuda"><?= $entregadas ?> órdenes entregadas con éxito</div>
    </div>

    <div class="indicador">
        <div class="indicador-cabecera">
            <span class="nombre">Órdenes de Venta</span>
            <span class="indicador-icono">🛒</span>
        </div>
        <div class="numero"><?= $ventas ?></div>
        <div class="ayuda">Totales calculados dinámicamente (3FN)</div>
    </div>

    <div class="indicador kpi-ambar">
        <div class="indicador-cabecera">
            <span class="nombre">Ventas Pendientes</span>
            <span class="indicador-icono">⏳</span>
        </div>
        <div class="numero"><?= $pendientes ?></div>
        <div class="ayuda">En espera de confirmación de pago</div>
    </div>

    <div class="indicador kpi-morado">
        <div class="indicador-cabecera">
            <span class="nombre">Clientes y Catálogo</span>
            <span class="indicador-icono">📦</span>
        </div>
        <div class="numero"><?= $clientes ?> / <?= $articulos ?></div>
        <div class="ayuda"><?= $clientes ?> clientes en <?= $ciudades ?> ciudades · <?= $articulos ?> artículos</div>
    </div>
</div>

<div class="columnas">
    <section class="tarjeta">
        <h2>
            <span>Últimas órdenes de venta</span>
            <a href="ventas.php" class="boton secundario chico">Ver todas →</a>
        </h2>
        <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th class="num">Total (3FN)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ultimas as $v): ?>
                <tr>
                    <td><a href="venta.php?id=<?= $v['id_venta'] ?>"><strong>#<?= $v['id_venta'] ?></strong></a></td>
                    <td><?= fecha($v['fecha']) ?></td>
                    <td><?= h($v['cliente']) ?></td>
                    <td><?= etiqueta_estado($v['estado']) ?></td>
                    <td class="num"><strong><?= dinero($v['total']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ultimas): ?><tr><td colspan="5" class="vacio">Aún no hay ventas registradas.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <div>
        <section class="tarjeta">
            <h2>
                <span>Recaudo por Forma de Pago</span>
                <a href="pagos.php" class="boton secundario chico">Ver pagos</a>
            </h2>
            <?php if ($pagos_por_tipo): ?>
                <?php foreach ($pagos_por_tipo as $p):
                    $porcentaje = $recaudado > 0 ? round(($p['total'] / $recaudado) * 100) : 0;
                ?>
                <div class="barra-pago-fila">
                    <div class="barra-pago-encabezado">
                        <span><?= h($p['tipo']) ?> <span class="ayuda">(<?= $p['transacciones'] ?>)</span></span>
                        <span><?= dinero($p['total']) ?> (<?= $porcentaje ?>%)</span>
                    </div>
                    <div class="barra-pago-pista">
                        <div class="barra-pago-relleno" style="width: <?= max(6, $porcentaje) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vacio">Sin pagos aprobados registrados.</div>
            <?php endif; ?>
        </section>

        <section class="tarjeta">
            <h2>
                <span>Alertas de Stock Bajo (≤ <?= STOCK_BAJO ?> uds)</span>
                <a href="articulos.php" class="boton secundario chico">Inventario</a>
            </h2>
            <table>
                <thead><tr><th>Artículo</th><th class="num">Precio</th><th class="num">Stock</th></tr></thead>
                <tbody>
                <?php foreach ($stock_bajo as $a): ?>
                    <tr>
                        <td>
                            <a href="articulos.php?accion=editar&id=<?= $a['id_articulo'] ?>"><?= h($a['nombre']) ?></a>
                            <div class="ayuda"><?= h($a['categoria']) ?></div>
                        </td>
                        <td class="num"><?= dinero($a['precio']) ?></td>
                        <td class="num"><span class="stock-bajo"><?= $a['stock'] ?> uds</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$stock_bajo): ?><tr><td colspan="3" class="vacio">Todos los artículos tienen stock suficiente.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<section class="tarjeta">
    <h2>
        <span>Top 5 Artículos Más Vendidos</span>
        <a href="articulos.php" class="boton secundario chico">Catálogo completo →</a>
    </h2>
    <div class="tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Categoría</th>
                <th class="num">Precio Unitario</th>
                <th class="num">Unidades Vendidas</th>
                <th class="num">Stock Disponible</th>
                <th class="num">Ingresos Generados</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($top_articulos as $top): ?>
            <tr>
                <td><a href="articulos.php?accion=editar&id=<?= $top['id_articulo'] ?>"><strong><?= h($top['nombre']) ?></strong></a></td>
                <td><span class="etiqueta gris"><?= h($top['categoria']) ?></span></td>
                <td class="num"><?= dinero($top['precio']) ?></td>
                <td class="num"><strong><?= $top['unidades_vendidas'] ?> uds</strong></td>
                <td class="num"><?= $top['stock'] <= STOCK_BAJO ? '<span class="stock-bajo">' . $top['stock'] . '</span>' : $top['stock'] ?></td>
                <td class="num"><strong><?= dinero($top['ingresos']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<?php include 'includes/pie.php'; ?>
