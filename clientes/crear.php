<?php
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'cusna1' => sanitizeInput($_POST['cusna1']),
        'cusna2' => sanitizeInput($_POST['cusna2']),
        'cusna3' => sanitizeInput($_POST['cusna3']),
        'cusna4' => sanitizeInput($_POST['cusna4']),
        'cuscty' => sanitizeInput($_POST['cuscty']),
        'cuseml' => sanitizeInput($_POST['cuseml']),
        'cusphn' => sanitizeInput($_POST['cusphn']),
        'cussts' => $_POST['cussts']
    ];
    
    try {
        $sql = "INSERT INTO cumst (cusna1, cusna2, cusna3, cusna4, cuscty, cuseml, cusphn, cussts, cuslau, cuslut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data) + [$_SESSION['username']]);
        
        header("Location: listar.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error al crear cliente: " . $e->getMessage();
    }
}
?>

<h1>Nuevo Cliente</h1>
<a href="listar.php">Volver al listado</a>

<?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>

<form method="POST">
    <label>Nombre Completo: <input type="text" name="cusna1" required></label><br>
    <label>Dirección Línea 1: <input type="text" name="cusna2"></label><br>
    <label>Dirección Línea 2: <input type="text" name="cusna3"></label><br>
    <label>Dirección Línea 3: <input type="text" name="cusna4"></label><br>
    <label>Ciudad: <input type="text" name="cuscty"></label><br>
    <label>Email: <input type="email" name="cuseml"></label><br>
    <label>Teléfono: <input type="text" name="cusphn"></label><br>
    <label>Estado: 
        <select name="cussts">
            <option value="A">Activo</option>
            <option value="I">Inactivo</option>
        </select>
    </label><br>
    <button type="submit">Guardar</button>
</form>

<?php require_once '../includes/footer.php'; ?>