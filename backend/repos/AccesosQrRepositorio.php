<?php
declare(strict_types=1);

final class AccesosQrRepositorio
{
    public function __construct(private PDO $conexion) {}

    public function listarTodosPases(): array
    {
        return $this->conexion->query(
            "SELECT
                id_pase AS idPase,
                id_usuario_anfitrion AS idUsuarioAnfitrion,
                nombre_anfitrion AS nombreAnfitrion,
                correo_anfitrion AS correoAnfitrion,
                COALESCE(numero_unidad, 'S/N') AS numeroUnidad,
                nombre_visitante AS nombreVisitante,
                token_qr AS tokenQr,
                fecha_inicio_vigencia AS fechaInicioVigencia,
                fecha_fin_vigencia AS fechaFinVigencia,
                CASE WHEN estado = 'vigente' AND NOW() > fecha_fin_vigencia THEN 'vencido' ELSE estado END AS estado,
                fecha_creacion AS fechaCreacion
            FROM view_pases_acceso_qr
            ORDER BY fecha_creacion DESC, id_pase DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPorAnfitrion(int $idUsuario): array
    {
        $consulta = $this->conexion->prepare('CALL sp_pases_por_anfitrion(:idUsuario)');
        $consulta->execute(['idUsuario' => $idUsuario]);
        $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $filas;
    }

    public function listarBitacora(): array
    {
        return $this->conexion->query(
            "SELECT
                id_visita AS idVisita,
                id_pase AS idPase,
                token_qr AS tokenQr,
                nombre_visitante AS nombreVisitante,
                id_usuario_anfitrion AS idUsuarioAnfitrion,
                nombre_anfitrion AS nombreAnfitrion,
                numero_unidad AS numeroUnidad,
                id_guardia AS idGuardia,
                nombre_guardia AS nombreGuardia,
                fecha_entrada AS fechaEntrada,
                fecha_salida AS fechaSalida,
                observaciones,
                estado_visita AS estadoVisita
            FROM view_bitacora_visitas
            ORDER BY COALESCE(fecha_entrada, '1970-01-01 00:00:00') DESC, id_visita DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generarPase(array $datos): array|false
    {
        return $this->transaccion(fn() => $this->generarRegistro($datos));
    }

    public function fechaActual(): string
    {
        return (string) $this->conexion->query('SELECT NOW()')->fetchColumn();
    }

    public function perteneceAlUsuario(int $idPase, int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare('SELECT id_usuario_anfitrion FROM pases_acceso_qr WHERE id_pase = ?');
        $consulta->execute([$idPase]);
        $anfitrion = $consulta->fetchColumn();
        if ($anfitrion === false) throw new OutOfBoundsException('Pase QR no encontrado.');
        return (int) $anfitrion === $idUsuario;
    }

    public function anfitrionActivo(int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare("SELECT 1 FROM usuarios WHERE id_usuario = ? AND activo = 1 AND estado = 'activo' AND tipo_usuario IN ('inquilino', 'propietario')");
        $consulta->execute([$idUsuario]);
        return (bool) $consulta->fetchColumn();
    }

    private function generarRegistro(array $datos): array|false
    {
        $consulta = $this->conexion->prepare(
            'CALL sp_generar_pase_qr(:idAnfitrion, :nombreVisitante, :fechaInicio, :fechaFin)'
        );
        $consulta->execute([
            'idAnfitrion' => $datos['idAnfitrion'],
            'nombreVisitante' => $datos['nombreVisitante'],
            'fechaInicio' => $datos['fechaInicio'],
            'fechaFin' => $datos['fechaFin'],
        ]);
        $pase = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        if ($pase) {
            $token = 'ALK-QR-' . bin2hex(random_bytes(24));
            $consulta = $this->conexion->prepare('UPDATE pases_acceso_qr SET token_qr = ? WHERE id_pase = ?');
            $consulta->execute([$token, $pase['idPase']]);
            $consulta = $this->conexion->prepare("UPDATE historial_actividades_kdd SET datos_contexto = JSON_SET(datos_contexto, '$.tokenQr', ?) WHERE tipo_actividad = 'pase_qr_generado' AND JSON_UNQUOTE(JSON_EXTRACT(datos_contexto, '$.idPase')) = ?");
            $consulta->execute([$token, (string) $pase['idPase']]);
            $pase['tokenQr'] = $token;
        }
        return $pase;
    }

    public function revocarPase(int $idPase, int $idUsuario): array|false
    {
        return $this->transaccion(function () use ($idPase, $idUsuario) {
            $consulta = $this->conexion->prepare("SELECT CASE WHEN estado = 'vigente' AND NOW() > fecha_fin_vigencia THEN 'vencido' ELSE estado END FROM pases_acceso_qr WHERE id_pase = ? FOR UPDATE");
            $consulta->execute([$idPase]);
            $estado = $consulta->fetchColumn();
            if ($estado === false) return false;
            if ($estado !== 'vigente') throw new DomainException('Solo se puede revocar un pase vigente.');
            $consulta = $this->conexion->prepare('SELECT id_visita FROM bitacora_visitas WHERE id_pase = ? AND fecha_entrada IS NOT NULL AND fecha_salida IS NULL');
            $consulta->execute([$idPase]);
            if ($consulta->fetchColumn()) throw new DomainException('Registra la salida antes de revocar una visita en el interior.');
            return $this->revocarRegistro($idPase, $idUsuario);
        });
    }

    private function revocarRegistro(int $idPase, int $idUsuario): array|false
    {
        $consulta = $this->conexion->prepare('CALL sp_revocar_pase_qr(:idPase, :idUsuario)');
        $consulta->execute([
            'idPase' => $idPase,
            'idUsuario' => $idUsuario,
        ]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $fila;
    }

    public function validarToken(string $tokenQr, int $idGuardia, ?string $observaciones): array|false
    {
        return $this->transaccion(function () use ($tokenQr, $idGuardia, $observaciones) {
            // Serializa validación y revocación del mismo pase hasta completar el procedimiento.
            $bloqueo = $this->conexion->prepare('SELECT id_pase FROM pases_acceso_qr WHERE token_qr = ? FOR UPDATE');
            $bloqueo->execute([$tokenQr]);
            $bloqueo->fetchColumn();
            $bloqueo->closeCursor();
            return $this->validarRegistro($tokenQr, $idGuardia, $observaciones);
        });
    }

    private function validarRegistro(string $tokenQr, int $idGuardia, ?string $observaciones): array|false
    {
        $consulta = $this->conexion->prepare(
            'CALL sp_validar_qr(:tokenQr, :idGuardia, :observaciones)'
        );
        $consulta->execute([
            'tokenQr' => $tokenQr,
            'idGuardia' => $idGuardia,
            'observaciones' => $observaciones,
        ]);
        $resultado = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $resultado;
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
