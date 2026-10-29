<?php
declare(strict_types=1);

require_once __DIR__ . '/ResidentesRepositorio.php';

final class IncidenciasRepositorio
{
    public function __construct(private PDO $conexion) {}

    public function listarTodas(): array
    {
        $consulta = $this->conexion->query('CALL sp_incidencias_admin()');
        $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $filas;
    }

    public function listarPorUsuario(int $idUsuario): array
    {
        $consulta = $this->conexion->prepare('CALL sp_incidencias_por_usuario(:idUsuario)');
        $consulta->execute(['idUsuario' => $idUsuario]);
        $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $filas;
    }

    public function buscar(int $idIncidencia): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT
                id_incidencia AS idIncidencia,
                id_usuario_reporta AS idUsuarioReporta,
                nombre_reporta AS nombreReporta,
                correo_reporta AS correoReporta,
                telefono_reporta AS telefonoReporta,
                id_inmueble AS idInmueble,
                nombre_inmueble AS nombreInmueble,
                id_unidad AS idUnidad,
                numero_unidad AS numeroUnidad,
                titulo,
                descripcion,
                categoria,
                urgencia,
                estado,
                fecha_reporte AS fechaReporte,
                total_evidencias AS totalEvidencias,
                id_ticket AS idTicket
            FROM view_incidencias
            WHERE id_incidencia = :id'
        );
        $consulta->execute(['id' => $idIncidencia]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function listarEvidencias(int $idIncidencia): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT
                id_evidencia AS idEvidencia,
                id_incidencia AS idIncidencia,
                ruta_archivo AS rutaArchivo,
                tipo_archivo AS tipoArchivo,
                descripcion,
                fecha_carga AS fechaCarga
            FROM view_evidencias_incidencia
            WHERE id_incidencia = :id
            ORDER BY fecha_carga DESC, id_evidencia DESC'
        );
        $consulta->execute(['id' => $idIncidencia]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $datos): int
    {
        return $this->transaccion(fn() => $this->crearRegistro($datos));
    }

    private function crearRegistro(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'CALL sp_crear_incidencia(
                :idUsuario,
                :idInmueble,
                :idUnidad,
                :titulo,
                :descripcion,
                :categoria,
                :urgencia,
                :rutaArchivo,
                :tipoArchivo,
                :descripcionEvidencia
            )'
        );
        $consulta->execute($datos);
        $resultado = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return (int) ($resultado['idIncidencia'] ?? 0);
    }

    public function agregarEvidencia(int $idIncidencia, array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO evidencias_incidencia (
                id_incidencia,
                ruta_archivo,
                tipo_archivo,
                descripcion
            ) VALUES (
                :idIncidencia,
                :rutaArchivo,
                :tipoArchivo,
                :descripcion
            )'
        );
        $consulta->execute([
            'idIncidencia' => $idIncidencia,
            'rutaArchivo' => $datos['rutaArchivo'],
            'tipoArchivo' => $datos['tipoArchivo'],
            'descripcion' => $datos['descripcion'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizarEstado(int $idIncidencia, array $datos): array|false
    {
        return $this->transaccion(fn() => $this->actualizarRegistro($idIncidencia, $datos));
    }

    private function actualizarRegistro(int $idIncidencia, array $datos): array|false
    {
        $consulta = $this->conexion->prepare(
            'CALL sp_actualizar_estado_incidencia(
                :idIncidencia,
                :categoria,
                :urgencia,
                :estado,
                :idAdmin
            )'
        );
        $consulta->execute([
            'idIncidencia' => $idIncidencia,
            'categoria' => $datos['categoria'],
            'urgencia' => $datos['urgencia'],
            'estado' => $datos['estado'],
            'idAdmin' => $datos['idAdmin'],
        ]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $fila;
    }

    public function listarResidentesActivos(): array
    {
        return (new ResidentesRepositorio($this->conexion))->listarActivos();
    }

    public function validarUbicacionResidente(int $idUsuario, ?int $idUnidad, ?int $idInmueble): void
    {
        $consulta = $this->conexion->prepare(
            "SELECT 1 FROM usuarios u
             WHERE u.id_usuario = :usuario AND u.activo = 1 AND u.estado = 'activo'
               AND u.tipo_usuario IN ('inquilino', 'propietario')"
        );
        $consulta->execute(['usuario' => $idUsuario]);
        if (!$consulta->fetchColumn()) {
            throw new InvalidArgumentException('El reportante debe ser un residente activo.');
        }
        if ($idUnidad !== null) {
            $consulta = $this->conexion->prepare(
                'SELECT pu.id_inmueble FROM usuarios_propiedades up
                 JOIN propiedades_unidades pu ON pu.id_unidad = up.id_unidad
                 WHERE up.id_usuario = :usuario AND up.id_unidad = :unidad
                   AND up.fecha_inicio <= CURRENT_DATE()
                   AND (up.fecha_fin IS NULL OR up.fecha_fin >= CURRENT_DATE()) LIMIT 1'
            );
            $consulta->execute(['usuario' => $idUsuario, 'unidad' => $idUnidad]);
            $inmueble = $consulta->fetchColumn();
            if ($inmueble === false || ($idInmueble !== null && (int) $inmueble !== $idInmueble)) {
                throw new InvalidArgumentException('La unidad y el inmueble deben corresponder al residente.');
            }
        } elseif ($idInmueble !== null) {
            throw new InvalidArgumentException('Selecciona la unidad correspondiente al inmueble.');
        }
    }
    private function transaccion(callable $accion): mixed
    {
        $propia = !$this->conexion->inTransaction();
        if ($propia) $this->conexion->beginTransaction();
        try {
            $resultado = $accion();
            if ($propia) $this->conexion->commit();
            return $resultado;
        } catch (Throwable $error) {
            if ($propia && $this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $error;
        }
    }
}
