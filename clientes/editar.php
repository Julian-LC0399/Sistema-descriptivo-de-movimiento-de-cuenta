<?php
// clientes/editar.php
require_once '../includes/config.php';
require_once '../includes/database.php';
requireLogin();

$tituloPagina = "Editar Cliente";
$mensaje = '';

// Obtener ID del cliente a editar
$idCliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idCliente <= 0) {
    header("Location: lista.php?error=ID+de+cliente+no+válido");
    exit();
}

// Obtener datos actuales del cliente
$cliente = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM cumst WHERE cuscun = :id");
    $stmt->execute([':id' => $idCliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        header("Location: lista.php?error=Cliente+no+encontrado");
        exit();
    }
} catch (PDOException $e) {
    header("Location: lista.php?error=Error+al+obtener+cliente");
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos antes de actualizar
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $ciudad = trim($_POST['ciudad']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);

        if (empty($nombre) || empty($direccion) || empty($ciudad)) {
            throw new Exception("Nombre, dirección y ciudad son campos obligatorios");
        }

        // Actualizar cliente
        $stmt = $pdo->prepare("
            UPDATE cumst SET
                cusna1 = :nombre,
                cusna2 = :direccion,
                cuscty = :ciudad,
                cuseml = :email,
                cusphn = :telefono,
                cuslut = NOW(),
                cuslau = :usuario
            WHERE cuscun = :id
        ");
        
        $stmt->execute([
            ':id' => $idCliente,
            ':nombre' => $nombre,
            ':direccion' => $direccion,
            ':ciudad' => $ciudad,
            ':email' => $email,
            ':telefono' => $telefono,
            ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
        ]);
        
        header("Location: lista.php?mensaje=Cliente+actualizado+exitosamente");
        exit();
        
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar cliente: " . $e->getMessage();
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> - Sistema Bancario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">
</head>
<body class="clientes">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-container">
        <div class="d-flex justify-content-center mb-4">
            <h2><?= htmlspecialchars($tituloPagina) ?></h2>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="<?= $idCliente ?>">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?= htmlspecialchars($cliente['cusna1'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="direccion" name="direccion" 
                               value="<?= htmlspecialchars($cliente['cusna2'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="ciudad" class="form-label">Ciudad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ciudad" name="ciudad" 
                               value="<?= htmlspecialchars($cliente['cuscty'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($cliente['cuseml'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" 
                               value="<?= htmlspecialchars($cliente['cusphn'] ?? '') ?>">
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="lista.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al listado
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>