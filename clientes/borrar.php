<?php
// clientes/borrar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

// Verificar que se recibió el ID del cliente
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No se especificó el cliente a desactivar";
    header('Location: lista.php');
    exit;
}

$idCliente = $_GET['id'];

// Obtener conexión PDO
$pdo = getPDO();

try {
    // Actualizar el estado del cliente a 'I' (Inactivo) en lugar de borrarlo
    $sql = "UPDATE cumst SET cussts = 'I' WHERE cuscun = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $idCliente, PDO::PARAM_STR);
    $stmt->execute();

    // Verificar si se actualizó algún registro
    if ($stmt->rowCount() > 0) {
        $_SESSION['mensaje'] = [
            'texto' => 'Cliente desactivado correctamente',
            'tipo' => 'exito'
        ];
    } else {
        $_SESSION['error'] = "No se encontró el cliente o ya estaba desactivado";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al desactivar el cliente: " . $e->getMessage();
}

// Redirigir de vuelta a la lista de clientes
header('Location: lista.php');
exit;
?>