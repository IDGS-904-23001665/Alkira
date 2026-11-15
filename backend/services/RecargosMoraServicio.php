<?php
declare(strict_types=1);

/**
 * HU02.3 - Aplicación única de recargos fijos y porcentuales por mora.
 *
 * Solo procesa adeudos con configuración registrada en
 * recargos_mora_adeudos. No altera los adeudos históricos
 * que carecen de configuración.
 *
 * Los porcentuales se calculan sobre el saldo principal pendiente
 * al momento de aplicar el recargo, redondeando al centavo.
 */
final class RecargosMoraServicio
{
    private const PUNTO_GUARDADO_PRUEBA = 'alkira_prueba_recargos';

    public function __construct(private PDO $conexion)
    {
    }

    /**
     * Consulta los recargos aplicables sin modificar la base de datos.
     *
     * @return array<string, mixed>
     */
    public function simularRecargos(): array
    {
        $fechaHoy = $this->obtenerFechaLocal();
        $pendientes = $this->consultarPendientes($fechaHoy, false);

        return $this->prepararResultado($pendientes, $fechaHoy, 'simulacion');
    }

    /**
     * Ensaya el recargo y lo revierte.
     *
     * Si ya existe una transacción (por ejemplo, de una prueba que
     * creó un adeudo temporal), utiliza un SAVEPOINT. Así no revierte
     * ni confirma los datos temporales de la transacción externa.
     *
     * @return array<string, mixed>
     */
    public function probarRecargos(): array
    {
        return $this->procesarRecargos(false);
    }

    /**
     * Aplica recargos reales; deshabilitado por defecto.
     * No admite transacciones externas para evitar confirmaciones
     * o efectos secundarios inesperados.
     *
     * @return array<string, mixed>
     */
    public function aplicarRecargos(): array
    {
        if (getenv('ALKIRA_RECARGOS_HABILITADOS') !== '1') {
            throw new RuntimeException(
                'Aplicacion de recargos deshabilitada.'
            );
        }

        return $this->procesarRecargos(true);
    }

