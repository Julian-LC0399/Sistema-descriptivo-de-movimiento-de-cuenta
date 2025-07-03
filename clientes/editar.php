<?php
// clientes/editar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Editar Cliente";

// Obtener ID del cliente a editar
$idCliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idCliente <= 0) {
    $_SESSION['error'] = "ID de cliente no válido";
    header("Location: lista.php");
    exit();
}

// Obtener datos actuales del cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM cumst WHERE cuscun = :id");
    $stmt->execute([':id' => $idCliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        $_SESSION['error'] = "Cliente no encontrado";
        header("Location: lista.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener cliente";
    header("Location: lista.php");
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
        
        $_SESSION['mensaje'] = "Cliente actualizado exitosamente";
        header("Location: lista.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error al actualizar cliente: " . $e->getMessage();
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
    <title><?= htmlspecialchars($tituloPagina) ?> - Sistema Bancario</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/clientes.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= htmlspecialchars($tituloPagina) ?></h2>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-container">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <input type="hidden" name="id" value="<?= $idCliente ?>">
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                Información Básica
                            </div>
                            <div class="card-body">
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
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                Información de Contacto
                            </div>
                            <div class="card-body">
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
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="lista.php" class="btn btn-outline-secondary">
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
</body>
</html>