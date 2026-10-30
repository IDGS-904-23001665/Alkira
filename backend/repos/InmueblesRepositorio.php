<?php

declare(strict_types=1);

final class InmueblesRepositorio
{
    public function __construct(private PDO $conexion) {}

    public function listar(): array
    {
        return $this->conexion->query('SELECT id_inmueble AS idInmueble, nombre, tipo, direccion, ciudad, estado_geografico AS estadoGeografico, codigo_postal AS codigoPostal, descripcion, estado_registro AS estadoRegistro, id_inmueble_padre AS idInmueblePadre FROM inmuebles WHERE activo = 1 ORDER BY id_inmueble')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): array|false
    {
        $consulta = $this->conexion->prepare('SELECT id_inmueble AS idInmueble, nombre, tipo, direccion, ciudad, estado_geografico AS estadoGeografico, codigo_postal AS codigoPostal, descripcion, estado_registro AS estadoRegistro, id_inmueble_padre AS idInmueblePadre FROM inmuebles WHERE id_inmueble = :id AND activo = 1');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare('INSERT INTO inmuebles (nombre,tipo,direccion,ciudad,estado_geografico,codigo_postal,descripcion,id_inmueble_padre) VALUES (:nombre,:tipo,:direccion,:ciudad,:estadoGeografico,:codigoPostal,:descripcion,:idInmueblePadre)');
        $consulta->execute($datos);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $datos['id'] = $id;
        $consulta = $this->conexion->prepare('UPDATE inmuebles SET nombre=:nombre,tipo=:tipo,direccion=:direccion,ciudad=:ciudad,estado_geografico=:estadoGeografico,codigo_postal=:codigoPostal,descripcion=:descripcion,id_inmueble_padre=:idInmueblePadre WHERE id_inmueble=:id AND activo=1');
        $consulta->execute($datos);
        return $this->buscar($id) !== false;
    }

    public function listarUnidades(int $idInmueble): array
    {
        $consulta = $this->conexion->prepare('SELECT id_unidad AS idUnidad, id_inmueble AS idInmueble, numero_unidad AS numeroUnidad, tipo_unidad AS tipoUnidad, superficie_m2 AS superficieM2, cajones_estacionamiento AS cajonesEstacionamiento, tiene_bodega AS tieneBodega, servicios_incluidos AS serviciosIncluidos, descripcion, estado_unidad AS estadoUnidad FROM propiedades_unidades WHERE id_inmueble=:id AND activo=1 ORDER BY numero_unidad');
        $consulta->execute(['id' => $idInmueble]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearUnidad(array $datos): int
    {
        $consulta = $this->conexion->prepare('INSERT INTO propiedades_unidades (id_inmueble,numero_unidad,tipo_unidad,superficie_m2,cajones_estacionamiento,tiene_bodega,servicios_incluidos,descripcion,estado_unidad) VALUES (:idInmueble,:numeroUnidad,:tipoUnidad,:superficieM2,:cajonesEstacionamiento,:tieneBodega,:serviciosIncluidos,:descripcion,:estadoUnidad)');
        $consulta->execute($datos);
        return (int) $this->conexion->lastInsertId();
    }

    public function buscarUnidad(int $id): array|false
    {
        $consulta = $this->conexion->prepare('SELECT id_unidad AS idUnidad, id_inmueble AS idInmueble, numero_unidad AS numeroUnidad, tipo_unidad AS tipoUnidad, superficie_m2 AS superficieM2, cajones_estacionamiento AS cajonesEstacionamiento, tiene_bodega AS tieneBodega, servicios_incluidos AS serviciosIncluidos, descripcion, estado_unidad AS estadoUnidad FROM propiedades_unidades WHERE id_unidad=:id AND activo=1');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarUnidad(int $id, array $datos): bool
    {
        $datos['id'] = $id;
        $consulta = $this->conexion->prepare('UPDATE propiedades_unidades SET numero_unidad=:numeroUnidad, tipo_unidad=:tipoUnidad, superficie_m2=:superficieM2, cajones_estacionamiento=:cajonesEstacionamiento, tiene_bodega=:tieneBodega, servicios_incluidos=:serviciosIncluidos, descripcion=:descripcion, estado_unidad=:estadoUnidad WHERE id_unidad=:id AND activo=1');
        $consulta->execute($datos);
        return $this->buscarUnidad($id) !== false;
    }

    public function desactivarInmueble(int $id): bool
    {
        $consulta = $this->conexion->prepare("UPDATE inmuebles SET activo=0, estado_registro='inactivo' WHERE id_inmueble=:id AND activo=1");
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() > 0;
    }

    public function desactivarUnidad(int $id): bool
    {
        $consulta = $this->conexion->prepare("UPDATE propiedades_unidades SET activo=0, estado_unidad='inactiva' WHERE id_unidad=:id AND activo=1");
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() > 0;
    }

    public function contarHijosActivos(int $id): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM inmuebles WHERE id_inmueble_padre=:id AND activo=1');
        $consulta->execute(['id' => $id]);
        return (int) $consulta->fetchColumn();
    }

    public function contarUnidadesActivas(int $id): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM propiedades_unidades WHERE id_inmueble=:id AND activo=1');
        $consulta->execute(['id' => $id]);
        return (int) $consulta->fetchColumn();
    }

    public function contarRelacionesVigentes(int $idUnidad): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM usuarios_propiedades WHERE id_unidad=:id AND fecha_inicio<=CURRENT_DATE() AND (fecha_fin IS NULL OR fecha_fin>CURRENT_DATE())');
        $consulta->execute(['id' => $idUnidad]);
        return (int) $consulta->fetchColumn();
    }

