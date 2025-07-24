<?php
// cuentas/editar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

date_default_timezone_set('America/Caracas');
requireLogin();

$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener datos de la cuenta a editar
$numeroCuenta = $_GET['id'] ?? null;
$cuenta = [];
$error = '';

if (!$numeroCuenta) {
    header('Location: listar.php');
    exit;
}

try {
    $pdo = getPDO();
    
    // Obtener datos de la cuenta
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
    $stmt->bindParam(':cuenta', $numeroCuenta);
    $stmt->execute();
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        throw new Exception("Cuenta no encontrada");
    }
    
    // Obtener datos del cliente
    $stmtCliente = $pdo->prepare("SELECT cuscun, CONCAT(cusna1, ' ', cusln1) AS nombre FROM cumst WHERE cuscun = :cliente");
    $stmtCliente->bindParam(':cliente', $cuenta['acmcun']);
    $stmtCliente->execute();
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Procesar actualización (solo estado es editable)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar solo el estado
        $estado = in_array($_POST['estado'] ?? '', ['A', 'I']) ? $_POST['estado'] : 'A';

        // Actualizar cuenta (solo estado)
        $sql = "UPDATE acmst SET 
                acmsta = :estado,
                acmlut = NOW(),
                acmlau = :usuario
                WHERE acmacc = :cuenta";
        
        $params = [
            ':cuenta' => $numeroCuenta,
            ':estado' => $estado,
            ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
        ];
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al actualizar la cuenta: " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Cuenta $numeroCuenta actualizada exitosamente"
        ];
        
        header('Location: listar.php');
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error en la base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cuenta - Banco Caroni</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Editar Cuenta Bancaria</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <!-- Sección Información de la Cuenta -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información de la Cuenta</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número de Cuenta</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo htmlspecialchars($cuenta['acmacc'] ?? ''); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?> (ID: <?php echo htmlspecialchars($cuenta['acmcun'] ?? ''); ?>)
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sucursal</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                Sucursal <?php echo htmlspecialchars($cuenta['acmbrn'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Moneda</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php 
                                $monedas = [
                                    'BS' => 'Bolívar',
                                    'USD' => 'Dólar',
                                    'EUR' => 'Euro',
                                    'COP' => 'Peso Colombiano',
                                    'BRL' => 'Real Brasileño'
                                ];
                                echo htmlspecialchars($monedas[$cuenta['acmccy']] ?? $cuenta['acmccy']); 
                                ?>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Producto Bancario</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo htmlspecialchars($cuenta['acmprd'] ?? ''); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Mostrar tipo y clase como información de solo lectura -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo de Cuenta</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php 
                                $tiposCuenta = [
                                    'CA' => 'CA - Ahorros',
                                    'CC' => 'CC - Corriente'
                                ];
                                echo htmlspecialchars($tiposCuenta[$cuenta['acmtyp']] ?? $cuenta['acmtyp']);
                                ?>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Clase de Cuenta</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php 
                                $clasesCuenta = [
                                    'N' => 'N - Normal',
                                    'J' => 'J - Jurídica',
                                    'V' => 'V - VIP'
                                ];
                                echo htmlspecialchars($clasesCuenta[$cuenta['acmcls']] ?? $cuenta['acmcls']);
                                ?>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo (!isset($cuenta['acmsta']) || $cuenta['acmsta'] === 'A') ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo (isset($cuenta['acmsta']) && $cuenta['acmsta'] === 'I') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Apertura</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo isset($cuenta['acmopn']) ? date('d/m/Y', strtotime($cuenta['acmopn'])) : date('d/m/Y'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Última Actualización</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo isset($cuenta['acmlut']) ? date('d/m/Y H:i', strtotime($cuenta['acmlut'])) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="listar.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </main>

    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) new bootstrap.Alert(alert).close();
        }, 5000);
    </script>
</body>
</html>