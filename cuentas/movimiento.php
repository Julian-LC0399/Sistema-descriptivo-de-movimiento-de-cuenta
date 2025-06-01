<?php
require '../includes/conexion.php';
require '../includes/auth.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdo->beginTransaction();

    try {
        // 1. Actualizar saldo en acmst
        $sql = "UPDATE acmst SET acmbal = acmbal + :monto WHERE acmacc = :cuenta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'monto' => $_POST['monto'],
            'cuenta' => $_POST['cuenta']
        ]);

        // 2. Registrar en actrd
        $sql = "INSERT INTO actrd (trdacc, trddat, trdseq, trdamt, trdbal, trdmd, trddsc) 
                VALUES (:cuenta, NOW(), 1, :monto, (SELECT acmbal FROM acmst WHERE acmacc = :cuenta), 'C', 'Depósito inicial')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'cuenta' => $_POST['cuenta'],
            'monto' => $_POST['monto']
        ]);

        $pdo->commit();
        $mensaje = "Transacción exitosa!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!-- Formulario para transacciones -->