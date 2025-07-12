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

// Obtener datos de la cuenta a editar
$numeroCuenta = $_GET['id'] ?? '';
$cuenta = null;
$error = '';

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

    // Obtener lista de clientes, sucursales y productos bancarios
    $clientes = $pdo->query("SELECT cuscun, cusna1 AS nombre FROM cumst WHERE cussts = 'A' ORDER BY cusna1")->fetchAll(PDO::FETCH_ASSOC);
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll(PDO::FETCH_ASSOC);
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll(PDO::FETCH_ASSOC);
    
    // Monedas disponibles
    $monedas = [
        ['codigo' => 'BS', 'nombre' => 'Bolívar'],
        ['codigo' => 'USD', 'nombre' => 'Dólar Estadounidense'],
        ['codigo' => 'EUR', 'nombre' => 'Euro'],
        ['codigo' => 'COP', 'nombre' => 'Peso Colombiano'],
        ['codigo' => 'BRL', 'nombre' => 'Real Brasileño']
    ];

} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
    error_log($error);
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log($error);
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar datos
        $estado = ($_POST['estado'] === 'A') ? 'A' : 'I';
        $tipoCuenta = substr(trim($_POST['tipo_cuenta'] ?? ''), 0, 2);
        $claseCuenta = substr(trim($_POST['clase_cuenta'] ?? ''), 0, 1);
        $moneda = in_array($_POST['moneda'] ?? '', ['BS', 'USD', 'EUR', 'COP', 'BRL']) ? $_POST['moneda'] : 'BS';
        $cedula = substr(trim($_POST['cedula'] ?? ''), 0, 20);

        // Validaciones básicas
        if (empty($cedula)) {
            throw new Exception("La cédula es obligatoria");
        }

        // Actualizar cuenta principal
        $sqlCuenta = "UPDATE acmst SET 
                     acmidn = :cedula,
                     acmccy = :moneda,
                     acmtyp = :tipo,
                     acmcls = :clase,
                     acmsta = :estado
                     WHERE acmacc = :cuenta";
        
        $paramsCuenta = [
            ':cuenta' => $numeroCuenta,
            ':cedula' => $cedula,
            ':moneda' => $moneda,
            ':tipo' => $tipoCuenta,
            ':clase' => $claseCuenta,
            ':estado' => $estado
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

// Si no se encontró la cuenta, redirigir
if (!$cuenta && empty($error)) {
    header('Location: listar.php');
    exit;
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
        
        <?php if ($cuenta): ?>
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
                                <?php 
                                $clienteNombre = 'Cliente no encontrado';
                                foreach ($clientes as $c) {
                                    if ($c['cuscun'] == $cuenta['acmcun']) {
                                        $clienteNombre = htmlspecialchars($c['nombre'] . ' (ID: ' . $c['cuscun'] . ')');
                                        break;
                                    }
                                }
                                echo $clienteNombre;
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="cedula" class="form-label required-field">Cédula</label>
                            <input type="text" class="form-control" id="cedula" name="cedula" 
                                   value="<?php echo htmlspecialchars($cuenta['acmidn'] ?? ''); ?>" 
                                   required maxlength="20" placeholder="Ej: V12345678">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sucursal</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                Sucursal <?php echo htmlspecialchars($cuenta['acmbrn']); ?>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Producto Bancario</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                Producto <?php echo htmlspecialchars($cuenta['acmprd']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="moneda" class="form-label required-field">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda" required>
                                <?php foreach ($monedas as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['codigo']); ?>"
                                        <?php echo ($cuenta['acmccy'] == $m['codigo']) ? 'selected' : ''; ?>>
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
                                <option value="CA" <?php echo ($cuenta['acmtyp'] === 'CA') ? 'selected' : ''; ?>>CA - Ahorros</option>
                                <option value="CC" <?php echo ($cuenta['acmtyp'] === 'CC') ? 'selected' : ''; ?>>CC - Corriente</option>
                                <option value="PL" <?php echo ($cuenta['acmtyp'] === 'PL') ? 'selected' : ''; ?>>PL - Préstamo</option>
                                <option value="TD" <?php echo ($cuenta['acmtyp'] === 'TD') ? 'selected' : ''; ?>>TD - Depósito</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <option value="N" <?php echo ($cuenta['acmcls'] === 'N') ? 'selected' : ''; ?>>N - Normal</option>
                                <option value="J" <?php echo ($cuenta['acmcls'] === 'J') ? 'selected' : ''; ?>>J - Jurídica</option>
                                <option value="E" <?php echo ($cuenta['acmcls'] === 'E') ? 'selected' : ''; ?>>E - Extranjera</option>
                                <option value="V" <?php echo ($cuenta['acmcls'] === 'V') ? 'selected' : ''; ?>>V - VIP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="A" <?php echo ($cuenta['acmsta'] === 'A') ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ($cuenta['acmsta'] === 'I') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Apertura</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">
                                <?php echo htmlspecialchars($cuenta['acmopn']); ?>
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
        <?php endif; ?>
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