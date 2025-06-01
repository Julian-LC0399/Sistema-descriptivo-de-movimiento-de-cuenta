<?php
$host = 'localhost';
$dbname = 'banco';
$username = 'root';
$password = '1234';
$port = '3306'; // Puerto por defecto de MySQL

try {
    // Conexión con puerto especificado
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", 
                   $username, 
                   $password);
    
    // Configuración de atributos
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Verificación adicional de conexión
    $status = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<h3>✅ Conexión exitosa</h3>";
    echo "<p><strong>Servidor:</strong> $host:$port</p>";
    echo "<p><strong>Base de datos:</strong> $dbname</p>";
    echo "<p><strong>Estado:</strong> $status</p>";
    
    // Opcional: Mostrar versión del servidor MySQL
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<p><strong>Versión MySQL:</strong> $version</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ Error de conexión</h3>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Código error:</strong> " . $e->getCode() . "</p>";
    echo "<p>Verifica los siguientes detalles:</p>";
    echo "<ul>";
    echo "<li>Servidor MySQL en ejecución</li>";
    echo "<li>Credenciales correctas (usuario/contraseña)</li>";
    echo "<li>Nombre de la base de datos existe</li>";
    echo "<li>Puerto correcto (actual: $port)</li>";
    echo "<li>Conexión de red permitida</li>";
    echo "</ul>";
    echo "</div>";
    
    // Retornar null en caso de error si este código es un include
    return null;
}

// Retornar la conexión (si estás usando este código como include)
return $pdo;
?>