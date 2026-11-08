<?php
/**
 * Pagos (tabla forma_pago): listar, filtrar, registrar, editar y eliminar.
 *   - Solo las ventas Pendiente o Pagada reciben o cambian pagos ($VENTA_ACEPTA_PAGOS).
 *   - La suma de pagos no rechazados no puede pasar del total de la venta.
 *   - El disparador trg_validar_fecha_pago no deja una fecha de pago anterior a la venta.
 *   - Después de cada cambio, sincronizar_estado_venta() pasa la venta a Pagada o la regresa a Pendiente.
 *   - Si se abrió desde una venta (volver=venta), al terminar regresa a esa venta.
 */

require_once 'includes/funciones.php';
require_admin();

$accion = get('accion');
$volver = get('volver', post('volver'));
if ($volver === '' && get('venta') !== '') {
    $volver = 'venta';
}

$errores = array();
$datos   = array('id_pago' => '', 'id_venta' => get('venta'), 'tipo' => 'PSE', 'monto' => '',
                 'fecha_pago' => date('Y-m-d'), 'estado' => 'Aprobado');

/** A dónde regresar después de guardar o borrar: la venta o la lista de pagos. */
function destino($volver, $id_venta)
{
    return ($volver === 'venta' && $id_venta) ? 'venta.php?id=' . (int)$id_venta : 'pagos.php';
}

/** Venta a la que pertenece un pago y su estado, o false si el pago no existe. */
function venta_del_pago($id_pago)
{
    return fila('SELECT p.id_venta, v.estado FROM forma_pago p JOIN venta v ON v.id_venta = p.id_venta WHERE p.id_pago = ?',
                array((int)$id_pago));
}

