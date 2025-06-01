<?php
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

// Consulta para obtener transacciones bancarias (actrd) con JOIN a cuentas (acmst) y clientes (cumst)
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
          LIMIT 50"; // Últimos 50 movimientos

$stmt = $pdo->prepare($query);
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Bancario - Movimientos</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Estilos adicionales para mejorar la visualización */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .credito { color: green; }
        .debito { color: red; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/header.php'; ?>

    <div class="container">
        <h1>Movimientos Bancarios</h1>
        
        <h2>Últimas Transacciones</h2>
        <table>
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
                    <td><?php echo htmlspecialchars($movimiento['fecha']); ?></td>
                    <td><?php echo htmlspecialchars($movimiento['cliente']); ?></td>
                    <td><?php echo htmlspecialchars($movimiento['cuenta']); ?></td>
                    <td><?php echo htmlspecialchars($movimiento['descripcion']); ?></td>
                    <td class="<?php echo ($movimiento['tipo'] == 'C') ? 'credito' : 'debito'; ?>">
                        <?php echo number_format($movimiento['monto'], 2, ',', '.'); ?>
                    </td>
                    <td><?php echo ($movimiento['tipo'] == 'C') ? 'Crédito' : 'Débito'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions">
            <a href="nuevo_movimiento.php" class="btn">Registrar Nueva Transacción</a>
            <a href="reportes.php" class="btn">Generar Reportes</a>
        </div>
    </div>

    <?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>