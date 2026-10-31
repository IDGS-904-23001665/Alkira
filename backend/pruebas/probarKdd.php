<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/KddServicio.php';

try {
    $conexion = Conexion::obtenerConexion();

    $repositorio = new KddRepositorio($conexion);

    $servicio = new KddServicio($repositorio);

    $resultado = $servicio->consultarIndicadores();

    echo json_encode(
        $resultado,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_THROW_ON_ERROR
    ) . PHP_EOL;

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Error al consultar KDD: '
        . $error->getMessage()
        . PHP_EOL
    );

    exit(1);
}