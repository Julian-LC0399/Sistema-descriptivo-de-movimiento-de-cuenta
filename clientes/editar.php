<?php
// clientes/editar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
requireLogin();

$tituloPagina = "Editar Cliente";

// Verificar si se recibió un ID válido
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No se especificó un cliente para editar";
    header("Location: lista.php");
    exit();
}

$idCliente = (int)$_GET['id'];

// Obtener datos del cliente
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM cumst WHERE cuscun = ?");
$stmt->execute([$idCliente]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $_SESSION['error'] = "Cliente no encontrado";
    header("Location: lista.php");
    exit();
}

// Inicializar variables con los datos del cliente
$valoresFormulario = $cliente;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obtener y sanitizar datos del formulario
        $valoresFormulario = [
            'cuscun' => $idCliente,
            'cusidn' => trim($_POST['cusidn'] ?? ''),
            'cusna1' => trim($_POST['cusna1'] ?? ''),
            'cusna2' => trim($_POST['cusna2'] ?? ''),
            'cusln1' => trim($_POST['cusln1'] ?? ''),
            'cusln2' => trim($_POST['cusln2'] ?? ''),
            'cusemp' => trim($_POST['cusemp'] ?? ''),
            'cusjob' => trim($_POST['cusjob'] ?? ''),
            'cusidp' => trim($_POST['cusidp'] ?? ''),
            'cusdir1' => trim($_POST['cusdir1'] ?? ''),
            'cusdir2' => trim($_POST['cusdir2'] ?? ''),
            'cusdir3' => trim($_POST['cusdir3'] ?? ''),
            'cuscty' => trim($_POST['cuscty'] ?? ''),
            'cuseml' => trim($_POST['cuseml'] ?? ''),
            'cusemw' => trim($_POST['cusemw'] ?? ''),
            'cusphn' => trim($_POST['cusphn'] ?? ''),
            'cusphh' => trim($_POST['cusphh'] ?? ''),
            'cusphw' => trim($_POST['cusphw'] ?? ''),
            'cuspxt' => trim($_POST['cuspxt'] ?? ''),
            'cusfax' => trim($_POST['cusfax'] ?? ''),
            'cusidc' => trim($_POST['cusidc'] ?? 'V'),
            'cusbds' => trim($_POST['cusbds'] ?? ''),
            'cusgen' => trim($_POST['cusgen'] ?? ''),
            'cusmar' => trim($_POST['cusmar'] ?? ''),
            'cusnac' => trim($_POST['cusnac'] ?? ''),
            'cusweb' => trim($_POST['cusweb'] ?? ''),
            'cussts' => trim($_POST['cussts'] ?? 'A') // Campo estado ahora editable
        ];

        // Validaciones (igual que en crear.php)
        if (empty($valoresFormulario['cusidn'])) {
            $errores['cusidn'] = "La cédula/RIF es obligatoria";
        }

        if (empty($valoresFormulario['cusna1'])) {
            $errores['cusna1'] = "El primer nombre es obligatorio";
        }

        if (empty($valoresFormulario['cusln1'])) {
            $errores['cusln1'] = "El primer apellido es obligatorio";
        }

        if (empty($valoresFormulario['cusdir1'])) {
            $errores['cusdir1'] = "La dirección línea 1 es obligatoria";
        }

        if (empty($valoresFormulario['cuscty'])) {
            $errores['cuscty'] = "La ciudad es obligatoria";
        }

        if (!empty($valoresFormulario['cuseml']) && !filter_var($valoresFormulario['cuseml'], FILTER_VALIDATE_EMAIL)) {
            $errores['cuseml'] = "El email personal no tiene un formato válido";
        }

        if (!empty($valoresFormulario['cusemw']) && !filter_var($valoresFormulario['cusemw'], FILTER_VALIDATE_EMAIL)) {
            $errores['cusemw'] = "El email corporativo no tiene un formato válido";
        }

        // Si no hay errores, proceder con la actualización
        if (empty($errores)) {
            $stmt = $pdo->prepare("
                UPDATE cumst SET
                    cusidn = :cusidn,
                    cusna1 = :cusna1,
                    cusna2 = :cusna2,
                    cusln1 = :cusln1,
                    cusln2 = :cusln2,
                    cusemp = :cusemp,
                    cusjob = :cusjob,
                    cusidp = :cusidp,
                    cusdir1 = :cusdir1,
                    cusdir2 = :cusdir2,
                    cusdir3 = :cusdir3,
                    cuscty = :cuscty,
                    cuseml = :cuseml,
                    cusemw = :cusemw,
                    cusphn = :cusphn,
                    cusphh = :cusphh,
                    cusphw = :cusphw,
                    cuspxt = :cuspxt,
                    cusfax = :cusfax,
                    cusidc = :cusidc,
                    cusbds = :cusbds,
                    cussts = :cussts,
                    cusgen = :cusgen,
                    cusmar = :cusmar,
                    cusnac = :cusnac,
                    cusweb = :cusweb,
                    cuslau = :usuario,
                    cuslut = NOW()
                WHERE cuscun = :cuscun
            ");
            
            $params = $valoresFormulario;
            $params[':usuario'] = $_SESSION['username'] ?? 'SISTEMA';
            
            $stmt->execute($params);
            
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => "Cliente actualizado exitosamente"
            ];
            header("Location: lista.php");
            exit();
        }
        
    } catch (PDOException $e) {
        $errores['general'] = "Error al actualizar cliente: " . $e->getMessage();
    } catch (Exception $e) {
        $errores['general'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> - Banco Caroni</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="<?= BASE_URL ?>assets/css/registros.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="container mt-4">
        <h2 class="mb-4"><?= htmlspecialchars($tituloPagina) ?></h2>
        
        <?php if (!empty($errores['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($errores['general']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" class="form-container">
            <!-- Sección Información de Identificación -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información de Identificación</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusidn" class="form-label required-field">Cédula</label>
                            <input type="text" class="form-control <?= isset($errores['cusidn']) ? 'is-invalid' : '' ?>" 
                                   id="cusidn" name="cusidn" value="<?= htmlspecialchars($valoresFormulario['cusidn']) ?>" required>
                            <?php if (isset($errores['cusidn'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['cusidn']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusidc" class="form-label">Tipo de Identificación</label>
                            <select class="form-select" id="cusidc" name="cusidc">
                                <option value="V" <?= $valoresFormulario['cusidc'] === 'V' ? 'selected' : '' ?>>V - Venezolano</option>
                                <option value="E" <?= $valoresFormulario['cusidc'] === 'E' ? 'selected' : '' ?>>E - Extranjero</option>
                                <option value="J" <?= $valoresFormulario['cusidc'] === 'J' ? 'selected' : '' ?>>J - Jurídico</option>
                                <option value="P" <?= $valoresFormulario['cusidc'] === 'P' ? 'selected' : '' ?>>P - Pasaporte</option>
                                <option value="G" <?= $valoresFormulario['cusidc'] === 'G' ? 'selected' : '' ?>>G - Gobierno</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusidp" class="form-label">Número de Pasaporte</label>
                            <input type="text" class="form-control" id="cusidp" name="cusidp" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusidp']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusnac" class="form-label">Nacionalidad</label>
                            <input type="text" class="form-control" id="cusnac" name="cusnac" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusnac']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Información Personal -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información Personal</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusna1" class="form-label required-field">Primer Nombre</label>
                            <input type="text" class="form-control <?= isset($errores['cusna1']) ? 'is-invalid' : '' ?>" 
                                   id="cusna1" name="cusna1" value="<?= htmlspecialchars($valoresFormulario['cusna1']) ?>" required>
                            <?php if (isset($errores['cusna1'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['cusna1']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusna2" class="form-label">Segundo Nombre</label>
                            <input type="text" class="form-control" id="cusna2" name="cusna2" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusna2']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusln1" class="form-label required-field">Primer Apellido</label>
                            <input type="text" class="form-control <?= isset($errores['cusln1']) ? 'is-invalid' : '' ?>" 
                                   id="cusln1" name="cusln1" value="<?= htmlspecialchars($valoresFormulario['cusln1']) ?>" required>
                            <?php if (isset($errores['cusln1'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['cusln1']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusln2" class="form-label">Segundo Apellido</label>
                            <input type="text" class="form-control" id="cusln2" name="cusln2" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusln2']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="cusbds" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="cusbds" name="cusbds" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusbds']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="cusgen" class="form-label">Género</label>
                            <select class="form-select" id="cusgen" name="cusgen">
                                <option value="">Seleccione...</option>
                                <option value="M" <?= $valoresFormulario['cusgen'] === 'M' ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= $valoresFormulario['cusgen'] === 'F' ? 'selected' : '' ?>>Femenino</option>
                                <option value="O" <?= $valoresFormulario['cusgen'] === 'O' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="cusmar" class="form-label">Estado Civil</label>
                            <select class="form-select" id="cusmar" name="cusmar">
                                <option value="">Seleccione...</option>
                                <option value="S" <?= $valoresFormulario['cusmar'] === 'S' ? 'selected' : '' ?>>Soltero/a</option>
                                <option value="C" <?= $valoresFormulario['cusmar'] === 'C' ? 'selected' : '' ?>>Casado/a</option>
                                <option value="D" <?= $valoresFormulario['cusmar'] === 'D' ? 'selected' : '' ?>>Divorciado/a</option>
                                <option value="V" <?= $valoresFormulario['cusmar'] === 'V' ? 'selected' : '' ?>>Viudo/a</option>
                                <option value="U" <?= $valoresFormulario['cusmar'] === 'U' ? 'selected' : '' ?>>Unión Libre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="cusweb" class="form-label">Sitio Web</label>
                            <input type="url" class="form-control" id="cusweb" name="cusweb" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusweb']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Información Laboral -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información Laboral</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusemp" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="cusemp" name="cusemp" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusemp']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusjob" class="form-label">Cargo/Puesto</label>
                            <input type="text" class="form-control" id="cusjob" name="cusjob" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusjob']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusemw" class="form-label">Email Corporativo</label>
                            <input type="email" class="form-control <?= isset($errores['cusemw']) ? 'is-invalid' : '' ?>" 
                                   id="cusemw" name="cusemw" value="<?= htmlspecialchars($valoresFormulario['cusemw']) ?>">
                            <?php if (isset($errores['cusemw'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['cusemw']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="cusphw" class="form-label">Teléfono Trabajo</label>
                            <input type="tel" class="form-control" id="cusphw" name="cusphw" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusphw']) ?>">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="cuspxt" class="form-label">Extensión</label>
                            <input type="text" class="form-control" id="cuspxt" name="cuspxt" 
                                   value="<?= htmlspecialchars($valoresFormulario['cuspxt']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Dirección -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Dirección</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="cusdir1" class="form-label required-field">Dirección Línea 1</label>
                        <input type="text" class="form-control <?= isset($errores['cusdir1']) ? 'is-invalid' : '' ?>" 
                               id="cusdir1" name="cusdir1" value="<?= htmlspecialchars($valoresFormulario['cusdir1']) ?>" required>
                        <?php if (isset($errores['cusdir1'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['cusdir1']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cusdir2" class="form-label">Dirección Línea 2</label>
                        <input type="text" class="form-control" id="cusdir2" name="cusdir2" 
                               value="<?= htmlspecialchars($valoresFormulario['cusdir2']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="cusdir3" class="form-label">Dirección Línea 3</label>
                        <input type="text" class="form-control" id="cusdir3" name="cusdir3" 
                               value="<?= htmlspecialchars($valoresFormulario['cusdir3']) ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cuscty" class="form-label required-field">Ciudad</label>
                            <input type="text" class="form-control <?= isset($errores['cuscty']) ? 'is-invalid' : '' ?>" 
                                   id="cuscty" name="cuscty" value="<?= htmlspecialchars($valoresFormulario['cuscty']) ?>" required>
                            <?php if (isset($errores['cuscty'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['cuscty']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusfax" class="form-label">Fax</label>
                            <input type="tel" class="form-control" id="cusfax" name="cusfax" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusfax']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Contacto -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Información de Contacto</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cuseml" class="form-label">Email Personal</label>
                            <input type="email" class="form-control <?= isset($errores['cuseml']) ? 'is-invalid' : '' ?>" 
                                   id="cuseml" name="cuseml" value="<?= htmlspecialchars($valoresFormulario['cuseml']) ?>">
                            <?php if (isset($errores['cuseml'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['cuseml']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cusphn" class="form-label">Teléfono Móvil</label>
                            <input type="tel" class="form-control" id="cusphn" name="cusphn" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusphn']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cusphh" class="form-label">Teléfono Habitación</label>
                            <input type="tel" class="form-control" id="cusphh" name="cusphh" 
                                   value="<?= htmlspecialchars($valoresFormulario['cusphh']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cuscun" class="form-label">ID Cliente</label>
                            <input type="text" class="form-control" id="cuscun" name="cuscun" 
                                   value="<?= htmlspecialchars($valoresFormulario['cuscun']) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Estado (EDITABLE) -->
            <div class="card mb-4 form-section">
                <div class="card-header">
                    <h5 class="mb-0">Estado del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cussts" class="form-label">Estado</label>
                            <select class="form-select" id="cussts" name="cussts">
                                <option value="A" <?= $valoresFormulario['cussts'] === 'A' ? 'selected' : '' ?>>Activo</option>
                                <option value="I" <?= $valoresFormulario['cussts'] === 'I' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="lista.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </main>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validar teléfonos (solo números)
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        // Cerrar alertas automáticamente
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);

        // Manejar el tipo de identificación
        document.getElementById('cusidc').addEventListener('change', function() {
            const cusidn = document.getElementById('cusidn');
            if (this.value === 'V') {
                cusidn.placeholder = 'Ej: V12345678';
            } else if (this.value === 'E') {
                cusidn.placeholder = 'Ej: E12345678';
            } else if (this.value === 'J') {
                cusidn.placeholder = 'Ej: J-12345678-9';
            } else if (this.value === 'P') {
                cusidn.placeholder = 'Número de pasaporte';
            } else if (this.value === 'G') {
                cusidn.placeholder = 'Identificación gubernamental';
            }
        });
    </script>
</body>
</html>