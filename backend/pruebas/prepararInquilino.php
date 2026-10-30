<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se puede ejecutar desde la terminal.' . PHP_EOL);
}

require_once dirname(__DIR__) . '/config/Conexion.php';

const CORREO_INQUILINO_PRUEBA = 'inquilino@alkira.test';

echo 'Preparar inquilino local: ' . CORREO_INQUILINO_PRUEBA . PHP_EOL;
echo 'Introduce una contraseña de al menos 12 caracteres: ';

$contrasena = trim((string) fgets(STDIN));

if (strlen($contrasena) < 12) {
    fwrite(STDERR, 'La contraseña debe tener al menos 12 caracteres.' . PHP_EOL);
    exit(1);
}

try {
    $conexion = Conexion::obtenerConexion();

    $consulta = $conexion->prepare(
        'SELECT id_usuario, tipo_usuario
         FROM usuarios
         WHERE correo = :correo
         LIMIT 1'
    );

    $consulta->execute([
        'correo' => CORREO_INQUILINO_PRUEBA
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario['tipo_usuario'] !== 'inquilino') {
        throw new RuntimeException('El correo pertenece a otro tipo de usuario.');
    }

    $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);

    if ($usuario) {
        $sentencia = $conexion->prepare(
            'UPDATE usuarios
             SET contrasena_hash = :contrasenaHash,
                 estado = :estado,
                 activo = 1
             WHERE id_usuario = :idUsuario'
        );

        $sentencia->execute([
            'contrasenaHash' => $contrasenaHash,
            'estado' => 'activo',
            'idUsuario' => $usuario['id_usuario']
        ]);
    } else {
        $sentencia = $conexion->prepare(
            'INSERT INTO usuarios
                (nombres, apellidos, correo, contrasena_hash,
                 tipo_usuario, estado, activo)
             VALUES
                (:nombres, :apellidos, :correo, :contrasenaHash,
                 :tipoUsuario, :estado, 1)'
        );

        $sentencia->execute([
            'nombres' => 'Inquilino',
            'apellidos' => 'Prueba',
            'correo' => CORREO_INQUILINO_PRUEBA,
            'contrasenaHash' => $contrasenaHash,
            'tipoUsuario' => 'inquilino',
            'estado' => 'activo'
        ]);
    }

    echo 'Inquilino de prueba preparado correctamente.' . PHP_EOL;

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'No se pudo preparar el inquilino. Revisa la conexión y la tabla usuarios.' . PHP_EOL
    );
    exit(1);
}
