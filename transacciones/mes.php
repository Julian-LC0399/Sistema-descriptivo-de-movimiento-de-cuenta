<?php
require_once __DIR__ . '/../includes/config.php'; 

// Iniciar sesión SIEMPRE al principio
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

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

// Definir nombres de meses en español
$meses_espanol = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transacciones del mes</title>
    <link rel="stylesheet" href="../assets/css/transacciones.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <!-- Solo incluir el sidebar sin estilos adicionales -->
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h2>Transacciones del Mes</h2>
        </div>
        
        <!-- Filter Form -->
        <div class="filter-form">
            <form method="get">
                <div class="form-group">
                    <label for="mes">Mes</label>
                    <select name="mes" id="mes" class="form-control">
                        <?php foreach($meses_espanol as $numero => $nombre): ?>
                            <option value="<?= $numero ?>" <?= $numero==$mes?'selected':'' ?>>
                                <?= $nombre ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="anio">Año</label>
                    <select name="anio" id="anio" class="form-control">
                        <?php for($y=date('Y')-5; $y<=date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
        
        <!-- Results Table -->
        <div class="results-table">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Cuenta</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transacciones as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($t['trddat']))) ?></td>
                        <td><?= htmlspecialchars($t['cliente']) ?></td>
                        <td><?= htmlspecialchars($t['trdacc']) ?></td>
                        <td class="<?= $t['trdmd']=='D' ? 'debito' : 'credito' ?>">
                            <?php if($t['trdmd']=='D'): ?>
                                Débito
                            <?php else: ?>
                                Crédito
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($t['trdamt'], 2) ?></td>
                        <td><?= htmlspecialchars($t['trddsc']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($transacciones)): ?>
                        <tr>
                            <td colspan="6" class="no-results">No se encontraron transacciones para este período</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</body>
</html>