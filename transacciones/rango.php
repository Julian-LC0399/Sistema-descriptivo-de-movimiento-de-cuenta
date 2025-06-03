<?php
require_once __DIR__ . '/../includes/config.php'; 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';


// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $numero_cuenta = $_POST['numero_cuenta'] ?? '';
    
    // Validaciones
    $errors = [];
    
    if (empty($numero_cuenta)) {
        $errors[] = "El número de cuenta es requerido";
    }
    
    if (empty($fecha_inicio)) {
        $errors[] = "La fecha de inicio es requerida";
    }
    
    if (empty($fecha_fin)) {
        $errors[] = "La fecha de fin es requerida";
    }
    
    if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
        $errors[] = "La fecha de inicio no puede ser mayor a la fecha de fin";
    }
    
    if (empty($errors)) {
        // Consulta para obtener transacciones
        $query = "SELECT 
                    trdacc AS cuenta,
                    trddat AS fecha,
                    trdmd AS tipo,
                    trdamt AS monto,
                    trdbal AS saldo,
                    trddsc AS descripcion,
                    trdref AS referencia
                  FROM actrd 
                  WHERE trdacc = ? 
                  AND trddat BETWEEN ? AND ?
                  ORDER BY trddat DESC, trdseq DESC";
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('iss', $numero_cuenta, $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $result = $stmt->get_result();
        $transacciones = $result->fetch_all(MYSQLI_ASSOC);
        
        // Consulta para información del cliente
        $query_cliente = "SELECT 
                            c.cusna1 AS nombre_cliente,
                            a.acmbal AS saldo_actual,
                            a.acmavl AS saldo_disponible,
                            a.acmccy AS moneda
                          FROM acmst a
                          JOIN cumst c ON a.acmcun = c.cuscun
                          WHERE a.acmacc = ?";
        
        $stmt_cliente = $mysqli->prepare($query_cliente);
        $stmt_cliente->bind_param('i', $numero_cuenta);
        $stmt_cliente->execute();
        $cliente = $stmt_cliente->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroni - Estado de Cuenta Por Rango</title>
    <link rel="stylesheet" href="../assets/css/rango.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="container">
        <div class="header">
            <h1>Banco Caroni</h1>
            <h2>Estado de Cuenta Por Rango</h2>
        </div>
        
        <div class="form-container">
            <form method="post">
                <div class="form-group">
                    <label for="numero_cuenta">Número de Cuenta</label>
                    <input type="number" id="numero_cuenta" name="numero_cuenta" required 
                           value="<?= htmlspecialchars($_POST['numero_cuenta'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_inicio">Fecha Desde</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" required
                           value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_fin">Fecha Hasta</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" required
                           value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>">
                </div>
                
                <button type="submit">Consultar</button>
            </form>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="message error">
                <h3>Errores encontrados:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($transacciones)): ?>
            <div class="cliente-info">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($cliente['nombre_cliente']) ?></p>
                <p><strong>Número de Cuenta:</strong> <?= htmlspecialchars($numero_cuenta) ?></p>
                <p><strong>Saldo Actual:</strong> <?= number_format($cliente['saldo_actual'], 2) ?> <?= htmlspecialchars($cliente['moneda']) ?></p>
                <p><strong>Saldo Disponible:</strong> <?= number_format($cliente['saldo_disponible'], 2) ?> <?= htmlspecialchars($cliente['moneda']) ?></p>
                <p><strong>Período Consultado:</strong> Desde <?= date('d/m/Y', strtotime($fecha_inicio)) ?> hasta <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
            </div>
            
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Referencia</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_debitos = 0;
                    $total_creditos = 0;
                    ?>
                    
                    <?php foreach ($transacciones as $trans): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                            <td><?= htmlspecialchars($trans['referencia']) ?></td>
                            <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                            <td><?= $trans['tipo'] == 'D' ? 'Débito' : 'Crédito' ?></td>
                            <td class="<?= $trans['tipo'] == 'D' ? 'debito' : 'credito' ?>">
                                <?= $trans['tipo'] == 'D' ? '-' : '+' ?>
                                <?= number_format($trans['monto'], 2) ?>
                            </td>
                            <td><?= number_format($trans['saldo'], 2) ?></td>
                        </tr>
                        
                        <?php 
                        if ($trans['tipo'] == 'D') {
                            $total_debitos += $trans['monto'];
                        } else {
                            $total_creditos += $trans['monto'];
                        }
                        ?>
                    <?php endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="3">Totales</td>
                        <td>Débitos</td>
                        <td class="debito">- <?= number_format($total_debitos, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3"></td>
                        <td>Créditos</td>
                        <td class="credito">+ <?= number_format($total_creditos, 2) ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="button-group">
                <button onclick="window.print()">Imprimir Estado de Cuenta</button>
                <button onclick="exportToExcel()">Exportar a Excel</button>
            </div>
            
            <script>
                function exportToExcel() {
                    // Crear un libro de Excel con los datos
                    let html = '<table>';
                    html += '<tr><th colspan="6">Banco Caroni - Estado de Cuenta</th></tr>';
                    html += '<tr><th colspan="6">Número de Cuenta: <?= $numero_cuenta ?></th></tr>';
                    html += '<tr><th colspan="6">Cliente: <?= htmlspecialchars($cliente['nombre_cliente']) ?></th></tr>';
                    html += '<tr><th colspan="6">Período: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></th></tr>';
                    html += '<tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th>Tipo</th><th>Monto</th><th>Saldo</th></tr>';
                    
                    <?php foreach ($transacciones as $trans): ?>
                        html += '<tr>';
                        html += '<td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>';
                        html += '<td><?= htmlspecialchars($trans['referencia']) ?></td>';
                        html += '<td><?= htmlspecialchars($trans['descripcion']) ?></td>';
                        html += '<td><?= $trans['tipo'] == 'D' ? 'Débito' : 'Crédito' ?></td>';
                        html += '<td><?= $trans['tipo'] == 'D' ? '-' : '+' ?><?= number_format($trans['monto'], 2) ?></td>';
                        html += '<td><?= number_format($trans['saldo'], 2) ?></td>';
                        html += '</tr>';
                    <?php endforeach; ?>
                    
                    html += '<tr><td colspan="3">Totales</td><td>Débitos</td><td>-<?= number_format($total_debitos, 2) ?></td><td></td></tr>';
                    html += '<tr><td colspan="3"></td><td>Créditos</td><td>+<?= number_format($total_creditos, 2) ?></td><td></td></tr>';
                    html += '</table>';
                    
                    // Crear archivo Excel
                    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
                    let link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'EstadoCuenta_<?= $numero_cuenta ?>_<?= date('Ymd') ?>.xls';
                    link.click();
                }
            </script>
        <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <div class="message error">
                No se encontraron transacciones para el rango de fechas y cuenta especificados.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>