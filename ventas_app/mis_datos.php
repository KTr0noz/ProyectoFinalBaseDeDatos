<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/mis_datos.php
 * DESCRIPCIÓN:
 *   El cliente corrige sus propios datos (nombre, apellido, correo, teléfono,
 *   dirección y ciudad) por si quedaron mal al crear la cuenta.
 *   - rol_cliente no tiene UPDATE sobre cliente: el cambio lo hace la función
 *     fn_actualizar_mis_datos (SECURITY DEFINER), que solo toca la fila del
 *     cliente en sesión (app.id_cliente).
 *   - La cédula no se puede cambiar aquí porque es la llave de sus ventas.
 * ============================================================================
 */

require_once 'includes/funciones.php';
require_cliente();

$cliente = usuario_actual();
$errores = array();
$datos   = $cliente;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = array(
        'id_cliente' => (string)$cliente['id_cliente'],
        'nombre'     => post('nombre'),
        'apellido'   => post('apellido'),
        'email'      => mb_strtolower(post('email'), 'UTF-8'),
        'telefono'   => post('telefono'),
        'direccion'  => post('direccion'),
        'id_ciudad'  => post('id_ciudad'),
    );

    if (!csrf_validar()) {
        $errores[] = 'La sesión del formulario expiró. Intente nuevamente.';
    } else {
        validar_datos_cliente($datos, $errores);
    }

    if (!$errores) {
        try {
            consulta(
                'SELECT fn_actualizar_mis_datos(?, ?, ?, ?, ?, ?)',
                array(
                    $datos['nombre'],
                    $datos['apellido'],
                    $datos['email'],
                    $datos['telefono'],
                    $datos['direccion'],
                    (int)$datos['id_ciudad'],
                )
            );
            flash('ok', 'Tus datos se actualizaron.');
            redirigir('mis_datos.php');
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
}

$ciudades = array();
foreach (filas('SELECT id_ciudad, nombre, departamento FROM ciudad ORDER BY nombre') as $c) {
    $ciudades[$c['id_ciudad']] = $c['nombre'] . ' (' . $c['departamento'] . ')';
}

$titulo = 'Mis datos';
$activo = 'mis_datos.php';
include 'includes/encabezado_tienda.php';
?>

<div class="encabezado">
    <div>
        <h1>Mis datos</h1>
        <p class="subtitulo">Corrige tus datos si quedaron mal al crear la cuenta. Usamos la dirección para tus envíos.</p>
    </div>
</div>

<?php mostrar_errores($errores); ?>

<form method="post" class="tarjeta formulario">
    <?= csrf_campo() ?>

    <div>
        <label for="id_cliente">Cédula</label>
        <input id="id_cliente" value="<?= h($cliente['id_cliente']) ?>" disabled>
        <div class="ayuda">No se puede cambiar. Si está mal, comunícate con el administrador.</div>
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
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" maxlength="120" autocomplete="email" required value="<?= h($datos['email']) ?>">
        <div class="ayuda">Es el correo con el que inicias sesión.</div>
    </div>

    <div>
        <label for="telefono">Teléfono</label>
        <input id="telefono" name="telefono" maxlength="20" required value="<?= h($datos['telefono']) ?>">
    </div>

    <div class="completo">
        <label for="direccion">Dirección de entrega</label>
        <input id="direccion" name="direccion" maxlength="150" required value="<?= h($datos['direccion']) ?>">
    </div>

    <div class="completo botones">
        <button class="boton">Guardar cambios</button>
        <a class="boton secundario" href="tienda.php">Cancelar</a>
    </div>
</form>

<?php include 'includes/pie.php'; ?>
