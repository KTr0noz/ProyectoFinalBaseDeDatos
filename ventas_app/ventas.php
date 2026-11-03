<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/ventas.php
 * DESCRIPCIÓN:
 *   Módulo de órdenes de venta:
 *     - Listado general con búsqueda, filtro por estado y cálculo dinámico (3FN)
 *       de unidades, total de la venta, monto pagado y saldo pendiente.
 *     - Creación del encabezado de una nueva venta (en estado 'Pendiente').
 *     - Eliminación de una venta devolviendo su stock al inventario dentro de
 *       una transacción ACID (salvo si tiene pagos registrados: RESTRICT).
 * ============================================================================
 */

require_once 'includes/funciones.php';

$accion  = get('accion');
$errores = array();
$datos   = array(
    'id_cliente'      => get('cliente'),
    'fecha'           => date('Y-m-d'),
    'estado'          => 'Pendiente',
    'direccion_envio' => '',
);

/* ============================================================================
 * 1. PROCESAMIENTO DE FORMULARIOS (POST: ELIMINAR O CREAR VENTA)
 * ============================================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1.1 Eliminar una orden de venta
    if (post('accion') === 'eliminar') {
        $id  = (int)post('id_venta');
        $pdo = db();

        try {
            // Transacción ACID:
            // - Si la venta no estaba 'Cancelada', devuelve las unidades al stock de cada artículo.
            // - Elimina la venta (detalle_venta se limpia por ON DELETE CASCADE).
            // - Si existen pagos asociados, fk_forma_pago_venta (ON DELETE RESTRICT) aborta la operación.
            $pdo->beginTransaction();

            consulta(
                "UPDATE articulo a
                 SET stock = a.stock + d.cantidad
                 FROM detalle_venta d
                 JOIN venta v ON v.id_venta = d.id_venta
                 WHERE d.id_venta = ?
                   AND d.id_articulo = a.id_articulo
                   AND v.estado <> 'Cancelada'",
                array($id)
            );

            consulta('DELETE FROM venta WHERE id_venta = ?', array($id));
            $pdo->commit();

            flash('ok', "Venta #$id eliminada y su stock devuelto al inventario.");
            redirigir('ventas.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash('error', mensaje_error_bd($e));
            redirigir('venta.php?id=' . $id);
        }
    }

    // 1.2 Crear el encabezado de una venta nueva
    $datos = array(
        'id_cliente'      => post('id_cliente'),
        'fecha'           => post('fecha'),
        'estado'          => 'Pendiente',
        'direccion_envio' => post('direccion_envio'),
    );

    $cliente = fila('SELECT * FROM cliente WHERE id_cliente = ?', array((int)$datos['id_cliente']));
    if (!$cliente) {
        $errores[] = 'Seleccione un cliente.';
    }

    validar_fecha($datos['fecha'], 'Fecha', $errores);
    validar_opcion($datos['estado'], 'Estado', $ESTADOS_VENTA, $errores);
    validar_texto($datos['direccion_envio'], 'Dirección de envío', 150, $errores, false);

    if (!$errores) {
        // Si no se especifica dirección de envío, se toma por defecto la dirección del cliente
        $direccion = $datos['direccion_envio'] !== '' ? $datos['direccion_envio'] : $cliente['direccion'];

        try {
            $id = valor(
                'INSERT INTO venta (fecha, estado, direccion_envio, id_cliente)
                 VALUES (?, ?, ?, ?)
                 RETURNING id_venta',
                array($datos['fecha'], $datos['estado'], $direccion, (int)$datos['id_cliente'])
            );

            flash('ok', "Venta #$id creada. Ahora agregue los artículos.");
            redirigir('venta.php?id=' . $id);
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }

    $accion = 'nueva';
}

/* ============================================================================
 * 2. RENDERIZADO DE VISTAS (FORMULARIO DE NUEVA VENTA O LISTADO)
 * ============================================================================ */

$titulo = 'Ventas';
$activo = 'ventas.php';
include 'includes/encabezado.php';

if ($accion === 'nueva'):
    $clientes = filas('SELECT id_cliente, nombre, apellido, direccion FROM cliente ORDER BY apellido, nombre');
?>
    <div class="encabezado">
        <h1>Nueva venta</h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if (!$clientes): ?>
        <div class="alerta alerta-info">
            Primero registre al menos un <a href="clientes.php?accion=nuevo">cliente</a>.
        </div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario">
        <input type="hidden" name="accion" value="guardar">

        <div class="completo">
            <label for="id_cliente">Cliente</label>
            <select id="id_cliente" name="id_cliente" required>
                <option value="">Seleccione…</option>
                <?php foreach ($clientes as $c): ?>
                    <option
                        value="<?= $c['id_cliente'] ?>"
                        data-direccion="<?= h($c['direccion']) ?>"
                        <?= (string)$c['id_cliente'] === (string)$datos['id_cliente'] ? 'selected' : '' ?>
                    >
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

    <script>
    // Al seleccionar un cliente, completa automáticamente su dirección de envío sugerida
    document.getElementById('id_cliente').addEventListener('change', function () {
        var opcion = this.options[this.selectedIndex];
        var campo  = document.getElementById('direccion_envio');
        if (opcion && opcion.dataset.direccion) {
            campo.value = opcion.dataset.direccion;
        }
    });
    </script>

<?php else:
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

    $sql   = sql_ventas() . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '');
    $lista = filas($sql . ' ORDER BY v.fecha DESC, v.id_venta DESC', $params);
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
                            <td>
                                <?= h($v['cliente']) ?>
                                <div class="ayuda"><?= h($v['ciudad']) ?></div>
                            </td>
                            <td><?= etiqueta_estado($v['estado']) ?></td>
                            <td class="num"><?= $v['unidades'] ?></td>
                            <td class="num"><?= dinero($v['total']) ?></td>
                            <td class="num"><?= dinero($v['pagado']) ?></td>
                            <td class="num <?= $saldo > 0 ? 'stock-bajo' : '' ?>"><?= dinero($saldo) ?></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="venta.php?id=<?= $v['id_venta'] ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr>
                            <td colspan="9" class="vacio">No hay ventas que coincidan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
