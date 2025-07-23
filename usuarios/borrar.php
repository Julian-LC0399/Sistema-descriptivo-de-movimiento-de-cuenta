<?php
// users/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden desactivar usuarios
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Obtener ID del usuario a desactivar
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['error'] = "ID de usuario no válido";
    header('Location: lista.php');
    exit;
}

// Verificar que no sea el mismo usuario que está logueado
if ($id == ($_SESSION['user_id'] ?? 0)) {
    $_SESSION['error'] = "No puedes desactivar tu propio usuario";
    header('Location: lista.php');
    exit;
}

// Obtener datos del usuario para el mensaje de confirmación
try {
    $pdo = getPDO();
    
    // Primero verificamos si el usuario existe
    $stmt = $pdo->prepare("SELECT username, activo FROM users WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $_SESSION['error'] = "El usuario no existe";
        header('Location: lista.php');
        exit;
    }

    // Verificar si ya está inactivo
    if ($usuario['activo'] == 0) {
        $_SESSION['error'] = "El usuario ya está inactivo";
        header('Location: lista.php');
        exit;
    }

    // Procesar la desactivación si se confirma
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $confirmacion = filter_input(INPUT_POST, 'confirmacion', FILTER_SANITIZE_STRING);
        
        if ($confirmacion === 'si') {
            $pdo->beginTransaction();
            
            // Desactivar el usuario (no lo eliminamos físicamente)
            $stmt = $pdo->prepare("UPDATE users SET activo = 0, actualizado_en = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $pdo->commit();
                $_SESSION['mensaje'] = [
                    'tipo' => 'success',
                    'texto' => "Usuario {$usuario['username']} desactivado correctamente"
                ];
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = "Error al desactivar el usuario";
            }
            
            header('Location: lista.php');
            exit;
        } else {
            // Si no confirmó, redirigir a la lista
            header('Location: lista.php');
            exit;
        }
    }

} catch (PDOException $e) {
    error_log("Error al desactivar usuario: " . $e->getMessage());
    $_SESSION['error'] = "Error en la base de datos al desactivar el usuario";
    header('Location: lista.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desactivar Usuario - Sistema Bancario</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Desactivar Usuario</h2>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Confirmar Desactivación</h5>
                <p class="card-text">
                    ¿Estás seguro que deseas desactivar al usuario <strong><?php echo htmlspecialchars($usuario['username']); ?></strong>?
                </p>
                <p class="text-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> El usuario no podrá acceder al sistema pero sus datos se mantendrán en la base de datos.
                </p>
                
                <form method="post">
                    <input type="hidden" name="confirmacion" id="confirmacion_si" value="si">
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-person-x-fill"></i> Sí, desactivar
                        </button>
                        <a href="lista.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>