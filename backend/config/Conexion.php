<?php
declare(strict_types=1);

final class Conexion
{
    public static function obtenerConexion(): PDO
    {
        $rutaConfiguracion = __DIR__ . '/baseDatos.local.php';

        if (!is_file($rutaConfiguracion)) {
            throw new RuntimeException(
                'Falta config/baseDatos.local.php.'
            );
        }

        $configuracion = require $rutaConfiguracion;

        if (!is_array($configuracion)) {
            throw new RuntimeException(
                'La configuración de MySQL no es válida.'
            );
        }

        foreach (
            ['servidor', 'puerto', 'baseDatos', 'usuario', 'contrasena']
            as $clave
        ) {
            if (
                !array_key_exists($clave, $configuracion)
                || !is_string($configuracion[$clave])
            ) {
                throw new RuntimeException(
                    'Falta el dato de configuración: ' . $clave
                );
            }
        }

        $servidor = $configuracion['servidor'];
        $puerto = $configuracion['puerto'];
        $baseDatos = $configuracion['baseDatos'];
        $usuario = $configuracion['usuario'];
        $contrasena = $configuracion['contrasena'];

        if (
            !preg_match('/^[0-9]{1,5}$/', $puerto)
            || (int) $puerto < 1
            || (int) $puerto > 65535
        ) {
            throw new RuntimeException('El puerto MySQL no es válido.');
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $baseDatos)) {
            throw new RuntimeException(
                'El nombre de la base de datos no es válido.'
            );
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $servidor,
            $puerto,
            $baseDatos
        );

        return new PDO($dsn, $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
