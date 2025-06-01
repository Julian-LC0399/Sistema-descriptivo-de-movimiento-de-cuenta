<?php
/**
 * Encabezado común para todas las páginas del sistema bancario
 */
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Bancario - <?php echo $pageTitle ?? 'Inicio'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos personalizados -->
    <style>
        body {
            padding-top: 56px;
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 20px 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, .75);
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }
        .sidebar-heading {
            font-size: .75rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .5);
            padding: 0.5rem 1rem;
        }
        .main-content {
            padding: 20px;
            margin-left: 220px;
        }
        .table-responsive {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-university me-2"></i>Sistema Bancario
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../login.php"><i class="fas fa-sign-in-alt me-1"></i>Iniciar sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenedor principal -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="sidebar-heading mt-3">
                            <span>MENÚ PRINCIPAL</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>" href="../index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        
                        <li class="sidebar-heading mt-3">
                            <span>CLIENTES</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'clientes') ? 'active' : ''; ?>" href="../clientes/listar.php">
                                <i class="fas fa-users me-2"></i>Lista de Clientes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'nuevo-cliente') ? 'active' : ''; ?>" href="../clientes/crear.php">
                                <i class="fas fa-user-plus me-2"></i>Nuevo Cliente
                            </a>
                        </li>
                        
                        <li class="sidebar-heading mt-3">
                            <span>CUENTAS</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'cuentas') ? 'active' : ''; ?>" href="../cuentas/listar.php">
                                <i class="fas fa-piggy-bank me-2"></i>Lista de Cuentas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'nueva-cuenta') ? 'active' : ''; ?>" href="../cuentas/crear.php">
                                <i class="fas fa-plus-circle me-2"></i>Nueva Cuenta
                            </a>
                        </li>
                        
                        <li class="sidebar-heading mt-3">
                            <span>TRANSACCIONES</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'transacciones') ? 'active' : ''; ?>" href="../transacciones/listar.php">
                                <i class="fas fa-exchange-alt me-2"></i>Movimientos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'nueva-transaccion') ? 'active' : ''; ?>" href="../transacciones/crear.php">
                                <i class="fas fa-hand-holding-usd me-2"></i>Nueva Transacción
                            </a>
                        </li>
                        
                        <li class="sidebar-heading mt-3">
                            <span>REPORTES</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'reportes') ? 'active' : ''; ?>" href="#">
                                <i class="fas fa-chart-bar me-2"></i>Estadísticas
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <?php endif; ?>

            <!-- Contenido principal -->
            <main role="main" class="<?php echo isset($_SESSION['user_id']) ? 'col-md-10 ms-sm-auto' : 'col-12'; ?> main-content">
                <?php if (isset($pageTitle)): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                    <?php if (isset($pageActions)): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php echo $pageActions; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Mostrar mensajes de éxito/error -->
                <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                endif; ?>