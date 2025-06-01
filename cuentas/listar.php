<?php
require_once '../includes/header.php';
redirectIfNotLoggedIn();

$cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

if ($cliente_id > 0) {
    $cuentas = getCuentasByCliente($cliente_id);
    $cliente = getClienteById($cliente_id);
} else {
    $cuentas = [];
    $cliente = null;
}
?>

<h1>Cuentas Bancarias</h1>

<?php if ($cliente): ?>
<h2>Cliente: <?= htmlspecialchars($cliente['cusna1']) ?></h2>
<?php endif; ?>

<a href="crear.php?cliente_id=<?= $cliente_id ?>">Nueva Cuenta</a>
<a href="../clientes/listar.php">Volver a Clientes</a>

<table border="1">
    <tr>
        <th>Número</th>
        <th>Tipo</th>
        <th>Moneda</th>
        <th>Saldo</th>
        <th>Disponible</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($cuentas as $cuenta): ?>
    <tr>
        <td><?= $cuenta['acmacc'] ?></td>
        <td><?= $cuenta['acmtyp'] ?></td>
        <td><?= $cuenta['acmccy'] ?></td>
        <td><?= number_format($cuenta['acmbal'], 2) ?></td>
        <td><?= number_format($cuenta['acmavl'], 2) ?></td>
        <td><?= $cuenta['acmsta'] == 'A' ? 'Activo' : 'Inactivo' ?></td>
        <td>
            <a href="ver.php?id=<?= $cuenta['acmacc'] ?>">Ver</a>
            <a href="editar.php?id=<?= $cuenta['acmacc'] ?>">Editar</a>
            <a href="../transacciones/listar.php?cuenta_id=<?= $cuenta['acmacc'] ?>">Transacciones</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once '../includes/footer.php'; ?>