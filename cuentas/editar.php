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

// Obtener el ID de la cuenta a editar
if (!isset($_GET['id'])) {
    header('Location: listar.php');
    exit;
}

$numeroCuenta = $_GET['id'];

try {
    $pdo = getPDO();
    
    // Obtener datos de la cuenta existente
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = :cuenta");
    $stmt->execute([':cuenta' => $numeroCuenta]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'La cuenta especificada no existe'
        ];
        header('Location: listar.php');
        exit;
    }

    // Obtener datos del cliente asociado
    $stmt = $pdo->prepare("SELECT cusna1 AS nombre FROM cumst WHERE cuscun = :cliente_id");
    $stmt->execute([':cliente_id' => $cuenta['acmcun']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener listas para los selects
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
    error_log("Error al obtener datos: " . $e->getMessage());
    die("Ocurrió un error al recuperar los datos. Por favor intente más tarde.");
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        
        // Validar y sanitizar datos
        $estado = $_POST['estado'] === 'A' ? 'A' : 'I';
        $fechaApertura = $_POST['fecha_apertura'];
        $sucursal = (int)$_POST['sucursal'];
        $productoBancario = (int)$_POST['producto_bancario'];
        $tipoCuenta = substr(trim($_POST['tipo_cuenta']), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta']), 0, 1);
        $moneda = in_array($_POST['moneda'], ['VES', 'USD', 'EUR']) ? $_POST['moneda'] : 'VES';
        
        // Validaciones básicas
        if (empty($sucursal) || empty($productoBancario)) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }
        
        // Validar rangos numéricos
        if ($productoBancario < 1 || $productoBancario > 99) {
            throw new Exception("El producto bancario debe ser entre 1 y 99");
        }
        if ($sucursal < 1 || $sucursal > 99) {
            throw new Exception("El número de sucursal debe ser entre 1 y 99");
        }
        
        // Actualizar en la base de datos
        $sql = "UPDATE acmst SET 
                acmbrn = :sucursal, 
                acmccy = :moneda, 
                acmprd = :producto, 
                acmtyp = :tipo, 
                acmcls = :clase, 
                acmsta = :estado, 
                acmopn = :fecha
                WHERE acmacc = :cuenta";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cuenta' => $numeroCuenta,
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
            'texto' => "Cuenta actualizada exitosamente. Número: $numeroCuenta"
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
                                <?php echo htmlspecialchars($cuenta['acmacc']); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo htmlspecialchars($cliente['nombre'] ?? 'No encontrado'); ?> 
                                (ID: <?php echo htmlspecialchars($cuenta['acmcun']); ?>)
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sucursal" class="form-label required-field">Sucursal</label>
                            <select class="form-select" id="sucursal" name="sucursal" required>
                                <option value="">Seleccione sucursal</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo htmlspecialchars($suc['acmbrn']); ?>"
                                        <?php echo ($cuenta['acmbrn'] == $suc['acmbrn'] || (isset($_POST['sucursal']) && $_POST['sucursal'] == $suc['acmbrn'])) ? 'selected' : ''; ?>>
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
                                        <?php echo ($cuenta['acmprd'] == $prod['acmprd'] || (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == $prod['acmprd'])) ? 'selected' : ''; ?>>
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
                                        <?php echo ($cuenta['acmccy'] == $m['codigo'] || (isset($_POST['moneda']) && $_POST['moneda'] == $m['codigo'])) ? 'selected' : ''; ?>>
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
                                <option value="CA" <?php echo ($cuenta['acmtyp'] == 'CA' || (!isset($_POST['tipo_cuenta']) && empty($cuenta['acmtyp']))) ? 'selected' : ''; ?>>CA - Ahorros</option>
                                <option value="CC" <?php echo ($cuenta['acmtyp'] == 'CC' || (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'CC')) ? 'selected' : ''; ?>>CC - Corriente</option>
                                <option value="PL" <?php echo ($cuenta['acmtyp'] == 'PL' || (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'PL')) ? 'selected' : ''; ?>>PL - Préstamo</option>
                                <option value="TD" <?php echo ($cuenta['acmtyp'] == 'TD' || (isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'TD')) ? 'selected' : ''; ?>>TD - Depósito</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo ($cuenta['acmcls'] == 'N' || (!isset($_POST['clase_cuenta']) && empty($cuenta['acmcls']))) ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo ($cuenta['acmcls'] == 'J' || (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'J')) ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="E" <?php echo ($cuenta['acmcls'] == 'E' || (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'E')) ? 'selected' : ''; ?>>E - Extranjera</option>
                                <option value="V" <?php echo ($cuenta['acmcls'] == 'V' || (isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'V')) ? 'selected' : ''; ?>>V - VIP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo ($cuenta['acmsta'] == 'A' || (!isset($_POST['estado']) && empty($cuenta['acmsta']))) ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ($cuenta['acmsta'] == 'I' || (isset($_POST['estado']) && $_POST['estado'] === 'I')) ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?php echo isset($_POST['fecha_apertura']) ? htmlspecialchars($_POST['fecha_apertura']) : htmlspecialchars($cuenta['acmopn']); ?>" required>
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