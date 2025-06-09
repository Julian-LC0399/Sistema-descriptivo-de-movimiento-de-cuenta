<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

if (isLoggedIn()) {
    redirectAfterLogin();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } elseif (!authenticate($username, $password)) {
        $error = 'Usuario o contraseña incorrectos';
    } else {
        redirectAfterLogin();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroní - SEC</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <!-- Barra superior con fecha animada -->
    <div class="date-ticker">
        <div class="ticker-content">
            <span id="current-date"></span> | Bienvenido al Sistema Estado de Cuenta del Banco Caroní
        </div>
    </div>

    <div class="login-container">
        <h1 class="bank-header">Banco Caroní</h1>
        <div class="sec-title">SEC - Sistema Estado de Cuenta</div>

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