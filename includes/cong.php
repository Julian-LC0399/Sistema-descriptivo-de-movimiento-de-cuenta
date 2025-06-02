<?php
// Configuración básica
define('BASE_PATH', dirname(__DIR__));  // Ruta absoluta al proyecto
define('BASE_URL', 'https://tudominio.com');  // URL base

// Configuración de entorno
define('ENVIRONMENT', 'development');  // 'production' en servidor real

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'nombre_bd');
define('DB_USER', 'usuario');
define('DB_PASS', 'contraseña_segura');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // Solo si usas HTTPS
session_start();

// Otras configuraciones
date_default_timezone_set('America/Mexico_City');
error_reporting(ENVIRONMENT === 'development' ? E_ALL : 0);
ini_set('display_errors', ENVIRONMENT === 'development' ? 1 : 0);