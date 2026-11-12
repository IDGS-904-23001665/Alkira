<?php
declare(strict_types=1);

/**
 * HU02.3 - Prueba de inserción transaccional.
 *
 * Solo para MySQL local de desarrollo.
 * Inserta un adeudo temporal y lo revierte.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta prueba solo puede ejecutarse en terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__)
    . '/repos/GeneracionAdeudosRepositorio.php';

$repositorio = null;

try {
    // Impedir ejecución accidental contra Railway.
    $rutaConfiguracion = dirname(__DIR__)
        . '/config/baseDatos.local.php';

    $configuracion = require $rutaConfiguracion;

    $servidor = (string) (
        $configuracion['servidor'] ?? ''
    );

    $baseDatos = (string) (
        $configuracion['baseDatos'] ?? ''
    );

    if (
        !in_array(
            $servidor,
            ['localhost', '127.0.0.1', '::1'],
            true
        )
        || $baseDatos !== 'alkira'
    ) {
        throw new RuntimeException(
            'La prueba solo está permitida en MySQL local alkira.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    $repositorio = new GeneracionAdeudosRepositorio(
        $conexion
    );

    // Verificar que la tabla soporte ROLLBACK.
    $consultaMotor = $conexion->query(
        <<<'SQL'
        SELECT ENGINE
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'estados_cuenta'
        SQL
    );

    $motor = (string) $consultaMotor->fetchColumn();

    if (strtoupper($motor) !== 'INNODB') {
        throw new RuntimeException(
            'La tabla estados_cuenta debe utilizar InnoDB.'
        );
    }

    // Buscar un contrato vigente y un concepto
    // de mantenimiento activo.
    $consultaDatos = $conexion->query(
        <<<'SQL'
        SELECT
            c.id_contrato AS idContrato,
            cc.id_concepto AS idConcepto
        FROM contratos c
        INNER JOIN propiedades_unidades u
            ON u.id_unidad = c.id_unidad
        CROSS JOIN conceptos_cobro cc
        WHERE c.estado_contrato = 'vigente'
          AND u.activo = 1
          AND cc.tipo_concepto = 'mantenimiento'
          AND cc.periodicidad = 'mensual'
          AND cc.activo = 1
        ORDER BY
            c.id_contrato,
            cc.id_concepto
        LIMIT 1
        SQL
    );

    $datos = $consultaDatos->fetch(
        PDO::FETCH_ASSOC
    );

    if ($datos === false) {
        throw new RuntimeException(
            'No existen contratos o conceptos adecuados para la prueba.'
        );
    }

    $idContrato = (int) $datos['idContrato'];
    $idConcepto = (int) $datos['idConcepto'];

    // Periodo futuro exclusivo para esta prueba.
    // Nunca se confirma el registro.
    $periodo = '2099-12';

    $contarEstados = static function (
        PDO $conexion
    ): int {
        return (int) $conexion
            ->query('SELECT COUNT(*) FROM estados_cuenta')
            ->fetchColumn();
    };

    $totalAntes = $contarEstados($conexion);

    echo "=== Prueba de inserción HU02.3 ===\n";
    echo "Contrato: #{$idContrato}\n";
    echo "Concepto: #{$idConcepto}\n";
    echo "Periodo temporal: {$periodo}\n";
    echo "Estados antes: {$totalAntes}\n";

    // Iniciar transacción real.
    $repositorio->iniciarTransaccion();

    try {
        $contrato = $repositorio->bloquearContrato(
            $idContrato
        );

        if ($contrato === false) {
            throw new RuntimeException(
                'No fue posible bloquear el contrato.'
            );
        }

        if (
            $repositorio->existeCobroMantenimiento(
                $idContrato,
                $periodo
            )
        ) {
            throw new RuntimeException(
                'Ya existe un mantenimiento para el periodo de prueba.'
            );
        }

        // Inserción temporal de un centavo.
        $idEstadoCuenta = $repositorio->crearAdeudo(
            $idContrato,
            $idConcepto,
            $periodo,
            '2099-12-01',
            '2099-12-10',
            '0.01'
        );

        echo "Adeudo temporal: #{$idEstadoCuenta}\n";

        $totalDurante = $contarEstados($conexion);

        if ($totalDurante !== $totalAntes + 1) {
            throw new RuntimeException(
                'No se confirmó la inserción temporal.'
            );
        }

        echo "Inserción temporal: correcta\n";

    } finally {
        // Incluso si hay errores, revertimos.
        $repositorio->revertirTransaccion();
    }

    $totalDespues = $contarEstados($conexion);

    $consultaVerificacion = $conexion->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM estados_cuenta
        WHERE id_estado_cuenta = :idEstadoCuenta
        SQL
    );

    $consultaVerificacion->execute([
        'idEstadoCuenta' => $idEstadoCuenta
    ]);

    $registroPersistente = (int)
        $consultaVerificacion->fetchColumn();

    echo "ROLLBACK: ejecutado\n";
    echo "Estados después: {$totalDespues}\n";

    if (
        $totalAntes !== $totalDespues
        || $registroPersistente !== 0
    ) {
        throw new RuntimeException(
            'La reversión no pasó las verificaciones.'
        );
    }

    echo "Resultado: prueba superada.\n";
    echo "No quedó ningún adeudo de prueba.\n";

} catch (Throwable $error) {
    $repositorio?->revertirTransaccion();

    fwrite(
        STDERR,
        "Error de prueba: {$error->getMessage()}\n"
    );

    exit(1);
}
