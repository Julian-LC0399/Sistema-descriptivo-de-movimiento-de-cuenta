<?php
// logout.php
require_once __DIR__ . '/includes/database.php';

// Destruir completamente la sesión
$_SESSION = array();

// Si se desea destruir la cookie de sesión
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

// Finalmente, destruir la sesión
session_destroy();

// Redirigir inmediatamente a login.php
header("Location: login.php");
exit(); // Asegura que no se ejecute código adicional