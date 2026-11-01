<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/TicketsServicio.php';
require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class TicketsControlador
{
    public function __construct(
        private AutenticacionServicio $autenticacion,
        private TicketsServicio $tickets
    ) {
    }

    public function procesar(string $metodo, string $ruta): void
    {
        $cabecera = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = null;
        if (is_string($cabecera) && strlen($cabecera) <= 4200 &&
            preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/iD', trim($cabecera), $partes) === 1) {
            $token = $partes[1];
        }

        $usuario = $this->autenticacion->verificarTokenMovil($token);
        if ($usuario === null) {
            $this->responder(401, ['mensaje' => 'Sesión móvil no válida o vencida.']);
            return;
        }

        $tipoUsuario = (string) ($usuario['tipoUsuario'] ?? '');
        $idUsuario = (int) ($usuario['idUsuario'] ?? 0);
        if ($idUsuario < 1 || !in_array($tipoUsuario, ['inquilino', 'propietario', 'tecnico'], true)) {
            $this->responder(403, ['mensaje' => 'No tienes permiso para consultar tickets.']);
            return;
        }

        try {
            if ($metodo === 'POST'
                && preg_match('#^/api/tickets/([1-9][0-9]*)/firma$#D', $ruta, $grupos) === 1) {
                $tipoContenido = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
                if (preg_match('~^application/json(?:\s*;|\s*$)~', $tipoContenido) !== 1) {
                    $this->responder(415, ['mensaje' => 'Se requiere formato JSON.']);
                    return;
                }
                $cuerpo = file_get_contents('php://input', false, null, 0, 800001);
                if ($cuerpo === false || strlen($cuerpo) > 800000) {
                    $this->responder(413, ['mensaje' => 'El archivo de firma es demasiado grande.']);
                    return;
                }
                $datos = json_decode($cuerpo, true);
                if (!is_array($datos) || !is_string($datos['firmaBase64'] ?? null)
                    || (isset($datos['comentario']) && !is_string($datos['comentario']))) {
                    $this->responder(422, ['mensaje' => 'Debes enviar una firma válida.']);
                    return;
                }
                $this->tickets->firmarConformidad(
                    (int) $grupos[1],
                    $idUsuario,
                    $tipoUsuario,
                    $datos['firmaBase64'],
                    $datos['comentario'] ?? null
                );
                $this->responder(200, [
                    'mensaje' => 'Conformidad registrada. El ticket quedó en evaluación.',
                    'estado' => 'pendiente_revision'
                ]);
                return;
            }

            if ($metodo === 'GET' && $ruta === '/api/tickets') {
                $this->responder(200, [
                    'tickets' => $this->tickets->listar($idUsuario, $tipoUsuario)
                ]);
                return;
            }

            if ($metodo === 'GET' && preg_match('#^/api/tickets/([1-9][0-9]*)/historial$#D', $ruta, $grupos) === 1) {
                $idTicket = (int) $grupos[1];
                $this->responder(200, [
                    'historial' => $this->tickets->consultarHistorial(
                        $idTicket, $idUsuario, $tipoUsuario
                    )
                ]);
                return;
            }

            $this->responder(404, ['mensaje' => 'Ruta de tickets no encontrada.']);
        } catch (OutOfBoundsException $error) {
            $this->responder(404, ['mensaje' => $error->getMessage()]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, ['mensaje' => $error->getMessage()]);
        } catch (DomainException $error) {
            $this->responder(409, ['mensaje' => $error->getMessage()]);
        }
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
