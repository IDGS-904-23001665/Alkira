<?php
declare(strict_types=1);

final class CierreMantenimientoRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    public function obtenerMateriales(int $idTicket): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT id_ticket AS idTicket, estado, costo_total AS costoTotal, fecha_cierre AS fechaCierre
             FROM tickets_mantenimiento WHERE id_ticket = :idTicket LIMIT 1'
        );
        $consulta->execute(['idTicket' => $idTicket]);
        $ticket = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($ticket === false) {
            throw new OutOfBoundsException('El ticket no existe.');
        }

        $consulta = $this->conexion->prepare(
            'SELECT id_material AS idMaterial, descripcion, cantidad,
                    costo_unitario AS costoUnitario, subtotal
             FROM materiales_utilizados_ticket
             WHERE id_ticket = :idTicket ORDER BY id_material ASC'
        );
        $consulta->execute(['idTicket' => $idTicket]);
        return ['ticket' => $ticket, 'materiales' => $consulta->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function cerrar(
        int $idTicket,
        int $idAdministrador,
        array $materiales,
        string $total,
        string $comentario
    ): array {
        $this->conexion->beginTransaction();
        try {
            $consulta = $this->conexion->prepare(
                'SELECT estado FROM tickets_mantenimiento
                 WHERE id_ticket = :idTicket FOR UPDATE'
            );
            $consulta->execute(['idTicket' => $idTicket]);
            $ticket = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($ticket === false) {
                throw new OutOfBoundsException('El ticket no existe.');
            }
            if ($ticket['estado'] !== 'pendiente_revision') {
                throw new DomainException('Solo pueden cerrarse tickets en evaluación.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT id_firma FROM firmas_conformidad
                 WHERE id_ticket = :idTicket LIMIT 1'
            );
            $consulta->execute(['idTicket' => $idTicket]);
            if ($consulta->fetchColumn() === false) {
                throw new DomainException('El ticket requiere la firma del residente antes del cierre.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT id_asignacion, estado FROM asignacion_ordenes_trabajo
                 WHERE id_ticket = :idTicket
                 ORDER BY id_asignacion DESC LIMIT 1 FOR UPDATE'
            );
            $consulta->execute(['idTicket' => $idTicket]);
            $orden = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($orden === false || $orden['estado'] === 'cancelada') {
                throw new DomainException('No existe una orden válida para cerrar este ticket.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT COUNT(*) FROM materiales_utilizados_ticket WHERE id_ticket = :idTicket'
            );
            $consulta->execute(['idTicket' => $idTicket]);
            if ((int) $consulta->fetchColumn() !== 0) {
                throw new DomainException('Ya existen materiales registrados. Revisa el desglose antes de continuar.');
            }

            $insertar = $this->conexion->prepare(
                'INSERT INTO materiales_utilizados_ticket
                     (id_ticket, descripcion, cantidad, costo_unitario, subtotal)
                 VALUES (:idTicket, :descripcion, :cantidad, :costoUnitario, :subtotal)'
            );
            foreach ($materiales as $material) {
                $insertar->execute([
                    'idTicket' => $idTicket,
                    'descripcion' => $material['descripcion'],
                    'cantidad' => $material['cantidad'],
                    'costoUnitario' => $material['costoUnitario'],
                    'subtotal' => $material['subtotal'],
                ]);
            }

            $consulta = $this->conexion->prepare(
                "UPDATE asignacion_ordenes_trabajo
                 SET estado = 'completada', costo_refacciones = :costoRefacciones
                 WHERE id_asignacion = :idAsignacion"
            );
            $consulta->execute([
                'costoRefacciones' => $total,
                'idAsignacion' => $orden['id_asignacion'],
            ]);

            $consulta = $this->conexion->prepare(
                "UPDATE tickets_mantenimiento
                 SET estado = 'cerrado', costo_total = :costoTotal, fecha_cierre = NOW()
                 WHERE id_ticket = :idTicket AND estado = 'pendiente_revision'"
            );
            $consulta->execute(['costoTotal' => $total, 'idTicket' => $idTicket]);
            if ($consulta->rowCount() !== 1) {
                throw new RuntimeException('No fue posible finalizar el ticket.');
            }

            $consulta = $this->conexion->prepare(
                'INSERT INTO historial_actividades_kdd
                    (id_usuario, tipo_actividad, descripcion, datos_contexto)
                 VALUES (:idAdministrador, :tipoActividad, :descripcion, :datosContexto)'
            );
            $consulta->execute([
                'idAdministrador' => $idAdministrador,
                'tipoActividad' => 'ticket_cerrado_administrativamente',
                'descripcion' => 'Cierre administrativo de mantenimiento',
                'datosContexto' => json_encode([
                    'idTicket' => $idTicket,
                    'idAsignacion' => (int) $orden['id_asignacion'],
                    'totalMateriales' => $total,
                    'materialesRegistrados' => count($materiales),
                    'comentarioCierre' => $comentario,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            $this->conexion->commit();
            return [
                'idTicket' => $idTicket,
                'estado' => 'cerrado',
                'costoTotal' => $total,
                'materialesRegistrados' => count($materiales),
                'mensaje' => 'Ticket cerrado administrativamente.',
            ];
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }
}
