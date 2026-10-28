<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AdeudosServicio.php';
require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class AdeudosControlador
{
    public function __construct(
        private AutenticacionServicio $autenticacion,
        private AdeudosServicio $adeudos
    ) {
    }

    public function consultarPendientes(): void
    {
        $token = $this->obtenerTokenBearer();
        $usuario = $this->autenticacion->verificarTokenMovil($token);

        if ($usuario === null) {
            $this->responder(401, ['mensaje' => 'Sesión no válida o vencida.']);
            return;
        }

        if ($usuario['tipoUsuario'] !== 'inquilino') {
            $this->responder(403, ['mensaje' => 'Esta consulta es exclusiva para inquilinos.']);
            return;
        }

        // La identidad se obtiene del JWT validado, nunca de un parámetro de la app.
        $resultado = $this->adeudos->consultarPendientes((int) $usuario['idUsuario']);
        $this->responder(200, $resultado);
    }

    private function obtenerTokenBearer(): ?string
    {
        $autorizacion = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (!is_string($autorizacion) || strlen($autorizacion) > 4200) {
            return null;
        }

        if (preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/i', trim($autorizacion), $coincidencias) !== 1) {
            return null;
        }

        return $coincidencias[1];
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
