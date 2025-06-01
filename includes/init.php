<?php
// Iniciar sesión (si no está activa)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 día
        'cookie_secure'   => true,  // Solo HTTPS
        'cookie_httponly' => true   // Protección XSS
    ]);
}

// Configuración de zona horaria
date_default_timezone_set('America/Caracas');

// Mostrar errores solo en desarrollo (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar dependencias
require_once __DIR__ . '/database.php';       // Conexión a DB
require_once __DIR__ . '/auth.php';     // Autenticación
require_once __DIR__ . '/functions.php';// Funciones helpers

// Verificar instalación mínima (opcional)
if (!function_exists('getClienteById')) {
    die("Error: Faltan archivos esenciales. Contacte al administrador.");
}
?>