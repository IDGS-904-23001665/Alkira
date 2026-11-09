<?php
declare(strict_types=1);

final class ContratosAdministrativosRepositorio
{
    public function __construct(private PDO $conexion)
    {
    }

    public function listarContratos(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                c.id_contrato AS idContrato,
                c.id_unidad AS idUnidad,
                pu.numero_unidad AS numeroUnidad,
                inm.nombre AS nombreInmueble,
                c.id_inquilino AS idInquilino,
                CONCAT_WS(' ', u.nombres, u.apellidos) AS nombreInquilino,
                u.correo AS correoInquilino,
                c.fecha_inicio AS fechaInicio,
                c.fecha_fin AS fechaFin,
                c.monto_renta AS montoRenta,
                c.deposito_garantia AS depositoGarantia,
                c.estado_contrato AS estadoContrato,
                (c.ruta_documento IS NOT NULL) AS documentoRegistrado
            FROM contratos c
            INNER JOIN propiedades_unidades pu
                ON pu.id_unidad = c.id_unidad
            INNER JOIN inmuebles inm
                ON inm.id_inmueble = pu.id_inmueble
            INNER JOIN usuarios u
                ON u.id_usuario = c.id_inquilino
            ORDER BY c.id_contrato DESC
            LIMIT 200
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarUnidadesActivas(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                pu.id_unidad AS idUnidad,
                pu.numero_unidad AS numeroUnidad,
                inm.nombre AS nombreInmueble,
                pu.estado_unidad AS estadoUnidad
            FROM propiedades_unidades pu
            INNER JOIN inmuebles inm
                ON inm.id_inmueble = pu.id_inmueble
            WHERE pu.activo = 1
              AND pu.estado_unidad <> 'inactiva'
            ORDER BY inm.nombre, pu.numero_unidad
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarInquilinosActivos(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                id_usuario AS idInquilino,
                CONCAT_WS(' ', nombres, apellidos) AS nombreInquilino,
                correo
            FROM usuarios
            WHERE tipo_usuario = 'inquilino'
              AND estado = 'activo'
              AND activo = 1
            ORDER BY nombres, apellidos, id_usuario
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bloquearUnidad(int $idUnidad): array|false
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                id_unidad AS idUnidad,
                estado_unidad AS estadoUnidad,
                activo
            FROM propiedades_unidades
            WHERE id_unidad = :idUnidad
            FOR UPDATE
            SQL
        );

        $consulta->execute([
            'idUnidad' => $idUnidad
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function existeContratoVigente(int $idUnidad): bool
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT id_contrato
            FROM contratos
            WHERE id_unidad = :idUnidad
              AND estado_contrato = 'vigente'
            LIMIT 1
            SQL
        );

        $consulta->execute([
            'idUnidad' => $idUnidad
        ]);

        return $consulta->fetchColumn() !== false;
    }

    public function existeInquilinoActivo(int $idInquilino): bool
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT id_usuario
            FROM usuarios
            WHERE id_usuario = :idInquilino
              AND tipo_usuario = 'inquilino'
              AND estado = 'activo'
              AND activo = 1
            LIMIT 1
            SQL
        );

        $consulta->execute([
            'idInquilino' => $idInquilino
        ]);

        return $consulta->fetchColumn() !== false;
    }

    public function crearContrato(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            INSERT INTO contratos (
                id_unidad,
                id_inquilino,
                fecha_inicio,
                fecha_fin,
                monto_renta,
                deposito_garantia,
                estado_contrato
            )
            VALUES (
                :idUnidad,
                :idInquilino,
                :fechaInicio,
                :fechaFin,
                :montoRenta,
                :depositoGarantia,
                :estadoContrato
            )
            SQL
        );

        $consulta->execute([
            'idUnidad' => $datos['idUnidad'],
            'idInquilino' => $datos['idInquilino'],
            'fechaInicio' => $datos['fechaInicio'],
            'fechaFin' => $datos['fechaFin'],
            'montoRenta' => $datos['montoRenta'],
            'depositoGarantia' => $datos['depositoGarantia'],
            'estadoContrato' => $datos['estadoContrato'],
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function obtenerContrato(int $idContrato): array|false
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                id_contrato AS idContrato,
                id_unidad AS idUnidad,
                id_inquilino AS idInquilino,
                estado_contrato AS estadoContrato,
                ruta_documento AS rutaDocumento
            FROM contratos
            WHERE id_contrato = :idContrato
            LIMIT 1
            SQL
        );

        $consulta->execute([
            'idContrato' => $idContrato
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function guardarRutaDocumento(
        int $idContrato,
        string $rutaDocumento
    ): bool {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            UPDATE contratos
            SET ruta_documento = :rutaDocumento
            WHERE id_contrato = :idContrato
              AND ruta_documento IS NULL
            SQL
        );

        $consulta->execute([
            'idContrato' => $idContrato,
            'rutaDocumento' => $rutaDocumento,
        ]);

        return $consulta->rowCount() === 1;
    }

    public function listarCobrosPorUnidad(int $idUnidad): array
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                ec.id_estado_cuenta AS idEstadoCuenta,
                ec.id_contrato AS idContrato,
                cc.nombre AS concepto,
                ec.periodo,
                ec.fecha_emision AS fechaEmision,
                ec.fecha_limite_pago AS fechaLimitePago,
                ec.monto_principal AS montoPrincipal,
                ec.recargo_mora AS recargoMora,
                ec.monto_pagado AS montoPagado,
                GREATEST(
                    ec.monto_principal
                    + ec.recargo_mora
                    - ec.monto_pagado,
                    0
                ) AS montoPendiente,
                ec.estado
            FROM estados_cuenta ec
            INNER JOIN contratos c
                ON c.id_contrato = ec.id_contrato
            INNER JOIN conceptos_cobro cc
                ON cc.id_concepto = ec.id_concepto
            WHERE c.id_unidad = :idUnidad
            ORDER BY
                ec.fecha_emision DESC,
                ec.id_estado_cuenta DESC
            LIMIT 300
            SQL
        );

        $consulta->execute([
            'idUnidad' => $idUnidad
        ]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
