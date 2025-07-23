<?php
// users/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden crear usuarios
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Inicializar variables
$error = '';
$valores = [
    'username' => '',
    'email' => '',
    'nombre' => '',
    'apellido' => '',
    'role' => 'cliente',
    'activo' => 1
];

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // Validar y sanitizar datos
        $valores['username'] = trim($_POST['username'] ?? '');
        $valores['email'] = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $valores['nombre'] = trim($_POST['nombre'] ?? '');
        $valores['apellido'] = trim($_POST['apellido'] ?? '');
        $valores['role'] = in_array($_POST['role'] ?? '', ['admin', 'gerente', 'cajero', 'cliente']) ? $_POST['role'] : 'cliente';
        $valores['activo'] = isset($_POST['activo']) && $_POST['activo'] == '1' ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validaciones
        if (empty($valores['username'])) {
            throw new Exception("El nombre de usuario es obligatorio");
        }

        if (empty($valores['email']) || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Debe proporcionar un email válido");
        }

        if (empty($password)) {
            throw new Exception("La contraseña es obligatoria");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Las contraseñas no coinciden");
        }

        if (strlen($password) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres");
        }

        // Verificar si el username ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->bindParam(':username', $valores['username']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("El nombre de usuario ya está en uso");
        }

        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->bindParam(':email', $valores['email']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("El email ya está registrado");
        }

        // Hash de la contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insertar usuario
        $sql = "INSERT INTO users 
                (username, email, nombre, apellido, role, password, activo, creado_en, actualizado_en) 
                VALUES 
                (:username, :email, :nombre, :apellido, :role, :password, :activo, NOW(), NOW())";
        
        $params = [
            ':username' => $valores['username'],
            ':email' => $valores['email'],
            ':nombre' => $valores['nombre'],
            ':apellido' => $valores['apellido'],
            ':role' => $valores['role'],
            ':password' => $passwordHash,
            ':activo' => $valores['activo']
        ];
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al crear el usuario: " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Usuario {$valores['username']} creado exitosamente"
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
    <title>Crear Nuevo Usuario - Sistema Bancario</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Crear Nuevo Usuario</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
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
                            <label for="username" class="form-label required-field">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($valores['username']); ?>" 
                                   required placeholder="Nombre de usuario único">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($valores['email']); ?>" 
                                   required placeholder="correo@ejemplo.com">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($valores['nombre']); ?>" 
                                   placeholder="Primer nombre">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">Apellido</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" 
                                   value="<?php echo htmlspecialchars($valores['apellido']); ?>" 
                                   placeholder="Primer apellido">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Seguridad -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Seguridad</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label required-field">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required placeholder="Mínimo 8 caracteres">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label required-field">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required placeholder="Repita la contraseña">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Configuración -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label required-field">Rol</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="cliente" <?= $valores['role'] === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                                <option value="cajero" <?= $valores['role'] === 'cajero' ? 'selected' : '' ?>>Cajero</option>
                                <option value="gerente" <?= $valores['role'] === 'gerente' ? 'selected' : '' ?>>Gerente</option>
                                <option value="admin" <?= $valores['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="activo" class="form-label required-field">Estado</label>
                            <select class="form-select" id="activo" name="activo" required>
                                <option value="1" <?= $valores['activo'] == 1 ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= $valores['activo'] == 0 ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="lista.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Usuario
                </button>
            </div>
        </form>
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