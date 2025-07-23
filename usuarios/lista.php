<?php
// usuarios/lista.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Usuarios del Sistema";

// Configuración de paginación
$usuariosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $usuariosPorPagina;

// Parámetros de búsqueda
$busquedaUsuario = isset($_GET['busqueda_usuario']) ? trim($_GET['busqueda_usuario']) : '';
$busquedaGeneral = isset($_GET['busqueda_general']) ? trim($_GET['busqueda_general']) : '';
$filtroRol = isset($_GET['rol']) ? $_GET['rol'] : '';
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '1'; // Por defecto muestra solo activos

// Consulta base
$sql = "SELECT u.id, u.username, u.nombre, u.apellido, u.role, u.activo, 
               u.creado_en, u.actualizado_en, c.cusna1, c.cusln1 
        FROM users u
        LEFT JOIN cumst c ON u.cuscun = c.cuscun";
$params = [];
$contarSql = "SELECT COUNT(*) as total FROM users u";
$conditions = [];

// Aplicar filtro de estado
if ($filtroEstado !== 'T') { // 'T' sería para mostrar Todos
    $conditions[] = "u.activo = :estado";
    $params[':estado'] = $filtroEstado;
}

// Aplicar filtro de rol
if (!empty($filtroRol)) {
    $conditions[] = "u.role = :rol";
    $params[':rol'] = $filtroRol;
}

// Aplicar filtros de búsqueda
if (!empty($busquedaUsuario)) {
    $conditions[] = "u.username LIKE :busqueda_usuario";
    $params[':busqueda_usuario'] = "%$busquedaUsuario%";
}

if (!empty($busquedaGeneral)) {
    $conditions[] = "(u.nombre LIKE :busqueda_nombre OR u.apellido LIKE :busqueda_apellido)";
    $params[':busqueda_nombre'] = "%$busquedaGeneral%";
    $params[':busqueda_apellido'] = "%$busquedaGeneral%";
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
$sql .= " ORDER BY u.nombre, u.apellido LIMIT :limit OFFSET :offset";
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
                    <label for="busqueda_usuario" class="form-label">Buscar por usuario</label>
                    <input type="text" class="form-control" id="busqueda_usuario" name="busqueda_usuario" 
                           value="<?= htmlspecialchars($busquedaUsuario) ?>" placeholder="Ingrese nombre de usuario">
                </div>
                <div class="form-group">
                    <label for="busqueda_general" class="form-label">Buscar por nombre o apellido</label>
                    <input type="text" class="form-control" id="busqueda_general" name="busqueda_general" 
                           value="<?= htmlspecialchars($busquedaGeneral) ?>" placeholder="Ingrese nombre o apellido">
                </div>
                <div class="form-group">
                    <label for="rol" class="form-label">Rol</label>
                    <select class="form-select" id="rol" name="rol">
                        <option value="">Todos</option>
                        <option value="admin" <?= $filtroRol === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="gerente" <?= $filtroRol === 'gerente' ? 'selected' : '' ?>>Gerente</option>
                        <option value="cajero" <?= $filtroRol === 'cajero' ? 'selected' : '' ?>>Cajero</option>
                        <option value="cliente" <?= $filtroRol === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="1" <?= $filtroEstado === '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $filtroEstado === '0' ? 'selected' : '' ?>>Inactivos</option>
                        <option value="T" <?= $filtroEstado === 'T' ? 'selected' : '' ?>>Todos</option>
                    </select>
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
        
        <!-- Tabla de usuarios -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Rol</th>
                            <th>Cliente Asociado</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Actualizado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
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
                                            ($usuario['nombre'] ?? '') . ' ' . 
                                            ($usuario['apellido'] ?? '')
                                        ) ?>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['role']) ?></td>
                                    <td>
                                        <?= !empty($usuario['cusna1']) ? 
                                            htmlspecialchars($usuario['cusna1'] . ' ' . $usuario['cusln1']) : 
                                            'N/A' ?>
                                    </td>
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
                                                        title="<?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="bi bi-<?= $usuario['activo'] ? 'trash' : 'arrow-counterclockwise' ?>"></i>
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
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="alert alert-info mb-0 py-2">
                    Mostrando <?= count($usuarios) ?> de <?= $totalUsuarios ?> usuarios
                    <?= $filtroEstado !== 'T' ? '('.($filtroEstado === '1' ? 'Activos' : 'Inactivos').')' : '' ?>
                    <?= !empty($filtroRol) ? '| Rol: '.ucfirst($filtroRol) : '' ?>
                    <?= !empty($busquedaUsuario) ? '| Usuario: '.htmlspecialchars($busquedaUsuario) : '' ?>
                    <?= !empty($busquedaGeneral) ? '| Búsqueda: '.htmlspecialchars($busquedaGeneral) : '' ?>
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
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar el clic en botones de borrar/activar
        document.querySelectorAll('.btn-borrar').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const idUsuario = this.getAttribute('data-id');
                const accion = this.getAttribute('title').toLowerCase();
                
                if (confirm(`¿Está seguro que desea ${accion} este usuario?\n\nEsta acción afectará su acceso al sistema.`)) {
                    window.location.href = 'cambiar_estado.php?id=' + idUsuario;
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