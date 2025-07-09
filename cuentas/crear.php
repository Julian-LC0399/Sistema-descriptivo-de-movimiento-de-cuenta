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

// Obtener lista de clientes, sucursales y productos bancarios (MODIFICADO: ahora solo cusna1)
try {
    $pdo = getPDO();
    $clientes = $pdo->query("SELECT cuscun, cusna1 AS nombre FROM cumst WHERE cussts = 'A' ORDER BY cusna1")->fetchAll();
    $sucursales = $pdo->query("SELECT DISTINCT acmbrn FROM acmst ORDER BY acmbrn")->fetchAll();
    $productos = $pdo->query("SELECT DISTINCT acmprd FROM acmst ORDER BY acmprd")->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
    $sucursales = [];
    $productos = [];
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
        
        // Generación del número de cuenta con verificación de unicidad
        $intentos = 0;
        $numeroCuenta = '';
        $cuentaExistente = true;
        $clienteIdOriginal = $clienteId; // Guardamos el ID original

        // Intentar generar un número único (máximo 5 intentos)
        while ($cuentaExistente && $intentos < 5) {
            // Generar número de cuenta (20 caracteres exactos)
            $numeroCuenta = sprintf(
                "0128%02d%02d%02d%010d",  // Formato: 4+2+2+2+10
                0,              // 2 dígitos de relleno
                $productoBancario, // 2 dígitos producto
                $sucursal,      // 2 dígitos sucursal
                $clienteId      // 10 dígitos cliente
            );
            
            // Verificar si el número de cuenta generado ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
            $stmt->execute([':cuenta' => $numeroCuenta]);
            $cuentaExistente = $stmt->fetchColumn() > 0;
            
            $intentos++;
            
            // Si la cuenta existe, modificar ligeramente el ID de cliente
            if ($cuentaExistente) {
                $clienteId = $clienteIdOriginal + $intentos;
                // Asegurarnos que no exceda el límite de 10 dígitos
                if ($clienteId > 9999999999) {
                    $clienteId = $clienteIdOriginal;
                    break;
                }
            }
        }

        if ($cuentaExistente) {
            // Último intento con un valor aleatorio
            $numeroCuenta = sprintf(
                "0128%02d%02d%02d%010d",
                0,
                $productoBancario,
                $sucursal,
                substr(uniqid(), -10) // Usar una parte de un ID único
            );
            
            // Verificar nuevamente
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM acmst WHERE acmacc = :cuenta");
            $stmt->execute([':cuenta' => $numeroCuenta]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("No se pudo generar un número de cuenta único. Por favor contacte al administrador.");
            }
        }
        
        // Verificar longitud exacta (por seguridad)
        if (strlen($numeroCuenta) !== 20) {
            throw new Exception("Error en el formato del número de cuenta generado");
        }
        
        // Insertar en la base de datos
        $sql = "INSERT INTO acmst 
                (acmacc, acmcun, acmbrn, acmccy, acmprd, acmtyp, acmcls, acmlsb, acmbal, acmavl, acmsta, acmopn) 
                VALUES 
                (:cuenta, :cliente, :sucursal, 'VES', :producto, :tipo, :clase, 0, 0, 0, :estado, :fecha)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cuenta' => $numeroCuenta,
            ':cliente' => $clienteIdOriginal, // Usamos el ID original del cliente
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
            'texto' => "Cuenta creada exitosamente. Número de cuenta generado: $numeroCuenta"
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
                                <i class="bi bi-info-circle"></i> Se generará automáticamente al guardar
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cliente_id" class="form-label required-field">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <?php $selected = (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['cuscun']) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($cliente['cuscun']); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($cliente['nombre']); ?> (ID: <?php echo htmlspecialchars($cliente['cuscun']); ?>)
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
                                    <?php $selected = (isset($_POST['sucursal']) && $_POST['sucursal'] == $suc['acmbrn']) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($suc['acmbrn']); ?>" <?php echo $selected; ?>>
                                        Sucursal <?php echo htmlspecialchars($suc['acmbrn']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="producto_bancario" class="form-label required-field">Producto Bancario</label>
                            <select class="form-select" id="producto_bancario" name="producto_bancario" required>
                                <option value="">Seleccione producto</option>
                                <?php foreach ($productos as $prod): ?>
                                    <?php $selected = (isset($_POST['producto_bancario']) && $_POST['producto_bancario'] == $prod['acmprd']) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($prod['acmprd']); ?>" <?php echo $selected; ?>>
                                        Producto <?php echo htmlspecialchars($prod['acmprd']); ?>
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
                                <?php $selected = (!isset($_POST['tipo_cuenta']) || $_POST['tipo_cuenta'] === 'CA') ? 'selected' : ''; ?>
                                <option value="CA" <?php echo $selected; ?>>CA - Cuenta de Ahorros</option>
                                <?php $selected = isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'CC' ? 'selected' : ''; ?>
                                <option value="CC" <?php echo $selected; ?>>CC - Cuenta Corriente</option>
                                <?php $selected = isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'PL' ? 'selected' : ''; ?>
                                <option value="PL" <?php echo $selected; ?>>PL - Préstamo</option>
                                <?php $selected = isset($_POST['tipo_cuenta']) && $_POST['tipo_cuenta'] === 'TD' ? 'selected' : ''; ?>
                                <option value="TD" <?php echo $selected; ?>>TD - Depósito a Término</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clase_cuenta" class="form-label required-field">Clase de Cuenta</label>
                            <select class="form-select" id="clase_cuenta" name="clase_cuenta" required>
                                <?php $selected = (!isset($_POST['clase_cuenta']) || $_POST['clase_cuenta'] === 'N') ? 'selected' : ''; ?>
                                <option value="N" <?php echo $selected; ?>>N - Normal</option>
                                <?php $selected = isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'J' ? 'selected' : ''; ?>
                                <option value="J" <?php echo $selected; ?>>J - Jurídica</option>
                                <?php $selected = isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'E' ? 'selected' : ''; ?>
                                <option value="E" <?php echo $selected; ?>>E - Extranjera</option>
                                <?php $selected = isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'V' ? 'selected' : ''; ?>
                                <option value="V" <?php echo $selected; ?>>V - VIP</option>
                                <?php $selected = isset($_POST['clase_cuenta']) && $_POST['clase_cuenta'] === 'P' ? 'selected' : ''; ?>
                                <option value="P" <?php echo $selected; ?>>P - Premium</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label required-field">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <?php $selected = (!isset($_POST['estado']) || $_POST['estado'] === 'A') ? 'selected' : ''; ?>
                                <option value="A" <?php echo $selected; ?>>Activo</option>
                                <?php $selected = isset($_POST['estado']) && $_POST['estado'] === 'I' ? 'selected' : ''; ?>
                                <option value="I" <?php echo $selected; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label required-field">Fecha de Apertura</label>
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
        // Mejorar experiencia de fecha
        document.getElementById('fecha_apertura').addEventListener('focus', function() {
            this.type = 'date';
        });
    </script>
</body>
</html>