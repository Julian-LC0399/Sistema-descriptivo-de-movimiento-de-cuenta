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

// Consulta para obtener las últimas transacciones
$query = "SELECT 
            a.trddat AS fecha,
            a.trddsc AS descripcion,
            a.trdamt AS monto,
            a.trdmd AS tipo,
            c.cusna1 AS cliente,
            ac.acmacc AS cuenta
          FROM actrd a
          JOIN acmst ac ON a.trdacc = ac.acmacc
          JOIN cumst c ON ac.acmcun = c.cuscun
          ORDER BY a.trddat DESC, a.trdseq DESC
          LIMIT 10"; // Últimos 10 movimientos para el dashboard

$stmt = $pdo->prepare($query);
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta para resumen de cuentas (ejemplo)
$resumenQuery = "SELECT 
                   COUNT(DISTINCT a.trdacc) AS total_cuentas,
                   SUM(CASE WHEN a.trdmd = 'C' THEN a.trdamt ELSE 0 END) AS total_creditos,
                   SUM(CASE WHEN a.trdmd = 'D' THEN a.trdamt ELSE 0 END) AS total_debitos
                 FROM actrd a
                 WHERE DATE(a.trddat) = CURDATE()";

$resumen = $pdo->query($resumenQuery)->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Bancario</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #0056b3;
            --color-secondary: #6c757d;
            --color-success: #28a745;
            --color-danger: #dc3545;
            --color-light: #f8f9fa;
            --color-dark: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        
        .dashboard-header {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .welcome-message h1 {
            color: var(--color-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .user-role {
            color: var(--color-secondary);
            font-size: 0.9rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--color-primary);
        }
        
        .stats-card h3 {
            font-size: 1rem;
            color: var(--color-secondary);
            margin-bottom: 1rem;
        }
        
        .stats-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .stats-card.creditos {
            border-left-color: var(--color-success);
        }
        
        .stats-card.debitos {
            border-left-color: var(--color-danger);
        }
        
        .recent-transactions {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .table th {
            background-color: var(--color-light);
            font-weight: 600;
        }
        
        .credito {
            color: var(--color-success);
            font-weight: 500;
        }
        
        .debito {
            color: var(--color-danger);
            font-weight: 500;
        }
        
        .quick-actions {
            margin-top: 2rem;
        }
        
        .btn-dashboard {
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 6px;
            margin-right: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/header.php'; ?>

    <div class="container py-4">
        <div class="dashboard-header">
            <div class="welcome-message">
                <h1>Bienvenido, <?php echo e($usuario['nombre']) . ' ' . e($usuario['apellido']); ?></h1>
                <div class="user-role">
                    Rol: <?php echo ucfirst(e($usuario['rol'])); ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="stats-card">
                    <h3>Cuentas Activas Hoy</h3>
                    <div class="value"><?php echo number_format($resumen['total_cuentas'] ?? 0); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card creditos">
                    <h3>Total Créditos Hoy</h3>
                    <div class="value"><?php echo number_format($resumen['total_creditos'] ?? 0, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card debitos">
                    <h3>Total Débitos Hoy</h3>
                    <div class="value"><?php echo number_format($resumen['total_debitos'] ?? 0, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>

        <div class="recent-transactions">
            <h2 class="mb-4">Últimas Transacciones</h2>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Cuenta</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $movimiento): ?>
                        <tr>
                            <td><?php echo e($movimiento['fecha']); ?></td>
                            <td><?php echo e($movimiento['cliente']); ?></td>
                            <td><?php echo e($movimiento['cuenta']); ?></td>
                            <td><?php echo e($movimiento['descripcion']); ?></td>
                            <td class="<?php echo ($movimiento['tipo'] == 'C') ? 'credito' : 'debito'; ?>">
                                <?php echo number_format($movimiento['monto'], 2, ',', '.'); ?>
                            </td>
                            <td><?php echo ($movimiento['tipo'] == 'C') ? 'Crédito' : 'Débito'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="quick-actions">
            <h3 class="mb-3">Acciones Rápidas</h3>
            <a href="nuevo_movimiento.php" class="btn btn-primary btn-dashboard">
                <i class="fas fa-plus-circle me-2"></i>Nueva Transacción
            </a>
            <a href="movimientos.php" class="btn btn-secondary btn-dashboard">
                <i class="fas fa-list me-2"></i>Ver Todos los Movimientos
            </a>
            <a href="reportes.php" class="btn btn-success btn-dashboard">
                <i class="fas fa-chart-bar me-2"></i>Generar Reportes
            </a>
            <?php if ($usuario['rol'] === 'admin'): ?>
            <a href="admin.php" class="btn btn-dark btn-dashboard">
                <i class="fas fa-cog me-2"></i>Administración
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>