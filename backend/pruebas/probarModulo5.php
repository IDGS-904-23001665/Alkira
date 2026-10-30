<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/IncidenciasServicio.php';

try {
    $conexion = Conexion::obtenerConexion();
    $repositorio = new IncidenciasRepositorio($conexion);
    $servicio = new IncidenciasServicio($repositorio);

    $todas = $servicio->listarTodas();

    echo json_encode(
        [
            'modulo' => 'HU05 - Incidencias con Evidencia Multimedia',
            'totalIncidencias' => count($todas),
            'incidencias' => $todas,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Falló probarModulo5.php: ' . $error->getMessage() . PHP_EOL
    );
    exit(1);
}
