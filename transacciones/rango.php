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

// Validar cuenta si se especifica (MODIFICADO PARA ACEPTAR DESDE 9 DÍGITOS)
if (!empty($cuenta) && !preg_match('/^[0-9]{9,20}$/', $cuenta)) {
    die("Número de cuenta inválido. Debe contener solo dígitos (9-20 caracteres).");
}

// Consulta para obtener el saldo inicial
$saldo_inicial = 0;
if (!empty($cuenta)) {
    try {
        // Primero intentamos obtener el último saldo antes de la fecha de inicio
        $sql_saldo_inicial = "SELECT t.trdbal AS saldo_inicial
                            FROM actrd t
                            WHERE t.trdacc = :cuenta
                            AND t.trddat < :fecha_inicio
                            ORDER BY t.trddat DESC, t.trdseq DESC
                            LIMIT 1";
        
        $stmt_saldo = $pdo->prepare($sql_saldo_inicial);
        $stmt_saldo->execute([':cuenta' => $cuenta, ':fecha_inicio' => $fecha_inicio]);
        $resultado_saldo = $stmt_saldo->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado_saldo) {
            $saldo_inicial = $resultado_saldo['saldo_inicial'];
        } else {
            // Si no hay movimientos previos, obtener saldo de la tabla de cuentas
            $sql_saldo_cuenta = "SELECT acmbal FROM acmst WHERE acmacc = :cuenta";
            $stmt_saldo_cuenta = $pdo->prepare($sql_saldo_cuenta);
            $stmt_saldo_cuenta->execute([':cuenta' => $cuenta]);
            $resultado_saldo_cuenta = $stmt_saldo_cuenta->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado_saldo_cuenta) {
                $saldo_inicial = $resultado_saldo_cuenta['acmbal'];
            }
        }
    } catch(PDOException $e) {
        error_log("Error al obtener saldo inicial: " . $e->getMessage());
        $saldo_inicial = 0;
    }
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
    
    // Calcular saldo final
    $saldo_final = $saldo_inicial + $total_creditos - $total_debitos;
} catch(PDOException $e) {
    error_log("Error en consulta de estado de cuenta: " . $e->getMessage());
    die("Ocurrió un error al procesar su solicitud. Por favor intente más tarde.");
}

