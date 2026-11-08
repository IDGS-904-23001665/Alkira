<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/services/ContratosAdministrativosServicio.php';

final class ContratosAdministrativosControlador
{
    private const LIMITE_JSON_BYTES = 16384;

    public function __construct(
        private ContratosAdministrativosServicio $servicio
    ) {
    }

    public function procesar(
        string $metodo,
        string $ruta,
        array $usuario
    ): void {
        if (($usuario['tipoUsuario'] ?? '') !== 'administrador') {
            $this->responder(403, [
                'mensaje' => 'Solo administración puede gestionar contratos.'
            ]);
            return;
        }

        try {
            // HU02.4: Listar contratos administrativos.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/contratos'
            ) {
                $this->responder(200, [
                    'contratos' => $this->servicio->listarContratos()
                ]);
                return;
            }

            // HU02.4: Visualizar el PDF privado de un contrato.
            if (
                $metodo === 'GET'
                && preg_match(
                    '#^/api/administracion/contratos/([1-9][0-9]*)/documento$#D',
                    $ruta,
                    $coincidencias
                ) === 1
            ) {
                $idContrato = (int) $coincidencias[1];

                $rutaArchivo = $this->servicio
                    ->obtenerArchivoContrato($idContrato);

                $tamanoArchivo = filesize($rutaArchivo);

                if ($tamanoArchivo === false) {
                    throw new RuntimeException(
                        'No fue posible consultar el tamaño del PDF.'
                    );
                }

                $lector = fopen($rutaArchivo, 'rb');

                if ($lector === false) {
                    throw new RuntimeException(
                        'No fue posible abrir el documento PDF.'
                    );
                }

                http_response_code(200);

                header('Content-Type: application/pdf');
                header(
                    'Content-Disposition: inline; filename="contrato-'
                    . $idContrato . '.pdf"'
                );
                header('Content-Length: ' . $tamanoArchivo);
                header('Cache-Control: private, no-store');
                header('X-Content-Type-Options: nosniff');

                try {
                    fpassthru($lector);
                } finally {
                    fclose($lector);
                }

                return;
            }

            // Listar unidades para registrar contratos.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/contratos/unidades'
            ) {
                $this->responder(200, [
                    'unidades' => $this->servicio->listarUnidadesActivas()
                ]);
                return;
            }

