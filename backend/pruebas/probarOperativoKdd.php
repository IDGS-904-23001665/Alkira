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
        'sinFiltros' => $servicio
            ->consultarOperativoFiltrado(null, null),

        'octubre2026' => $servicio
            ->consultarOperativoFiltrado(null, '2026-10'),

        'torreAOctubre' => $servicio
            ->consultarOperativoFiltrado(2, '2026-10'),

        'condominioOctubre' => $servicio
            ->consultarOperativoFiltrado(1, '2026-10'),

        'septiembre2026' => $servicio
            ->consultarOperativoFiltrado(null, '2026-09')
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
        'Error al consultar mantenimiento KDD: '
        . $error->getMessage()
        . PHP_EOL
    );

    exit(1);
}