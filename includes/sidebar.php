<?php if (isset($_SESSION['user_id'])): ?>
<!-- Incluir el CSS del sidebar -->
<link rel="stylesheet" href="assets/css/sidebar.css">
<!-- Incluir Font Awesome para los iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Botón para mostrar/ocultar sidebar en móviles -->
<button class="sidebar-toggle d-lg-none btn btn-primary position-fixed" 
        style="z-index: 1050; top: 10px; left: 10px;">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-sticky">
        <div class="text-center py-4">
            <h4 class="text-white mb-0">Banco Caroni</h4>
        </div>
        
        <ul class="nav flex-column">
            <!-- Sección CONSULTAS -->
            <li class="sidebar-heading">CONSULTAS</li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'consulta-rango' ? 'active' : '' ?>" href="consultas/por-rango.php">
                    <i class="fas fa-calendar-alt"></i>
                    Consulta por saldo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'consulta-mes' ? 'active' : '' ?>" 
                   href="transacciones/mes.php">
                    <i class="fas fa-calendar-week"></i>
                    Consulta por mes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'simulador' ? 'active' : '' ?>" href="simulador/">
                    <i class="fas fa-calculator"></i>
                    Simulador
                </a>
            </li>
            
            <!-- Separador -->
            <li class="sidebar-heading mt-4"></li>
            
            <!-- Opción de Cerrar Sesión -->
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?= 
                    (strpos($_SERVER['PHP_SELF'], 'transacciones') !== false) ? 
                    '../logout.php' : 
                    'logout.php' 
                ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar sesión
                </a>
            </li>
            
            <!-- Eslogan -->
            <li class="nav-item mt-4 px-3 text-center">
                <small class="text-white-50">Un Banco tan sólido como sus raíces</small>
            </li>
        </ul>
    </div>
</div>

<!-- JavaScript para el toggle del sidebar -->
<script>
document.querySelector('.sidebar-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
});
</script>
<?php endif; ?>