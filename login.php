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
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            background-color: #f0f2f5;  /* Gris claro neutro */
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .bank-header {
            background-color: #003366;  /* Azul oscuro como en la imagen */
            color: white;
            padding: 1rem;
            margin: -2rem -2rem 2rem -2rem;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .bank-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        .bank-logo img {
            height: 50px;
            margin-bottom: 10px;
        }
        .form-floating {
            margin-bottom: 1.5rem;
        }
        .btn-login {
            background-color: #0056b3;  /* Azul claro para el botón */
            border: none;
            padding: 10px;
            font-weight: 500;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #004494;  /* Azul más oscuro al hover */
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
        }
        .footer-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .footer-links a {
            color: #0056b3;  /* Azul claro para enlaces */
            text-decoration: none;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
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