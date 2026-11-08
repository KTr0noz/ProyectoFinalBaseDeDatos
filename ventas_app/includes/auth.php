<?php
/**
 * Inicio de sesión, roles y seguridad de formularios.
 *
 *   - Cada usuario de la página es una fila de "cliente" con rol 'cliente' o 'admin'.
 *   - Antes de consultar la base se hace SET ROLE al rol de PostgreSQL que corresponde
 *     (rol_visitante, rol_cliente o rol_admin). Así es la base la que limita qué puede
 *     ver y cambiar cada uno (permisos y RLS de login_roles.sql).
 *   - CSRF: cada formulario lleva un token secreto de la sesión que se revisa al enviarlo.
 *   - Fuerza bruta: tras 5 claves malas en 10 minutos, el correo se bloquea 5 minutos.
 */

// Archivo .env opcional en la carpeta del proyecto (solo para correrlo en un computador).
// Cada línea "NOMBRE=valor" se vuelve variable de entorno si no existía ya.
$ruta_env = dirname(__DIR__, 2) . '/.env';
if (is_readable($ruta_env)) {
    foreach (file($ruta_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        list($nombre, $valor) = array_map('trim', explode('=', $linea, 2));
        $valor = preg_replace('/^(["\'])(.*)\1$/', '$2', $valor); // quita comillas
        if ($nombre !== '' && getenv($nombre) === false) {
            putenv("$nombre=$valor");
        }
    }
}

define('INACTIVIDAD_SESION', 1800);    // 30 min sin actividad cierran la sesión
define('MAX_INTENTOS_LOGIN', 5);
define('VENTANA_INTENTOS_LOGIN', 600); // los intentos se cuentan en 10 min
define('TIEMPO_BLOQUEO_LOGIN', 300);   // bloqueo de 5 min

/* ============================================================================
 * SESIÓN Y ROLES
 * ============================================================================ */

/** Evita que el navegador guarde páginas privadas (botón Atrás después de salir). */
function cabeceras_no_cache()
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

/**
 * Cambia el rol de PostgreSQL de la conexión según quién usa la página.
 * app.id_cliente es la cédula en sesión: la usan las políticas RLS y fn_actualizar_mis_datos.
 */
function aplicar_rol_bd($rol = null)
{
    $rol = $rol ?? ($_SESSION['rol'] ?? 'visitante');
    $id_cliente = ($rol === 'cliente') ? (string)(int)($_SESSION['id_cliente'] ?? 0) : '';

    $roles_bd = array('admin' => 'rol_admin', 'cliente' => 'rol_cliente');
    db()->exec('SET ROLE ' . ($roles_bd[$rol] ?? 'rol_visitante'));
    consulta("SELECT set_config('app.id_cliente', ?, false)", array($id_cliente === '0' ? '' : $id_cliente));
}

function iniciar_sesion_usuario($id_cliente, $rol)
{
    session_regenerate_id(true); // nuevo id de sesión al entrar (evita robo de sesión)
    $_SESSION['id_cliente']       = (int)$id_cliente;
    $_SESSION['rol']              = ($rol === 'admin') ? 'admin' : 'cliente';
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['csrf_token']       = bin2hex(random_bytes(32));
}

function cerrar_sesion_usuario()
{
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/** Cierra la sesión si pasaron más de 30 minutos sin actividad. */
function verificar_inactividad_sesion()
{
    if (!isset($_SESSION['id_cliente'])) {
        return;
    }
    if (time() - (int)($_SESSION['ultima_actividad'] ?? time()) > INACTIVIDAD_SESION) {
        cerrar_sesion_usuario();
        flash('info', 'Su sesión expiró por inactividad. Ingrese nuevamente.');
        redirigir('login.php');
    }
    $_SESSION['ultima_actividad'] = time();
}

/** Página para cualquier usuario con sesión iniciada. */
function require_login()
{
    cabeceras_no_cache();
    verificar_inactividad_sesion();

    if (empty($_SESSION['id_cliente']) || empty($_SESSION['rol'])) {
        redirigir('login.php');
    }
    aplicar_rol_bd();
}

/** Página solo para el administrador; un cliente vuelve a la tienda. */
function require_admin()
{
    require_login();
    if ($_SESSION['rol'] !== 'admin') {
        flash('error', 'No tiene permisos para acceder al panel de administración.');
        redirigir('tienda.php');
    }
}

/** Página solo para clientes; el administrador va a su panel. */
function require_cliente()
{
    require_login();
    if ($_SESSION['rol'] !== 'cliente') {
        redirigir('index.php');
    }
}

/** Página pública (login y registro): si ya hay sesión, va a la página de su rol. */
function solo_visitantes()
{
    cabeceras_no_cache();
    verificar_inactividad_sesion();

    if (!empty($_SESSION['id_cliente']) && !empty($_SESSION['rol'])) {
        redirigir(pagina_inicio($_SESSION['rol']));
    }
    aplicar_rol_bd('visitante');
}

/** Primera página de cada rol. */
function pagina_inicio($rol)
{
    return $rol === 'admin' ? 'index.php' : 'tienda.php';
}

/** Datos del usuario en sesión (se consultan una sola vez por página). */
function usuario_actual()
{
    static $usuario = null;

    if (empty($_SESSION['id_cliente'])) {
        return null;
    }
    if ($usuario === null || (int)$usuario['id_cliente'] !== (int)$_SESSION['id_cliente']) {
        $usuario = fila(
            'SELECT id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad
             FROM cliente WHERE id_cliente = ?',
            array((int)$_SESSION['id_cliente'])
        );
        if (!$usuario) { // la cuenta ya no existe
            cerrar_sesion_usuario();
            redirigir('login.php');
        }
        $usuario['rol'] = $_SESSION['rol'];
    }
    return $usuario;
}

/* ============================================================================
 * CSRF (token secreto en cada formulario)
 * ============================================================================ */

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto que va dentro de cada <form method="post">. */
function csrf_campo()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_validar()
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* ============================================================================
 * BLOQUEO POR INTENTOS FALLIDOS
 * Se guarda un archivo pequeño por correo + IP en la carpeta temporal del servidor.
 * ============================================================================ */

function ruta_intentos_login($email)
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'vi_login_' . hash('sha256', $ip . '|' . mb_strtolower((string)$email, 'UTF-8')) . '.json';
}

function leer_intentos_login($email)
{
    $archivo = ruta_intentos_login($email);
    $datos = is_readable($archivo) ? json_decode((string)file_get_contents($archivo), true) : null;
    return is_array($datos) ? $datos : array('intentos' => array(), 'bloqueado_hasta' => 0);
}

function login_bloqueado($email)
{
    return (int)(leer_intentos_login($email)['bloqueado_hasta'] ?? 0) > time();
}

function registrar_intento_login($email, $exito)
{
    $archivo = ruta_intentos_login($email);

    if ($exito) {
        @unlink($archivo);
        return;
    }

    // Solo cuentan los intentos de los últimos 10 minutos
    $ahora    = time();
    $intentos = array();
    foreach (leer_intentos_login($email)['intentos'] ?? array() as $momento) {
        if ($ahora - (int)$momento < VENTANA_INTENTOS_LOGIN) {
            $intentos[] = (int)$momento;
        }
    }
    $intentos[] = $ahora;

    $bloqueado_hasta = count($intentos) >= MAX_INTENTOS_LOGIN ? $ahora + TIEMPO_BLOQUEO_LOGIN : 0;
    file_put_contents($archivo, json_encode(array('intentos' => $intentos, 'bloqueado_hasta' => $bloqueado_hasta)), LOCK_EX);
}

/* ============================================================================
 * VALIDACIONES DE CUENTA (registro, clientes y Mis datos)
 * ============================================================================ */

function validar_clave($clave, $confirmacion, &$errores, $verificar_confirmacion = true)
{
    if ($clave === '') {
        $errores[] = 'La contraseña es obligatoria.';
    } elseif (mb_strlen($clave, 'UTF-8') < 8 || strlen($clave) > 72) { // bcrypt usa máximo 72 bytes
        $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
    } elseif ($verificar_confirmacion && $clave !== $confirmacion) {
        $errores[] = 'La confirmación de la contraseña no coincide.';
    }
}

function validar_datos_cliente($datos, &$errores)
{
    validar_entero($datos['id_cliente'], 'Cédula', $errores);
    validar_texto($datos['nombre'], 'Nombre', 80, $errores);
    validar_texto($datos['apellido'], 'Apellido', 80, $errores);
    validar_texto($datos['email'], 'Correo', 120, $errores);
    validar_email($datos['email'], 'Correo', $errores);
    validar_texto($datos['telefono'], 'Teléfono', 20, $errores);
    validar_patron(
        $datos['telefono'],
        'Teléfono',
        '/^[0-9+() -]{7,20}$/',
        'solo números, espacios, +, ( ) o guiones (mínimo 7).',
        $errores
    );
    validar_texto($datos['direccion'], 'Dirección', 150, $errores);

    if (!fila('SELECT 1 FROM ciudad WHERE id_ciudad = ?', array((int)$datos['id_ciudad']))) {
        $errores[] = 'Seleccione una ciudad.';
    }
}

/** Lee del formulario los datos de un cliente (registro, clientes.php y Mis datos). */
function datos_cliente_del_formulario()
{
    return array(
        'id_cliente' => post('id_cliente'),
        'nombre'     => post('nombre'),
        'apellido'   => post('apellido'),
        'email'      => mb_strtolower(post('email'), 'UTF-8'),
        'telefono'   => post('telefono'),
        'direccion'  => post('direccion'),
        'id_ciudad'  => post('id_ciudad'),
    );
}
