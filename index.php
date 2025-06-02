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
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #0056b3;
            --color-secondary: #6c757d;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        
        .bank-header {
            background-color: white;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .bank-title {
            color: var(--color-primary);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .bank-slogan {
            color: var(--color-secondary);
            font-size: 1.2rem;
            font-style: italic;
        }
        
        .menu-container {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .menu-title {
            color: var(--color-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .menu-list {
            list-style-type: none;
            padding: 0;
        }
        
        .menu-item {
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .menu-item:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        
        .menu-item a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            display: block;
        }
        
        .welcome-message {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--color-secondary);
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/header.php'; ?>

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