<?php
// clientes/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: lista.php?error=ID+de+cliente+no+proporcionado");
    exit();
}

$idCliente = $_GET['id'];

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // 1. Primero eliminamos las cuentas asociadas al cliente
    $sqlCuentas = "DELETE FROM acmst WHERE acmcun = :id";
    $stmtCuentas = $pdo->prepare($sqlCuentas);
    $stmtCuentas->bindParam(':id', $idCliente, PDO::PARAM_INT);
    $stmtCuentas->execute();

    // 2. Eliminamos las transacciones relacionadas (opcional, según necesidades)
    // Esto podría omitirse si se quiere mantener historial financiero
    
    // 3. Finalmente eliminamos el cliente
    $sqlCliente = "DELETE FROM cumst WHERE cuscun = :id";
    $stmtCliente = $pdo->prepare($sqlCliente);
    $stmtCliente->bindParam(':id', $idCliente, PDO::PARAM_INT);
    $stmtCliente->execute();

    $pdo->commit();
    
    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => "Cliente y datos relacionados eliminados correctamente"
    ];
    header("Location: lista.php");
    exit();
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Error al eliminar el cliente: " . $e->getMessage();
    header("Location: lista.php");
    exit();
}