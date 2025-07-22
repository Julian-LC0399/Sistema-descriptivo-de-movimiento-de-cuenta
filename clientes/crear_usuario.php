<?php
// Iniciar sesión y verificar permisos
session_start();
require_once '../includes/database.php'; // Conexión a DB (ajusta la ruta según tu estructura)
require_once '../includes/functions.php'; // Funciones de autenticación

// Redirigir si no es admin
if (!esAdmin()) {
    header('Location: ../login.php?error=permisos');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $role = 'cliente'; // Rol fijo para nuevos usuarios

    // Validaciones básicas
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Todos los campos son obligatorios";
    } else {
        // Hash de contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insertar en DB (usando prepared statements)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, nombre, apellido, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $passwordHash, $email, $nombre, $apellido, $role]);

            $success = "Usuario creado exitosamente";
        } catch (PDOException $e) {
            $error = "Error al crear usuario: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Nuevo Usuario - Sistema Bancario</title>
    <link rel="stylesheet" href="../assets/css/styles.css"> <!-- Ajusta la ruta -->
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1>Crear Nuevo Usuario Cliente</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario*</label>
                <input type="text" id="username" name="username" required class="form-control">
            </div>

            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" required class="form-control">
            </div>

            <div class="form-group">
                <label for="password">Contraseña*</label>
                <input type="password" id="password" name="password" required class="form-control" minlength="6">
            </div>

            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" class="form-control">
            </div>

            <div class="form-group">
                <label for="apellido">Apellido</label>
                <input type="text" id="apellido" name="apellido" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">Crear Usuario</button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>