<?php
declare(strict_types=1);

require_once __DIR__ . '/AutenticacionServicio.php';

final class AutenticacionSolicitud
{
    public static function usuario(AutenticacionServicio $servicio): ?array
    {
        $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($cabecera !== null) {
            if (!is_string($cabecera) || strlen($cabecera) > 4200 ||
                preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/iD', trim($cabecera), $partes) !== 1) return null;
            return $servicio->verificarTokenMovil($partes[1]);
        }
        $cookie = $_COOKIE['alkira_token'] ?? null;
        return method_exists($servicio, 'verificarTokenWeb')
            ? $servicio->verificarTokenWeb($cookie) : $servicio->verificarToken($cookie);
    }
}
