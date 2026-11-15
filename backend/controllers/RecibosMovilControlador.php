<?php
declare(strict_types=1);

/**
 * HU04.1 - Consulta de recibos desde Android.
 *
 * Solo el inquilino titular de un pago aprobado
 * puede descargar su recibo.
 */
final class RecibosMovilControlador
{
    public function __construct(
        private PDO $conexion,
        private ConciliacionRepositorio $conciliacion
    ) {}

    public function procesar(
        int $idPago,
        array $usuario
    ): void {

        // Verificar el perfil del usuario.
        if (
            ($usuario['tipoUsuario'] ?? '') !== 'inquilino'
        ) {

            http_response_code(403);

            echo json_encode([
                'mensaje' => 'Acceso exclusivo para inquilinos.'
            ]);

            return;
        }

        // Verificar que el pago pertenece al inquilino.
        $consulta = $this->conexion->prepare("
            SELECT p.id_pago

            FROM pagos_transacciones p

            JOIN estados_cuenta ec
                ON ec.id_estado_cuenta = p.id_estado_cuenta

            JOIN contratos c
                ON c.id_contrato = ec.id_contrato

            WHERE p.id_pago = :idPago
              AND c.id_inquilino = :idUsuario
              AND p.estado = 'aprobado'

            LIMIT 1
        ");

        $consulta->execute([
            'idPago' => $idPago,
            'idUsuario' => (int) $usuario['idUsuario']
        ]);

        if ($consulta->fetchColumn() === false) {

            http_response_code(404);

            echo json_encode([
                'mensaje' => 'Recibo no disponible.'
            ]);

            return;
        }

        // Obtener el recibo de la conciliación web.
        $datos = $this->conciliacion->recibo($idPago);

        if ($datos === null) {

            http_response_code(404);

            echo json_encode([
                'mensaje' => 'Recibo no emitido.'
            ]);

            return;
        }

        // Generar el mismo PDF que utiliza HU04.2.
        (new ConciliacionControlador(
            $this->conciliacion
        ))->emitirPdf($datos);
    }
}
