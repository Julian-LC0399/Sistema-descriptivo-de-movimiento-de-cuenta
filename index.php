<?php
session_start();

// Redirigir si no está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require 'includes/conexion.php';

// Obtener estadísticas para el dashboard
$clientes = $pdo->query("SELECT COUNT(*) FROM cumst")->fetchColumn();
$cuentas = $pdo->query("SELECT COUNT(*) FROM acmst")->fetchColumn();
$saldo_total = $pdo->query("SELECT SUM(acmbal) FROM acmst")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Sistema Bancario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-stat {
            transition: transform 0.3s;
        }
        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'templates/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-speedometer2 me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="clientes/listar.php">
                                <i class="bi bi-people me-2"></i> Clientes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cuentas/listar.php">
                                <i class="bi bi-bank me-2"></i> Cuentas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transacciones/listar.php">
                                <i class="bi bi-arrow-left-right me-2"></i> Transacciones
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card card-stat bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Clientes</h5>
                                        <h2><?= number_format($clientes) ?></h2>
                                    </div>
                                    <i class="bi bi-people display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Cuentas</h5>
                                        <h2><?= number_format($cuentas) ?></h2>
                                    </div>
                                    <i class="bi bi-bank display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Saldo Total</h5>
                                        <h2><?= number_format($saldo_total, 2) ?> VES</h2>
                                    </div>
                                    <i class="bi bi-currency-exchange display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimas transacciones -->
                <div class="card">
                    <div class="card-header">
                        <h5>Últimas Transacciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cuenta</th>
                                        <th>Monto</th>
                                        <th>Tipo</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $transacciones = $pdo->query("
                                        SELECT trdacc, acmacc, trdamt, trdmd, trddat 
                                        FROM actrd 
                                        JOIN acmst ON trdacc = acmacc 
                                        ORDER BY trddat DESC LIMIT 5
                                    ")->fetchAll();

                                    foreach ($transacciones as $tx): ?>
                                    <tr>
                                        <td><?= $tx['trdacc'] ?></td>
                                        <td><?= substr($tx['acmacc'], -4) ?>****</td>
                                        <td class="<?= $tx['trdmd'] == 'C' ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($tx['trdamt'], 2) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $tx['trdmd'] == 'C' ? 'success' : 'danger' ?>">
                                                <?= $tx['trdmd'] == 'C' ? 'Crédito' : 'Débito' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($tx['trddat'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'templates/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>