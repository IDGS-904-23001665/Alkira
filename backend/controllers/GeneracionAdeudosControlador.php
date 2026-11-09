<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/services/GeneracionAdeudosServicio.php';

final class GeneracionAdeudosControlador
{
    public function __construct(
        private GeneracionAdeudosServicio $servicio
    ) {
    }

    public function procesar(
        string $metodo,
        string $ruta,
        array $usuario
    ): void {
        // La simulación es exclusiva de administración.
        if (
            ($usuario['tipoUsuario'] ?? null)
            !== 'administrador'
        ) {
            $this->responder(403, [
                'mensaje' => 'No tienes permiso para simular adeudos.'
            ]);

            return;
        }

        try {
            // HU02.3: simulación mensual sin modificar MySQL.
            if (
                $metodo === 'GET'
                && $ruta === '/api/administracion/adeudos/simulacion'
            ) {
                $periodo = $_GET['periodo'] ?? null;

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

                $resultado = $this->servicio
                    ->simularPeriodo($periodo);

                $this->responder(200, [
                    'mensaje' => 'Simulación realizada correctamente.',
                    'simulacion' => $resultado
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

            $this->responder(500, [
                'mensaje' => 'No fue posible consultar los datos financieros.'
            ]);

        } catch (Throwable $error) {
            error_log($error->getMessage());

            $this->responder(500, [
                'mensaje' => 'No fue posible realizar la simulación.'
            ]);
        }
    }

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
