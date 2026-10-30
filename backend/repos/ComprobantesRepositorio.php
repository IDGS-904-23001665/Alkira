<?php
declare(strict_types=1);

final class ComprobantesRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    /**
     * Comprobantes recibidos con datos de transferencia y titular del contrato.
     */
    public function obtenerTodos(): array
    {
        $sql = "
            SELECT
                c.id_comprobante,
                c.id_pago,
                c.ruta_archivo,
                c.tipo_archivo,
                c.fecha_carga,
                c.estado_revision,
                c.motivo_revision,
                p.estado AS estado_pago,
                p.monto,
                p.metodo_pago,
                p.referencia_bancaria,
                u.nombres,
                u.apellidos
            FROM comprobantes_pago AS c
            INNER JOIN pagos_transacciones AS p
                ON c.id_pago = p.id_pago
            INNER JOIN estados_cuenta AS ec
                ON ec.id_estado_cuenta = p.id_estado_cuenta
            INNER JOIN contratos AS ct
                ON ct.id_contrato = ec.id_contrato
            INNER JOIN usuarios AS u
                ON u.id_usuario = ct.id_inquilino
            ORDER BY
                c.fecha_carga DESC,
                c.id_comprobante DESC
        ";

        $consulta = $this->conexion->query($sql);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * HU03.2: confirmar el preregistro de un pago existente para iniciar
     * la verificacion, sin aprobarlo ni rechazarlo.
     */
    public function confirmarPreregistro(
        int $idComprobante,
        int $idAdministrador
    ): bool {
        if ($idComprobante <= 0 || $idAdministrador <= 0) {
            throw new InvalidArgumentException('Identificadores invalidos.');
        }

        $this->conexion->beginTransaction();

        try {
            // Bloquear el comprobante y el pago relacionado.
            $consulta = $this->conexion->prepare("
                SELECT
                    c.id_pago,
                    c.estado_revision,
                    p.estado AS estado_pago
                FROM comprobantes_pago AS c
                INNER JOIN pagos_transacciones AS p
                    ON p.id_pago = c.id_pago
                WHERE c.id_comprobante = :id
                FOR UPDATE
            ");

            $consulta->bindValue(':id', $idComprobante, PDO::PARAM_INT);
            $consulta->execute();
            $registro = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                $this->conexion->rollBack();
                return false;
            }

            if ($registro['estado_revision'] !== 'pendiente') {
                throw new DomainException('Este comprobante ya fue revisado.');
            }

            if ($registro['estado_pago'] !== 'preregistrado') {
                throw new DomainException(
                    'El pago ya fue enviado a verificacion o no esta disponible.'
                );
            }

            // Actualizar solamente la transaccion existente.
            $actualizarPago = $this->conexion->prepare("
                UPDATE pagos_transacciones
                SET estado = 'en_revision'
                WHERE id_pago = :idPago
                  AND estado = 'preregistrado'
            ");

            $actualizarPago->bindValue(
                ':idPago',
                (int) $registro['id_pago'],
                PDO::PARAM_INT
            );
            $actualizarPago->execute();

            if ($actualizarPago->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se pudo actualizar el estado del pago.'
                );
            }

            $guardarHistorial = $this->conexion->prepare("
                INSERT INTO historial_estatus_pago (
                    id_pago,
                    estado_anterior,
                    estado_nuevo,
                    id_usuario_responsable,
                    comentario
                )
                VALUES (
                    :idPago,
                    'preregistrado',
                    'en_revision',
                    :idAdministrador,
                    :comentario
                )
            ");

            $guardarHistorial->execute([
                'idPago' => (int) $registro['id_pago'],
                'idAdministrador' => $idAdministrador,
                'comentario' =>
                    'Preregistro confirmado por administracion. Enviado a verificacion.'
            ]);

            $this->conexion->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            throw $error;
        }
    }
}
