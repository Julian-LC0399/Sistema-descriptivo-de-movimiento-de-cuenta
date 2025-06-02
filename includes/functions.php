<?php
require_once __DIR__ . '/database.php';

/**
 * Funciones de utilidad general
 */

/**
 * Sanitiza los datos de entrada para prevenir inyecciones XSS
 * @param string $data Datos a sanitizar
 * @return string Datos sanitizados
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica si el usuario está autenticado
 * Redirige a la página de login si no hay sesión activa
 */
function check_auth() {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si el usuario está logueado
    if (!isset($_SESSION['user_id'])) {
        // Redirigir al login
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Verifica si el usuario tiene un permiso específico
 * @param string $permission El permiso a verificar (ej: 'cliente')
 * @return bool True si tiene el permiso, False si no
 */
function has_permission($permission) {
    // Verificar si la sesión está activa y tiene permisos
    if (isset($_SESSION['permissions'])) {
        // Si los permisos son un array, verificar si contiene el permiso
        if (is_array($_SESSION['permissions'])) {
            return in_array($permission, $_SESSION['permissions']);
        }
        // Si es un string, verificar si coincide exactamente
        elseif (is_string($_SESSION['permissions'])) {
            return $_SESSION['permissions'] === $permission;
        }
    }
    
    return false;
}


/**
 * Formatea un número de cuenta para mostrarlo con separaciones
 * @param int|string $numero Número de cuenta
 * @return string Número formateado
 */
function formatear_numero_cuenta($numero) {
    return chunk_split($numero, 4, ' ');
}

/**
 * Formatea un monto monetario con su símbolo correspondiente
 * @param float $monto Cantidad a formatear
 * @param string $moneda Código de moneda (VES, USD)
 * @return string Monto formateado
 */
function formatear_moneda($monto, $moneda) {
    $simbolos = [
        'VES' => 'Bs.', 
        'USD' => '$',
        'EUR' => '€'
    ];
    $simbolo = $simbolos[$moneda] ?? $moneda;
    return $simbolo . ' ' . number_format((float)$monto, 2, ',', '.');
}

/**
 * Formatea una fecha a formato dd/mm/YYYY
 * @param string $fecha Fecha en formato válido para strtotime
 * @return string Fecha formateada
 */
function formatear_fecha($fecha) {
    if (empty($fecha)) return 'N/A';
    return date('d/m/Y', strtotime($fecha));
}

/**
 * Devuelve el nombre del mes en español
 * @param int $mes Número del mes (1-12)
 * @return string Nombre del mes
 */
function nombre_mes($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$mes] ?? 'Mes inválido';
}

/**
 * Obtiene el nombre de un producto bancario por su código
 * @param int $codigo Código del producto
 * @return string Nombre del producto
 */
function obtener_nombre_producto($codigo) {
    $productos = [
        10 => 'Cuenta Corriente',
        20 => 'Cuenta de Ahorros',
        30 => 'Cuenta Nómina',
        40 => 'Depósito a Plazo Fijo'
    ];
    return $productos[$codigo] ?? 'Producto ' . $codigo;
}

/**
 * Funciones de operaciones CRUD para clientes
 */

/**
 * Obtiene todos los clientes ordenados por nombre
 * @return array Lista de clientes
 */
function getClientes() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM cumst ORDER BY cusna1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene un cliente por su ID
 * @param int $id ID del cliente
 * @return array|null Datos del cliente o null si no existe
 */
function getClienteById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM cumst WHERE cuscun = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crea un nuevo cliente
 * @param array $data Datos del cliente
 * @return bool True si se creó correctamente
 */
function crearCliente($data) {
    global $pdo;
    try {
        $sql = "INSERT INTO cumst (cusna1, cusna2, cusna3, cusna4, cuscty, cuseml, cusemc, cusphn, cuspxt, cusfax, cusidc, cusbds, cussts, cuslau, cuslut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['cusna1'], $data['cusna2'], $data['cusna3'], $data['cusna4'],
            $data['cuscty'], $data['cuseml'], $data['cusemc'], $data['cusphn'],
            $data['cuspxt'], $data['cusfax'], $data['cusidc'], $data['cusbds'],
            $data['cussts'], $_SESSION['username']
        ]);
    } catch (PDOException $e) {
        error_log("Error al crear cliente: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualiza un cliente existente
 * @param int $id ID del cliente
 * @param array $data Datos actualizados del cliente
 * @return bool True si se actualizó correctamente
 */
function actualizarCliente($id, $data) {
    global $pdo;
    try {
        $sql = "UPDATE cumst SET 
                cusna1 = ?, cusna2 = ?, cusna3 = ?, cusna4 = ?, 
                cuscty = ?, cuseml = ?, cusemc = ?, cusphn = ?, 
                cuspxt = ?, cusfax = ?, cusidc = ?, cusbds = ?, 
                cussts = ?, cuslau = ?, cuslut = NOW() 
                WHERE cuscun = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['cusna1'], $data['cusna2'], $data['cusna3'], $data['cusna4'],
            $data['cuscty'], $data['cuseml'], $data['cusemc'], $data['cusphn'],
            $data['cuspxt'], $data['cusfax'], $data['cusidc'], $data['cusbds'],
            $data['cussts'], $_SESSION['username'], $id
        ]);
    } catch (PDOException $e) {
        error_log("Error al actualizar cliente: " . $e->getMessage());
        return false;
    }
}

