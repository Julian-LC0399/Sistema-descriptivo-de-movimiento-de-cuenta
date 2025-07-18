<?php
// cuentas/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Configurar zona horaria para Venezuela
date_default_timezone_set('America/Caracas');

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden crear cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener lista de clientes activos y sucursales
try {
    $pdo = getPDO();
    $clientes = $pdo->query("SELECT cuscun, CONCAT(cusna1, ' ', cusln1) AS nombre FROM cumst WHERE cussts = 'A' ORDER BY cusna1")->fetchAll(PDO::FETCH_ASSOC);
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
    error_log($error);
}

// Función para generar número de cuenta único
function generarNumeroCuenta($pdo, $producto, $sucursal, $clienteId) {
    $intentos = 0;
    $clienteOriginal = $clienteId;
    
    do {
        $numeroCuenta = sprintf("0128%02d%02d%02d%010d", 0, $producto, $sucursal, $clienteId);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
        $stmt->bindValue(':cuenta', $numeroCuenta);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            return $numeroCuenta;
        }
        
        $clienteId = $clienteOriginal + ++$intentos;
        if ($clienteId > 9999999999) $clienteId = $clienteOriginal;
        
    } while ($intentos < 5);

    // Si no se encontró después de 5 intentos, generar uno único
    $numeroCuenta = sprintf("0128%02d%02d%02d%010d", 0, $producto, $sucursal, substr(uniqid(), -10));
    
    $stmt->execute([':cuenta' => $numeroCuenta]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("No se pudo generar un número de cuenta único. Contacte al administrador.");
    }
    
    return $numeroCuenta;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar datos
        $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
        $sucursal = filter_input(INPUT_POST, 'sucursal', FILTER_VALIDATE_INT);
        $productoBancario = filter_input(INPUT_POST, 'producto_bancario', FILTER_VALIDATE_INT);
        $moneda = in_array($_POST['moneda'] ?? '', ['BS', 'USD', 'EUR', 'COP', 'BRL']) ? $_POST['moneda'] : 'BS';
        $tipoCuenta = in_array($_POST['tipo_cuenta'] ?? '', ['CA', 'CC']) ? $_POST['tipo_cuenta'] : 'CA';
        $claseCuenta = in_array($_POST['clase_cuenta'] ?? '', ['N', 'J', 'V']) ? $_POST['clase_cuenta'] : 'N';
        $estado = 'A'; // Estado siempre activo
        $fechaApertura = date('Y-m-d'); // Siempre fecha actual de Venezuela

        // Validaciones básicas
        if (!$clienteId || !$sucursal || !$productoBancario) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }

        if ($productoBancario < 1 || $productoBancario > 99) {
            throw new Exception("El producto bancario debe ser entre 1 y 99");
        }

        if ($sucursal < 1 || $sucursal > 99) {
            throw new Exception("El número de sucursal debe ser entre 1 y 99");
        }

        // Verificar cliente existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cumst WHERE cuscun = :cliente AND cussts = 'A'");
        $stmt->bindParam(':cliente', $clienteId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() === 0) {
            throw new Exception("El cliente seleccionado no existe o está inactivo");
        }

        // Generar número de cuenta único
        $numeroCuenta = generarNumeroCuenta($pdo, $productoBancario, $sucursal, $clienteId);

        // Insertar cuenta principal
        $sqlCuenta = "INSERT INTO acmst 
                     (acmacc, acmcun, acmbrn, acmccy, acmprd, acmtyp, acmcls, acmlsb, acmbal, acmavl, acmsta, acmopn, acmlau, acmlut) 
                     VALUES 
                     (:cuenta, :cliente, :sucursal, :moneda, :producto, :tipo, :clase, 0, 0, 0, :estado, :fecha, :usuario, NOW())";
        
        $paramsCuenta = [
            ':cuenta' => $numeroCuenta,
            ':cliente' => $clienteId,
            ':sucursal' => $sucursal,
            ':moneda' => $moneda,
            ':producto' => $productoBancario,
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':estado' => $estado,
            ':fecha' => $fechaApertura,
            ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
        ];
        
        $stmt = $pdo->prepare($sqlCuenta);
        if (!$stmt->execute($paramsCuenta)) {
            throw new Exception("Error al crear la cuenta: " . implode(" ", $stmt->errorInfo()));
        }

        // Insertar referencia de cuenta
        $sqlReferencia = "INSERT INTO acref 
                         (acrnac, acrcun, acrrac, acrtyp, acrsts) 
                         VALUES 
                         (:cuenta, :cliente, :cuenta_ref, 'O', 'A')";
        
        $paramsRef = [
            ':cuenta' => $numeroCuenta,
            ':cliente' => $clienteId,
            ':cuenta_ref' => $numeroCuenta
        ];
        
        $stmtRef = $pdo->prepare($sqlReferencia);
        if (!$stmtRef->execute($paramsRef)) {
            throw new Exception("Error al crear la referencia: " . implode(" ", $stmtRef->errorInfo()));
        }

        $pdo->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Cuenta $numeroCuenta creada exitosamente con su referencia correspondiente"
        ];
        
        header('Location: listar.php');
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error en la base de datos: " . $e->getMessage();
        error_log($error);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        error_log($error);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Cuenta - Banco Caroni</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Crear Nueva Cuenta Bancaria</h2>
        
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
                                Se generará automáticamente
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cliente_id" class="form-label required-field">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo htmlspecialchars($cliente['cuscun']); ?>"
                                        <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['cuscun']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cliente['nombre']); ?> (ID: <?php echo htmlspecialchars($cliente['cuscun']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sucursal" class="form-label required-field">Sucursal</label>
                            <select class="form-select" id="sucursal" name="sucursal" required>
                                <option value="">Seleccione sucursal</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo htmlspecialchars($suc['acmbrn']); ?>"
                                        <?php echo (isset($_POST['sucursal']) && $_POST['sucursal'] == $suc['acmbrn']) ? 'selected' : ''; ?>>
                                        Sucursal <?php echo htmlspecialchars($suc['acmbrn']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="moneda" class="form-label required-field">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda" required>
                                <option value="BS" <?php echo (!isset($_POST['moneda']) || $_POST['moneda'] === 'BS') ? 'selected' : ''; ?>>BS - Bolívar</option>
                                <option value="USD" <?php echo (isset($_POST['moneda']) && $_POST['moneda'] === 'USD') ? 'selected' : ''; ?>>USD - Dólar</option>
                                <option value="EUR" <?php echo (isset($_POST['moneda']) && $_POST['moneda'] === 'EUR') ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="COP" <?php echo (isset($_POST['moneda']) && $_POST['moneda'] === 'COP') ? 'selected' : ''; ?>>COP - Peso Colombiano</option>
                                <option value="BRL" <?php echo (isset($_POST['moneda']) && $_POST['moneda'] === 'BRL') ? 'selected' : ''; ?>>BRL - Real Brasileño</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="producto_bancario" class="form-label required-field">Producto Bancario</label>
                            <select class="form-select" id="producto_bancario" name="producto_bancario" required>
                                <option value="">Seleccione producto</option>
                                <option value="10" <?php echo (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '10') ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '20') ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '30') ? 'selected' : ''; ?>>30</option>
                                <option value="40" <?php echo (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '40') ? 'selected' : ''; ?>>40</option>
                                <option value="50" <?php echo (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '50') ? 'selected' : ''; ?>>50</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Configuración -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Configuración de la Cuenta</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="tipo_cuenta" class="form-label required-field">Tipo de Cuenta</label>
                            <select class="form-select" id="tipo_cuenta" name="tipo_cuenta" required>
                                <option value="CA" <?php echo (!isset($_POST['tipo_cuenta']) || $_POST['tipo_cuenta'] === 'CA') ? 'selected' : ''; ?>>CA - Ahorros</option>
                                <option value="CC" <?php echo (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'CC') ? 'selected' : ''; ?>>CC - Corriente</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo (!isset($_POST['clase_cuenta']) || $_POST['clase_cuenta'] === 'N') ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'J') ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="V" <?php echo (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'V') ? 'selected' : ''; ?>>V - VIP</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estado</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                Activo (siempre)
                            </div>
                            <input type="hidden" name="estado" value="A">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Apertura</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo date('d/m/Y'); ?> (fecha actual)
                            </div>
                            <input type="hidden" name="fecha_apertura" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Saldo Inicial</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                0.00 (Se establecerá automáticamente)
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
                    <i class="bi bi-save"></i> Guardar Cuenta
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