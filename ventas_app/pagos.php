<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/pagos.php
 * DESCRIPCIÓN:
 *   Módulo de formas de pago (`forma_pago`):
 *     - Permite listar, filtrar por medio o estado, registrar, editar y eliminar
 *       transacciones de pago asociadas a una venta.
 *     - El disparador `trg_validar_fecha_pago` en PostgreSQL garantiza que
 *       `fecha_pago >= venta.fecha` tanto en INSERT como en UPDATE.
 *     - Al aprobar o modificar pagos, `sincronizar_estado_venta()` actualiza
 *       automáticamente la venta entre 'Pendiente' y 'Pagada'.
 * ============================================================================
 */

require_once 'includes/funciones.php';

$accion = get('accion');
$volver = get('volver', post('volver'));

if ($volver === '' && get('venta') !== '') {
    $volver = 'venta';
}

$errores = array();
$datos   = array(
    'id_pago'    => '',
    'id_venta'   => get('venta'),
    'tipo'       => 'PSE',
    'monto'      => '',
    'fecha_pago' => date('Y-m-d'),
    'estado'     => 'Aprobado',
);

/** Determina la URL de retorno tras guardar o eliminar un pago. */
function destino($volver, $id_venta)
{
    return ($volver === 'venta' && $id_venta)
        ? 'venta.php?id=' . (int)$id_venta
        : 'pagos.php';
}

/* ============================================================================
 * 1. PROCESAMIENTO DE FORMULARIOS (POST: ELIMINAR, CREAR O EDITAR PAGO)
 * ============================================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1.1 Eliminar un pago existente
    if (post('accion') === 'eliminar') {
        $pago = fila(
            'SELECT p.id_venta, v.estado
             FROM forma_pago p
             JOIN venta v ON v.id_venta = p.id_venta
             WHERE p.id_pago = ?',
            array((int)post('id_pago'))
        );

        if (!$pago) {
            flash('error', 'El pago no existe.');
            redirigir('pagos.php');
        }

        if (!in_array($pago['estado'], $VENTA_ACEPTA_PAGOS, true)) {
            flash('error', 'No se puede eliminar el pago: la venta #' . $pago['id_venta'] . ' está ' . $pago['estado'] . '.');
            redirigir(destino($volver, $pago['id_venta']));
        }

        consulta('DELETE FROM forma_pago WHERE id_pago = ?', array((int)post('id_pago')));
        flash('ok', 'Pago eliminado.');

        // Si la venta estaba 'Pagada' y al borrar el pago ya no cubre el total, vuelve a 'Pendiente'
        sincronizar_estado_venta($pago['id_venta']);
        redirigir(destino($volver, $pago['id_venta']));
    }

    // 1.2 Crear o actualizar un pago
    $datos = array(
        'id_pago'    => post('id_pago'),
        'id_venta'   => post('id_venta'),
        'tipo'       => post('tipo'),
        'monto'      => str_replace(',', '.', post('monto')),
        'fecha_pago' => post('fecha_pago'),
        'estado'     => post('estado'),
    );

    $venta = fila(sql_ventas() . ' WHERE v.id_venta = ?', array((int)$datos['id_venta']));
    if (!$venta) {
        $errores[] = 'Seleccione la venta a la que corresponde el pago.';
    }

    validar_opcion($datos['tipo'], 'Medio de pago', $TIPOS_PAGO, $errores);
    validar_dinero($datos['monto'], 'Monto', $errores);
    validar_fecha($datos['fecha_pago'], 'Fecha de pago', $errores);
    validar_opcion($datos['estado'], 'Estado', $ESTADOS_PAGO, $errores);

    // Si es edición, verificar que la venta original también permita cambios
    $anterior = false;
    if ($datos['id_pago'] !== '') {
        $anterior = fila(
            'SELECT p.id_venta, v.estado
             FROM forma_pago p
             JOIN venta v ON v.id_venta = p.id_venta
             WHERE p.id_pago = ?',
            array((int)$datos['id_pago'])
        );

        if (!$anterior) {
            $errores[] = 'El pago no existe.';
        } elseif (!in_array($anterior['estado'], $VENTA_ACEPTA_PAGOS, true)) {
            $errores[] = 'No se puede modificar el pago: la venta #' . $anterior['id_venta'] . ' está ' . $anterior['estado'] . '.';
        }
    }

    // Validar saldo disponible de la venta
    if ($venta && !$errores) {
        if (!in_array($venta['estado'], $VENTA_ACEPTA_PAGOS, true)) {
            $errores[] = 'La venta #' . $venta['id_venta'] . ' está ' . $venta['estado'] . ': ya no recibe pagos.';
        } elseif ((float)$venta['total'] <= 0) {
            $errores[] = 'La venta #' . $venta['id_venta'] . ' no tiene artículos: agréguelos antes de registrar pagos.';
        } elseif ($datos['estado'] !== 'Rechazado' && is_numeric($datos['monto'])) {
            $disponible = (float)$venta['total'] - pagos_comprometidos($venta['id_venta'], (int)$datos['id_pago']);
            if ((float)$datos['monto'] > $disponible + 0.001) {
                $errores[] = 'El monto supera lo que falta por pagar de la venta: máximo ' . dinero(max($disponible, 0)) . '.';
            }
        }
    }

    if (!$errores) {
        $params = array(
            $datos['tipo'],
            $datos['monto'],
            $datos['fecha_pago'],
            $datos['estado'],
            (int)$datos['id_venta'],
        );

        try {
            if ($datos['id_pago'] === '') {
                consulta(
                    'INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta)
                     VALUES (?, ?, ?, ?, ?)',
                    $params
                );
                flash('ok', 'Pago registrado.');
            } else {
                $params[] = (int)$datos['id_pago'];
                consulta(
                    'UPDATE forma_pago
                     SET tipo = ?, monto = ?, fecha_pago = ?, estado = ?, id_venta = ?
                     WHERE id_pago = ?',
                    $params
                );
                flash('ok', 'Pago actualizado.');
            }

            // Actualizar estado de la venta según los pagos aprobados
            sincronizar_estado_venta($datos['id_venta']);
            if ($anterior && (int)$anterior['id_venta'] !== (int)$datos['id_venta']) {
                sincronizar_estado_venta($anterior['id_venta']);
            }

            redirigir(destino($volver, $datos['id_venta']));
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }

    $accion = 'formulario';
}

/* ============================================================================
 * 2. PREPARACIÓN Y RENDERIZADO DE VISTAS
 * ============================================================================ */

