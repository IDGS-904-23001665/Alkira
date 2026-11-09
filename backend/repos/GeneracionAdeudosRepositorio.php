<?php
declare(strict_types=1);

final class GeneracionAdeudosRepositorio
{
    public function __construct(
        private PDO $conexion
    ) {
    }

    /**
     * Consulta los contratos vigentes y sus unidades.
     *
     * El servicio verificará posteriormente
     * las fechas del contrato para cada periodo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarContratosVigentes(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                c.id_contrato AS idContrato,
                c.id_unidad AS idUnidad,
                u.tipo_unidad AS tipoUnidad,
                c.fecha_inicio AS fechaInicio,
                c.fecha_fin AS fechaFin
            FROM contratos c
            INNER JOIN propiedades_unidades u
                ON u.id_unidad = c.id_unidad
            WHERE c.estado_contrato = 'vigente'
              AND u.activo = 1
            ORDER BY c.id_contrato ASC
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las reglas de mantenimiento
     * mensuales que están activas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarReglasActivas(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                rc.id_regla_cobro AS idReglaCobro,
                rc.id_concepto AS idConcepto,
                rc.alcance,
                rc.monto_cuota AS montoCuota,
                rc.tipo_recargo AS tipoRecargo,
                rc.valor_recargo AS valorRecargo,
                rc.dia_corte AS diaCorte,
                rc.dia_limite_pago AS diaLimitePago
            FROM reglas_cobro rc
            INNER JOIN conceptos_cobro cc
                ON cc.id_concepto = rc.id_concepto
            WHERE rc.activo = 1
              AND cc.activo = 1
              AND cc.tipo_concepto = 'mantenimiento'
              AND cc.periodicidad = 'mensual'
            ORDER BY
                rc.alcance ASC,
                rc.id_regla_cobro ASC
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inicia la transacción del proceso mensual.
     */
    public function iniciarTransaccion(): void
    {
        if ($this->conexion->inTransaction()) {
            throw new LogicException(
                'Ya existe una transacción activa.'
            );
        }

        $this->conexion->beginTransaction();
    }

    /**
     * Confirma los cobros generados.
     */
    public function confirmarTransaccion(): void
    {
        if (!$this->conexion->inTransaction()) {
            throw new LogicException(
                'No existe una transacción para confirmar.'
            );
        }

        $this->conexion->commit();
    }

    /**
     * Deshace los cambios si ocurre un error.
     */
    public function revertirTransaccion(): void
    {
        if ($this->conexion->inTransaction()) {
            $this->conexion->rollBack();
        }
    }

    /**
     * Bloquea un contrato dentro de la transacción.
     *
     * Esto permite que dos ejecuciones del
     * generador no procesen simultáneamente
     * el mismo contrato.
     *
     * @return array<string, mixed>|false
     */
    public function bloquearContrato(
        int $idContrato
    ): array|false {
        if (!$this->conexion->inTransaction()) {
            throw new LogicException(
                'Debes iniciar una transacción antes de bloquear contratos.'
            );
        }

        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                c.id_contrato AS idContrato,
                c.fecha_inicio AS fechaInicio,
                c.fecha_fin AS fechaFin,
                u.tipo_unidad AS tipoUnidad
            FROM contratos c
            INNER JOIN propiedades_unidades u
                ON u.id_unidad = c.id_unidad
            WHERE c.id_contrato = :idContrato
              AND c.estado_contrato = 'vigente'
              AND u.activo = 1
            LIMIT 1
            FOR UPDATE
            SQL
        );

        $consulta->execute([
            'idContrato' => $idContrato
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Revisa si un contrato ya tiene mantenimiento
     * registrado para el periodo solicitado.
     *
     * Comprueba el tipo de concepto y no
     * únicamente el identificador.
     *
     * Así detecta cobros registrados con
     * conceptos distintos de mantenimiento.
     */
    public function existeCobroMantenimiento(
        int $idContrato,
        string $periodo
    ): bool {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                ec.id_estado_cuenta
            FROM estados_cuenta ec
            INNER JOIN conceptos_cobro cc
                ON cc.id_concepto = ec.id_concepto
            WHERE ec.id_contrato = :idContrato
              AND ec.periodo = :periodo
              AND cc.tipo_concepto = 'mantenimiento'
            LIMIT 1
            SQL
        );

        $consulta->execute([
            'idContrato' => $idContrato,
            'periodo' => $periodo
        ]);

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Registra un adeudo de mantenimiento.
     *
     * Los valores deben llegar validados
     * desde GeneracionAdeudosServicio.
     *
     * El recargo comienza en cero: no se
     * cobra mora al emitir el adeudo.
     */
    public function crearAdeudo(
        int $idContrato,
        int $idConcepto,
        string $periodo,
        string $fechaEmision,
        string $fechaLimitePago,
        string $montoPrincipal
    ): int {
        if (!$this->conexion->inTransaction()) {
            throw new LogicException(
                'La generación de adeudos requiere una transacción.'
            );
        }

        $consulta = $this->conexion->prepare(
            <<<'SQL'
            INSERT INTO estados_cuenta (
                id_contrato,
                id_concepto,
                periodo,
                fecha_emision,
                fecha_limite_pago,
                monto_principal,
                recargo_mora,
                monto_pagado,
                estado
            )
            VALUES (
                :idContrato,
                :idConcepto,
                :periodo,
                :fechaEmision,
                :fechaLimitePago,
                :montoPrincipal,
                0.00,
                0.00,
                'pendiente'
            )
            SQL
        );

        $consulta->execute([
            'idContrato' => $idContrato,
            'idConcepto' => $idConcepto,
            'periodo' => $periodo,
            'fechaEmision' => $fechaEmision,
            'fechaLimitePago' => $fechaLimitePago,
            'montoPrincipal' => $montoPrincipal
        ]);

        return (int) $this->conexion->lastInsertId();
    }
}
