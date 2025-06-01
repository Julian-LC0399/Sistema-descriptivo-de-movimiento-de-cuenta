<?php
require '../includes/conexion.php';
require '../includes/auth.php';
verificarSesion();

$stmt = $pdo->query("SELECT * FROM cumst");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lista de Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Clientes</h2>
        <a href="crear.php" class="btn btn-primary mb-3">Nuevo Cliente</a>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td><?= htmlspecialchars($cliente['cuscun']) ?></td>
                    <td><?= htmlspecialchars($cliente['cusna1']) ?></td>
                    <td><?= htmlspecialchars($cliente['cuseml']) ?></td>
                    <td>
                        <a href="editar.php?id=<?= $cliente['cuscun'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>