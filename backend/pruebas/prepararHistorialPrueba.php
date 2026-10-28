<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la terminal.' . PHP_EOL);
}

require_once dirname(__DIR__) . '/config/Conexion.php';

const CORREO_INQUILINO_PRUEBA = 'inquilino@alkira.test';
const REFERENCIA_PRUEBA = 'REF-ALKIRA-HISTORIAL-LOCAL';

try {
    $conexion = Conexion::obtenerConexion();
    $conexion->beginTransaction();

    $consulta = $conexion->prepare(
        'SELECT u.id_usuario, ec.id_estado_cuenta, ec.monto_principal
         FROM usuarios AS u
         INNER JOIN contratos AS c ON c.id_inquilino = u.id_usuario
         INNER JOIN estados_cuenta AS ec ON ec.id_contrato = c.id_contrato
         WHERE u.correo = :correo
           AND u.tipo_usuario = :tipo
         ORDER BY ec.fecha_limite_pago ASC, ec.id_estado_cuenta ASC
         LIMIT 1'
    );
    $consulta->execute([
        'correo' => CORREO_INQUILINO_PRUEBA,
        'tipo' => 'inquilino',
    ]);
    $registro = $consulta->fetch(PDO::FETCH_ASSOC);
    if ($registro === false) {
        throw new RuntimeException('Primero ejecuta prepararAdeudosPrueba.php.');
    }

    $comprobar = $conexion->prepare(
        'SELECT id_pago FROM pagos_transacciones
         WHERE id_usuario_registro = :idInquilino
           AND referencia_bancaria = :referencia
         LIMIT 1'
    );
    $comprobar->execute([
        'idInquilino' => (int) $registro['id_usuario'],
        'referencia' => REFERENCIA_PRUEBA,
    ]);
    if ($comprobar->fetchColumn() !== false) {
        $conexion->commit();
        echo 'El pago de prueba ya existe. No se duplicó.' . PHP_EOL;
        exit;
    }

    $insertar = $conexion->prepare(
        'INSERT INTO pagos_transacciones
            (id_estado_cuenta, id_usuario_registro, fecha_pago, monto,
             metodo_pago, referencia_bancaria, estado, observaciones)
         VALUES
            (:idEstadoCuenta, :idUsuario, NOW(), :monto,
             :metodo, :referencia, :estado, :observaciones)'
    );
    $insertar->execute([
        'idEstadoCuenta' => (int) $registro['id_estado_cuenta'],
        'idUsuario' => (int) $registro['id_usuario'],
        'monto' => (string) $registro['monto_principal'],
        'metodo' => 'transferencia',
        'referencia' => REFERENCIA_PRUEBA,
        'estado' => 'en_revision',
        'observaciones' => 'Registro simulado para probar el historial móvil.',
    ]);

    $conexion->commit();
    echo 'Pago de prueba creado en estado en revisión.' . PHP_EOL;
    echo 'El monto pendiente no cambia hasta que el pago sea aprobado.' . PHP_EOL;
} catch (Throwable $error) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    fwrite(STDERR, 'No se pudo crear el pago de prueba: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