/**
 * Funciones de operaciones CRUD para cuentas
 */

/**
 * Obtiene las cuentas asociadas a un cliente
 * @param int $cuscun ID del cliente
 * @return array Lista de cuentas del cliente
 */
function getCuentasByCliente($cuscun) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM acmst WHERE acmcun = ? ORDER BY acmacc");
    $stmt->execute([$cuscun]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todas las cuentas bancarias
 * @return array Lista de todas las cuentas
 */
function getAllCuentas() {
    global $pdo;
    $stmt = $pdo->query("SELECT a.*, c.cusna1 as nombre_cliente FROM acmst a JOIN cumst c ON a.acmcun = c.cuscun ORDER BY a.acmacc");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene una cuenta por su número
 * @param int $acmacc Número de cuenta
 * @return array|null Datos de la cuenta o null si no existe
 */
function getCuentaById($acmacc) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT a.*, c.cusna1 as nombre_cliente FROM acmst a JOIN cumst c ON a.acmcun = c.cuscun WHERE a.acmacc = ?");
    $stmt->execute([$acmacc]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las cuentas activas de un cliente
 * @param int $cliente_id ID del cliente
 * @return array Lista de cuentas [acmacc, acmccy, acmprd]
 */
function obtener_cuentas_cliente($cliente_id) {
    global $pdo;
    $query = "SELECT acmacc, acmccy, acmprd FROM acmst WHERE acmcun = ? AND acmsta = 'A'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$cliente_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Crea una nueva cuenta bancaria
 * @param array $data Datos de la cuenta
 * @return bool True si se creó correctamente
 */
function crearCuenta($data) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO acmst (
                acmacc, acmcun, acmbrn, acmccy, acmprd, acmtyp, acmcls, 
                acmlsb, acmbal, acmavl, acmhld, acmglc, acmlsm, acmlsd, 
                acmlsy, acmrdm, acmrdd, acmrdy, acmsta, acmopn, acmprn, 
                acmrte, acmiva, acmrmk, acmlau, acmlut
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, NOW()
            )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['acmacc'], $data['acmcun'], $data['acmbrn'], $data['acmccy'],
            $data['acmprd'], $data['acmtyp'], $data['acmcls'], $data['acmlsb'],
            $data['acmbal'], $data['acmavl'], $data['acmhld'], $data['acmglc'],
            $data['acmlsm'], $data['acmlsd'], $data['acmlsy'], $data['acmrdm'],
            $data['acmrdd'], $data['acmrdy'], $data['acmsta'], $data['acmopn'],
            $data['acmprn'], $data['acmrte'], $data['acmiva'], $data['acmrmk'],
            $_SESSION['username']
        ]);
        
        // Crear referencia oficial si existe
        if (!empty($data['acrrac'])) {
            $sql = "INSERT INTO acref (acrnac, acrcun, acrrac, acrtyp, acrsts) 
                    VALUES (?, ?, ?, 'O', 'A')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['acmacc'], $data['acmcun'], $data['acrrac']]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al crear cuenta: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualiza una cuenta bancaria existente
 * @param int $acmacc Número de cuenta
 * @param array $data Datos actualizados de la cuenta
 * @return bool True si se actualizó correctamente
 */
function actualizarCuenta($acmacc, $data) {
    global $pdo;
    try {
        $sql = "UPDATE acmst SET 
                acmcun = ?, acmbrn = ?, acmccy = ?, acmprd = ?, 
                acmtyp = ?, acmcls = ?, acmlsb = ?, acmbal = ?, 
                acmavl = ?, acmhld = ?, acmglc = ?, acmlsm = ?, 
                acmlsd = ?, acmlsy = ?, acmrdm = ?, acmrdd = ?, 
                acmrdy = ?, acmsta = ?, acmopn = ?, acmprn = ?, 
                acmrte = ?, acmiva = ?, acmrmk = ?, acmlau = ?, 
                acmlut = NOW() 
                WHERE acmacc = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['acmcun'], $data['acmbrn'], $data['acmccy'], $data['acmprd'],
            $data['acmtyp'], $data['acmcls'], $data['acmlsb'], $data['acmbal'],
            $data['acmavl'], $data['acmhld'], $data['acmglc'], $data['acmlsm'],
            $data['acmlsd'], $data['acmlsy'], $data['acmrdm'], $data['acmrdd'],
            $data['acmrdy'], $data['acmsta'], $data['acmopn'], $data['acmprn'],
            $data['acmrte'], $data['acmiva'], $data['acmrmk'], $_SESSION['username'],
            $acmacc
        ]);
    } catch (PDOException $e) {
        error_log("Error al actualizar cuenta: " . $e->getMessage());
        return false;
    }
}

