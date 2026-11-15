<?php
declare(strict_types=1);

/**
 * HU02.3 - Prueba del recargo porcentual único.
 * Crea datos temporales en una transacción y siempre hace ROLLBACK.
 * No habilita la aplicación real de recargos.
 */

if (PHP_SAPI !== 'cli') {
    exit('Esta prueba solo puede ejecutarse desde la terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/repos/GeneracionAdeudosRepositorio.php';
require_once dirname(__DIR__) . '/services/RecargosMoraServicio.php';

$conexion = null;

try {
    $configuracion = require dirname(__DIR__) . '/config/baseDatos.local.php';

    if (
        !is_array($configuracion)
        || !in_array(
            $configuracion['servidor'] ?? null,
            ['127.0.0.1', 'localhost', '::1'],
            true
        )
        || ($configuracion['baseDatos'] ?? null) !== 'alkira'
    ) {
        throw new RuntimeException('Esta prueba requiere MySQL local alkira.');
    }

    $conexion = Conexion::obtenerConexion();

    if ($conexion->query('SELECT DATABASE()')->fetchColumn() !== 'alkira') {
        throw new RuntimeException('Se detectó otra base de datos.');
    }

    $repositorio = new GeneracionAdeudosRepositorio($conexion);
    $servicio = new RecargosMoraServicio($conexion);

    $idContrato = 2;
    $periodo = '2026-09';

    $consultaGeneral = $conexion->query(
        "SELECT id_concepto FROM reglas_cobro
         WHERE alcance = 'general' AND activo = 1"
    );
    $reglasGenerales = $consultaGeneral->fetchAll(PDO::FETCH_ASSOC);

    if (count($reglasGenerales) !== 1) {
        throw new RuntimeException('Se requiere una sola regla general activa.');
    }

    $idConcepto = (int) $reglasGenerales[0]['id_concepto'];

    $consultaEspecifica = $conexion->prepare(
        "SELECT COUNT(*) FROM reglas_cobro
         WHERE id_concepto = :idConcepto AND alcance = 'departamento'"
    );
    $consultaEspecifica->execute(['idConcepto' => $idConcepto]);

    if ((int) $consultaEspecifica->fetchColumn() !== 0) {
        throw new RuntimeException(
            'Ya existe una regla de departamento; no se creará otra.'
        );
    }

    if ($repositorio->existeCobroMantenimiento($idContrato, $periodo)) {
        throw new RuntimeException('El contrato ya tiene mantenimiento en el periodo de prueba.');
    }

    $contar = static fn (string $tabla): int => (int) $conexion->query(
        "SELECT COUNT(*) FROM {$tabla}"
    )->fetchColumn();

    $adeudosAntes = $contar('estados_cuenta');
    $configuracionesAntes = $contar('recargos_mora_adeudos');
    $reglasAntes = $contar('reglas_cobro');

    echo "=== HU02.3 / Recargo porcentual único ===\n";
    echo "Adeudos iniciales: {$adeudosAntes}\n";
    echo "Configuraciones iniciales: {$configuracionesAntes}\n";

    $repositorio->iniciarTransaccion();

    // Regla temporal del 10 %, revertida al final.
    $crearRegla = $conexion->prepare(
        "INSERT INTO reglas_cobro (
            id_concepto, alcance, monto_cuota,
            tipo_recargo, valor_recargo,
            dia_corte, dia_limite_pago, activo
         ) VALUES (
            :idConcepto, 'departamento', 800.00,
            'porcentaje', 10.00, 1, 10, 1
         )"
    );
    $crearRegla->execute(['idConcepto' => $idConcepto]);
    $idRegla = (int) $conexion->lastInsertId();

    // Adeudo vencido de $800, con pago parcial de $500.
    $idEstadoCuenta = $repositorio->crearAdeudo(
        $idContrato,
        $idConcepto,
        $periodo,
        '2026-09-01',
        '2026-09-10',
        '800.00'
    );

    $registrarPago = $conexion->prepare(
        "UPDATE estados_cuenta
         SET monto_pagado = 500.00, estado = 'parcial'
         WHERE id_estado_cuenta = :idEstadoCuenta"
    );
    $registrarPago->execute(['idEstadoCuenta' => $idEstadoCuenta]);

    if ($registrarPago->rowCount() !== 1) {
        throw new RuntimeException('No se pudo preparar el pago parcial.');
    }

    $repositorio->registrarConfiguracionRecargo(
        $idEstadoCuenta,
        $idRegla,
        'porcentaje',
        '10.00'
    );

    $simulacion = $servicio->simularRecargos();
    $encontrado = false;

    foreach ($simulacion['detalle'] as $registro) {
        if ((int) $registro['idEstadoCuenta'] !== $idEstadoCuenta) {
            continue;
        }

        if (
            $registro['tipoRecargo'] !== 'porcentaje'
            || $registro['baseCalculo'] !== '300.00'
            || $registro['montoRecargo'] !== '30.00'
        ) {
            throw new RuntimeException('El cálculo porcentual es incorrecto.');
        }

        $encontrado = true;
        echo "[CORRECTO] Base pendiente: $300.00\n";
        echo "[CORRECTO] Recargo único del 10 %: $30.00\n";
    }

    if (!$encontrado) {
        throw new RuntimeException('La simulación no encontró el adeudo temporal.');
    }

    // SAVEPOINT interno: prueba la actualización y luego la revierte.
    $ensayo = $servicio->probarRecargos();
    $saldoCorrecto = false;

    foreach ($ensayo['detalle'] as $registro) {
        if ((int) $registro['idEstadoCuenta'] !== $idEstadoCuenta) {
            continue;
        }

        $saldoCorrecto =
            $registro['montoRecargo'] === '30.00'
            && ($registro['saldoDuranteEnsayo'] ?? null) === '330.00';
    }

    if (
        !$saldoCorrecto
        || ($ensayo['segundaAplicacionBloqueada'] ?? false) !== true
    ) {
        throw new RuntimeException('Falló el saldo o la protección contra duplicados.');
    }

    echo "[CORRECTO] Saldo durante el ensayo: $330.00\n";
    echo "[CORRECTO] Segunda aplicación bloqueada.\n";

    // Comprobar que el SAVEPOINT restauró el adeudo temporal.
    $verificarReversion = $conexion->prepare(
        'SELECT ec.recargo_mora AS recargoMora,
                rm.fecha_aplicacion AS fechaAplicacion,
                rm.monto_aplicado AS montoAplicado
         FROM estados_cuenta ec
         INNER JOIN recargos_mora_adeudos rm
             ON rm.id_estado_cuenta = ec.id_estado_cuenta
         WHERE ec.id_estado_cuenta = :idEstadoCuenta'
    );
    $verificarReversion->execute(['idEstadoCuenta' => $idEstadoCuenta]);
    $registroRevertido = $verificarReversion->fetch(PDO::FETCH_ASSOC);

    if (
        $registroRevertido === false
        || $registroRevertido['recargoMora'] !== '0.00'
        || $registroRevertido['fechaAplicacion'] !== null
        || $registroRevertido['montoAplicado'] !== null
    ) {
        throw new RuntimeException('El ensayo no restauró el registro temporal.');
    }

    $repositorio->revertirTransaccion();

    $adeudosDespues = $contar('estados_cuenta');
    $configuracionesDespues = $contar('recargos_mora_adeudos');
    $reglasDespues = $contar('reglas_cobro');

    if (
        $adeudosAntes !== $adeudosDespues
        || $configuracionesAntes !== $configuracionesDespues
        || $reglasAntes !== $reglasDespues
    ) {
        throw new RuntimeException('Las tablas cambiaron después del ROLLBACK.');
    }

    echo "Adeudos finales: {$adeudosDespues}\n";
    echo "Configuraciones finales: {$configuracionesDespues}\n";
    echo "[CORRECTO] ROLLBACK completo; sin cambios persistentes.\n";

} catch (Throwable $error) {
    if ($conexion instanceof PDO && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    fwrite(STDERR, 'ERROR HU02.3: ' . $error->getMessage() . "\n");
    exit(1);
}
