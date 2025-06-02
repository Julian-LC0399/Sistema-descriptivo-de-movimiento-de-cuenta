<?php
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos
check_auth();
if (!has_permission('cliente')) {
    header('Location: ../index.php');
    exit;
}

// Obtener el ID del cliente de la sesión
$cliente_id = $_SESSION['user_id'];

// Consulta SQL para obtener saldos de cuentas
$query = "SELECT 
            a.acmacc AS numero_cuenta,
            a.acmccy AS moneda,
            a.acmbal AS saldo_actual,
            a.acmavl AS saldo_disponible,
            a.acmopn AS fecha_apertura,
            a.acmprd AS producto,
            c.cusna1 AS nombre_cliente
          FROM acmst a
          JOIN cumst c ON a.acmcun = c.cuscun
          WHERE a.acmcun = :cliente_id
          AND a.acmsta = 'A'
          ORDER BY a.acmccy, a.acmprd";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
$stmt->execute();
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incluir cabecera
include '../includes/header.php';
?>

<div class="container">
    <h2>Consulta de Saldos</h2>
    
    <?php if (count($cuentas) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Número de Cuenta</th>
                        <th>Moneda</th>
                        <th>Saldo Actual</th>
                        <th>Saldo Disponible</th>
                        <th>Producto</th>
                        <th>Fecha Apertura</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cuentas as $cuenta): ?>
                        <tr>
                            <td><?= formatear_numero_cuenta($cuenta['numero_cuenta']) ?></td>
                            <td><?= $cuenta['moneda'] ?></td>
                            <td class="text-end"><?= formatear_moneda($cuenta['saldo_actual'], $cuenta['moneda']) ?></td>
                            <td class="text-end"><?= formatear_moneda($cuenta['saldo_disponible'], $cuenta['moneda']) ?></td>
                            <td><?= obtener_nombre_producto($cuenta['producto']) ?></td>
                            <td><?= formatear_fecha($cuenta['fecha_apertura']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No se encontraron cuentas activas.</div>
    <?php endif; ?>
</div>

<?php
// Incluir pie de página
include '../includes/footer.php';
?>