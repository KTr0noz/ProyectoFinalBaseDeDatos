<?php
/**
 * Inicio de sesión con correo y contraseña. Lleva al panel (admin) o a la tienda (cliente).
 *   - Las contraseñas se guardan con password_hash (bcrypt) y se revisan con password_verify.
 *   - Si el correo no existe se compara igual contra un hash de relleno, para que la respuesta
 *     tarde lo mismo y no se pueda adivinar qué correos están registrados.
 *   - 5 intentos fallidos bloquean el correo 5 minutos (auth.php).
 */

require_once 'includes/funciones.php';
solo_visitantes();

$errores = array();
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(post('email'), 'UTF-8');
    $clave = (string)($_POST['clave'] ?? '');

    if (!csrf_validar()) {
        $errores[] = 'La sesión del formulario expiró. Intente nuevamente.';
    } elseif (login_bloqueado($email)) {
        usleep(250000);
        $errores[] = 'Demasiados intentos fallidos. Espere 5 minutos antes de intentar nuevamente.';
    } else {
        $cliente = $email === '' ? false
            : fila('SELECT id_cliente, password_hash, rol FROM cliente WHERE LOWER(email) = ?', array($email));
        $tiene_clave = $cliente && !empty($cliente['password_hash']);

        $hash_relleno = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $clave_ok = $clave !== '' && strlen($clave) <= 72
            && password_verify($clave, $tiene_clave ? $cliente['password_hash'] : $hash_relleno);

        if ($tiene_clave && $clave_ok) {
            registrar_intento_login($email, true);
            iniciar_sesion_usuario($cliente['id_cliente'], $cliente['rol']);
            redirigir(pagina_inicio($cliente['rol']));
        }

        registrar_intento_login($email, false);
        usleep(250000);
        $errores[] = 'Correo o contraseña incorrectos.';
    }
}

$titulo = 'Ingresar';
include 'includes/encabezado_auth.php';
?>
<main class="contenedor-auth" id="contenido">
    <section class="panel-auth" aria-labelledby="titulo-login">
        <div class="cabecera-auth">
            <h1 id="titulo-login">Ingresa a tu cuenta</h1>
            <p class="subtitulo">Escribe tu correo y contraseña para comprar o administrar.</p>
        </div>

        <?php mostrar_flash(); ?>
        <?php mostrar_errores($errores); ?>

        <form method="post" class="formulario-auth">
            <?= csrf_campo() ?>

            <div>
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" maxlength="120" autocomplete="email" required autofocus value="<?= h($email) ?>">
            </div>

            <div>
                <label for="clave">Contraseña</label>
                <input id="clave" name="clave" type="password" minlength="8" maxlength="72" autocomplete="current-password" required>
            </div>

            <div class="botones-auth">
                <button class="boton boton-completo">Continuar</button>
                <a class="boton secundario boton-completo" href="registro.php">Crear cuenta</a>
            </div>
        </form>
    </section>
</main>
<?php include 'includes/pie_auth.php'; ?>
