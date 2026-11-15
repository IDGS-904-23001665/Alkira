<?php
declare(strict_types=1);

/**
 * HU02.3 - Verificación del filtro de fechas
 * del ejecutor de adeudos.
 *
 * Solo utiliza probarPeriodo(), que revierte
 * las inserciones de prueba.
 */

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__)
    . '/services/EjecucionAdeudosServicio.php';

function verificarFechaRechazada(
    EjecucionAdeudosServicio $ejecutor,
    string $periodo,
    string $fecha,
    string $descripcion
): void {
    try {
        $ejecutor->probarPeriodo(
            $periodo,
            $fecha
        );
    } catch (InvalidArgumentException $error) {
        echo "[CORRECTO] {$descripcion}\n";
        return;
    }

    throw new RuntimeException(
        "FALLÓ: {$descripcion}. "
        . 'El ejecutor aceptó una fecha no permitida.'
    );
}

try {
    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')
            ->fetchColumn() !== 'alkira'
    ) {
        throw new RuntimeException(
            'La conexión no corresponde a alkira.'
        );
    }

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

    $hoy = new DateTimeImmutable(
        'today',
        new DateTimeZone('America/Mexico_City')
    );

    $periodo = $hoy->format('Y-m');
    $fechaActual = $hoy->format('Y-m-d');

    $fechaFutura = $hoy
        ->modify('+1 day')
        ->format('Y-m-d');

    $fechaOtroPeriodo = $hoy
        ->modify('last day of previous month')
        ->format('Y-m-d');

    $totalInicial = (int) $conexion->query(
        'SELECT COUNT(*) FROM estados_cuenta'
    )->fetchColumn();

    echo "=== Prueba del ejecutor HU02.3 ===\n";
    echo "Periodo: {$periodo}\n";
    echo "Fecha actual: {$fechaActual}\n";
    echo "Estados de cuenta iniciales: {$totalInicial}\n";

    // 1. La fecha actual debe ser aceptada.
    // El método probarPeriodo revierte cualquier
    // inserción que pudiera realizar.
    $resultado = $ejecutor->probarPeriodo(
        $periodo,
        $fechaActual
    );

    if ($resultado['modo'] !== 'prueba_revertida') {
        throw new RuntimeException(
            'El ejecutor no devolvió el modo de prueba.'
        );
    }

    echo "[CORRECTO] Fecha actual aceptada.\n";

    echo 'Cobros ensayados y revertidos: '
        . $resultado['creados']
        . "\n";

    // 2. No permitir ejecutar con fecha futura.
    verificarFechaRechazada(
        $ejecutor,
        $periodo,
        $fechaFutura,
        'Fecha futura rechazada'
    );

    // 3. No permitir fechas de otro periodo.
    verificarFechaRechazada(
        $ejecutor,
        $periodo,
        $fechaOtroPeriodo,
        'Fecha de otro periodo rechazada'
    );

    // 4. No permitir fechas inexistentes.
    verificarFechaRechazada(
        $ejecutor,
        $periodo,
        $periodo . '-32',
        'Fecha inválida rechazada'
    );

    // 5. Confirmar que MySQL quedó sin cambios.
    $totalFinal = (int) $conexion->query(
        'SELECT COUNT(*) FROM estados_cuenta'
    )->fetchColumn();

    echo "Estados de cuenta finales: {$totalFinal}\n";

    if ($totalInicial !== $totalFinal) {
        throw new RuntimeException(
            'El número de estados de cuenta cambió.'
        );
    }

    echo "RESULTADO: pruebas superadas sin cobros persistentes.\n";

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'ERROR HU02.3: ' . $error->getMessage() . "\n"
    );

    exit(1);
}
