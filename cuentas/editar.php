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

// Obtener ID de cuenta a editar
$cuentaId = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($cuentaId)) {
    header('Location: listar.php');
    exit;
}

// Obtener datos de la cuenta
try {
    $pdo = getPDO();
    
    // Obtener información de la cuenta
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
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
    
    // Obtener lista de clientes, sucursales y productos bancarios
    $clientes = $pdo->query("SELECT cuscun, CONCAT(cusna1, ' ', cusna2) AS nombre FROM cumst ORDER BY nombre")->fetchAll();
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll();
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error al obtener datos: " . $e->getMessage());
    die("Ocurrió un error al cargar los datos. Por favor intente más tarde.");
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar y sanitizar datos
        $clienteId = (int)$_POST['cliente_id'];
        $estado = $_POST['estado'] === 'A' ? 'A' : 'I';
        $fechaApertura = $_POST['fecha_apertura'];
        $sucursal = (int)$_POST['sucursal'];
        $productoBancario = (int)$_POST['producto_bancario'];
        $tipoCuenta = substr(trim($_POST['tipo_cuenta']), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta']), 0, 1);
        
        // Validaciones básicas
        if (empty($clienteId) || empty($sucursal) || empty($productoBancario)) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }
        
        // Verificar si el cliente existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cumst WHERE cuscun = :cliente");
        $stmt->execute([':cliente' => $clienteId]);
        if ($stmt->fetchColumn() === 0) {
            throw new Exception("El cliente seleccionado no existe");
        }
        
        // Actualizar la cuenta en la base de datos
        $sql = "UPDATE acmst SET 
                acmcun = :cliente, 
                acmbrn = :sucursal, 
                acmprd = :producto, 
                acmtyp = :tipo, 
                acmcls = :clase, 
                acmsta = :estado, 
                acmopn = :fecha
                WHERE acmacc = :cuenta";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cuenta' => $cuentaId,
            ':cliente' => $clienteId,
            ':sucursal' => $sucursal,
            ':producto' => $productoBancario,
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':estado' => $estado,
            ':fecha' => $fechaApertura
        ]);
        
        // Redirigir al listado con mensaje de éxito
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Cuenta actualizada exitosamente"
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
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Editar Cuenta Bancaria</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
                                <?= htmlspecialchars($cuenta['acmacc']) ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cliente_id" class="form-label required-field">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= htmlspecialchars($cliente['cuscun']) ?>" 
                                        <?= $cliente['cuscun'] == $cuenta['acmcun'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nombre']) ?> (ID: <?= htmlspecialchars($cliente['cuscun']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sucursal" class="form-label required-field">Sucursal</label>
                            <select class="form-select" id="sucursal" name="sucursal" required>
                                <option value="">Seleccione sucursal</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?= htmlspecialchars($suc['acmbrn']) ?>"
                                        <?= $suc['acmbrn'] == $cuenta['acmbrn'] ? 'selected' : '' ?>>
                                        Sucursal <?= htmlspecialchars($suc['acmbrn']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="producto_bancario" class="form-label required-field">Producto Bancario</label>
                            <select class="form-select" id="producto_bancario" name="producto_bancario" required>
                                <option value="">Seleccione producto</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value="<?= htmlspecialchars($prod['acmprd']) ?>"
                                        <?= $prod['acmprd'] == $cuenta['acmprd'] ? 'selected' : '' ?>>
                                        Producto <?= htmlspecialchars($prod['acmprd']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                <option value="CA" <?= $cuenta['acmtyp'] === 'CA' ? 'selected' : '' ?>>CA - Cuenta de Ahorros</option>
                                <option value="CC" <?= $cuenta['acmtyp'] === 'CC' ? 'selected' : '' ?>>CC - Cuenta Corriente</option>
                                <option value="PL" <?= $cuenta['acmtyp'] === 'PL' ? 'selected' : '' ?>>PL - Préstamo</option>
                                <option value="TD" <?= $cuenta['acmtyp'] === 'TD' ? 'selected' : '' ?>>TD - Depósito a Término</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?= $cuenta['acmcls'] === 'N' ? 'selected' : '' ?>>N - Normal</option>
                                <option value="J" <?= $cuenta['acmcls'] === 'J' ? 'selected' : '' ?>>J - Jurídica</option>
                                <option value="E" <?= $cuenta['acmcls'] === 'E' ? 'selected' : '' ?>>E - Extranjera</option>
                                <option value="V" <?= $cuenta['acmcls'] === 'V' ? 'selected' : '' ?>>V - VIP</option>
                                <option value="P" <?= $cuenta['acmcls'] === 'P' ? 'selected' : '' ?>>P - Premium</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?= $cuenta['acmsta'] === 'A' ? 'selected' : '' ?>>Activo</option>
                                <option value="I" <?= $cuenta['acmsta'] === 'I' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha de Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?= date('Y-m-d', strtotime($cuenta['acmopn'])) ?>" required>
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

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mejorar experiencia de fecha
        document.getElementById('fecha_apertura').addEventListener('focus', function() {
            this.type = 'date';
        });
    </script>
</body>
</html>