<?php
declare(strict_types=1);

/**
 * HU02.3 - Generación mensual de adeudos (local o Railway).
 *
 * Ejemplos:
 * php backend/cron/generarAdeudosMensuales.php --simular
 * php backend/cron/generarAdeudosMensuales.php --ejecutar
 *
 * El modo --simular es siempre de solo lectura.
 * El modo --ejecutar requiere ALKIRA_CRON_ADEUDOS_HABILITADO=1.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta tarea solo se ejecuta desde la terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/EjecucionAdeudosServicio.php';
require_once dirname(__DIR__) . '/services/RecargosMoraServicio.php';

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
    // La base local sigue siendo la opción predeterminada.
    // Railway debe seleccionarse explícitamente.
    $baseEsperada = getenv('ALKIRA_CRON_ADEUDOS_BASE_ESPERADA');

    if ($baseEsperada === false || $baseEsperada === '') {
        $baseEsperada = 'alkira';
    }

    if (!in_array($baseEsperada, ['alkira', 'railway'], true)) {
        throw new RuntimeException(
            'La base de datos esperada no está autorizada.'
        );
    }

    if ($baseEsperada === 'alkira') {
        $rutaConfiguracion = dirname(__DIR__)
            . '/config/baseDatos.local.php';

        if (!is_file($rutaConfiguracion)) {
            throw new RuntimeException(
                'No existe la configuración local de MySQL.'
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
            || ($configuracion['baseDatos'] ?? null) !== 'alkira'
        ) {
            throw new RuntimeException(
                'La tarea local solo admite MySQL local: alkira.'
            );
        }
    } else {
        $variables = [
            'MYSQLHOST',
            'MYSQLPORT',
            'MYSQLDATABASE',
            'MYSQLUSER',
            'MYSQLPASSWORD'
        ];

        foreach ($variables as $variable) {
            $valor = getenv($variable);

            if ($valor === false || $valor === '') {
                throw new RuntimeException(
                    'Falta la variable de Railway: ' . $variable
                );
            }
        }

        if (getenv('MYSQLDATABASE') !== 'railway') {
            throw new RuntimeException(
                'La configuración de Railway apunta a otra base.'
            );
        }
    }

    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')->fetchColumn()
        !== $baseEsperada
    ) {
        throw new RuntimeException(
            'La conexión usa una base de datos distinta de la esperada.'
        );
    }

    // Servicios existentes de HU02.3.
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

    $servicioRecargos = new RecargosMoraServicio($conexion);

    // Fecha de México.
    $hoy = new DateTimeImmutable(
        'today',
        new DateTimeZone('America/Mexico_City')
    );

    $fechaActual = $hoy->format('Y-m-d');
    $periodo = $hoy->format('Y-m');

    echo "=== HU02.3 / {$periodo} ===\n";
    echo "Fecha local: {$fechaActual}\n";

    echo 'Modo: '
        . (
            $modo === '--ejecutar'
                ? 'ejecución'
                : 'simulación'
        )
        . "\n";

    // NUEVO:
    // Evaluamos cada contrato según su propia fecha
    // de corte, sin exigir que todas las reglas
    // tengan el mismo día.
    //
    // Las cuotas futuras quedan como corte_pendiente.
    // Las cuotas vencidas no se generan retroactivamente.
    $simulacion = $simulador->simularPeriodo(
        $periodo,
        $fechaActual
    );

    echo 'Reglas activas: '
        . $simulacion['reglasActivas'] . "\n";

    echo 'Contratos evaluados: '
        . $simulacion['contratosEvaluados'] . "\n";

    echo 'Cobros generables hoy: '
        . $simulacion['generables'] . "\n";

    echo 'Contratos omitidos: '
        . $simulacion['omitidos'] . "\n";

    echo 'Fechas por definir: '
        . $simulacion['pendientesDefinicion'] . "\n";

    // Mostrar resultados individuales.
    foreach ($simulacion['detalle'] as $registro) {
        echo 'Contrato #'
            . $registro['idContrato']
            . ': '
            . $registro['resultado']
            . "\n";
    }

    // Consultar recargos vencidos sin aplicarlos.
    // Tambien permanece en modo lectura si el usuario
    // ejecuta el comando de generacion de adeudos.
    $simulacionRecargos = $servicioRecargos->simularRecargos();

    echo "--- Simulacion de recargos por mora ---\n";
    echo 'Adeudos evaluados: '
        . $simulacionRecargos['adeudosEvaluados'] . "\n";
    echo 'Recargos fijos aplicables: '
        . $simulacionRecargos['recargosFijosAplicables'] . "\n";
    echo 'Recargos porcentuales aplicables: '
        . $simulacionRecargos['recargosPorcentualesAplicables'] . "\n";
    echo 'Monto total simulado: $'
        . $simulacionRecargos['montoTotalRecargos'] . "\n";

    foreach ($simulacionRecargos['detalle'] as $registro) {
        echo 'Adeudo #'
            . $registro['idEstadoCuenta']
            . ': recargo '
            . $registro['tipoRecargo']
            . ' $'
            . $registro['montoRecargo']
            . "\n";
    }

    echo "Los recargos NO se aplicaron.\n";

    // Modo seguro: ninguna escritura en MySQL.
    if ($modo === '--simular') {
        echo "Simulación completada. No se modificó MySQL.\n";
        exit(0);
    }

    // La ejecución real sigue deshabilitada
    // a menos que se active explícitamente.
    if (
        getenv('ALKIRA_CRON_ADEUDOS_HABILITADO') !== '1'
    ) {
        throw new RuntimeException(
            'Ejecución bloqueada: '
            . 'ALKIRA_CRON_ADEUDOS_HABILITADO no está en 1.'
        );
    }

    if ((int) $simulacion['reglasActivas'] === 0) {
        echo "Sin reglas activas. No se generaron cobros.\n";
        exit(0);
    }

    if (
        (int) $simulacion['pendientesDefinicion'] > 0
    ) {
        throw new DomainException(
            'Existen reglas con fechas inconsistentes. '
            . 'No se generaron cobros.'
        );
    }

    // Evitar que dos tareas CLI generen
    // el mismo periodo simultáneamente.
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
            'Ya existe otra ejecución mensual '
            . 'para este periodo.'
        );
    }

    try {
        // NUEVO:
        // Enviar la fecha actual al ejecutor.
        // Solo se procesan contratos cuyo corte
        // ya corresponde y cuyo plazo no ha vencido.
        //
        // El ejecutor vuelve a validar las reglas,
        // la vigencia y los duplicados.
        $resultado = $ejecutor->generarPeriodo(
            $periodo,
            $fechaActual
        );

        echo 'Adeudos creados: '
            . $resultado['creados']
            . "\n";

        echo 'Duplicados omitidos durante ejecución: '
            . $resultado['omitidosPorDuplicado']
            . "\n";

        echo "Generación mensual finalizada.\n";

    } finally {
        // Liberar bloqueo incluso si falla el proceso.
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
