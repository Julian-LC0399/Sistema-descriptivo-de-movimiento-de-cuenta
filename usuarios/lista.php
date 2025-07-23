<?php
// users/lista.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Verificar autenticación y permisos
requireLogin();

// Solo administradores pueden ver la lista de usuarios
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Inicializar variables
$usuarios = []; // Inicializar como array vacío para evitar errores
$totalRegistros = 0;
$totalPaginas = 1;

// Configuración de paginación
$porPagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $porPagina;

// Filtros
$filtroNombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
$filtroRol = isset($_GET['rol']) ? $_GET['rol'] : '';
$filtroEstado = isset($_GET['activo']) ? (int)$_GET['activo'] : '';

try {
    $pdo = getPDO();
    
    // Construir consulta base
    $sql = "SELECT 
                id,
                username,
                email,
                nombre,
                apellido,
                role,
                activo,
                creado_en,
                actualizado_en
            FROM users";
    
    $where = [];
    $params = [];
    
    // Aplicar filtros de búsqueda
    if ($filtroNombre !== '') {
        $where[] = "(CONCAT(nombre, ' ', apellido) LIKE :nombre OR username LIKE :nombre_user)";
        $params[':nombre'] = "%$filtroNombre%";
        $params[':nombre_user'] = "%$filtroNombre%";
    }
    
    if ($filtroRol !== '') {
        $where[] = "role = :rol";
        $params[':rol'] = $filtroRol;
    }
    
    if ($filtroEstado !== '') {
        $where[] = "activo = :activo";
        $params[':activo'] = $filtroEstado;
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
    $sql .= " ORDER BY creado_en DESC LIMIT :offset, :por_pagina";
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
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al listar usuarios: " . $e->getMessage());
    $_SESSION['error'] = "Ocurrió un error al recuperar los usuarios. Por favor intente más tarde.";
    $usuarios = []; // Asegurar que $usuarios es un array
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios del sistema - sistema bancario</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/registros.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4">Usuarios del sistema</h2>
        
        <!-- Mensajes flotantes -->
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
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </h3>
            </div>
            <form method="get" class="filtros-grid">
                <div class="form-group">
                    <label for="nombre" class="form-label">Nombre o usuario</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" 
                           value="<?php echo htmlspecialchars($filtroNombre); ?>" 
                           placeholder="Nombre, apellido o nombre de usuario">
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
                    <label for="activo" class="form-label">Estado</label>
                    <select class="form-select" id="activo" name="activo">
                        <option value="">Todos</option>
                        <option value="1" <?= $filtroEstado === '1' ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= $filtroEstado === '0' ? 'selected' : '' ?>>Inactivo</option>
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
        
        <!-- Tabla de usuarios -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre de usuario</th>
                            <th>Email</th>
                            <th>Nombre completo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado en</th>
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
                                    <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <?php 
                                            echo htmlspecialchars(
                                                trim($usuario['nombre'] . ' ' . $usuario['apellido']) !== '' ? 
                                                $usuario['nombre'] . ' ' . $usuario['apellido'] : 
                                                'Sin nombre'
                                            ); 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($usuario['role']) {
                                                    case 'admin': echo 'bg-danger'; break;
                                                    case 'gerente': echo 'bg-primary'; break;
                                                    case 'cajero': echo 'bg-info'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                            ?>">
                                            <?php 
                                                switch($usuario['role']) {
                                                    case 'admin': echo 'Administrador'; break;
                                                    case 'gerente': echo 'Gerente'; break;
                                                    case 'cajero': echo 'Cajero'; break;
                                                    default: echo 'Cliente';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $usuario['activo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usuario['creado_en'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="editar.php?id=<?php echo urlencode($usuario['id']); ?>" 
                                               class="btn btn-sm btn-warning btn-action"
                                               title="Editar usuario">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button class="btn btn-sm btn-<?php echo $usuario['activo'] ? 'danger' : 'success'; ?> btn-action btn-cambiar-estado" 
                                                    data-id="<?php echo htmlspecialchars($usuario['id']); ?>" 
                                                    data-activo="<?php echo $usuario['activo']; ?>"
                                                    title="<?php echo $usuario['activo'] ? 'Desactivar' : 'Activar'; ?> usuario">
                                                <i class="bi <?php echo $usuario['activo'] ? 'bi-person-x' : 'bi-person-check'; ?>"></i>
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
                    Mostrando <?php echo count($usuarios); ?> de <?php echo $totalRegistros; ?> usuarios
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
    </main>

    <!-- Bootstrap JS Bundle con Popper -->
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para cambiar estado -->
    <script>
    document.querySelectorAll('.btn-cambiar-estado').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const activo = this.getAttribute('data-activo');
            const accion = activo === '1' ? 'desactivar' : 'activar';
            
            if (confirm(`¿Está seguro que desea ${accion} este usuario?`)) {
                window.location.href = `cambiar_estado.php?id=${encodeURIComponent(id)}&accion=${accion}`;
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

    // Permitir cerrar al hacer clic
    document.addEventListener('click', function(e) {
        if (e.target.closest('.mensaje-flotante')) {
            const mensaje = e.target.closest('.mensaje-flotante');
            mensaje.classList.add('cerrando');
            setTimeout(() => mensaje.remove(), 300);
        }
    });
    </script>
</body>
</html>