<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/AccesosQrServicio.php';

try {
    $conexion = Conexion::obtenerConexion();
    $repositorio = new AccesosQrRepositorio($conexion);
    $servicio = new AccesosQrServicio($repositorio);

    $pases = $servicio->listarTodosPases();
    $bitacora = $servicio->listarBitacora();

    echo json_encode(
        [
            'modulo' => 'HU10 - Generador de Pases QR y Bitácora de Visitas',
            'totalPases' => count($pases),
            'totalBitacora' => count($bitacora),
            'pases' => $pases,
            'bitacora' => $bitacora,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Falló probarModulo10.php: ' . $error->getMessage() . PHP_EOL
    );
    exit(1);
}
