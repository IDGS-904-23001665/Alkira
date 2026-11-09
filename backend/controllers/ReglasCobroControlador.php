<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/services/ReglasCobroServicio.php';

final class ReglasCobroControlador
{
    private const LIMITE_JSON_BYTES = 16384;

    public function __construct(
        private ReglasCobroServicio $servicio
    ) {
    }

    public function procesar(
        string $metodo,
        string $ruta,
        array $usuario
    ): void {
        // Solo los administradores pueden configurar cobros.
        if (($usuario['tipoUsuario'] ?? '') !== 'administrador') {
            $this->responder(403, [
                'mensaje' => 'No tienes permisos para administrar reglas de cobro.'
            ]);
            return;
        }

        try {

            // GET: listar todas las reglas.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/reglas-cobro'
            ) {
                $this->responder(200, [
                    'reglas' => $this->servicio->listarReglas()
                ]);
                return;
            }

            // GET: conceptos disponibles de mantenimiento.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/reglas-cobro/conceptos'
            ) {
                $this->responder(200, [
                    'conceptos' => $this->servicio
                        ->listarConceptosMantenimiento()
                ]);
                return;
            }

            // GET: consultar una regla específica.
            if (
                $metodo === 'GET'
                && preg_match(
                    '#^/api/administracion/reglas-cobro/([1-9][0-9]*)$#D',
                    $ruta,
                    $coincidencias
                ) === 1
            ) {
                $idReglaCobro = (int) $coincidencias[1];

                $this->responder(200, [
                    'regla' => $this->servicio
                        ->obtenerRegla($idReglaCobro)
                ]);
                return;
            }

            // POST: registrar una nueva regla de cobro.
            if (
                $metodo === 'POST'
                && $ruta === '/api/administracion/reglas-cobro'
            ) {
                if (!$this->origenPermitido()) {
                    return;
                }

                $datos = $this->leerJson();

                $regla = $this->servicio->crearRegla(
                    $datos
                );

                $this->responder(201, [
                    'mensaje' => 'Regla de cobro registrada correctamente.',
                    'regla' => $regla
                ]);
                return;
            }

            // PUT: actualizar una regla existente.
            if (
                $metodo === 'PUT'
                && preg_match(
                    '#^/api/administracion/reglas-cobro/([1-9][0-9]*)$#D',
                    $ruta,
                    $coincidencias
                ) === 1
            ) {
                if (!$this->origenPermitido()) {
                    return;
                }

                $idReglaCobro = (int) $coincidencias[1];

                $datos = $this->leerJson();

                $regla = $this->servicio->actualizarRegla(
                    $idReglaCobro,
                    $datos
                );

                $this->responder(200, [
                    'mensaje' => 'Regla de cobro actualizada correctamente.',
                    'regla' => $regla
                ]);
                return;
            }

            // Ruta no encontrada.
            $this->responder(404, [
                'mensaje' => 'Ruta de reglas de cobro no encontrada.'
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

            // Una restricción UNIQUE puede impedir
            // configuraciones duplicadas simultáneas.
            $esDuplicado = $error->getCode() === '23000'
                && (int) ($error->errorInfo[1] ?? 0) === 1062;

            if ($esDuplicado) {
                $this->responder(409, [
                    'mensaje' => 'Ya existe una regla con esa configuración.'
                ]);
                return;
            }

            $this->responder(500, [
                'mensaje' => 'No fue posible consultar la base de datos.'
            ]);

        } catch (Throwable $error) {
            error_log($error->getMessage());

            $this->responder(500, [
                'mensaje' => 'No fue posible completar la operación.'
            ]);
        }
    }

    /**
     * Lee un objeto JSON con un límite de 16 KB.
     */
    private function leerJson(): array
    {
        $tipoContenido = strtolower(
            trim((string) ($_SERVER['CONTENT_TYPE'] ?? ''))
        );

        if (
            preg_match(
                '~^application/json(?:\s*;|\s*$)~',
                $tipoContenido
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'La solicitud debe enviarse en formato JSON.'
            );
        }

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
                'La solicitud supera el tamaño permitido.'
            );
        }

        try {
            $datos = json_decode(
                $contenido,
                false,
                32,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException(
                'El JSON enviado no es válido.'
            );
        }

        if (!$datos instanceof stdClass) {
            throw new InvalidArgumentException(
                'Debes enviar un objeto JSON.'
            );
        }

        return (array) $datos;
    }

    /**
     * Verifica el origen de las solicitudes que modifican datos.
     */
    private function origenPermitido(): bool
    {
        $origen = trim(
            (string) ($_SERVER['HTTP_ORIGIN'] ?? '')
        );

        $protocolo = (
            !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
        ) ? 'https' : 'http';

        $servidor = $protocolo . '://'
            . (string) ($_SERVER['HTTP_HOST'] ?? '');

        $origenesPermitidos = [
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
            $origenesPermitidos[] = rtrim(
                $origenProduccion,
                '/'
            );
        }

        if (
            $origen === ''
            || !in_array(
                $origen,
                $origenesPermitidos,
                true
            )
        ) {
            $this->responder(403, [
                'mensaje' => 'Origen de solicitud no permitido.'
            ]);

            return false;
        }

        return true;
    }

    /**
     * Devuelve una respuesta JSON.
     */
    private function responder(
        int $codigo,
        array $datos
    ): void {
        http_response_code($codigo);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
