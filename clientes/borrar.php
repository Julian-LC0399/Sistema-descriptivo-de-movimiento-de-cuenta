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
    // En lugar de borrar físicamente, marcamos como inactivo
    $sql = "UPDATE cumst SET cussts = 'I' WHERE cuscun = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $idCliente, PDO::PARAM_INT);
    $stmt->execute();
    
    $_SESSION['mensaje'] = "Cliente desactivado correctamente";
    header("Location: lista.php");
    exit();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al desactivar el cliente: " . $e->getMessage();
    header("Location: lista.php");
    exit();
}