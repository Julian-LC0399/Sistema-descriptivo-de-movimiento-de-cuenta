<?php
// register.php
require 'includes/conexion.php'; // Tu archivo de conexión PDO

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $nombre = trim($_POST['nombre']);
    $contrasena = $_POST['contrasena'];
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, contrasena, nombre) VALUES (?, ?, ?)");
        $stmt->execute([$usuario, $contrasena_hash, $nombre]);
        
        echo "Usuario registrado exitosamente!";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Error de duplicado
            echo "El nombre de usuario ya existe";
        } else {
            echo "Error al registrar: " . $e->getMessage();
        }
    }
}
?>

<form method="post">
    <input type="text" name="usuario" placeholder="Usuario" required>
    <input type="text" name="nombre" placeholder="Nombre completo" required>
    <input type="password" name="contrasena" placeholder="Contraseña" required>
    <button type="submit">Registrar</button>
</form>