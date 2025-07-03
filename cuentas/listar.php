<?php
// cuentas/listar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores y gerentes pueden ver todas las cuentas
// Los clientes solo ven sus propias cuentas
$allowedRoles = ['admin', 'gerente'];
$isAdminOrGerente = in_array($_SESSION['role'], $allowedRoles);

// Configuración de paginación
$porPagina = 10; // Número de registros por página
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $porPagina;

// Filtros
$filtroCliente = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
$filtroCuenta = isset($_GET['cuenta']) ? trim($_GET['cuenta']) : '';
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';

try {
    $pdo = getPDO();
    
    // Construir consulta base
    $sql = "SELECT 
                a.acmacc AS cuenta, 
                a.acmbal AS saldo, 
                a.acmavl AS disponible, 
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
        // Asumimos que hay una relación entre users.id y cumst.cuscun
        $sql .= " JOIN users u ON c.cuscun = u.cliente_id";
        $where[] = "u.id = :user_id";
        $params[':user_id'] = $_SESSION['user_id'];
    }
    
    // Aplicar filtros
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
    
    // Consulta para el total de registros (para paginación)
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
    
    // Vincular parámetros de paginación como enteros
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':por_pagina', $porPagina, PDO::PARAM_INT);
    
    // Vincular otros parámetros
    foreach ($params as $key => $value) {
        if ($key !== ':offset' && $key !== ':por_pagina') {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $cuentas = $stmt->fetchAll();
    
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
    <!-- Bootstrap CSS -->
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Nuestro CSS personalizado -->
    <link href="<?= BASE_URL ?>assets/css/cuentas.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Fuente Roboto Mono para montos -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Listado de Cuentas Bancarias</h2>
        
        <!-- Filtros -->
        <div class="filtros mb-4">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="cliente" class="form-label">Cliente</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" 
                           value="<?= htmlspecialchars($filtroCliente) ?>" placeholder="Nombre o ID">
                </div>
                <div class="col-md-3">
                    <label for="cuenta" class="form-label">Número de Cuenta</label>
                    <input type="text" class="form-control" id="cuenta" name="cuenta" 
                           value="<?= htmlspecialchars($filtroCuenta) ?>" placeholder="Número de cuenta">
                </div>
                <div class="col-md-2">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="A" <?= $filtroEstado === 'A' ? 'selected' : '' ?>>Activo</option>
                        <option value="I" <?= $filtroEstado === 'I' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                    <a href="listar.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
        
        <!-- Mostrar mensaje de éxito si existe -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['mensaje']) ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        
        <!-- Tabla de cuentas -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Número de Cuenta</th>
                        <th>Cliente</th>
                        <th>Saldo</th>
                        <th>Disponible</th>
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
                            <td colspan="<?= $isAdminOrGerente ? '7' : '6' ?>" class="text-center">No se encontraron cuentas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuentas as $cuenta): ?>
                            <tr>
                                <td><?= htmlspecialchars($cuenta['cuenta']) ?></td>
                                <td><?= htmlspecialchars($cuenta['nombre_cliente']) ?></td>
                                <td class="text-end"><?= number_format($cuenta['saldo'], 2, ',', '.') ?></td>
                                <td class="text-end"><?= number_format($cuenta['disponible'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="badge <?= $cuenta['estado'] === 'A' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $cuenta['estado'] === 'A' ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($cuenta['fecha_apertura'])) ?></td>
                                <?php if ($isAdminOrGerente): ?>
                                    <td>
                                        <a href="editar.php?id=<?= urlencode($cuenta['cuenta']) ?>" 
                                           class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil-fill"></i> Editar
                                        </a>
                                        <button class="btn btn-sm btn-danger btn-borrar" 
                                                data-cuenta="<?= htmlspecialchars($cuenta['cuenta']) ?>" 
                                                data-nombre="<?= htmlspecialchars($cuenta['nombre_cliente']) ?>"
                                                title="Borrar">
                                            <i class="bi bi-trash-fill"></i> Borrar
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Resumen -->
        <div class="alert alert-info mt-3">
            Mostrando <?= count($cuentas) ?> de <?= $totalRegistros ?> cuentas encontradas.
        </div>

        <!-- Botón para agregar nueva cuenta (solo visible para admin/gerente) -->
        <?php if ($isAdminOrGerente): ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
            <a href="crear.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Agregar Nueva Cuenta
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php if ($pagina > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                Anterior
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                                Siguiente
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </main>

    <!-- Bootstrap JS Bundle con Popper -->
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para confirmar borrado -->
    <script>
    // Confirmación para borrar cuenta
    document.querySelectorAll('.btn-borrar').forEach(btn => {
        btn.addEventListener('click', function() {
            const cuenta = this.getAttribute('data-cuenta');
            const nombre = this.getAttribute('data-nombre');
            
            if (confirm(`¿Está seguro que desea borrar la cuenta ${cuenta} del cliente ${nombre}?\n\nEsta acción no se puede deshacer.`)) {
                window.location.href = `borrar.php?id=${encodeURIComponent(cuenta)}`;
            }
        });
    });
    </script>
</body>
</html>