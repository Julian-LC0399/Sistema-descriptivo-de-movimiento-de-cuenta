<?php
ob_start();

require_once __DIR__ . '/../includes/config.php'; 
session_start();

// Inicializar variables
$nombre_cliente = 'CLIENTE NO ESPECIFICADO';
$nombre_cliente_web = '';
$transacciones = [];
$saldo_inicial = 0;
$saldo_final = 0;
$total_debitos = 0;
$total_creditos = 0;
$count_debitos = 0;
$count_creditos = 0;
$moneda = 'BS';
$direccion1 = $direccion2 = $direccion3 = $ciudad = '';

function getMesEspanol($mes) {
    $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
        '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
        '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
        '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    return $meses[$mes] ?? $mes;
}

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

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$cuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : null;

// Validar mes y año
if (!preg_match('/^(0[1-9]|1[0-2])$/', $mes)) {
    die("Mes inválido. Debe ser entre 01 y 12.");
}

if (!preg_match('/^\d{4}$/', $ano)) {
    die("Año inválido. Use formato YYYY.");
}

// Calcular fechas del mes
$fecha_inicio = "$ano-$mes-01";
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

if (!empty($cuenta) && !preg_match('/^[0-9]{9,20}$/', $cuenta)) {
    die("Número de cuenta inválido. Debe contener solo dígitos (9-20 caracteres).");
}

if (!empty($cuenta)) {
    try {
        // Obtener saldo inicial
        $sql_saldo_inicial = "SELECT t.trdbal AS saldo_inicial FROM actrd t 
                             WHERE t.trdacc = :cuenta AND t.trddat < :fecha_inicio
                             ORDER BY t.trddat DESC, t.trdseq DESC LIMIT 1";
        $stmt_saldo = $pdo->prepare($sql_saldo_inicial);
        $stmt_saldo->execute([':cuenta' => $cuenta, ':fecha_inicio' => $fecha_inicio]);
        
        if ($resultado = $stmt_saldo->fetch(PDO::FETCH_ASSOC)) {
            $saldo_inicial = $resultado['saldo_inicial'];
        } else {
            $stmt_saldo_cuenta = $pdo->prepare("SELECT acmbal, acmccy FROM acmst WHERE acmacc = :cuenta");
            $stmt_saldo_cuenta->execute([':cuenta' => $cuenta]);
            if ($resultado = $stmt_saldo_cuenta->fetch(PDO::FETCH_ASSOC)) {
                $saldo_inicial = $resultado['acmbal'];
                $moneda = $resultado['acmccy'] ?? 'BS';
            }
        }

        // Obtener información del cliente (MODIFICADO)
        $stmt_cliente = $pdo->prepare("SELECT 
                                      CONCAT(c.cusna1, ' ', IFNULL(c.cusna2, ''), ' ', c.cusln1, ' ', IFNULL(c.cusln2, '')) AS nombre_completo,
                                      c.cusdir1 AS direccion1, 
                                      c.cusdir2 AS direccion2, 
                                      c.cusdir3 AS direccion3,
                                      c.cuscty AS ciudad
                                      FROM cumst c 
                                      JOIN acmst a ON c.cuscun = a.acmcun
                                      WHERE a.acmacc = :cuenta");
        $stmt_cliente->execute([':cuenta' => $cuenta]);
        $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC) ?? [];
        
        $nombre_cliente = $cliente_info['nombre_completo'] ?? 'CLIENTE NO ENCONTRADO';
        $direccion1 = $cliente_info['direccion1'] ?? '';
        $direccion2 = $cliente_info['direccion2'] ?? '';
        $direccion3 = $cliente_info['direccion3'] ?? '';
        $ciudad = $cliente_info['ciudad'] ?? '';
        $nombre_cliente_web = $nombre_cliente;
    } catch(PDOException $e) {
        error_log("Error al obtener datos iniciales: " . $e->getMessage());
    }
}

// Consulta de transacciones del mes
$sql = "SELECT t.trddat AS fecha, t.trdseq AS secuencia, t.trdmd AS tipo,
               t.trdamt AS monto, t.trdbal AS saldo, t.trddsc AS descripcion,
               t.trdref AS referencia, t.trdusr AS usuario, a.acmccy AS moneda";
               
