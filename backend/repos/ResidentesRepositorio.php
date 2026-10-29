<?php
declare(strict_types=1);

final class ResidentesRepositorio
{
    public function __construct(private PDO $conexion) {}

    public function listarActivos(): array
    {
        return $this->conexion->query(
            "SELECT
                u.id_usuario AS idUsuario,
                CONCAT(u.nombres, ' ', u.apellidos) AS nombreCompleto,
                u.correo,
                u.tipo_usuario AS tipoUsuario,
                up.id_unidad AS idUnidad,
                pu.numero_unidad AS numeroUnidad,
                pu.id_inmueble AS idInmueble,
                inm.nombre AS nombreInmueble
            FROM usuarios AS u
            LEFT JOIN usuarios_propiedades AS up
                ON up.id_usuario = u.id_usuario
                AND up.fecha_inicio <= CURRENT_DATE()
                AND (up.fecha_fin IS NULL OR up.fecha_fin >= CURRENT_DATE())
            LEFT JOIN propiedades_unidades AS pu ON pu.id_unidad = up.id_unidad
            LEFT JOIN inmuebles AS inm ON inm.id_inmueble = pu.id_inmueble
            WHERE u.activo = 1 AND u.estado = 'activo'
              AND u.tipo_usuario IN ('inquilino', 'propietario')
            ORDER BY u.nombres, u.apellidos"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

}
