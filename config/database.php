<?php
$host = 'localhost';
$dbname = 'banco';
$username = 'root';
$password = '1234';
$port = '3306'; // Puerto predeterminado de MySQL (cámbialo si es diferente)

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", 
        $username, 
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "¡Conexión exitosa!"; // Opcional: para depuración
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>