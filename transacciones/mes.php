<?php
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos
check_auth();
if (!has_permission('cliente')) {
    header('Location: ../index.php');
    exit;
}

// Obtener parámetros de filtrado
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
$cuenta = isset($_GET['cuenta']) ? (int)$_GET['cuenta'] : null;

// Obtener el ID del cliente de la sesión
$cliente_id = $_SESSION['user_id'];

// Consulta base para transacciones
$query = "SELECT 
            t.trddat AS fecha,
            t.trdmd AS tipo,
            t.trdamt AS monto,
            t.trdbal AS saldo,
            t.trddsc AS descripcion,
            t.trdref AS referencia,
            a.acmccy AS moneda
          FROM actrd t
          JOIN acmst a ON t.trdacc = a.acmacc
          WHERE a.acmcun = :cliente_id
          AND MONTH(t.trddat) = :mes
          AND YEAR(t.trddat) = :anio";

// Filtrar por cuenta específica si se seleccionó
if ($cuenta) {
    $query .= " AND t.trdacc = :cuenta";
}

$query .= " ORDER BY t.trddat DESC, t.trdseq DESC";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
$stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
$stmt->bindParam(':anio', $anio, PDO::PARAM_INT);

if ($cuenta) {
    $stmt->bindParam(':cuenta', $cuenta, PDO::PARAM_INT);
}

$stmt->execute();
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener cuentas del cliente para el selector
$cuentas = obtener_cuentas_cliente($cliente_id);

// Incluir cabecera
include '../includes/header.php';
?>

<div class="container">
    <h2>Consulta de Transacciones por Mes</h2>
    
    <form method="get" class="mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="mes" class="form-label">Mes</label>
                <select name="mes" id="mes" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $mes ? 'selected' : '' ?>>
                            <?= nombre_mes($i) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="anio" class="form-label">Año</label>
                <select name="anio" id="anio" class="form-select">
                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?= $i ?>" <?= $i == $anio ? 'selected' : '' ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="cuenta" class="form-label">Cuenta (opcional)</label>
                <select name="cuenta" id="cuenta" class="form-select">
                    <option value="">Todas las cuentas</option>
                    <?php foreach ($cuentas as $c): ?>
                        <option value="<?= $c['acmacc'] ?>" <?= $cuenta == $c['acmacc'] ? 'selected' : '' ?>>
                            <?= formatear_numero_cuenta($c['acmacc']) ?> - <?= $c['acmccy'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </div>
    </form>
    
    <?php if (count($transacciones) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Referencia</th>
                        <th class="text-end">Monto</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transacciones as $trans): ?>
                        <tr>
                            <td><?= formatear_fecha($trans['fecha']) ?></td>
                            <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                            <td><?= htmlspecialchars($trans['referencia']) ?></td>
                            <td class="text-end <?= $trans['tipo'] == 'C' ? 'text-success' : 'text-danger' ?>">
                                <?= $trans['tipo'] == 'C' ? '+' : '-' ?>
                                <?= formatear_moneda($trans['monto'], $trans['moneda']) ?>
                            </td>
                            <td class="text-end"><?= formatear_moneda($trans['saldo'], $trans['moneda']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No se encontraron transacciones para el período seleccionado.</div>
    <?php endif; ?>
</div>

<?php
// Incluir pie de página
include '../includes/footer.php';
?>