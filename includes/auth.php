<?php
session_start();

// Función para verificar sesión
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../login.php");
        exit();
    }
}

// Conexión a la base de datos (debes configurar esto)
$conexion = new mysqli('localhost', 'root', '1234', 'banco');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    // Consulta preparada para evitar inyección SQL
    $stmt = $conexion->prepare("SELECT id, contrasena, nombre FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();
        
        // Verificar contraseña hasheada (asumiendo que usaste SHA2 en la BD)
        if (hash('sha256', $contrasena) === $fila['contrasena']) {
            $_SESSION['usuario_id'] = $fila['id'];
            $_SESSION['usuario_nombre'] = $fila['nombre'];
            header("Location: index.php");
            exit();
        }
    }
    
    // Si llega aquí, las credenciales son incorrectas
    $error = "Usuario o contraseña incorrectos";
}

// Cerrar conexión
$conexion->close();
?>