    public function contarContratosVigentes(int $idUnidad): int
    {
        $consulta = $this->conexion->prepare("SELECT COUNT(*) FROM contratos WHERE id_unidad=:id AND estado_contrato='vigente'");
        $consulta->execute(['id' => $idUnidad]);
        return (int) $consulta->fetchColumn();
    }

    public function buscarUsuarios(string $consultaTexto): array
    {
        $consulta = $this->conexion->prepare("SELECT id_usuario AS idUsuario, nombres, apellidos, correo, tipo_usuario AS tipoUsuario FROM usuarios WHERE activo=1 AND estado='activo' AND (correo LIKE :correo OR CONCAT(nombres,' ',apellidos) LIKE :nombre) ORDER BY nombres, apellidos LIMIT 30");
        $termino = '%' . $consultaTexto . '%';
        $consulta->execute(['correo' => $termino, 'nombre' => $termino]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarUsuario(int $id): array|false
    {
        $consulta = $this->conexion->prepare("SELECT id_usuario AS idUsuario, tipo_usuario AS tipoUsuario FROM usuarios WHERE id_usuario=:id AND activo=1 AND estado='activo'");
        $consulta->execute(['id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function listarAsignaciones(int $idUnidad): array
    {
        $consulta = $this->conexion->prepare('SELECT up.id_usuario_propiedad AS idAsignacion, up.id_usuario AS idUsuario, up.id_unidad AS idUnidad, up.relacion, up.fecha_inicio AS fechaInicio, up.fecha_fin AS fechaFin, up.es_principal AS esPrincipal, u.nombres, u.apellidos, u.correo FROM usuarios_propiedades up INNER JOIN usuarios u ON u.id_usuario=up.id_usuario WHERE up.id_unidad=:id ORDER BY up.fecha_inicio DESC, up.id_usuario_propiedad DESC');
        $consulta->execute(['id' => $idUnidad]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAsignacion(int $id): array|false
    {
        $consulta = $this->conexion->prepare('SELECT id_usuario_propiedad AS idAsignacion, id_usuario AS idUsuario, id_unidad AS idUnidad, relacion, fecha_inicio AS fechaInicio, fecha_fin AS fechaFin FROM usuarios_propiedades WHERE id_usuario_propiedad=:id');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function crearAsignacion(array $datos): int
    {
        $consulta = $this->conexion->prepare('INSERT INTO usuarios_propiedades (id_usuario,id_unidad,relacion,fecha_inicio,fecha_fin,es_principal) VALUES (:idUsuario,:idUnidad,:relacion,:fechaInicio,:fechaFin,:esPrincipal)');
        $consulta->execute($datos);
        return (int)$this->conexion->lastInsertId();
    }

    public function cerrarAsignacion(int $id, string $fechaFin): bool
    {
        $consulta = $this->conexion->prepare('UPDATE usuarios_propiedades SET fecha_fin=:fechaFin WHERE id_usuario_propiedad=:id AND (fecha_fin IS NULL OR fecha_fin>:fechaCorte)');
        $consulta->execute(['id' => $id, 'fechaFin' => $fechaFin, 'fechaCorte' => $fechaFin]);
        return $consulta->rowCount() > 0;
    }

    public function tieneAsignacionSolapada(int $idUsuario, int $idUnidad, string $fechaInicio, ?string $fechaFin): bool
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM usuarios_propiedades WHERE id_usuario=:idUsuario AND id_unidad=:idUnidad AND fecha_inicio <= :fechaFinComparacion AND (fecha_fin IS NULL OR fecha_fin > :fechaInicio)');
        $consulta->execute(['idUsuario' => $idUsuario, 'idUnidad' => $idUnidad, 'fechaFinComparacion' => $fechaFin ?? '9999-12-31', 'fechaInicio' => $fechaInicio]);
        return (int)$consulta->fetchColumn() > 0;
    }

    public function existeNumeroUnidad(int $idInmueble, string $numero, ?int $excluir = null): bool
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM propiedades_unidades WHERE id_inmueble=:id AND numero_unidad=:numero AND activo=1 AND (:excluir IS NULL OR id_unidad<>:excluir2)');
        $consulta->execute(['id' => $idInmueble, 'numero' => $numero, 'excluir' => $excluir, 'excluir2' => $excluir]);
        return (int)$consulta->fetchColumn() > 0;
    }
}
