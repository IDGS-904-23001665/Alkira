<?php
declare(strict_types=1);

/**
 * HU02.3 - Generación mensual automática de adeudos.
 *
 * Ejecutar en terminal:
 *
 * php backend/cron/generarAdeudosMensuales.php --simular
 * php backend/cron/generarAdeudosMensuales.php --ejecutar
 *
 * La ejecución real requiere autorización explícita.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta tarea solo puede ejecutarse desde terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__)
    . '/services/EjecucionAdeudosServicio.php';

$modo = $argv[1] ?? '--simular';

if (
    count($argv) > 2
    || !in_array(
        $modo,
        ['--simular', '--ejecutar'],
        true
    )
) {
    fwrite(
        STDERR,
        "Uso: php backend/cron/generarAdeudosMensuales.php "
        . "[--simular|--ejecutar]\n"
    );
    exit(1);
}

try {
    // Verificar la configuración de desarrollo.
    $rutaConfiguracion = dirname(__DIR__)
        . '/config/baseDatos.local.php';

    if (!is_file($rutaConfiguracion)) {
        throw new RuntimeException(
            'Falta la configuración local de MySQL.'
        );
    }

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
            'Esta tarea solo admite MySQL local: alkira.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')
            ->fetchColumn() !== 'alkira'
    ) {
        throw new RuntimeException(
            'La conexión apunta a otra base de datos.'
        );
    }

    // Inicializar nuestros servicios.
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

    // Trabajar con la fecha local de México.
    $hoy = new DateTimeImmutable(
        'today',
        new DateTimeZone('America/Mexico_City')
    );

    $fechaActual = $hoy->format('Y-m-d');
    $periodo = $hoy->format('Y-m');
    $ultimoDia = (int) $hoy->format('t');

    echo "=== HU02.3 / {$periodo} ===\n";
    echo "Fecha local: {$fechaActual}\n";

    echo 'Modo: '
        . ($modo === '--ejecutar'
            ? 'ejecución'
            : 'simulación')
        . "\n";

    // Consultar el periodo sin escrituras.
    $simulacion = $simulador
        ->simularPeriodo($periodo);

    echo 'Reglas activas: '
        . $simulacion['reglasActivas'] . "\n";

    echo 'Contratos evaluados: '
        . $simulacion['contratosEvaluados'] . "\n";

    echo 'Cobros generables: '
        . $simulacion['generables'] . "\n";

    echo 'Contratos omitidos: '
        . $simulacion['omitidos'] . "\n";

    // El modo predeterminado nunca escribe en MySQL.
    if ($modo === '--simular') {
        echo "Simulación completada. No se modificó MySQL.\n";
        exit(0);
    }

    // Protección adicional para la tarea programada.
    if (
        getenv('ALKIRA_CRON_ADEUDOS_HABILITADO')
        !== '1'
    ) {
        throw new RuntimeException(
            'Ejecución bloqueada: '
            . 'ALKIRA_CRON_ADEUDOS_HABILITADO '
            . 'no está en 1.'
        );
    }

    $reglas = $repositorio->listarReglasActivas();

    if ($reglas === []) {
        echo "Sin reglas activas. No se generaron cobros.\n";
        exit(0);
    }

    // El ejecutor trabaja por periodo completo.
    // Para impedir cobros anticipados, todas las
    // reglas deben coincidir en la fecha de corte.
    $fechasCorte = [];

    foreach ($reglas as $regla) {
        $diaCorte = (int) $regla['diaCorte'];

        if ($diaCorte < 1 || $diaCorte > 31) {
            throw new DomainException(
                'Hay un día de corte inválido.'
            );
        }

        // Ajustar al último día en meses cortos.
        $diaAplicable = min(
            $diaCorte,
            $ultimoDia
        );

        $fechasCorte[] = sprintf(
            '%s-%02d',
            $periodo,
            $diaAplicable
        );
    }

    $fechasUnicas = array_values(
        array_unique($fechasCorte)
    );

    if (count($fechasUnicas) !== 1) {
        throw new DomainException(
            'Hay diferentes fechas de corte activas. '
            . 'La automatización por alcance '
            . 'requiere una adaptación adicional.'
        );
    }

    // Solo generar en el día de corte.
    if ($fechaActual !== $fechasUnicas[0]) {
        echo 'Hoy no es el día de corte ('
            . $fechasUnicas[0]
            . "). No se generaron cobros.\n";

        exit(0);
    }

    if (
        (int) $simulacion['pendientesDefinicion'] > 0
    ) {
        throw new DomainException(
            'Existen reglas con fechas inconsistentes.'
        );
    }

    // Evitar que dos tareas CLI ejecuten
    // simultáneamente el mismo periodo.
    $nombreBloqueo = 'alkira_hu02_3_' . $periodo;

    $consultaBloqueo = $conexion->prepare(
        'SELECT GET_LOCK(:nombre, 0)'
    );

    $consultaBloqueo->execute([
        'nombre' => $nombreBloqueo
    ]);

    if (
        (int) $consultaBloqueo->fetchColumn() !== 1
    ) {
        throw new RuntimeException(
            'Ya hay otra generación mensual en ejecución.'
        );
    }

    try {
        // El servicio vuelve a verificar reglas,
        // contratos vigentes y duplicados.
        $resultado = $ejecutor->generarPeriodo(
            $periodo
        );

        echo 'Adeudos creados: '
            . $resultado['creados'] . "\n";

        echo 'Duplicados omitidos durante ejecución: '
            . $resultado['omitidosPorDuplicado']
            . "\n";

        echo "Generación mensual finalizada.\n";

    } finally {
        // Liberar el bloqueo de la tarea.
        $consultaLiberar = $conexion->prepare(
            'SELECT RELEASE_LOCK(:nombre)'
        );

        $consultaLiberar->execute([
            'nombre' => $nombreBloqueo
        ]);
    }

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'HU02.3: ' . $error->getMessage() . "\n"
    );

    exit(1);
}
