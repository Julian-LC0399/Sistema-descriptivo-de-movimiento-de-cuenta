<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitizeInput($_POST['usuario']);
    $contrasena = $_POST['contrasena'];
    
    $stmt = $pdo->prepare("SELECT id, usuario, contrasena, nombre FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($contrasena, $user['contrasena'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['usuario'];
        $_SESSION['nombre'] = $user['nombre'];
        
        // Actualizar último login
        $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        header("Location: index.php");
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>