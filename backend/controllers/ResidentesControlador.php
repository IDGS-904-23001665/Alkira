<?php
declare(strict_types=1);

require_once dirname(__DIR__) .
    '/services/ResidentesServicio.php';

final class ResidentesControlador
{
    public function __construct(
        private ResidentesServicio $servicio
    ) {}

    public function procesar(
        string $metodo,
        string $ruta = '/api/incidencias/residentes'
    ): void {
        try {
            // Endpoint original del módulo de incidencias.
            if ($ruta === '/api/incidencias/residentes') {
                if ($metodo !== 'GET') {
                    header('Allow: GET');

                    $this->responder(405, [
                        'mensaje' => 'Método no permitido.'
                    ]);
                    return;
                }

                $this->responder(200, [
                    'residentes' => $this->servicio->listarActivos()
                ]);
                return;
            }

            // CRUD administrativo de residentes.
            if ($ruta === '/api/administracion/residentes') {
                if ($metodo === 'GET') {
                    $this->responder(200, [
                        'residentes' => $this->servicio->listar()
                    ]);
                    return;
                }

                if ($metodo === 'POST') {
                    $id = $this->servicio->crear(
                        $this->leerJson()
                    );

                    $this->responder(201, [
                        'mensaje' => 'Residente registrado correctamente.',
                        'idUsuario' => $id
                    ]);
                    return;
                }
            }

            if (preg_match(
                '#^/api/administracion/residentes/([1-9][0-9]*)(/reactivar)?$#D',
                $ruta,
                $coincidencias
            )) {
                $id = (int) $coincidencias[1];

                $reactivar =
                    ($coincidencias[2] ?? '') === '/reactivar';

                if ($reactivar && $metodo === 'PATCH') {
                    $this->servicio->cambiarEstado($id, true);

                    $this->responder(200, [
                        'mensaje' => 'Residente reactivado.'
                    ]);
                    return;
                }

                if (!$reactivar && $metodo === 'GET') {
                    $this->responder(200, [
                        'residente' => $this->servicio->buscar($id)
                    ]);
                    return;
                }

                if (!$reactivar && $metodo === 'PUT') {
                    $this->servicio->actualizar(
                        $id,
                        $this->leerJson()
                    );

                    $this->responder(200, [
                        'mensaje' => 'Residente actualizado correctamente.'
                    ]);
                    return;
                }

                if (!$reactivar && $metodo === 'DELETE') {
                    $this->servicio->cambiarEstado($id, false);

                    $this->responder(200, [
                        'mensaje' => 'Residente desactivado correctamente.'
                    ]);
                    return;
                }
            }

            $this->responder(405, [
                'mensaje' => 'Método no permitido.'
            ]);

        } catch (DomainException $error) {
            $this->responder(409, [
                'mensaje' => $error->getMessage()
            ]);

        } catch (InvalidArgumentException $error) {
            $this->responder(422, [
                'mensaje' => $error->getMessage()
            ]);

        } catch (OutOfBoundsException $error) {
            $this->responder(404, [
                'mensaje' => $error->getMessage()
            ]);

        } catch (PDOException $error) {
            error_log($error->getMessage());

            $this->responder(
                $error->getCode() === '23000' ? 409 : 500,
                [
                    'mensaje' => 'No fue posible procesar los datos.'
                ]
            );
        }
    }

    private function leerJson(): array
    {
        $contenido = file_get_contents('php://input');

        if ($contenido === false || strlen($contenido) > 65536) {
            throw new InvalidArgumentException(
                'Cuerpo de solicitud inválido.'
            );
        }

        try {
            $datos = json_decode(
                $contenido,
                true,
                32,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('JSON inválido.');
        }

        if (!is_array($datos) || array_is_list($datos)) {
            throw new InvalidArgumentException(
                'Se espera un objeto JSON.'
            );
        }

        return $datos;
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
