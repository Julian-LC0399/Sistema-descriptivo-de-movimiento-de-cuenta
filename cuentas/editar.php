<?php
// cuentas/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden crear/editar cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener lista de clientes, sucursales y productos bancarios
try {
    $pdo = getPDO();
    $clientes = $pdo->query("SELECT cuscun, CONCAT(cusna1, ' ', cusna2) AS nombre FROM cumst ORDER BY nombre")->fetchAll();
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll();
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
    $sucursales = [];
    $productos = [];
    error_log("Error al obtener datos: " . $e->getMessage());
}

// Variables para modo edición
$modoEdicion = false;
$cuentaExistente = null;
$numeroCuentaOriginal = null;

// Verificar si estamos en modo edición (se pasa ID por GET)
if (isset($_GET['id'])) {
    $modoEdicion = true;
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
        $stmt->execute([':cuenta' => $_GET['id']]);
        $cuentaExistente = $stmt->fetch();
        
        if (!$cuentaExistente) {
            header('Location: listar.php');
            exit;
        }
        
        $numeroCuentaOriginal = $cuentaExistente['acmacc'];
        
    } catch (PDOException $e) {
        $error = "Error al cargar la cuenta: " . $e->getMessage();
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        
        // Validar y sanitizar datos
        $numeroCuenta = trim($_POST['numero_cuenta']);
        $clienteId = (int)$_POST['cliente_id'];
        $saldoInicial = (float)$_POST['saldo_inicial'];
        $disponible = (float)$_POST['disponible'];
        $estado = $_POST['estado'] === 'A' ? 'A' : 'I';
        $fechaApertura = $_POST['fecha_apertura'];
        $sucursal = (int)$_POST['sucursal'];
        $productoBancario = (int)$_POST['producto_bancario'];
        $tipoCuenta = substr(trim($_POST['tipo_cuenta']), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta']), 0, 1);
        
        // Validaciones básicas
        if (empty($numeroCuenta) || empty($clienteId) || empty($sucursal) || empty($productoBancario)) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }
        
        if (!preg_match('/^[0-9]{20}$/', $numeroCuenta)) {
            throw new Exception("El número de cuenta debe tener exactamente 20 dígitos");
        }
        
        // En modo edición, verificar si el número de cuenta cambió
        if ($modoEdicion && $numeroCuenta !== $numeroCuentaOriginal) {
            // Verificar si el nuevo número de cuenta ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
            $stmt->execute([':cuenta' => $numeroCuenta]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El número de cuenta ya existe");
            }
        }
        // En modo creación, siempre verificar que no exista
        elseif (!$modoEdicion) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
            $stmt->execute([':cuenta' => $numeroCuenta]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El número de cuenta ya existe");
            }
        }
        
        // Verificar si el cliente existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cumst WHERE cuscun = :cliente");
        $stmt->execute([':cliente' => $clienteId]);
        if ($stmt->fetchColumn() === 0) {
            throw new Exception("El cliente seleccionado no existe");
        }
        
        if ($modoEdicion) {
            // Actualizar la cuenta existente
            $sql = "UPDATE acmst SET 
                    acmacc = :cuenta, 
                    acmcun = :cliente, 
                    acmbrn = :sucursal, 
                    acmprd = :producto, 
                    acmtyp = :tipo, 
                    acmcls = :clase, 
                    acmbal = :saldo, 
                    acmavl = :disponible, 
                    acmsta = :estado, 
                    acmopn = :fecha
                    WHERE acmacc = :cuenta_original";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cuenta' => $numeroCuenta,
                ':cliente' => $clienteId,
                ':sucursal' => $sucursal,
                ':producto' => $productoBancario,
                ':tipo' => $tipoCuenta,
                ':clase' => $claseCuenta,
                ':saldo' => $saldoInicial,
                ':disponible' => $disponible,
                ':estado' => $estado,
                ':fecha' => $fechaApertura,
                ':cuenta_original' => $numeroCuentaOriginal
            ]);
            
            $mensaje = "Cuenta actualizada exitosamente";
        } else {
            // Insertar nueva cuenta
            $sql = "INSERT INTO acmst 
                    (acmacc, acmcun, acmbrn, acmccy, acmprd, acmtyp, acmcls, acmlsb, acmbal, acmavl, acmsta, acmopn) 
                    VALUES 
                    (:cuenta, :cliente, :sucursal, 'VES', :producto, :tipo, :clase, 0, :saldo, :disponible, :estado, :fecha)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cuenta' => $numeroCuenta,
                ':cliente' => $clienteId,
                ':sucursal' => $sucursal,
                ':producto' => $productoBancario,
                ':tipo' => $tipoCuenta,
                ':clase' => $claseCuenta,
                ':saldo' => $saldoInicial,
                ':disponible' => $disponible,
                ':estado' => $estado,
                ':fecha' => $fechaApertura
            ]);
            
            $mensaje = "Cuenta creada exitosamente";
        }
        
        // Redirigir al listado con mensaje de éxito
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => $mensaje
        ];
        header('Location: listar.php');
        exit;
        
    } catch (PDOException $e) {
        $error = "Error al " . ($modoEdicion ? "actualizar" : "crear") . " la cuenta: " . $e->getMessage();
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
    <title><?= $modoEdicion ? 'Editar' : 'Crear' ?> Cuenta - Sistema Bancario</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= $modoEdicion ? 'Editar Cuenta Bancaria' : 'Crear Nueva Cuenta Bancaria' ?></h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Información de la Cuenta</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="numero_cuenta" class="form-label required-field">Número de Cuenta</label>
                        <input type="text" class="form-control" id="numero_cuenta" name="numero_cuenta" 
                               required pattern="[0-9]{20}" title="Debe ser un número de 20 dígitos"
                               value="<?= htmlspecialchars($modoEdicion ? $cuentaExistente['acmacc'] : '') ?>"
                               <?= $modoEdicion ? '' : 'maxlength="20"' ?>>
                        <small class="form-text text-muted">Ejemplo: 01280001180101104262</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cliente_id" class="form-label required-field">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= htmlspecialchars($cliente['cuscun']) ?>"
                                    <?= ($modoEdicion && $cuentaExistente['acmcun'] == $cliente['cuscun']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nombre']) ?> (ID: <?= htmlspecialchars($cliente['cuscun']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sucursal" class="form-label required-field">Sucursal</label>
                            <select class="form-select" id="sucursal" name="sucursal" required>
                                <option value="">Seleccione sucursal</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?= htmlspecialchars($suc['acmbrn']) ?>"
                                        <?= ($modoEdicion && $cuentaExistente['acmbrn'] == $suc['acmbrn']) ? 'selected' : '' ?>>
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
                                        <?= ($modoEdicion && $cuentaExistente['acmprd'] == $prod['acmprd']) ? 'selected' : '' ?>>
                                        Producto <?= htmlspecialchars($prod['acmprd']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Configuración de la Cuenta</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_cuenta" class="form-label required-field">Tipo de Cuenta</label>
                            <select class="form-select" id="tipo_cuenta" name="tipo_cuenta" required>
                                <option value="CA" <?= ($modoEdicion && $cuentaExistente['acmtyp'] == 'CA') ? 'selected' : '' ?>>CA - Cuenta de Ahorros</option>
                                <option value="CC" <?= ($modoEdicion && $cuentaExistente['acmtyp'] == 'CC') ? 'selected' : '' ?>>CC - Cuenta Corriente</option>
                                <option value="PL" <?= ($modoEdicion && $cuentaExistente['acmtyp'] == 'PL') ? 'selected' : '' ?>>PL - Préstamo</option>
                                <option value="TD" <?= ($modoEdicion && $cuentaExistente['acmtyp'] == 'TD') ? 'selected' : '' ?>>TD - Depósito a Término</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?= ($modoEdicion && $cuentaExistente['acmcls'] == 'N') ? 'selected' : '' ?>>N - Normal</option>
                                <option value="J" <?= ($modoEdicion && $cuentaExistente['acmcls'] == 'J') ? 'selected' : '' ?>>J - Jurídica</option>
                                <option value="E" <?= ($modoEdicion && $cuentaExistente['acmcls'] == 'E') ? 'selected' : '' ?>>E - Extranjera</option>
                                <option value="V" <?= ($modoEdicion && $cuentaExistente['acmcls'] == 'V') ? 'selected' : '' ?>>V - VIP</option>
                                <option value="P" <?= ($modoEdicion && $cuentaExistente['acmcls'] == 'P') ? 'selected' : '' ?>>P - Premium</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="saldo_inicial" class="form-label required-field">Saldo Inicial</label>
                            <input type="number" step="0.01" class="form-control" id="saldo_inicial" 
                                   name="saldo_inicial" value="<?= $modoEdicion ? htmlspecialchars($cuentaExistente['acmbal']) : '0.00' ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="disponible" class="form-label required-field">Disponible</label>
                            <input type="number" step="0.01" class="form-control" id="disponible" 
                                   name="disponible" value="<?= $modoEdicion ? htmlspecialchars($cuentaExistente['acmavl']) : '0.00' ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?= ($modoEdicion && $cuentaExistente['acmsta'] == 'A') ? 'selected' : '' ?>>Activo</option>
                                <option value="I" <?= ($modoEdicion && $cuentaExistente['acmsta'] == 'I') ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha de Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?= $modoEdicion ? htmlspecialchars($cuentaExistente['acmopn']) : date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="listar.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= $modoEdicion ? 'Actualizar' : 'Guardar' ?> Cuenta
                </button>
            </div>
        </form>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación básica del número de cuenta
        document.getElementById('numero_cuenta').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 20) {
                this.value = this.value.substring(0, 20);
            }
        });

        // Sincronizar saldo inicial con disponible
        document.getElementById('saldo_inicial').addEventListener('change', function() {
            document.getElementById('disponible').value = this.value;
        });
    </script>
</body>
</html>