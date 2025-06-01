<?php
require_once '../includes/header.php';
redirectIfNotLoggedIn();

$cuenta_id = isset($_GET['cuenta_id']) ? (int)$_GET['cuenta_id'] : 0;

if ($cuenta_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM actrd WHERE trdacc = ? ORDER BY trddat DESC, trdseq DESC");
    $stmt->execute([$cuenta_id]);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmacc = ?");
    $stmt->execute([$cuenta_id]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $transacciones = [];
    $cuenta = null;
}
?>

<h1>Transacciones de Cuenta</h1>

<?php if ($cuenta): ?>
<h2>Cuenta: <?= $cuenta['acmacc'] ?> (Saldo: <?= number_format($cuenta['acmbal'], 2) ?>)</h2>
<?php endif; ?>

<a href="../cuentas/listar.php?cliente_id=<?= $cuenta['acmcun'] ?? 0 ?>">Volver a Cuentas</a>

<table border="1">
    <tr>
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Monto</th>
        <th>Saldo</th>
        <th>Descripción</th>
        <th>Referencia</th>
    </tr>
    <?php foreach ($transacciones as $trans): ?>
    <tr>
        <td><?= $trans['trddat'] ?></td>
        <td><?= $trans['trdmd'] == 'D' ? 'Débito' : 'Crédito' ?></td>
        <td><?= number_format($trans['trdamt'], 2) ?></td>
        <td><?= number_format($trans['trdbal'], 2) ?></td>
        <td><?= htmlspecialchars($trans['trddsc']) ?></td>
        <td><?= htmlspecialchars($trans['trdref']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once '../includes/footer.php'; ?>