<?php
// clientes/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Agregar Nuevo Cliente";

// Inicializar variables para mantener los valores del formulario
$valoresFormulario = [
    'nombre' => '',
    'direccion' => '',
    'ciudad' => '',
    'email' => '',
    'telefono' => ''
];

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obtener y sanitizar datos del formulario
        $valoresFormulario = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'ciudad' => trim($_POST['ciudad'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? '')
        ];

        // Validar campos obligatorios
        if (empty($valoresFormulario['nombre'])) {
            $errores['nombre'] = "El nombre es obligatorio";
        }

        if (empty($valoresFormulario['direccion'])) {
            $errores['direccion'] = "La dirección es obligatoria";
        }

        if (empty($valoresFormulario['ciudad'])) {
            $errores['ciudad'] = "La ciudad es obligatoria";
        }

        // Validar email si se proporcionó
        if (!empty($valoresFormulario['email']) && !filter_var($valoresFormulario['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = "El email no tiene un formato válido";
        }

        // Si no hay errores, proceder con la inserción
        if (empty($errores)) {
            $pdo = getPDO();
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
                ':nombre' => $valoresFormulario['nombre'],
                ':direccion' => $valoresFormulario['direccion'],
                ':ciudad' => $valoresFormulario['ciudad'],
                ':email' => $valoresFormulario['email'],
                ':telefono' => $valoresFormulario['telefono'],
                ':usuario' => $_SESSION['username'] ?? 'SISTEMA'
            ]);
            
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => "Cliente agregado exitosamente"
            ];
            header("Location: lista.php");
            exit();
        }
        
    } catch (PDOException $e) {
        $errores['general'] = "Error al agregar cliente: " . $e->getMessage();
    } catch (Exception $e) {
        $errores['general'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> - Banco Caroni</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= htmlspecialchars($tituloPagina) ?></h2>
        
        <?php if (!empty($errores['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($errores['general']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <!-- Sección Información Básica -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información Básica</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label required-field">Nombre</label>
                            <input type="text" class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" 
                                   id="nombre" name="nombre" value="<?= htmlspecialchars($valoresFormulario['nombre']) ?>" required>
                            <?php if (isset($errores['nombre'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['nombre']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="ciudad" class="form-label required-field">Ciudad</label>
                            <input type="text" class="form-control <?= isset($errores['ciudad']) ? 'is-invalid' : '' ?>" 
                                   id="ciudad" name="ciudad" value="<?= htmlspecialchars($valoresFormulario['ciudad']) ?>" required>
                            <?php if (isset($errores['ciudad'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['ciudad']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="direccion" class="form-label required-field">Dirección</label>
                        <input type="text" class="form-control <?= isset($errores['direccion']) ? 'is-invalid' : '' ?>" 
                               id="direccion" name="direccion" value="<?= htmlspecialchars($valoresFormulario['direccion']) ?>" required>
                        <?php if (isset($errores['direccion'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['direccion']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sección Información de Contacto -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información de Contacto</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?= isset($errores['email']) ? 'is-invalid' : '' ?>" 
                                   id="email" name="email" value="<?= htmlspecialchars($valoresFormulario['email']) ?>">
                            <?php if (isset($errores['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   value="<?= htmlspecialchars($valoresFormulario['telefono']) ?>">
                        </div>
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
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validar teléfono (solo números)
        document.getElementById('telefono').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Cerrar alertas automáticamente
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>