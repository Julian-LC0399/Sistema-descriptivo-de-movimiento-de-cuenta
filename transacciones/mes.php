<?php
require '../includes/functions.php';
require '../includes/database.php';

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

// Incluir cabecera
include '../includes/header.php';
?>

<h2>Transacciones del Mes</h2>

<!-- Formulario para seleccionar mes/año -->
<form method="get" class="form-inline mb-4">
    <select name="mes" class="form-control mr-2">
        <?php for($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= $m==$mes?'selected':'' ?>>
                <?= DateTime::createFromFormat('!m', $m)->format('F') ?>
            </option>
        <?php endfor; ?>
    </select>
    
    <select name="anio" class="form-control mr-2">
        <?php for($y=date('Y')-5; $y<=date('Y'); $y++): ?>
            <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
    
    <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<!-- Tabla de resultados -->
<table class="table table-striped">
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
            <td><?= htmlspecialchars($t['trddat']) ?></td>
            <td><?= htmlspecialchars($t['cliente']) ?></td>
            <td><?= htmlspecialchars($t['trdacc']) ?></td>
            <td><?= $t['trdmd']=='D'?'Débito':'Crédito' ?></td>
            <td><?= number_format($t['trdamt'], 2) ?></td>
            <td><?= htmlspecialchars($t['trddsc']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>