// Verificar si se solicitó PDF
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once __DIR__ . '/../includes/library/tcpdf.php';
    
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Banco Caroni');
    $pdf->SetAuthor('Sistema Bancario');
    $pdf->SetTitle('Estado de Cuenta '.$fecha_inicio.' al '.$fecha_fin);
    $pdf->SetSubject('Estado de Cuenta por Rango');
    
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
        h1 { text-align: center; font-size: 16px; }
        h2 { text-align: center; font-size: 14px; margin-bottom: 10px; }
        .info { font-size: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        td, th { border: 1px solid #ddd; padding: 4px; }
        .debit { color: #FF0000; }
        .credit { color: #00AA00; }
        .totals { margin-top: 10px; font-size: 10px; }
        .total-label { font-weight: bold; }
    </style>
    
    <h1>BANCO CARONI</h1>
    <h2>ESTADO DE CUENTA POR RANGO</h2>
    
    <div class="info">
        <strong>Período:</strong> '.date('d/m/Y', strtotime($fecha_inicio)).' al '.date('d/m/Y', strtotime($fecha_fin)).' | 
        '.(!empty($cuenta) ? '<strong>Cuenta:</strong> '.htmlspecialchars($cuenta).' | ' : '').'
        <strong>Generado:</strong> '.date('d/m/Y H:i:s').'
    </div>';
    
    if (!empty($cuenta)) {
        $html .= '<div class="info"><strong>Saldo Inicial:</strong> '.number_format($saldo_inicial, 2).' '.(!empty($transacciones[0]['moneda']) ? $transacciones[0]['moneda'] : '').'</div>';
    }
    
    $html .= '
    <table>
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
        <tbody>';
    
    if (!empty($transacciones)) {
        foreach ($transacciones as $trans) {
            $html .= '
            <tr>
                <td>'.date('d/m/Y', strtotime($trans['fecha'])).'</td>
                <td>'.htmlspecialchars($trans['secuencia']).'</td>
                <td class="'.($trans['tipo'] == 'D' ? 'debit' : 'credit').'">'.($trans['tipo'] == 'D' ? 'Débito' : 'Crédito').'</td>
                <td>'.number_format($trans['monto'], 2).'</td>
                <td>'.number_format($trans['saldo'], 2).'</td>
                <td>'.htmlspecialchars($trans['descripcion']).'</td>
                <td>'.htmlspecialchars($trans['referencia']).'</td>
                <td>'.htmlspecialchars($trans['usuario']).'</td>
                <td>'.htmlspecialchars($trans['moneda']).'</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="9">No se encontraron transacciones en el período seleccionado</td></tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    if (!empty($transacciones)) {
        $html .= '
        <div class="totals">
            <div><span class="total-label">Total Débitos:</span> '.number_format($total_debitos, 2).'</div>
            <div><span class="total-label">Total Créditos:</span> '.number_format($total_creditos, 2).'</div>
            '.(!empty($cuenta) ? '<div><span class="total-label">Saldo Final:</span> '.number_format($saldo_final, 2).'</div>' : '').'
        </div>';
    }
    
    // Escribir HTML en el PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generar y descargar PDF
    $pdf->Output('estado_cuenta_'.$fecha_inicio.'_'.$fecha_fin.'.pdf', 'D');
    exit();
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
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/rango.css">
    <link rel="stylesheet" href="../assets/css/pdf-export.css">
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

            <div class="export-buttons">
                <a href="?fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>&cuenta=<?= urlencode($cuenta) ?>&export=pdf" 
                   class="btn-export pdf" target="_blank">
                   <i class="fas fa-file-pdf"></i> Exportar a PDF
                </a>
            </div>
        </div>

        <?php if (!empty($cuenta)): ?>
        <div class="account-info">
            <h4><i class="fas fa-info-circle"></i> Información de la Cuenta</h4>
            <p><strong><i class="fas fa-credit-card"></i> Número de Cuenta:</strong> <?= htmlspecialchars($cuenta) ?></p>
            <p><strong><i class="far fa-calendar"></i> Período:</strong> <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
            
            <div class="saldo-box">
                <span class="saldo-label"><i class="fas fa-coins"></i> Saldo Inicial:</span>
                <span class="saldo-value"><?= number_format($saldo_inicial, 2) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th><i class="far fa-calendar"></i> Fecha</th>
                        <th><i class="fas fa-hashtag"></i> Secuencia</th>
                        <th><i class="fas fa-exchange-alt"></i> Tipo</th>
                        <th><i class="fas fa-money-bill-wave"></i> Monto</th>
                        <th><i class="fas fa-piggy-bank"></i> Saldo</th>
                        <th><i class="fas fa-align-left"></i> Descripción</th>
                        <th><i class="fas fa-barcode"></i> Referencia</th>
                        <th><i class="fas fa-user"></i> Usuario</th>
                        <th><i class="fas fa-dollar-sign"></i> Moneda</th>
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
                <div class="total-label"><i class="fas fa-arrow-down"></i> Total Débitos</div>
                <div class="total-value"><?= number_format($total_debitos, 2) ?></div>
            </div>
            <div class="total-box total-credits">
                <div class="total-label"><i class="fas fa-arrow-up"></i> Total Créditos</div>
                <div class="total-value"><?= number_format($total_creditos, 2) ?></div>
            </div>
            <div class="total-box" style="border-top: 3px solid #3182ce;">
                <div class="total-label"><i class="fas fa-coins"></i> Saldo Final</div>
                <div class="total-value"><?= number_format($saldo_final, 2) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validación de fechas
            document.querySelector('form').addEventListener('submit', function(e) {
                const inicio = document.getElementById('fecha_inicio').value;
                const fin = document.getElementById('fecha_fin').value;
                
                if (new Date(inicio) > new Date(fin)) {
                    alert('La fecha de inicio no puede ser mayor a la fecha final');
                    e.preventDefault();
                }
            });

            // Mejorar experiencia de usuario para campos de fecha
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_inicio').max = today;
            document.getElementById('fecha_fin').max = today;
            
            // Sincronizar fechas para mejor UX
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