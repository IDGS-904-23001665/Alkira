<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class AutenticacionControlador
{
    public function __construct(private AutenticacionServicio $servicio)
    {
    }

    public function ingresar(): void
    {
        $contenido = file_get_contents('php://input');
        $datos = json_decode($contenido === false ? '' : $contenido, true);

        if (!is_array($datos) || !is_string($datos['correo'] ?? null)
            || !is_string($datos['contrasena'] ?? null)
            || strlen($datos['correo']) > 150 || strlen($datos['contrasena']) > 1024
        ) {
            $this->responder(422, ['mensaje' => 'Datos de acceso inválidos.']);
            return;
        }

        $usuario = $this->servicio->ingresar($datos['correo'], $datos['contrasena']);
        if ($usuario === null) {
            $this->responder(401, ['mensaje' => 'Correo o contraseña incorrectos.']);
            return;
        }

        $this->guardarCookie($this->servicio->crearToken($usuario['idUsuario']), 3600);
        $this->responder(200, ['usuario' => $usuario]);
    }

    public function sesion(): void
    {
        $usuario = $this->servicio->verificarToken($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            $this->responder(401, ['mensaje' => 'Sesión no válida o vencida.']);
            return;
        }
        $this->responder(200, ['usuario' => $usuario]);
    }

    public function salir(): void
    {
        $this->guardarCookie('', -3600);
        $this->responder(200, ['mensaje' => 'Sesión finalizada.']);
    }

    private function guardarCookie(string $valor, int $duracion): void
    {
        $servidor = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
        $entornoLocal = in_array($servidor, ['localhost', '127.0.0.1'], true);

        setcookie('alkira_token', $valor, [
            'expires' => time() + $duracion,
            'path' => '/',
            'secure' => !$entornoLocal,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
