<?php
// clientes/borrar.php
require_once '../includes/config.php';
require_once '../includes/database.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: lista.php?mensaje=ID de cliente no proporcionado");
    exit();
}

$idCliente = $_GET['id'];

try {
    // En lugar de borrar físicamente, marcamos como inactivo (asumiendo que cussts es el campo de estado)
    $sql = "UPDATE cumst SET cussts = 'I' WHERE cuscun = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $idCliente, PDO::PARAM_INT);
    $stmt->execute();
    
    header("Location: lista.php?mensaje=Cliente desactivado correctamente");
    exit();
} catch (PDOException $e) {
    header("Location: lista.php?mensaje=Error al desactivar el cliente: " . urlencode($e->getMessage()));
    exit();
}