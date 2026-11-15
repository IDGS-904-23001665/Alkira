<?php
declare(strict_types=1);

/**
 * HU02.3: verifica el registro transaccional
 * del adeudo y su configuración de mora.
 *
 * No confirma la transacción ni aplica recargos.
 */

if (PHP_SAPI !== 'cli') {
    exit('Solo se permite ejecutar desde terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__)
    . '/repos/GeneracionAdeudosRepositorio.php';

$conexion = null;

try {
    // Permitir únicamente MySQL local.
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
        || ($configuracion['baseDatos'] ?? null) !== 'alkira'
    ) {
        throw new RuntimeException(
            'La prueba requiere MySQL local alkira.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    if (
        $conexion->query('SELECT DATABASE()')
            ->fetchColumn() !== 'alkira'
    ) {
        throw new RuntimeException(
            'La conexión utiliza otra base de datos.'
        );
    }

    $repositorio = new GeneracionAdeudosRepositorio(
        $conexion
    );

    $contratos = $repositorio->listarContratosVigentes();
    $reglas = $repositorio->listarReglasActivas();

    if ($contratos === [] || $reglas === []) {
        throw new RuntimeException(
            'Se necesita un contrato vigente y una regla activa.'
        );
    }

    $contrato = $contratos[0];
    $regla = $reglas[0];

    $idContrato = (int) $contrato['idContrato'];
    $periodo = '2099-12';

    if (
        $repositorio->existeCobroMantenimiento(
            $idContrato,
            $periodo
        )
    ) {
        throw new RuntimeException(
            'Ya existe mantenimiento del periodo de prueba.'
        );
    }

    // Consultar únicamente las dos tablas permitidas.
    $contar = static function (
        PDO $conexion,
        string $tabla
    ): int {
        if (
            !in_array(
                $tabla,
                ['estados_cuenta', 'recargos_mora_adeudos'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Tabla no permitida.'
            );
        }

        return (int) $conexion->query(
            "SELECT COUNT(*) FROM {$tabla}"
        )->fetchColumn();
    };

    $adeudosAntes = $contar(
        $conexion,
        'estados_cuenta'
    );

    $recargosAntes = $contar(
        $conexion,
        'recargos_mora_adeudos'
    );

    echo "=== Prueba de configuración de mora HU02.3 ===\n";
    echo "Adeudos iniciales: {$adeudosAntes}\n";
    echo "Configuraciones iniciales: {$recargosAntes}\n";

    // Crear ambos registros en una misma transacción.
    $repositorio->iniciarTransaccion();

    $idEstadoCuenta = $repositorio->crearAdeudo(
        $idContrato,
        (int) $regla['idConcepto'],
        $periodo,
        '2099-12-01',
        '2099-12-10',
        (string) $regla['montoCuota']
    );

    $repositorio->registrarConfiguracionRecargo(
        $idEstadoCuenta,
        (int) $regla['idReglaCobro'],
        (string) $regla['tipoRecargo'],
        (string) $regla['valorRecargo']
    );

    // Verificar ambos registros ANTES del ROLLBACK.
    $consulta = $conexion->prepare(
        'SELECT
            ec.recargo_mora AS recargoMora,
            rm.tipo_recargo AS tipoRecargo,
            rm.valor_recargo AS valorRecargo,
            rm.fecha_aplicacion AS fechaAplicacion,
            rm.monto_aplicado AS montoAplicado
         FROM estados_cuenta ec
         INNER JOIN recargos_mora_adeudos rm
             ON rm.id_estado_cuenta = ec.id_estado_cuenta
         WHERE ec.id_estado_cuenta = :idEstadoCuenta'
    );

    $consulta->execute([
        'idEstadoCuenta' => $idEstadoCuenta
    ]);

    $registro = $consulta->fetch(PDO::FETCH_ASSOC);

    if (
        $registro === false
        || (float) $registro['recargoMora'] !== 0.0
        || $registro['tipoRecargo'] !== $regla['tipoRecargo']
        || (float) $registro['valorRecargo']
            !== (float) $regla['valorRecargo']
        || $registro['fechaAplicacion'] !== null
        || $registro['montoAplicado'] !== null
    ) {
        throw new RuntimeException(
            'La configuración temporal no coincide con la regla.'
        );
    }

    echo "[CORRECTO] Adeudo y configuración registrados.\n";
    echo "[CORRECTO] Recargo todavía no aplicado.\n";

    // Revertir ambos registros.
    $repositorio->revertirTransaccion();

    $adeudosDespues = $contar(
        $conexion,
        'estados_cuenta'
    );

    $recargosDespues = $contar(
        $conexion,
        'recargos_mora_adeudos'
    );

    echo "Adeudos finales: {$adeudosDespues}\n";
    echo "Configuraciones finales: {$recargosDespues}\n";

    if (
        $adeudosAntes !== $adeudosDespues
        || $recargosAntes !== $recargosDespues
    ) {
        throw new RuntimeException(
            'El ROLLBACK no restauró los conteos.'
        );
    }

    echo "RESULTADO: prueba superada sin escrituras persistentes.\n";

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
