<?php
// usuarios/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden desactivar usuarios
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "No tienes permisos para realizar esta acción";
    header('Location: lista.php');
    exit;
}

// Obtener ID del usuario a desactivar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = "ID de usuario no válido";
    header('Location: lista.php');
    exit;
}

// Evitar que un usuario se desactive a sí mismo
if ($id == $_SESSION['user_id']) {
    $_SESSION['error'] = "No puedes desactivar tu propio usuario";
    header('Location: lista.php');
    exit;
}

try {
    $pdo = getPDO();
    
    // Cambiar el estado a inactivo (0)
    $stmt = $pdo->prepare("UPDATE users SET activo = 0, actualizado_en = NOW() WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['mensaje'] = "Usuario desactivado correctamente";
    } else {
        // Verificar si ya estaba inactivo
        $stmt = $pdo->prepare("SELECT activo FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $estado = $stmt->fetchColumn();
        
        if ($estado === 0) {
            $_SESSION['error'] = "El usuario ya estaba inactivo";
        } else {
            $_SESSION['error'] = "No se encontró el usuario";
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al desactivar el usuario: " . $e->getMessage();
    error_log($_SESSION['error']);
}

// Redirigir de vuelta a la lista
header('Location: lista.php');
exit;
?>