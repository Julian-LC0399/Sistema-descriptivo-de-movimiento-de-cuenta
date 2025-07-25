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

    // 1. Verificar si la cuenta existe y obtener su estado actual
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

    // 3. Marcar la cuenta como inactiva (I)
    $sqlActualizarCuenta = "UPDATE acmst SET acmsta = 'I', acmlut = NOW(), acmlau = :usuario WHERE acmacc = :cuenta";
    $stmtCuenta = $pdo->prepare($sqlActualizarCuenta);
    $paramsCuenta = [
        ':cuenta' => $numeroCuenta,
        ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
    ];
    
    if (!$stmtCuenta->execute($paramsCuenta)) {
        throw new Exception("Error al marcar la cuenta como inactiva: " . implode(" ", $stmtCuenta->errorInfo()));
    }

    // 4. Marcar también la referencia asociada como inactiva
    $sqlActualizarReferencia = "UPDATE acref SET acrsts = 'I' WHERE acrnac = :cuenta";
    $stmtReferencia = $pdo->prepare($sqlActualizarReferencia);
    $stmtReferencia->bindParam(':cuenta', $numeroCuenta, PDO::PARAM_STR);
    
    if (!$stmtReferencia->execute()) {
        throw new Exception("Error al marcar la referencia como inactiva: " . implode(" ", $stmtReferencia->errorInfo()));
    }

    // 5. Obtener el próximo número de secuencia para el histórico
    $stmtSecuencia = $pdo->prepare("SELECT IFNULL(MAX(hstseq), 0) + 1 FROM achst WHERE hstacc = :cuenta");
    $stmtSecuencia->bindParam(':cuenta', $numeroCuenta);
    $stmtSecuencia->execute();
    $proximaSecuencia = $stmtSecuencia->fetchColumn();

    // 6. Registrar el cambio en el histórico de estados
    $sqlHistorico = "INSERT INTO achst 
                    (hstacc, hstdat, hstseq, hststa, hstrsn, hstusr, hstip, hstauth) 
                    VALUES 
                    (:cuenta, NOW(), :secuencia, 'I', :razon, :usuario, :ip, :auth)";
    
    $stmtHistorico = $pdo->prepare($sqlHistorico);
    $paramsHistorico = [
        ':cuenta' => $numeroCuenta,
        ':secuencia' => $proximaSecuencia,
        ':razon' => 'Marcada como inactiva por administrador',
        ':usuario' => $_SESSION['username'] ?? 'SISTEMA',
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ':auth' => $_SESSION['username'] ?? 'SISTEMA'
    ];
    
    if (!$stmtHistorico->execute($paramsHistorico)) {
        throw new Exception("Error al registrar en histórico: " . implode(" ", $stmtHistorico->errorInfo()));
    }

    $pdo->commit();

    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => "Cuenta $numeroCuenta marcada como inactiva exitosamente. Se registró en el histórico de cambios."
    ];

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Registrar error detallado en logs
    error_log("Error PDO en borrar.php - Cuenta: $numeroCuenta - Código: " . $e->getCode() . " - Mensaje: " . $e->getMessage());
    
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => "Ocurrió un error técnico al procesar la solicitud. Por favor intente nuevamente."
    ];
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error general en borrar.php - Cuenta: $numeroCuenta - Mensaje: " . $e->getMessage());
    
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => $e->getMessage()
    ];
}

// Redirigir de vuelta al listado
header('Location: listar.php');
exit;