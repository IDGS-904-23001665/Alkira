<?php
declare(strict_types=1);

final class ReglasCobroRepositorio
{
    public function __construct(
        private PDO $conexion
    ) {
    }

    /**
     * Obtiene las reglas de cobro registradas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarReglas(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                rc.id_regla_cobro AS idReglaCobro,
                rc.id_concepto AS idConcepto,
                cc.nombre AS nombreConcepto,
                rc.alcance,
                rc.monto_cuota AS montoCuota,
                rc.tipo_recargo AS tipoRecargo,
                rc.valor_recargo AS valorRecargo,
                rc.dia_corte AS diaCorte,
                rc.dia_limite_pago AS diaLimitePago,
                rc.activo,
                rc.fecha_creacion AS fechaCreacion,
                rc.fecha_actualizacion AS fechaActualizacion
            FROM reglas_cobro rc
            INNER JOIN conceptos_cobro cc
                ON cc.id_concepto = rc.id_concepto
            ORDER BY
                rc.activo DESC,
                rc.alcance ASC,
                rc.id_regla_cobro DESC
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista los conceptos de mantenimiento mensuales
     * que pueden utilizarse para crear reglas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarConceptosMantenimiento(): array
    {
        $consulta = $this->conexion->query(
            <<<'SQL'
            SELECT
                id_concepto AS idConcepto,
                nombre,
                descripcion,
                monto_base AS montoBase
            FROM conceptos_cobro
            WHERE tipo_concepto = 'mantenimiento'
              AND periodicidad = 'mensual'
              AND activo = 1
            ORDER BY nombre ASC, id_concepto ASC
            SQL
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una regla por su identificador.
     *
     * @return array<string, mixed>|false
     */
    public function obtenerRegla(int $idReglaCobro): array|false
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT
                id_regla_cobro AS idReglaCobro,
                id_concepto AS idConcepto,
                alcance,
                monto_cuota AS montoCuota,
                tipo_recargo AS tipoRecargo,
                valor_recargo AS valorRecargo,
                dia_corte AS diaCorte,
                dia_limite_pago AS diaLimitePago,
                activo
            FROM reglas_cobro
            WHERE id_regla_cobro = :idReglaCobro
            LIMIT 1
            SQL
        );

        $consulta->execute([
            'idReglaCobro' => $idReglaCobro
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica que el concepto corresponda
     * a mantenimiento mensual y esté activo.
     */
    public function existeConceptoMantenimiento(
        int $idConcepto
    ): bool {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT id_concepto
            FROM conceptos_cobro
            WHERE id_concepto = :idConcepto
              AND tipo_concepto = 'mantenimiento'
              AND periodicidad = 'mensual'
              AND activo = 1
            LIMIT 1
            SQL
        );

        $consulta->execute([
            'idConcepto' => $idConcepto
        ]);

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Comprueba si ya existe una regla activa
     * de mantenimiento para el mismo alcance.
     *
     * Permite excluir una regla cuando se edita.
     */
    public function existeReglaActivaParaAlcance(
        string $alcance,
        ?int $idReglaExcluir = null
    ): bool {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            SELECT rc.id_regla_cobro
            FROM reglas_cobro rc
            INNER JOIN conceptos_cobro cc
                ON cc.id_concepto = rc.id_concepto
            WHERE rc.alcance = :alcance
              AND rc.activo = 1
              AND cc.tipo_concepto = 'mantenimiento'
              AND cc.periodicidad = 'mensual'
              AND (
                  :idReglaExcluir IS NULL
                  OR rc.id_regla_cobro <> :idReglaComparar
              )
            LIMIT 1
            SQL
        );

        $consulta->bindValue(
            ':alcance',
            $alcance,
            PDO::PARAM_STR
        );

        $consulta->bindValue(
            ':idReglaExcluir',
            $idReglaExcluir,
            $idReglaExcluir === null
                ? PDO::PARAM_NULL
                : PDO::PARAM_INT
        );

        $consulta->bindValue(
            ':idReglaComparar',
            $idReglaExcluir,
            $idReglaExcluir === null
                ? PDO::PARAM_NULL
                : PDO::PARAM_INT
        );

        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Registra una nueva regla.
     *
     * Los datos llegan validados desde el servicio.
     */
    public function crearRegla(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            INSERT INTO reglas_cobro (
                id_concepto,
                alcance,
                monto_cuota,
                tipo_recargo,
                valor_recargo,
                dia_corte,
                dia_limite_pago,
                activo
            )
            VALUES (
                :idConcepto,
                :alcance,
                :montoCuota,
                :tipoRecargo,
                :valorRecargo,
                :diaCorte,
                :diaLimitePago,
                :activo
            )
            SQL
        );

        $consulta->execute([
            'idConcepto' => $datos['idConcepto'],
            'alcance' => $datos['alcance'],
            'montoCuota' => $datos['montoCuota'],
            'tipoRecargo' => $datos['tipoRecargo'],
            'valorRecargo' => $datos['valorRecargo'],
            'diaCorte' => $datos['diaCorte'],
            'diaLimitePago' => $datos['diaLimitePago'],
            'activo' => (int) $datos['activo']
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Actualiza la configuración de una regla existente.
     *
     * No modifica adeudos generados anteriormente.
     */
    public function actualizarRegla(
        int $idReglaCobro,
        array $datos
    ): void {
        $consulta = $this->conexion->prepare(
            <<<'SQL'
            UPDATE reglas_cobro
            SET
                id_concepto = :idConcepto,
                alcance = :alcance,
                monto_cuota = :montoCuota,
                tipo_recargo = :tipoRecargo,
                valor_recargo = :valorRecargo,
                dia_corte = :diaCorte,
                dia_limite_pago = :diaLimitePago,
                activo = :activo
            WHERE id_regla_cobro = :idReglaCobro
            SQL
        );

        $consulta->execute([
            'idReglaCobro' => $idReglaCobro,
            'idConcepto' => $datos['idConcepto'],
            'alcance' => $datos['alcance'],
            'montoCuota' => $datos['montoCuota'],
            'tipoRecargo' => $datos['tipoRecargo'],
            'valorRecargo' => $datos['valorRecargo'],
            'diaCorte' => $datos['diaCorte'],
            'diaLimitePago' => $datos['diaLimitePago'],
            'activo' => (int) $datos['activo']
        ]);
    }
}
