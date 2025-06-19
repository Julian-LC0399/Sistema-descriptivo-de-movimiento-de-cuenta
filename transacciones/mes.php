<?php
require_once __DIR__ . '/../includes/config.php'; 
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Configuración de parámetros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
$cuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : null;

// Validación de parámetros
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($anio < 2000 || $anio > date('Y')) $anio = date('Y');
if (!empty($cuenta) && !preg_match('/^[0-9]{9,20}$/', $cuenta)) {
    die("Número de cuenta inválido. Debe contener solo dígitos (9-20 caracteres).");
}

// Inicializar variables para saldos
$saldo_inicial = 0;
$saldo_final = 0;
$saldo_acumulado = 0;

// Consulta para transacciones por mes
try {
    // Obtener saldo inicial solo si se filtró por cuenta
    if (!empty($cuenta)) {
        // Consulta para obtener el saldo inicial (último saldo antes del mes actual)
        $sql_saldo_inicial = "
            SELECT trdbal 
            FROM actrd 
            WHERE trdacc = ? 
              AND trddat < ?
            ORDER BY trddat DESC, trdseq DESC 
            LIMIT 1
        ";
        
        $fecha_primer_dia_mes = date("$anio-$mes-01");
        $stmt_saldo = $pdo->prepare($sql_saldo_inicial);
        $stmt_saldo->execute([$cuenta, $fecha_primer_dia_mes]);
        $saldo_inicial = $stmt_saldo->fetchColumn();
        
        // Si no hay saldo anterior, tomar el saldo de la tabla de cuentas (acmst)
        if ($saldo_inicial === false) {
            $sql_saldo_cuenta = "SELECT acmbal FROM acmst WHERE acmacc = ?";
            $stmt_cuenta = $pdo->prepare($sql_saldo_cuenta);
            $stmt_cuenta->execute([$cuenta]);
            $saldo_inicial = $stmt_cuenta->fetchColumn();
        }
        
        $saldo_inicial = $saldo_inicial ?: 0;
        $saldo_acumulado = $saldo_inicial;
    }

    // Consulta principal de transacciones
    $sql = "
        SELECT 
            t.trddat AS fecha,
            t.trdacc AS cuenta,
            t.trdmd AS tipo,
            t.trdamt AS monto,
            t.trdbal AS saldo,
            t.trddsc AS descripcion,
            t.trdref AS referencia,
            t.trdusr AS usuario,
            a.acmccy AS moneda,
            c.cusna1 AS cliente
        FROM actrd t
        JOIN acmst a ON t.trdacc = a.acmacc
        JOIN cumst c ON a.acmcun = c.cuscun
        WHERE MONTH(t.trddat) = ? AND YEAR(t.trddat) = ?
    ";
    
    $params = [$mes, $anio];
    
    if (!empty($cuenta)) {
        $sql .= " AND t.trdacc = ?";
        $params[] = $cuenta;
    }
    
    $sql .= " ORDER BY t.trddat ASC, t.trdseq ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales y saldo acumulado
    $total_debitos = 0;
    $total_creditos = 0;
    
    if (!empty($cuenta)) {
        foreach ($transacciones as &$t) {
            if ($t['tipo'] == 'D') {
                $total_debitos += $t['monto'];
                $saldo_acumulado -= $t['monto'];
            } else {
                $total_creditos += $t['monto'];
                $saldo_acumulado += $t['monto'];
            }
            $t['saldo_acumulado'] = $saldo_acumulado;
        }
        unset($t);
        
        $saldo_final = $saldo_acumulado;
    } else {
        foreach ($transacciones as $t) {
            if ($t['tipo'] == 'D') {
                $total_debitos += $t['monto'];
            } else {
                $total_creditos += $t['monto'];
            }
        }
    }
} catch(PDOException $e) {
    error_log("Error en consulta de transacciones: " . $e->getMessage());
    die("Ocurrió un error al procesar su solicitud.");
}

