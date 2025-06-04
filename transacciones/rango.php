<?php
require_once __DIR__ . '/../includes/config.php'; 
session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Obtener y validar parámetros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$cuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : null;

// Validación robusta de fechas
if (!validateDate($fecha_inicio) || !validateDate($fecha_fin)) {
    die("Formato de fecha inválido. Use YYYY-MM-DD.");
}

if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    die("La fecha de inicio no puede ser mayor a la fecha final.");
}

// Validar cuenta si se especifica
if (!empty($cuenta) && !preg_match('/^[0-9]{10,20}$/', $cuenta)) {
    die("Número de cuenta inválido. Debe contener solo dígitos (10-20 caracteres).");
}

// Consulta SQL con parámetros seguros
$sql = "SELECT 
            t.trddat AS fecha,
            t.trdseq AS secuencia,
            t.trdmd AS tipo,
            t.trdamt AS monto,
            t.trdbal AS saldo,
            t.trddsc AS descripcion,
            t.trdref AS referencia,
            t.trdusr AS usuario,
            a.acmccy AS moneda
        FROM actrd t
        JOIN acmst a ON t.trdacc = a.acmacc
        WHERE t.trddat BETWEEN :fecha_inicio AND :fecha_fin";

if (!empty($cuenta)) {
    $sql .= " AND t.trdacc = :cuenta";
}

$sql .= " ORDER BY t.trddat, t.trdseq";

try {
    $stmt = $pdo->prepare($sql);
    $params = [
        ':fecha_inicio' => $fecha_inicio,
        ':fecha_fin' => $fecha_fin
    ];
    
    if (!empty($cuenta)) {
        $params[':cuenta'] = $cuenta;
    }
    
    $stmt->execute($params);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales
    $total_debitos = 0;
    $total_creditos = 0;
    foreach ($transacciones as $t) {
        if ($t['tipo'] == 'D') {
            $total_debitos += $t['monto'];
        } else {
            $total_creditos += $t['monto'];
        }
    }
} catch(PDOException $e) {
    error_log("Error en consulta de estado de cuenta: " . $e->getMessage());
    die("Ocurrió un error al procesar su solicitud. Por favor intente más tarde.");
}

// Función auxiliar para validar fechas
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroni - Estado de Cuenta por Rango</title>
    <link rel="stylesheet" href="../assets/css/rango.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <!-- Solo incluir el sidebar sin estilos adicionales -->
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>BANCO CARONI</h1>
            <h2>ESTADO DE CUENTA POR RANGO</h2>
        </div>

        <div class="filter-form">
            <form method="get" class="form-inline">
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Desde:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">Hasta:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" 
                               value="<?= htmlspecialchars($fecha_fin) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cuenta">Cuenta:</label>
                        <input type="text" id="cuenta" name="cuenta" 
                               value="<?= htmlspecialchars($cuenta) ?>" placeholder="Número de cuenta">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Consultar</button>
            </form>
        </div>

        <?php if (!empty($cuenta)): ?>
        <div class="account-info">
            <p><strong>Número de Cuenta:</strong> <?= htmlspecialchars($cuenta) ?></p>
            <p><strong>Período:</strong> <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Secuencia</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                        <th>Descripción</th>
                        <th>Referencia</th>
                        <th>Usuario</th>
                        <th>Moneda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transacciones)): ?>
                        <?php foreach ($transacciones as $trans): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                                <td><?= htmlspecialchars($trans['secuencia']) ?></td>
                                <td class="<?= $trans['tipo'] == 'D' ? 'debit' : 'credit' ?>">
                                    <?= $trans['tipo'] == 'D' ? 'Débito' : 'Crédito' ?>
                                </td>
                                <td><?= number_format($trans['monto'], 2) ?></td>
                                <td><?= number_format($trans['saldo'], 2) ?></td>
                                <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                                <td><?= htmlspecialchars($trans['referencia']) ?></td>
                                <td><?= htmlspecialchars($trans['usuario']) ?></td>
                                <td><?= htmlspecialchars($trans['moneda']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-results">
                                <i class="fas fa-info-circle"></i>
                                No se encontraron transacciones en el período seleccionado
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($transacciones)): ?>
        <div class="totals-container">
            <div class="total-box total-debits">
                <div class="total-label">Total Débitos</div>
                <div class="total-value"><?= number_format($total_debitos, 2) ?></div>
            </div>
            <div class="total-box total-credits">
                <div class="total-label">Total Créditos</div>
                <div class="total-value"><?= number_format($total_creditos, 2) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <script>
        // Validación del lado del cliente
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('form').addEventListener('submit', function(e) {
                const inicio = document.getElementById('fecha_inicio').value;
                const fin = document.getElementById('fecha_fin').value;
                
                if (new Date(inicio) > new Date(fin)) {
                    alert('La fecha de inicio no puede ser mayor a la fecha final');
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>