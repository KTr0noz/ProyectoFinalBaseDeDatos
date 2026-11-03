<?php
/*
 * Inicio: resumen general (solo consulta, no registra nada).
 * 1. «Por hacer»: tareas que salen de los estados reales de ventas, pagos e inventario.
 * 2. Embudo: cuántas ventas y cuánto dinero hay en cada estado.
 * 3. Cifras generales y últimas ventas.
 */
require_once 'includes/funciones.php';

$ventas_sql = sql_ventas();

/* ---------- 1. Por hacer ---------- */
$tareas = array();

// Ventas pendientes con saldo: hay que cobrarlas
foreach (filas($ventas_sql . " WHERE v.estado = 'Pendiente' AND COALESCE(t.total, 0) > 0
                                 AND COALESCE(t.total, 0) > COALESCE(p.pagado, 0)
                               ORDER BY v.fecha, v.id_venta") as $v) {
    $saldo = $v['total'] - $v['pagado'];
    $en_espera = (float)valor("SELECT COALESCE(SUM(monto), 0) FROM forma_pago WHERE id_venta = ? AND estado = 'Pendiente'", array($v['id_venta']));
    $tareas[] = array(
        'tipo'    => 'cobrar',
        'que'     => 'Cobrar la venta #' . $v['id_venta'] . ' de ' . $v['cliente'],
        'detalle' => 'Pendiente desde el ' . fecha($v['fecha']) . ', saldo ' . dinero($saldo)
                     . ($en_espera > 0 ? ' (hay ' . dinero($en_espera) . ' en pagos por aprobar)' : ''),
        'enlace'  => 'venta.php?id=' . $v['id_venta'],
        'boton'   => 'Ver venta',
    );
}

// Pagos pendientes de aprobación en ventas que todavía reciben pagos
foreach (filas("SELECT p.id_pago, p.tipo, p.monto, p.fecha_pago, p.id_venta
                FROM forma_pago p JOIN venta v ON v.id_venta = p.id_venta
                WHERE p.estado = 'Pendiente' AND v.estado IN ('Pendiente', 'Pagada')
                ORDER BY p.fecha_pago, p.id_pago") as $p) {
    $tareas[] = array(
        'tipo'    => 'aprobar',
        'que'     => 'Aprobar o rechazar el pago #' . $p['id_pago'] . ' por ' . $p['tipo'],
        'detalle' => dinero($p['monto']) . ' para la venta #' . $p['id_venta'] . ', registrado el ' . fecha($p['fecha_pago']),
        'enlace'  => 'pagos.php?accion=editar&id=' . $p['id_pago'],
        'boton'   => 'Revisar pago',
    );
}

// Ventas pagadas: falta despacharlas
foreach (filas($ventas_sql . " WHERE v.estado = 'Pagada' ORDER BY v.fecha, v.id_venta") as $v) {
    $tareas[] = array(
        'tipo'    => 'despachar',
        'que'     => 'Despachar la venta #' . $v['id_venta'] . ' de ' . $v['cliente'],
        'detalle' => 'Pagada por ' . dinero($v['total']) . '. Enviar a ' . $v['direccion_envio'] . ', ' . $v['ciudad'],
        'enlace'  => 'venta.php?id=' . $v['id_venta'],
        'boton'   => 'Abrir venta',
    );
}

// Ventas enviadas: falta confirmar la entrega
foreach (filas($ventas_sql . " WHERE v.estado = 'Enviada' ORDER BY v.fecha, v.id_venta") as $v) {
    $tareas[] = array(
        'tipo'    => 'entregar',
        'que'     => 'Confirmar la entrega de la venta #' . $v['id_venta'] . ' de ' . $v['cliente'],
        'detalle' => 'En camino a ' . $v['direccion_envio'] . ', ' . $v['ciudad'],
        'enlace'  => 'venta.php?id=' . $v['id_venta'],
        'boton'   => 'Abrir venta',
    );
}

// Ventas pendientes que todavía no tienen artículos
foreach (filas($ventas_sql . " WHERE v.estado = 'Pendiente' AND COALESCE(t.total, 0) = 0 ORDER BY v.fecha, v.id_venta") as $v) {
    $tareas[] = array(
        'tipo'    => 'aprobar',
        'que'     => 'Agregar artículos a la venta #' . $v['id_venta'] . ' de ' . $v['cliente'],
        'detalle' => 'Creada el ' . fecha($v['fecha']) . ' y todavía sin artículos',
        'enlace'  => 'venta.php?id=' . $v['id_venta'],
        'boton'   => 'Ver venta',
    );
}

// Inventario bajo
$stock_bajo = filas(
    'SELECT a.id_articulo, a.nombre, a.stock, c.nombre AS categoria
     FROM articulo a JOIN categoria c ON c.id_categoria = a.id_categoria
     WHERE a.stock <= ? ORDER BY a.stock, a.nombre',
    array(STOCK_BAJO)
);
foreach ($stock_bajo as $a) {
    $tareas[] = array(
        'tipo'    => 'reponer',
        'que'     => 'Reponer ' . $a['nombre'],
        'detalle' => ($a['stock'] == 0 ? 'Sin unidades' : 'Quedan ' . $a['stock'] . ($a['stock'] == 1 ? ' unidad' : ' unidades')) . ', categoría ' . $a['categoria'],
        'enlace'  => 'articulos.php?accion=editar&id=' . $a['id_articulo'],
        'boton'   => 'Editar artículo',
    );
}

/* ---------- 2. Embudo de estados ---------- */
$embudo = array();
foreach ($ESTADOS_VENTA as $e) {
    $embudo[$e] = array('n' => 0, 'monto' => 0);
}
foreach (filas('SELECT estado, COUNT(*) AS n, COALESCE(SUM(total), 0) AS monto FROM (' . $ventas_sql . ') s GROUP BY estado') as $f) {
    $embudo[$f['estado']] = array('n' => (int)$f['n'], 'monto' => (float)$f['monto']);
}
$monto_total = array_sum(array_column($embudo, 'monto'));

/* ---------- 3. Cifras generales ---------- */
$recaudado  = valor("SELECT COALESCE(SUM(monto), 0) FROM forma_pago WHERE estado = 'Aprobado'");
$por_cobrar = valor('SELECT COALESCE(SUM(total - pagado), 0) FROM (' . $ventas_sql . ") s
                     WHERE estado <> 'Cancelada' AND total > pagado");
$clientes   = valor('SELECT COUNT(*) FROM cliente');
$articulos  = valor('SELECT COUNT(*) FROM articulo');
$ultimas    = filas($ventas_sql . ' ORDER BY v.fecha DESC, v.id_venta DESC LIMIT 6');

$titulo = 'Inicio';
$activo = 'index.php';
include 'includes/encabezado.php';
?>
<div class="encabezado">
    <div>
        <h1>Resumen de la tienda</h1>
        <p class="subtitulo">Lo que necesita atención primero, después el estado de las ventas.</p>
    </div>
</div>

<section class="bandeja" aria-labelledby="por-hacer">
    <div class="bandeja-cab">
        <h2 id="por-hacer">Por hacer</h2>
        <span class="etq"><?= count($tareas) ?> <?= count($tareas) === 1 ? 'tarea' : 'tareas' ?></span>
    </div>
    <?php if ($tareas): ?>
        <ul>
            <?php foreach ($tareas as $t): ?>
                <li class="tarea <?= h($t['tipo']) ?>">
                    <span class="franja" aria-hidden="true"></span>
                    <div>
                        <div class="que"><?= h($t['que']) ?></div>
                        <div class="det"><?= h($t['detalle']) ?></div>
                    </div>
                    <a class="boton secundario chico" href="<?= h($t['enlace']) ?>"><?= h($t['boton']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="al-dia">Todo al día: no hay cobros, pagos por aprobar, despachos ni artículos por reponer.</p>
    <?php endif; ?>
</section>

<section class="embudo" aria-labelledby="dinero">
    <h2 id="dinero" class="etq">Dónde está el dinero de las ventas</h2>
    <div class="embudo-barra" role="img" aria-label="<?= h(implode(', ', array_map(function ($e) use ($embudo) {
        return $e . ' ' . dinero($embudo[$e]['monto']);
    }, $ESTADOS_VENTA))) ?>">
        <?php foreach ($embudo as $estado => $d): if ($d['monto'] <= 0) { continue; } ?>
            <span class="<?= clase_estado($estado) ?>" style="flex: <?= round($d['monto'] / max($monto_total, 1) * 1000) ?>"></span>
        <?php endforeach; ?>
    </div>
    <ul class="embudo-pasos">
        <?php foreach ($embudo as $estado => $d): ?>
            <li>
                <a href="ventas.php?estado=<?= urlencode($estado) ?>">
                    <?= etiqueta_estado($estado) ?>
                    <strong><?= $d['n'] ?></strong>
                </a>
                <span><?= dinero($d['monto']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="linea-cifras">
    <span>Recaudado <b><?= dinero($recaudado) ?></b></span>
    <span>Por cobrar <b class="<?= $por_cobrar > 0 ? 'saldo' : '' ?>"><?= dinero($por_cobrar) ?></b></span>
    <span>Clientes <b><?= $clientes ?></b></span>
    <span>Artículos <b><?= $articulos ?></b></span>
    <span>Stock bajo (<?= STOCK_BAJO ?> o menos) <b class="<?= $stock_bajo ? 'stock-bajo' : '' ?>"><?= count($stock_bajo) ?></b></span>
</div>

<section class="tarjeta" aria-labelledby="ultimas">
    <h2 id="ultimas">Últimas ventas</h2>
    <div class="tabla-contenedor">
    <table>
        <thead><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th class="num">Total</th><th class="num">Saldo</th></tr></thead>
        <tbody>
        <?php foreach ($ultimas as $v): $saldo = $v['total'] - $v['pagado']; ?>
            <tr>
                <td><a href="venta.php?id=<?= $v['id_venta'] ?>"><?= $v['id_venta'] ?></a></td>
                <td class="fecha"><?= fecha($v['fecha']) ?></td>
                <td><?= h($v['cliente']) ?></td>
                <td><?= etiqueta_estado($v['estado']) ?></td>
                <td class="num"><?= dinero($v['total']) ?></td>
                <td class="num <?= $saldo > 0 && $v['estado'] !== 'Cancelada' ? 'stock-bajo' : '' ?>"><?= dinero($saldo) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$ultimas): ?><tr><td colspan="6" class="vacio">Aún no hay ventas.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php include 'includes/pie.php'; ?>