// Nombres de meses en español
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Verificar si se solicitó PDF
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once __DIR__ . '/../includes/library/tcpdf.php';
    
    $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Reporte de Transacciones '.$meses_espanol[$mes].' '.$anio);
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(TRUE, 8);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 7.5);

    $logo_path = realpath(__DIR__ . '/../assets/images/logo-banco.jpg');
    $logo_html = '';
    
    if (file_exists($logo_path)) {
        $logo_html = '<img src="'.$logo_path.'" width="80">';
    } else {
        error_log("Logo no encontrado: " . $logo_path);
    }

    // Obtener datos del cliente si hay cuenta
    $nombre_cliente = $direccion1 = $direccion2 = $ciudad = $moneda = '';
    if (!empty($cuenta)) {
        try {
            $stmt_cliente = $pdo->prepare("SELECT c.cusna1 AS nombre_completo, c.cusna2 AS direccion1, 
                                          c.cusna3 AS direccion2, c.cuscty AS ciudad, a.acmccy AS moneda
                                          FROM cumst c JOIN acmst a ON c.cuscun = a.acmcun
                                          WHERE a.acmacc = ?");
            $stmt_cliente->execute([$cuenta]);
            $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
            
            $nombre_cliente = $cliente_info['nombre_completo'] ?? 'CLIENTE NO ENCONTRADO';
            $direccion1 = $cliente_info['direccion1'] ?? '';
            $direccion2 = $cliente_info['direccion2'] ?? '';
            $ciudad = $cliente_info['ciudad'] ?? '';
            $moneda = $cliente_info['moneda'] ?? 'VES';
        } catch(PDOException $e) {
            error_log("Error al obtener info cliente: " . $e->getMessage());
            $nombre_cliente = 'CLIENTE NO ENCONTRADO';
            $direccion1 = $direccion2 = $ciudad = '';
            $moneda = 'VES';
        }
    } else {
        $moneda = 'VES';
    }
    
    $html = '
    <style>
        .header { text-align:center; margin-bottom:2px; }
        .client-container { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .client-name { font-size:14px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; text-align: right; }
        .client-info { line-height:1.1; font-size:9px; text-align: right; }
        .account-info { margin:2px 0; font-weight:bold; }
        .title { 
            text-align:center; 
            font-weight:bold; 
            font-size:10px; 
            margin:5px 0 3px 0;
            border-top:1px solid #000; 
            border-bottom:1px solid #000; 
            padding:1px 0; 
            text-transform:uppercase; 
        }
        .transaction-table {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .transaction-table th {
            background-color: #f5f5f5;
            color: #333;
            font-weight: bold;
            padding: 3px 2px;
            border: 0.5px solid #ddd;
            text-align: center;
            height: 18px;
            font-size: 7.5pt;
        }
        .transaction-table td {
            padding: 3px 2px;
            border: 0.5px solid #ddd;
            height: 16px;
            line-height: 1.2;
            font-size: 7pt;
            overflow: hidden;
            word-wrap: break-word;
        }
        .transaction-table .date-col { width: 12%; text-align: center; }
        .transaction-table .ref-col { width: 15%; text-align: center; }
        .transaction-table .desc-col { width: 33%; }
        .transaction-table .amount-col { width: 12%; text-align: right; }
        .transaction-table .balance-col { width: 15%; text-align: right; }
        .transaction-table tr:nth-child(even) { background-color: #f9f9f9; }
        .totals { 
            margin-top:5px; 
            font-size:8px; 
            border-top:1px solid #000; 
            padding-top:2px; 
            width:60%; 
            margin-left:auto; 
            margin-right:auto; 
        }
        .total-row { display:flex; justify-content:space-between; margin:1px 0; }
        .page-number { text-align:center; font-size:6px; margin-top:2px; }
        .address-line { margin-bottom:2px; }
        .logo-container { width: 30%; }
        .client-data-container { width: 65%; }
    </style>
    
    <div class="client-container">
        <div class="logo-container">
            '.$logo_html.'
        </div>
        <div class="client-data-container">
            <div class="client-name">'.strtoupper($nombre_cliente).'</div>
            <div class="client-info">
                '.($direccion1 ? '<div class="address-line"><strong>'.strtoupper($direccion1).'</strong></div>' : '').'
                '.($direccion2 ? '<div class="address-line"><strong>'.strtoupper($direccion2).'</strong></div>' : '').'
                '.($ciudad ? '<div class="address-line"><strong>'.strtoupper($ciudad).'</strong></div>' : '').'
                '.($cuenta ? '<div><strong>NÚMERO DE CUENTA: '.$cuenta.'</strong></div>' : '').'
                <div><strong>Fecha Emisión: '.date('d/m/Y H:i A').'</strong></div>
            </div>
        </div>
    </div>
    
    <div class="title">DETALLE DE TRANSACCIONES '.strtoupper($meses_espanol[$mes].' '.$anio).'</div>
    
    <table class="transaction-table">
        <thead>
            <tr>
                <th class="date-col">Fecha</th>
                <th class="ref-col">Serial</th>
                <th class="desc-col">Descripción</th>
                <th class="amount-col">Débito</th>
                <th class="amount-col">Crédito</th>
                '.(!empty($cuenta) ? '<th class="balance-col">Saldo</th>' : '').'
            </tr>
        </thead>
        <tbody>';

    if (!empty($transacciones)) {
        foreach ($transacciones as $trans) {
            $html .= '
            <tr>
                <td class="date-col">'.date('d/m/Y', strtotime($trans['fecha'])).'</td>
                <td class="ref-col">'.htmlspecialchars($trans['referencia']).'</td>
                <td class="desc-col">'.htmlspecialchars($trans['descripcion']).'</td>
                <td class="amount-col">'.($trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '').'</td>
                <td class="amount-col">'.($trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '').'</td>
                '.(!empty($cuenta) ? '<td class="balance-col">'.number_format($trans['saldo_acumulado'] ?? $trans['saldo'], 2, ',', '.').'</td>' : '').'
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="'.(!empty($cuenta) ? '6' : '5').'" style="text-align:center;">No se encontraron transacciones</td></tr>';
    }

    $html .= '
        </tbody>
    </table>
    
    <div class="totals">
        '.(!empty($cuenta) ? '
        <div class="total-row">
            <span>Saldo Inicial:</span>
            <span>'.number_format($saldo_inicial, 2, ',', '.').' '.$moneda.'</span>
        </div>' : '').'
        <div class="total-row">
            <span>Total Débitos:</span>
            <span>'.number_format($total_debitos, 2, ',', '.').' '.$moneda.'</span>
        </div>
        <div class="total-row">
            <span>Total Créditos:</span>
            <span>'.number_format($total_creditos, 2, ',', '.').' '.$moneda.'</span>
        </div>
        '.(!empty($cuenta) ? '
        <div class="total-row">
            <span>Saldo Final:</span>
            <span>'.number_format($saldo_final, 2, ',', '.').' '.$moneda.'</span>
        </div>' : '').'
    </div>
    <div class="page-number">Página '.$pdf->getAliasNumPage().' / '.$pdf->getAliasNbPages().'</div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('transacciones_'.$mes.'_'.$anio.'.pdf', 'D');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroni - Transacciones Mensuales</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/mes.css">
    <link rel="stylesheet" href="../assets/css/pdf-export.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>BANCO CARONI</h1>
            <h2>TRANSACCIONES MENSUALES</h2>
        </div>

        <div class="filter-container">
            <form method="get" class="filter-form">
                <div class="filter-header">
                    <h3><i class="fas fa-filter"></i> Filtros de Consulta</h3>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Buscar Transacciones
                    </button>
                </div>
                
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="mes" class="filter-label">
                            <i class="far fa-calendar-alt"></i> Mes
                        </label>
                        <select id="mes" name="mes" class="filter-input" required>
                            <?php foreach($meses_espanol as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= $num==$mes?'selected':'' ?>>
                                    <?= $nombre ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="anio" class="filter-label">
                            <i class="far fa-calendar"></i> Año
                        </label>
                        <select id="anio" name="anio" class="filter-input" required>
                            <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                                <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="cuenta" class="filter-label">
                            <i class="fas fa-wallet"></i> Número de Cuenta
                        </label>
                        <input type="text" id="cuenta" name="cuenta" 
                               value="<?= htmlspecialchars($cuenta) ?>" 
                               placeholder="Ej: 123456789"
                               class="filter-input">
                    </div>
                </div>
            </form>

            <?php if (!empty($transacciones)): ?>
                <div class="export-buttons">
                    <a href="?mes=<?= $mes ?>&anio=<?= $anio ?>&cuenta=<?= urlencode($cuenta) ?>&export=pdf" 
                       class="btn-export pdf" target="_blank" title="Exportar a PDF">
                       <i class="fas fa-print"></i> Exportar PDF
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($transacciones)): ?>
            <div class="month-section">
                <h3 class="month-title"><?= strtoupper($meses_espanol[$mes] . ' ' . $anio) ?></h3>
                
                <div class="account-info">
                    <?php if (!empty($cuenta)): ?>
                        <p><strong><i class="fas fa-user"></i> Cliente:</strong> <?= htmlspecialchars($transacciones[0]['cliente'] ?? '') ?></p>
                        <p><strong><i class="fas fa-wallet"></i> Número de Cuenta:</strong> <?= htmlspecialchars($cuenta) ?></p>
                        <p><strong><i class="fas fa-coins"></i> Saldo Inicial:</strong> <?= number_format($saldo_inicial, 2, ',', '.') ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="table-container">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th><i class="far fa-calendar"></i> Fecha</th>
                                <th><i class="fas fa-barcode"></i> serial</th>
                                <th><i class="fas fa-align-left"></i> Descripción</th>
                                <th><i class="fas fa-arrow-down"></i> Débito</th>
                                <th><i class="fas fa-arrow-up"></i> Crédito</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacciones as $trans): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($trans['referencia']) ?></td>
                                    <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                                    <td class="debit"><?= $trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                    <td class="credit"><?= $trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="month-totals">
                    <div class="total-box">
                        <div class="total-label"><i class="fas fa-arrow-down"></i> Total Débitos</div>
                        <div class="total-value"><?= number_format($total_debitos, 2, ',', '.') ?></div>
                    </div>
                    <div class="total-box">
                        <div class="total-label"><i class="fas fa-arrow-up"></i> Total Créditos</div>
                        <div class="total-value"><?= number_format($total_creditos, 2, ',', '.') ?></div>
                    </div>
                    <?php if (!empty($cuenta)): ?>
                        <div class="total-box">
                            <div class="total-label"><i class="fas fa-coins"></i> Saldo Final</div>
                            <div class="total-value"><?= number_format($saldo_final, 2, ',', '.') ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-info-circle"></i>
                No se encontraron transacciones en el período seleccionado
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>