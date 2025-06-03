<?php if (isset($_SESSION['user_id'])): ?>
<!-- CSS del sidebar universal -->
<link rel="stylesheet" href="assets/css/sidebar.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Botón para mostrar/ocultar sidebar en móviles (conservando funcionalidad) -->
<button class="sidebar-toggle mobile-only" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Container (estructura universal) -->
<div class="sidebar-universal" id="sidebar">
    <d class="sidebar-content">
        <div class="sidebar-header">
            <h4>Banco Caroni</h4>
        </div>
        
        <ul class="sidebar-nav">
            <!-- Sección CONSULTAS -->
            <li class="menu-section">CONSULTAS</li>
            
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'consulta-rango' ? 'active' : '' ?>" 
                   href="consultas/por-rango.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Consulta por rango</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'consulta-mes' ? 'active' : '' ?>" 
                   href="transacciones/mes.php">
                    <i class="fas fa-calendar-week"></i>
                    <span>Consulta por mes</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'simulador' ? 'active' : '' ?>" 
                   href="simulador/">
                    <i class="fas fa-calculator"></i>
                    <span>Simulador</span>
                </a>
            </li>
            
            <!-- Cerrar Sesión -->
            <li class="nav-item logout-item">
                <a class="nav-link" href="<?= 
                    (strpos($_SERVER['PHP_SELF'], 'transacciones') !== false) ? 
                    '../logout.php' : 
                    'logout.php' 
                ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar sesión</span>
                </a>
            </li>
            
            <!-- Eslogan -->
            <li class="sidebar-footer">
                <small>Un Banco tan sólido como sus raíces</small>
            </li>
        </ul>
</div>

<!-- JavaScript para el toggle del sidebar (conservando funcionalidad original) -->
<script>
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
});
</script>
<?php endif; ?>