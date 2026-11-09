<?php
declare(strict_types=1);

final class ContratosRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    /**
     * Consulta los contratos del inquilino.
     *
     * El filtrado de borradores para el listado
     * se realizará en la capa de servicio.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPorInquilino(int $idInquilino): array
    {
        if ($idInquilino <= 0) {
            throw new InvalidArgumentException(
                'El identificador del inquilino no es válido.'
            );
        }

        $sentencia = $this->conexion->prepare(
            'CALL sp_contratos(:idInquilino)'
        );

        $sentencia->bindValue(
            ':idInquilino',
            $idInquilino,
            PDO::PARAM_INT
        );

        $sentencia->execute();

        try {
            return $sentencia->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $sentencia->closeCursor();
        }
    }

    /**
     * Devuelve un documento únicamente cuando:
     * - El contrato pertenece al inquilino.
     * - El contrato no está en borrador.
     *
     * @return array<string, mixed>|false
     */
    public function obtenerDocumento(
        int $idContrato,
        int $idInquilino
    ): array|false {
        if ($idContrato <= 0 || $idInquilino <= 0) {
            return false;
        }

        $sentencia = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                id_contrato,
                ruta_documento
            FROM contratos
            WHERE id_contrato = :idContrato
              AND id_inquilino = :idInquilino
              AND estado_contrato <> 'borrador'
            LIMIT 1
            SQL
        );

        $sentencia->bindValue(
            ':idContrato',
            $idContrato,
            PDO::PARAM_INT
        );

        $sentencia->bindValue(
            ':idInquilino',
            $idInquilino,
            PDO::PARAM_INT
        );

        $sentencia->execute();

        return $sentencia->fetch(PDO::FETCH_ASSOC);
    }
}
