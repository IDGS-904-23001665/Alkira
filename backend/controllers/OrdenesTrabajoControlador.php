<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/OrdenesTrabajoServicio.php';

final class OrdenesTrabajoControlador
{
    public function __construct(private OrdenesTrabajoServicio $servicio)
    {
    }

    public function procesar(string $metodo, string $ruta, array $usuario): void
    {
        if (($usuario['tipoUsuario'] ?? null) !== 'administrador') {
            $this->responder(403, ['mensaje' => 'Solo administración puede asignar órdenes de trabajo.']);
            return;
        }

        try {
            if ($metodo === 'GET' && $ruta === '/api/mantenimiento/tickets') {
                $this->responder(200, ['tickets' => $this->servicio->listarTickets()]);
                return;
            }
            if ($metodo === 'GET' && $ruta === '/api/mantenimiento/tecnicos') {
                $this->responder(200, ['tecnicos' => $this->servicio->listarTecnicos()]);
                return;
            }
            if ($metodo === 'POST'
                && preg_match('#^/api/mantenimiento/tickets/([1-9][0-9]*)/asignar$#D', $ruta, $encontrados) === 1) {
                $origen = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
                $sitio = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
                $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    ? 'https' : 'http';
                $origenServidor = $protocolo . '://' . (string) ($_SERVER['HTTP_HOST'] ?? '');
                $origenesPermitidos = [
                    $origenServidor,
                    'http://localhost:5173',
                    'http://127.0.0.1:5173',
                ];
                if ($sitio === 'cross-site' ||
                    ($origen !== '' && !in_array($origen, $origenesPermitidos, true))) {
                    $this->responder(403, ['mensaje' => 'Origen de solicitud no permitido.']);
                    return;
                }
                if (preg_match('~^application/json(?:\s*;|\s*$)~i', (string) ($_SERVER['CONTENT_TYPE'] ?? '')) !== 1) {
                    $this->responder(415, ['mensaje' => 'Se requiere una solicitud JSON.']);
                    return;
                }
                $cuerpo = file_get_contents('php://input', false, null, 0, 8193);
                if ($cuerpo === false || strlen($cuerpo) > 8192) {
                    $this->responder(413, ['mensaje' => 'La solicitud supera el tamaño permitido.']);
                    return;
                }
                try {
                    $datos = json_decode($cuerpo, true, 32, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new InvalidArgumentException('JSON inválido.');
                }
                if (!is_array($datos)) {
                    throw new InvalidArgumentException('El contenido de la solicitud es inválido.');
                }
                $asignacion = $this->servicio->asignar(
                    (int) $encontrados[1],
                    $datos,
                    (int) ($usuario['idUsuario'] ?? 0)
                );
                $this->responder(201, [
                    'mensaje' => 'Orden creada y asignada. Notificación pendiente de entrega.',
                    'asignacion' => $asignacion,
                ]);
                return;
            }

            $this->responder(404, ['mensaje' => 'Ruta de mantenimiento no encontrada.']);
        } catch (OutOfBoundsException $error) {
            $this->responder(404, ['mensaje' => $error->getMessage()]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, ['mensaje' => $error->getMessage()]);
        } catch (DomainException $error) {
            $this->responder(409, ['mensaje' => $error->getMessage()]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->responder(500, ['mensaje' => 'No fue posible consultar o asignar la orden.']);
        }
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
