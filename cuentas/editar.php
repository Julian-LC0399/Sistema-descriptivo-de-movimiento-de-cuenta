<?php
// cuentas/editar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Configurar zona horaria para Venezuela
date_default_timezone_set('America/Caracas');

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden editar cuentas
$allowedRoles = ['admin', 'gerente'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: listar.php');
    exit;
}

// Obtener ID de cuenta a editar
$idCuenta = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($idCuenta)) {
    $_SESSION['error'] = "No se especificó una cuenta para editar";
    header('Location: listar.php');
    exit;
}

// Obtener datos de la cuenta
try {
    $pdo = getPDO();
    
    // Obtener información de la cuenta
    $stmt = $pdo->prepare("SELECT 
                          a.acmacc AS cuenta,
                          a.acmcun AS cliente_id,
                          CONCAT(c.cusna1, ' ', c.cusln1) AS nombre_cliente,
                          a.acmbrn AS sucursal,
                          a.acmccy AS moneda,
                          a.acmprd AS producto,
                          a.acmtyp AS tipo_cuenta,
                          a.acmcls AS clase_cuenta,
                          a.acmsta AS estado,
                          DATE(a.acmopn) AS fecha_apertura
                      FROM acmst a
                      JOIN cumst c ON a.acmcun = c.cuscun
                      WHERE a.acmacc = :id");
    $stmt->bindParam(':id', $idCuenta);
    $stmt->execute();
    
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        $_SESSION['error'] = "La cuenta especificada no existe";
        header('Location: listar.php');
        exit;
    }
    
    // Obtener listas para selects
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error al obtener datos para edición: " . $e->getMessage());
    $_SESSION['error'] = "Error al cargar los datos de la cuenta";
    header('Location: listar.php');
    exit;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar datos (cliente, número de cuenta y fecha apertura no se editan)
        $sucursal = filter_input(INPUT_POST, 'sucursal', FILTER_VALIDATE_INT);
        $productoBancario = filter_input(INPUT_POST, 'producto_bancario', FILTER_VALIDATE_INT);
        $moneda = in_array($_POST['moneda'] ?? '', ['BS', 'USD', 'EUR', 'COP', 'BRL']) ? $_POST['moneda'] : 'BS';
        $tipoCuenta = in_array($_POST['tipo_cuenta'] ?? '', ['CA', 'CC']) ? $_POST['tipo_cuenta'] : 'CA';
        $claseCuenta = in_array($_POST['clase_cuenta'] ?? '', ['N', 'J', 'V']) ? $_POST['clase_cuenta'] : 'N';
        $estado = in_array($_POST['estado'] ?? '', ['A', 'I']) ? $_POST['estado'] : 'A';

        // Validaciones básicas
        if (!$sucursal || !$productoBancario) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }

        // Actualizar cuenta (sin modificar cliente, número de cuenta ni fecha apertura)
        $sql = "UPDATE acmst SET 
                acmbrn = :sucursal,
                acmccy = :moneda,
                acmprd = :producto,
                acmtyp = :tipo,
                acmcls = :clase,
                acmsta = :estado,
                acmlut = NOW(),
                acmlau = :usuario
                WHERE acmacc = :cuenta";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':cuenta' => $idCuenta,
            ':sucursal' => $sucursal,
            ':moneda' => $moneda,
            ':producto' => $productoBancario,
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':estado' => $estado,
            ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
        ];
        
        if (!$stmt->execute($params)) {
            throw new Exception("Error al actualizar la cuenta: " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit();
        
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Cuenta $idCuenta actualizada exitosamente"
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
                                <?php echo htmlspecialchars($cuenta['cuenta']); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo htmlspecialchars($cuenta['nombre_cliente']); ?> (ID: <?php echo htmlspecialchars($cuenta['cliente_id']); ?>)
                            </div>
                            <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($cuenta['cliente_id']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sucursal" class="form-label required-field">Sucursal</label>
                            <select class="form-select" id="sucursal" name="sucursal" required>
                                <option value="">Seleccione sucursal</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo htmlspecialchars($suc['acmbrn']); ?>"
                                        <?php echo ($suc['acmbrn'] == $cuenta['sucursal']) ? 'selected' : ''; ?>>
                                        Sucursal <?php echo htmlspecialchars($suc['acmbrn']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="moneda" class="form-label required-field">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda" required>
                                <option value="BS" <?php echo ($cuenta['moneda'] === 'BS') ? 'selected' : ''; ?>>BS - Bolívar</option>
                                <option value="USD" <?php echo ($cuenta['moneda'] === 'USD') ? 'selected' : ''; ?>>USD - Dólar</option>
                                <option value="EUR" <?php echo ($cuenta['moneda'] === 'EUR') ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="COP" <?php echo ($cuenta['moneda'] === 'COP') ? 'selected' : ''; ?>>COP - Peso Colombiano</option>
                                <option value="BRL" <?php echo ($cuenta['moneda'] === 'BRL') ? 'selected' : ''; ?>>BRL - Real Brasileño</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="producto_bancario" class="form-label required-field">Producto Bancario</label>
                            <select class="form-select" id="producto_bancario" name="producto_bancario" required>
                                <option value="">Seleccione producto</option>
                                <option value="10" <?php echo ($cuenta['producto'] == '10') ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo ($cuenta['producto'] == '20') ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo ($cuenta['producto'] == '30') ? 'selected' : ''; ?>>30</option>
                                <option value="40" <?php echo ($cuenta['producto'] == '40') ? 'selected' : ''; ?>>40</option>
                                <option value="50" <?php echo ($cuenta['producto'] == '50') ? 'selected' : ''; ?>>50</option>
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
                                <option value="CA" <?php echo ($cuenta['tipo_cuenta'] === 'CA') ? 'selected' : ''; ?>>CA - Ahorros</option>
                                <option value="CC" <?php echo ($cuenta['tipo_cuenta'] === 'CC') ? 'selected' : ''; ?>>CC - Corriente</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo ($cuenta['clase_cuenta'] === 'N') ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo ($cuenta['clase_cuenta'] === 'J') ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="V" <?php echo ($cuenta['clase_cuenta'] === 'V') ? 'selected' : ''; ?>>V - VIP</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo ($cuenta['estado'] === 'A') ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ($cuenta['estado'] === 'I') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Apertura</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo date('d/m/Y', strtotime($cuenta['fecha_apertura'])); ?> (no editable)
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Última Actualización</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo date('d/m/Y H:i:s'); ?> (automática)
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