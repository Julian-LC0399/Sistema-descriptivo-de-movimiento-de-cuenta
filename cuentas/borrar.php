<?php
// cuentas/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden borrar cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener ID de cuenta a borrar
$cuentaId = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($cuentaId)) {
    header('Location: listar.php');
    exit;
}

// Verificar si la cuenta existe y obtener información del cliente
try {
    $pdo = getPDO();
    
    // Obtener información de la cuenta y el cliente
    $sql = "SELECT a.acmacc, c.cusna1 AS nombre_cliente 
            FROM acmst a 
            JOIN cumst c ON a.acmcun = c.cuscun 
            WHERE a.acmacc = :cuenta";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cuenta' => $cuentaId]);
    $cuenta = $stmt->fetch();
    
    if (!$cuenta) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'La cuenta solicitada no existe'
        ];
        header('Location: listar.php');
        exit;
    }
    
    // Verificar si la cuenta tiene saldo o transacciones
    $stmt = $pdo->prepare("SELECT acmbal FROM acmst WHERE acmacc = :cuenta");
    $stmt->execute([':cuenta' => $cuentaId]);
    $saldo = $stmt->fetchColumn();
    
    if ($saldo != 0) {
        $_SESSION['mensaje'] = [
            'tipo' => 'warning',
            'texto' => 'No se puede eliminar una cuenta con saldo diferente de cero'
        ];
        header('Location: listar.php');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM actrd WHERE trdacc = :cuenta");
    $stmt->execute([':cuenta' => $cuentaId]);
    $tieneTransacciones = $stmt->fetchColumn() > 0;
    
    if ($tieneTransacciones) {
        $_SESSION['mensaje'] = [
            'tipo' => 'warning',
            'texto' => 'No se puede eliminar una cuenta con historial de transacciones'
        ];
        header('Location: listar.php');
        exit;
    }
    
    // Si se confirma el borrado
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'si') {
        // Iniciar transacción
        $pdo->beginTransaction();
        
        try {
            // 1. Eliminar referencias de cuenta si existen
            $stmt = $pdo->prepare("DELETE FROM acref WHERE acrnac = :cuenta");
            $stmt->execute([':cuenta' => $cuentaId]);
            
            // 2. Eliminar la cuenta
            $stmt = $pdo->prepare("DELETE FROM acmst WHERE acmacc = :cuenta");
            $stmt->execute([':cuenta' => $cuentaId]);
            
            // Confirmar transacción
            $pdo->commit();
            
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => "Cuenta {$cuentaId} del cliente {$cuenta['nombre_cliente']} eliminada correctamente"
            ];
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al eliminar cuenta: " . $e->getMessage());
            $_SESSION['mensaje'] = [
                'tipo' => 'danger',
                'texto' => 'Ocurrió un error al eliminar la cuenta. Por favor intente nuevamente.'
            ];
        }
        
        header('Location: listar.php');
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Error en borrar.php: " . $e->getMessage());
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Ocurrió un error al procesar la solicitud'
    ];
    header('Location: listar.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Eliminación - Banco Caroni</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación</h5>
            </div>
            <div class="card-body">
                <h4 class="card-title">¿Está seguro que desea eliminar esta cuenta?</h4>
                
                <div class="alert alert-warning">
                    <strong>Número de Cuenta:</strong> <?= htmlspecialchars($cuentaId) ?><br>
                    <strong>Cliente:</strong> <?= htmlspecialchars($cuenta['nombre_cliente']) ?>
                </div>
                
                <p class="text-danger">
                    <i class="bi bi-exclamation-circle"></i> Esta acción es irreversible y eliminará permanentemente 
                    la cuenta del sistema.
                </p>
                
                <form method="post">
                    <input type="hidden" name="confirmar" value="si">
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Confirmar Eliminación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>