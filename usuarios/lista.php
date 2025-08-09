<?php
// usuarios/lista.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Usuarios";

// Configuración de paginación
$usuariosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $usuariosPorPagina;

// Parámetros de búsqueda
$busquedaId = isset($_GET['busqueda_id']) ? trim($_GET['busqueda_id']) : '';
$busquedaUsuario = isset($_GET['busqueda_usuario']) ? trim($_GET['busqueda_usuario']) : '';
$busquedaNombre = isset($_GET['busqueda_nombre']) ? trim($_GET['busqueda_nombre']) : '';
$mostrarResultados = !empty($busquedaId) || !empty($busquedaUsuario) || !empty($busquedaNombre);

// Consulta base
$sql = "SELECT u.id, u.username, u.role, u.activo, 
               u.creado_en, u.actualizado_en, 
               c.cusna1, c.cusna2, c.cusln1, c.cusln2 
        FROM users u
        LEFT JOIN cumst c ON u.cuscun = c.cuscun";
$params = [];
$contarSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN cumst c ON u.cuscun = c.cuscun"; // Corrección aplicada aquí
$conditions = [];

// Aplicar filtros de búsqueda
if (!empty($busquedaId)) {
    $conditions[] = "u.id = :busqueda_id";
    $params[':busqueda_id'] = $busquedaId;
}

if (!empty($busquedaUsuario)) {
    $conditions[] = "u.username LIKE :busqueda_usuario";
    $params[':busqueda_usuario'] = "%$busquedaUsuario%";
}

if (!empty($busquedaNombre)) {
    $conditions[] = "CONCAT(c.cusna1, ' ', c.cusna2, ' ', c.cusln1, ' ', c.cusln2) LIKE :busqueda_nombre";
    $params[':busqueda_nombre'] = "%$busquedaNombre%";
}

// Construir WHERE clause si hay condiciones
if (!empty($conditions)) {
    $whereClause = " WHERE " . implode(" AND ", $conditions);
    $sql .= $whereClause;
    $contarSql .= $whereClause;
}

// Obtener conexión PDO
$pdo = getPDO();

// Contar total de usuarios
try {
    $stmt = $pdo->prepare($contarSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalUsuarios = $stmt->fetchColumn();
    $totalPaginas = ceil($totalUsuarios / $usuariosPorPagina);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar usuarios: " . $e->getMessage();
    $totalUsuarios = 0;
    $totalPaginas = 1;
}

// Consulta para obtener usuarios con paginación
$sql .= " ORDER BY c.cusna1, c.cusln1 LIMIT :limit OFFSET :offset";
$params[':limit'] = $usuariosPorPagina;
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
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener usuarios: " . $e->getMessage();
    $usuarios = [];
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
    <style>
        .table-container {
            display: <?= $mostrarResultados ? 'block' : 'none' ?>;
        }
    </style>
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

        <!-- Filtros -->
        <div class="filtros-card mb-4">
            <div class="filtros-header">
                <h3 class="filtros-title">
                    <i class="bi bi-funnel"></i> Buscar Usuarios
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="busqueda_id" class="form-label">Buscar por código</label>
                    <input type="number" class="form-control" id="busqueda_id" name="busqueda_id" 
                           value="<?= htmlspecialchars($busquedaId) ?>" placeholder="Ingrese código del usuario" min="1">
                </div>
                <div class="form-group">
                    <label for="busqueda_usuario" class="form-label">Buscar por usuario</label>
                    <input type="text" class="form-control" id="busqueda_usuario" name="busqueda_usuario" 
                           value="<?= htmlspecialchars($busquedaUsuario) ?>" placeholder="Ingrese nombre de usuario">
                </div>
                <div class="form-group">
                    <label for="busqueda_nombre" class="form-label">Buscar por nombre o apellido</label>
                    <input type="text" class="form-control" id="busqueda_nombre" name="busqueda_nombre" 
                           value="<?= htmlspecialchars($busquedaNombre) ?>" placeholder="Ingrese parte del nombre">
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
        
        <?php if (!$mostrarResultados): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-info-circle fs-4"></i>
                <p class="mt-2 mb-0">Utilice los filtros de búsqueda para mostrar usuarios</p>
            </div>
        <?php endif; ?>
        
        <!-- Tabla de usuarios -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Actualizado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-exclamation-circle fs-4"></i>
                                    <p class="mt-2">No se encontraron usuarios</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario['id']) ?></td>
                                    <td><?= htmlspecialchars($usuario['username']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(
                                            trim(
                                                ($usuario['cusna1'] ?? '') . ' ' . 
                                                ($usuario['cusna2'] ?? '') . ' ' .
                                                ($usuario['cusln1'] ?? '') . ' ' . 
                                                ($usuario['cusln2'] ?? '')
                                            )
                                        ); ?>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['role']) ?></td>
                                    <td>
                                        <span class="badge <?= $usuario['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($usuario['creado_en'])) ?></td>
                                    <td>
                                        <?= $usuario['actualizado_en'] ? 
                                            date('d/m/Y H:i', strtotime($usuario['actualizado_en'])) : 
                                            'Nunca' ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="editar.php?id=<?= urlencode($usuario['id']) ?>" 
                                               class="btn btn-sm btn-warning btn-action"
                                               title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-danger btn-action btn-borrar" 
                                                        data-id="<?= htmlspecialchars($usuario['id']) ?>"
                                                        title="Desactivar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumen y paginación -->
            <?php if ($mostrarResultados): ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="alert alert-info mb-0 py-2">
                        Mostrando <?= count($usuarios) ?> de <?= $totalUsuarios ?> usuarios
                        <?= !empty($busquedaId) ? '| ID: ' . htmlspecialchars($busquedaId) : '' ?>
                        <?= !empty($busquedaUsuario) ? '| Usuario: ' . htmlspecialchars($busquedaUsuario) : '' ?>
                        <?= !empty($busquedaNombre) ? '| Nombre: ' . htmlspecialchars($busquedaNombre) : '' ?>
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
            <?php endif; ?>
        </div>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar el clic en botones de borrar/activar
        document.querySelectorAll('.btn-borrar').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const idUsuario = this.getAttribute('data-id');
                
                if (confirm('¿Está seguro que desea desactivar este usuario?\n\nEl usuario perderá acceso al sistema pero podrá reactivarse después.')) {
                    window.location.href = 'borrar.php?id=' + idUsuario;
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