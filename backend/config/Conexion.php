<?php
declare(strict_types=1);

/**
 * Conexión PDO para ALKIRA.
 * En Railway utiliza las variables de entorno del servicio MySQL.
 * En desarrollo conserva config/baseDatos.local.php.
 */
final class Conexion
{
    public static function obtenerConexion(): PDO
    {
        $variablesRailway = [
            'servidor' => 'MYSQLHOST',
            'puerto' => 'MYSQLPORT',
            'baseDatos' => 'MYSQLDATABASE',
            'usuario' => 'MYSQLUSER',
            'contrasena' => 'MYSQLPASSWORD',
        ];

        $hayVariablesRailway = false;

        foreach ($variablesRailway as $variable) {
            $valor = getenv($variable);
            if ($valor !== false && $valor !== '') {
                $hayVariablesRailway = true;
                break;
            }
        }

        if ($hayVariablesRailway) {
            $configuracion = [];

            foreach ($variablesRailway as $clave => $variable) {
                $valor = getenv($variable);

                if ($valor === false || $valor === '') {
                    throw new RuntimeException(
                        'Falta la variable de entorno requerida: ' . $variable
                    );
                }

                $configuracion[$clave] = $valor;
            }
        } else {
            $rutaConfiguracion = __DIR__ . '/baseDatos.local.php';

            if (!is_file($rutaConfiguracion)) {
                throw new RuntimeException(
                    'Falta la configuración de MySQL: variables Railway o baseDatos.local.php.'
                );
            }

            $configuracion = require $rutaConfiguracion;
        }

        if (!is_array($configuracion)) {
            throw new RuntimeException('La configuración de MySQL no es válida.');
        }

        foreach (array_keys($variablesRailway) as $clave) {
            if (
                !array_key_exists($clave, $configuracion)
                || !is_string($configuracion[$clave])
                || $configuracion[$clave] === ''
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
            preg_match('/^[0-9]{1,5}$/D', $puerto) !== 1
            || (int) $puerto < 1
            || (int) $puerto > 65535
        ) {
            throw new RuntimeException('El puerto MySQL no es válido.');
        }

        if (preg_match('/^[a-zA-Z0-9_]+$/D', $baseDatos) !== 1) {
            throw new RuntimeException('El nombre de la base de datos no es válido.');
        }

        if (
            strpbrk($servidor, ";\r\n") !== false
            || strpbrk($usuario, "\r\n") !== false
        ) {
            throw new RuntimeException('El servidor o el usuario de MySQL no es válido.');
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
