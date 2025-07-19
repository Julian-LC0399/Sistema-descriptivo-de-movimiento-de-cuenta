<?php if (isset($_SESSION['user_id'])): ?>
<?php
// Secure session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}

if (!defined('BASE_URL')) {
    die('Error: BASE_URL not defined. Check config.php');
}

$logoUrl = BASE_URL . 'assets/images/logo-banco.jpg';
?>
<!-- CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sidebar.css?v=<?= time() ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Sidebar -->
<div class="sidebar-universal" id="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-logo-container">
            <img src="<?= $logoUrl ?>" alt="Banco Caroni" class="sidebar-logo">
        </div>
        
        <ul class="sidebar-nav">
            <li class="menu-section">CONSULTAS</li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>clientes/lista.php">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>cuentas/listar.php">
                    <i class="fas fa-wallet"></i>
                    <span>Cuentas</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>transacciones/mes.php">
                    <i class="fas fa-calendar-week"></i>
                    <span>Consulta por mes</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>transacciones/rango.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Consulta por rango</span>
                </a>
            </li>
            
            <li class="nav-item logout-item">
                <a class="nav-link" href="<?= BASE_URL ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar sesión</span>
                </a>
            </li>
        </ul>
    </div>
</div>
<?php endif; ?>