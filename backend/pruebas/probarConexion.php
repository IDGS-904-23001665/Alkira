<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';

try {
    $conexion = Conexion::obtenerConexion();

    $resultado = $conexion->query(
        'SELECT DATABASE() AS baseDatos, VERSION() AS versionMysql'
    )->fetch();

    echo 'Conexión MySQL correcta.' . PHP_EOL;
    echo 'Base de datos: ' . $resultado['baseDatos'] . PHP_EOL;
    echo 'Servidor MySQL: ' . $resultado['versionMysql'] . PHP_EOL;

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'No fue posible conectar. Comprueba credenciales, servidor y pdo_mysql.'
        . PHP_EOL
    );
    exit(1);
}
