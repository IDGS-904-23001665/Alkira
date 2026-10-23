<?php
declare(strict_types=1);

final class AdeudosRepositorio
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    /** @return array<int, array<string, mixed>> */
    public function obtenerPendientes(int $idInquilino): array
    {
        if ($idInquilino <= 0) {
            throw new InvalidArgumentException(
                'El identificador del inquilino no es válido.'
            );
        }

        $sentencia = $this->conexion->prepare(
            'CALL sp_adeudos(:idInquilino)'
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
}
