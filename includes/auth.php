<?php
/**
 * Archivo de autenticación y manejo de sesiones
 * 
 * Verifica el estado de la sesión y proporciona funciones de autenticación
 */

// Verificar y iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 día de duración de la sesión
        'cookie_secure'   => true,   // Solo enviar cookies sobre HTTPS
        'cookie_httponly' => true,   // Accesible solo por HTTP (no JavaScript)
        'use_strict_mode' => true    // Mejor seguridad para IDs de sesión
    ]);
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para requerir autenticación
function requireAuth() {
    if (!isLoggedIn()) {
        // Guardar la URL actual para redireccionar después del login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // Redirigir al login
        header('Location: login.php');
        exit();
    }
}

// Función para verificar permisos (ejemplo básico)
function hasPermission($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Aquí puedes implementar lógica más compleja de RBAC
    return ($_SESSION['user_role'] ?? 'guest') === $requiredRole;
}

// Función para hacer logout
function logout() {
    // Destruir todos los datos de sesión
    $_SESSION = array();
    
    // Borrar la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
    
    // Redirigir al login
    header('Location: login.php');
    exit();
}

// Función para regenerar el ID de sesión periódicamente (prevención de fixation)
function regenerateSession() {
    if (isLoggedIn()) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Regenerar el ID de sesión cada 30 minutos como medida de seguridad
if (isset($_SESSION['last_regeneration'])) {
    $interval = 60 * 30; // 30 minutos en segundos
    if (time() - $_SESSION['last_regeneration'] >= $interval) {
        regenerateSession();
    }
} else {
    $_SESSION['last_regeneration'] = time();
}

// Función para obtener el ID del usuario actual
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Función para obtener el nombre de usuario actual
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}