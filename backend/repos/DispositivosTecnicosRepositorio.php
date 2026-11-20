<?php
declare(strict_types=1);

final class DispositivosTecnicosRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    public function registrarToken(int $idUsuario, string $token): void
    {
        $consulta = $this->conexion->prepare(
            "INSERT INTO hu07_dispositivos (id_usuario, token_fcm)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                 id_usuario = ?,
                 actualizado_en = NOW()"
        );

        $consulta->execute([$idUsuario, $token, $idUsuario]);
    }

    public function eliminarToken(int $idUsuario, string $token): void
    {
        $consulta = $this->conexion->prepare(
            "DELETE FROM hu07_dispositivos
             WHERE id_usuario = ? AND token_fcm = ?"
        );

        $consulta->execute([$idUsuario, $token]);
    }
}
