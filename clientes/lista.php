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
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/clientes.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= htmlspecialchars($tituloPagina) ?></h2>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['mensaje']) ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filtros mb-4">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="busqueda" class="form-control" placeholder="Buscar clientes..." 
                           value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col-md-4">
                    <select name="campo" class="form-select">
                        <?php foreach ($camposBusqueda as $valor => $texto): ?>
                            <option value="<?= htmlspecialchars($valor) ?>" <?= $campoBusqueda == $valor ? 'selected' : '' ?>>
                                <?= htmlspecialchars($texto) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tabla de clientes -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
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
                            <td colspan="7" class="text-center">No se encontraron clientes</td>
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
                                    <a href="editar.php?id=<?= urlencode($cliente['cuscun']) ?>" 
                                       class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger btn-borrar" 
                                            data-id="<?= htmlspecialchars($cliente['cuscun']) ?>"
                                            title="Borrar">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Resumen -->
        <div class="alert alert-info mt-3">
            Mostrando <?= count($clientes) ?> de <?= $totalClientes ?> clientes encontrados.
        </div>

        <!-- Botón para agregar nuevo cliente -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
            <a href="crear.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Agregar Nuevo Cliente
            </a>
        </div>
        
        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php if ($paginaActual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual - 1])) ?>">
                                Anterior
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
                                Siguiente
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar el clic en botones de borrar
        document.querySelectorAll('.btn-borrar').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const idCliente = this.getAttribute('data-id');
                
                if (confirm('¿Estás seguro de que deseas desactivar este cliente?')) {
                    window.location.href = 'borrar.php?id=' + idCliente;
                }
            });
        });
    });
    </script>
</body>
</html>