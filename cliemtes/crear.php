<?php
require '../includes/conexion.php';
require '../includes/auth.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datos = [
        'cuscun' => $_POST['cuscun'],
        'cusna1' => $_POST['cusna1'],
        'cuseml' => $_POST['cuseml'],
        'cussts' => 'A'
    ];

    $sql = "INSERT INTO cumst (cuscun, cusna1, cuseml, cussts) VALUES (:cuscun, :cusna1, :cuseml, :cussts)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($datos);

    header("Location: listar.php");
    exit();
}
?>

<!-- Formulario HTML (similar al listar.php) -->