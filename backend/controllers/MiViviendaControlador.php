<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/services/AutenticacionServicio.php';

final class MiViviendaControlador
{
    public function __construct(
        private PDO $conexion,
        private AutenticacionServicio $autenticacion
    ) {}

    public function consultar(): void
    {
        $token = $this->obtenerToken();

        if ($token === null) {
            $this->responder(401, [
                'mensaje' => 'Debes iniciar sesión.'
            ]);
            return;
        }

        $usuario = $this->autenticacion
            ->verificarTokenMovil($token);

        if ($usuario === null) {
            $this->responder(401, [
                'mensaje' => 'Sesión no válida o vencida.'
            ]);
            return;
        }

        if (($usuario['tipoUsuario'] ?? '') !== 'inquilino') {
            $this->responder(403, [
                'mensaje' =>
                    'Esta función es exclusiva para inquilinos.'
            ]);
            return;
        }

        $idUsuario = (int) $usuario['idUsuario'];

        $viviendas = $this->consultarViviendas($idUsuario);
        $notificaciones = $this->consultarNotificaciones(
            $idUsuario
        );

        $this->responder(200, [
            'viviendas' => $viviendas,
            'notificaciones' => $notificaciones
        ]);
    }

    private function consultarViviendas(
        int $idUsuario
    ): array {

        $sql = "
            SELECT
                u.id_unidad AS idUnidad,
                u.numero_unidad AS numeroUnidad,
                u.tipo_unidad AS tipoUnidad,
                u.superficie_m2 AS superficie,
                u.cajones_estacionamiento AS estacionamientos,
                u.tiene_bodega AS tieneBodega,
                u.estado_unidad AS estadoUnidad,
                COALESCE(u.descripcion, '') AS descripcion,
                COALESCE(
                    u.servicios_incluidos, ''
                ) AS serviciosIncluidos,

                i.nombre AS inmueble,
                i.tipo AS tipoInmueble,

                COALESCE(
                    abuelo.nombre,
                    padre.nombre,
                    i.nombre
                ) AS condominio,

                COALESCE(
                    abuelo.direccion,
                    padre.direccion,
                    i.direccion
                ) AS direccion,

                COALESCE(
                    abuelo.ciudad,
                    padre.ciudad,
                    i.ciudad
                ) AS ciudad,

                up.fecha_inicio AS fechaInicio,
                up.fecha_fin AS fechaFin

            FROM usuarios_propiedades up

            INNER JOIN propiedades_unidades u
                ON u.id_unidad = up.id_unidad

            INNER JOIN inmuebles i
                ON i.id_inmueble = u.id_inmueble

            LEFT JOIN inmuebles padre
                ON padre.id_inmueble =
                    i.id_inmueble_padre
                AND padre.activo = 1

            LEFT JOIN inmuebles abuelo
                ON abuelo.id_inmueble =
                    padre.id_inmueble_padre
                AND abuelo.activo = 1

            WHERE up.id_usuario = :idUsuario

                AND up.relacion = 'inquilino'

                AND up.fecha_inicio <= CURRENT_DATE()

                AND (
                    up.fecha_fin IS NULL
                    OR up.fecha_fin >= CURRENT_DATE()
                )

                AND u.activo = 1
                AND i.activo = 1

            ORDER BY
                up.es_principal DESC,
                up.fecha_inicio DESC,
                u.id_unidad
        ";

        $consulta = $this->conexion->prepare($sql);

        $consulta->execute([
            'idUsuario' => $idUsuario
        ]);

        $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);

        // Evitar duplicar una unidad si tiene
        // varias asignaciones vigentes.
        $viviendas = [];

        foreach ($filas as $fila) {
            $idUnidad = (int) $fila['idUnidad'];

            if (isset($viviendas[$idUnidad])) {
                continue;
            }

            $fila['idUnidad'] = $idUnidad;

            $fila['estacionamientos'] =
                (int) $fila['estacionamientos'];

            $fila['tieneBodega'] =
                (int) $fila['tieneBodega'];

            $viviendas[$idUnidad] = $fila;
        }

        return array_values($viviendas);
    }

    private function consultarNotificaciones(
        int $idUsuario
    ): array {

        $sql = "
            SELECT
                id_notificacion AS id,
                titulo,
                mensaje,
                fecha_envio AS fecha,
                estado_envio AS estado

            FROM notificaciones_push_log

            WHERE id_usuario = :idUsuario

            ORDER BY fecha_envio DESC,
                     id_notificacion DESC

            LIMIT 20
        ";

        $consulta = $this->conexion->prepare($sql);

        $consulta->execute([
            'idUsuario' => $idUsuario
        ]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerToken(): ?string
    {
        $autorizacion =
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (
            !is_string($autorizacion)
            || strlen($autorizacion) > 4200
        ) {
            return null;
        }

        if (
            preg_match(
                '/^Bearer ([A-Za-z0-9_.-]+)$/iD',
                trim($autorizacion),
                $coincidencias
            ) !== 1
        ) {
            return null;
        }

        return $coincidencias[1];
    }

    private function responder(
        int $codigo,
        array $datos
    ): void {

        http_response_code($codigo);

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
    }
}
