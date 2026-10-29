<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/IncidenciasServicio.php';

final class IncidenciasControlador
{
    public function __construct(
        private IncidenciasServicio $servicio,
        private array $usuarioSesion
    ) {}

    public function procesar(string $metodo, string $ruta): void
    {
        try {
            $idSesion = (int) ($this->usuarioSesion['idUsuario'] ?? 1);

            if ($metodo === 'GET' && $ruta === '/api/incidencias') {
                if (isset($_GET['idUsuario']) && is_numeric($_GET['idUsuario'])) {
                    $this->responder(200, [
                        'incidencias' => $this->servicio->listarPorUsuario((int) $_GET['idUsuario']),
                    ]);
                    return;
                }
                $this->responder(200, [
                    'incidencias' => $this->servicio->listarTodas(),
                ]);
                return;
            }

            if ($metodo === 'GET' && $ruta === '/api/incidencias/residentes') {
                $this->responder(200, [
                    'residentes' => $this->servicio->listarResidentesActivos(),
                ]);
                return;
            }

            if ($metodo === 'POST' && $ruta === '/api/incidencias') {
                $incidencia = $this->servicio->crear($this->leerJson(), $idSesion);
                $this->responder(201, ['incidencia' => $incidencia]);
                return;
            }

            if (preg_match('#^/api/incidencias/([1-9][0-9]*)(/evidencias|/estado)?$#D', $ruta, $coincidencias)) {
                $idIncidencia = (int) $coincidencias[1];
                $subruta = $coincidencias[2] ?? '';

                if ($subruta === '' && $metodo === 'GET') {
                    $this->responder(200, [
                        'incidencia' => $this->servicio->buscar($idIncidencia),
                    ]);
                    return;
                }

                if ($subruta === '/evidencias' && $metodo === 'GET') {
                    $this->responder(200, [
                        'evidencias' => $this->servicio->listarEvidencias($idIncidencia),
                    ]);
                    return;
                }

                if ($subruta === '/evidencias' && $metodo === 'POST') {
                    $evidencias = $this->servicio->agregarEvidencia($idIncidencia, $this->leerJson());
                    $this->responder(201, ['evidencias' => $evidencias]);
                    return;
                }

                if (($subruta === '/estado' || $subruta === '') && in_array($metodo, ['PUT', 'PATCH'], true)) {
                    $incidencia = $this->servicio->actualizarEstado($idIncidencia, $this->leerJson(), $idSesion);
                    $this->responder(200, ['incidencia' => $incidencia]);
                    return;
                }
            }

            $this->responder(404, ['mensaje' => 'Ruta de incidencias no encontrada.']);
        } catch (DomainException $error) {
            $this->responder(409, ['mensaje' => $error->getMessage()]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, ['mensaje' => $error->getMessage()]);
        } catch (OutOfBoundsException $error) {
            $this->responder(404, ['mensaje' => $error->getMessage()]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->responder(500, ['mensaje' => 'No fue posible procesar la operación de incidencias.']);
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
