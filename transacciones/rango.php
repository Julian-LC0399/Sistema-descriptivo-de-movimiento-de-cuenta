<?php
require_once __DIR__ . '/../includes/config.php'; 
session_start();

function getMesEspanol($fecha) {
    $meses = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    return $meses[date('F', strtotime($fecha))] ?? date('F', strtotime($fecha));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$cuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : null;

if (!validateDate($fecha_inicio) || !validateDate($fecha_fin)) {
    die("Formato de fecha inválido. Use YYYY-MM-DD.");
}

if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    die("La fecha de inicio no puede ser mayor a la fecha final.");
}

if (!empty($cuenta) && !preg_match('/^[0-9]{9,20}$/', $cuenta)) {
    die("Número de cuenta inválido. Debe contener solo dígitos (9-20 caracteres).");
}

$saldo_inicial = 0;
if (!empty($cuenta)) {
    try {
        $sql_saldo_inicial = "SELECT t.trdbal AS saldo_inicial FROM actrd t 
                             WHERE t.trdacc = :cuenta AND t.trddat < :fecha_inicio
                             ORDER BY t.trddat DESC, t.trdseq DESC LIMIT 1";
        $stmt_saldo = $pdo->prepare($sql_saldo_inicial);
        $stmt_saldo->execute([':cuenta' => $cuenta, ':fecha_inicio' => $fecha_inicio]);
        
        if ($resultado = $stmt_saldo->fetch(PDO::FETCH_ASSOC)) {
            $saldo_inicial = $resultado['saldo_inicial'];
        } else {
            $stmt_saldo_cuenta = $pdo->prepare("SELECT acmbal FROM acmst WHERE acmacc = :cuenta");
            $stmt_saldo_cuenta->execute([':cuenta' => $cuenta]);
            if ($resultado = $stmt_saldo_cuenta->fetch(PDO::FETCH_ASSOC)) {
                $saldo_inicial = $resultado['acmbal'];
            }
        }
    } catch(PDOException $e) {
        error_log("Error al obtener saldo inicial: " . $e->getMessage());
    }
}

$sql = "SELECT t.trddat AS fecha, t.trdseq AS secuencia, t.trdmd AS tipo,
               t.trdamt AS monto, t.trdbal AS saldo, t.trddsc AS descripcion,
               t.trdref AS referencia, t.trdusr AS usuario, a.acmccy AS moneda
        FROM actrd t JOIN acmst a ON t.trdacc = a.acmacc
        WHERE t.trddat BETWEEN :fecha_inicio AND :fecha_fin";

if (!empty($cuenta)) $sql .= " AND t.trdacc = :cuenta";
$sql .= " ORDER BY t.trddat, t.trdseq";

