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
$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: lista.php');
    exit;
}

// Obtener información del usuario
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, username, role, activo FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'Usuario no encontrado'
        ];
        header('Location: lista.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Error al obtener usuario: " . $e->getMessage();
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
        $activo = $_POST['activo'] === '1' ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Solo validar contraseña si se proporcionó una nueva
        $updatePassword = false;
        
        if (!empty($password)) {
            if (strlen($password) < 8) {
                throw new Exception("La contraseña debe tener al menos 8 caracteres");
            }

            if ($password !== $confirmPassword) {
                throw new Exception("Las contraseñas no coinciden");
            }
            
            $updatePassword = true;
        }

        // Actualizar usuario
        if ($updatePassword) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users 
                    SET activo = :activo, 
                        password = :password,
                        actualizado_en = NOW()
                    WHERE id = :id";
            
            $params = [
                ':activo' => $activo,
                ':password' => $passwordHash,
                ':id' => $userId
            ];
        } else {
            $sql = "UPDATE users 
                    SET activo = :activo,
                        actualizado_en = NOW()
                    WHERE id = :id";
            
            $params = [
                ':activo' => $activo,
                ':id' => $userId
            ];
        }
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al actualizar el usuario: " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Usuario {$usuario['username']} actualizado exitosamente"
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
    <title>Editar usuario - Sistema Bancario</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Editar usuario</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <!-- Sección Información Básica -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['username']) ?>" readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['role']) ?>" readonly>
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
                    <div class="mb-3">
                        <label for="activo" class="form-label">Estado</label>
                        <select class="form-select" id="activo" name="activo" required>
                            <option value="1" <?= $usuario['activo'] ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= !$usuario['activo'] ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                        <small class="text-muted">Los usuarios inactivos no pueden iniciar sesión</small>
                    </div>
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
                            <small class="form-text text-muted">Dejar en blanco para mantener la contraseña actual</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
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
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validar que las contraseñas coincidan si se proporcionan
            document.querySelector('form').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password && password !== confirmPassword) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                    return false;
                }
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