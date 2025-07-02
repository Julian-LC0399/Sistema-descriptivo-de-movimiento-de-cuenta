<?php
// clientes/lista.php
require_once '../includes/config.php';
require_once '../includes/database.php';
requireLogin(); // Requiere que el usuario esté logueado

$tituloPagina = "Listado de Clientes";
$mensaje = isset($_GET['mensaje']) ? $_GET['mensaje'] : '';

// Configuración de paginación
$clientesPorPagina = 20;
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
    $mensaje = "Error al contar clientes: " . $e->getMessage();
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
    $mensaje = "Error al obtener clientes: " . $e->getMessage();
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
    <title><?= e($tituloPagina) ?> - Sistema Bancario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">
</head>
<body class="clientes">
    <!-- Incluir sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Contenido principal -->
    <div class="main-container">
        <!-- Título centrado -->
        <div class="d-flex justify-content-center mb-4">
            <h2><?= e($tituloPagina) ?></h2>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-info"><?= e($mensaje) ?></div>
        <?php endif; ?>
        
        <div class="search-box">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="busqueda" class="form-control" placeholder="Buscar clientes..." 
                           value="<?= e($busqueda) ?>">
                </div>
                <div class="col-md-4">
                    <select name="campo" class="form-select">
                        <?php foreach ($camposBusqueda as $valor => $texto): ?>
                            <option value="<?= e($valor) ?>" <?= $campoBusqueda == $valor ? 'selected' : '' ?>>
                                <?= e($texto) ?>
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
        
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr class="table-primary">
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
                                <td colspan="7" class="text-center py-4">No se encontraron clientes</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td data-label="ID Cliente"><?= e($cliente['cuscun']) ?></td>
                                    <td data-label="Nombre"><?= e($cliente['cusna1']) ?></td>
                                    <td data-label="Dirección"><?= e($cliente['cusna2']) ?></td>
                                    <td data-label="Ciudad"><?= e($cliente['cuscty']) ?></td>
                                    <td data-label="Email"><?= e($cliente['cuseml']) ?></td>
                                    <td data-label="Teléfono"><?= e($cliente['cusphn']) ?></td>
                                    <td data-label="Acciones">
                                        <a href="editar.php?id=<?= e($cliente['cuscun']) ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger btn-borrar" data-id="<?= e($cliente['cuscun']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación">
                    <ul class="pagination">
                        <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=1&busqueda=<?= e($busqueda) ?>&campo=<?= e($campoBusqueda) ?>">
                                Primera
                            </a>
                        </li>
                        <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>&busqueda=<?= e($busqueda) ?>&campo=<?= e($campoBusqueda) ?>">
                                Anterior
                            </a>
                        </li>
                        
                        <?php 
                        // Mostrar páginas cercanas a la actual
                        $inicio = max(1, $paginaActual - 2);
                        $fin = min($totalPaginas, $paginaActual + 2);
                        
                        for ($i = $inicio; $i <= $fin; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= e($busqueda) ?>&campo=<?= e($campoBusqueda) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>&busqueda=<?= e($busqueda) ?>&campo=<?= e($campoBusqueda) ?>">
                                Siguiente
                            </a>
                        </li>
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $totalPaginas ?>&busqueda=<?= e($busqueda) ?>&campo=<?= e($campoBusqueda) ?>">
                                Última
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="results-count">
                    Mostrando <?= ($offset + 1) ?> a <?= min($offset + $clientesPorPagina, $totalClientes) ?> de <?= $totalClientes ?> clientes
                </div>
                <div>
                    <a href="crear.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Agregar Cliente
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar el clic en botones de borrar
        document.querySelectorAll('.btn-borrar').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const idCliente = this.getAttribute('data-id');
                
                if (confirm('¿Estás seguro de que deseas borrar este cliente? Esta acción no se puede deshacer.')) {
                    window.location.href = 'borrar.php?id=' + idCliente;
                }
            });
        });
    });
    </script>
</body>
</html>