if ($accion === 'editar') {
    $datos = fila('SELECT * FROM forma_pago WHERE id_pago = ?', array((int)get('id')));
    if (!$datos) {
        flash('error', 'El pago no existe.');
        redirigir('pagos.php');
    }
    $accion = 'formulario';
}

if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$titulo = 'Pagos';
$activo = 'pagos.php';
include 'includes/encabezado.php';

if ($accion === 'formulario'):
    $ventas        = array();
    $venta_cerrada = false;

    foreach (filas(sql_ventas() . ' ORDER BY v.id_venta DESC') as $v) {
        $abierta = in_array($v['estado'], $VENTA_ACEPTA_PAGOS, true);

        if ((string)$v['id_venta'] === (string)$datos['id_venta'] && !$abierta && $datos['id_pago'] !== '') {
            $venta_cerrada = $v;
        }
        if (!$abierta && (string)$v['id_venta'] !== (string)$datos['id_venta']) {
            continue;
        }

        $ventas[$v['id_venta']] = '#' . $v['id_venta'] . ' — ' . $v['cliente'] . ' — ' . fecha($v['fecha'])
                                . ' — total ' . dinero($v['total']) . ', saldo ' . dinero($v['total'] - $v['pagado']);
    }
?>
    <div class="encabezado">
        <h1><?= $datos['id_pago'] === '' ? 'Registrar pago' : 'Editar pago #' . h($datos['id_pago']) ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if ($venta_cerrada): ?>
        <div class="alerta alerta-info">
            La venta #<?= $venta_cerrada['id_venta'] ?> está <?= h($venta_cerrada['estado']) ?>: este pago ya no se puede modificar ni eliminar.
        </div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id_pago" value="<?= h($datos['id_pago']) ?>">
        <input type="hidden" name="volver" value="<?= h($volver) ?>">

        <div class="completo">
            <label for="id_venta">Venta</label>
            <select id="id_venta" name="id_venta" required>
                <option value="">Seleccione…</option>
                <?= opciones($ventas, $datos['id_venta']) ?>
            </select>
        </div>

        <div>
            <label for="tipo">Medio de pago</label>
            <select id="tipo" name="tipo">
                <?= opciones_simples($TIPOS_PAGO, $datos['tipo']) ?>
            </select>
        </div>

        <div>
            <label for="monto">Monto (COP)</label>
            <input id="monto" name="monto" type="number" min="0.01" step="0.01" required value="<?= h($datos['monto']) ?>">
        </div>

        <div>
            <label for="fecha_pago">Fecha de pago</label>
            <input id="fecha_pago" name="fecha_pago" type="date" required value="<?= h($datos['fecha_pago']) ?>">
            <div class="ayuda">No puede ser anterior a la fecha de la venta.</div>
        </div>

        <div>
            <label for="estado">Estado de la transacción</label>
            <select id="estado" name="estado">
                <?= opciones_simples($ESTADOS_PAGO, $datos['estado']) ?>
            </select>
        </div>

        <div class="completo botones">
            <?php if (!$venta_cerrada): ?>
                <button class="boton">Guardar</button>
            <?php endif; ?>
            <a class="boton secundario" href="<?= h(destino($volver, $datos['id_venta'])) ?>">
                <?= $venta_cerrada ? 'Volver' : 'Cancelar' ?>
            </a>
        </div>
    </form>

    <?php if ($datos['id_pago'] !== '' && !$venta_cerrada): ?>
        <form method="post" onsubmit="return confirm(<?= js('¿Eliminar el pago #' . $datos['id_pago'] . '?') ?>)">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_pago" value="<?= h($datos['id_pago']) ?>">
            <input type="hidden" name="volver" value="<?= h($volver) ?>">
            <button class="boton peligro">Eliminar pago</button>
        </form>
    <?php endif; ?>

