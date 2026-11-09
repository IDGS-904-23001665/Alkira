<?php
declare(strict_types=1);

require_once __DIR__ . '/GeneracionAdeudosServicio.php';

final class EjecucionAdeudosServicio
{
    public function __construct(
        private GeneracionAdeudosRepositorio $repositorio,
        private GeneracionAdeudosServicio $simulador
    ) {
    }

    /**
     * Registra los adeudos autorizados por la simulación.
     * Este método todavía NO está expuesto en un endpoint.
     *
     * @return array<string, mixed>
     */
    public function generarPeriodo(string $periodo): array
    {
        $simulacion = $this->simulador->simularPeriodo($periodo);

        if ((int) $simulacion['pendientesDefinicion'] > 0) {
            throw new DomainException(
                'Existen fechas pendientes de definición. No se generó ningún adeudo.'
            );
        }

        if ((int) $simulacion['reglasActivas'] === 0) {
            throw new DomainException(
                'No existen reglas activas. No se generó ningún adeudo.'
            );
        }

        $candidatos = array_values(array_filter(
            $simulacion['detalle'],
            static fn (array $registro): bool =>
                $registro['resultado'] === 'generable'
        ));

        // Orden estable para bloquear contratos.
        usort(
            $candidatos,
            static fn (array $primero, array $segundo): int =>
                (int) $primero['idContrato']
                <=>
                (int) $segundo['idContrato']
        );

        $creados = [];
        $omitidosPorDuplicado = [];

        if ($candidatos === []) {
            return [
                'periodo' => $periodo,
                'modo' => 'generacion_controlada',
                'creados' => 0,
                'omitidosPorDuplicado' => 0,
                'detalle' => []
            ];
        }

        $this->repositorio->iniciarTransaccion();

        try {
            foreach ($candidatos as $registro) {
                $idContrato = (int) $registro['idContrato'];

                // Bloquear el contrato antes de registrar.
                $contrato = $this->repositorio
                    ->bloquearContrato($idContrato);

                if ($contrato === false) {
                    throw new DomainException(
                        "El contrato #{$idContrato} dejó de estar vigente. Vuelve a simular."
                    );
                }

                if (
                    (string) $contrato['tipoUnidad']
                    !== (string) $registro['tipoUnidad']
                ) {
                    throw new DomainException(
                        "Cambió el tipo de unidad del contrato #{$idContrato}. Vuelve a simular."
                    );
                }

                // Revisar la regla después del bloqueo.
                $reglasActuales = $this->repositorio
                    ->listarReglasActivas();

                $regla = $this->seleccionarRegla(
                    $reglasActuales,
                    (string) $contrato['tipoUnidad']
                );

                if ($regla === null) {
                    throw new DomainException(
                        "El contrato #{$idContrato} ya no tiene una regla aplicable."
                    );
                }

                $fechaEmision = $this->fechaDelPeriodo(
                    $periodo,
                    (int) $regla['diaCorte']
                );

                $fechaLimitePago = $this->fechaDelPeriodo(
                    $periodo,
                    (int) $regla['diaLimitePago']
                );

                $montoPrincipal = $this->normalizarMonto(
                    $regla['montoCuota']
                );

                // Evitar generar usando una configuración
                // diferente a la simulada.
                if (
                    (int) $regla['idReglaCobro']
                        !== (int) $registro['idReglaCobro']
                    || (int) $regla['idConcepto']
                        !== (int) $registro['idConcepto']
                    || $montoPrincipal
                        !== (string) $registro['montoPrincipal']
                    || $fechaEmision
                        !== (string) $registro['fechaEmision']
                    || $fechaLimitePago
                        !== (string) $registro['fechaLimitePago']
                ) {
                    throw new DomainException(
                        "La configuración del contrato #{$idContrato} cambió. Vuelve a simular."
                    );
                }

                $fechaInicio = (string) $contrato['fechaInicio'];

                $fechaFin = $contrato['fechaFin'] === null
                    ? null
                    : (string) $contrato['fechaFin'];

                // No generar cobros fuera de vigencia
                // ni con fechas de pago inconsistentes.
                if (
                    $fechaInicio > $fechaEmision
                    || (
                        $fechaFin !== null
                        && $fechaFin < $fechaEmision
                    )
                    || $fechaLimitePago < $fechaEmision
                ) {
                    throw new DomainException(
                        "Las fechas del contrato #{$idContrato} requieren revisión."
                    );
                }

                // Comprobar otra vez dentro de la transacción.
                if (
                    $this->repositorio->existeCobroMantenimiento(
                        $idContrato,
                        $periodo
                    )
                ) {
                    $omitidosPorDuplicado[] = $idContrato;
                    continue;
                }

                // Solo se registra el monto principal.
                // El recargo por mora comienza en cero.
                $idEstadoCuenta = $this->repositorio
                    ->crearAdeudo(
                        $idContrato,
                        (int) $regla['idConcepto'],
                        $periodo,
                        $fechaEmision,
                        $fechaLimitePago,
                        $montoPrincipal
                    );

                $creados[] = [
                    'idEstadoCuenta' => $idEstadoCuenta,
                    'idContrato' => $idContrato,
                    'idConcepto' => (int) $regla['idConcepto'],
                    'montoPrincipal' => $montoPrincipal
                ];
            }

            // Confirmar todos los cambios juntos.
            $this->repositorio->confirmarTransaccion();

        } catch (Throwable $error) {
            // Si algo falla, deshacer toda la operación.
            $this->repositorio->revertirTransaccion();

            throw $error;
        }

        return [
            'periodo' => $periodo,
            'modo' => 'generacion_controlada',
            'creados' => count($creados),
            'omitidosPorDuplicado' => count(
                $omitidosPorDuplicado
            ),
            'detalle' => $creados
        ];
    }

