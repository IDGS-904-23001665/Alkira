<?php
declare(strict_types=1);

// Prueba de bloqueos de MySQL para HU02.3.
// Solo realiza consultas y transacciones que se revierten.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta prueba solo puede ejecutarse en la terminal.');
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/repos/GeneracionAdeudosRepositorio.php';

$repositorioPrimero = null;
$repositorioSegundo = null;
$codigoSalida = 0;

try {
    $conexionPrimera = Conexion::obtenerConexion();
    $conexionSegunda = Conexion::obtenerConexion();

    $repositorioPrimero = new GeneracionAdeudosRepositorio(
        $conexionPrimera
    );

    $repositorioSegundo = new GeneracionAdeudosRepositorio(
        $conexionSegunda
    );

    $contratos = $repositorioPrimero->listarContratosVigentes();

    if ($contratos === []) {
        throw new RuntimeException(
            'No hay contratos vigentes para probar el bloqueo.'
        );
    }

    $idContrato = (int) $contratos[0]['idContrato'];

    // La segunda conexión esperará como máximo
    // dos segundos. Solo afecta a esta sesión.
    $conexionSegunda->exec(
        'SET SESSION innodb_lock_wait_timeout = 2'
    );

    echo "=== Prueba de concurrencia HU02.3 ===\n";
    echo "Contrato de prueba: #{$idContrato}\n";

    // Primera conexión: bloquea el contrato.
    $repositorioPrimero->iniciarTransaccion();

    $contratoBloqueado = $repositorioPrimero
        ->bloquearContrato($idContrato);

    if ($contratoBloqueado === false) {
        throw new RuntimeException(
            'El contrato ya no está disponible para esta prueba.'
        );
    }

    echo "Conexión 1: contrato bloqueado.\n";

    // Segunda conexión: intenta bloquear
    // el mismo contrato.
    $repositorioSegundo->iniciarTransaccion();

    try {
        $repositorioSegundo->bloquearContrato(
            $idContrato
        );

        throw new RuntimeException(
            'La conexión 2 obtuvo el contrato sin esperar el desbloqueo.'
        );

    } catch (PDOException $error) {
        $codigoMysql = (int) (
            $error->errorInfo[1] ?? 0
        );

        // MySQL 1205: tiempo de espera del bloqueo.
        if ($codigoMysql !== 1205) {
            throw $error;
        }

        echo "Conexión 2: bloqueo confirmado (MySQL 1205).\n";
    }

    echo "Resultado: protección por contrato comprobada.\n";

} catch (Throwable $error) {
    $codigoSalida = 1;

    fwrite(
        STDERR,
        "Prueba no superada: {$error->getMessage()}\n"
    );

} finally {
    // Nunca confirmamos ninguna transacción.
    $repositorioSegundo?->revertirTransaccion();
    $repositorioPrimero?->revertirTransaccion();
}

exit($codigoSalida);