/**
 * Cambia el estado de una cuenta y registra el cambio en el histórico
 * @param int $acmacc Número de cuenta
 * @param string $nuevoEstado Nuevo estado (A/I)
 * @param string $razon Razón del cambio
 * @return bool True si se realizó el cambio correctamente
 */
function cambiarEstadoCuenta($acmacc, $nuevoEstado, $razon = '') {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Actualizar estado en la cuenta
        $sql = "UPDATE acmst SET acmsta = ?, acmlau = ?, acmlut = NOW() WHERE acmacc = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevoEstado, $_SESSION['username'], $acmacc]);
        
        // Obtener siguiente secuencia para el histórico
        $stmt = $pdo->prepare("SELECT MAX(hstseq) as max_seq FROM achst WHERE hstacc = ? AND hstdat = CURDATE()");
        $stmt->execute([$acmacc]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_seq = ($result['max_seq'] ?? 0) + 1;
        
        // Registrar en histórico
        $sql = "INSERT INTO achst (hstacc, hstdat, hstseq, hststa, hstrsn, hstusr) 
                VALUES (?, CURDATE(), ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$acmacc, $next_seq, $nuevoEstado, $razon, $_SESSION['username']]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al cambiar estado de cuenta: " . $e->getMessage());
        return false;
    }
}

/**
 * Funciones de operaciones CRUD para transacciones
 */

/**
 * Obtiene las transacciones de una cuenta
 * @param int $acmacc Número de cuenta
 * @param int $limit Límite de transacciones a mostrar (opcional)
 * @return array Lista de transacciones
 */
