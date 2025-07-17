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

// Parámetro de búsqueda general
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Consulta base (incluyendo cusphh - teléfono de habitación)
$sql = "SELECT cuscun, cusidn, cusna1, cusna2, cusln1, cusln2, cuscty, cuseml, cusphn, cusphh, cusemp, cusjob FROM cumst WHERE cussts = 'A'";
$params = [];
$contarSql = "SELECT COUNT(*) as total FROM cumst WHERE cussts = 'A'";

// Aplicar filtros de búsqueda
if (!empty($busqueda)) {
    $searchTerm = "%$busqueda%";
    $sql .= " AND (cusna1 LIKE :busqueda_nombre OR cusln1 LIKE :busqueda_apellido OR cusidn LIKE :busqueda_cedula)";
    $contarSql .= " AND (cusna1 LIKE :busqueda_nombre OR cusln1 LIKE :busqueda_apellido OR cusidn LIKE :busqueda_cedula)";
    $params[':busqueda_nombre'] = $searchTerm;
    $params[':busqueda_apellido'] = $searchTerm;
    $params[':busqueda_cedula'] = $searchTerm;
}

// Obtener conexión PDO
$pdo = getPDO();

// Contar total de clientes
try {
    $stmt = $pdo->prepare($contarSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalClientes = $stmt->fetchColumn();
    $totalPaginas = ceil($totalClientes / $clientesPorPagina);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar clientes: " . $e->getMessage();
    $totalClientes = 0;
    $totalPaginas = 1;
}

// Consulta para obtener clientes con paginación
$sql .= " ORDER BY cusna1 LIMIT :limit OFFSET :offset";
$params[':limit'] = $clientesPorPagina;
$params[':offset'] = $offset;

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener clientes: " . $e->getMessage();
    $clientes = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> - Sistema Bancario</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= htmlspecialchars($tituloPagina) ?></h2>
        
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
                    <i class="bi bi-funnel"></i> Buscar Clientes
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="busqueda" class="form-label">Buscar por nombre, apellido o cédula</label>
                    <input type="text" class="form-control" id="busqueda" name="busqueda" 
                           value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ingrese nombre, apellido o cédula">
                </div>
                <div class="filtros-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="lista.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Tabla de clientes (con teléfono de habitación) -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID Cliente</th>
                            <th>Cédula</th>
                            <th>Nombre Completo</th>
                            <th>Empresa</th>
                            <th>Cargo</th>
                            <th>Ciudad</th>
                            <th>Email</th>
                            <th>Teléfono Móvil</th>
                            <th>Teléfono Habitación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="bi bi-exclamation-circle fs-4"></i>
                                    <p class="mt-2">No se encontraron clientes</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['cuscun']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cusidn']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $cliente['cusna1'] . ' ' . 
                                            ($cliente['cusna2'] ? $cliente['cusna2'] . ' ' : '') . 
                                            $cliente['cusln1'] . ' ' . 
                                            ($cliente['cusln2'] ? $cliente['cusln2'] : '')
                                        ) ?>
                                    </td>
                                    <td><?= htmlspecialchars($cliente['cusemp'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($cliente['cusjob'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($cliente['cuscty']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cuseml']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cusphn']) ?></td>
                                    <td><?= htmlspecialchars($cliente['cusphh'] ?? 'N/A') ?></td>
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

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
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
        
        // Cerrar mensajes automáticamente
        setTimeout(() => {
            const mensajes = document.querySelectorAll('.mensaje-flotante');
            mensajes.forEach(mensaje => {
                mensaje.classList.add('cerrando');
                setTimeout(() => mensaje.remove(), 300);
            });
        }, 5000);

        // Permitir cerrar al hacer click
        document.addEventListener('click', function(e) {
            if (e.target.closest('.mensaje-flotante')) {
                const mensaje = e.target.closest('.mensaje-flotante');
                mensaje.classList.add('cerrando');
                setTimeout(() => mensaje.remove(), 300);
            }
        });
    });
    </script>
</body>
</html>