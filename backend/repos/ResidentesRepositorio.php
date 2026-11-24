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

    public function listar(): array
{
    $residentes = $this->conexion->query(
        "SELECT
            id_usuario AS idUsuario,
            nombres,
            apellidos,
            correo,
            telefono,
            tipo_usuario AS tipoUsuario,
            estado,
            activo,
            fecha_registro AS fechaRegistro
        FROM usuarios
        WHERE tipo_usuario IN ('inquilino', 'propietario')
        ORDER BY nombres, apellidos"
    )->fetchAll(PDO::FETCH_ASSOC);

    $asignaciones = $this->conexion->query(
        "SELECT
            up.id_usuario AS idUsuario,
            up.id_usuario_propiedad AS idAsignacion,
            up.id_unidad AS idUnidad,
            up.relacion,
            up.fecha_inicio AS fechaInicio,
            up.fecha_fin AS fechaFin,
            pu.numero_unidad AS numeroUnidad,
            i.nombre AS nombreInmueble
        FROM usuarios_propiedades up
        INNER JOIN propiedades_unidades pu
            ON pu.id_unidad = up.id_unidad
        INNER JOIN inmuebles i
            ON i.id_inmueble = pu.id_inmueble
        INNER JOIN usuarios u
            ON u.id_usuario = up.id_usuario
        WHERE u.tipo_usuario IN ('inquilino', 'propietario')
        AND up.fecha_inicio <= CURRENT_DATE()
        AND (
            up.fecha_fin IS NULL
            OR up.fecha_fin > CURRENT_DATE()
        )
        ORDER BY i.nombre, pu.numero_unidad"
    )->fetchAll(PDO::FETCH_ASSOC);

    $mapa = [];

    foreach ($asignaciones as $asignacion) {
        $mapa[$asignacion['idUsuario']][] = $asignacion;
    }

    foreach ($residentes as &$residente) {
        $residente['asignaciones'] =
            $mapa[$residente['idUsuario']] ?? [];
    }

    unset($residente);

    return $residentes;
}

public function buscar(int $id): ?array
{
    $consulta = $this->conexion->prepare(
        "SELECT
            id_usuario AS idUsuario,
            nombres,
            apellidos,
            correo,
            telefono,
            tipo_usuario AS tipoUsuario,
            estado,
            activo
        FROM usuarios
        WHERE id_usuario = :id
        AND tipo_usuario IN ('inquilino', 'propietario')"
    );

    $consulta->execute(['id' => $id]);

    return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
}

public function correoExiste(
    string $correo,
    ?int $id = null
): bool {
    $sql = "SELECT COUNT(*)
            FROM usuarios
            WHERE correo = :correo";

    $parametros = ['correo' => $correo];

    if ($id !== null) {
        $sql .= " AND id_usuario <> :id";
        $parametros['id'] = $id;
    }

    $consulta = $this->conexion->prepare($sql);
    $consulta->execute($parametros);

    return (int) $consulta->fetchColumn() > 0;
}

public function crear(array $datos): int
{
    $consulta = $this->conexion->prepare(
        "INSERT INTO usuarios (
            nombres,
            apellidos,
            correo,
            telefono,
            contrasena_hash,
            tipo_usuario,
            estado,
            activo
        ) VALUES (
            :nombres,
            :apellidos,
            :correo,
            :telefono,
            :contrasenaHash,
            :tipoUsuario,
            'activo',
            1
        )"
    );

    $consulta->execute($datos);

    return (int) $this->conexion->lastInsertId();
}

public function actualizar(int $id, array $datos): void
{
    $sql = "UPDATE usuarios SET
        nombres = :nombres,
        apellidos = :apellidos,
        correo = :correo,
        telefono = :telefono,
        tipo_usuario = :tipoUsuario";

    if (isset($datos['contrasenaHash'])) {
        $sql .= ", contrasena_hash = :contrasenaHash";
    }

    $sql .= " WHERE id_usuario = :id
              AND tipo_usuario IN ('inquilino', 'propietario')";

    $datos['id'] = $id;

    $consulta = $this->conexion->prepare($sql);
    $consulta->execute($datos);
}

public function cambiarEstado(int $id, bool $activo): void
{
    $consulta = $this->conexion->prepare(
        "UPDATE usuarios
        SET activo = :activo,
            estado = :estado
        WHERE id_usuario = :id
        AND tipo_usuario IN ('inquilino', 'propietario')
        AND estado <> 'bloqueado'"
    );

    $consulta->execute([
        'activo' => $activo ? 1 : 0,
        'estado' => $activo ? 'activo' : 'inactivo',
        'id' => $id
    ]);
}

}
