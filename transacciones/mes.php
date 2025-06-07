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
if (!empty($cuenta) && !preg_match('/^[0-9]{10,20}$/', $cuenta)) {
    die("Número de cuenta inválido. Debe contener solo dígitos (10-20 caracteres).");
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
    
    $sql .= " ORDER BY t.trddat ASC, t.trdseq ASC"; // Orden ascendente para cálculo correcto
    
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
            // Agregar saldo acumulado a cada transacción para mostrar en la tabla
            $t['saldo_acumulado'] = $saldo_acumulado;
        }
        unset($t); // Romper la referencia
        
        $saldo_final = $saldo_acumulado;
    } else {
        // Si no hay cuenta específica, solo calcular totales
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
    
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Reporte de Transacciones '.$meses_espanol[$mes].' '.$anio);
    $pdf->SetSubject('Reporte de Transacciones Mensuales');
    
    // Configurar para eliminar líneas automáticas
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurar márgenes
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Agregar página
    $pdf->AddPage();
    
    // Ruta al logo
    $logo_path = realpath(__DIR__ . '/../assets/images/logo-banco.jpg');
    
    // Insertar logo SIN bordes
    if (file_exists($logo_path)) {
        $pdf->Image(
            $logo_path,       // Ruta del archivo
            15,              // Posición X (15mm desde la izquierda)
            15,              // Posición Y (15mm desde arriba)
            30,              // Ancho (30mm)
            0,               // Alto (automático)
            'JPG',           // Tipo de imagen
            '',              // Enlace (vacío)
            'T',             // Alineación (T = top)
            false,           // Resize
            300,             // DPI
            '',              // Alineación (vacío)
            false,           // Máscara
            false,           // Imagen máscara
            0,               // BORDE (0 = sin borde) - CLAVE PARA ELIMINAR LÍNEA
            false,           // Fitbox
            false,           // Hidden
            false            // Fit on page
        );
        $pdf->SetY(25); // Ajustar posición después del logo
    } else {
        error_log("Logo no encontrado: " . $logo_path);
        $pdf->SetY(20); // Posición sin logo
    }
    
    // Contenido HTML del PDF
    $html = '
    <style>
        .header { margin-bottom: 10px; }
        h1 { text-align: center; font-size: 16px; margin: 0; }
        h2 { text-align: center; font-size: 14px; margin: 5px 0 10px 0; }
        .info { font-size: 10px; margin-bottom: 10px; text-align: center; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        td, th { border: 1px solid #ddd; padding: 4px; }
        .debit { color: #FF0000; }
        .credit { color: #00AA00; }
        .totals { margin-top: 10px; font-size: 10px; }
        .total-label { font-weight: bold; }
    </style>
    
    <div class="header">
        <div>
            <h1>BANCO CARONI</h1>
            <h2>REPORTE DE TRANSACCIONES MENSUALES</h2>
        </div>
        <div class="info">
            <strong>Período:</strong> '.$meses_espanol[$mes].' '.$anio.' | 
            '.(!empty($cuenta) ? '<strong>Cuenta:</strong> '.htmlspecialchars($cuenta).' | ' : '').'
            <strong>Generado:</strong> '.date('d/m/Y H:i:s').'
        </div>
    </div>';
    
    if (!empty($cuenta)) {
        $html .= '<div class="info"><strong>Saldo Inicial:</strong> '.number_format($saldo_inicial, 2).' '.(!empty($transacciones[0]['moneda']) ? $transacciones[0]['moneda'] : '').'</div>';
    }
    
    $html .= '
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Cuenta</th>
                <th>Tipo</th>
                <th>Monto</th>
                <th>Saldo '.($cuenta ? 'Acumulado' : '').'</th>
                <th>Descripción</th>
                <th>Referencia</th>
                <th>Usuario</th>
                <th>Moneda</th>
            </tr>
        </thead>
        <tbody>';
    
    if (!empty($transacciones)) {
        foreach ($transacciones as $trans) {
            $html .= '
            <tr>
                <td>'.date('d/m/Y', strtotime($trans['fecha'])).'</td>
                <td>'.htmlspecialchars($trans['cliente']).'</td>
                <td>'.htmlspecialchars($trans['cuenta']).'</td>
                <td class="'.($trans['tipo'] == 'D' ? 'debit' : 'credit').'">'.($trans['tipo'] == 'D' ? 'Débito' : 'Crédito').'</td>
                <td>'.number_format($trans['monto'], 2).'</td>
                <td>'.number_format(!empty($cuenta) ? ($trans['saldo_acumulado'] ?? $trans['saldo']) : $trans['saldo'], 2).'</td>
                <td>'.htmlspecialchars($trans['descripcion']).'</td>
                <td>'.htmlspecialchars($trans['referencia']).'</td>
                <td>'.htmlspecialchars($trans['usuario']).'</td>
                <td>'.htmlspecialchars($trans['moneda']).'</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="10">No se encontraron transacciones</td></tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    if (!empty($transacciones)) {
        $html .= '
        <div class="totals">
            '.($cuenta ? '<div><span class="total-label">Saldo Final:</span> '.number_format($saldo_final, 2).'</div>' : '').'
            <div><span class="total-label">Total Débitos:</span> '.number_format($total_debitos, 2).'</div>
            <div><span class="total-label">Total Créditos:</span> '.number_format($total_creditos, 2).'</div>
        </div>';
    }
    
    // Escribir HTML en el PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generar y descargar PDF
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
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
            </div>
            <form method="get" class="filter-grid">
                <div class="filter-group">
                    <label for="mes" class="filter-label"><i class="far fa-calendar-alt"></i> Mes:</label>
                    <select id="mes" name="mes" class="filter-input" required>
                        <?php foreach($meses_espanol as $num => $nombre): ?>
                            <option value="<?= $num ?>" <?= $num==$mes?'selected':'' ?>>
                                <?= $nombre ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="anio" class="filter-label"><i class="far fa-calendar"></i> Año:</label>
                    <select id="anio" name="anio" class="filter-input" required>
                        <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                            <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="cuenta" class="filter-label"><i class="fas fa-wallet"></i> Cuenta:</label>
                    <input type="text" id="cuenta" name="cuenta" 
                           value="<?= htmlspecialchars($cuenta) ?>" 
                           placeholder="Número de cuenta"
                           class="filter-input">
                </div>
                
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Consultar
                </button>
            </form>
            
            <div class="export-buttons">
                <a href="?mes=<?= $mes ?>&anio=<?= $anio ?>&cuenta=<?= urlencode($cuenta) ?>&export=pdf" 
                   class="btn-export pdf" target="_blank">
                   <i class="fas fa-file-pdf"></i> Exportar a PDF
                </a>
            </div>
        </div>

        <div class="account-info">
            <h4><i class="fas fa-info-circle"></i> Información del Reporte</h4>
            <p>
                <i class="far fa-calendar"></i> <strong>Período:</strong> <?= $meses_espanol[$mes] ?> <?= $anio ?>
                <?php if (!empty($cuenta)): ?>
                    | <i class="fas fa-wallet"></i> <strong>Cuenta:</strong> <?= htmlspecialchars($cuenta) ?>
                <?php endif; ?>
            </p>
            
            <?php if (!empty($cuenta)): ?>
                <div class="saldo-box">
                    <span class="saldo-label"><i class="fas fa-coins"></i> Saldo Inicial:</span>
                    <span class="saldo-value"><?= number_format($saldo_inicial, 2) ?> <?= !empty($transacciones[0]['moneda']) ? $transacciones[0]['moneda'] : '' ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th><i class="far fa-calendar"></i> Fecha</th>
                        <th><i class="fas fa-user"></i> Cliente</th>
                        <th><i class="fas fa-wallet"></i> Cuenta</th>
                        <th><i class="fas fa-exchange-alt"></i> Tipo</th>
                        <th><i class="fas fa-money-bill-wave"></i> Monto</th>
                        <th><i class="fas fa-piggy-bank"></i> Saldo <?= !empty($cuenta) ? 'Acumulado' : '' ?></th>
                        <th><i class="fas fa-align-left"></i> Descripción</th>
                        <th><i class="fas fa-hashtag"></i> Referencia</th>
                        <th><i class="fas fa-user-cog"></i> Usuario</th>
                        <th><i class="fas fa-coins"></i> Moneda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transacciones)): ?>
                        <?php foreach ($transacciones as $trans): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                                <td><?= htmlspecialchars($trans['cliente']) ?></td>
                                <td><?= htmlspecialchars($trans['cuenta']) ?></td>
                                <td class="<?= $trans['tipo'] == 'D' ? 'debit' : 'credit' ?>">
                                    <i class="<?= $trans['tipo'] == 'D' ? 'fas fa-arrow-down' : 'fas fa-arrow-up' ?>"></i>
                                    <?= $trans['tipo'] == 'D' ? 'Débito' : 'Crédito' ?>
                                </td>
                                <td><?= number_format($trans['monto'], 2) ?></td>
                                <td>
                                    <?= !empty($cuenta) ? number_format($trans['saldo_acumulado'] ?? $trans['saldo'], 2) : number_format($trans['saldo'], 2) ?>
                                </td>
                                <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                                <td><?= htmlspecialchars($trans['referencia']) ?></td>
                                <td><?= htmlspecialchars($trans['usuario']) ?></td>
                                <td><?= htmlspecialchars($trans['moneda']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-results">
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
            <?php if (!empty($cuenta)): ?>
                <div class="total-box saldo-final-box">
                    <div class="total-label"><i class="fas fa-coins"></i> Saldo Final</div>
                    <div class="total-value"><?= number_format($saldo_final, 2) ?></div>
                </div>
            <?php endif; ?>
            
            <div class="total-box total-debits">
                <div class="total-label"><i class="fas fa-arrow-down"></i> Total Débitos</div>
                <div class="total-value"><?= number_format($total_debitos, 2) ?></div>
            </div>
            <div class="total-box total-credits">
                <div class="total-label"><i class="fas fa-arrow-up"></i> Total Créditos</div>
                <div class="total-value"><?= number_format($total_creditos, 2) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>