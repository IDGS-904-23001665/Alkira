<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/DispositivosTecnicosRepositorio.php';

final class DispositivosTecnicosControlador
{
    public function __construct(
        private DispositivosTecnicosRepositorio $repositorio
    ) {
    }

    public function procesar(string $metodo, array $usuario): void
    {
        if (($usuario['tipoUsuario'] ?? '') !== 'tecnico') {
            $this->responder(403, [
                'mensaje' => 'Esta operación es exclusiva para técnicos.'
            ]);
        }

        $idUsuario = (int) ($usuario['idUsuario'] ?? 0);

        if ($idUsuario <= 0) {
            $this->responder(401, ['mensaje' => 'Sesión no válida.']);
        }

        if (!in_array($metodo, ['POST', 'DELETE'], true)) {
            $this->responder(405, ['mensaje' => 'Método no permitido.']);
        }

        $tipo = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

        if (preg_match(
            '~^application/json(?:\s*;|\s*$)~i',
            $tipo
        ) !== 1) {
            $this->responder(415, ['mensaje' => 'Se requiere JSON.']);
        }

        $cuerpo = file_get_contents('php://input', false, null, 0, 2049);

        if ($cuerpo === false || strlen($cuerpo) > 2048) {
            $this->responder(413, ['mensaje' => 'Solicitud demasiado grande.']);
        }

        try {
            $datos = json_decode($cuerpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->responder(422, ['mensaje' => 'JSON inválido.']);
        }

        $token = is_array($datos) ? ($datos['token'] ?? null) : null;

        if (
            !is_string($token)
            || strlen($token) < 30
            || strlen($token) > 512
            || preg_match('/^[A-Za-z0-9:_-]+$/D', $token) !== 1
        ) {
            $this->responder(422, ['mensaje' => 'Token FCM inválido.']);
        }

        try {
            if ($metodo === 'POST') {
                $this->repositorio->registrarToken($idUsuario, $token);

                $this->responder(200, [
                    'mensaje' => 'Dispositivo del técnico registrado.'
                ]);
            }

            $this->repositorio->eliminarToken($idUsuario, $token);

            $this->responder(200, [
                'mensaje' => 'Dispositivo del técnico desvinculado.'
            ]);
        } catch (PDOException $error) {
            error_log(
                'HU06: error al registrar dispositivo. Código SQL: '
                . $error->getCode()
            );

            $this->responder(500, [
                'mensaje' => 'No fue posible actualizar el dispositivo.'
            ]);
        }
    }

    private function responder(int $codigo, array $contenido): never
    {
        http_response_code($codigo);

        echo json_encode(
            $contenido,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        exit;
    }
}
