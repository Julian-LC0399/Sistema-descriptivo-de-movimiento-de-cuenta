<?php
// login.php
session_start();
require 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $contrasena = $_POST['contrasena'];

    try {
        $stmt = $pdo->prepare("SELECT id, usuario, contrasena, nombre FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($contrasena, $user['contrasena'])) {
            // Autenticación exitosa
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            
            header("Location: dashboard.php");
            exit();
        } else {
            // Autenticación fallida
            $_SESSION['error'] = "Usuario o contraseña incorrectos";
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en el sistema";
        header("Location: login.php");
        exit();
    }
}
?>

<!-- Formulario de login -->
<?php if (isset($_SESSION['error'])): ?>
    <div style="color: red;"><?= $_SESSION['error'] ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<form method="post">
    <input type="text" name="usuario" placeholder="Usuario" required>
    <input type="password" name="contrasena" placeholder="Contraseña" required>
    <button type="submit">Iniciar sesión</button>
</form>