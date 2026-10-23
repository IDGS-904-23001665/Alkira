<?php
declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/servicios/AdeudosServicio.php';

$idInquilino = filter_var(
    $argv[1] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => ['min_range' => 1],
    ]
);

if ($idInquilino === false || $idInquilino === null) {
    fwrite(
        STDERR,
        'Uso: php backend/pruebas/probarModulo2.php ID_INQUILINO'
        . PHP_EOL
    );
    exit(1);
}

try {
    $conexion = Conexion::obtenerConexion();

    $repositorio = new AdeudosRepositorio($conexion);

    $servicio = new AdeudosServicio($repositorio);

    $resultado = $servicio->consultarPendientes(
        $idInquilino
    );

    echo json_encode(
        $resultado,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Falló la consulta. Comprueba que ejecutaste modulo2.sql y que existe el usuario.'
        . PHP_EOL
    );
    exit(1);
}
