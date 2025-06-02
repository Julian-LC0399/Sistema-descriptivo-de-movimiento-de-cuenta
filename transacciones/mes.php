<?php

// Iniciar sesión SIEMPRE al principio
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Incluir el header que contiene el sidebar
include '../includes/header.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Configuración de fechas
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Consulta para transacciones por mes
$stmt = $pdo->prepare("
    SELECT t.*, c.cusna1 as cliente 
    FROM actrd t
    JOIN acmst a ON t.trdacc = a.acmacc
    JOIN cumst c ON a.acmcun = c.cuscun
    WHERE MONTH(t.trddat) = ? AND YEAR(t.trddat) = ?
    ORDER BY t.trddat DESC
");
$stmt->execute([$mes, $anio]);
$transacciones = $stmt->fetchAll();


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transacciones del Mes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .table-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .debit {
            color: #dc3545;
            font-weight: bold;
        }
        .credit {
            color: #28a745;
            font-weight: bold;
        }
        .filter-form {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .page-header {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="bi bi-arrow-left-right me-2"></i>Transacciones del Mes</h2>
        </div>
        
        <!-- Filter Form -->
        <div class="filter-form">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="mes" class="form-label">Mes</label>
                    <select name="mes" id="mes" class="form-select">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m==$mes?'selected':'' ?>>
                                <?= DateTime::createFromFormat('!m', $m)->format('F') ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="anio" class="form-label">Año</label>
                    <select name="anio" id="anio" class="form-select">
                        <?php for($y=date('Y')-5; $y<=date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Results Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><i class="bi bi-calendar me-1"></i>Fecha</th>
                            <th><i class="bi bi-person me-1"></i>Cliente</th>
                            <th><i class="bi bi-credit-card me-1"></i>Cuenta</th>
                            <th><i class="bi bi-arrow-left-right me-1"></i>Tipo</th>
                            <th><i class="bi bi-currency-dollar me-1"></i>Monto</th>
                            <th><i class="bi bi-card-text me-1"></i>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transacciones as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($t['trddat']))) ?></td>
                            <td><?= htmlspecialchars($t['cliente']) ?></td>
                            <td><?= htmlspecialchars($t['trdacc']) ?></td>
                            <td>
                                <?php if($t['trdmd']=='D'): ?>
                                    <span class="badge bg-danger">Débito</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Crédito</span>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $t['trdmd']=='D'?'debit':'credit' ?>">
                                <?= number_format($t['trdamt'], 2) ?>
                            </td>
                            <td><?= htmlspecialchars($t['trddsc']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($transacciones)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No se encontraron transacciones para este período</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../includes/footer.php'; ?>