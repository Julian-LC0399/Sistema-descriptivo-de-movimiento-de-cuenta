<?php
/**
 * Encabezado común para todas las páginas del sistema bancario
 * Versión optimizada para mejor visualización
 */

// Verificación segura del estado de la sesión
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start([
        'cookie_lifetime' => 86400,
        'use_strict_mode' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true
    ]);
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Bancario - <?php echo htmlspecialchars($pageTitle ?? 'Inicio'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos optimizados -->
    <style>
        :root {
            --sidebar-width: 250px;
            --navbar-height: 56px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
        }
        
        body {
            padding-top: var(--navbar-height);
            background-color: #f5f7fa;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        /* Navbar mejorada */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: var(--primary-color) !important;
        }
        
        /* Sidebar rediseñado */
        .sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: var(--navbar-height);
            bottom: 0;
            left: 0;
            background: var(--secondary-color);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-sticky {
            padding: 20px 0;
            height: calc(100vh - var(--navbar-height));
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            margin: 0 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }
        
        .sidebar-heading {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            padding: 10px 15px;
            margin-top: 15px;
        }
        
        /* Contenido principal */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Tarjetas y tablas */
        .card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border-radius: 8px;
        }
        
        .table-responsive {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .navbar-brand {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Barra de navegación superior -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-university me-2"></i>
                <span class="d-none d-sm-inline">Sistema Bancario</span>
            </a>
            
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown">
                        <img src="https://via.placeholder.com/30" alt="Perfil" class="rounded-circle me-2">
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <a class="btn btn-outline-light ms-2" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>
                    <span class="d-none d-md-inline">Iniciar sesión</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <!-- Menú Principal -->
                <li class="sidebar-heading">NAVEGACIÓN</li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'dashboard' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                
                <!-- Clientes -->
                <li class="sidebar-heading">CLIENTES</li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'clientes' ? 'active' : '' ?>" href="clientes/listar.php">
                        <i class="fas fa-users"></i>
                        Lista de Clientes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'nuevo-cliente' ? 'active' : '' ?>" href="clientes/crear.php">
                        <i class="fas fa-user-plus"></i>
                        Nuevo Cliente
                    </a>
                </li>
                
                <!-- Cuentas -->
                <li class="sidebar-heading">CUENTAS</li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'cuentas' ? 'active' : '' ?>" href="cuentas/listar.php">
                        <i class="fas fa-piggy-bank"></i>
                        Lista de Cuentas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'nueva-cuenta' ? 'active' : '' ?>" href="cuentas/crear.php">
                        <i class="fas fa-plus-circle"></i>
                        Nueva Cuenta
                    </a>
                </li>
                
                <!-- Transacciones -->
                <li class="sidebar-heading">TRANSACCIONES</li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'transacciones' ? 'active' : '' ?>" href="transacciones/listar.php">
                        <i class="fas fa-exchange-alt"></i>
                        Movimientos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'nueva-transaccion' ? 'active' : '' ?>" href="transacciones/crear.php">
                        <i class="fas fa-hand-holding-usd"></i>
                        Nueva Transacción
                    </a>
                </li>
                
                <!-- Reportes -->
                <li class="sidebar-heading">REPORTES</li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') == 'reportes' ? 'active' : '' ?>" href="reportes/">
                        <i class="fas fa-chart-bar"></i>
                        Estadísticas
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenido principal -->
    <main class="main-content">
        <?php if (isset($pageTitle)): ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2 mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php if (isset($pageActions)): ?>
            <div class="btn-toolbar mb-2 mb-md-0">
                <?php echo $pageActions; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>
        
        <!-- El contenido específico de cada página se insertará aquí -->
    </main>

    <!-- Bootstrap JS Bundle con Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para el sidebar responsive -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar en móviles
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarCollapse && sidebar) {
                sidebarCollapse.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Cerrar alerts automáticamente
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    bootstrap.Alert.getInstance(alert)?.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>