    /**
     * Selecciona primero la regla específica
     * y utiliza la general como alternativa.
     *
     * @param array<int, array<string, mixed>> $reglas
     */
    private function seleccionarRegla(
        array $reglas,
        string $tipoUnidad
    ): ?array {
        $porAlcance = [];

        foreach ($reglas as $regla) {
            $alcance = (string) $regla['alcance'];

            if (isset($porAlcance[$alcance])) {
                throw new DomainException(
                    "Existen dos reglas activas para el alcance {$alcance}."
                );
            }

            $porAlcance[$alcance] = $regla;
        }

        return $porAlcance[$tipoUnidad]
            ?? $porAlcance['general']
            ?? null;
    }

    /**
     * Calcula las fechas utilizando el criterio
     * provisional del simulador.
     */
    private function fechaDelPeriodo(
        string $periodo,
        int $dia
    ): string {
        if ($dia < 1 || $dia > 31) {
            throw new DomainException(
                'La regla contiene un día inválido.'
            );
        }

        $fecha = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $periodo . '-01'
        );

        if (
            $fecha === false
            || $fecha->format('Y-m') !== $periodo
        ) {
            throw new InvalidArgumentException(
                'El periodo no es válido.'
            );
        }

        $diaAplicable = min(
            $dia,
            (int) $fecha->format('t')
        );

        return $fecha->setDate(
            (int) $fecha->format('Y'),
            (int) $fecha->format('m'),
            $diaAplicable
        )->format('Y-m-d');
    }

    /**
     * Valida y normaliza el importe sin
     * utilizar cálculos de punto flotante.
     */
    private function normalizarMonto(mixed $monto): string
    {
        $valor = trim((string) $monto);

        if (
            preg_match(
                '/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',
                $valor
            ) !== 1
        ) {
            throw new DomainException(
                'La cuota configurada no es válida.'
            );
        }

        $partes = explode('.', $valor);

        return $partes[0] . '.' . str_pad(
            $partes[1] ?? '',
            2,
            '0',
            STR_PAD_RIGHT
        );
    }
}