function getTransaccionesByCuenta($acmacc, $limit = null) {
    global $pdo;
    $sql = "SELECT * FROM actrd WHERE trdacc = ? ORDER BY trddat DESC, trdseq DESC";
    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$acmacc]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Crea una nueva transacción
 * @param array $data Datos de la transacción
 * @return bool True si se creó correctamente
 */
function crearTransaccion($data) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Insertar transacción
        $sql = "INSERT INTO actrd (
                trdacc, trddat, trdseq, trdamt, trdbal, trdmd, 
                trddsc, trdref, trdusr, trdbnk, trdoff, trdtrn
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['trdacc'], $data['trddat'], $data['trdseq'], $data['trdamt'],
            $data['trdbal'], $data['trdmd'], $data['trddsc'], $data['trdref'],
            $_SESSION['username'], $data['trdbnk'], $data['trdoff'], $data['trdtrn']
        ]);
        
        // Actualizar saldos en la cuenta
        $sql = "UPDATE acmst SET 
                acmlsb = acmbal,
                acmbal = ?,
                acmavl = ?,
                acmlsm = MONTH(NOW()),
                acmlsd = DAY(NOW()),
                acmlsy = YEAR(NOW()),
                acmlau = ?,
                acmlut = NOW()
                WHERE acmacc = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['trdbal'], $data['trdbal'], $_SESSION['username'], $data['trdacc']
        ]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al crear transacción: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el próximo número de secuencia para una transacción en una fecha dada
 * @param int $acmacc Número de cuenta
 * @param string $fecha Fecha de la transacción (YYYY-MM-DD)
 * @return int Número de secuencia siguiente
 */
function getNextSeqTransaccion($acmacc, $fecha) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT MAX(trdseq) as max_seq FROM actrd WHERE trdacc = ? AND trddat = ?");
    $stmt->execute([$acmacc, $fecha]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['max_seq'] ?? 0) + 1;
}

/**
 * Funciones de consultas adicionales
 */

/**
 * Obtiene las referencias externas de una cuenta
 * @param int $acmacc Número de cuenta
 * @return array Lista de referencias
 */
