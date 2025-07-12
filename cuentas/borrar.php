<?php
// cuentas/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden eliminar cuentas
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'No tienes permisos para realizar esta acción'
    ];
    header('Location: listar.php');
    exit;
}

// Verificar que se haya proporcionado un ID válido
$numeroCuenta = $_GET['id'] ?? '';
if (empty($numeroCuenta)) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'No se especificó la cuenta a eliminar'
    ];
    header('Location: listar.php');
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // 1. Verificar si la cuenta existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
    $stmt->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->fetchColumn() === 0) {
        throw new Exception("La cuenta no existe");
    }

    // 2. Eliminar primero las referencias (por restricciones de clave foránea)
    $sqlBorrarRef = "DELETE FROM acref WHERE acrnac = :cuenta OR acrrac = :cuenta";
    $stmtRef = $pdo->prepare($sqlBorrarRef);
    $stmtRef->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    
    if (!$stmtRef->execute()) {
        throw new Exception("Error al eliminar referencias: " . implode(" ", $stmtRef->errorInfo()));
    }

    // 3. Eliminar la cuenta principal
    $sqlBorrarCuenta = "DELETE FROM acmst WHERE acmacc = :cuenta";
    $stmtCuenta = $pdo->prepare($sqlBorrarCuenta);
    $stmtCuenta->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    
    if (!$stmtCuenta->execute()) {
        throw new Exception("Error al eliminar la cuenta: " . implode(" ", $stmtCuenta->errorInfo()));
    }

    $pdo->commit();

    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => "Cuenta $numeroCuenta eliminada exitosamente con todas sus referencias"
    ];

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => "Error al eliminar la cuenta: " . $e->getMessage()
    ];
    error_log("Error al borrar cuenta $numeroCuenta: " . $e->getMessage());
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => $e->getMessage()
    ];
    error_log("Error al borrar cuenta $numeroCuenta: " . $e->getMessage());
}

// Redirigir de vuelta al listado
header('Location: listar.php');
exit;