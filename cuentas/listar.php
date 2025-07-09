<?php
// cuentas/listar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden ver todas las cuentas
$allowedRoles = ['admin', 'gerente'];
$isAdminOrGerente = in_array($_SESSION['role'], $allowedRoles);

// Configuración de paginación
$porPagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $porPagina;

// Filtros
$filtroCliente = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
$filtroCuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : '';
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';

try {
    $pdo = getPDO();
    
    // Construir consulta base (MODIFICADO: ahora solo muestra cusna1 como nombre_cliente)
    $sql = "SELECT 
                a.acmacc AS cuenta, 
                a.acmbrn AS sucursal,
                a.acmsta AS estado, 
                a.acmopn AS fecha_apertura,
                c.cuscun AS id_cliente,
                c.cusna1 AS nombre_cliente
            FROM acmst a
            JOIN cumst c ON a.acmcun = c.cuscun";
    
    $where = [];
    $params = [];
    
    // Si no es admin/gerente, solo mostrar cuentas del usuario actual
    if (!$isAdminOrGerente) {
        $sql .= " JOIN users u ON c.cuscun = u.cliente_id";
        $where[] = "u.id = :user_id";
        $params[':user_id'] = $_SESSION['user_id'];
    }
    
    // Aplicar filtros de búsqueda (MODIFICADO: ahora solo busca en cusna1)
    if ($filtroCliente !== '') {
        $where[] = "(c.cusna1 LIKE :cliente OR c.cuscun = :cliente_num)";
        $params[':cliente'] = "%$filtroCliente%";
        $params[':cliente_num'] = $filtroCliente;
    }
    
    if ($filtroCuenta !== '') {
        $where[] = "a.acmacc LIKE :cuenta";
        $params[':cuenta'] = "%$filtroCuenta%";
    }
    
    if ($filtroEstado !== '' && in_array($filtroEstado, ['A', 'I'])) {
        $where[] = "a.acmsta = :estado";
        $params[':estado'] = $filtroEstado;
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
    die("Ocurrió un error al recuperar las cuentas. Por favor intente más tarde.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Cuentas Bancarias</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Listado de Cuentas Bancarias</h2>
        
        <!-- Filtros -->
        <div class="filtros-card mb-4">
            <div class="filtros-header">
                <h3 class="filtros-title">
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="cliente" class="form-label">Cliente</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" 
                           value="<?php echo htmlspecialchars($filtroCliente); ?>" placeholder="Nombre o ID">
                </div>
                <div class="form-group">
                    <label for="cuenta" class="form-label">Número de Cuenta</label>
                    <input type="text" class="form-control" id="cuenta" name="cuenta" 
                           value="<?php echo htmlspecialchars($filtroCuenta); ?>" placeholder="Número de cuenta">
                </div>
                <div class="form-group">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="A" <?php echo $filtroEstado === 'A' ? 'selected' : ''; ?>>Activo</option>
                        <option value="I" <?php echo $filtroEstado === 'I' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
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
        
        <!-- Mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['mensaje']['tipo']); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['mensaje']['texto']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        
        <!-- Tabla de cuentas -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número de Cuenta</th>
                            <th>Cliente</th>
                            <th>Sucursal</th>
                            <th>Estado</th>
                            <th>Fecha Apertura</th>
                            <?php if ($isAdminOrGerente): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cuentas)): ?>
                            <tr>
                                <td colspan="<?php echo $isAdminOrGerente ? 6 : 5; ?>" class="text-center py-4">
                                    <i class="bi bi-exclamation-circle fs-4"></i>
                                    <p class="mt-2">No se encontraron cuentas</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cuentas as $cuenta): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cuenta['cuenta']); ?></td>
                                    <td><?php echo htmlspecialchars($cuenta['nombre_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($cuenta['sucursal']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $cuenta['estado'] === 'A' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $cuenta['estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($cuenta['fecha_apertura'])); ?></td>
                                    <?php if ($isAdminOrGerente): ?>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="editar.php?id=<?php echo urlencode($cuenta['cuenta']); ?>" 
                                                   class="btn btn-sm btn-warning btn-action"
                                                   title="Editar cuenta">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <button class="btn btn-sm btn-danger btn-action btn-borrar" 
                                                        data-cuenta="<?php echo htmlspecialchars($cuenta['cuenta']); ?>" 
                                                        data-nombre="<?php echo htmlspecialchars($cuenta['nombre_cliente']); ?>"
                                                        title="Eliminar cuenta">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

        <!-- Botón para agregar nueva cuenta -->
        <?php if ($isAdminOrGerente): ?>
            <div class="text-end mt-4">
                <a href="crear.php" class="btn btn-success btn-lg">
                    <i class="bi bi-plus-circle"></i> Agregar Nueva Cuenta
                </a>
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
            const nombre = this.getAttribute('data-nombre');
            
            if (confirm(`¿Está seguro que desea borrar la cuenta ${cuenta} del cliente ${nombre}?\n\nEsta acción no se puede deshacer.`)) {
                window.location.href = `borrar.php?id=${encodeURIComponent(cuenta)}`;
            }
        });
    });
    
    // Cerrar automáticamente alertas después de 5 segundos
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            new bootstrap.Alert(alert).close();
        });
    }, 5000);
    </script>
</body>
</html>