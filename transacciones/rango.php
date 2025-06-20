<?php
// Iniciar buffer de salida para evitar problemas con TCPDF
ob_start();

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

    // Inicializar y calcular totales generales
    $total_general_debitos = 0;
    $total_general_creditos = 0;
    $saldo_final = $saldo_inicial;
    
    foreach ($saldos_por_mes as $mes) {
        $total_general_debitos += $mes['total_debitos'];
        $total_general_creditos += $mes['total_creditos'];
        $saldo_final = $mes['saldo_final'];
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
    // Limpiar buffer de salida antes de generar PDF
    ob_end_clean();
    
    require_once __DIR__ . '/../includes/library/tcpdf.php';
    
    $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Estado de Cuenta '.$fecha_inicio.' al '.$fecha_fin);
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    $logo_path = realpath(__DIR__ . '/../assets/images/logo-banco.jpg');
    $logo_html = '';
    
    if (file_exists($logo_path)) {
        $logo_html = '<img src="'.$logo_path.'" width="100">';
    }

    try {
        $stmt_cliente = $pdo->prepare("SELECT c.cusna1 AS nombre_completo, c.cusna2 AS direccion1, 
                                      c.cusna3 AS direccion2, c.cuscty AS ciudad, a.acmccy AS moneda,
                                      a.acmbrn AS sucursal
                                      FROM cumst c JOIN acmst a ON c.cuscun = a.acmcun
                                      WHERE a.acmacc = :cuenta");
        $stmt_cliente->execute([':cuenta' => $cuenta]);
        $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC) ?? [];
        
        $nombre_cliente = $cliente_info['nombre_completo'] ?? 'CLIENTE NO ENCONTRADO';
        $direccion1 = $cliente_info['direccion1'] ?? '';
        $direccion2 = $cliente_info['direccion2'] ?? '';
        $ciudad = $cliente_info['ciudad'] ?? '';
        $moneda = $cliente_info['moneda'] ?? 'VES';
        $sucursal = $cliente_info['sucursal'] ?? 'ND';
    } catch(PDOException $e) {
        error_log("Error al obtener info cliente: " . $e->getMessage());
        $nombre_cliente = 'CLIENTE NO ENCONTRADO';
        $direccion1 = $direccion2 = $ciudad = '';
        $moneda = 'VES';
        $sucursal = 'ND';
    }
    
    $html = '
    <style>
        .header { 
            text-align: center; 
            margin-bottom: 5px; 
            border-bottom: 1px solid #003366;
            padding-bottom: 5px;
        }
            
        .client-info {
            font-size: 9pt;
            line-height: 1.3;
            margin-bottom: 5px;
        }
        .account-box {
            background-color: #f5f5f5;
            border-left: 3px solid #003366;
            padding: 5px;
            margin: 5px 0;
            font-size: 8pt;
        }
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-top: 5px;
        }
        .transaction-table th {
            background-color: #003366;
            color: white;
            padding: 3px;
            text-align: center;
        }
        .transaction-table td {
            padding: 3px;
            border: 0.5px solid #ddd;
        }
        .debit { color: #D32F2F; text-align: right; }
        .credit { color: #388E3C; text-align: right; }
        .totals {
            margin-top: 10px;
            border-top: 1px solid #003366;
            padding-top: 5px;
            font-size: 8pt;
        }
    </style>

    <div class="header">
        '.$logo_html.'
        <div style="font-size: 10pt; font-weight: bold;">ESTADO DE CUENTA</div>
    </div>

    <div class="client-info">
        <strong>CLIENTE:</strong> '.strtoupper($nombre_cliente).'<br>
        <strong>DIRECCIÓN:</strong> '.strtoupper($direccion1.', '.$direccion2.', '.$ciudad).'
    </div>

    <div class="account-box">
        <strong>CUENTA:</strong> '.$cuenta.' | 
        <strong>SUCURSAL:</strong> '.$sucursal.' | 
        <strong>PERÍODO:</strong> '.date('d/m/Y', strtotime($fecha_inicio)).' AL '.date('d/m/Y', strtotime($fecha_fin)).' | 
        <strong>EMISIÓN:</strong> '.date('d-m-Y H:i A').'
    </div>';

    foreach ($transacciones_por_mes as $mes_ano => $trans_mes) {
        $mes_nombre = getMesEspanol('01-'.$mes_ano).' '.date('Y', strtotime('01-'.$mes_ano));
        $saldo_mes = $saldos_por_mes[$mes_ano];
        
        $html .= '
        <div style="margin-top: 10px;">
            <div style="font-weight: bold; font-size: 9pt; border-bottom: 1px solid #003366;">
                '.strtoupper($mes_nombre).'
            </div>
            <div style="font-size: 8pt; margin: 3px 0;">
                <strong>Saldo Inicial:</strong> '.number_format($saldo_mes['saldo_inicial'], 2, ',', '.').' '.$moneda.'
            </div>
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Fecha</th>
                        <th style="width: 20%;">Serial</th>
                        <th style="width: 35%;">Descripción</th>
                        <th style="width: 15%;">Débito</th>
                        <th style="width: 15%;">Crédito</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($trans_mes as $trans) {
            $html .= '
                <tr>
                    <td>'.date('d/m/Y', strtotime($trans['fecha'])).'</td>
                    <td>'.htmlspecialchars($trans['referencia']).'</td>
                    <td>'.htmlspecialchars($trans['descripcion']).'</td>
                    <td class="debit">'.($trans['tipo'] == 'D' ? number_format($trans['monto'], 2, ',', '.') : '').'</td>
                    <td class="credit">'.($trans['tipo'] == 'C' ? number_format($trans['monto'], 2, ',', '.') : '').'</td>
                </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            <div class="totals" style="text-align: right;">
                <div><strong>Total Débitos:</strong> '.number_format($saldo_mes['total_debitos'], 2, ',', '.').' '.$moneda.'</div>
                <div><strong>Total Créditos:</strong> '.number_format($saldo_mes['total_creditos'], 2, ',', '.').' '.$moneda.'</div>
                <div><strong>Saldo Final:</strong> '.number_format($saldo_mes['saldo_final'], 2, ',', '.').' '.$moneda.'</div>
            </div>
        </div>';
    }

    $html .= '
    <div class="totals" style="margin-top: 15px; border-top: 2px solid #003366;">
        <div style="text-align: center; font-weight: bold; font-size: 9pt;">RESUMEN GENERAL</div>
        <div style="text-align: right;">
            <div><strong>Saldo Inicial:</strong> '.number_format($saldo_inicial, 2, ',', '.').' '.$moneda.'</div>
            <div><strong>Total Débitos:</strong> '.number_format($total_general_debitos, 2, ',', '.').' '.$moneda.'</div>
            <div><strong>Total Créditos:</strong> '.number_format($total_general_creditos, 2, ',', '.').' '.$moneda.'</div>
            <div style="font-weight: bold;"><strong>Saldo Final:</strong> '.number_format($saldo_final, 2, ',', '.').' '.$moneda.'</div>
        </div>
    </div>

    <div style="text-align: center; font-size: 7pt; margin-top: 10px; color: #555;">
        Documento generado electrónicamente - Banco Caroni C.A.
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('estado_cuenta_'.$cuenta.'_'.date('Ymd').'.pdf', 'D');
    exit();
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Limpiar buffer para la salida HTML
ob_end_flush();
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