try {
    $stmt = $pdo->prepare($sql);
    $params = [':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin];
    if (!empty($cuenta)) $params[':cuenta'] = $cuenta;
    
    $stmt->execute($params);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $transacciones_por_mes = [];
    foreach ($transacciones as $trans) {
        $mes_ano = date('m-Y', strtotime($trans['fecha']));
        $transacciones_por_mes[$mes_ano][] = $trans;
    }
    
    $saldos_por_mes = [];
    $saldo_acumulado = $saldo_inicial;
    foreach ($transacciones_por_mes as $mes_ano => $trans_mes) {
        $saldos_por_mes[$mes_ano]['saldo_inicial'] = $saldo_acumulado;
        $total_debitos = $total_creditos = 0;
        
        foreach ($trans_mes as $trans) {
            if ($trans['tipo'] == 'D') $total_debitos += $trans['monto'];
            else $total_creditos += $trans['monto'];
            $saldo_acumulado = $trans['saldo'];
        }
        
        $saldos_por_mes[$mes_ano]['total_debitos'] = $total_debitos;
        $saldos_por_mes[$mes_ano]['total_creditos'] = $total_creditos;
        $saldos_por_mes[$mes_ano]['saldo_final'] = $saldo_acumulado;
    }

    if (!empty($cuenta)) {
        $stmt_cliente_web = $pdo->prepare("SELECT c.cusna1 AS nombre_completo 
                                          FROM cumst c JOIN acmst a ON c.cuscun = a.acmcun
                                          WHERE a.acmacc = :cuenta");
        $stmt_cliente_web->execute([':cuenta' => $cuenta]);
        $cliente_info_web = $stmt_cliente_web->fetch(PDO::FETCH_ASSOC) ?? [];
        $nombre_cliente_web = $cliente_info_web['nombre_completo'] ?? 'CLIENTE NO ENCONTRADO';
    } else {
        $nombre_cliente_web = '';
    }
} catch(PDOException $e) {
    die("Ocurrió un error al procesar su solicitud. Por favor intente más tarde.");
}

if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once __DIR__ . '/../includes/library/tcpdf.php';
    
    $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Estado de Cuenta '.$fecha_inicio.' al '.$fecha_fin);
    
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

    try {
        $stmt_cliente = $pdo->prepare("SELECT c.cusna1 AS nombre_completo, c.cusna2 AS direccion1, 
                                      c.cusna3 AS direccion2, c.cuscty AS ciudad, a.acmccy AS moneda
                                      FROM cumst c JOIN acmst a ON c.cuscun = a.acmcun
                                      WHERE a.acmacc = :cuenta");
        $stmt_cliente->execute([':cuenta' => $cuenta]);
        $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC) ?? [];
        
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
                <div class="address-line"><strong>'.strtoupper($direccion1).'</strong></div>
                <div class="address-line"><strong>'.strtoupper($direccion2).'</strong></div>
                <div class="address-line"><strong>'.strtoupper($ciudad).'</strong></div>
                <div><strong>NÚMERO DE CUENTA: '.$cuenta.'</strong></div>
                <div><strong>Fecha Emisión: '.date('d/m/Y H:i A').'</strong></div>
            </div>
        </div>
    </div>';
    
    $total_general_debitos = $total_general_creditos = 0;
    $saldo_final = $saldo_inicial;
    
    foreach ($transacciones_por_mes as $mes_ano => $trans_mes) {
        $mes_nombre = getMesEspanol('01-'.$mes_ano).' '.date('Y', strtotime('01-'.$mes_ano));
        $saldo_mes = $saldos_por_mes[$mes_ano];
        
        $html .= '
        <div>
            <div class="title">ESTADO DE CUENTA '.strtoupper($mes_nombre).'</div>
            <div style="text-align:center; font-size:8px; margin-bottom:3px;">
                <strong>Saldo Inicial:</strong> '.number_format($saldo_mes['saldo_inicial'], 2, ',', '.').' '.$moneda.'
            </div>
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th class="date-col">Fecha</th>
                        <th class="ref-col">Referencia</th>
                        <th class="desc-col">Descripción</th>
                        <th class="amount-col">Débito</th>
                        <th class="amount-col">Crédito</th>
                        <th class="balance-col">Saldo</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($trans_mes as $trans) {
            $html .= '
                <tr>
                    <td class="date-col">'.date('d/m/Y', strtotime($trans['fecha'])).'</td>
                    <td class="ref-col">'.htmlspecialchars($trans['referencia']).'</td>
                    <td class="desc-col">'.htmlspecialchars($trans['descripcion']).'</td>
                    <td class="amount-col">'.($trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '').'</td>
                    <td class="amount-col">'.($trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '').'</td>
                    <td class="balance-col">'.number_format($trans['saldo'], 2, ',', '.').'</td>
                </tr>';
            $saldo_final = $trans['saldo'];
        }
        
        $html .= '
                </tbody>
            </table>
            <div class="totals">
                <div class="total-row">
                    <span>Total Débitos:</span>
                    <span>'.number_format($saldo_mes['total_debitos'], 2, ',', '.').' '.$moneda.'</span>
                </div>
                <div class="total-row">
                    <span>Total Créditos:</span>
                    <span>'.number_format($saldo_mes['total_creditos'], 2, ',', '.').' '.$moneda.'</span>
                </div>
                <div class="total-row">
                    <span>Saldo Final:</span>
                    <span>'.number_format($saldo_mes['saldo_final'], 2, ',', '.').' '.$moneda.'</span>
                </div>
            </div>
        </div>';
        
        $total_general_debitos += $saldo_mes['total_debitos'];
        $total_general_creditos += $saldo_mes['total_creditos'];
    }
    
    $html .= '
    <div class="title">RESUMEN GENERAL</div>
    <div class="totals">
        <div class="total-row">
            <span>Saldo Inicial Total:</span>
            <span>'.number_format($saldo_inicial, 2, ',', '.').' '.$moneda.'</span>
        </div>
        <div class="total-row">
            <span>Total General Débitos:</span>
            <span>'.number_format($total_general_debitos, 2, ',', '.').' '.$moneda.'</span>
        </div>
        <div class="total-row">
            <span>Total General Créditos:</span>
            <span>'.number_format($total_general_creditos, 2, ',', '.').' '.$moneda.'</span>
        </div>
        <div class="total-row">
            <span>Saldo Final Total:</span>
            <span>'.number_format($saldo_final, 2, ',', '.').' '.$moneda.'</span>
        </div>
    </div>
    <div class="page-number">Página '.$pdf->getAliasNumPage().' / '.$pdf->getAliasNbPages().'</div>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('estado_cuenta_'.$fecha_inicio.'_'.$fecha_fin.'.pdf', 'D');
    exit();
}

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
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/rango.css">
    <link rel="stylesheet" href="../assets/css/pdf-export.css">
    <link rel="stylesheet" href="../assets/css/pdf-export-individual.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>BANCO CARONI</h1>
            <h2>ESTADO DE CUENTA POR RANGO</h2>
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
                        <label for="fecha_inicio" class="filter-label">
                            <i class="far fa-calendar-alt"></i> Fecha Inicial
                        </label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>" 
                               class="filter-input" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="fecha_fin" class="filter-label">
                            <i class="far fa-calendar-alt"></i> Fecha Final
                        </label>
                        <input type="date" id="fecha_fin" name="fecha_fin" 
                               value="<?= htmlspecialchars($fecha_fin) ?>" 
                               class="filter-input" required>
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

            <?php if (!empty($transacciones_por_mes)): ?>
                <div class="export-buttons">
                    <a href="?fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>&cuenta=<?= urlencode($cuenta) ?>&export=pdf" 
                       class="btn-export pdf" target="_blank" title="Exportar Todo a PDF">
                       <i class="fas fa-print"></i> Exportar todo
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($transacciones_por_mes)): ?>
            <?php foreach ($transacciones_por_mes as $mes_ano => $trans_mes): ?>
                <?php 
                $mes_nombre = getMesEspanol('01-'.$mes_ano) . ' ' . date('Y', strtotime('01-'.$mes_ano));
                $saldo_mes = $saldos_por_mes[$mes_ano];
                $primer_dia_mes = date('Y-m-01', strtotime('01-'.$mes_ano));
                $ultimo_dia_mes = date('Y-m-t', strtotime('01-'.$mes_ano));
                ?>
                
                <div class="month-section">
                    <h3 class="month-title"><?= strtoupper($mes_nombre) ?></h3>
                    
                    <?php if (!empty($trans_mes)): ?>
                        <div class="export-month-buttons">
                            <a href="?fecha_inicio=<?= $primer_dia_mes ?>&fecha_fin=<?= $ultimo_dia_mes ?>&cuenta=<?= urlencode($cuenta) ?>&export=pdf" 
                               class="btn-export pdf" target="_blank" title="Exportar Este Mes a PDF">
                               <i class="fas fa-print"></i> Exportar Mes
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="account-info">
                        <p><strong><i class="fas fa-user"></i> Cliente:</strong> <?= htmlspecialchars($nombre_cliente_web) ?></p>
                        <p><strong><i class="fas fa-wallet"></i> Número de Cuenta:</strong> <?= htmlspecialchars($cuenta) ?></p>
                        <p><strong><i class="fas fa-coins"></i> Saldo Inicial:</strong> <?= number_format($saldo_mes['saldo_inicial'], 2, ',', '.') ?></p>
                    </div>
                    
                    <div class="table-container">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th><i class="far fa-calendar"></i> Fecha</th>
                                    <th><i class="fas fa-barcode"></i> Serial</th>
                                    <th><i class="fas fa-align-left"></i> Descripción</th>
                                    <th><i class="fas fa-arrow-down"></i> Débito</th>
                                    <th><i class="fas fa-arrow-up"></i> Crédito</th>
                                    <th><i class="fas fa-wallet"></i> Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trans_mes as $trans): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                                        <td><?= htmlspecialchars($trans['referencia']) ?></td>
                                        <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                                        <td class="debit"><?= $trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                        <td class="credit"><?= $trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '' ?></td>
                                        <td class="balance"><?= number_format($trans['saldo'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="month-totals">
                        <div class="total-box">
                            <div class="total-label"><i class="fas fa-arrow-down"></i> Total Débitos</div>
                            <div class="total-value"><?= number_format($saldo_mes['total_debitos'], 2, ',', '.') ?></div>
                        </div>
                        <div class="total-box">
                            <div class="total-label"><i class="fas fa-arrow-up"></i> Total Créditos</div>
                            <div class="total-value"><?= number_format($saldo_mes['total_creditos'], 2, ',', '.') ?></div>
                        </div>
                        <div class="total-box">
                            <div class="total-label"><i class="fas fa-coins"></i> Saldo Final</div>
                            <div class="total-value"><?= number_format($saldo_mes['saldo_final'], 2, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-info-circle"></i>
                No se encontraron transacciones en el período seleccionado
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('form').addEventListener('submit', function(e) {
                const inicio = document.getElementById('fecha_inicio').value;
                const fin = document.getElementById('fecha_fin').value;
                
                if (new Date(inicio) > new Date(fin)) {
                    alert('La fecha de inicio no puede ser mayor a la fecha final');
                    e.preventDefault();
                }
            });

            const today = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_inicio').max = today;
            document.getElementById('fecha_fin').max = today;
            
            document.getElementById('fecha_inicio').addEventListener('change', function() {
                const fechaFin = document.getElementById('fecha_fin');
                if (this.value > fechaFin.value) {
                    fechaFin.value = this.value;
                }
                fechaFin.min = this.value;
            });
        });
    </script>
</body>
</html>