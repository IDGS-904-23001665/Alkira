<?php
declare(strict_types=1);

final class KddRepositorio
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerResumenFinanciero(): array
    {
        return $this->consultarResumen(
            'CALL sp_kdd_resumen_financiero()'
        );
    }

    public function obtenerResumenOperativo(): array
    {
        return $this->consultarResumen(
            'CALL sp_kdd_resumen_operativo()'
        );
    }

    private function consultarResumen(string $consulta): array
    {
        $sentencia = $this->conexion->prepare($consulta);

        $sentencia->execute();

        try {
            $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);

            if (!is_array($resultado)) {
                throw new UnexpectedValueException(
                    'No se encontraron resultados KDD.'
                );
            }

            return $resultado;
        } finally {
            $sentencia->closeCursor();
        }
    }
}