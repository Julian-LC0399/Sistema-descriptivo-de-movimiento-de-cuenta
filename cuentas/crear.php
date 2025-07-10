<?php
// cuentas/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden crear cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener lista de clientes, sucursales y productos bancarios
try {
    $pdo = getPDO();
    $clientes = $pdo->query("SELECT cuscun, cusna1 AS nombre FROM cumst WHERE cussts = 'A' ORDER BY cusna1")->fetchAll();
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll();
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll();
    
    // Monedas disponibles
    $monedas = [
        ['codigo' => 'VES', 'nombre' => 'Bolívar'],
        ['codigo' => 'USD', 'nombre' => 'Dólar Estadounidense'],
        ['codigo' => 'EUR', 'nombre' => 'Euro']
    ];
} catch (PDOException $e) {
    $clientes = [];
    $sucursales = [];
    $productos = [];
    $monedas = [];
    error_log("Error al obtener datos: " . $e->getMessage());
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        
        // Validar y sanitizar datos
        $clienteId = (int)$_POST['cliente_id'];
        $estado = $_POST['estado'] === 'A' ? 'A' : 'I';
        $fechaApertura = $_POST['fecha_apertura'];
        $sucursal = (int)$_POST['sucursal'];
        $productoBancario = (int)$_POST['producto_bancario'];
        $tipoCuenta = substr(trim($_POST['tipo_cuenta']), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta']), 0, 1);
        $moneda = in_array($_POST['moneda'], ['VES', 'USD', 'EUR']) ? $_POST['moneda'] : 'VES';
        
        // Validaciones básicas
        if (empty($clienteId) || empty($sucursal) || empty($productoBancario)) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }
        
        // Validar rangos numéricos
        if ($productoBancario < 1 || $productoBancario > 99) {
            throw new Exception("El producto bancario debe ser entre 1 y 99");
        }
        if ($sucursal < 1 || $sucursal > 99) {
            throw new Exception("El número de sucursal debe ser entre 1 y 99");
        }
        if ($clienteId < 1 || $clienteId > 9999999999) {
            throw new Exception("ID de cliente inválido (máximo 10 dígitos)");
        }
        
        // Verificar si el cliente existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cumst WHERE cuscun = :cliente AND cussts = 'A'");
        $stmt->execute([':cliente' => $clienteId]);
        if ($stmt->fetchColumn() === 0) {
            throw new Exception("El cliente seleccionado no existe o está inactivo");
        }
        
        // Generación del número de cuenta
        $intentos = 0;
        $numeroCuenta = '';
        $cuentaExistente = true;
        $clienteIdOriginal = $clienteId;

        while ($cuentaExistente && $intentos < 5) {
            $numeroCuenta = sprintf("0128%02d%02d%02d%010d", 0, $productoBancario, $sucursal, $clienteId);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
            $stmt->execute([':cuenta' => $numeroCuenta]);
            $cuentaExistente = $stmt->fetchColumn() > 0;
            
            if ($cuentaExistente) {
                $clienteId = $clienteIdOriginal + ++$intentos;
                if ($clienteId > 9999999999) $clienteId = $clienteIdOriginal;
            }
        }

        if ($cuentaExistente) {
            $numeroCuenta = sprintf("0128%02d%02d%02d%010d", 0, $productoBancario, $sucursal, substr(uniqid(), -10));
            $stmt->execute([':cuenta' => $numeroCuenta]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("No se pudo generar un número de cuenta único. Contacte al administrador.");
            }
        }
        
        // Insertar en la base de datos
        $sql = "INSERT INTO acmst 
                (acmacc, acmcun, acmbrn, acmccy, acmprd, acmtyp, acmcls, acmlsb, acmbal, acmavl, acmsta, acmopn) 
                VALUES 
                (:cuenta, :cliente, :sucursal, :moneda, :producto, :tipo, :clase, 0, 0, 0, :estado, :fecha)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cuenta' => $numeroCuenta,
            ':cliente' => $clienteIdOriginal,
            ':sucursal' => $sucursal,
            ':moneda' => $moneda,
            ':producto' => $productoBancario,
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':estado' => $estado,
            ':fecha' => $fechaApertura
        ]);
        
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Cuenta creada exitosamente. Número: $numeroCuenta | Moneda: $moneda"
        ];
        header('Location: listar.php');
        exit;
        
    } catch (PDOException $e) {
        $error = "Error al crear la cuenta: " . $e->getMessage();
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
                            <label for="producto_bancario" class="form-label required-field">Producto Bancario</label>
                            <select class="form-select" id="producto_bancario" name="producto_bancario" required>
                                <option value="">Seleccione producto</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value="<?php echo htmlspecialchars($prod['acmprd']); ?>"
                                        <?php echo (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == $prod['acmprd']) ? 'selected' : ''; ?>>
                                        Producto <?php echo htmlspecialchars($prod['acmprd']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="moneda" class="form-label required-field">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda" required>
                                <?php foreach ($monedas as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['codigo']); ?>"
                                        <?php echo (isset($_POST['moneda']) && $_POST['moneda'] == $m['codigo']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['codigo']); ?> - <?php echo htmlspecialchars($m['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
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
                        <div class="col-md-6 mb-3">
                            <label for="tipo_cuenta" class="form-label required-field">Tipo de Cuenta</label>
                            <select class="form-select" id="tipo_cuenta" name="tipo_cuenta" required>
                                <option value="CA" <?php echo (!isset($_POST['tipo_cuenta']) || $_POST['tipo_cuenta'] === 'CA') ? 'selected' : ''; ?>>CA - Ahorros</option>
                                <option value="CC" <?php echo (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'CC') ? 'selected' : ''; ?>>CC - Corriente</option>
                                <option value="PL" <?php echo (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'PL') ? 'selected' : ''; ?>>PL - Préstamo</option>
                                <option value="TD" <?php echo (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'TD') ? 'selected' : ''; ?>>TD - Depósito</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo (!isset($_POST['clase_cuenta']) || $_POST['clase_cuenta'] === 'N') ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'J') ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="E" <?php echo (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'E') ? 'selected' : ''; ?>>E - Extranjera</option>
                                <option value="V" <?php echo (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'V') ? 'selected' : ''; ?>>V - VIP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo (!isset($_POST['estado']) || $_POST['estado'] === 'A') ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'I') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?php echo isset($_POST['fecha_apertura']) ? htmlspecialchars($_POST['fecha_apertura']) : date('Y-m-d'); ?>" required>
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