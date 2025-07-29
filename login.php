<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    registrarAcceso(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'already_logged_in_redirect',
        ['redirect_to' => $_SESSION['redirect_url'] ?? 'index.php']
    );
    redirectAfterLogin();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
        registrarAcceso(0, 'GUEST', 'login_failed', [
            'reason' => 'empty_fields',
            'attempted_username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    } elseif (!authenticate($username, $password)) {
        $error = 'Usuario o contraseña incorrectos';
        registrarAcceso(0, $username, 'login_failed', [
            'reason' => 'invalid_credentials',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } else {
        registrarAcceso(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'login_success',
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'login_time' => date('Y-m-d H:i:s')
            ]
        );
        redirectAfterLogin();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroní - MRMC</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="date-ticker">
        <div class="ticker-content">
            <span id="current-date"></span> | Bienvenido al módulo de reportes de movimientos de cuentas del Banco Caroní
        </div>
    </div>

    <div class="login-container">
        <h1 class="bank-header">Banco Caroní</h1>
        <div class="sec-title">MRMC - Módulo de Reportes de Movimientos de Cuentas</div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Ingresar</button>
        </form>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>