// ---------- Formularios enviados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('pagos.php');

    // 1. Eliminar pago
    if (post('accion') === 'eliminar') {
        $pago = venta_del_pago(post('id_pago'));
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
        sincronizar_estado_venta($pago['id_venta']); // si estaba Pagada y ya no alcanza, vuelve a Pendiente
        redirigir(destino($volver, $pago['id_venta']));
    }

    // 2. Guardar: sin id es nuevo, con id es edición
    $datos = array(
        'id_pago'    => post('id_pago'),
        'id_venta'   => post('id_venta'),
        'tipo'       => post('tipo'),
        'monto'      => str_replace(',', '.', post('monto')),
        'fecha_pago' => post('fecha_pago'),
        'estado'     => post('estado'),
    );

    $venta = buscar_venta($datos['id_venta']);
    if (!$venta) {
        $errores[] = 'Seleccione la venta a la que corresponde el pago.';
    }
    validar_opcion($datos['tipo'], 'Medio de pago', $TIPOS_PAGO, $errores);
    validar_dinero($datos['monto'], 'Monto', $errores);
    validar_fecha($datos['fecha_pago'], 'Fecha de pago', $errores);
    validar_opcion($datos['estado'], 'Estado', $ESTADOS_PAGO, $errores);

    // Al editar, la venta a la que pertenecía el pago también debe aceptar cambios
    $anterior = false;
    if ($datos['id_pago'] !== '') {
        $anterior = venta_del_pago($datos['id_pago']);
        if (!$anterior) {
            $errores[] = 'El pago no existe.';
        } elseif (!in_array($anterior['estado'], $VENTA_ACEPTA_PAGOS, true)) {
            $errores[] = 'No se puede modificar el pago: la venta #' . $anterior['id_venta'] . ' está ' . $anterior['estado'] . '.';
        }
    }

    // La venta debe recibir pagos y el monto no puede pasar de lo que falta por pagar
    if ($venta && !$errores) {
        if (!in_array($venta['estado'], $VENTA_ACEPTA_PAGOS, true)) {
            $errores[] = 'La venta #' . $venta['id_venta'] . ' está ' . $venta['estado'] . ': ya no recibe pagos.';
        } elseif ((float)$venta['total'] <= 0) {
            $errores[] = 'La venta #' . $venta['id_venta'] . ' no tiene artículos: agréguelos antes de registrar pagos.';
        } elseif ($datos['estado'] !== 'Rechazado') {
            $disponible = (float)$venta['total'] - pagos_comprometidos($venta['id_venta'], (int)$datos['id_pago']);
            if ((float)$datos['monto'] > $disponible + 0.001) {
                $errores[] = 'El monto supera lo que falta por pagar de la venta: máximo ' . dinero(max($disponible, 0)) . '.';
            }
        }
    }

    if (!$errores) {
        $params = array($datos['tipo'], $datos['monto'], $datos['fecha_pago'], $datos['estado'], (int)$datos['id_venta']);
        try {
            if ($datos['id_pago'] === '') {
                consulta('INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta) VALUES (?, ?, ?, ?, ?)', $params);
                flash('ok', 'Pago registrado.');
            } else {
                $params[] = (int)$datos['id_pago'];
                consulta('UPDATE forma_pago SET tipo = ?, monto = ?, fecha_pago = ?, estado = ?, id_venta = ? WHERE id_pago = ?', $params);
                flash('ok', 'Pago actualizado.');
            }

            sincronizar_estado_venta($datos['id_venta']);
            if ($anterior && (int)$anterior['id_venta'] !== (int)$datos['id_venta']) {
                sincronizar_estado_venta($anterior['id_venta']); // el pago se movió de venta
            }
            redirigir(destino($volver, $datos['id_venta']));
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
    $accion = 'formulario';
}

if ($accion === 'editar') {
    $datos  = buscar_o_volver('SELECT * FROM forma_pago WHERE id_pago = ?', (int)get('id'), 'El pago no existe.', 'pagos.php');
    $accion = 'formulario';
}
if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$titulo = 'Pagos';
$activo = 'pagos.php';
include 'includes/encabezado.php';

// ---------- Formulario de registrar / editar ----------
if ($accion === 'formulario'):
    // Ventas que se pueden elegir: las que aceptan pagos, más la del pago que se edita.
    // Si esa venta ya está cerrada (Enviada, Entregada o Cancelada), el pago solo se puede ver.
    $ventas        = array();
    $venta_cerrada = false;
    foreach (filas(sql_ventas() . ' ORDER BY v.id_venta DESC') as $v) {
        $abierta     = in_array($v['estado'], $VENTA_ACEPTA_PAGOS, true);
        $es_la_suya  = (string)$v['id_venta'] === (string)$datos['id_venta'];

        if ($es_la_suya && !$abierta && $datos['id_pago'] !== '') {
            $venta_cerrada = $v;
        }
        if ($abierta || $es_la_suya) {
            $ventas[$v['id_venta']] = '#' . $v['id_venta'] . ' — ' . $v['cliente'] . ' — ' . fecha($v['fecha'])
                                    . ' — total ' . dinero($v['total']) . ', saldo ' . dinero($v['total'] - $v['pagado']);
        }
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
        <?= csrf_campo() ?>
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
            <select id="tipo" name="tipo"><?= opciones_simples($TIPOS_PAGO, $datos['tipo']) ?></select>
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
            <select id="estado" name="estado"><?= opciones_simples($ESTADOS_PAGO, $datos['estado']) ?></select>
        </div>

        <div class="completo botones">
            <?php if (!$venta_cerrada): ?>
                <button class="boton">Guardar</button>
            <?php endif; ?>
            <a class="boton secundario" href="<?= h(destino($volver, $datos['id_venta'])) ?>"><?= $venta_cerrada ? 'Volver' : 'Cancelar' ?></a>
        </div>
    </form>

    <?php if ($datos['id_pago'] !== '' && !$venta_cerrada): ?>
        <?= boton_post(array('accion' => 'eliminar', 'id_pago' => $datos['id_pago'], 'volver' => $volver),
                       '¿Eliminar el pago #' . $datos['id_pago'] . '?', 'Eliminar pago', 'boton peligro') ?>
    <?php endif; ?>

<?php else:
    // ---------- Lista con filtros por medio y estado ----------
    $tipo   = get('tipo');
    $estado = get('estado');
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
        "SELECT p.*, c.nombre || ' ' || c.apellido AS cliente
         FROM forma_pago p
         JOIN venta v ON v.id_venta = p.id_venta
         JOIN cliente c ON c.id_cliente = v.id_cliente"
        . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '')
        . ' ORDER BY p.fecha_pago DESC, p.id_pago DESC',
        $params
    );
    $suma = array_sum(array_column($lista, 'monto'));
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
                            <td class="acciones"><a class="boton secundario chico" href="pagos.php?accion=editar&id=<?= $p['id_pago'] ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr><td colspan="8" class="vacio">No hay pagos que coincidan.</td></tr>
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
