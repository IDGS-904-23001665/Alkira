<?php
declare(strict_types=1);

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$archivo = realpath(__DIR__ . (string) $ruta);

if ($archivo && str_starts_with($archivo, __DIR__) && is_file($archivo)) {
    return false;
}

require __DIR__ . '/index.php';
