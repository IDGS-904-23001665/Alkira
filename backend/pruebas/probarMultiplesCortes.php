<?php
declare(strict_types=1);

/**
 * HU02.3 - Prueba de diferentes días de corte.
 *
 * No genera adeudos reales.
 * Inserta una regla temporal y ejecuta ROLLBACK.
 */

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__)
    . '/services/GeneracionAdeudosServicio.php';

/**
 * Obtiene el resultado de un contrato en la simulación.
 */
function obtenerResultadoContrato(
    array $simulacion,
    int $idContrato
): string {
    foreach ($simulacion['detalle'] as $registro) {
        if ((int) $registro['idContrato'] === $idContrato) {
            return (string) $registro['resultado'];
        }
    }

    throw new RuntimeException(
        "No se encontró el contrato #{$idContrato}."
    );
}

/**
 * Verifica que el resultado coincida con lo esperado.
 */
function verificarResultado(
    string $descripcion,
    string $obtenido,
    string $esperado
): void {
    echo "{$descripcion}: {$obtenido}\n";

    if ($obtenido !== $esperado) {
        throw new RuntimeException(
            "Se esperaba '{$esperado}' y se obtuvo '{$obtenido}'."
        );
    }
}

$conexion = null;

try {
    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')->fetchColumn()
        !== 'alkira'
    ) {
        throw new RuntimeException(
            'La conexión no corresponde a la base alkira.'
        );
    }

    $repositorio = new GeneracionAdeudosRepositorio(
        $conexion
    );

    $servicio = new GeneracionAdeudosServicio(
        $repositorio
    );

    $periodo = '2027-01';
    $idContrato = 2;
    $fechaAntesCorte = '2027-01-05';
    $fechaEnCorte = '2027-01-15';

    echo "=== Prueba HU02.3 / múltiples cortes ===\n";

    // Identificar la regla general activa.
    $consultaGeneral = $conexion->query(
        "SELECT id_concepto, monto_cuota,
                tipo_recargo, valor_recargo, dia_corte
         FROM reglas_cobro
         WHERE alcance = 'general' AND activo = 1"
    );

    $reglasGenerales = $consultaGeneral->fetchAll(
        PDO::FETCH_ASSOC
    );

    if (
        count($reglasGenerales) !== 1
        || (int) $reglasGenerales[0]['dia_corte'] !== 1
    ) {
        throw new RuntimeException(
            'Esta prueba requiere una regla general '
            . 'activa con corte el día 1.'
        );
    }

    $reglaGeneral = $reglasGenerales[0];
    $idConcepto = (int) $reglaGeneral['id_concepto'];

    // Evitar conflictos con reglas existentes.
    $consultaEspecifica = $conexion->prepare(
        "SELECT COUNT(*)
         FROM reglas_cobro
         WHERE id_concepto = :idConcepto
           AND alcance = 'departamento'"
    );

    $consultaEspecifica->execute([
        'idConcepto' => $idConcepto
    ]);

    if ((int) $consultaEspecifica->fetchColumn() > 0) {
        throw new RuntimeException(
            'Ya existe una regla para departamentos. '
            . 'No se modificará.'
        );
    }

    $reglasAntes = (int) $conexion->query(
        'SELECT COUNT(*) FROM reglas_cobro'
    )->fetchColumn();

    $adeudosAntes = (int) $conexion->query(
        'SELECT COUNT(*) FROM estados_cuenta'
    )->fetchColumn();

    echo "Reglas iniciales: {$reglasAntes}\n";
    echo "Estados de cuenta iniciales: {$adeudosAntes}\n";

    // Prueba 1: únicamente regla general.
    $simulacionGeneral = $servicio->simularPeriodo(
        $periodo,
        $fechaAntesCorte
    );

    verificarResultado(
        'Regla general, día 5',
        obtenerResultadoContrato(
            $simulacionGeneral,
            $idContrato
        ),
        'generable'
    );

    // Iniciar una transacción para crear
    // una regla específica temporal.
    $conexion->beginTransaction();

    $consultaInsertar = $conexion->prepare(
        "INSERT INTO reglas_cobro (
            id_concepto,
            alcance,
            monto_cuota,
            tipo_recargo,
            valor_recargo,
            dia_corte,
            dia_limite_pago,
            activo
        ) VALUES (
            :idConcepto,
            'departamento',
            :montoCuota,
            :tipoRecargo,
            :valorRecargo,
            15,
            20,
            1
        )"
    );

    $consultaInsertar->execute([
        'idConcepto' => $idConcepto,
        'montoCuota' => $reglaGeneral['monto_cuota'],
        'tipoRecargo' => $reglaGeneral['tipo_recargo'],
        'valorRecargo' => $reglaGeneral['valor_recargo']
    ]);

    echo "Regla temporal: departamentos, corte 15.\n";

    // Prueba 2: antes del corte específico.
    $simulacionAntes = $servicio->simularPeriodo(
        $periodo,
        $fechaAntesCorte
    );

    verificarResultado(
        'Regla departamento, día 5',
        obtenerResultadoContrato(
            $simulacionAntes,
            $idContrato
        ),
        'corte_pendiente'
    );

    // Prueba 3: cuando llega el corte específico.
    $simulacionCorte = $servicio->simularPeriodo(
        $periodo,
        $fechaEnCorte
    );

    verificarResultado(
        'Regla departamento, día 15',
        obtenerResultadoContrato(
            $simulacionCorte,
            $idContrato
        ),
        'generable'
    );

    // Deshacer la regla de prueba.
    $conexion->rollBack();

    echo "ROLLBACK ejecutado.\n";

    // Verificar que regresa la regla general.
    $simulacionRestaurada = $servicio->simularPeriodo(
        $periodo,
        $fechaAntesCorte
    );

    verificarResultado(
        'Regla general restaurada, día 5',
        obtenerResultadoContrato(
            $simulacionRestaurada,
            $idContrato
        ),
        'generable'
    );

    $reglasDespues = (int) $conexion->query(
        'SELECT COUNT(*) FROM reglas_cobro'
    )->fetchColumn();

    $adeudosDespues = (int) $conexion->query(
        'SELECT COUNT(*) FROM estados_cuenta'
    )->fetchColumn();

    echo "Reglas finales: {$reglasDespues}\n";
    echo "Estados de cuenta finales: {$adeudosDespues}\n";

    if (
        $reglasAntes !== $reglasDespues
        || $adeudosAntes !== $adeudosDespues
    ) {
        throw new RuntimeException(
            'Las cantidades finales no coinciden.'
        );
    }

    echo "RESULTADO: prueba superada sin cambios persistentes.\n";

} catch (Throwable $error) {
    if (
        $conexion instanceof PDO
        && $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }

    fwrite(
        STDERR,
        'ERROR HU02.3: ' . $error->getMessage() . "\n"
    );

    exit(1);
}
