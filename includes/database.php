<?php
// includes/database.php

/**
 * Configuración de sesión segura
 * Recomendación: Iniciar sesión antes de cualquier output
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 día
        'cookie_secure'   => isset($_SERVER['HTTPS']), // Solo HTTPS en producción
        'cookie_httponly' => true, // Protección contra XSS
        'use_strict_mode' => true // Mejor seguridad de sesión
    ]);
}

// Configuración de la base de datos (Recomendación: Mover a configuración separada en producción)
$host = 'localhost';
$dbname = 'banco';
$username = 'root';
$password = '1234';
$port = '3306';

// Intentar conexión a la base de datos
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false // Recomendación: No usar conexiones persistentes
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Recomendación: Loggear el error en producción en lugar de mostrar detalles
    error_log("Error de conexión a BD: " . $e->getMessage());
    die("Error en el sistema. Por favor intente más tarde.");
}

/**
 * Verifica si el usuario está autenticado
 * Recomendación: Verificar también el agente de usuario e IP para seguridad
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
    // Mejora adicional (opcional):
    // && $_SESSION['ip'] === $_SERVER['REMOTE_ADDR']
    // && $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'];
}

/**
 * Autentica un usuario
 * Recomendación: Añadir límite de intentos fallidos
 */
function authenticate(string $username, string $password): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password, nombre, apellido, role 
            FROM users 
            WHERE username = :username 
            AND activo = 1
            LIMIT 1
        ");
        
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerar ID de sesión para prevenir fixation
            session_regenerate_id(true);
            
            $_SESSION = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'role' => $user['role'],
                'ip' => $_SERVER['REMOTE_ADDR'], // Para verificación posterior
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] // Para verificación posterior
            ];
            return true;
        }
        
        // Recomendación: Registrar intento fallido (para límite de intentos)
        error_log("Intento fallido de login para usuario: $username");
        return false;
    } catch (PDOException $e) {
        error_log("Error de autenticación: " . $e->getMessage());
        return false;
    }
}

/**
 * Cierra la sesión del usuario
 * Mejorado: Limpieza más completa
 */
function logout(): void {
    // Destruir todas las variables de sesión
    $_SESSION = [];

    // Borrar cookie de sesión
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

    // Destruir sesión
    session_destroy();

    // Recomendación: Redirigir a login después de cerrar sesión
    header("Location: login.php");
    exit;
}

/**
 * Requiere que el usuario esté autenticado
 * Recomendación: Añadir verificación de seguridad adicional
 */
function requireLogin(?string $rolRequerido = null): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    
    // Verificación de seguridad adicional (opcional)
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR'] || 
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logout();
    }
    
    if ($rolRequerido && $_SESSION['role'] !== $rolRequerido) {
        header('HTTP/1.0 403 Forbidden');
        // Recomendación: Mostrar página de error personalizada
        die('Acceso no autorizado para tu rol');
    }
}

/**
 * Redirige al usuario después del login
 * Recomendación: Validar URL antes de redirigir
 */
function redirectAfterLogin(string $urlDefault = 'index.php'): void {
    $url = $_SESSION['redirect_url'] ?? $urlDefault;
    
    // Validación básica de URL (mejorable)
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        header("Location: $urlDefault");
        exit;
    }
    
    // Redirigir a URL local válida
    $allowedPaths = ['index.php', 'dashboard.php', 'perfil.php']; // Ajustar según necesidades
    if (in_array(parse_url($url, PHP_URL_PATH), $allowedPaths)) {
        header("Location: $url");
    } else {
        header("Location: $urlDefault");
    }
    exit;
}

/**
 * Escapa y sanitiza datos para HTML
 * Recomendación: Mantener esta función para consistencia
 */
function e(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Obtiene la conexión PDO
 * Recomendación: Considerar inyección de dependencias en lugar de global
 */
function getPDO(): PDO {
    global $pdo;
    return $pdo;
}

/**
 * Recomendación adicional: Función para hashear contraseñas
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Recomendación: Función para verificar necesidad de rehashear
 */
function needsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}