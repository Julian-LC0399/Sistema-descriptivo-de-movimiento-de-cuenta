<?php
require_once '../includes/header.php';
redirectIfNotLoggedIn();

$clientes = getClientes();
?>

<h1>Listado de Clientes</h1>
<a href="crear.php">Nuevo Cliente</a>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Email</th>
        <th>Teléfono</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($clientes as $cliente): ?>
    <tr>
        <td><?= $cliente['cuscun'] ?></td>
        <td><?= htmlspecialchars($cliente['cusna1']) ?></td>
        <td><?= htmlspecialchars($cliente['cuseml']) ?></td>
        <td><?= htmlspecialchars($cliente['cusphn']) ?></td>
        <td><?= $cliente['cussts'] == 'A' ? 'Activo' : 'Inactivo' ?></td>
        <td>
            <a href="ver.php?id=<?= $cliente['cuscun'] ?>">Ver</a>
            <a href="editar.php?id=<?= $cliente['cuscun'] ?>">Editar</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once '../includes/footer.php'; ?>