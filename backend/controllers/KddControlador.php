<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/KddServicio.php';
require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class KddControlador
{
    public function __construct(
        private KddServicio $servicio,
        private AutenticacionServicio $autenticacion
    ) {
    }

    public function indicadores(): void
    {
        $token = $_COOKIE['alkira_token'] ?? null;

        $usuario = $this->autenticacion->verificarToken(
            is_string($token) ? $token : null
        );

        if ($usuario === null) {
            http_response_code(401);

            echo json_encode([
                'mensaje' => 'Sesión no válida o vencida.'
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return;
        }

        if ($usuario['tipoUsuario'] !== 'administrador') {
            http_response_code(403);

            echo json_encode([
                'mensaje' => 'No tienes permiso para consultar KDD.'
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return;
        }

        $indicadores = $this->servicio->consultarIndicadores();

        http_response_code(200);

        echo json_encode(
            $indicadores,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}