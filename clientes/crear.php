<?php
// clientes/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Agregar Nuevo Cliente";

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

        // Generar el nuevo ID de cliente
        $stmt = $pdo->query("SELECT MAX(cuscun) as max_id FROM cumst");
        $maxId = (int)$stmt->fetch()['max_id'];
        $nuevoId = $maxId + 1;

        // Insertar con el nuevo ID generado
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
            ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
        ]);
        
        $_SESSION['mensaje'] = "Cliente agregado exitosamente";
        header("Location: lista.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error al agregar cliente: " . $e->getMessage();
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
                        <div class="card mb-4">
                            <div class="card-header">
                                Información Básica
                            </div>
                            <div class="card-body">
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
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                Información de Contacto
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="lista.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar Cliente
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