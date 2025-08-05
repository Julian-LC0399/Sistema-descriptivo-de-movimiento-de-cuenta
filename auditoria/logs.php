<?php
// logs.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Registro de Accesos al Sistema";

// Configuración de paginación
$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Parámetros de búsqueda
$busquedaUsuario = isset($_GET['busqueda_usuario']) ? trim($_GET['busqueda_usuario']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Determinar si hay filtros aplicados
$filtrosAplicados = !empty($busquedaUsuario) || !empty($fechaDesde) || !empty($fechaHasta);

// Consulta base con JOINs
$sql = "SELECT l.*, u.role as rol_usuario
        FROM access_logs l
        JOIN users u ON l.user_id = u.id";
$params = [];
$contarSql = "SELECT COUNT(*) as total FROM access_logs l JOIN users u ON l.user_id = u.id";
$conditions = [];

// Aplicar filtros de búsqueda
if (!empty($busquedaUsuario)) {
    $conditions[] = "u.username LIKE :busqueda_usuario";
    $params[':busqueda_usuario'] = "%".$busquedaUsuario."%";
}

if (!empty($fechaDesde)) {
    $conditions[] = "DATE(l.access_time) >= :fecha_desde";
    $params[':fecha_desde'] = $fechaDesde;
}

if (!empty($fechaHasta)) {
    $conditions[] = "DATE(l.access_time) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fechaHasta;
}

// Construir WHERE clause si hay condiciones
if (!empty($conditions)) {
    $whereClause = " WHERE " . implode(" AND ", $conditions);
    $sql .= $whereClause;
    $contarSql .= $whereClause;
}

// Obtener conexión PDO
$pdo = getPDO();

// Contar total de registros
try {
    $stmt = $pdo->prepare($contarSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalRegistros = $stmt->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar registros: " . $e->getMessage();
    $totalRegistros = 0;
    $totalPaginas = 1;
}

// Consulta para obtener registros con paginación
$sql .= " ORDER BY l.access_time DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $registrosPorPagina;
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
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener registros: " . $e->getMessage();
    $registros = [];
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
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </h3>
            </div>
            <form method="get" class="filtros-grid" id="filtrosForm">
                <div class="form-group">
                    <label for="busqueda_usuario" class="form-label">Buscar por usuario</label>
                    <input type="text" class="form-control" id="busqueda_usuario" name="busqueda_usuario" 
                           value="<?= htmlspecialchars($busquedaUsuario) ?>" placeholder="Nombre de usuario">
                </div>
                <div class="form-group">
                    <label for="fecha_desde" class="form-label">Fecha desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                           value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="form-group">
                    <label for="fecha_hasta" class="form-label">Fecha hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                           value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
                <div class="filtros-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="logs.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Mensaje inicial cuando no hay filtros -->
        <?php if (!$filtrosAplicados): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-info-circle fs-4"></i>
                <p class="mt-2 mb-0">Utilice los filtros de búsqueda para mostrar registros</p>
            </div>
        <?php else: ?>
            <!-- Tabla de registros -->
            <div class="table-container" id="tablaResultados">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Fecha/Hora</th>
                                <th>Acción</th>
                                <th>Estado</th>
                                <th>Endpoint</th>
                                <th>IP</th>
                                <th>Dispositivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registros) && $filtrosAplicados): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-exclamation-circle fs-4"></i>
                                        <p class="mt-2">No se encontraron registros con los filtros aplicados</p>
                                    </td>
                                </tr>
                            <?php elseif (!empty($registros)): ?>
                                <?php foreach ($registros as $registro): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($registro['username']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $registro['rol_usuario'] === 'admin' ? 'danger' : 
                                                ($registro['rol_usuario'] === 'gerente' ? 'warning' : 
                                                ($registro['rol_usuario'] === 'cajero' ? 'info' : 'primary')) 
                                            ?>">
                                                <?= htmlspecialchars(ucfirst($registro['rol_usuario'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i:s', strtotime($registro['access_time'])) ?></td>
                                        <td><?= htmlspecialchars($registro['action']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $registro['status_code'] >= 200 && $registro['status_code'] < 300 ? 'success' : 
                                                ($registro['status_code'] >= 400 && $registro['status_code'] < 500 ? 'warning' : 
                                                ($registro['status_code'] >= 500 ? 'danger' : 'secondary')) 
                                            ?>">
                                                <?= htmlspecialchars($registro['status_code'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($registro['endpoint'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($registro['ip_address']) ?></td>
                                        <td>
                                            <small><?= htmlspecialchars(
                                                strlen($registro['user_agent']) > 30 ? 
                                                substr($registro['user_agent'], 0, 30).'...' : 
                                                $registro['user_agent']
                                            ) ?></small>
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
                        Mostrando <?= count($registros) ?> de <?= $totalRegistros ?> registros
                        <?= !empty($busquedaUsuario) ? '| Usuario: '.htmlspecialchars($busquedaUsuario) : '' ?>
                        <?= !empty($fechaDesde) ? '| Desde: '.htmlspecialchars($fechaDesde) : '' ?>
                        <?= !empty($fechaHasta) ? '| Hasta: '.htmlspecialchars($fechaHasta) : '' ?>
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
        <?php endif; ?>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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

        // Mostrar tabla cuando se aplican filtros
        const filtrosForm = document.getElementById('filtrosForm');
        if (filtrosForm) {
            filtrosForm.addEventListener('submit', function() {
                document.getElementById('tablaResultados').style.display = 'block';
            });
        }
    });
    </script>
</body>
</html>