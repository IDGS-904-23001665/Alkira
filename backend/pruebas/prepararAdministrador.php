<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';

$correo = 'admin@alkira.test';
echo 'Preparar administrador local: ' . $correo . PHP_EOL;
echo 'La contraseña será visible mientras la escribes en esta terminal.' . PHP_EOL;
echo 'Nueva contraseña (mínimo 12 caracteres): ';
$contrasena = trim((string) fgets(STDIN));

if (strlen($contrasena) < 12) {
    fwrite(STDERR, 'La contraseña debe tener al menos 12 caracteres.' . PHP_EOL);
    exit(1);
}

try {
    $conexion = Conexion::obtenerConexion();
    $consulta = $conexion->prepare('SELECT id_usuario, tipo_usuario FROM usuarios WHERE correo = :correo LIMIT 1');
    $consulta->execute(['correo' => $correo]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario['tipo_usuario'] !== 'administrador') {
        throw new RuntimeException('El correo pertenece a un usuario de otro rol.');
    }

    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    if ($usuario) {
        $sentencia = $conexion->prepare(
            'UPDATE usuarios SET contrasena_hash = :hash, estado = :estado, activo = 1 WHERE id_usuario = :idUsuario'
        );
        $sentencia->execute(['hash' => $hash, 'estado' => 'activo', 'idUsuario' => $usuario['id_usuario']]);
    } else {
        $sentencia = $conexion->prepare(
            'INSERT INTO usuarios (nombres, apellidos, correo, contrasena_hash, tipo_usuario, estado, activo)
             VALUES (:nombres, :apellidos, :correo, :hash, :tipoUsuario, :estado, 1)'
        );
        $sentencia->execute([
            'nombres' => 'Administrador',
            'apellidos' => 'Local',
            'correo' => $correo,
            'hash' => $hash,
            'tipoUsuario' => 'administrador',
            'estado' => 'activo',
        ]);
    }
    echo 'Administrador local preparado correctamente.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'No fue posible preparar el administrador. Revisa la conexión y schema.sql.' . PHP_EOL);
    exit(1);
}
