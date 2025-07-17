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
$numeroCuenta = isset($_GET['id']) ? trim($_GET['id']) : null;
if (!$numeroCuenta) {
    header('Location: listar.php');
    exit;
}

// Obtener datos de la cuenta
try {
    $pdo = getPDO();
    
    // Obtener información de la cuenta
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
    $stmt->bindParam(':cuenta', $numeroCuenta);
    $stmt->execute();
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'La cuenta especificada no existe'
        ];
        header('Location: listar.php');
        exit;
    }

    // Obtener lista de clientes activos y sucursales
    $clientes = $pdo->query("SELECT cuscun, CONCAT(cusna1, ' ', cusln1) AS nombre FROM cumst WHERE cussts = 'A' ORDER BY cusna1")->fetchAll(PDO::FETCH_ASSOC);
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
    error_log($error);
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
        $estado = ($_POST['estado'] === 'A') ? 'A' : 'I';
        $fechaApertura = $_POST['fecha_apertura'] ?? date('Y-m-d');

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

        // Actualizar cuenta principal
        $sqlCuenta = "UPDATE acmst SET 
                     acmcun = :cliente, 
                     acmbrn = :sucursal, 
                     acmccy = :moneda, 
                     acmprd = :producto, 
                     acmtyp = :tipo, 
                     acmcls = :clase, 
                     acmsta = :estado, 
                     acmopn = :fecha, 
                     acmlut = NOW(),
                     acmlau = :usuario
                     WHERE acmacc = :cuenta";
        
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
                                <?php echo htmlspecialchars($numeroCuenta); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cliente_id" class="form-label required-field">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo htmlspecialchars($cliente['cuscun']); ?>"
                                        <?php echo ((isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['cuscun']) || $cuenta['acmcun'] == $cliente['cuscun']) ? 'selected' : ''; ?>>
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
                                        <?php echo ((isset($_POST['sucursal']) && $_POST['sucursal'] == $suc['acmbrn']) || $cuenta['acmbrn'] == $suc['acmbrn']) ? 'selected' : ''; ?>>
                                        Sucursal <?php echo htmlspecialchars($suc['acmbrn']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="moneda" class="form-label required-field">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda" required>
                                <option value="BS" <?php echo ((!isset($_POST['moneda']) && $cuenta['acmccy'] === 'BS') || (isset($_POST['moneda']) && $_POST['moneda'] === 'BS')) ? 'selected' : ''; ?>>BS - Bolívar</option>
                                <option value="USD" <?php echo ((!isset($_POST['moneda']) && $cuenta['acmccy'] === 'USD') || (isset($_POST['moneda']) && $_POST['moneda'] === 'USD')) ? 'selected' : ''; ?>>USD - Dólar</option>
                                <option value="EUR" <?php echo ((!isset($_POST['moneda']) && $cuenta['acmccy'] === 'EUR') || (isset($_POST['moneda']) && $_POST['moneda'] === 'EUR')) ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="COP" <?php echo ((!isset($_POST['moneda']) && $cuenta['acmccy'] === 'COP') || (isset($_POST['moneda']) && $_POST['moneda'] === 'COP')) ? 'selected' : ''; ?>>COP - Peso Colombiano</option>
                                <option value="BRL" <?php echo ((!isset($_POST['moneda']) && $cuenta['acmccy'] === 'BRL') || (isset($_POST['moneda']) && $_POST['moneda'] === 'BRL')) ? 'selected' : ''; ?>>BRL - Real Brasileño</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="producto_bancario" class="form-label required-field">Producto Bancario</label>
                            <select class="form-select" id="producto_bancario" name="producto_bancario" required>
                                <option value="">Seleccione producto</option>
                                <option value="10" <?php echo ((!isset($_POST['producto_bancario']) && $cuenta['acmprd'] == '10') || (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '10')) ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo ((!isset($_POST['producto_bancario']) && $cuenta['acmprd'] == '20') || (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '20')) ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo ((!isset($_POST['producto_bancario']) && $cuenta['acmprd'] == '30') || (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '30')) ? 'selected' : ''; ?>>30</option>
                                <option value="40" <?php echo ((!isset($_POST['producto_bancario']) && $cuenta['acmprd'] == '40') || (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '40')) ? 'selected' : ''; ?>>40</option>
                                <option value="50" <?php echo ((!isset($_POST['producto_bancario']) && $cuenta['acmprd'] == '50') || (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == '50')) ? 'selected' : ''; ?>>50</option>
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
                                <option value="CA" <?php echo ((!isset($_POST['tipo_cuenta']) && $cuenta['acmtyp'] === 'CA') || (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'CA')) ? 'selected' : ''; ?>>CA - Ahorros</option>
                                <option value="CC" <?php echo ((!isset($_POST['tipo_cuenta']) && $cuenta['acmtyp'] === 'CC') || (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'CC')) ? 'selected' : ''; ?>>CC - Corriente</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo ((!isset($_POST['clase_cuenta']) && $cuenta['acmcls'] === 'N') || (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'N')) ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo ((!isset($_POST['clase_cuenta']) && $cuenta['acmcls'] === 'J') || (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'J')) ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="V" <?php echo ((!isset($_POST['clase_cuenta']) && $cuenta['acmcls'] === 'V') || (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'V')) ? 'selected' : ''; ?>>V - VIP</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo ((!isset($_POST['estado']) && $cuenta['acmsta'] === 'A') || (isset($_POST['estado']) && $_POST['estado'] === 'A')) ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ((!isset($_POST['estado']) && $cuenta['acmsta'] === 'I') || (isset($_POST['estado']) && $_POST['estado'] === 'I')) ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?php echo isset($_POST['fecha_apertura']) ? htmlspecialchars($_POST['fecha_apertura']) : htmlspecialchars($cuenta['acmopn']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Saldo Actual</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo number_format($cuenta['acmbal'], 2); ?>
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
        document.getElementById('fecha_apertura').addEventListener('focus', function() {
            this.type = 'date';
        });
        
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) new bootstrap.Alert(alert).close();
        }, 5000);
    </script>
</body>
</html>