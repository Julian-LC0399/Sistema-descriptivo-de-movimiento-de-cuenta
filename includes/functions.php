<?php
require_once 'includes/database.php';

/**
 * Sanitiza los datos de entrada para prevenir inyecciones XSS
 * @param string $data Datos a sanitizar
 * @return string Datos sanitizados
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

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
            $data['trdbal'], $data['trdavl'], $_SESSION['username'], $data['trdacc']
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
?>