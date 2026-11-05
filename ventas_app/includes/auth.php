<?php

$ruta_env_local = dirname(__DIR__, 2) . '/.env';
if (is_file($ruta_env_local) && is_readable($ruta_env_local)) {
    foreach (file($ruta_env_local, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea_env) {
        $linea_env = trim($linea_env);
        if ($linea_env === '' || strpos($linea_env, '#') === 0 || strpos($linea_env, '=') === false) {
            continue;
        }
        list($clave_env, $valor_env) = explode('=', $linea_env, 2);
        $clave_env = trim($clave_env);
        $valor_env = trim($valor_env);
        if (
            (substr($valor_env, 0, 1) === '"' && substr($valor_env, -1) === '"')
            || (substr($valor_env, 0, 1) === "'" && substr($valor_env, -1) === "'")
        ) {
            $valor_env = substr($valor_env, 1, -1);
        }
        if ($clave_env !== '' && getenv($clave_env) === false) {
            putenv($clave_env . '=' . $valor_env);
        }
    }
}

define('INACTIVIDAD_SESION', 1800);
define('MAX_INTENTOS_LOGIN', 5);
define('VENTANA_INTENTOS_LOGIN', 600);
define('TIEMPO_BLOQUEO_LOGIN', 300);

$ROLES_CLIENTE = array(
    'admin',
    'cliente',
);

function cabeceras_no_cache()
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

function aplicar_rol_bd($rol_forzado = null)
{
    $rol = $rol_forzado !== null
        ? $rol_forzado
        : (isset($_SESSION['rol']) ? $_SESSION['rol'] : 'visitante');

    if ($rol === 'admin') {
        db()->exec('SET ROLE rol_admin');
        consulta("SELECT set_config('app.id_cliente', '', false)");
    } elseif ($rol === 'cliente') {
        db()->exec('SET ROLE rol_cliente');
        $id_sesion = isset($_SESSION['id_cliente']) ? (string)(int)$_SESSION['id_cliente'] : '';
        consulta("SELECT set_config('app.id_cliente', ?, false)", array($id_sesion));
    } else {
        db()->exec('SET ROLE rol_visitante');
        consulta("SELECT set_config('app.id_cliente', '', false)");
    }
}

function iniciar_sesion_usuario($id_cliente, $rol)
{
    session_regenerate_id(true);
    $_SESSION['id_cliente']       = (int)$id_cliente;
    $_SESSION['rol']              = ($rol === 'admin') ? 'admin' : 'cliente';
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['csrf_token']       = bin2hex(random_bytes(32));
}

function cerrar_sesion_usuario()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function verificar_inactividad_sesion()
{
    if (!isset($_SESSION['id_cliente'])) {
        return;
    }

    if (isset($_SESSION['ultima_actividad']) && (time() - (int)$_SESSION['ultima_actividad']) > INACTIVIDAD_SESION) {
        cerrar_sesion_usuario();
        flash('info', 'Su sesión expiró por inactividad. Ingrese nuevamente.');
        redirigir('login.php');
    }

    $_SESSION['ultima_actividad'] = time();
}

function require_login()
{
    cabeceras_no_cache();
    verificar_inactividad_sesion();

    if (empty($_SESSION['id_cliente']) || empty($_SESSION['rol'])) {
        redirigir('login.php');
    }

    aplicar_rol_bd();
}

function require_admin()
{
    require_login();

    if ($_SESSION['rol'] !== 'admin') {
        flash('error', 'No tiene permisos para acceder al panel de administración.');
        redirigir('tienda.php');
    }

    aplicar_rol_bd('admin');
}

function require_cliente()
{
    require_login();

    if ($_SESSION['rol'] !== 'cliente') {
        redirigir('index.php');
    }

    aplicar_rol_bd('cliente');
}

function usuario_actual()
{
    static $cache = null;

    if (empty($_SESSION['id_cliente'])) {
        return null;
    }

    if ($cache !== null && (int)$cache['id_cliente'] === (int)$_SESSION['id_cliente']) {
        return $cache;
    }

    $fila = fila(
        'SELECT id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad
         FROM cliente
         WHERE id_cliente = ?',
        array((int)$_SESSION['id_cliente'])
    );

    if (!$fila) {
        cerrar_sesion_usuario();
        redirigir('login.php');
    }

    $fila['rol'] = $_SESSION['rol'];
    $cache = $fila;
    return $cache;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_campo()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_validar()
{
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !is_string($_POST['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function ruta_intentos_login($email)
{
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $llave = hash('sha256', $ip . '|' . mb_strtolower((string)$email, 'UTF-8'));
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'vi_login_' . $llave . '.json';
}

function login_bloqueado($email)
{
    $archivo = ruta_intentos_login($email);
    if (!is_file($archivo) || !is_readable($archivo)) {
        return false;
    }

    $contenido = file_get_contents($archivo);
    $datos     = is_string($contenido) ? json_decode($contenido, true) : null;
    if (!is_array($datos)) {
        return false;
    }

    $ahora = time();
    if (!empty($datos['bloqueado_hasta']) && (int)$datos['bloqueado_hasta'] > $ahora) {
        return true;
    }

    return false;
}

function registrar_intento_login($email, $exito)
{
    $archivo = ruta_intentos_login($email);

    if ($exito) {
        if (is_file($archivo)) {
            @unlink($archivo);
        }
        return;
    }

    $ahora    = time();
    $intentos = array();

    if (is_file($archivo) && is_readable($archivo)) {
        $contenido = file_get_contents($archivo);
        $previo    = is_string($contenido) ? json_decode($contenido, true) : null;
        if (is_array($previo) && !empty($previo['intentos']) && is_array($previo['intentos'])) {
            foreach ($previo['intentos'] as $ts) {
                if (($ahora - (int)$ts) < VENTANA_INTENTOS_LOGIN) {
                    $intentos[] = (int)$ts;
                }
            }
        }
    }

    $intentos[]      = $ahora;
    $bloqueado_hasta = count($intentos) >= MAX_INTENTOS_LOGIN ? ($ahora + TIEMPO_BLOQUEO_LOGIN) : 0;

    file_put_contents(
        $archivo,
        json_encode(array('intentos' => $intentos, 'bloqueado_hasta' => $bloqueado_hasta)),
        LOCK_EX
    );
}

function validar_clave($clave, $confirmacion, &$errores, $verificar_confirmacion = true)
{
    if ($clave === '') {
        $errores[] = 'La contraseña es obligatoria.';
        return;
    }

    $longitud = mb_strlen($clave, 'UTF-8');
    if ($longitud < 8 || strlen($clave) > 72) {
        $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        return;
    }

    if ($verificar_confirmacion && $clave !== $confirmacion) {
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
