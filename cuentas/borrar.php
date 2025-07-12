<?php
// cuentas/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

// Verificar que se reciba el ID
if (!isset($_GET['id'])) {
    header("Location: listar.php?error=ID+no+proporcionado");
    exit();
}

$numeroCuenta = $_GET['id'];

try {
    $pdo = getPDO();
    
    // Eliminar físicamente la cuenta (¡ATENCIÓN: Esto no se puede deshacer!)
    $sql = "DELETE FROM acmst WHERE acmacc = :cuenta";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    $stmt->execute();
    
    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => "Cuenta $numeroCuenta eliminada permanentemente"
    ];
    header("Location: listar.php");
    exit();
    
} catch (PDOException $e) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => "Error al eliminar la cuenta: " . $e->getMessage()
    ];
    header("Location: listar.php");
    exit();
}