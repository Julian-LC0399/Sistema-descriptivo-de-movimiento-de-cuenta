<?php
// cuentas/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden borrar cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: listar.php');
    exit;
}

$cuentaId = $_GET['id'];

try {
    $pdo = getPDO();
    
    // Verificar que la cuenta existe
    $stmt = $pdo->prepare("SELECT acmacc, acmbal FROM acmst WHERE acmacc = ?");
    $stmt->execute([$cuentaId]);
    $cuenta = $stmt->fetch();
    
    if (!$cuenta) {
        $_SESSION['mensaje'] = "La cuenta no existe";
        header('Location: listar.php');
        exit;
    }
    
    // Verificar que la cuenta tiene saldo cero antes de borrar
    if ($cuenta['acmbal'] != 0) {
        $_SESSION['mensaje'] = "No se puede borrar una cuenta con saldo distinto de cero";
        header('Location: listar.php');
        exit;
    }
    
    // Borrar la cuenta
    $stmt = $pdo->prepare("DELETE FROM acmst WHERE acmacc = ?");
    $stmt->execute([$cuentaId]);
    
    $_SESSION['mensaje'] = "Cuenta borrada correctamente";
    
} catch (PDOException $e) {
    error_log("Error al borrar cuenta: " . $e->getMessage());
    $_SESSION['mensaje'] = "Error al borrar la cuenta: " . $e->getMessage();
}

header('Location: listar.php');
exit;
?>