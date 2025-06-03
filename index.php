<?php
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

// Requerir autenticación
requireLogin();

// Obtener información del usuario actual
$usuario = [
    'nombre' => $_SESSION['nombre'] ?? '',
    'apellido' => $_SESSION['apellido'] ?? '',
    'rol' => $_SESSION['rol'] ?? 'usuario'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroni - Sistema Bancario</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container py-4">
        <div class="bank-header">
            <div class="bank-title">Banco Caroni</div>
            <div class="bank-slogan">Un Banco tan sólido como sus raíces</div>
        </div>

        <div class="welcome-message">
            <h2>Bienvenido, <?php echo e($usuario['nombre']) . ' ' . e($usuario['apellido']); ?></h2>
            <p>Rol: <?php echo ucfirst(e($usuario['rol'])); ?></p>
        </div>
    </div>

    <?php include_once __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>