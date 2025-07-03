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

// Obtener el número de cuenta a editar
$numeroCuenta = $_GET['id'] ?? '';
if (empty($numeroCuenta)) {
    header('Location: listar.php');
    exit;
}

// Obtener datos de la cuenta
try {
    $pdo = getPDO();
    
    // Obtener información de la cuenta
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
    $stmt->execute([':cuenta' => $numeroCuenta]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        $_SESSION['error'] = "La cuenta no existe";
        header('Location: listar.php');
        exit;
    }
    
    // Obtener lista de clientes, sucursales y productos bancarios
    $clientes = $pdo->query("SELECT cuscun, CONCAT(cusna1, ' ', cusna2) AS nombre FROM cumst ORDER BY nombre")->fetchAll();
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll();
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error al obtener datos: " . $e->getMessage());
    $_SESSION['error'] = "Error al cargar los datos de la cuenta";
    header('Location: listar.php');
    exit;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar y sanitizar datos
        $clienteId = (int)$_POST['cliente_id'];
        $saldoActual = (float)$_POST['saldo_actual'];
        $disponible = (float)$_POST['disponible'];
        $estado = $_POST['estado'] === 'A' ? 'A' : 'I';
        $sucursal = (int)$_POST['sucursal'];
        $productoBancario = (int)$_POST['producto_bancario'];
        $tipoCuenta = substr(trim($_POST['tipo_cuenta']), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta']), 0, 1);
        $comentarios = substr(trim($_POST['comentarios']), 0, 100);
        
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
        
        // Actualizar en la base de datos
        // NOTA: acmlsb (saldo anterior) no se modifica directamente, se actualiza con triggers o lógica de negocio
        $sql = "UPDATE acmst SET 
                acmcun = :cliente, 
                acmbrn = :sucursal, 
                acmprd = :producto, 
                acmtyp = :tipo, 
                acmcls = :clase, 
                acmbal = :saldo, 
                acmavl = :disponible, 
                acmsta = :estado, 
                acmrmk = :comentarios,
                acmlut = NOW()
                WHERE acmacc = :cuenta";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cuenta' => $numeroCuenta,
            ':cliente' => $clienteId,
            ':sucursal' => $sucursal,
            ':producto' => $productoBancario,
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':saldo' => $saldoActual,
            ':disponible' => $disponible,
            ':estado' => $estado,
            ':comentarios' => $comentarios
        ]);
        
        // Registrar el cambio de saldo si fue modificado
        if ($cuenta['acmbal'] != $saldoActual) {
            $sqlHistorial = "INSERT INTO achst 
                            (hstacc, hstdat, hstseq, hststa, hstrsn, hstusr) 
                            VALUES 
                            (:cuenta, CURDATE(), 1, :estado, 'Actualización manual de saldo', :usuario)";
            $stmtHistorial = $pdo->prepare($sqlHistorial);
            $stmtHistorial->execute([
                ':cuenta' => $numeroCuenta,
                ':estado' => $estado,
                ':usuario' => $_SESSION['username']
            ]);
        }
        
        // Redirigir al listado con mensaje de éxito
        $_SESSION['mensaje'] = "Cuenta actualizada exitosamente";
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
    <title>Editar Cuenta Bancaria</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/cuentas.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Editar Cuenta Bancaria</h2>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-container">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="card mb-4">
                            <div class="card-header">
                                Información Básica
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Número de Cuenta</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($cuenta['acmacc']) ?>" readonly>
                                    <small class="form-text text-muted">No editable</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cliente_id" class="form-label">Cliente</label>
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

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal" class="form-label">Sucursal</label>
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
                                        <label for="producto_bancario" class="form-label">Producto Bancario</label>
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

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="tipo_cuenta" class="form-label">Tipo de Cuenta</label>
                                        <select class="form-select" id="tipo_cuenta" name="tipo_cuenta" required>
                                            <option value="CA" <?= $cuenta['acmtyp'] == 'CA' ? 'selected' : '' ?>>CA - Cuenta de Ahorros</option>
                                            <option value="CC" <?= $cuenta['acmtyp'] == 'CC' ? 'selected' : '' ?>>CC - Cuenta Corriente</option>
                                            <option value="PL" <?= $cuenta['acmtyp'] == 'PL' ? 'selected' : '' ?>>PL - Préstamo</option>
                                            <option value="TD" <?= $cuenta['acmtyp'] == 'TD' ? 'selected' : '' ?>>TD - Depósito a Término</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="clase_cuenta" class="form-label">Clase de Cuenta</label>
                                        <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                            <option value="N" <?= $cuenta['acmcls'] == 'N' ? 'selected' : '' ?>>N - Normal</option>
                                            <option value="J" <?= $cuenta['acmcls'] == 'J' ? 'selected' : '' ?>>J - Jurídica</option>
                                            <option value="E" <?= $cuenta['acmcls'] == 'E' ? 'selected' : '' ?>>E - Extranjera</option>
                                            <option value="V" <?= $cuenta['acmcls'] == 'V' ? 'selected' : '' ?>>V - VIP</option>
                                            <option value="P" <?= $cuenta['acmcls'] == 'P' ? 'selected' : '' ?>>P - Premium</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                Saldos y Estado
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Saldo Anterior</label>
                                        <input type="text" class="form-control" value="<?= number_format($cuenta['acmlsb'], 2) ?>" readonly>
                                        <small class="form-text text-muted">No editable</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="saldo_actual" class="form-label">Saldo Actual</label>
                                        <input type="number" step="0.01" class="form-control" id="saldo_actual" 
                                               name="saldo_actual" value="<?= number_format($cuenta['acmbal'], 2) ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="disponible" class="form-label">Disponible</label>
                                        <input type="number" step="0.01" class="form-control" id="disponible" 
                                               name="disponible" value="<?= number_format($cuenta['acmavl'], 2) ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="estado" class="form-label">Estado</label>
                                        <select class="form-select" id="estado" name="estado" required>
                                            <option value="A" <?= $cuenta['acmsta'] == 'A' ? 'selected' : '' ?>>Activo</option>
                                            <option value="I" <?= $cuenta['acmsta'] == 'I' ? 'selected' : '' ?>>Inactivo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha de Apertura</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($cuenta['acmopn']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                Información Adicional
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="comentarios" class="form-label">Comentarios</label>
                                    <textarea class="form-control" id="comentarios" name="comentarios" rows="3"><?= htmlspecialchars($cuenta['acmrmk'] ?? '') ?></textarea>
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
                </div>
            </div>
        </div>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación básica de campos numéricos
        document.getElementById('saldo_actual').addEventListener('change', function() {
            const disponible = document.getElementById('disponible');
            if (parseFloat(this.value) < parseFloat(disponible.value)) {
                disponible.value = this.value;
            }
        });

        document.getElementById('disponible').addEventListener('change', function() {
            const saldoActual = document.getElementById('saldo_actual');
            if (parseFloat(this.value) > parseFloat(saldoActual.value)) {
                this.value = saldoActual.value;
                alert('El disponible no puede ser mayor al saldo actual');
            }
        });
    </script>
</body>
</html>