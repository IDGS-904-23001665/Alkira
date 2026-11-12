<?php
declare(strict_types=1);

/**
 * HU02.3 - Ensayo integral de la generación mensual.
 *
 * Uso:
 * php backend/pruebas/probarEjecucionAdeudos.php YYYY-MM
 *
 * Nunca llama a generarPeriodo().
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se permite ejecutar desde la terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__)
    . '/services/EjecucionAdeudosServicio.php';

$zonaHoraria = new DateTimeZone('America/Mexico_City');

$hoy = new DateTimeImmutable(
    'today',
    $zonaHoraria
);

$periodo = $argv[1]
    ?? $hoy->modify('first day of next month')
        ->format('Y-m');

if (
    count($argv) > 2
    || !is_string($periodo)
    || preg_match(
        '/^[0-9]{4}-(0[1-9]|1[0-2])$/D',
        $periodo
    ) !== 1
    || !DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $periodo . '-01'
    )
    || $periodo < $hoy->format('Y-m')
) {
    fwrite(
        STDERR,
        "Indica un mes actual o futuro: YYYY-MM.\n"
    );

    exit(1);
}

try {
    // Solo trabajar con la base local de desarrollo.
    $rutaConfiguracion = dirname(__DIR__)
        . '/config/baseDatos.local.php';

    $configuracion = require $rutaConfiguracion;

    if (
        !is_array($configuracion)
        || !in_array(
            $configuracion['servidor'] ?? null,
            ['localhost', '127.0.0.1', '::1'],
            true
        )
        || ($configuracion['baseDatos'] ?? null)
            !== 'alkira'
    ) {
        throw new RuntimeException(
            'Esta prueba solo se permite con MySQL local: alkira.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')
            ->fetchColumn() !== 'alkira'
    ) {
        throw new RuntimeException(
            'La conexión no apunta a la base de datos esperada.'
        );
    }

    // Confirmar que la tabla permite transacciones.
    $motor = $conexion->query(
        "SELECT ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'estados_cuenta'"
    )->fetchColumn();

    if (strtoupper((string) $motor) !== 'INNODB') {
        throw new RuntimeException(
            'La tabla estados_cuenta debe utilizar InnoDB.'
        );
    }

    // Preparar los servicios que ya desarrollamos.
    $repositorio = new GeneracionAdeudosRepositorio(
        $conexion
    );

    $simulador = new GeneracionAdeudosServicio(
        $repositorio
    );

    $ejecutor = new EjecucionAdeudosServicio(
        $repositorio,
        $simulador
    );

    // Contar adeudos antes y después del ensayo.
    $contarRegistros = static function (
        PDO $conexion,
        string $periodo
    ): array {
        $total = (int) $conexion->query(
            'SELECT COUNT(*) FROM estados_cuenta'
        )->fetchColumn();

        $consulta = $conexion->prepare(
            'SELECT COUNT(*)
             FROM estados_cuenta
             WHERE periodo = :periodo'
        );

        $consulta->execute([
            'periodo' => $periodo
        ]);

        return [
            $total,
            (int) $consulta->fetchColumn()
        ];
    };

    [$totalAntes, $periodoAntes] = $contarRegistros(
        $conexion,
        $periodo
    );

    $simulacion = $simulador->simularPeriodo(
        $periodo
    );

    echo "=== Ensayo integral HU02.3 / {$periodo} ===\n";

    echo 'Reglas activas: '
        . $simulacion['reglasActivas'] . "\n";

    echo 'Cobros generables: '
        . $simulacion['generables'] . "\n";

    echo "Estados de cuenta iniciales: {$totalAntes}\n";

    // Primera situación: no hay reglas activas.
    if ((int) $simulacion['reglasActivas'] === 0) {
        try {
            $ejecutor->probarPeriodo($periodo);

            throw new RuntimeException(
                'Error: el ejecutor permitió operar sin reglas activas.'
            );

        } catch (DomainException $error) {
            if (
                !str_contains(
                    $error->getMessage(),
                    'No existen reglas activas'
                )
            ) {
                throw $error;
            }

            echo "Protección confirmada: sin reglas activas no se generan adeudos.\n";
        }

    } else {
        // Segunda situación: existen reglas activas.
        // Ejecutar exclusivamente el método reversible.
        $resultado = $ejecutor->probarPeriodo(
            $periodo
        );

        if (
            ($resultado['modo'] ?? null)
            !== 'prueba_revertida'
        ) {
            throw new RuntimeException(
                'El ejecutor no devolvió el modo de prueba esperado.'
            );
        }

        echo 'Cobros ensayados y revertidos: '
            . (int) $resultado['creados'] . "\n";

        echo 'Omitidos por duplicado: '
            . (int) $resultado['omitidosPorDuplicado']
            . "\n";

        if ((int) $resultado['creados'] === 0) {
            echo "Aviso: no hubo inserciones para comprobar el ROLLBACK integral.\n";
        }
    }

    // Verificar que el servicio no dejó
    // una transacción abierta.
    if ($conexion->inTransaction()) {
        throw new RuntimeException(
            'La prueba dejó una transacción abierta.'
        );
    }

    [$totalDespues, $periodoDespues] = $contarRegistros(
        $conexion,
        $periodo
    );

    echo "Estados de cuenta finales: {$totalDespues}\n";

    if (
        $totalAntes !== $totalDespues
        || $periodoAntes !== $periodoDespues
    ) {
        throw new RuntimeException(
            'Cambió el número de adeudos tras el ensayo.'
        );
    }

    echo "Resultado: prueba superada, sin nuevos cobros persistentes.\n";

} catch (Throwable $error) {
    if (
        isset($conexion)
        && $conexion instanceof PDO
        && $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }

    fwrite(
        STDERR,
        "Prueba fallida: {$error->getMessage()}\n"
    );

    exit(1);
}
