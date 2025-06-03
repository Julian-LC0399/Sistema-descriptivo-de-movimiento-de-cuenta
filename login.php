<?php
require_once __DIR__ . '/includes/database.php';

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirectAfterLogin();
}

// Manejar el formulario de login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } elseif (!authenticate($username, $password)) {
        $error = 'Usuario o contraseña incorrectos';
    } else {
        // Redirigir después de login exitoso
        redirectAfterLogin();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema Bancario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars('assets/css/login.css'); ?>">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="bank-header">
                <div class="bank-logo">
                    <img src="assets/images/logo-banco.png" alt="Banco Caroní">
                </div>
                <h2>Sistema Estado de Cuenta</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Usuario" required autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label for="username">Usuario</label>
                </div>

                <div class="form-floating">
                    <input type="password" class="form-control" id="password" 
                           name="password" placeholder="Contraseña" required>
                    <label for="password">Contraseña</label>
                </div>

                <button class="btn btn-login btn-lg" type="submit">Ingresar</button>
            </form>

            <div class="footer-links">
                <a href="recuperar-contrasena.php">¿Olvidó su contraseña?</a>
                <span class="mx-2">•</span>
                <a href="soporte.php">Ayuda y soporte</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>