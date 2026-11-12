<?php
declare(strict_types=1);

// Diagnóstico local exclusivo de terminal.
// No modifica registros de MySQL.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este diagnóstico solo se ejecuta desde la terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/GeneracionAdeudosServicio.php';

$periodo = $argv[1] ?? null;

if (
    !is_string($periodo)
    || preg_match(
        '/^[0-9]{4}-(0[1-9]|1[0-2])$/D',
        $periodo
    ) !== 1
) {
    fwrite(
        STDERR,
        "Uso: php backend/pruebas/diagnosticarGeneracionAdeudos.php YYYY-MM\n"
    );
    exit(1);
}

try {
    $conexion = Conexion::obtenerConexion();

    $repositorio = new GeneracionAdeudosRepositorio(
        $conexion
    );

    $servicio = new GeneracionAdeudosServicio(
        $repositorio
    );

    $contarEstados = static function (PDO $conexion): int {
        return (int) $conexion
            ->query('SELECT COUNT(*) FROM estados_cuenta')
            ->fetchColumn();
    };

    $totalAntes = $contarEstados($conexion);

    // 1. Ejecutar únicamente la simulación.
    $simulacion = $servicio->simularPeriodo($periodo);

    echo "=== Diagnóstico HU02.3 / {$periodo} ===\n";

    echo "Reglas activas: {$simulacion['reglasActivas']}\n";
    echo "Contratos evaluados: {$simulacion['contratosEvaluados']}\n";
    echo "Generables: {$simulacion['generables']}\n";
    echo "Omitidos: {$simulacion['omitidos']}\n";
    echo "Fechas por definir: {$simulacion['pendientesDefinicion']}\n\n";

    foreach ($simulacion['detalle'] as $registro) {
        echo 'Contrato #' . $registro['idContrato']
            . ': ' . $registro['resultado'] . "\n";
    }

    // 2. Detectar duplicados de mantenimiento.
    // Se revisa el tipo de concepto, no solo su ID.
    $consultaDuplicados = $conexion->prepare(
        <<<'SQL'
        SELECT
            ec.id_contrato AS idContrato,
            COUNT(*) AS cantidad
        FROM estados_cuenta ec
        INNER JOIN conceptos_cobro cc
            ON cc.id_concepto = ec.id_concepto
        WHERE ec.periodo = :periodo
          AND cc.tipo_concepto = 'mantenimiento'
        GROUP BY ec.id_contrato
        HAVING COUNT(*) > 1
        ORDER BY ec.id_contrato
        SQL
    );

    $consultaDuplicados->execute([
        'periodo' => $periodo
    ]);

    $duplicados = $consultaDuplicados->fetchAll(
        PDO::FETCH_ASSOC
    );

    echo "\nContratos con mantenimiento duplicado: "
        . count($duplicados) . "\n";

    foreach ($duplicados as $duplicado) {
        echo 'Contrato #' . $duplicado['idContrato']
            . ': ' . $duplicado['cantidad']
            . " cargos\n";
    }

    // 3. Probar las consultas con bloqueo transaccional.
    // Siempre se revierte la transacción.
    $contratos = $repositorio->listarContratosVigentes();

    $repositorio->iniciarTransaccion();

    try {
        foreach ($contratos as $contrato) {
            $idContrato = (int) $contrato['idContrato'];

            $bloqueado = $repositorio->bloquearContrato(
                $idContrato
            );

            if ($bloqueado === false) {
                throw new RuntimeException(
                    "No fue posible bloquear el contrato #{$idContrato}."
                );
            }

            $repositorio->existeCobroMantenimiento(
                $idContrato,
                $periodo
            );
        }
    } finally {
        $repositorio->revertirTransaccion();
    }

    // 4. Comprobar que no cambiamos la cantidad de cobros.
    $totalDespues = $contarEstados($conexion);

    echo "\nConsultas transaccionales: correctas\n";
    echo "Estados de cuenta antes: {$totalAntes}\n";
    echo "Estados de cuenta después: {$totalDespues}\n";

    if ($totalAntes !== $totalDespues) {
        throw new RuntimeException(
            'Cambió la cantidad de estados de cuenta durante el diagnóstico.'
        );
    }

    echo "Resultado: diagnóstico finalizado sin escrituras.\n";

} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Error de diagnóstico: {$error->getMessage()}\n"
    );

    exit(1);
}
