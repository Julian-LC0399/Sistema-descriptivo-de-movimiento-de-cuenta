<?php
// estados/lista.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Histórico de estados de cuentas";

// Configuración de paginación
$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Parámetros de búsqueda
$busquedaCuenta = isset($_GET['busqueda_cuenta']) ? trim($_GET['busqueda_cuenta']) : '';
$busquedaCliente = isset($_GET['busqueda_cliente']) ? trim($_GET['busqueda_cliente']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Consulta base con JOINs
$sql = "SELECT h.hstacc, h.hstdat, h.hststa, h.hstrsn, h.hstusr, h.hstip, h.hstauth,
               a.acmcun, c.cusna1, c.cusna2, c.cusln1, c.cusln2,
               u.username as usuario_nombre
        FROM achst h
        JOIN acmst a ON h.hstacc = a.acmacc COLLATE utf8mb4_unicode_ci
        LEFT JOIN cumst c ON a.acmcun = c.cuscun COLLATE utf8mb4_unicode_ci
        LEFT JOIN users u ON h.hstusr = u.username COLLATE utf8mb4_unicode_ci";

// Consulta para contar debe incluir los mismos JOINs
$contarSql = "SELECT COUNT(*) as total 
              FROM achst h
              JOIN acmst a ON h.hstacc = a.acmacc COLLATE utf8mb4_unicode_ci
              LEFT JOIN cumst c ON a.acmcun = c.cuscun COLLATE utf8mb4_unicode_ci
              LEFT JOIN users u ON h.hstusr = u.username COLLATE utf8mb4_unicode_ci";

$params = [];
$conditions = [];

// Aplicar filtros de búsqueda con búsqueda parcial
if (!empty($busquedaCuenta)) {
    $conditions[] = "h.hstacc LIKE CONCAT('%', :busqueda_cuenta, '%') COLLATE utf8mb4_unicode_ci";
    $params[':busqueda_cuenta'] = $busquedaCuenta;
}

if (!empty($busquedaCliente)) {
    $conditions[] = "(c.cusna1 LIKE CONCAT('%', :busqueda_nombre, '%') COLLATE utf8mb4_unicode_ci OR 
                     c.cusln1 LIKE CONCAT('%', :busqueda_apellido, '%') COLLATE utf8mb4_unicode_ci OR
                     c.cusna2 LIKE CONCAT('%', :busqueda_nombre2, '%') COLLATE utf8mb4_unicode_ci OR
                     c.cusln2 LIKE CONCAT('%', :busqueda_apellido2, '%') COLLATE utf8mb4_unicode_ci)";
    $params[':busqueda_nombre'] = $busquedaCliente;
    $params[':busqueda_apellido'] = $busquedaCliente;
    $params[':busqueda_nombre2'] = $busquedaCliente;
    $params[':busqueda_apellido2'] = $busquedaCliente;
}

if (!empty($fechaDesde)) {
    $conditions[] = "DATE(h.hstdat) >= :fecha_desde";
    $params[':fecha_desde'] = $fechaDesde;
}

if (!empty($fechaHasta)) {
    $conditions[] = "DATE(h.hstdat) <= :fecha_hasta";
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
    error_log("Error al contar registros: " . $e->getMessage());
    error_log("Consulta SQL: " . $contarSql);
    $_SESSION['error'] = "Error al procesar la búsqueda. Por favor intente nuevamente.";
    $totalRegistros = 0;
    $totalPaginas = 1;
}

// Consulta para obtener registros con paginación
$sql .= " ORDER BY h.hstdat DESC LIMIT :limit OFFSET :offset";
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
    error_log("Error al obtener registros: " . $e->getMessage());
    error_log("Consulta SQL: " . $sql);
    $_SESSION['error'] = "Error al cargar los registros. Por favor intente nuevamente.";
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

        <!-- Filtros con búsqueda mejorada -->
        <div class="filtros-card mb-4">
            <div class="filtros-header">
                <h3 class="filtros-title">
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="busqueda_cuenta" class="form-label">Buscar por cuenta</label>
                    <input type="text" class="form-control" id="busqueda_cuenta" name="busqueda_cuenta" 
                           value="<?= htmlspecialchars($busquedaCuenta) ?>" placeholder="Parte del número de cuenta">
                </div>
                <div class="form-group">
                    <label for="busqueda_cliente" class="form-label">Buscar por cliente</label>
                    <input type="text" class="form-control" id="busqueda_cliente" name="busqueda_cliente" 
                           value="<?= htmlspecialchars($busquedaCliente) ?>" placeholder="Parte del nombre o apellido">
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
                    <a href="estados.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Mensaje inicial cuando no hay filtros -->
        <?php if (empty($busquedaCuenta) && empty($busquedaCliente) && empty($fechaDesde) && empty($fechaHasta)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-info-circle fs-4"></i>
                <p class="mt-2 mb-0">Utilice los filtros de búsqueda para mostrar registros</p>
            </div>
        <?php else: ?>
            <!-- Tabla de registros -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Cuenta</th>
                                <th>Cliente</th>
                                <th>Fecha/Hora</th>
                                <th>Estado</th>
                                <th>Razón</th>
                                <th>Usuario</th>
                                <th>Autorizó</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registros)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-exclamation-circle fs-4"></i>
                                        <p class="mt-2">No se encontraron registros</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($registros as $registro): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($registro['hstacc']) ?></td>
                                        <td>
                                            <?= !empty($registro['cusna1']) 
                                                ? htmlspecialchars(
                                                    trim(
                                                        ($registro['cusna1'] ?? '') . ' ' . 
                                                        ($registro['cusna2'] ?? '') . ' ' .
                                                        ($registro['cusln1'] ?? '') . ' ' . 
                                                        ($registro['cusln2'] ?? '')
                                                    )
                                                )
                                                : 'N/A' 
                                            ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i:s', strtotime($registro['hstdat'])) ?></td>
                                        <td>
                                            <span class="badge <?= $registro['hststa'] === 'A' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $registro['hststa'] === 'A' ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($registro['hstrsn']) ?></td>
                                        <td><?= htmlspecialchars($registro['hstusr']) ?></td>
                                        <td><?= !empty($registro['hstauth']) ? htmlspecialchars($registro['hstauth']) : 'N/A' ?></td>
                                        <td><?= !empty($registro['hstip']) ? htmlspecialchars($registro['hstip']) : 'N/A' ?></td>
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
                        <?= !empty($busquedaCuenta) ? '| Cuenta: '.htmlspecialchars($busquedaCuenta) : '' ?>
                        <?= !empty($busquedaCliente) ? '| Cliente: '.htmlspecialchars($busquedaCliente) : '' ?>
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
    });
    </script>
</body>
</html>