            // Listar inquilinos para registrar contratos.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/contratos/inquilinos'
            ) {
                $this->responder(200, [
                    'inquilinos' => $this->servicio->listarInquilinosActivos()
                ]);
                return;
            }

            // Consultar el historial de cobros de una unidad.
            if (
                $metodo === 'GET'
                && preg_match(
                    '#^/api/administracion/unidades/([1-9][0-9]*)/cobros$#D',
                    $ruta,
                    $coincidencias
                ) === 1
            ) {
                $this->responder(200, [
                    'cobros' => $this->servicio->listarCobrosPorUnidad(
                        (int) $coincidencias[1]
                    )
                ]);
                return;
            }

            // Registrar un contrato nuevo desde la AWP.
            if (
                $metodo === 'POST'
                && $ruta === '/api/administracion/contratos'
            ) {
                if (!$this->origenPermitido()) {
                    return;
                }

                $tipoContenido = strtolower(
                    (string) ($_SERVER['CONTENT_TYPE'] ?? '')
                );

                if (
                    preg_match(
                        '~^application/json(?:\s*;|\s*$)~',
                        $tipoContenido
                    ) !== 1
                ) {
                    $this->responder(415, [
                        'mensaje' => 'Se requiere formato JSON.'
                    ]);
                    return;
                }

                $datos = $this->leerJson();

                $contrato = $this->servicio->crearContrato(
                    $datos
                );

                $this->responder(201, [
                    'mensaje' => 'Contrato registrado correctamente.',
                    'contrato' => $contrato
                ]);
                return;
            }

            // Subir el PDF de un contrato.
            if (
                $metodo === 'POST'
                && preg_match(
                    '#^/api/administracion/contratos/([1-9][0-9]*)/documento$#D',
                    $ruta,
                    $coincidencias
                ) === 1
            ) {
                if (!$this->origenPermitido()) {
                    return;
                }

                $tipoContenido = strtolower(
                    (string) ($_SERVER['CONTENT_TYPE'] ?? '')
                );

                if (
                    !str_starts_with(
                        $tipoContenido,
                        'multipart/form-data;'
                    )
                ) {
                    $this->responder(415, [
                        'mensaje' => 'Envía el archivo mediante multipart/form-data.'
                    ]);
                    return;
                }

                $archivo = $_FILES['documento'] ?? null;

                if (
                    !is_array($archivo)
                    || is_array($archivo['error'] ?? null)
                ) {
                    $this->responder(422, [
                        'mensaje' => 'Selecciona un archivo PDF.'
                    ]);
                    return;
                }

                if (
                    in_array(
                        $archivo['error'] ?? null,
                        [
                            UPLOAD_ERR_INI_SIZE,
                            UPLOAD_ERR_FORM_SIZE
                        ],
                        true
                    )
                ) {
                    $this->responder(413, [
                        'mensaje' => 'El documento supera el límite permitido.'
                    ]);
                    return;
                }

                $resultado = $this->servicio->subirDocumento(
                    (int) $coincidencias[1],
                    $archivo
                );

                $this->responder(200, [
                    'mensaje' => 'Documento PDF registrado correctamente.',
                    'contrato' => $resultado
                ]);
                return;
            }

            $this->responder(404, [
                'mensaje' => 'Ruta administrativa de contratos no encontrada.'
            ]);

        } catch (OutOfBoundsException $error) {
            $this->responder(404, [
                'mensaje' => $error->getMessage()
            ]);

        } catch (InvalidArgumentException $error) {
            $this->responder(422, [
                'mensaje' => $error->getMessage()
            ]);

        } catch (DomainException $error) {
            $this->responder(409, [
                'mensaje' => $error->getMessage()
            ]);

        } catch (PDOException $error) {
            error_log($error->getMessage());

            $this->responder(500, [
                'mensaje' => 'No fue posible consultar la base de datos.'
            ]);

        } catch (Throwable $error) {
            error_log($error->getMessage());

            $this->responder(500, [
                'mensaje' => 'No fue posible completar la operación de contratos.'
            ]);
        }
    }

    private function leerJson(): array
    {
        $contenido = file_get_contents(
            'php://input',
            false,
            null,
            0,
            self::LIMITE_JSON_BYTES + 1
        );

        if (
            $contenido === false
            || strlen($contenido) > self::LIMITE_JSON_BYTES
        ) {
            throw new InvalidArgumentException(
                'La solicitud es demasiado grande.'
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
            throw new InvalidArgumentException(
                'El JSON enviado no es válido.'
            );
        }

        if (
            !is_array($datos)
            || array_is_list($datos)
        ) {
            throw new InvalidArgumentException(
                'Se requiere un objeto JSON válido.'
            );
        }

        return $datos;
    }

    private function origenPermitido(): bool
    {
        $origen = trim(
            (string) ($_SERVER['HTTP_ORIGIN'] ?? '')
        );

        $protocolo = !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
                ? 'https'
                : 'http';

        $servidor = $protocolo . '://'
            . (string) ($_SERVER['HTTP_HOST'] ?? '');

        $permitidos = [
            $servidor,
            'http://localhost:5173',
            'http://127.0.0.1:5173'
        ];

        $origenProduccion = getenv('ALKIRA_ORIGEN_WEB');

        if (
            is_string($origenProduccion)
            && str_starts_with(
                $origenProduccion,
                'https://'
            )
        ) {
            $permitidos[] = rtrim(
                $origenProduccion,
                '/'
            );
        }

        if (
            $origen === ''
            || !in_array($origen, $permitidos, true)
        ) {
            $this->responder(403, [
                'mensaje' => 'Origen de solicitud no permitido.'
            ]);
            return false;
        }

        return true;
    }

    private function responder(
        int $codigo,
        array $datos
    ): void {
        http_response_code($codigo);

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
