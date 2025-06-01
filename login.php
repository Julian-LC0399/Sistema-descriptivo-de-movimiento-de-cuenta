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
            background-color: #f8f9fa;
            background-image: linear-gradient(to right, #f5f7fa, #e4e8f0);
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
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .bank-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .bank-logo img {
            height: 60px;
        }
        .form-floating {
            margin-bottom: 1.5rem;
        }
        .btn-primary {
            background-color: #0056b3;
            border: none;
            padding: 10px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #004494;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }
        .footer-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }
        .footer-links a {
            color: #6c757d;
            text-decoration: none;
        }
        .footer-links a:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="bank-logo">
                <img src="assets/images/logo-banco.png" alt="Logo del Banco">
                <h2 class="mt-3">Sistema Bancario</h2>
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

                <button class="w-100 btn btn-lg btn-primary" type="submit">Ingresar</button>
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