<?php
declare(strict_types=1);

final class UsuariosRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    public function buscarPorCorreo(string $correo): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT id_usuario, nombres, apellidos, correo, contrasena_hash, tipo_usuario
             FROM usuarios
             WHERE correo = :correo AND estado = :estado AND activo = 1
             LIMIT 1'
        );
        $consulta->execute(['correo' => $correo, 'estado' => 'activo']);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $idUsuario): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT id_usuario, nombres, apellidos, correo, tipo_usuario
             FROM usuarios
             WHERE id_usuario = :idUsuario AND estado = :estado AND activo = 1
             LIMIT 1'
        );
        $consulta->bindValue(':idUsuario', $idUsuario, PDO::PARAM_INT);
        $consulta->bindValue(':estado', 'activo');
        $consulta->execute();
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }
}
