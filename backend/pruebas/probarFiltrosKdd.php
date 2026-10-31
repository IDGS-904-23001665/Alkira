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

    $resultado = [
        'inmuebles' => $servicio->consultarInmuebles(),

        'sinFiltros' => $servicio
            ->consultarFinancieroFiltrado(null, null),

        'octubre2026' => $servicio
            ->consultarFinancieroFiltrado(null, '2026-10'),

        'septiembre2026' => $servicio
            ->consultarFinancieroFiltrado(null, '2026-09'),

        'torreAOctubre' => $servicio
            ->consultarFinancieroFiltrado(2, '2026-10')
    ];

    echo json_encode(
        $resultado,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_THROW_ON_ERROR
    ) . PHP_EOL;

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Error al probar filtros KDD: '
        . $error->getMessage()
        . PHP_EOL
    );

    exit(1);
}