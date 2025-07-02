<?php
// clientes/crear.php
require_once '../includes/config.php';
require_once '../includes/database.php';
requireLogin();

$tituloPagina = "Agregar Nuevo Cliente";
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos antes de insertar
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $ciudad = trim($_POST['ciudad']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);

        if (empty($nombre) || empty($direccion) || empty($ciudad)) {
            throw new Exception("Nombre, dirección y ciudad son campos obligatorios");
        }

        // --- SOLUCIÓN MANUAL: Generar el nuevo ID de cliente ---
        // 1. Obtener el máximo ID actual
        $stmt = $pdo->query("SELECT MAX(cuscun) as max_id FROM cumst");
        $maxId = (int)$stmt->fetch()['max_id'];
        
        // 2. Incrementar en 1 para el nuevo cliente
        $nuevoId = $maxId + 1;

        // 3. Insertar con el nuevo ID generado
        $stmt = $pdo->prepare("
            INSERT INTO cumst (
                cuscun, cusna1, cusna2, cuscty, 
                cuseml, cusphn, cussts, cuslau, cuslut
            ) VALUES (
                :id, :nombre, :direccion, :ciudad, 
                :email, :telefono, 'A', :usuario, NOW()
            )
        ");
        
        $stmt->execute([
            ':id' => $nuevoId,
            ':nombre' => $nombre,
            ':direccion' => $direccion,
            ':ciudad' => $ciudad,
            ':email' => $email,
            ':telefono' => $telefono,
            ':usuario' => $_SESSION['username'] ?? 'SISTEMA' // Asignar usuario logueado
        ]);
        
        header("Location: lista.php?mensaje=Cliente+agregado+exitosamente");
        exit();
        
    } catch (PDOException $e) {
        $mensaje = "Error al agregar cliente: " . $e->getMessage();
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
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required>
                    </div>
                    <div class="mb-3">
                        <label for="ciudad" class="form-label">Ciudad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ciudad" name="ciudad" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="lista.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al listado
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>