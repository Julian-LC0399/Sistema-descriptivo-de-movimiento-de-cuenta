<?php
// cuentas/editar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden editar cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener número de cuenta a editar
$numeroCuenta = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($numeroCuenta)) {
    header('Location: listar.php');
    exit;
}

// Obtener datos de la cuenta existente
try {
    $pdo = getPDO();
    
    // Consulta para obtener los datos de la cuenta
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
    $stmt->execute([':cuenta' => $numeroCuenta]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'La cuenta solicitada no existe'
        ];
        header('Location: listar.php');
        exit;
    }
    
    // Obtener lista de clientes, sucursales y productos bancarios
    $clientes = $pdo->query("SELECT cuscun, CONCAT(cusna1, ' ', cusna2) AS nombre FROM cumst ORDER BY nombre")->fetchAll();
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll();
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
    error_log($error);
    $clientes = [];
    $sucursales = [];
    $productos = [];
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        
        // Validar y sanitizar datos
        $estado = $_POST['estado'] === 'A' ? 'A' : 'I';
        $fechaApertura = $_POST['fecha_apertura'];
        $tipoCuenta = substr(trim($_POST['tipo_cuenta']), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta']), 0, 1);
        
        // Validaciones básicas
        if (empty($fechaApertura)) {
            throw new Exception("La fecha de apertura es obligatoria");
        }
        
        // Actualizar en la base de datos
        $sql = "UPDATE acmst 
                SET acmtyp = :tipo, 
                    acmcls = :clase, 
                    acmsta = :estado, 
                    acmopn = :fecha
                WHERE acmacc = :cuenta";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':estado' => $estado,
            ':fecha' => $fechaApertura,
            ':cuenta' => $numeroCuenta
        ]);
        
        // Redirigir al listado con mensaje de éxito
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Cuenta $numeroCuenta actualizada exitosamente"
        ];
        header('Location: listar.php');
        exit;
        
    } catch (PDOException $e) {
        $error = "Error al actualizar la cuenta: " . $e->getMessage();
    } catch (Exception $e) {
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
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
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
                                <?php 
                                $nombreCliente = '';
                                foreach ($clientes as $cliente) {
                                    if ($cliente['cuscun'] == $cuenta['acmcun']) {
                                        $nombreCliente = $cliente['nombre'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($nombreCliente . ' (ID: ' . ($cuenta['acmcun'] ?? '') . ')'); 
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sucursal</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                Sucursal <?php echo htmlspecialchars($cuenta['acmbrn'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Producto Bancario</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                Producto <?php echo htmlspecialchars($cuenta['acmprd'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Configuración de la Cuenta -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Configuración de la Cuenta</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_cuenta" class="form-label required-field">Tipo de Cuenta</label>
                            <select class="form-select" id="tipo_cuenta" name="tipo_cuenta" required>
                                <option value="CA" <?php echo (($cuenta['acmtyp'] ?? 'CA') === 'CA') ? 'selected' : ''; ?>>CA - Cuenta de Ahorros</option>
                                <option value="CC" <?php echo (($cuenta['acmtyp'] ?? '') === 'CC') ? 'selected' : ''; ?>>CC - Cuenta Corriente</option>
                                <option value="PL" <?php echo (($cuenta['acmtyp'] ?? '') === 'PL') ? 'selected' : ''; ?>>PL - Préstamo</option>
                                <option value="TD" <?php echo (($cuenta['acmtyp'] ?? '') === 'TD') ? 'selected' : ''; ?>>TD - Depósito a Término</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo (($cuenta['acmcls'] ?? 'N') === 'N') ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo (($cuenta['acmcls'] ?? '') === 'J') ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="E" <?php echo (($cuenta['acmcls'] ?? '') === 'E') ? 'selected' : ''; ?>>E - Extranjera</option>
                                <option value="V" <?php echo (($cuenta['acmcls'] ?? '') === 'V') ? 'selected' : ''; ?>>V - VIP</option>
                                <option value="P" <?php echo (($cuenta['acmcls'] ?? '') === 'P') ? 'selected' : ''; ?>>P - Premium</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo (($cuenta['acmsta'] ?? 'A') === 'A') ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo (($cuenta['acmsta'] ?? '') === 'I') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha de Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?php echo isset($_POST['fecha_apertura']) ? htmlspecialchars($_POST['fecha_apertura']) : htmlspecialchars($cuenta['acmopn'] ?? date('Y-m-d')); ?>" required>
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
        // Mejorar experiencia de fecha
        document.getElementById('fecha_apertura').addEventListener('focus', function() {
            this.type = 'date';
        });
    </script>
</body>
</html>