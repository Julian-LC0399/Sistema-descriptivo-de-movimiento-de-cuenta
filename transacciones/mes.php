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

function getSaldoColor($saldo) {
    if ($saldo > 0) {
        return '#007050';
    } elseif ($saldo < 0) {
        return '#c00000';
    } else {
        return '#003366';
    }
}

function formatAccountNumber($cuenta) {
    if (empty($cuenta)) return '';
    
    $cuenta = preg_replace('/[^0-9]/', '', $cuenta);
    
    if (strlen($cuenta) == 20) {
        return substr($cuenta, 0, 4) . '-' . 
               substr($cuenta, 4, 4) . '-' . 
               substr($cuenta, 8, 2) . '-' . 
               substr($cuenta, 10, 10);
    }
    
    return $cuenta;
}

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
$total_debitos = 0;
$total_creditos = 0;
$nombre_cliente = 'CLIENTE NO ESPECIFICADO';
$direccion1 = $direccion2 = $ciudad = '';
$moneda = 'VES';

// Consulta para transacciones por mes
try {
    // Obtener datos del cliente si hay cuenta
    if (!empty($cuenta)) {
        $stmt_cliente = $pdo->prepare("SELECT c.cusna1 AS nombre_completo, c.cusna2 AS direccion1, 
                                      c.cusna3 AS direccion2, c.cuscty AS ciudad, a.acmccy AS moneda
                                      FROM cumst c JOIN acmst a ON c.cuscun = a.acmcun
                                      WHERE a.acmacc = ?");
        $stmt_cliente->execute([$cuenta]);
        $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente_info) {
            $nombre_cliente = $cliente_info['nombre_completo'] ?? 'CLIENTE NO ENCONTRADO';
            $direccion1 = $cliente_info['direccion1'] ?? '';
            $direccion2 = $cliente_info['direccion2'] ?? '';
            $ciudad = $cliente_info['ciudad'] ?? '';
            $moneda = $cliente_info['moneda'] ?? 'VES';
        }

        // Consulta para obtener el saldo inicial
        $sql_saldo_inicial = "SELECT trdbal FROM actrd WHERE trdacc = ? AND trddat < ? ORDER BY trddat DESC, trdseq DESC LIMIT 1";
        $fecha_primer_dia_mes = date("$anio-$mes-01");
        $stmt_saldo = $pdo->prepare($sql_saldo_inicial);
        $stmt_saldo->execute([$cuenta, $fecha_primer_dia_mes]);
        $saldo_inicial = $stmt_saldo->fetchColumn();
        
        if ($saldo_inicial === false) {
            $sql_saldo_cuenta = "SELECT acmbal FROM acmst WHERE acmacc = ?";
            $stmt_cuenta = $pdo->prepare($sql_saldo_cuenta);
            $stmt_cuenta->execute([$cuenta]);
            $saldo_inicial = $stmt_cuenta->fetchColumn();
        }
        
        $saldo_inicial = $saldo_inicial ?: 0;
        $saldo_acumulado = $saldo_inicial;

        // Obtener saldo final directamente desde acmst
        $stmt_saldo_final = $pdo->prepare("SELECT acmbal FROM acmst WHERE acmacc = ?");
        $stmt_saldo_final->execute([$cuenta]);
        $saldo_final = $stmt_saldo_final->fetchColumn() ?: 0;
    }

    // Consulta principal de transacciones
    $sql = "SELECT 
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
            WHERE MONTH(t.trddat) = ? AND YEAR(t.trddat) = ?";
    
    $params = [$mes, $anio];
    
    if (!empty($cuenta)) {
        $sql .= " AND t.trdacc = ?";
        $params[] = $cuenta;
    }
    
    $sql .= " ORDER BY t.trddat ASC, t.trdseq ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales (solo débitos y créditos)
    foreach ($transacciones as &$t) {
        if ($t['tipo'] == 'D') {
            $total_debitos += $t['monto'];
        } else {
            $total_creditos += $t['monto'];
        }
        
        // Opcional: Mostrar saldo acumulado por transacción (usando trdbal)
        if (!empty($cuenta)) {
            $t['saldo_acumulado'] = $t['saldo']; // Usamos el saldo registrado en actrd
        }
    }
    unset($t);

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
    date_default_timezone_set('America/Caracas');

    class MYPDF extends TCPDF {
        protected $cuenta;
        protected $nombre_cliente;
        protected $fecha_inicio;
        protected $fecha_fin;
        protected $moneda;
        protected $direccion;
        
        public function __construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa, $cuenta = '', $nombre_cliente = '', $fecha_inicio = '', $fecha_fin = '', $moneda = 'VES', $direccion = '') {
            parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
            $this->cuenta = $cuenta;
            $this->nombre_cliente = $nombre_cliente;
            $this->fecha_inicio = $fecha_inicio;
            $this->fecha_fin = $fecha_fin;
            $this->moneda = $moneda;
            $this->direccion = $direccion;
        }
        
        public function Header() {
            $logo_path = realpath(__DIR__ . '/../assets/images/logo-banco.jpg');
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 8, 20, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            
            $this->SetFont('helvetica', 'B', 9);
            $this->SetY(10);
            $this->Cell(0, 4, 'BANCO CARONI C.A. | RIF: J-12345678-9', 0, 1, 'C');
            
            $this->SetFont('helvetica', '', 7);
            $this->Cell(0, 4, 'Emisión: '.date('d/m/Y H:i'), 0, 1, 'C');
            
            $this->SetY(18);
            
            $this->SetFont('helvetica', 'B', 7);
            $this->Cell(20, 4, 'CLIENTE:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 7);
            $this->Cell(0, 4, strtoupper($this->nombre_cliente), 0, 1, 'L');
            
            $this->SetFont('helvetica', 'B', 7);
            $this->Cell(20, 4, 'CUENTA:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 7);
            $formatted_account = formatAccountNumber($this->cuenta);
            $this->Cell(0, 4, $formatted_account.' | '.$this->moneda, 0, 1, 'L');
            
            $this->SetFont('helvetica', 'B', 7);
            $this->Cell(20, 4, 'DIRECCIÓN:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 7);
            $this->Cell(0, 4, strtoupper($this->direccion), 0, 1, 'L');
            
            $this->SetLineWidth(0.1);
            $this->Line(10, $this->GetY()+2, $this->getPageWidth()-10, $this->GetY()+2);
            $this->SetY($this->GetY()+5);
        }
        
        public function Footer() {
            $this->SetY(-10);
            $this->SetFont('helvetica', 'I', 6);
            $this->Cell(0, 5, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C');
        }
    }

    $fecha_inicio = date("$anio-$mes-01");
    $fecha_fin = date("$anio-$mes-t");
    $direccion_completa = trim(implode(' ', array_filter([$direccion1, $direccion2, $ciudad])));
    
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false, false, $cuenta, $nombre_cliente, $fecha_inicio, $fecha_fin, $moneda, $direccion_completa);
    
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Estado de Cuenta '.$meses_espanol[$mes].' '.$anio);
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 35, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(5);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 7);
    
    $html = '
    <style>
        .month-header {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 7pt;
            padding: 1mm 2mm;
            margin: 2mm 0 1mm 0;
            border-left: 2mm solid #003366;
            page-break-after: avoid;
            text-align: center;
        }
        .saldo-inicial-mejorado {
            text-align: right;
            font-size: 7pt;
            margin-right: 10px;
            margin-bottom: 5px;
            border-bottom: 0.5px solid #e0e0e0;
            padding-bottom: 3px;
        }
        .saldo-inicial-mejorado .label {
            font-weight: bold;
            color: #003366;
        }
        .saldo-inicial-mejorado .value {
            font-weight: bold;
        }
        .saldo-inicial-mejorado .fecha {
            color: #666666;
            font-size: 6pt;
            margin-left: 5px;
        }
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            margin: 0 0 1mm 0;
        }
        .transaction-table th {
            background-color: #003366;
            color: white;
            padding: 1.5mm;
            text-align: center;
            font-weight: bold;
            border: 0.1mm solid #003366;
        }
        .transaction-table td {
            padding: 1.5mm;
            border: 0.1mm solid #e0e0e0;
            vertical-align: middle;
        }
        .date-col { width: 10%; text-align: center; }
        .ref-col { width: 12%; text-align: center; font-size: 5.5pt; }
        .desc-col { width: 38%; font-size: 6pt; }
        .amount-col { width: 10%; text-align: right; }
        .balance-col { width: 10%; text-align: right; }
        .debit { color: #cc0000; }
        .credit { color: #009900; }
        .summary-section {
            page-break-before: avoid;
            margin-top: 5mm;
        }
        .summary-header {
            background-color: #003366;
            color: white;
            padding: 2mm;
            font-weight: bold;
            font-size: 7pt;
            text-align: center;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .summary-table td {
            padding: 1.5mm;
            border: 0.1mm solid #e0e0e0;
        }
        .footer-note {
            font-size: 5.5pt;
            text-align: center;
            color: #666666;
            margin-top: 5mm;
            padding-top: 3mm;
            border-top: 0.2mm solid #f0f0f0;
        }
        .account-number {
            font-family: "Courier New", monospace;
            letter-spacing: 0.5px;
            font-size: 6.5pt;
        }
    </style>
    
    <div style="margin-bottom: 5px;">
        <div class="month-header">'.strtoupper($meses_espanol[$mes].' '.$anio).'</div>
        '.(!empty($cuenta) ? '
        <div class="saldo-inicial-mejorado">
            <span class="label">SALDO INICIAL: </span>
            <span class="value" style="color: '.getSaldoColor($saldo_inicial).'">
                '.number_format($saldo_inicial, 2, ',', '.').' '.$moneda.'
            </span>
            <span class="fecha">(al '.date('d/m/Y', strtotime($fecha_inicio)).')</span>
        </div>' : '').'
    </div>
    
    <table class="transaction-table">
        <thead>
            <tr>
                <th class="date-col">FECHA</th>
                <th class="ref-col">REFERENCIA</th>';

    if (empty($cuenta)) {
        $html .= '<th class="desc-col">CUENTA</th>';
    }
    
    $html .= '
                <th class="desc-col">DESCRIPCIÓN</th>
                <th class="amount-col">DÉBITO</th>
                <th class="amount-col">CRÉDITO</th>
                '.(!empty($cuenta) ? '<th class="balance-col">SALDO</th>' : '').'
            </tr>
        </thead>
        <tbody>';
    
    if (!empty($transacciones)) {
        foreach ($transacciones as $trans) {
            $html .= '
            <tr>
                <td class="date-col">'.date('d/m/Y', strtotime($trans['fecha'])).'</td>
                <td class="ref-col">'.htmlspecialchars($trans['referencia']).'</td>';
            
            if (empty($cuenta)) {
                $html .= '<td class="desc-col account-number">'.htmlspecialchars(formatAccountNumber($trans['cuenta'] ?? '')).'</td>';
            }
            
            $html .= '
                <td class="desc-col">'.htmlspecialchars($trans['descripcion']).'</td>
                <td class="amount-col '.($trans['tipo'] == 'D' ? 'debit' : '').'">'.($trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '-').'</td>
                <td class="amount-col '.($trans['tipo'] == 'C' ? 'credit' : '').'">'.($trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '-').'</td>
                '.(!empty($cuenta) ? '<td class="balance-col" style="color: '.getSaldoColor($trans['saldo']).';">'.number_format($trans['saldo'], 2, ',', '.').'</td>' : '').'
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="'.(!empty($cuenta) ? '6' : '5').'" style="text-align:center;">No se encontraron transacciones</td></tr>';
    }
    
    $html .= '
        </tbody>
    </table>
    
    <div class="summary-section">
        <div class="summary-header">
            RESUMEN GENERAL
        </div>
        <table class="summary-table">
            '.(!empty($cuenta) ? '
            <tr>
                <td style="width: 40%;"><strong>Saldo Inicial</strong></td>
                <td style="width: 60%; text-align: right;">'.number_format($saldo_inicial, 2, ',', '.').' '.$moneda.'</td>
            </tr>' : '').'
            <tr>
                <td><strong>Total Débitos ('.count(array_filter($transacciones, function($t) { return $t['tipo'] == 'D'; })).' movimientos)</strong></td>
                <td style="text-align: right; color: #cc0000;">'.number_format($total_debitos, 2, ',', '.').' '.$moneda.'</td>
            </tr>
            <tr>
                <td><strong>Total Créditos ('.count(array_filter($transacciones, function($t) { return $t['tipo'] == 'C'; })).' movimientos)</strong></td>
                <td style="text-align: right; color: #009900;">'.number_format($total_creditos, 2, ',', '.').' '.$moneda.'</td>
            </tr>';

    if (!empty($cuenta)) {
        $html .= '
            <tr>
                <td style="font-weight: bold;"><strong>Saldo Final (desde BD)</strong></td>
                <td style="text-align: right; font-weight: bold; color: '.getSaldoColor($saldo_final).';">'.number_format($saldo_final, 2, ',', '.').' '.$moneda.'</td>
            </tr>';
    }
    
    $html .= '
        </table>
    </div>
    <div class="footer-note">
        Documento generado electrónicamente - Banco Caroni C.A.<br>
        Fecha y hora de generación: '.date('d-m-Y H:i A').' (Hora de Venezuela)
    </div>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('estado_cuenta_'.(!empty($cuenta) ? $cuenta.'_' : '').$mes.'_'.$anio.'.pdf', 'D');
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
                
                <?php if (!empty($cuenta)): ?>
                    <div class="account-info">
                        <p><strong><i class="fas fa-user"></i> Cliente:</strong> <?= htmlspecialchars($nombre_cliente) ?></p>
                        <p><strong><i class="fas fa-wallet"></i> Número de Cuenta:</strong> <?= htmlspecialchars(formatAccountNumber($cuenta)) ?></p>
                        <p><strong><i class="fas fa-coins"></i> Saldo Inicial:</strong> <?= number_format($saldo_inicial, 2, ',', '.') ?></p>
                        <p><strong><i class="fas fa-database"></i> Saldo Final (desde BD):</strong> <span style="color: <?= getSaldoColor($saldo_final) ?>"><?= number_format($saldo_final, 2, ',', '.') ?></span></p>
                    </div>
                <?php endif; ?>
                
                <div class="table-container">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th><i class="far fa-calendar"></i> Fecha</th>
                                <th><i class="fas fa-barcode"></i> serial</th>
                                <?php if (empty($cuenta)): ?>
                                    <th><i class="fas fa-wallet"></i> Cuenta</th>
                                <?php endif; ?>
                                <th><i class="fas fa-align-left"></i> Descripción</th>
                                <th><i class="fas fa-arrow-down"></i> Débito</th>
                                <th><i class="fas fa-arrow-up"></i> Crédito</th>
                                <?php if (!empty($cuenta)): ?>
                                    <th><i class="fas fa-wallet"></i> Saldo</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacciones as $trans): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($trans['referencia']) ?></td>
                                    <?php if (empty($cuenta)): ?>
                                        <td class="account-number"><?= htmlspecialchars(formatAccountNumber($trans['cuenta'] ?? '')) ?></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                                    <td class="debit"><?= $trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                    <td class="credit"><?= $trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                    <?php if (!empty($cuenta)): ?>
                                        <td class="balance" style="color: <?= getSaldoColor($trans['saldo']) ?>"><?= number_format($trans['saldo'], 2, ',', '.') ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="month-totals">
                    <div class="total-box">
                        <div class="total-label"><i class="fas fa-arrow-down"></i> Total Débitos</div>
                        <div class="total-value"><?= number_format($total_debitos, 2, ',', '.') ?></div>
                        <div class="total-count"><?= count(array_filter($transacciones, function($t) { return $t['tipo'] == 'D'; })) ?> movimientos</div>
                    </div>
                    <div class="total-box">
                        <div class="total-label"><i class="fas fa-arrow-up"></i> Total Créditos</div>
                        <div class="total-value"><?= number_format($total_creditos, 2, ',', '.') ?></div>
                        <div class="total-count"><?= count(array_filter($transacciones, function($t) { return $t['tipo'] == 'C'; })) ?> movimientos</div>
                    </div>
                    <?php if (!empty($cuenta)): ?>
                        <div class="total-box">
                            <div class="total-label"><i class="fas fa-database"></i> Saldo Final (BD)</div>
                            <div class="total-value" style="color: <?= getSaldoColor($saldo_final) ?>"><?= number_format($saldo_final, 2, ',', '.') ?></div>
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