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
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Sidebar -->
<div class="sidebar-universal" id="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-logo-container">
            <img src="<?= $logoUrl ?>" alt="Banco Caroni" class="sidebar-logo">
        </div>
        
        <ul class="sidebar-nav">
            <li class="menu-section">CONSULTAS</li>
            
            <li class="nav-item has-submenu">
                <a class="nav-link submenu-parent" href="#">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                    <i class="fas fa-chevron-right submenu-toggle"></i>
                </a>
                <ul class="submenu" style="display: none;">
                    <li class="submenu-item">
                        <a href="<?= BASE_URL ?>clientes/lista.php">
                            <i class="fas fa-search"></i>
                            <span>Consulta</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="<?= BASE_URL ?>clientes/crear.php">
                            <i class="fas fa-plus-circle"></i>
                            <span>Registro</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="nav-item has-submenu">
                <a class="nav-link submenu-parent" href="#">
                    <i class="fas fa-wallet"></i>
                    <span>Cuentas</span>
                    <i class="fas fa-chevron-right submenu-toggle"></i>
                </a>
                <ul class="submenu" style="display: none;">
                    <li class="submenu-item">
                        <a href="<?= BASE_URL ?>cuentas/listar.php">
                            <i class="fas fa-search"></i>
                            <span>Consulta</span>
                        </a>
                    </li>
                    <?php if (in_array($_SESSION['role'], ['admin', 'gerente'])): ?>
                    <li class="submenu-item">
                        <a href="<?= BASE_URL ?>cuentas/crear.php">
                            <i class="fas fa-plus-circle"></i>
                            <span>Registro</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
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

<!-- Script para manejar el submenú -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const submenuParents = document.querySelectorAll('.submenu-parent');
    
    submenuParents.forEach(parent => {
        parent.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const isOpen = submenu.style.display === 'block';
            
            // Cerrar todos los submenús primero
            document.querySelectorAll('.submenu').forEach(sm => {
                if (sm !== submenu) {
                    sm.style.display = 'none';
                    sm.previousElementSibling.querySelector('.submenu-toggle').classList.remove('fa-chevron-down');
                    sm.previousElementSibling.querySelector('.submenu-toggle').classList.add('fa-chevron-right');
                }
            });
            
            // Alternar el submenú actual
            submenu.style.display = isOpen ? 'none' : 'block';
            const toggleIcon = this.querySelector('.submenu-toggle');
            toggleIcon.classList.toggle('fa-chevron-right', isOpen);
            toggleIcon.classList.toggle('fa-chevron-down', !isOpen);
        });
    });
    
    // Cerrar submenús al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.has-submenu')) {
            document.querySelectorAll('.submenu').forEach(submenu => {
                submenu.style.display = 'none';
                const toggleIcon = submenu.previousElementSibling.querySelector('.submenu-toggle');
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-right');
            });
        }
    });
});
</script>
<?php endif; ?>