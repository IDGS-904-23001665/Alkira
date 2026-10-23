<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

$ruta = dirname(__DIR__) . '/config/autenticacion.local.php';
if (file_exists($ruta)) {
    echo 'La clave de autenticación ya existe. No se modificó.' . PHP_EOL;
    exit;
}

$contenido = '<?php' . PHP_EOL . 'return ' . var_export([
    'claveJwt' => bin2hex(random_bytes(32)),
], true) . ';' . PHP_EOL;

if (file_put_contents($ruta, $contenido, LOCK_EX) === false) {
    fwrite(STDERR, 'No fue posible crear la clave local.' . PHP_EOL);
    exit(1);
}
@chmod($ruta, 0600);
echo 'Clave local creada en backend/config/autenticacion.local.php.' . PHP_EOL;