<?php else:
    $tipo        = get('tipo');
    $estado      = get('estado');
    $condiciones = array();
    $params      = array();

    if ($tipo !== '') {
        $condiciones[] = 'p.tipo = ?';
        $params[]      = $tipo;
    }
    if ($estado !== '') {
        $condiciones[] = 'p.estado = ?';
        $params[]      = $estado;
    }

    $lista = filas(
        "SELECT p.*, c.nombre || ' ' || c.apellido AS cliente, v.fecha AS fecha_venta
         FROM forma_pago p
         JOIN venta v ON v.id_venta = p.id_venta
         JOIN cliente c ON c.id_cliente = v.id_cliente"
        . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '')
        . ' ORDER BY p.fecha_pago DESC, p.id_pago DESC',
        $params
    );

    $suma = 0;
    foreach ($lista as $p) {
        $suma += $p['monto'];
    }
?>
    <div class="encabezado">
        <div>
            <h1>Pagos</h1>
            <p class="subtitulo">Transacciones por PSE, tarjetas, Efecty y transferencias.</p>
        </div>
        <a class="boton" href="pagos.php?accion=nuevo">+ Registrar pago</a>
    </div>

    <div class="tarjeta">
        <form class="filtros">
            <select name="tipo">
                <option value="">Todos los medios</option>
                <?= opciones_simples($TIPOS_PAGO, $tipo) ?>
            </select>
            <select name="estado">
                <option value="">Todos los estados</option>
                <?= opciones_simples($ESTADOS_PAGO, $estado) ?>
            </select>
            <button class="boton secundario">Filtrar</button>
        </form>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Venta</th>
                        <th>Cliente</th>
                        <th>Medio</th>
                        <th>Estado</th>
                        <th class="num">Monto</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $p): ?>
                        <tr>
                            <td><?= $p['id_pago'] ?></td>
                            <td><?= fecha($p['fecha_pago']) ?></td>
                            <td><a href="venta.php?id=<?= $p['id_venta'] ?>">#<?= $p['id_venta'] ?></a></td>
                            <td><?= h($p['cliente']) ?></td>
                            <td><?= h($p['tipo']) ?></td>
                            <td><?= etiqueta_estado($p['estado']) ?></td>
                            <td class="num"><?= dinero($p['monto']) ?></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="pagos.php?accion=editar&id=<?= $p['id_pago'] ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr>
                            <td colspan="8" class="vacio">No hay pagos que coincidan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($lista): ?>
                    <tfoot>
                        <tr>
                            <td colspan="6">Suma de los pagos listados</td>
                            <td class="num"><?= dinero($suma) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
