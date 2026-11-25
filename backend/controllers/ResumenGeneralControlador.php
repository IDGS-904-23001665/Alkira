<?php

declare(strict_types=1);

final class ResumenGeneralControlador
{
    public function __construct(
        private PDO $conexion
    ) {}

    public function consultar(?array $usuario): void
    {
        if ($usuario === null) {
            $this->responder(401, [
                'mensaje' => 'Debes iniciar sesión.'
            ]);
            return;
        }

        if (($usuario['tipoUsuario'] ?? '') !== 'administrador') {
            $this->responder(403, [
                'mensaje' => 'No tienes permiso para consultar el resumen.'
            ]);
            return;
        }

        $this->responder(200, [
            'indicadores' => $this->obtenerIndicadores(),
            'pendientes' => $this->obtenerPendientes(),
            'agenda' => $this->obtenerAgenda(),
            'actividad' => $this->obtenerActividad(),
            'actualizado' => date('Y-m-d H:i:s')
        ]);
    }

    private function contar(string $sql): int
    {
        return (int) $this->conexion
            ->query($sql)
            ->fetchColumn();
    }

    private function obtenerIndicadores(): array
    {
        return [
            'propiedades' => $this->contar("
                SELECT COUNT(*)
                FROM inmuebles
                WHERE activo = 1
                AND estado_registro = 'activo'
            "),

            'ocupadas' => $this->contar("
                SELECT COUNT(*)
                FROM propiedades_unidades u
                INNER JOIN inmuebles i
                    ON i.id_inmueble = u.id_inmueble
                WHERE u.activo = 1
                AND i.activo = 1
                AND i.estado_registro = 'activo'
                AND u.estado_unidad = 'ocupada'
            "),

            'disponibles' => $this->contar("
                SELECT COUNT(*)
                FROM propiedades_unidades u
                INNER JOIN inmuebles i
                    ON i.id_inmueble = u.id_inmueble
                WHERE u.activo = 1
                AND i.activo = 1
                AND i.estado_registro = 'activo'
                AND u.estado_unidad = 'disponible'
            "),

            'residentes' => $this->contar("
                SELECT COUNT(*)
                FROM usuarios
                WHERE activo = 1
                AND estado = 'activo'
                AND tipo_usuario IN (
                    'inquilino',
                    'propietario'
                )
            ")
        ];
    }

    private function obtenerPendientes(): array
    {
        return [
            'comprobantes' => $this->contar("
                SELECT COUNT(*)
                FROM comprobantes_pago
                WHERE estado_revision = 'pendiente'
            "),

            'incidencias' => $this->contar("
                SELECT COUNT(*)
                FROM incidencias
                WHERE estado IN (
                    'reportada',
                    'en_revision'
                )
            "),

            'mantenimiento' => $this->contar("
                SELECT COUNT(*)
                FROM tickets_mantenimiento
                WHERE estado IN (
                    'abierto',
                    'asignado',
                    'en_proceso',
                    'pendiente_revision'
                )
            "),

            'contratos' => $this->contar("
                SELECT COUNT(*)
                FROM contratos
                WHERE estado_contrato = 'vigente'
                AND fecha_fin BETWEEN CURDATE()
                    AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ")
        ];
    }

    private function obtenerAgenda(): array
    {
        $sql = "
            SELECT
                CONCAT('Mantenimiento: ', i.titulo) AS titulo,
                t.fecha_programada AS fecha,
                'mantenimiento' AS seccion
            FROM tickets_mantenimiento t
            INNER JOIN incidencias i
                ON i.id_incidencia = t.id_incidencia
            WHERE t.fecha_programada >= NOW()
            AND t.estado NOT IN ('cerrado', 'cancelado')

            UNION ALL

            SELECT
                CONCAT('Cierre de votación: ', titulo),
                fecha_cierre,
                'votaciones'
            FROM votaciones
            WHERE estado = 'activa'
            AND fecha_cierre >= NOW()

            UNION ALL

            SELECT
                CONCAT('Comunicado: ', titulo),
                fecha_programada,
                'comunicados'
            FROM comunicados
            WHERE estado = 'programado'
            AND fecha_programada >= NOW()

            UNION ALL

            SELECT
                CONCAT('Vencimiento de contrato #', id_contrato),
                fecha_fin,
                'pagos'
            FROM contratos
            WHERE estado_contrato = 'vigente'
            AND fecha_fin >= CURDATE()

            ORDER BY fecha ASC
            LIMIT 8
        ";

        return $this->conexion
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerActividad(): array
    {
        $sql = "
            SELECT
                CONCAT('Nuevo inmueble: ', nombre) AS titulo,
                fecha_registro AS fecha,
                'propiedades' AS seccion
            FROM inmuebles
            WHERE activo = 1

            UNION ALL

            SELECT
                CONCAT('Residente registrado: ', nombres, ' ', apellidos),
                fecha_registro,
                'residentes'
            FROM usuarios
            WHERE tipo_usuario IN ('inquilino', 'propietario')

            UNION ALL

            SELECT
                CONCAT('Incidencia reportada: ', titulo),
                fecha_reporte,
                'incidencias'
            FROM incidencias

            UNION ALL

            SELECT
                CONCAT('Comprobante recibido #', id_comprobante),
                fecha_carga,
                'comprobantes'
            FROM comprobantes_pago

            ORDER BY fecha DESC
            LIMIT 8
        ";

        return $this->conexion
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}