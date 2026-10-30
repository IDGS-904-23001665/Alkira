<?php
declare(strict_types=1);

final class ContratosRepositorio
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

        $sentencia = $this->conexion->prepare('CALL sp_contratos(:idInquilino)');
        $sentencia->bindValue(':idInquilino', $idInquilino, PDO::PARAM_INT);
        $sentencia->execute();
        try {
            return $sentencia->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $sentencia->closeCursor();
        }
    }

    /** @return array<string, mixed>|false */
    public function obtenerDocumento(int $idContrato, int $idInquilino): array|false
    {
        $sentencia = $this->conexion->prepare(
            'SELECT id_contrato, ruta_documento
             FROM contratos
             WHERE id_contrato = :idContrato
               AND id_inquilino = :idInquilino
             LIMIT 1'
        );
        $sentencia->bindValue(':idContrato', $idContrato, PDO::PARAM_INT);
        $sentencia->bindValue(':idInquilino', $idInquilino, PDO::PARAM_INT);
        $sentencia->execute();
        return $sentencia->fetch(PDO::FETCH_ASSOC);
    }
}
