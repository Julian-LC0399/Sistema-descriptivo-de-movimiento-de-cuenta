<?php
require_once __DIR__ . '/includes/functions.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $session_duration = isset($_SESSION['login_time']) 
        ? time() - $_SESSION['login_time'] 
        : 'N/A';
    
    registrarAcceso(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'logout',
        [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_duration_seconds' => $session_duration,
            'logout_time' => date('Y-m-d H:i:s')
        ]
    );
}

// Destruir completamente la sesión
$_SESSION = array();

// Eliminar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: login.php");
exit();
?>