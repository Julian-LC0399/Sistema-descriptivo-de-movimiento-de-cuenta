<?php
// cuentas/listar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Inicializar variables
$cuentas = []; // Inicializar como array vacío para evitar errores
$totalRegistros = 0;
$totalPaginas = 1;

// Solo administradores y gerentes pueden ver todas las cuentas
$allowedRoles = ['admin', 'gerente'];
$isAdminOrGerente = in_array($_SESSION['role'], $allowedRoles);

// Configuración de paginación
$porPagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $porPagina;

// Filtros
$filtroNombreCliente = isset($_GET['nombre_cliente']) ? trim($_GET['nombre_cliente']) : '';
$filtroCedula = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$filtroCuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : '';

// Determinar si hay filtros activos
$filtrosActivos = !empty($filtroNombreCliente) || !empty($filtroCedula) || !empty($filtroCuenta);

try {
    $pdo = getPDO();
    
    // Construir consulta base
    $sql = "SELECT 
                a.acmacc AS cuenta,
                c.cusidn AS cedula,
                CONCAT(c.cusna1, ' ', c.cusln1) AS nombre_cliente,
                a.acmbrn AS sucursal,
                a.acmccy AS moneda,
                a.acmtyp AS tipo_cuenta,
                a.acmprd AS producto,
                a.acmsta AS estado
            FROM acmst a
            JOIN cumst c ON a.acmcun = c.cuscun";
    
    $where = [];
    $params = [];
    
    // Si no es admin/gerente, solo mostrar cuentas del usuario actual y solo activas
    if (!$isAdminOrGerente) {
        $sql .= " JOIN users u ON c.cuscun = u.cliente_id";
        $where[] = "u.id = :user_id";
        $params[':user_id'] = $_SESSION['user_id'];
        $where[] = "a.acmsta = 'A'"; // Solo mostrar activas para usuarios normales
    }
    
    // Aplicar filtros de búsqueda
    if (!empty($filtroNombreCliente)) {
        $where[] = "CONCAT(c.cusna1, ' ', c.cusln1) LIKE :nombre_cliente";
        $params[':nombre_cliente'] = "%$filtroNombreCliente%";
    }
    
    // Filtro por cédula/RIF
    if (!empty($filtroCedula)) {
        $where[] = "c.cusidn LIKE :cedula";
        $params[':cedula'] = "%$filtroCedula%";
    }
    
    // Filtro por número de cuenta
    if (!empty($filtroCuenta)) {
        $where[] = "a.acmacc LIKE :cuenta";
        $params[':cuenta'] = "%$filtroCuenta%";
    }
    
    // Combinar condiciones WHERE
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    // Consulta para el total de registros
    $sqlCount = "SELECT COUNT(*) AS total FROM ($sql) AS total_query";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $porPagina);
    
    // Consulta principal con paginación
    $sql .= " ORDER BY a.acmopn DESC LIMIT :offset, :por_pagina";
    $params[':offset'] = $offset;
    $params[':por_pagina'] = $porPagina;
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':por_pagina', $porPagina, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        if ($key !== ':offset' && $key !== ':por_pagina') {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al listar cuentas: " . $e->getMessage());
    $_SESSION['error'] = "Ocurrió un error al recuperar las cuentas. Por favor intente más tarde.";
    $cuentas = []; // Asegurar que $cuentas es un array
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas bancarias - sistema bancario</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Cuentas bancarias</h2>
        
        <!-- Mensajes flotantes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="mensaje-flotante mensaje-exito">
                <div class="contenido-mensaje">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($_SESSION['mensaje']['texto'] ?? $_SESSION['mensaje']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mensaje-flotante mensaje-error">
                <div class="contenido-mensaje">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Filtros simplificados -->
        <div class="filtros-card mb-4">
            <div class="filtros-header">
                <h3 class="filtros-title">
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="nombre_cliente" class="form-label">Nombre del Cliente</label>
                    <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" 
                           value="<?php echo htmlspecialchars($filtroNombreCliente); ?>" 
                           placeholder="Nombre del cliente">
                </div>
                <div class="form-group">
                    <label for="cedula" class="form-label">Cédula</label>
                    <input type="text" class="form-control" id="cedula" name="cedula" 
                           value="<?php echo htmlspecialchars($filtroCedula); ?>" 
                           placeholder="Ej: V12345678 o J123456789">
                </div>
                <div class="form-group">
                    <label for="cuenta" class="form-label">Número de Cuenta</label>
                    <input type="text" class="form-control" id="cuenta" name="cuenta" 
                           value="<?php echo htmlspecialchars($filtroCuenta); ?>" 
                           placeholder="Ej: 123456789">
                </div>
                <div class="filtros-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="listar.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Mensaje inicial cuando no hay filtros -->
        <?php if (!$filtrosActivos): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-info-circle fs-4"></i>
                <p class="mt-2 mb-0">Utilice los filtros de búsqueda para mostrar cuentas</p>
            </div>
        <?php else: ?>
            <!-- Tabla de cuentas -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número de Cuenta</th>
                                <th>Cédula</th>
                                <th>Cliente</th>
                                <th>Sucursal</th>
                                <th>Moneda</th>
                                <th>Tipo de Cuenta</th>
                                <th>Producto</th>
                                <th>Estado</th>
                                <?php if ($isAdminOrGerente): ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cuentas)): ?>
                                <tr>
                                    <td colspan="<?php echo $isAdminOrGerente ? 9 : 8; ?>" class="text-center py-4">
                                        <i class="bi bi-exclamation-circle fs-4"></i>
                                        <p class="mt-2">No se encontraron cuentas con los filtros aplicados</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cuentas as $cuenta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cuenta['cuenta']); ?></td>
                                        <td><?php echo htmlspecialchars($cuenta['cedula']); ?></td>
                                        <td><?php echo htmlspecialchars($cuenta['nombre_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($cuenta['sucursal']); ?></td>
                                        <td><?php echo htmlspecialchars($cuenta['moneda']); ?></td>
                                        <td><?php echo htmlspecialchars($cuenta['tipo_cuenta']); ?></td>
                                        <td><?php echo htmlspecialchars($cuenta['producto']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $cuenta['estado'] === 'A' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $cuenta['estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <?php if ($isAdminOrGerente): ?>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="editar.php?id=<?php echo urlencode($cuenta['cuenta']); ?>" 
                                                       class="btn btn-sm btn-warning btn-action"
                                                       title="Editar cuenta">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <?php if ($cuenta['estado'] === 'A'): ?>
                                                        <button class="btn btn-sm btn-danger btn-action btn-borrar" 
                                                                data-cuenta="<?php echo htmlspecialchars($cuenta['cuenta']); ?>" 
                                                                title="Eliminar cuenta">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Resumen y paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="alert alert-info mb-0 py-2">
                        Mostrando <?php echo count($cuentas); ?> de <?php echo $totalRegistros; ?> cuentas
                        <?php if (!empty($filtroNombreCliente)): ?>| Cliente: <?php echo htmlspecialchars($filtroNombreCliente); ?><?php endif; ?>
                        <?php if (!empty($filtroCedula)): ?>| Cédula: <?php echo htmlspecialchars($filtroCedula); ?><?php endif; ?>
                        <?php if (!empty($filtroCuenta)): ?>| Cuenta: <?php echo htmlspecialchars($filtroCuenta); ?><?php endif; ?>
                    </div>
                    
                    <?php if ($totalPaginas > 1): ?>
                        <nav aria-label="Paginación">
                            <ul class="pagination mb-0">
                                <?php if ($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagina < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap JS Bundle con Popper -->
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para confirmar borrado -->
    <script>
    document.querySelectorAll('.btn-borrar').forEach(btn => {
        btn.addEventListener('click', function() {
            const cuenta = this.getAttribute('data-cuenta');
            
            if (confirm(`¿Está seguro que desea marcar como inactiva la cuenta ${cuenta}?\n\nLa cuenta no se eliminará pero dejará de ser visible para la mayoría de usuarios.`)) {
                window.location.href = `borrar.php?id=${encodeURIComponent(cuenta)}`;
            }
        });
    });
    
    // Cerrar mensajes automáticamente
    setTimeout(() => {
        const mensajes = document.querySelectorAll('.mensaje-flotante');
        mensajes.forEach(mensaje => {
            mensaje.classList.add('cerrando');
            setTimeout(() => mensaje.remove(), 300);
        });
    }, 5000);

    // Permitir cerrar al hacer clic
    document.addEventListener('click', function(e) {
        if (e.target.closest('.mensaje-flotante')) {
            const mensaje = e.target.closest('.mensaje-flotante');
            mensaje.classList.add('cerrando');
            setTimeout(() => mensaje.remove(), 300);
        }
    });
    </script>
</body>
</html>