    /**
     * Procesa recargos dentro de una transacción o un SAVEPOINT.
     *
     * @return array<string, mixed>
     */
    private function procesarRecargos(bool $confirmar): array
    {
        $transaccionExterna = $this->conexion->inTransaction();

        if ($confirmar && $transaccionExterna) {
            throw new LogicException(
                'No se permite aplicar recargos dentro de una transaccion externa.'
            );
        }

        $fechaHoy = $this->obtenerFechaLocal();
        $fechaAplicacion = (new DateTimeImmutable(
            'now',
            new DateTimeZone('America/Mexico_City')
        ))->format('Y-m-d H:i:s');

        if ($transaccionExterna) {
            $this->conexion->exec(
                'SAVEPOINT ' . self::PUNTO_GUARDADO_PRUEBA
            );
        } else {
            $this->conexion->beginTransaction();
        }

        try {
            $pendientes = $this->consultarPendientes($fechaHoy, true);
            $modo = $confirmar ? 'aplicacion' : 'prueba_revertida';
            $resultado = $this->prepararResultado(
                $pendientes,
                $fechaHoy,
                $modo
            );

            $actualizarCuenta = $this->conexion->prepare(
                <<<'SQL'
                UPDATE estados_cuenta
                SET recargo_mora = :monto,
                    estado = CASE
                        WHEN estado = 'pendiente' THEN 'vencido'
                        ELSE estado
                    END
                WHERE id_estado_cuenta = :idEstadoCuenta
                  AND recargo_mora = 0.00
                  AND estado IN ('pendiente', 'parcial', 'vencido')
                  AND fecha_limite_pago < :fechaHoy
                  AND monto_principal > monto_pagado
                SQL
            );

            $actualizarRecargo = $this->conexion->prepare(
                <<<'SQL'
                UPDATE recargos_mora_adeudos
                SET monto_aplicado = :monto,
                    fecha_aplicacion = :fechaAplicacion
                WHERE id_estado_cuenta = :idEstadoCuenta
                  AND monto_aplicado IS NULL
                  AND fecha_aplicacion IS NULL
                SQL
            );

            $identificadoresAplicados = [];

            foreach ($resultado['detalle'] as $registro) {
                if ($registro['resultado'] !== 'aplicable') {
                    continue;
                }

                $idEstadoCuenta = (int) $registro['idEstadoCuenta'];
                $montoRecargo = (string) $registro['montoRecargo'];

                $actualizarCuenta->execute([
                    'monto' => $montoRecargo,
                    'idEstadoCuenta' => $idEstadoCuenta,
                    'fechaHoy' => $fechaHoy
                ]);

                // Un importe de cero puede no modificar la fila, pero
                // igualmente debe registrarse como aplicado una vez.
                if (
                    $actualizarCuenta->rowCount() !== 1
                    && $montoRecargo !== '0.00'
                ) {
                    throw new RuntimeException(
                        'El estado de cuenta cambio durante la aplicacion.'
                    );
                }

                $actualizarRecargo->execute([
                    'monto' => $montoRecargo,
                    'fechaAplicacion' => $fechaAplicacion,
                    'idEstadoCuenta' => $idEstadoCuenta
                ]);

                if ($actualizarRecargo->rowCount() !== 1) {
                    throw new RuntimeException(
                        'El recargo ya fue aplicado o cambio durante la operacion.'
                    );
                }

                $identificadoresAplicados[] = $idEstadoCuenta;
            }

            if (!$confirmar) {
                // Dentro de la misma transacción, comprobar el estado
                // temporal antes de ejecutar ROLLBACK. Esta consulta
                // representa el conjunto elegible para una segunda pasada.
                $segundaPasada = $this->consultarPendientes($fechaHoy, true);

                foreach ($segundaPasada as $registroPendiente) {
                    if (in_array(
                        (int) $registroPendiente['idEstadoCuenta'],
                        $identificadoresAplicados,
                        true
                    )) {
                        throw new RuntimeException(
                            'Un recargo aplicado continua siendo elegible ' .
                            'para una segunda aplicacion.'
                        );
                    }
                }

                // Comprobar el saldo calculado mientras las actualizaciones
                // temporales aún son visibles; luego se revertirán.
                $consultaSaldo = $this->conexion->prepare(
                    <<<'SQL'
                    SELECT
                        ec.monto_principal AS montoPrincipal,
                        ec.recargo_mora AS recargoMora,
                        ec.monto_pagado AS montoPagado,
                        rm.monto_aplicado AS montoAplicado,
                        rm.fecha_aplicacion AS fechaAplicacion
                    FROM estados_cuenta ec
                    INNER JOIN recargos_mora_adeudos rm
                        ON rm.id_estado_cuenta = ec.id_estado_cuenta
                    WHERE ec.id_estado_cuenta = :idEstadoCuenta
                    SQL
                );

                foreach ($resultado['detalle'] as &$registro) {
                    if ($registro['resultado'] !== 'aplicable') {
                        continue;
                    }

                    $consultaSaldo->execute([
                        'idEstadoCuenta' => $registro['idEstadoCuenta']
                    ]);
                    $cuenta = $consultaSaldo->fetch(PDO::FETCH_ASSOC);

                    if (
                        $cuenta === false
                        || (string) $cuenta['recargoMora']
                            !== (string) $registro['montoRecargo']
                        || (string) $cuenta['montoAplicado']
                            !== (string) $registro['montoRecargo']
                        || $cuenta['fechaAplicacion'] === null
                    ) {
                        throw new RuntimeException(
                            'El recargo temporal no coincide con el adeudo.'
                        );
                    }

                    $saldoCentavos =
                        $this->convertirACentavos((string) $cuenta['montoPrincipal'])
                        + $this->convertirACentavos((string) $cuenta['recargoMora'])
                        - $this->convertirACentavos((string) $cuenta['montoPagado']);

                    $registro['saldoDuranteEnsayo'] =
                        $this->formatearCentavos($saldoCentavos);
                }
                unset($registro);

                $resultado['segundaAplicacionBloqueada'] = true;
            }

            if ($confirmar) {
                $this->conexion->commit();
            } elseif ($transaccionExterna) {
                $this->conexion->exec(
                    'ROLLBACK TO SAVEPOINT ' . self::PUNTO_GUARDADO_PRUEBA
                );
                $this->conexion->exec(
                    'RELEASE SAVEPOINT ' . self::PUNTO_GUARDADO_PRUEBA
                );
            } else {
                $this->conexion->rollBack();
            }

            return $resultado;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                if ($transaccionExterna) {
                    $this->conexion->exec(
                        'ROLLBACK TO SAVEPOINT ' . self::PUNTO_GUARDADO_PRUEBA
                    );
                    $this->conexion->exec(
                        'RELEASE SAVEPOINT ' . self::PUNTO_GUARDADO_PRUEBA
                    );
                } else {
                    $this->conexion->rollBack();
                }
            }

            throw $error;
        }
    }

    /**
     * Selecciona adeudos vencidos con recargo pendiente de aplicar.
     * Los adeudos anteriores sin configuración quedan excluidos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function consultarPendientes(
        string $fechaHoy,
        bool $bloquear
    ): array {
        $consultaSql = <<<'SQL'
            SELECT
                ec.id_estado_cuenta AS idEstadoCuenta,
                ec.id_contrato AS idContrato,
                ec.periodo,
                ec.fecha_limite_pago AS fechaLimitePago,
                ec.monto_principal AS montoPrincipal,
                ec.monto_pagado AS montoPagado,
                rm.tipo_recargo AS tipoRecargo,
                rm.valor_recargo AS valorRecargo
            FROM estados_cuenta ec
            INNER JOIN recargos_mora_adeudos rm
                ON rm.id_estado_cuenta = ec.id_estado_cuenta
            WHERE ec.fecha_limite_pago < :fechaHoy
              AND ec.estado IN ('pendiente', 'parcial', 'vencido')
              AND ec.monto_principal > ec.monto_pagado
              AND ec.recargo_mora = 0.00
              AND rm.fecha_aplicacion IS NULL
              AND rm.monto_aplicado IS NULL
            ORDER BY ec.id_estado_cuenta ASC
            SQL;

        if ($bloquear) {
            $consultaSql .= ' FOR UPDATE';
        }

        $consulta = $this->conexion->prepare($consultaSql);
        $consulta->execute(['fechaHoy' => $fechaHoy]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array<string, mixed>> $pendientes
     * @return array<string, mixed>
     */
    private function prepararResultado(
        array $pendientes,
        string $fechaHoy,
        string $modo
    ): array {
        $detalle = [];
        $recargosFijosAplicables = 0;
        $recargosPorcentualesAplicables = 0;
        $montoTotalFijoCentavos = 0;
        $montoTotalPorcentualCentavos = 0;

        foreach ($pendientes as $registro) {
            $tipoRecargo = (string) $registro['tipoRecargo'];
            $idEstadoCuenta = (int) $registro['idEstadoCuenta'];
            $valorRecargoCentavos = $this->convertirACentavos(
                (string) $registro['valorRecargo']
            );

            if ($tipoRecargo === 'fijo') {
                $montoRecargoCentavos = $valorRecargoCentavos;
                $recargosFijosAplicables++;
                $montoTotalFijoCentavos += $montoRecargoCentavos;
            } elseif ($tipoRecargo === 'porcentaje') {
                // Una tasa de 10.00 % equivale a 1000 centésimas
                // de punto porcentual. 100.00 % equivale a 10000.
                if ($valorRecargoCentavos > 10000) {
                    throw new DomainException(
                        "El porcentaje del adeudo #{$idEstadoCuenta} supera el 100 %."
                    );
                }

                // La base es el principal NO PAGADO al momento de
                // aplicar la mora; nunca incluye recargos previos.
                $principalCentavos = $this->convertirACentavos(
                    (string) $registro['montoPrincipal']
                );
                $pagadoCentavos = $this->convertirACentavos(
                    (string) $registro['montoPagado']
                );
                $saldoBaseCentavos = $principalCentavos - $pagadoCentavos;

                if ($saldoBaseCentavos <= 0) {
                    throw new DomainException(
                        "El adeudo #{$idEstadoCuenta} no tiene saldo principal pendiente."
                    );
                }

                // Cálculo con enteros: redondeo comercial al centavo
                // (0.5 centavos se redondea hacia arriba).
                $montoRecargoCentavos = intdiv(
                    $saldoBaseCentavos * $valorRecargoCentavos + 5000,
                    10000
                );

                $recargosPorcentualesAplicables++;
                $montoTotalPorcentualCentavos += $montoRecargoCentavos;
            } else {
                throw new DomainException(
                    "Tipo de recargo no permitido en el adeudo #{$idEstadoCuenta}."
                );
            }

            $registroDetalle = [
                'idEstadoCuenta' => $idEstadoCuenta,
                'idContrato' => (int) $registro['idContrato'],
                'periodo' => (string) $registro['periodo'],
                'tipoRecargo' => $tipoRecargo,
                'resultado' => 'aplicable',
                'montoRecargo' => $this->formatearCentavos(
                    $montoRecargoCentavos
                )
            ];

            if ($tipoRecargo === 'porcentaje') {
                $registroDetalle['baseCalculo'] =
                    $this->formatearCentavos($saldoBaseCentavos);
            }

            $detalle[] = $registroDetalle;
        }

        return [
            'modo' => $modo,
            'fechaConsulta' => $fechaHoy,
            'adeudosEvaluados' => count($pendientes),
            'recargosFijosAplicables' => $recargosFijosAplicables,
            'recargosPorcentualesAplicables' => $recargosPorcentualesAplicables,
            // Clave anterior conservada por compatibilidad.
            'porcentualesPendientes' => 0,
            'montoTotalFijo' => $this->formatearCentavos(
                $montoTotalFijoCentavos
            ),
            'montoTotalPorcentual' => $this->formatearCentavos(
                $montoTotalPorcentualCentavos
            ),
            'montoTotalRecargos' => $this->formatearCentavos(
                $montoTotalFijoCentavos + $montoTotalPorcentualCentavos
            ),
            'detalle' => $detalle
        ];
    }

    private function obtenerFechaLocal(): string
    {
        return (new DateTimeImmutable(
            'today',
            new DateTimeZone('America/Mexico_City')
        ))->format('Y-m-d');
    }

    /**
     * Convierte importes a centavos, sin punto flotante.
     */
    private function convertirACentavos(string $monto): int
    {
        if (
            preg_match(
                '/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D',
                $monto
            ) !== 1
        ) {
            throw new DomainException(
                'El valor monetario no es valido.'
            );
        }

        [$entero, $decimales] = explode('.', $monto);

        return (int) $entero * 100 + (int) $decimales;
    }

    private function formatearCentavos(int $centavos): string
    {
        if ($centavos < 0) {
            throw new DomainException('El saldo de prueba resulto negativo.');
        }

        return intdiv($centavos, 100)
            . '.'
            . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
