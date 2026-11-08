<?php
/**
 * Ventas: lista con búsqueda y filtro por estado, crear una venta nueva y eliminar una venta.
 *   - El total, las unidades y lo pagado se calculan con sql_ventas() (no se guardan: 3FN).
 *   - Una venta nueva empieza Pendiente; los artículos se agregan después en venta.php.
 *   - Al eliminar una venta, sus unidades vuelven al inventario. Si tiene pagos, la base
 *     no la deja borrar (fk_forma_pago_venta ON DELETE RESTRICT) y nada cambia.
 */

require_once 'includes/funciones.php';
require_admin();

$accion  = get('accion');
$errores = array();
$datos   = array('id_cliente' => get('cliente'), 'fecha' => date('Y-m-d'), 'direccion_envio' => '');

// ---------- Formularios enviados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('ventas.php');

    // Eliminar venta (se envía desde venta.php)
    if (post('accion') === 'eliminar') {
        $id = (int)post('id_venta');
        try {
            en_transaccion(function () use ($id) {
                devolver_stock_venta($id);
                consulta('DELETE FROM venta WHERE id_venta = ?', array($id)); // detalle_venta se borra en cascada
            });
            flash('ok', "Venta #$id eliminada y su stock devuelto al inventario.");
            redirigir('ventas.php');
        } catch (PDOException $e) {
            flash('error', mensaje_error_bd($e));
            redirigir('venta.php?id=' . $id);
        }
    }

    // Crear venta nueva
    $datos = array(
        'id_cliente'      => post('id_cliente'),
        'fecha'           => post('fecha'),
        'direccion_envio' => post('direccion_envio'),
    );
    $cliente = fila('SELECT id_cliente, direccion FROM cliente WHERE id_cliente = ?', array((int)$datos['id_cliente']));
    if (!$cliente) {
        $errores[] = 'Seleccione un cliente.';
    }
    validar_fecha($datos['fecha'], 'Fecha', $errores);
    validar_texto($datos['direccion_envio'], 'Dirección de envío', 150, $errores, false);

    if (!$errores) {
        // Sin dirección de envío se usa la dirección del cliente
        $direccion = $datos['direccion_envio'] !== '' ? $datos['direccion_envio'] : $cliente['direccion'];
        try {
            $id = valor(
                "INSERT INTO venta (fecha, estado, direccion_envio, id_cliente) VALUES (?, 'Pendiente', ?, ?) RETURNING id_venta",
                array($datos['fecha'], $direccion, (int)$datos['id_cliente'])
            );
            flash('ok', "Venta #$id creada. Ahora agregue los artículos.");
            redirigir('venta.php?id=' . $id);
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
    $accion = 'nueva';
}

$titulo = 'Ventas';
$activo = 'ventas.php';
include 'includes/encabezado.php';

// ---------- Formulario de venta nueva ----------
if ($accion === 'nueva'):
    $clientes = filas('SELECT id_cliente, nombre, apellido, direccion FROM cliente ORDER BY apellido, nombre');
?>
    <div class="encabezado">
        <h1>Nueva venta</h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if (!$clientes): ?>
        <div class="alerta alerta-info">Primero registre al menos un <a href="clientes.php?accion=nuevo">cliente</a>.</div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario">
        <?= csrf_campo() ?>

        <div class="completo">
            <label for="id_cliente">Cliente</label>
            <!-- js/app.js copia la dirección del cliente elegido en "Dirección de envío" -->
            <select id="id_cliente" name="id_cliente" required data-copiar-direccion="direccion_envio">
                <option value="">Seleccione…</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id_cliente'] ?>" data-direccion="<?= h($c['direccion']) ?>"<?= (string)$c['id_cliente'] === (string)$datos['id_cliente'] ? ' selected' : '' ?>>
                        <?= h($c['apellido'] . ', ' . $c['nombre'] . ' — ' . $c['id_cliente']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="fecha">Fecha de la venta</label>
            <input id="fecha" name="fecha" type="date" required value="<?= h($datos['fecha']) ?>">
        </div>

        <div>
            <label for="estado">Estado</label>
            <input id="estado" value="Pendiente" disabled>
            <div class="ayuda">Toda venta nueva empieza Pendiente.</div>
        </div>

        <div class="completo">
            <label for="direccion_envio">Dirección de envío</label>
            <input id="direccion_envio" name="direccion_envio" maxlength="150" value="<?= h($datos['direccion_envio']) ?>">
            <div class="ayuda">Si la deja vacía se usa la dirección del cliente.</div>
        </div>

        <div class="completo botones">
            <button class="boton">Crear venta y agregar artículos</button>
            <a class="boton secundario" href="ventas.php">Cancelar</a>
        </div>
    </form>

<?php else:
    // ---------- Lista: busca por número de venta, nombre o cédula, y filtra por estado ----------
    $q      = get('q');
    $estado = get('estado');
    $condiciones = array();
    $params      = array();

    if ($q !== '') {
        $condiciones[] = "(c.nombre || ' ' || c.apellido ILIKE ? OR CAST(c.id_cliente AS TEXT) LIKE ? OR CAST(v.id_venta AS TEXT) = ?)";
        array_push($params, "%$q%", "%$q%", $q);
    }
    if ($estado !== '') {
        $condiciones[] = 'v.estado = ?';
        $params[]      = $estado;
    }

    $lista = filas(
        sql_ventas() . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '') . ' ORDER BY v.fecha DESC, v.id_venta DESC',
        $params
    );
?>
    <div class="encabezado">
        <div>
            <h1>Ventas</h1>
            <p class="subtitulo">Seguimiento de pedidos: estado, total y pagos.</p>
        </div>
        <a class="boton" href="ventas.php?accion=nueva">+ Nueva venta</a>
    </div>

    <div class="tarjeta">
        <form class="filtros">
            <input name="q" placeholder="N.º de venta, cliente o cédula" value="<?= h($q) ?>">
            <select name="estado">
                <option value="">Todos los estados</option>
                <?= opciones_simples($ESTADOS_VENTA, $estado) ?>
            </select>
            <button class="boton secundario">Filtrar</button>
        </form>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th class="num">Unid.</th>
                        <th class="num">Total</th>
                        <th class="num">Pagado</th>
                        <th class="num">Saldo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $v): $saldo = $v['total'] - $v['pagado']; ?>
                        <tr>
                            <td><a href="venta.php?id=<?= $v['id_venta'] ?>"><?= $v['id_venta'] ?></a></td>
                            <td><?= fecha($v['fecha']) ?></td>
                            <td><?= h($v['cliente']) ?><div class="ayuda"><?= h($v['ciudad']) ?></div></td>
                            <td><?= etiqueta_estado($v['estado']) ?></td>
                            <td class="num"><?= $v['unidades'] ?></td>
                            <td class="num"><?= dinero($v['total']) ?></td>
                            <td class="num"><?= dinero($v['pagado']) ?></td>
                            <td class="num <?= $saldo > 0 ? 'stock-bajo' : '' ?>"><?= dinero($saldo) ?></td>
                            <td class="acciones"><a class="boton secundario chico" href="venta.php?id=<?= $v['id_venta'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr><td colspan="9" class="vacio">No hay ventas que coincidan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
