<?php
declare(strict_types=1);

final class AccesosQrRepositorio
{
    public function __construct(private PDO $conexion) {}

    public function listarTodosPases(): array
    {
        $this->conexion->exec(
            "UPDATE pases_acceso_qr
             SET estado = 'vencido'
             WHERE estado = 'vigente' AND NOW() > fecha_fin_vigencia"
        );

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
                estado,
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
        return $pase;
    }

    public function revocarPase(int $idPase, int $idUsuario): array|false
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
}
