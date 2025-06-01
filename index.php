<?php
session_start();

// Verifica si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Incluye la conexión a la base de datos y funciones
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Consulta para obtener movimientos de cuenta (ejemplo)
$usuario_id = $_SESSION['usuario_id'];
$query = "SELECT * FROM movimientos WHERE usuario_id = :usuario_id ORDER BY fecha DESC";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Movimientos de Cuenta</title>
    <link rel="stylesheet" href="assets/css/styles.css"> <!-- Si tienes CSS -->
</head>
<body>
    <?php include_once __DIR__ . '/includes/header.php'; ?> <!-- Cabecera común -->

    <div class="container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h1>
        
        <!-- Tabla de movimientos -->
        <h2>Movimientos Recientes</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $movimiento): ?>
                <tr>
                    <td><?php echo htmlspecialchars($movimiento['fecha']); ?></td>
                    <td><?php echo htmlspecialchars($movimiento['descripcion']); ?></td>
                    <td><?php echo htmlspecialchars($movimiento['monto']); ?></td>
                    <td><?php echo htmlspecialchars($movimiento['tipo']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Botones de acción -->
        <div class="actions">
            <a href="nuevo_movimiento.php" class="btn">Agregar Movimiento</a>
            <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
        </div>
    </div>

    <?php include_once __DIR__ . '/includes/footer.php'; ?> <!-- Pie de página común -->
</body>
</html>