function getReferenciasByCuenta($acmacc) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM acref WHERE acrnac = ?");
    $stmt->execute([$acmacc]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene datos adicionales de una cuenta
 * @param int $acmacc Número de cuenta
 * @return array Lista de datos adicionales
 */
function getDatosAdicionalesByCuenta($acmacc) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM acadd WHERE addacc = ?");
    $stmt->execute([$acmacc]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el histórico de estados de una cuenta
 * @param int $acmacc Número de cuenta
 * @return array Lista de cambios de estado
 */
function getHistoricoEstados($acmacc) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM achst WHERE hstacc = ? ORDER BY hstdat DESC, hstseq DESC");
    $stmt->execute([$acmacc]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Busca clientes por nombre, email o teléfono
 * @param string $query Término de búsqueda
 * @return array Lista de clientes que coinciden
 */
function buscarClientes($query) {
    global $pdo;
    $search = "%$query%";
    $stmt = $pdo->prepare("SELECT * FROM cumst 
                          WHERE cusna1 LIKE ? OR cuseml LIKE ? OR cusphn LIKE ? 
                          ORDER BY cusna1");
    $stmt->execute([$search, $search, $search]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Funciones de reportes y estadísticas
 */

/**
 * Obtiene estadísticas de cuentas por tipo
 * @return array Estadísticas de cuentas agrupadas por tipo
 */
function getEstadisticasCuentas() {
    global $pdo;
    $stmt = $pdo->query("SELECT acmtyp as tipo, COUNT(*) as cantidad, 
                         SUM(acmbal) as saldo_total 
                         FROM acmst 
                         GROUP BY acmtyp");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el saldo total de todas las cuentas
 * @return float Saldo total
 */
function getSaldoTotal() {
    global $pdo;
    $stmt = $pdo->query("SELECT SUM(acmbal) as saldo_total FROM acmst");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['saldo_total'] ?? 0;
}

/**
 * Obtiene el movimiento diario total (suma de transacciones del día)
 * @return array Débitos y créditos del día
 */
function getMovimientoDiario() {
    global $pdo;
    $stmt = $pdo->query("SELECT 
                         SUM(CASE WHEN trdmd = 'D' THEN trdamt ELSE 0 END) as debitos,
                         SUM(CASE WHEN trdmd = 'C' THEN trdamt ELSE 0 END) as creditos
                         FROM actrd 
                         WHERE trddat = CURDATE()");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el saldo consolidado de todas las cuentas de un cliente
 * @param int $cliente_id ID del cliente
 * @return array Saldos agrupados por moneda
 */
function getSaldosCliente($cliente_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 
                          acmccy AS moneda,
                          SUM(acmbal) AS saldo_total,
                          SUM(acmavl) AS saldo_disponible
                          FROM acmst 
                          WHERE acmcun = ? AND acmsta = 'A'
                          GROUP BY acmccy");
    $stmt->execute([$cliente_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las transacciones de un cliente filtradas por mes y año
 * @param int $cliente_id ID del cliente
 * @param int|null $mes Mes a filtrar (opcional)
 * @param int|null $anio Año a filtrar (opcional)
 * @param int|null $cuenta_id ID de cuenta específica (opcional)
 * @return array Transacciones filtradas
 */
function getTransaccionesPorMes($cliente_id, $mes = null, $anio = null, $cuenta_id = null) {
    global $pdo;
    
    // Valores por defecto
    $mes = $mes ?? date('n');
    $anio = $anio ?? date('Y');
    
    $sql = "SELECT 
            t.trddat AS fecha,
            t.trdmd AS tipo,
            t.trdamt AS monto,
            t.trdbal AS saldo,
            t.trddsc AS descripcion,
            t.trdref AS referencia,
            a.acmacc AS cuenta,
            a.acmccy AS moneda
            FROM actrd t
            JOIN acmst a ON t.trdacc = a.acmacc
            WHERE a.acmcun = ?
            AND MONTH(t.trddat) = ?
            AND YEAR(t.trddat) = ?";
    
    $params = [$cliente_id, $mes, $anio];
    
    if ($cuenta_id) {
        $sql .= " AND t.trdacc = ?";
        $params[] = $cuenta_id;
    }
    
    $sql .= " ORDER BY t.trddat DESC, t.trdseq DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el resumen mensual de transacciones por tipo (débito/crédito)
 * @param int $cliente_id ID del cliente
 * @param int $mes Mes a consultar
 * @param int $anio Año a consultar
 * @return array Resumen de movimientos
 */
function getResumenMensual($cliente_id, $mes, $anio) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT 
                          a.acmccy AS moneda,
                          t.trdmd AS tipo,
                          COUNT(*) AS cantidad,
                          SUM(t.trdamt) AS total
                          FROM actrd t
                          JOIN acmst a ON t.trdacc = a.acmacc
                          WHERE a.acmcun = ?
                          AND MONTH(t.trddat) = ?
                          AND YEAR(t.trddat) = ?
                          GROUP BY a.acmccy, t.trdmd
                          ORDER BY a.acmccy, t.trdmd");
    $stmt->execute([$cliente_id, $mes, $anio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene los meses con transacciones disponibles para un cliente
 * @param int $cliente_id ID del cliente
 * @return array Meses y años con transacciones
 */
function getMesesConTransacciones($cliente_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT 
                          DISTINCT YEAR(trddat) AS anio,
                          MONTH(trddat) AS mes
                          FROM actrd t
                          JOIN acmst a ON t.trdacc = a.acmacc
                          WHERE a.acmcun = ?
                          ORDER BY anio DESC, mes DESC");
    $stmt->execute([$cliente_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el saldo inicial de una cuenta en un mes específico
 * @param int $cuenta_id ID de la cuenta
 * @param int $mes Mes a consultar
 * @param int $anio Año a consultar
 * @return float Saldo inicial
 */
function getSaldoInicialMes($cuenta_id, $mes, $anio) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT trdbal 
                          FROM actrd 
                          WHERE trdacc = ?
                          AND trddat < ?
                          ORDER BY trddat DESC, trdseq DESC 
                          LIMIT 1");
    $fecha_inicio = "$anio-$mes-01";
    $stmt->execute([$cuenta_id, $fecha_inicio]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['trdbal'] : 0;
}

/**
 * Obtiene el historial de saldos de una cuenta para un rango de fechas
 * @param int $cuenta_id ID de la cuenta
 * @param string $fecha_inicio Fecha de inicio (YYYY-MM-DD)
 * @param string $fecha_fin Fecha de fin (YYYY-MM-DD)
 * @return array Historial de saldos
 */
function getHistorialSaldos($cuenta_id, $fecha_inicio, $fecha_fin) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT 
                          trddat AS fecha,
                          trdbal AS saldo
                          FROM actrd 
                          WHERE trdacc = ?
                          AND trddat BETWEEN ? AND ?
                          ORDER BY trddat ASC, trdseq ASC");
    $stmt->execute([$cuenta_id, $fecha_inicio, $fecha_fin]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el saldo promedio mensual de una cuenta
 * @param int $cuenta_id ID de la cuenta
 * @param int $mes Mes a consultar
 * @param int $anio Año a consultar
 * @return float Saldo promedio
 */
function getSaldoPromedioMensual($cuenta_id, $mes, $anio) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT AVG(trdbal) AS saldo_promedio
                          FROM actrd 
                          WHERE trdacc = ?
                          AND MONTH(trddat) = ?
                          AND YEAR(trddat) = ?");
    $stmt->execute([$cuenta_id, $mes, $anio]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? (float)$result['saldo_promedio'] : 0.0;
}

/**
 * Obtiene el total de transacciones por tipo para un cliente en un período
 * @param int $cliente_id ID del cliente
 * @param string $fecha_inicio Fecha de inicio (YYYY-MM-DD)
 * @param string $fecha_fin Fecha de fin (YYYY-MM-DD)
 * @return array Totales por tipo de transacción
 */
function getTotalesTransaccionesPeriodo($cliente_id, $fecha_inicio, $fecha_fin) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT 
                          a.acmccy AS moneda,
                          t.trdmd AS tipo,
                          COUNT(*) AS cantidad,
                          SUM(t.trdamt) AS total
                          FROM actrd t
                          JOIN acmst a ON t.trdacc = a.acmacc
                          WHERE a.acmcun = ?
                          AND t.trddat BETWEEN ? AND ?
                          GROUP BY a.acmccy, t.trdmd
                          ORDER BY a.acmccy, t.trdmd");
    $stmt->execute([$cliente_id, $fecha_inicio, $fecha_fin]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las cuentas con mayor movimiento en un período
 * @param string $fecha_inicio Fecha de inicio (YYYY-MM-DD)
 * @param string $fecha_fin Fecha de fin (YYYY-MM-DD)
 * @param int $limit Límite de resultados
 * @return array Cuentas con mayor movimiento
 */
function getCuentasMayorMovimiento($fecha_inicio, $fecha_fin, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT 
                          a.acmacc AS cuenta,
                          c.cusna1 AS cliente,
                          COUNT(t.trdacc) AS transacciones,
                          SUM(CASE WHEN t.trdmd = 'D' THEN t.trdamt ELSE 0 END) AS debitos,
                          SUM(CASE WHEN t.trdmd = 'C' THEN t.trdamt ELSE 0 END) AS creditos
                          FROM actrd t
                          JOIN acmst a ON t.trdacc = a.acmacc
                          JOIN cumst c ON a.acmcun = c.cuscun
                          WHERE t.trddat BETWEEN ? AND ?
                          GROUP BY a.acmacc, c.cusna1
                          ORDER BY transacciones DESC
                          LIMIT ?");
    $stmt->execute([$fecha_inicio, $fecha_fin, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>