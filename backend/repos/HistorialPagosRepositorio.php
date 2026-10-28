<?php
declare(strict_types=1);

final class HistorialPagosRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function obtenerPorInquilino(int $idInquilino): array
    {
        if ($idInquilino <= 0) {
            throw new InvalidArgumentException('El identificador del inquilino no es válido.');
        }

        $sentencia = $this->conexion->prepare(
            'SELECT
                p.id_pago AS idPago,
                p.fecha_pago AS fechaPago,
                p.monto,
                p.metodo_pago AS metodoPago,
                p.referencia_bancaria AS referenciaBancaria,
                p.estado,
                cc.nombre AS concepto,
                ec.periodo,
                pu.numero_unidad AS numeroUnidad
             FROM pagos_transacciones AS p
             INNER JOIN estados_cuenta AS ec
                ON ec.id_estado_cuenta = p.id_estado_cuenta
             INNER JOIN contratos AS c
                ON c.id_contrato = ec.id_contrato
             INNER JOIN conceptos_cobro AS cc
                ON cc.id_concepto = ec.id_concepto
             INNER JOIN propiedades_unidades AS pu
                ON pu.id_unidad = c.id_unidad
             WHERE c.id_inquilino = :idInquilino
             ORDER BY COALESCE(p.fecha_pago, \'1000-01-01\') DESC, p.id_pago DESC'
        );
        $sentencia->bindValue(':idInquilino', $idInquilino, PDO::PARAM_INT);
        $sentencia->execute();
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }
}
