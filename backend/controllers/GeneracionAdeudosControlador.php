<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/GeneracionAdeudosServicio.php';
require_once dirname(__DIR__) . '/services/EjecucionAdeudosServicio.php';

final class GeneracionAdeudosControlador
{
    private const LIMITE_JSON_BYTES = 2048;

    public function __construct(
        private GeneracionAdeudosServicio $servicio,
        private ?EjecucionAdeudosServicio $ejecutor = null
    ) {
    }

    public function procesar(
        string $metodo,
        string $ruta,
        array $usuario
    ): void {
        if (($usuario['tipoUsuario'] ?? null) !== 'administrador') {
            $this->responder(403, [
                'mensaje' => 'No tienes permiso para administrar adeudos.'
            ]);
            return;
        }

        try {
            // Simulación: solamente consulta datos.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/adeudos/simulacion'
            ) {
                $periodo = $this->validarPeriodo(
                    $_GET['periodo'] ?? null
                );

                $simulacion = $this->servicio
                    ->simularPeriodo($periodo);

                $this->responder(200, [
                    'mensaje' => 'Simulación realizada correctamente.',
                    'simulacion' => $simulacion
                ]);
                return;
            }

            // Generación real: todavía no está
            // conectada al enrutador principal.
            if (
                $metodo === 'POST'
                && $ruta === '/api/administracion/adeudos/generar'
            ) {
                if (!$this->origenPermitido()) {
                    return;
                }

                $datos = $this->leerJson();

                $periodo = $this->validarPeriodo(
                    $datos['periodo'] ?? null
                );

                if (($datos['confirmar'] ?? null) !== true) {
                    throw new InvalidArgumentException(
                        'Debes confirmar explícitamente la generación de adeudos.'
                    );
                }

                // Protección mientras la ejecución
                // todavía no está habilitada.
                if ($this->ejecutor === null) {
                    $this->responder(503, [
                        'mensaje' => 'La generación real todavía no está habilitada.'
                    ]);
                    return;
                }

                $resultado = $this->ejecutor
                    ->generarPeriodo($periodo);

                $this->responder(200, [
                    'mensaje' => 'Proceso de generación finalizado.',
                    'generacion' => $resultado
                ]);
                return;
            }

            $this->responder(404, [
                'mensaje' => 'Ruta de generación de adeudos no encontrada.'
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

            $esDuplicado = $error->getCode() === '23000'
                && (int) ($error->errorInfo[1] ?? 0) === 1062;

            $this->responder(
                $esDuplicado ? 409 : 500,
                [
                    'mensaje' => $esDuplicado
                        ? 'Ya existe un cobro para ese contrato, concepto y periodo.'
                        : 'No fue posible completar la operación financiera.'
                ]
            );

        } catch (Throwable $error) {
            error_log($error->getMessage());

            $this->responder(500, [
                'mensaje' => 'No fue posible completar la operación.'
            ]);
        }
    }

    private function validarPeriodo(mixed $periodo): string
    {
        if (
            !is_string($periodo)
            || preg_match(
                '/^[0-9]{4}-(0[1-9]|1[0-2])$/D',
                $periodo
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Indica un periodo válido con formato YYYY-MM.'
            );
        }

        return $periodo;
    }

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
                16,
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

    private function origenPermitido(): bool
    {
        $origen = trim(
            (string) ($_SERVER['HTTP_ORIGIN'] ?? '')
        );

        $protocolo = (
            !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
        ) ? 'https' : 'http';

        $origenesPermitidos = [
            $protocolo . '://'
                . (string) ($_SERVER['HTTP_HOST'] ?? ''),
            'http://localhost:5173',
            'http://127.0.0.1:5173'
        ];

        $origenProduccion = getenv(
            'ALKIRA_ORIGEN_WEB'
        );

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

    private function responder(
        int $codigo,
        array $datos
    ): void {
        http_response_code($codigo);

        header(
            'Content-Type: application/json; charset=utf-8'
        );
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