if (empty($cuenta)) $sql .= ", t.trdacc AS cuenta";

$sql .= " FROM actrd t JOIN acmst a ON t.trdacc = a.acmacc
        WHERE t.trddat BETWEEN :fecha_inicio AND :fecha_fin";

if (!empty($cuenta)) $sql .= " AND t.trdacc = :cuenta";
$sql .= " ORDER BY t.trddat, t.trdseq";

try {
    $stmt = $pdo->prepare($sql);
    $params = [':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin];
    if (!empty($cuenta)) $params[':cuenta'] = $cuenta;
    
    $stmt->execute($params);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $saldo_acumulado = $saldo_inicial;
    foreach ($transacciones as $trans) {
        $moneda = $trans['moneda'] ?? $moneda;
        
        if ($trans['tipo'] == 'D') {
            $total_debitos += $trans['monto'];
            $count_debitos++;
        } else {
            $total_creditos += $trans['monto'];
            $count_creditos++;
        }
        $saldo_acumulado = $trans['saldo'];
    }
    
    $saldo_final = $saldo_acumulado;
} catch(PDOException $e) {
    die("Ocurrió un error al procesar su solicitud. Por favor intente más tarde.");
}

if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once __DIR__ . '/../includes/library/tcpdf.php';
    date_default_timezone_set('America/Caracas');

    class MYPDF extends TCPDF {
        protected $cuenta;
        protected $nombre_cliente;
        protected $moneda;
        protected $direccion;
        
        public function __construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa, $cuenta = '', $nombre_cliente = '', $moneda = 'BS', $direccion = '') {
            parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
            $this->cuenta = $cuenta;
            $this->nombre_cliente = $nombre_cliente;
            $this->moneda = $moneda;
            $this->direccion = $direccion;
        }
        
        public function Header() {
            // Logo
            $logo_path = realpath(__DIR__ . '/../assets/images/logo-banco.jpg');
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 8, 35, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            
            // Fecha de emisión
            $this->SetFont('helvetica', 'B', 8);
            $this->SetTextColor(80, 80, 80);
            $this->SetFillColor(245, 245, 245);
            $this->SetY(30);
            $this->Cell(35, 6, 'EMITIDO: '.date('d/m/Y H:i'), 0, 1, 'C', 1);
            
            // Línea separadora
            $this->SetLineWidth(0.5);
            $this->SetDrawColor(0, 51, 102);
            $this->Line(10, 38, $this->getPageWidth()-10, 38);
            
            // Información del cliente
            $this->SetY(15);
            $this->SetX(120);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, strtoupper($this->nombre_cliente), 0, 1, 'L');
            
            // Dirección (MODIFICADO para mostrar mejor la dirección)
            $this->SetFont('helvetica', '', 8);
            $this->SetX(120);
            $this->MultiCell(80, 4, strtoupper($this->direccion), 0, 'L');
            
            // Número de cuenta
            $this->SetFont('helvetica', 'B', 8);
            $this->SetX(120);
            $this->Cell(0, 6, 'CUENTA: '.formatAccountNumber($this->cuenta), 0, 1, 'L');
            
            $this->SetY(42);
        }
        
        public function Footer() {
            $this->SetY(-10);
            $this->SetFont('helvetica', 'I', 6);
            $this->Cell(0, 5, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C');
        }
    }

    // Dirección completa (MODIFICADO)
    $direccion_completa = trim(implode(', ', array_filter([
        $cliente_info['direccion1'] ?? '',
        $cliente_info['direccion2'] ?? '',
        $cliente_info['direccion3'] ?? '',
        $cliente_info['ciudad'] ?? ''
    ])));
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false, false, $cuenta, $nombre_cliente, $moneda, $direccion_completa);
    
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Estado de Cuenta '.getMesEspanol($mes).' '.$ano);
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 45, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(5);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 7);
    
    $mes_nombre = strtoupper(getMesEspanol($mes).' '.$ano);
    
    $html = '
    <style>
        .logo-container {
            position: relative;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
            padding: 2px;
            display: inline-block;
            margin-bottom: 5px;
        }
        
        .issue-date {
            font-family: "Courier New", monospace;
            font-size: 7.5pt;
            font-weight: bold;
            color: #003366;
            background-color: #f8f8f8;
            padding: 2px 5px;
            border-radius: 3px;
            border-left: 3px solid #003366;
        }
        
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
    </style>';

    $html .= '
    <div style="margin-bottom: 5px;">
        <div class="month-header">'.$mes_nombre.'</div>
        <div class="saldo-inicial-mejorado">
            <span class="label">SALDO INICIAL: </span>
            <span class="value" style="color: '.getSaldoColor($saldo_inicial).'">
                '.number_format($saldo_inicial, 2, ',', '.').' '.$moneda.'
            </span>
            <span class="fecha">(al '.date('d/m/Y', strtotime($fecha_inicio)).')</span>
        </div>
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
                <th class="balance-col">SALDO</th>
            </tr>
        </thead>
        <tbody>';
    
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
                <td class="balance-col" style="color: '.getSaldoColor($trans['saldo']).';">'.(!empty($cuenta) ? number_format($trans['saldo'], 2, ',', '.') : '-').'</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>
    <div style="margin: 2mm 0 4mm 0; page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: separate; border-spacing: 1mm; font-size: 7pt;">
            <tr>
                <td style="width: 32%; text-align: center; padding: 1.5mm; background-color: #fff8f8; border: 0.3mm solid #ffdddd; border-radius: 1mm;">
                    <div style="font-weight: bold; margin-bottom: 0.5mm; color: #990000;">DÉBITOS</div>
                    <div style="font-size: 8pt; font-weight: bold; color: #cc0000;">'.number_format($total_debitos, 2, ',', '.').' '.$moneda.'</div>
                    <div style="font-size: 5pt; color: #666; margin-top: 0.5mm;">'.$count_debitos.' Movimientos ▼</div>
                </td>
                
                <td style="width: 32%; text-align: center; padding: 1.5mm; background-color: #f8fff8; border: 0.3mm solid #ddffdd; border-radius: 1mm;">
                    <div style="font-weight: bold; margin-bottom: 0.5mm; color: #009900;">CRÉDITOS</div>
                    <div style="font-size: 8pt; font-weight: bold; color: #009900;">'.number_format($total_creditos, 2, ',', '.').' '.$moneda.'</div>
                    <div style="font-size: 5pt; color: #666; margin-top: 0.5mm;">'.$count_creditos.' Movimientos ▲</div>
                </td>
                
                <td style="width: 36%; text-align: center; padding: 1.5mm; background-color: #f8f8ff; border: 0.3mm solid '.getSaldoColor($saldo_final).'; border-radius: 1mm;">
                    <div style="font-weight: bold; margin-bottom: 0.5mm; color: #003366;">SALDO FINAL</div>
                    <div style="font-size: 9pt; font-weight: bold; color: '.getSaldoColor($saldo_final).';">'.number_format($saldo_final, 2, ',', '.').' '.$moneda.'</div>
                    <div style="font-size: 5pt; color: #666; margin-top: 0.5mm;">'.date('d/m/Y', strtotime(end($transacciones)['fecha'])).'</div>
                </td>
            </tr>
        </table>
    </div>';

    $html .= '
    <div class="footer-note">
        Documento generado electrónicamente - Banco Caroni C.A.<br>
        Fecha y hora de generación: '.date('d-m-Y H:i A').' (Hora de Venezuela)
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('estado_cuenta_'.(!empty($cuenta) ? $cuenta.'_' : '').$mes.'_'.$ano.'.pdf', 'D');
    exit();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Caroni - Estado de Cuenta Mensual</title>
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
            <h2>ESTADO DE CUENTA MENSUAL</h2>
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
                        <select id="mes" name="mes" class="filter-input">
                            <?php
                            $meses = [
                                '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
                                '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                                '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
                                '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                            ];
                            
                            foreach ($meses as $num => $nombre) {
                                $selected = ($num == $mes) ? 'selected' : '';
                                echo "<option value=\"$num\" $selected>$nombre</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="ano" class="filter-label">
                            <i class="far fa-calendar-alt"></i> Año
                        </label>
                        <select id="ano" name="ano" class="filter-input">
                            <?php
                            $current_year = date('Y');
                            for ($i = $current_year; $i >= $current_year - 5; $i--) {
                                $selected = ($i == $ano) ? 'selected' : '';
                                echo "<option value=\"$i\" $selected>$i</option>";
                            }
                            ?>
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
                    <a href="?mes=<?= urlencode($mes) ?>&ano=<?= urlencode($ano) ?>&cuenta=<?= urlencode($cuenta) ?>&export=pdf" 
                       class="btn-export pdf" target="_blank" title="Exportar a PDF">
                       <i class="fas fa-file-pdf"></i> Exportar a PDF
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($transacciones)): ?>
            <div class="month-section">
                <h3 class="month-title"><?= strtoupper(getMesEspanol($mes).' '.$ano) ?></h3>
                
                <?php if (!empty($cuenta)): ?>
                    <div class="account-info">
                        <p><strong><i class="fas fa-user"></i> Cliente:</strong> <?= htmlspecialchars($nombre_cliente_web) ?></p>
                        <p><strong><i class="fas fa-wallet"></i> Número de Cuenta:</strong> <?= htmlspecialchars(formatAccountNumber($cuenta)) ?></p>
                        <p><strong><i class="fas fa-coins"></i> Saldo Inicial:</strong> <?= number_format($saldo_inicial, 2, ',', '.') ?> <?= $moneda ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="table-container">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th><i class="far fa-calendar"></i> Fecha</th>
                                <th><i class="fas fa-barcode"></i> Referencia</th>
                                <?php if (empty($cuenta)): ?>
                                    <th><i class="fas fa-wallet"></i> Cuenta</th>
                                <?php endif; ?>
                                <th><i class="fas fa-align-left"></i> Descripción</th>
                                <th style="text-align: right;"><i class="fas fa-arrow-down"></i> Débito</th>
                                <th style="text-align: right;"><i class="fas fa-arrow-up"></i> Crédito</th>
                                <?php if (!empty($cuenta)): ?>
                                    <th style="text-align: right;"><i class="fas fa-wallet"></i> Saldo</th>
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
                                    <td class="debit" style="text-align: right;"><?= $trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                    <td class="credit" style="text-align: right;"><?= $trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                    <?php if (!empty($cuenta)): ?>
                                        <td class="balance" style="text-align: right; color: <?= getSaldoColor($trans['saldo']) ?>"><?= number_format($trans['saldo'], 2, ',', '.') ?> <?= $moneda ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="month-totals">
                    <div class="total-box">
                        <div class="total-label"><i class="fas fa-arrow-down"></i> Total Débitos</div>
                        <div class="total-value"><?= number_format($total_debitos, 2, ',', '.') ?> <?= $moneda ?></div>
                        <div class="total-count"><?= $count_debitos ?> movimientos</div>
                    </div>
                    <div class="total-box">
                        <div class="total-label"><i class="fas fa-arrow-up"></i> Total Créditos</div>
                        <div class="total-value"><?= number_format($total_creditos, 2, ',', '.') ?> <?= $moneda ?></div>
                        <div class="total-count"><?= $count_creditos ?> movimientos</div>
                    </div>
                    <?php if (!empty($cuenta)): ?>
                        <div class="total-box">
                            <div class="total-label"><i class="fas fa-coins"></i> Saldo Final</div>
                            <div class="total-value" style="color: <?= getSaldoColor($saldo_final) ?>"><?= number_format($saldo_final, 2, ',', '.') ?> <?= $moneda ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-info-circle"></i>
                No se encontraron transacciones para el mes seleccionado
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validar número de cuenta si se ingresa
            document.querySelector('form').addEventListener('submit', function(e) {
                const cuenta = document.getElementById('cuenta').value;
                if (cuenta && !/^\d{9,20}$/.test(cuenta)) {
                    alert('Número de cuenta inválido. Debe contener solo dígitos (9-20 caracteres).');
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>