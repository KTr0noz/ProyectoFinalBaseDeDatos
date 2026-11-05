<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/clientes.php
 * DESCRIPCIÓN:
 *   Módulo de clientes compradores (`cliente`):
 *     - Permite listar, buscar, registrar, editar y eliminar clientes.
 *     - Reglas de integridad en PostgreSQL:
 *         * `id_cliente > 0` (`ck_cliente_id`).
 *         * `email` único (`uq_cliente_email`).
 *         * `ON UPDATE CASCADE` en `fk_venta_cliente`: si se corrige la cédula
 *           de un cliente, sus ventas asociadas se actualizan automáticamente.
 *         * `ON DELETE RESTRICT` en `fk_venta_cliente`: impide eliminar clientes
 *           que ya tienen historial de ventas.
 * ============================================================================
 */

require_once 'includes/funciones.php';
require_admin();

$accion  = get('accion');
$errores = array();
$datos   = array(
    'original'   => '',
    'id_cliente' => '',
    'nombre'     => '',
    'apellido'   => '',
    'email'      => '',
    'telefono'   => '',
    'direccion'  => '',
    'id_ciudad'  => '',
);

/* ============================================================================
 * 1. PROCESAMIENTO DE FORMULARIOS (POST: ELIMINAR, CREAR O EDITAR CLIENTE)
 * ============================================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        flash('error', 'La sesión del formulario expiró. Intente nuevamente.');
        redirigir('clientes.php');
    }

    // 1.1 Eliminar cliente
    if (post('accion') === 'eliminar') {
        $id_eliminar = (int)post('id_cliente');
        $objetivo    = fila('SELECT rol FROM cliente WHERE id_cliente = ?', array($id_eliminar));

        if ($objetivo && $objetivo['rol'] === 'admin') {
            flash('error', 'No se puede eliminar una cuenta de administrador.');
            redirigir('clientes.php');
        }

        try {
            consulta('DELETE FROM cliente WHERE id_cliente = ?', array($id_eliminar));
            flash('ok', 'Cliente eliminado.');
        } catch (PDOException $e) {
            flash('error', mensaje_error_bd($e));
        }
        redirigir('clientes.php');
    }

    // 1.2 Crear o actualizar cliente
    $datos = array(
        'original'   => post('original'),
        'id_cliente' => post('id_cliente'),
        'nombre'     => post('nombre'),
        'apellido'   => post('apellido'),
        'email'      => mb_strtolower(post('email'), 'UTF-8'),
        'telefono'   => post('telefono'),
        'direccion'  => post('direccion'),
        'id_ciudad'  => post('id_ciudad'),
    );

    validar_datos_cliente($datos, $errores);

    if (!$errores) {
        $params = array(
            (int)$datos['id_cliente'],
            $datos['nombre'],
            $datos['apellido'],
            $datos['email'],
            $datos['telefono'],
            $datos['direccion'],
            (int)$datos['id_ciudad'],
        );

        try {
            if ($datos['original'] === '') {
                consulta(
                    'INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    $params
                );
                flash('ok', 'Cliente registrado.');
            } else {
                $params[] = (int)$datos['original'];
                consulta(
                    'UPDATE cliente
                     SET id_cliente = ?, nombre = ?, apellido = ?, email = ?, telefono = ?, direccion = ?, id_ciudad = ?
                     WHERE id_cliente = ?',
                    $params
                );
                flash('ok', 'Cliente actualizado.');
            }
            redirigir('clientes.php');
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
    $datos = fila(
        'SELECT id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad, rol
         FROM cliente
         WHERE id_cliente = ?',
        array((int)get('id'))
    );
    if (!$datos) {
        flash('error', 'El cliente no existe.');
        redirigir('clientes.php');
    }
    $datos['original'] = $datos['id_cliente'];
    $accion = 'formulario';
}

if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$titulo = 'Clientes';
$activo = 'clientes.php';
include 'includes/encabezado.php';

if ($accion === 'formulario'):
    $ciudades = array();
    foreach (filas('SELECT id_ciudad, nombre, departamento FROM ciudad ORDER BY nombre') as $c) {
        $ciudades[$c['id_ciudad']] = $c['nombre'] . ' (' . $c['departamento'] . ')';
    }
?>
    <div class="encabezado">
        <h1><?= $datos['original'] === '' ? 'Nuevo cliente' : 'Editar cliente' ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if (!$ciudades): ?>
        <div class="alerta alerta-info">
            Primero registre al menos una <a href="ciudades.php?accion=nueva">ciudad</a>.
        </div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="original" value="<?= h($datos['original']) ?>">

        <div>
            <label for="id_cliente">Cédula</label>
            <input id="id_cliente" name="id_cliente" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" required value="<?= h($datos['id_cliente']) ?>">
            <div class="ayuda">Solo números, mayor que 0.</div>
        </div>

        <div>
            <label for="id_ciudad">Ciudad</label>
            <select id="id_ciudad" name="id_ciudad" required>
                <option value="">Seleccione…</option>
                <?= opciones($ciudades, $datos['id_ciudad']) ?>
            </select>
        </div>

        <div>
            <label for="nombre">Nombres</label>
            <input id="nombre" name="nombre" maxlength="80" required value="<?= h($datos['nombre']) ?>">
        </div>

        <div>
            <label for="apellido">Apellidos</label>
            <input id="apellido" name="apellido" maxlength="80" required value="<?= h($datos['apellido']) ?>">
        </div>

        <div>
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" maxlength="120" required value="<?= h($datos['email']) ?>">
        </div>

        <div>
            <label for="telefono">Teléfono</label>
            <input id="telefono" name="telefono" maxlength="20" required value="<?= h($datos['telefono']) ?>">
        </div>

        <div class="completo">
            <label for="direccion">Dirección</label>
            <input id="direccion" name="direccion" maxlength="150" required value="<?= h($datos['direccion']) ?>">
        </div>

        <div class="completo botones">
            <button class="boton">Guardar</button>
            <a class="boton secundario" href="clientes.php">Cancelar</a>
        </div>
    </form>

<?php else:
    $q = get('q');
    $lista = filas(
        "SELECT
             c.id_cliente,
             c.nombre,
             c.apellido,
             c.email,
             c.telefono,
             c.direccion,
             c.id_ciudad,
             c.rol,
             ci.nombre AS ciudad,
             (SELECT COUNT(*) FROM venta v WHERE v.id_cliente = c.id_cliente) AS ventas
         FROM cliente c
         JOIN ciudad ci ON ci.id_ciudad = c.id_ciudad
         WHERE c.nombre || ' ' || c.apellido ILIKE ?
            OR c.email ILIKE ?
            OR CAST(c.id_cliente AS TEXT) LIKE ?
         ORDER BY c.apellido, c.nombre",
        array("%$q%", "%$q%", "%$q%")
    );
?>
    <div class="encabezado">
        <div>
            <h1>Clientes</h1>
            <p class="subtitulo">Compradores registrados en el portal.</p>
        </div>
        <a class="boton" href="clientes.php?accion=nuevo">+ Nuevo cliente</a>
    </div>

    <div class="tarjeta">
        <form class="filtros">
            <input name="q" placeholder="Buscar por nombre, cédula o correo" value="<?= h($q) ?>">
            <button class="boton secundario">Buscar</button>
        </form>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Ciudad</th>
                        <th class="num">Ventas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $c): ?>
                        <tr>
                            <td><?= $c['id_cliente'] ?></td>
                            <td>
                                <?= h($c['nombre'] . ' ' . $c['apellido']) ?>
                                <div class="ayuda"><?= h($c['direccion']) ?></div>
                            </td>
                            <td>
                                <?= h($c['email']) ?>
                                <div class="ayuda"><?= h($c['telefono']) ?></div>
                            </td>
                            <td><?= h($c['ciudad']) ?></td>
                            <td class="num">
                                <a href="ventas.php?q=<?= $c['id_cliente'] ?>"><?= $c['ventas'] ?></a>
                            </td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="clientes.php?accion=editar&id=<?= $c['id_cliente'] ?>">Editar</a>
                                <?php if ($c['rol'] !== 'admin'): ?>
                                    <form method="post" onsubmit="return confirm(<?= js('¿Eliminar al cliente ' . $c['nombre'] . ' ' . $c['apellido'] . '?') ?>)">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                                        <button class="boton peligro chico">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr>
                            <td colspan="6" class="vacio">No hay clientes que coincidan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
