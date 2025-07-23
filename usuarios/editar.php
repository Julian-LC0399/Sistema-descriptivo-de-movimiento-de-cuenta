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
$idUsuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idUsuario === 0) {
    header('Location: lista.php');
    exit;
}

// Obtener datos del usuario
try {
    $pdo = getPDO();
    $sql = "SELECT u.id, u.username, u.role, u.activo, u.cuscun, 
                   c.cusna1, c.cusln1, c.cusidn
            FROM users u
            LEFT JOIN cumst c ON u.cuscun = c.cuscun
            WHERE u.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idUsuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: lista.php');
    exit;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar datos
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Validaciones
        if (empty($username)) {
            throw new Exception("El nombre de usuario es obligatorio");
        }

        // Si se proporcionó contraseña, validarla
        if (!empty($password)) {
            if (strlen($password) < 8) {
                throw new Exception("La contraseña debe tener al menos 8 caracteres");
            }

            if ($password !== $confirmPassword) {
                throw new Exception("Las contraseñas no coinciden");
            }
        }

        // Verificar si el username ya existe (excluyendo el usuario actual)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
        $stmt->execute([':username' => $username, ':id' => $idUsuario]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("El nombre de usuario ya está registrado");
        }

        // Actualizar usuario
        if (!empty($password)) {
            // Actualizar con nueva contraseña
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users 
                    SET username = :username, password = :password, activo = :activo, actualizado_en = NOW()
                    WHERE id = :id";
            $params = [
                ':username' => $username,
                ':password' => $passwordHash,
                ':activo' => $activo,
                ':id' => $idUsuario
            ];
        } else {
            // Actualizar sin cambiar contraseña
            $sql = "UPDATE users 
                    SET username = :username, activo = :activo, actualizado_en = NOW()
                    WHERE id = :id";
            $params = [
                ':username' => $username,
                ':activo' => $activo,
                ':id' => $idUsuario
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
    <style>
        .campo-no-editable {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        .estado-activo {
            color: #28a745;
            font-weight: bold;
        }
        .estado-inactivo {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Editar Usuario</h2>
        
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
                            <label for="username" class="form-label required-field">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($usuario['username']) ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control campo-no-editable" 
                                   value="<?= htmlspecialchars(ucfirst($usuario['role'])) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Cliente Asociado (solo lectura) -->
            <?php if (!empty($usuario['cuscun'])): ?>
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Cliente Asociado</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del Cliente</label>
                            <input type="text" class="form-control campo-no-editable" 
                                   value="<?= htmlspecialchars($usuario['cusna1'] . ' ' . $usuario['cusln1']) ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cédula/RIF</label>
                            <input type="text" class="form-control campo-no-editable" 
                                   value="<?= htmlspecialchars($usuario['cusidn']) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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

            <!-- Sección Estado -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Estado del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                               <?= $usuario['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label <?= $usuario['activo'] ? 'estado-activo' : 'estado-inactivo' ?>" 
                               for="activo">
                            <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                        </label>
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
            // Validar contraseñas si se ingresan
            document.querySelector('form').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password && password.length < 8) {
                    e.preventDefault();
                    alert('La contraseña debe tener al menos 8 caracteres');
                    return false;
                }
                
                if (password !== confirmPassword) {
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