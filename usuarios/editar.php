<?php
// usuarios/editar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Configurar zona horaria para Venezuela
date_default_timezone_set('America/Caracas');

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden editar usuarios
if ($_SESSION['role'] !== 'admin') {
    header('Location: lista.php');
    exit;
}

// Obtener ID del usuario a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $pdo = getPDO();
    
    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }

    // Obtener datos del cliente asociado (si existe)
    $clienteAsociado = null;
    if ($usuario['cuscun']) {
        $stmt = $pdo->prepare("SELECT CONCAT(cusna1, ' ', cusln1) AS nombre, cusidn FROM cumst WHERE cuscun = :cuscun");
        $stmt->execute([':cuscun' => $usuario['cuscun']]);
        $clienteAsociado = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
    error_log($error);
    header('Location: lista.php');
    exit;
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log($error);
    header('Location: lista.php');
    exit;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar datos
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Validaciones
        if (!empty($password)) {
            if (strlen($password) < 8) {
                throw new Exception("La contraseña debe tener al menos 8 caracteres");
            }

            if ($password !== $confirmPassword) {
                throw new Exception("Las contraseñas no coinciden");
            }

            // Hash de la contraseña
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Actualizar con nueva contraseña
            $sql = "UPDATE users 
                    SET password = :password, 
                        activo = :activo,
                        actualizado_en = NOW()
                    WHERE id = :id";
            
            $params = [
                ':password' => $passwordHash,
                ':activo' => $activo,
                ':id' => $id
            ];
        } else {
            // Actualizar sin cambiar contraseña
            $sql = "UPDATE users 
                    SET activo = :activo,
                        actualizado_en = NOW()
                    WHERE id = :id";
            
            $params = [
                ':activo' => $activo,
                ':id' => $id
            ];
        }

        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al actualizar el usuario: " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Usuario actualizado exitosamente"
        ];
        
        header('Location: lista.php');
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
    <title>Editar Usuario - Sistema Bancario</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Editar Usuario: <?= htmlspecialchars($usuario['username']) ?></h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <!-- Sección Información del Usuario (solo lectura) -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($usuario['username']) ?>" readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars(ucfirst($usuario['role'])) ?>" readonly>
                        </div>
                    </div>
                    
                    <?php if ($clienteAsociado): ?>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Cliente Asociado</label>
                            <input type="text" class="form-control-plaintext" 
                                   value="<?= htmlspecialchars($clienteAsociado['nombre']) ?> (<?= htmlspecialchars($clienteAsociado['cusidn']) ?>)" readonly>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sección Contraseña -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted">Dejar en blanco para no cambiar</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Estado -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Estado del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                               <?= $usuario['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">
                            Usuario <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                        </label>
                    </div>
                    <small class="form-text text-muted">Activar/desactivar el acceso del usuario al sistema</small>
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
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validar contraseñas si se ingresan
            document.querySelector('form').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password || confirmPassword) {
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('La contraseña debe tener al menos 8 caracteres');
                        return false;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Las contraseñas no coinciden');
                        return false;
                    }
                }
            });

            // Actualizar texto del estado al cambiar el switch
            document.getElementById('activo').addEventListener('change', function() {
                const label = document.querySelector('label[for="activo"]');
                label.textContent = 'Usuario ' + (this.checked ? 'Activo' : 'Inactivo');
            });

            // Cerrar alertas automáticamente
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) new bootstrap.Alert(alert).close();
            }, 5000);
        });
    </script>
</body>
</html>