<?php
// cuentas/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden "eliminar" (marcar como inactivas) cuentas
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
        'texto' => 'No se especificó la cuenta a marcar como inactiva'
    ];
    header('Location: listar.php');
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // 1. Verificar si la cuenta existe
    $stmt = $pdo->prepare("SELECT acmsta FROM acmst WHERE acmacc = :cuenta");
    $stmt->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    $stmt->execute();
    
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        throw new Exception("La cuenta no existe");
    }

    // 2. Si ya está inactiva, no hacer nada
    if ($cuenta['acmsta'] === 'I') {
        throw new Exception("La cuenta ya está marcada como inactiva");
    }

    // 3. Marcar la cuenta como inactiva (I) en lugar de eliminarla
    $sqlActualizarCuenta = "UPDATE acmst SET acmsta = 'I' WHERE acmacc = :cuenta";
    $stmtCuenta = $pdo->prepare($sqlActualizarCuenta);
    $stmtCuenta->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    
    if (!$stmtCuenta->execute()) {
        throw new Exception("Error al marcar la cuenta como inactiva: " . implode(" ", $stmtCuenta->errorInfo()));
    }

    $pdo->commit();

    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => "Cuenta $numeroCuenta marcada como inactiva exitosamente"
    ];

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => "Error al marcar la cuenta como inactiva: " . $e->getMessage()
    ];
    error_log("Error al marcar cuenta $numeroCuenta como inactiva: " . $e->getMessage());
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => $e->getMessage()
    ];
    error_log("Error al marcar cuenta $numeroCuenta como inactiva: " . $e->getMessage());
}

// Redirigir de vuelta al listado
header('Location: listar.php');
exit;