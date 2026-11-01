<?php
declare(strict_types=1);

final class OrdenesTrabajoRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    public function listarTickets(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                t.id_ticket AS idTicket,
                t.id_incidencia AS idIncidencia,
                t.descripcion_trabajo AS descripcionTrabajo,
                t.fecha_programada AS fechaProgramada,
                t.estado,
                i.titulo,
                i.descripcion,
                i.urgencia,
                i.fecha_reporte AS fechaReporte,
                p.numero_unidad AS numeroUnidad,
                inm.nombre AS nombreInmueble,
                CONCAT_WS(' ', res.nombres, res.apellidos) AS nombreResidente,
                a.id_asignacion AS idAsignacion,
                CASE WHEN a.estado NOT IN ('cancelada', 'completada') THEN 1 ELSE 0 END AS tieneOrdenActiva,
                a.id_tecnico AS idTecnico,
                a.descripcion_orden AS descripcionOrden,
                a.estado AS estadoOrden,
                CASE WHEN tec.id_usuario IS NULL THEN NULL
                    ELSE CONCAT_WS(' ', tec.nombres, tec.apellidos)
                END AS nombreTecnico
            FROM tickets_mantenimiento t
            INNER JOIN incidencias i ON i.id_incidencia = t.id_incidencia
            INNER JOIN usuarios res ON res.id_usuario = i.id_usuario_reporta
            LEFT JOIN propiedades_unidades p ON p.id_unidad = i.id_unidad
            LEFT JOIN inmuebles inm ON inm.id_inmueble = COALESCE(i.id_inmueble, p.id_inmueble)
            LEFT JOIN asignacion_ordenes_trabajo a ON a.id_asignacion = (
                SELECT a2.id_asignacion
                FROM asignacion_ordenes_trabajo a2
                WHERE a2.id_ticket = t.id_ticket
                ORDER BY a2.id_asignacion DESC
                LIMIT 1
            )
            LEFT JOIN usuarios tec ON tec.id_usuario = a.id_tecnico
            ORDER BY FIELD(t.estado, 'abierto', 'asignado', 'en_proceso', 'pendiente_revision', 'cerrado', 'cancelado'),
                     t.id_ticket DESC
            LIMIT 300
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTecnicos(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT id_usuario AS idTecnico,
                   CONCAT_WS(' ', nombres, apellidos) AS nombreTecnico,
                   correo
            FROM usuarios
            WHERE tipo_usuario = 'tecnico'
              AND estado = 'activo'
              AND activo = 1
            ORDER BY nombres, apellidos, id_usuario
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asignar(
        int $idTicket,
        int $idTecnico,
        string $descripcionOrden,
        string $fechaProgramada,
        int $idAdministrador
    ): int {
        $this->conexion->beginTransaction();
        try {
            // Bloquear el ticket evita asignaciones simultáneas desde dos administradores.
            $consulta = $this->conexion->prepare(
                'SELECT estado FROM tickets_mantenimiento
                 WHERE id_ticket = :idTicket FOR UPDATE'
            );
            $consulta->execute(['idTicket' => $idTicket]);
            $ticket = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($ticket === false) {
                throw new OutOfBoundsException('El ticket no existe.');
            }
            if (!in_array($ticket['estado'], ['abierto', 'asignado'], true)) {
                throw new DomainException('Este ticket ya no admite una asignación nueva.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT id_usuario FROM usuarios
                 WHERE id_usuario = :idTecnico
                   AND tipo_usuario = :tipoUsuario
                   AND estado = :estado AND activo = 1
                 FOR UPDATE'
            );
            $consulta->execute([
                'idTecnico' => $idTecnico,
                'tipoUsuario' => 'tecnico',
                'estado' => 'activo',
            ]);
            if ($consulta->fetchColumn() === false) {
                throw new InvalidArgumentException('Selecciona un técnico activo y registrado.');
            }

            $consulta = $this->conexion->prepare(
                "SELECT id_asignacion FROM asignacion_ordenes_trabajo
                 WHERE id_ticket = :idTicket
                   AND estado NOT IN ('cancelada', 'completada')
                 LIMIT 1"
            );
            $consulta->execute(['idTicket' => $idTicket]);
            if ($consulta->fetchColumn() !== false) {
                throw new DomainException('El ticket ya tiene una orden de trabajo activa.');
            }

            $consulta = $this->conexion->prepare(
                "INSERT INTO asignacion_ordenes_trabajo
                    (id_ticket, id_tecnico, descripcion_orden, estado)
                 VALUES (:idTicket, :idTecnico, :descripcionOrden, 'asignada')"
            );
            $consulta->execute([
                'idTicket' => $idTicket,
                'idTecnico' => $idTecnico,
                'descripcionOrden' => $descripcionOrden,
            ]);
            $idAsignacion = (int) $this->conexion->lastInsertId();

            $consulta = $this->conexion->prepare(
                "UPDATE tickets_mantenimiento
                 SET fecha_programada = :fechaProgramada,
                     descripcion_trabajo = :descripcionTrabajo,
                     estado = 'asignado'
                 WHERE id_ticket = :idTicket"
            );
            $consulta->execute([
                'fechaProgramada' => $fechaProgramada,
                'descripcionTrabajo' => $descripcionOrden,
                'idTicket' => $idTicket,
            ]);

            // Se crea el aviso pendiente; su entrega push requiere un emisor configurado.
            $consulta = $this->conexion->prepare(
                "INSERT INTO notificaciones_push_log
                    (id_usuario, titulo, mensaje, estado_envio)
                 VALUES (:idTecnico, :titulo, :mensaje, 'pendiente')"
            );
            $consulta->execute([
                'idTecnico' => $idTecnico,
                'titulo' => 'Nueva orden de mantenimiento',
                'mensaje' => sprintf(
                    'Se te asignó la orden #%d para el ticket #%d. Visita: %s.',
                    $idAsignacion,
                    $idTicket,
                    $fechaProgramada
                ),
            ]);

            $consulta = $this->conexion->prepare(
                'INSERT INTO historial_actividades_kdd
                    (id_usuario, tipo_actividad, descripcion, datos_contexto)
                 VALUES (:idAdministrador, :tipoActividad, :descripcion, :datosContexto)'
            );
            $consulta->execute([
                'idAdministrador' => $idAdministrador,
                'tipoActividad' => 'orden_trabajo_asignada',
                'descripcion' => 'Asignación de orden de mantenimiento',
                'datosContexto' => json_encode([
                    'idTicket' => $idTicket,
                    'idAsignacion' => $idAsignacion,
                    'idTecnico' => $idTecnico,
                ], JSON_THROW_ON_ERROR),
            ]);

            $this->conexion->commit();
            return $idAsignacion;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }
}
