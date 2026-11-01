<?php
declare(strict_types=1);

final class TicketsRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    private function consultaBase(): string
    {
        return <<<'SQL'
            SELECT
                t.id_ticket AS idTicket,
                t.id_incidencia AS idIncidencia,
                t.fecha_programada AS fechaProgramada,
                t.descripcion_trabajo AS descripcionTrabajo,
                t.estado,
                EXISTS (SELECT 1 FROM firmas_conformidad f WHERE f.id_ticket = t.id_ticket) AS tieneFirma,
                i.titulo,
                i.descripcion,
                i.urgencia,
                i.fecha_reporte AS fechaReporte,
                p.numero_unidad AS numeroUnidad,
                inm.nombre AS nombreInmueble,
                a.descripcion_orden AS descripcionOrden,
                a.estado AS estadoOrden,
                CASE WHEN tec.id_usuario IS NULL THEN NULL
                    ELSE CONCAT_WS(' ', tec.nombres, tec.apellidos)
                END AS nombreTecnico
            FROM tickets_mantenimiento t
            INNER JOIN incidencias i ON i.id_incidencia = t.id_incidencia
            LEFT JOIN propiedades_unidades p ON p.id_unidad = i.id_unidad
            LEFT JOIN inmuebles inm ON inm.id_inmueble = COALESCE(i.id_inmueble, p.id_inmueble)
            LEFT JOIN asignacion_ordenes_trabajo a ON a.id_asignacion = (
                SELECT a2.id_asignacion
                FROM asignacion_ordenes_trabajo a2
                WHERE a2.id_ticket = t.id_ticket
                ORDER BY a2.fecha_asignacion DESC, a2.id_asignacion DESC
                LIMIT 1
            )
            LEFT JOIN usuarios tec ON tec.id_usuario = a.id_tecnico
        SQL;
    }

    private function condicionAcceso(bool $esTecnico): string
    {
        return $esTecnico
            ? 'a.id_tecnico = :idUsuario'
            : 'i.id_usuario_reporta = :idUsuario';
    }

    public function listarPorUsuario(int $idUsuario, bool $esTecnico): array
    {
        $sql = $this->consultaBase() . ' WHERE ' . $this->condicionAcceso($esTecnico)
            . ' ORDER BY i.fecha_reporte DESC, t.id_ticket DESC LIMIT 100';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute(['idUsuario' => $idUsuario]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorUsuario(int $idTicket, int $idUsuario, bool $esTecnico): array|false
    {
        $sql = $this->consultaBase()
            . ' WHERE t.id_ticket = :idTicket AND ' . $this->condicionAcceso($esTecnico)
            . ' LIMIT 1';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute(['idTicket' => $idTicket, 'idUsuario' => $idUsuario]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function historial(int $idTicket): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT id_movimiento AS idMovimiento,
                    estado_anterior AS estadoAnterior,
                    estado_nuevo AS estadoNuevo,
                    fecha_cambio AS fechaCambio
             FROM historial_estados_tickets
             WHERE id_ticket = :idTicket
             ORDER BY fecha_cambio ASC, id_movimiento ASC'
        );
        $consulta->execute(['idTicket' => $idTicket]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrarConformidad(
        int $idTicket,
        int $idUsuario,
        string $imagenPng,
        ?string $comentario
    ): void {
        $directorio = dirname(__DIR__) . '/documentos/firmas';
        $rutaAbsoluta = null;
        $this->conexion->beginTransaction();
        try {
            $consulta = $this->conexion->prepare(
                'SELECT t.estado
                 FROM tickets_mantenimiento t
                 INNER JOIN incidencias i ON i.id_incidencia = t.id_incidencia
                 WHERE t.id_ticket = :idTicket
                   AND i.id_usuario_reporta = :idUsuario
                 FOR UPDATE'
            );
            $consulta->execute(['idTicket' => $idTicket, 'idUsuario' => $idUsuario]);
            $ticket = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($ticket === false) {
                throw new OutOfBoundsException('No se encontró un ticket que puedas firmar.');
            }
            if ($ticket['estado'] !== 'en_proceso') {
                throw new DomainException('Solo puedes firmar un ticket en proceso.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT id_firma FROM firmas_conformidad WHERE id_ticket = :idTicket LIMIT 1'
            );
            $consulta->execute(['idTicket' => $idTicket]);
            if ($consulta->fetchColumn() !== false) {
                throw new DomainException('Este ticket ya tiene una firma de conformidad.');
            }

            if (!is_dir($directorio) && !mkdir($directorio, 0700, true) && !is_dir($directorio)) {
                throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
            }
            $nombreArchivo = 'firma-ticket-' . $idTicket . '-' . bin2hex(random_bytes(16)) . '.png';
            $rutaAbsoluta = $directorio . '/' . $nombreArchivo;
            if (file_put_contents($rutaAbsoluta, $imagenPng, LOCK_EX) !== strlen($imagenPng)) {
                throw new RuntimeException('No se pudo guardar la firma digital.');
            }
            chmod($rutaAbsoluta, 0600);

            $consulta = $this->conexion->prepare(
                'INSERT INTO firmas_conformidad
                    (id_ticket, id_usuario_residente, ruta_firma, comentario)
                 VALUES (:idTicket, :idUsuario, :rutaFirma, :comentario)'
            );
            $consulta->execute([
                'idTicket' => $idTicket,
                'idUsuario' => $idUsuario,
                'rutaFirma' => 'documentos/firmas/' . $nombreArchivo,
                'comentario' => $comentario
            ]);

            $consulta = $this->conexion->prepare(
                "UPDATE tickets_mantenimiento SET estado = 'pendiente_revision'
                 WHERE id_ticket = :idTicket AND estado = 'en_proceso'"
            );
            $consulta->execute(['idTicket' => $idTicket]);
            if ($consulta->rowCount() !== 1) {
                throw new RuntimeException('No se pudo cambiar el estado del ticket.');
            }
            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            if ($rutaAbsoluta !== null && is_file($rutaAbsoluta)) {
                unlink($rutaAbsoluta);
            }
            throw $error;
        }
    }

}
