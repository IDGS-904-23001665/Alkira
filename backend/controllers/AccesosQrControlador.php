<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AccesosQrServicio.php';

final class AccesosQrControlador
{
    public function __construct(
        private AccesosQrServicio $servicio,
        private array $usuarioSesion
    ) {}

    public function procesar(string $metodo, string $ruta): void
    {
        try {
            $idSesion = (int) ($this->usuarioSesion['idUsuario'] ?? 1);

            if ($metodo === 'GET' && $ruta === '/api/accesos-qr') {
                if (isset($_GET['idUsuario']) && is_numeric($_GET['idUsuario'])) {
                    $this->responder(200, [
                        'pases' => $this->servicio->listarPorAnfitrion((int) $_GET['idUsuario']),
                    ]);
                    return;
                }
                $this->responder(200, [
                    'pases' => $this->servicio->listarTodosPases(),
                ]);
                return;
            }

            if ($metodo === 'GET' && $ruta === '/api/accesos-qr/bitacora') {
                $this->responder(200, [
                    'bitacora' => $this->servicio->listarBitacora(),
                ]);
                return;
            }

            if ($metodo === 'POST' && $ruta === '/api/accesos-qr') {
                $pase = $this->servicio->generarPase($this->leerJson(), $idSesion);
                $this->responder(201, ['pase' => $pase]);
                return;
            }

            if ($metodo === 'POST' && $ruta === '/api/accesos-qr/validar') {
                $validacion = $this->servicio->validarToken($this->leerJson(), $idSesion);
                $this->responder(200, [
                    'validacion' => $validacion,
                    'bitacora' => $this->servicio->listarBitacora(),
                    'pases' => $this->servicio->listarTodosPases(),
                ]);
                return;
            }

            if (preg_match('#^/api/accesos-qr/([1-9][0-9]*)/revocar$#D', $ruta, $coincidencias) && in_array($metodo, ['PATCH', 'PUT', 'POST'], true)) {
                $idPase = (int) $coincidencias[1];
                $resultado = $this->servicio->revocarPase($idPase, $idSesion);
                $this->responder(200, ['pase' => $resultado]);
                return;
            }

            $this->responder(404, ['mensaje' => 'Ruta de accesos QR no encontrada.']);
        } catch (DomainException $error) {
            $this->responder(409, ['mensaje' => $error->getMessage()]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, ['mensaje' => $error->getMessage()]);
        } catch (OutOfBoundsException $error) {
            $this->responder(404, ['mensaje' => $error->getMessage()]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->responder(500, ['mensaje' => 'No fue posible procesar la solicitud de accesos QR.']);
        }
    }

    private function leerJson(): array
    {
        $contenido = file_get_contents('php://input');
        if ($contenido === false || strlen($contenido) > 65536) {
            throw new InvalidArgumentException('Cuerpo de solicitud inválido.');
        }
        try {
            $datos = json_decode($contenido, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('JSON inválido.');
        }
        if (!is_array($datos) || array_is_list($datos)) {
            throw new InvalidArgumentException('Se espera un objeto JSON.');
        }
        return $datos;
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
