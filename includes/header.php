<!-- Sidebar -->
<?php if (isset($_SESSION['user_id'])): ?>
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
                    Consulta por rango
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') == 'consulta-mes' ? 'active' : '' ?>" 
                   href="cuentas/transacciones/mes.php">
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
                <a class="nav-link text-danger" href="logout.php">
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
<?php endif; ?>