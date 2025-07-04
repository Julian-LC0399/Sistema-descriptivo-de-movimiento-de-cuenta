<?php
// clientes/lista.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Listado de Clientes";

// Configuración de paginación
$clientesPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $clientesPorPagina;

// Parámetros de búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$campoBusqueda = isset($_GET['campo']) ? $_GET['campo'] : 'cusna1';

// Consulta base
$sql = "SELECT cuscun, cusna1, cusna2, cuscty, cuseml, cusphn FROM cumst WHERE cussts = 'A'";
$params = [];
$contarSql = "SELECT COUNT(*) as total FROM cumst WHERE cussts = 'A'";

// Aplicar filtros de búsqueda
if (!empty($busqueda)) {
    $sql .= " AND $campoBusqueda LIKE :busqueda";
    $contarSql .= " AND $campoBusqueda LIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}

// Contar total de clientes
try {
    $stmt = $pdo->prepare($contarSql);
    $stmt->execute($params);
    $totalClientes = $stmt->fetch()['total'];
    $totalPaginas = ceil($totalClientes / $clientesPorPagina);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar clientes: " . $e->getMessage();
}

// Consulta para obtener clientes con paginación
$sql .= " ORDER BY cusna1 LIMIT :limit OFFSET :offset";
$params[':limit'] = $clientesPorPagina;
$params[':offset'] = $offset;

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => &$val) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindParam($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindParam($key, $val);
        }
    }
    $stmt->execute();
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener clientes: " . $e->getMessage();
    $clientes = [];
}

// Campos disponibles para búsqueda
$camposBusqueda = [
    'cusna1' => 'Nombre',
    'cusna2' => 'Dirección',
    'cuscty' => 'Ciudad',
    'cuseml' => 'Email',
    'cusphn' => 'Teléfono',
    'cuscun' => 'ID Cliente'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> - Sistema Bancario</title>
    <!-- Bootstrap CSS -->
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS personalizado -->
    <link href="<?= BASE_URL ?>assets/css/cuentas.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Fuente para montos -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= htmlspecialchars($tituloPagina) ?></h2>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filtros-card mb-4">
            <div class="filtros-header">
                <h3 class="filtros-title">
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="busqueda" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="busqueda" name="busqueda" 
                           value="<?= htmlspecialchars($busqueda) ?>" placeholder="Término de búsqueda">
                </div>
                <div class="form-group">
                    <label for="campo" class="form-label">Campo</label>
                    <select name="campo" class="form-select" id="campo">
                        <?php foreach ($camposBusqueda as $valor => $texto): ?>
                            <option value="<?= htmlspecialchars($valor) ?>" <?= $campoBusqueda == $valor ? 'selected' : '' ?>>
                                <?= htmlspecialchars($texto) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtros-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="lista.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Tabla de clientes -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID Cliente</th>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Ciudad</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-exclamation-circle fs-4"></i>
                                    <p class="mt-2">No se encontraron clientes</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['cuscun']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cusna1']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cusna2']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cuscty']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cuseml']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cusphn']) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="editar.php?id=<?= urlencode($cliente['cuscun']) ?>" 
                                               class="btn btn-sm btn-warning btn-action"
                                               title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger btn-action btn-borrar" 
                                                    data-id="<?= htmlspecialchars($cliente['cuscun']) ?>"
                                                    title="Borrar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumen y paginación -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="alert alert-info mb-0 py-2">
                    Mostrando <?= count($clientes) ?> de <?= $totalClientes ?> clientes
                </div>
                
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación">
                        <ul class="pagination mb-0">
                            <?php if ($paginaActual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual - 1])) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($paginaActual < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual + 1])) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botón para agregar nuevo cliente -->
        <div class="text-end mt-4">
            <a href="crear.php" class="btn btn-success btn-lg">
                <i class="bi bi-plus-circle"></i> Agregar Nuevo Cliente
            </a>
        </div>
    </main>

    <!-- Bootstrap JS Bundle con Popper -->
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para confirmar borrado -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar el clic en botones de borrar
        document.querySelectorAll('.btn-borrar').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const idCliente = this.getAttribute('data-id');
                
                if (confirm('¿Está seguro que desea desactivar este cliente?\n\nEsta acción no se puede deshacer.')) {
                    window.location.href = 'borrar.php?id=' + idCliente;
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
    });
    </script>
</body>
</html>