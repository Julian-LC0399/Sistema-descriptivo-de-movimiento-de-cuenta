<?php
// usuarios/cambiar_estado.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Configurar zona horaria para Venezuela
date_default_timezone_set('America/Caracas');

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden cambiar estados
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "No tienes permisos para esta acción";
    header('Location: lista.php');
    exit;
}

// Obtener ID del usuario a modificar
$idUsuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idUsuario === 0) {
    $_SESSION['error'] = "ID de usuario no válido";
    header('Location: lista.php');
    exit;
}

// Evitar que un usuario se desactive a sí mismo
if ($idUsuario == $_SESSION['user_id']) {
    $_SESSION['error'] = "No puedes cambiar tu propio estado";
    header('Location: lista.php');
    exit;
}

try {
    $pdo = getPDO();
    
    // Primero obtener el estado actual
    $sql = "SELECT activo FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idUsuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    // Determinar el nuevo estado (alternar entre activo/inactivo)
    $nuevoEstado = $usuario['activo'] ? 0 : 1;
    $accion = $usuario['activo'] ? 'desactivado' : 'activado';
    
    // Actualizar el estado
    $sql = "UPDATE users SET activo = :activo, actualizado_en = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':activo' => $nuevoEstado,
        ':id' => $idUsuario
    ]);
    
    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => "Usuario $accion correctamente"
    ];
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    error_log($_SESSION['error']);
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    error_log($_SESSION['error']);
}

header('Location: lista.php');
exit;
?>