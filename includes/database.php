<?php
// includes/database.php

/**
 * Configuración de sesión segura
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'banco';
$username = 'root';
$password = '1234';
$port = '3306';

// Conexión a la base de datos
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log("Error de conexión a BD: " . $e->getMessage());
    die("Error en el sistema. Por favor intente más tarde.");
}

/**
 * Verifica si el usuario está autenticado
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Autentica un usuario (versión corregida)
 */
function authenticate(string $username, string $password): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password, role, cuscun
            FROM users 
            WHERE username = :username 
            AND activo = 1
            LIMIT 1
        ");
        
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            
            $_SESSION = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'cuscun' => $user['cuscun'],
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];
            return true;
        }
        
        error_log("Intento fallido de login para usuario: $username");
        return false;
    } catch (PDOException $e) {
        error_log("Error de autenticación: " . $e->getMessage());
        return false;
    }
}

/**
 * Cierra la sesión del usuario
 */
function logout(): void {
    $_SESSION = [];

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

    session_destroy();
    header("Location: login.php");
    exit;
}

/**
 * Requiere que el usuario esté autenticado
 */
function requireLogin(?string $rolRequerido = null): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR'] || 
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logout();
    }
    
    if ($rolRequerido && $_SESSION['role'] !== $rolRequerido) {
        header('HTTP/1.0 403 Forbidden');
        die('Acceso no autorizado para tu rol');
    }
}

/**
 * Redirige al usuario después del login
 */
function redirectAfterLogin(string $urlDefault = 'index.php'): void {
    $url = $_SESSION['redirect_url'] ?? $urlDefault;
    unset($_SESSION['redirect_url']);
    
    $allowedPaths = ['index.php', 'dashboard.php', 'perfil.php'];
    $path = parse_url($url, PHP_URL_PATH);
    
    if (in_array($path, $allowedPaths)) {
        header("Location: $url");
    } else {
        header("Location: $urlDefault");
    }
    exit;
}

/**
 * Escapa y sanitiza datos para HTML
 */
function e(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Obtiene la conexión PDO
 */
function getPDO(): PDO {
    global $pdo;
    return $pdo;
}

/**
 * Hashea contraseñas
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifica necesidad de rehashear
 */
function needsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Obtiene información del usuario actual
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, role, cuscun, creado_en 
            FROM users 
            WHERE id = :user_id
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error al obtener usuario: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifica si el usuario tiene un permiso específico
 */
function hasPermission(string $permCode): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = :user_id AND p.perm_code = :perm_code
        ");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':perm_code' => $permCode
        ]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error al verificar permiso: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra un evento en el log del sistema
 */
function logEvent(string $action, ?array $details = null): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs 
            (user_id, action, details, ip_address, user_agent) 
            VALUES 
            (:user_id, :action, :details, :ip, :user_agent)
        ");
        
        return $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action,
            ':details' => $details ? json_encode($details) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'],
            ':user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar evento: " . $e->getMessage());
        return false;
    }
}