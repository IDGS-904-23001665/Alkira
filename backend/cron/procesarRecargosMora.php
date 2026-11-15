<?php
declare(strict_types=1);

/**
 * ALKIRA HU02.3: proceso independiente de recargos por mora.
 *
 * php backend/cron/procesarRecargosMora.php --simular
 * php backend/cron/procesarRecargosMora.php --probar
 * php backend/cron/procesarRecargosMora.php --aplicar
 *
 * La aplicación real está bloqueada por defecto y requiere
 * dos interruptores explícitos. No se programa en cron todavía.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta tarea solo puede ejecutarse desde terminal.');
}

$modo = $argv[1] ?? '--simular';

if (
    count($argv) > 2
    || !in_array($modo, ['--simular', '--probar', '--aplicar'], true)
) {
    fwrite(
        STDERR,
        "Uso: php backend/cron/procesarRecargosMora.php "
        . "[--simular|--probar|--aplicar]\n"
    );
    exit(1);
}

// La aplicación real exige autorización explícita,
// independiente de la generación mensual de adeudos.
if (
    $modo === '--aplicar'
    && (
        getenv('ALKIRA_CRON_RECARGOS_HABILITADO') !== '1'
        || getenv('ALKIRA_RECARGOS_HABILITADOS') !== '1'
    )
) {
    fwrite(
        STDERR,
        "HU02.3: Aplicación bloqueada. "
        . "Se requieren ambos interruptores de recargos.\n"
    );
    exit(1);
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/RecargosMoraServicio.php';

try {
    $rutaConfiguracion = dirname(__DIR__)
        . '/config/baseDatos.local.php';

    if (!is_file($rutaConfiguracion)) {
        throw new RuntimeException('Falta la configuración local de MySQL.');
    }

    $configuracion = require $rutaConfiguracion;

    if (
        !is_array($configuracion)
        || !in_array(
            $configuracion['servidor'] ?? null,
            ['127.0.0.1', 'localhost', '::1'],
            true
        )
        || ($configuracion['baseDatos'] ?? null) !== 'alkira'
    ) {
        throw new RuntimeException(
            'El proceso está limitado a la base MySQL local alkira.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')->fetchColumn() !== 'alkira'
    ) {
        throw new RuntimeException('La conexión usa otra base de datos.');
    }

    $servicio = new RecargosMoraServicio($conexion);

    // Simulación y ensayo jamás guardan los cambios.
    if ($modo === '--simular') {
        $resultado = $servicio->simularRecargos();
    } elseif ($modo === '--probar') {
        $resultado = $servicio->probarRecargos();
    } else {
        // Impide que dos procesos reales de recargos se solapen.
        $nombreBloqueo = 'alkira_hu02_3_recargos';
        $consultaBloqueo = $conexion->prepare(
            'SELECT GET_LOCK(:nombre, 0)'
        );
        $consultaBloqueo->execute(['nombre' => $nombreBloqueo]);

        if ((int) $consultaBloqueo->fetchColumn() !== 1) {
            throw new RuntimeException(
                'Ya existe otra aplicación de recargos en ejecución.'
            );
        }

        try {
            $resultado = $servicio->aplicarRecargos();
        } finally {
            $consultaLiberar = $conexion->prepare(
                'SELECT RELEASE_LOCK(:nombre)'
            );
            $consultaLiberar->execute(['nombre' => $nombreBloqueo]);
        }
    }

    echo "=== ALKIRA HU02.3 / Recargos por mora ===\n";
    echo 'Modo: ' . $resultado['modo'] . "\n";
    echo 'Fecha: ' . $resultado['fechaConsulta'] . "\n";
    echo 'Adeudos evaluados: ' . $resultado['adeudosEvaluados'] . "\n";
    echo 'Recargos fijos: ' . $resultado['recargosFijosAplicables'] . "\n";
    echo 'Recargos porcentuales: '
        . $resultado['recargosPorcentualesAplicables'] . "\n";
    echo 'Monto total: $' . $resultado['montoTotalRecargos'] . "\n";

    foreach ($resultado['detalle'] as $registro) {
        echo 'Adeudo #' . $registro['idEstadoCuenta']
            . ' | ' . $registro['tipoRecargo']
            . ' | $' . $registro['montoRecargo'] . "\n";
    }

    if ($modo === '--simular') {
        echo "Simulación terminada: sin escrituras.\n";
    } elseif ($modo === '--probar') {
        echo "Ensayo terminado: cambios revertidos.\n";
    } else {
        echo "Aplicación real terminada.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'HU02.3: ' . $error->getMessage() . "\n");
    exit(1);
}
