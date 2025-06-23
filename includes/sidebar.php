<?php if (isset($_SESSION['user_id'])): ?>
<?php
// Inicio seguro de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo incluir config.php si BASE_URL no está definida
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
}

// Verificación de que BASE_URL existe
if (!defined('BASE_URL')) {
    die('Error: BASE_URL no está definida. Verifica config.php');
}
?>
<!-- CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Sidebar -->
<div class="sidebar-universal" id="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <h4>Banco Caroni</h4>
        </div>
        
        <ul class="sidebar-nav">
            <li class="menu-section">CONSULTAS</li>
            
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