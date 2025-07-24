<?php
// usuarios/crear.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Configurar zona horaria para Venezuela
date_default_timezone_set('America/Caracas');

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden crear usuarios
if ($_SESSION['role'] !== 'admin') {
    header('Location: lista.php');
    exit;
}

// Obtener lista de clientes activos con estado de asociación
try {
    $pdo = getPDO();
    $sql = "SELECT c.cuscun, CONCAT(c.cusna1, ' ', c.cusln1) AS nombre, c.cusidn, 
                   IF(u.id IS NULL, 'Disponible', CONCAT('Asociado a: ', u.username)) AS estado
            FROM cumst c
            LEFT JOIN users u ON c.cuscun = u.cuscun
            WHERE c.cussts = 'A'
            ORDER BY c.cusna1, c.cusln1";
    $clientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener clientes: " . $e->getMessage();
    error_log($error);
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
        $role = in_array($_POST['role'] ?? '', ['admin', 'gerente', 'cajero', 'cliente']) ? $_POST['role'] : 'cliente';
        $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;

        // Validaciones
        if (empty($username)) {
            throw new Exception("El nombre de usuario es obligatorio");
        }

        if (strlen($password) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres");
        }

        if ($password !== $confirmPassword) {
            throw new Exception("Las contraseñas no coinciden");
        }

        // Si es cliente, debe tener asociado un cliente
        if ($role === 'cliente' && empty($clienteId)) {
            throw new Exception("Los usuarios con rol cliente deben tener un cliente asociado");
        }

        // Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("El nombre de usuario ya está registrado");
        }

        // Verificar que el cliente no esté ya asociado a otro usuario
        if ($clienteId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE cuscun = :cuscun");
            $stmt->execute([':cuscun' => $clienteId]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Este cliente ya está asociado a otro usuario");
            }
        }

        // Hash de la contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insertar usuario (sin campo email)
        $sql = "INSERT INTO users 
                (username, password, role, activo, creado_en, actualizado_en, cuscun) 
                VALUES 
                (:username, :password, :role, 1, NOW(), NOW(), :cuscun)";
        
        $params = [
            ':username' => $username,
            ':password' => $passwordHash,
            ':role' => $role,
            ':cuscun' => $clienteId
        ];
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al crear el usuario: " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Usuario $username creado exitosamente"
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
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
    <style>
        .selector-clientes {
            max-height: 300px;
            overflow-y: auto;
        }
        .cliente-option {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .cliente-option:hover {
            background-color: #f8f9fa;
        }
        .form-check-input:checked ~ .form-check-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Crear Nuevo Usuario</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <!-- Sección Cliente Asociado - Versión Modificada -->
            <div class="card mb-4 form-section">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-check-fill"></i> Asociar Cliente Existente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="cliente_id" class="form-label">
                            Seleccionar Cliente
                            <small class="text-muted">(Obligatorio para rol Cliente)</small>
                        </label>
                        
                        <div class="selector-clientes border rounded p-2 mb-2">
                            <?php foreach ($clientes as $cliente): ?>
                                <?php $disponible = ($cliente['estado'] === 'Disponible'); ?>
                                <div class="form-check cliente-option p-3 mb-2 rounded">
                                    <input class="form-check-input" type="radio" name="cliente_id" 
                                           id="cliente_<?= $cliente['cuscun'] ?>" 
                                           value="<?= $cliente['cuscun'] ?>"
                                           <?= !$disponible ? 'disabled' : '' ?>
                                           <?= (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['cuscun'] ? 'checked' : '') ?>>
                                    <label class="form-check-label w-100" for="cliente_<?= $cliente['cuscun'] ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($cliente['nombre']) ?></strong>
                                                <span class="text-muted ms-2">(<?= htmlspecialchars($cliente['cusidn']) ?>)</span>
                                            </div>
                                            <span class="<?= $disponible ? 'text-success' : 'text-danger' ?>">
                                                <?= $disponible ? 'Disponible' : $cliente['estado'] ?>
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-flex gap-2 mt-3">
                            <span class="text-success">
                                <i class="bi bi-check-circle"></i> Disponible
                            </span>
                            <span class="text-danger">
                                <i class="bi bi-x-circle"></i> Ya asociado
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Información de Usuario -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label required-field">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                            <small class="form-text text-muted">Mínimo 3 caracteres, sin espacios</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label required-field">Rol</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin' ? 'selected' : '') ?>>Administrador</option>
                                <option value="gerente" <?= (isset($_POST['role']) && $_POST['role'] === 'gerente' ? 'selected' : '') ?>>Gerente</option>
                                <option value="cajero" <?= (isset($_POST['role']) && $_POST['role'] === 'cajero' ? 'selected' : '') ?>>Cajero</option>
                                <option value="cliente" <?= (!isset($_POST['role']) || $_POST['role'] === 'cliente') ? 'selected' : '' ?>>Cliente</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Contraseña -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Seguridad</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label required-field">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="form-text text-muted">Mínimo 8 caracteres</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label required-field">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Estado (solo informativa) -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Estado del Usuario</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle-fill"></i> El usuario se creará en estado <strong>ACTIVO</strong>
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

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validar que los clientes sean obligatorios para rol cliente
            document.querySelector('form').addEventListener('submit', function(e) {
                const role = document.getElementById('role').value;
                const clienteSeleccionado = document.querySelector('input[name="cliente_id"]:checked');
                
                if (role === 'cliente' && (!clienteSeleccionado || clienteSeleccionado.disabled)) {
                    e.preventDefault();
                    alert('Debe seleccionar un cliente disponible para el rol Cliente');
                    return false;
                }
            });

            // Resaltar clientes requeridos cuando el rol es Cliente
            document.getElementById('role').addEventListener('change', function() {
                const clienteSection = document.querySelector('.selector-clientes');
                if (this.value === 'cliente') {
                    clienteSection.classList.add('border-primary', 'border-2');
                } else {
                    clienteSection.classList.remove('border-primary', 'border-2');
                }
            });

            // Inicializar estado
            const roleSelect = document.getElementById('role');
            if (roleSelect.value === 'cliente') {
                document.querySelector('.selector-clientes').classList.add('border-primary', 'border-2');
            }

            // Cerrar alertas automáticamente
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) new bootstrap.Alert(alert).close();
            }, 5000);
        });
    </script>
</body>
</html>