<?php
declare(strict_types=1);

/**
 * ALKIRA HU02.3 - Prueba de COMMIT, idempotencia y ROLLBACK.
 * Crea exclusivamente tablas TEMPORALES que ocultan las tablas
 * reales durante esta conexión. No modifica adeudos permanentes.
 *
 * Ruta: backend/pruebas/probarAplicacionRealAislada.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Esta prueba solo puede ejecutarse desde la terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/RecargosMoraServicio.php';

$conexion = null;
$interruptorAnterior = getenv('ALKIRA_RECARGOS_HABILITADOS');

try {
    $configuracion = require dirname(__DIR__) . '/config/baseDatos.local.php';
    if (
        !is_array($configuracion)
        || !in_array(
            $configuracion['servidor'] ?? null,
            ['localhost', '127.0.0.1', '::1'],
            true
        )
        || ($configuracion['baseDatos'] ?? null) !== 'alkira'
    ) {
        throw new RuntimeException('La prueba requiere MySQL local alkira.');
    }

    $conexion = Conexion::obtenerConexion();
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($conexion->query('SELECT DATABASE()')->fetchColumn() !== 'alkira') {
        throw new RuntimeException('Conexión a una base de datos inesperada.');
    }

    $adeudosIniciales = (int) $conexion->query(
        'SELECT COUNT(*) FROM estados_cuenta'
    )->fetchColumn();
    $configuracionesIniciales = (int) $conexion->query(
        'SELECT COUNT(*) FROM recargos_mora_adeudos'
    )->fetchColumn();

    // Estas tablas solo existen en esta sesión y OCULTAN las reales.
    $conexion->exec(<<<'SQL'
        CREATE TEMPORARY TABLE estados_cuenta (
            id_estado_cuenta INT UNSIGNED NOT NULL PRIMARY KEY,
            id_contrato INT UNSIGNED NOT NULL,
            periodo VARCHAR(7) NOT NULL,
            fecha_limite_pago DATE NOT NULL,
            monto_principal DECIMAL(12,2) NOT NULL,
            recargo_mora DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            estado ENUM('pendiente','parcial','pagado','vencido','cancelado') NOT NULL,
            marca_prueba_aislada TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB
        SQL);

    $conexion->exec(<<<'SQL'
        CREATE TEMPORARY TABLE recargos_mora_adeudos (
            id_estado_cuenta INT UNSIGNED NOT NULL PRIMARY KEY,
            tipo_recargo ENUM('fijo','porcentaje') NOT NULL,
            valor_recargo DECIMAL(12,2) NOT NULL,
            monto_aplicado DECIMAL(12,2) NULL,
            fecha_aplicacion DATETIME NULL,
            marca_prueba_aislada TINYINT NOT NULL DEFAULT 1,
            CONSTRAINT chk_prueba_fallo_aislado
                CHECK (id_estado_cuenta <> 900006 OR monto_aplicado IS NULL)
        ) ENGINE=InnoDB
        SQL);

    // No habilitar el servicio si alguna tabla temporal no existe.
    foreach (['estados_cuenta', 'recargos_mora_adeudos'] as $tabla) {
        $columna = $conexion->query(
            "SHOW COLUMNS FROM {$tabla} LIKE 'marca_prueba_aislada'"
        )->fetch(PDO::FETCH_ASSOC);
        if ($columna === false) {
            throw new RuntimeException('No se pudo aislar la tabla ' . $tabla);
        }
    }

    $hoy = new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City'));
    $ayer = $hoy->modify('-1 day')->format('Y-m-d');
    $manana = $hoy->modify('+1 day')->format('Y-m-d');
    $periodo = $hoy->format('Y-m');

    $insertarCuenta = $conexion->prepare(<<<'SQL'
        INSERT INTO estados_cuenta (
            id_estado_cuenta, id_contrato, periodo, fecha_limite_pago,
            monto_principal, monto_pagado, estado
        ) VALUES (?, 2, ?, ?, '800.00', ?, ?)
        SQL);

    $insertarRegla = $conexion->prepare(<<<'SQL'
        INSERT INTO recargos_mora_adeudos (
            id_estado_cuenta, tipo_recargo, valor_recargo
        ) VALUES (?, ?, ?)
        SQL);

    $crearCaso = static function (
        int $idEstadoCuenta,
        string $fechaLimite,
        string $montoPagado,
        string $estado,
        string $tipoRecargo,
        string $valorRecargo
    ) use ($insertarCuenta, $insertarRegla, $periodo): void {
        $insertarCuenta->execute([
            $idEstadoCuenta, $periodo, $fechaLimite, $montoPagado, $estado
        ]);
        $insertarRegla->execute([$idEstadoCuenta, $tipoRecargo, $valorRecargo]);
    };

    $crearCaso(900001, $ayer, '0.00', 'pendiente', 'fijo', '60.00');
    $crearCaso(900002, $ayer, '500.00', 'parcial', 'porcentaje', '10.00');
    $crearCaso(900003, $ayer, '800.00', 'pagado', 'fijo', '60.00');
    $crearCaso(900004, $manana, '0.00', 'pendiente', 'fijo', '60.00');

    if (
        (int) $conexion->query('SELECT COUNT(*) FROM estados_cuenta')->fetchColumn() !== 4
        || (int) $conexion->query('SELECT COUNT(*) FROM recargos_mora_adeudos')->fetchColumn() !== 4
    ) {
        throw new RuntimeException('Las tablas temporales no contienen los casos esperados.');
    }

    $servicio = new RecargosMoraServicio($conexion);
    $simulacion = $servicio->simularRecargos();
    if (
        (int) $simulacion['adeudosEvaluados'] !== 2
        || (string) $simulacion['montoTotalRecargos'] !== '90.00'
    ) {
        throw new RuntimeException('La simulación aislada no calculó $60 + $30.');
    }
    echo "[CORRECTO] Simulación aislada: $60 fijo + $30 porcentual.\n";

    // Se habilita SOLO en este proceso y SOLO después del aislamiento.
    putenv('ALKIRA_RECARGOS_HABILITADOS=1');
    $aplicacion = $servicio->aplicarRecargos();
    if (
        (int) $aplicacion['adeudosEvaluados'] !== 2
        || (string) $aplicacion['montoTotalRecargos'] !== '90.00'
    ) {
        throw new RuntimeException('La aplicación aislada no registró ambos recargos.');
    }

    $consultaSaldos = $conexion->query(<<<'SQL'
        SELECT ec.id_estado_cuenta,
               ec.recargo_mora,
               ec.estado,
               CAST(ec.monto_principal + ec.recargo_mora - ec.monto_pagado
                    AS DECIMAL(12,2)) AS saldo,
               rm.monto_aplicado,
               rm.fecha_aplicacion
        FROM estados_cuenta ec
        INNER JOIN recargos_mora_adeudos rm
            ON rm.id_estado_cuenta = ec.id_estado_cuenta
        ORDER BY ec.id_estado_cuenta
        SQL);

    $filas = [];
    foreach ($consultaSaldos->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $filas[(int) $fila['id_estado_cuenta']] = $fila;
    }

    if (
        $filas[900001]['recargo_mora'] !== '60.00'
        || $filas[900001]['saldo'] !== '860.00'
        || $filas[900001]['monto_aplicado'] !== '60.00'
        || $filas[900001]['fecha_aplicacion'] === null
        || $filas[900002]['recargo_mora'] !== '30.00'
        || $filas[900002]['saldo'] !== '330.00'
        || $filas[900002]['monto_aplicado'] !== '30.00'
        || $filas[900002]['fecha_aplicacion'] === null
        || $filas[900003]['monto_aplicado'] !== null
        || $filas[900004]['monto_aplicado'] !== null
    ) {
        throw new RuntimeException('Los saldos confirmados no coinciden.');
    }
    echo "[CORRECTO] COMMIT aislado: saldos $860 y $330.\n";

    $segundaAplicacion = $servicio->aplicarRecargos();
    if ((int) $segundaAplicacion['adeudosEvaluados'] !== 0) {
        throw new RuntimeException('Se intentó aplicar nuevamente un recargo.');
    }
    echo "[CORRECTO] Segunda aplicación: 0 recargos nuevos.\n";

    // Fallo inyectado en la segunda actualización del lote.
    // El CHECK temporal impide aplicar el recargo a 900006.
    $crearCaso(900005, $ayer, '0.00', 'pendiente', 'fijo', '20.00');
    $crearCaso(900006, $ayer, '0.00', 'pendiente', 'fijo', '20.00');

    $fallaEsperada = false;
    try {
        $servicio->aplicarRecargos();
    } catch (PDOException $error) {
        $fallaEsperada = true;
    }
    if (!$fallaEsperada) {
        throw new RuntimeException('No se produjo el fallo controlado.');
    }

    $consultaReversion = $conexion->query(<<<'SQL'
        SELECT ec.recargo_mora, rm.monto_aplicado, rm.fecha_aplicacion
        FROM estados_cuenta ec
        INNER JOIN recargos_mora_adeudos rm
            ON rm.id_estado_cuenta = ec.id_estado_cuenta
        WHERE ec.id_estado_cuenta IN (900005, 900006)
        ORDER BY ec.id_estado_cuenta
        SQL);
    $filasRevertidas = $consultaReversion->fetchAll(PDO::FETCH_ASSOC);
    if (count($filasRevertidas) !== 2) {
        throw new RuntimeException('No se encontraron los dos casos de fallo.');
    }
    foreach ($filasRevertidas as $fila) {
        if (
            $fila['recargo_mora'] !== '0.00'
            || $fila['monto_aplicado'] !== null
            || $fila['fecha_aplicacion'] !== null
        ) {
            throw new RuntimeException('El lote fallido no se revirtió completamente.');
        }
    }
    echo "[CORRECTO] Error controlado: ROLLBACK de todo el lote.\n";

} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR HU02.3: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    // Restablecer la variable de entorno de este proceso.
    if ($interruptorAnterior === false) {
        putenv('ALKIRA_RECARGOS_HABILITADOS');
    } else {
        putenv('ALKIRA_RECARGOS_HABILITADOS=' . $interruptorAnterior);
    }
    // DROP TEMPORARY TABLE nunca elimina las tablas permanentes.
    if ($conexion instanceof PDO) {
        $conexion->exec('DROP TEMPORARY TABLE IF EXISTS recargos_mora_adeudos');
        $conexion->exec('DROP TEMPORARY TABLE IF EXISTS estados_cuenta');
    }
}

$adeudosFinales = (int) $conexion->query(
    'SELECT COUNT(*) FROM estados_cuenta'
)->fetchColumn();
$configuracionesFinales = (int) $conexion->query(
    'SELECT COUNT(*) FROM recargos_mora_adeudos'
)->fetchColumn();

if (
    $adeudosFinales !== $adeudosIniciales
    || $configuracionesFinales !== $configuracionesIniciales
) {
    fwrite(STDERR, "ERROR HU02.3: Los datos reales cambiaron.\n");
    exit(1);
}

echo "[CORRECTO] MySQL real sin cambios: {$adeudosFinales} adeudos, "
    . "{$configuracionesFinales} configuraciones.\n";
echo "RESULTADO: prueba de aplicación real aislada superada.\n";
