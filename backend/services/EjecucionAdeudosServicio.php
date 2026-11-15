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
     * Genera y confirma los adeudos del periodo.
     *
     * La fecha máxima es opcional para conservar
     * el funcionamiento de la generación manual.
     *
     * @return array<string, mixed>
     */
    public function generarPeriodo(
        string $periodo,
        ?string $fechaMaximaEmision = null
    ): array {
        return $this->procesarPeriodo(
            $periodo,
            true,
            $fechaMaximaEmision
        );
    }

    /**
     * Ensaya la generación y revierte las inserciones.
     *
     * @return array<string, mixed>
     */
    public function probarPeriodo(
        string $periodo,
        ?string $fechaMaximaEmision = null
    ): array {
        return $this->procesarPeriodo(
            $periodo,
            false,
            $fechaMaximaEmision
        );
    }

    /**
     * Ejecuta las validaciones y procesa el periodo.
     *
     * @return array<string, mixed>
     */
    private function procesarPeriodo(
        string $periodo,
        bool $confirmarCambios,
        ?string $fechaMaximaEmision
    ): array {
        $hoy = new DateTimeImmutable(
            'today',
            new DateTimeZone('America/Mexico_City')
        );

        $fechaActual = $hoy->format('Y-m-d');

        // En la automatización, impedir utilizar
        // fechas futuras o de otro periodo.
        if ($fechaMaximaEmision !== null) {
            $this->validarFechaMaximaEmision(
                $periodo,
                $fechaMaximaEmision,
                $fechaActual
            );
        }

        // La simulación selecciona solamente
        // contratos cuya fecha de corte ya llegó.
        // Sin fecha máxima conserva el modo manual.
        $simulacion = $this->simulador->simularPeriodo(
            $periodo,
            $fechaMaximaEmision
        );

        if ($periodo < $hoy->format('Y-m')) {
            throw new DomainException(
                'No se permite generar adeudos de meses anteriores.'
            );
        }

        if ((int) $simulacion['pendientesDefinicion'] > 0) {
            throw new DomainException(
                'Hay reglas con fechas pendientes de definición.'
            );
        }

        if ((int) $simulacion['reglasActivas'] === 0) {
            throw new DomainException(
                'No existen reglas activas. No se generó ningún adeudo.'
            );
        }

        // Solamente procesar registros generables.
        // Los corte_pendiente quedan excluidos.
        $candidatos = array_values(
            array_filter(
                $simulacion['detalle'],
                static fn (array $registro): bool =>
                    $registro['resultado'] === 'generable'
            )
        );

        // Orden estable para bloquear contratos.
        usort(
            $candidatos,
            static fn (
                array $primero,
                array $segundo
            ): int =>
                (int) $primero['idContrato']
                <=> (int) $segundo['idContrato']
        );

        foreach ($candidatos as $registro) {
            if (
                (string) $registro['fechaLimitePago']
                < $fechaActual
            ) {
                throw new DomainException(
                    'Existe una cuota con plazo vencido; no se generó el lote.'
                );
            }

            if (
                $fechaMaximaEmision !== null
                && (string) $registro['fechaEmision']
                    > $fechaMaximaEmision
            ) {
                throw new DomainException(
                    'La simulación incluyó una cuota antes de su fecha de corte.'
                );
            }
        }

        $modo = $confirmarCambios
            ? 'generacion_controlada'
            : 'prueba_revertida';

        if ($candidatos === []) {
            return [
                'periodo' => $periodo,
                'modo' => $modo,
                'creados' => 0,
                'omitidosPorDuplicado' => 0,
                'detalle' => []
            ];
        }

        $creados = [];
        $omitidosPorDuplicado = 0;

        $this->repositorio->iniciarTransaccion();

        try {
            foreach ($candidatos as $registro) {
                $idContrato = (int) $registro['idContrato'];

                // Bloquear el contrato antes de
                // comprobar si tiene mantenimiento.
                $contrato = $this->repositorio
                    ->bloquearContrato($idContrato);

                if ($contrato === false) {
                    throw new DomainException(
                        "El contrato #{$idContrato} dejó de estar vigente."
                    );
                }

                if (
                    (string) $contrato['tipoUnidad']
                    !== (string) $registro['tipoUnidad']
                ) {
                    throw new DomainException(
                        "Cambió el tipo de unidad del contrato #{$idContrato}."
                    );
                }

                // Recuperar las reglas actuales.
                $reglasActuales = $this->repositorio
                    ->listarReglasActivas();

                $regla = $this->seleccionarRegla(
                    $reglasActuales,
                    (string) $contrato['tipoUnidad']
                );

                if ($regla === null) {
                    throw new DomainException(
                        "El contrato #{$idContrato} ya no tiene regla activa."
                    );
                }

                if (
                    (int) $regla['diaLimitePago']
                    < (int) $regla['diaCorte']
                ) {
                    throw new DomainException(
                        "La regla del contrato #{$idContrato} tiene fechas inconsistentes."
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

                // Comprobar que la configuración
                // no cambió después de simular.
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
                        "Cambió la configuración del contrato #{$idContrato}."
                    );
                }

                $fechaInicio = (string) $contrato['fechaInicio'];

                $fechaFin = $contrato['fechaFin'] === null
                    ? null
                    : (string) $contrato['fechaFin'];

                // Verificar vigencia del contrato
                // y coherencia de las fechas.
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

                if ($fechaLimitePago < $fechaActual) {
                    throw new DomainException(
                        "La cuota del contrato #{$idContrato} ya venció."
                    );
                }

                // NUEVO: verificar nuevamente el corte
                // dentro de la transacción.
                if (
                    $fechaMaximaEmision !== null
                    && $fechaEmision > $fechaMaximaEmision
                ) {
                    throw new DomainException(
                        "Todavía no llega el corte del contrato #{$idContrato}."
                    );
                }

                // Segunda comprobación de duplicados.
                if (
                    $this->repositorio
                        ->existeCobroMantenimiento(
                            $idContrato,
                            $periodo
                        )
                ) {
                    $omitidosPorDuplicado++;
                    continue;
                }

                // Crear el adeudo.
                // El recargo inicial permanece en 0.00.
                $idEstadoCuenta = $this->repositorio
                    ->crearAdeudo(
                        $idContrato,
                        (int) $regla['idConcepto'],
                        $periodo,
                        $fechaEmision,
                        $fechaLimitePago,
                        $montoPrincipal
                    );

                // Guardar la configuración del recargo al crear
                // el adeudo. Ambos registros comparten transacción.
                // Si falla el INSERT auxiliar, se revierte el adeudo.
                $this->repositorio->registrarConfiguracionRecargo(
                    $idEstadoCuenta,
                    (int) $regla['idReglaCobro'],
                    (string) $regla['tipoRecargo'],
                    (string) $regla['valorRecargo']
                );

                $cobro = [
                    'idContrato' => $idContrato,
                    'idConcepto' => (int) $regla['idConcepto'],
                    'montoPrincipal' => $montoPrincipal
                ];

                if ($confirmarCambios) {
                    $cobro['idEstadoCuenta'] = $idEstadoCuenta;
                }

                $creados[] = $cobro;
            }

            if ($confirmarCambios) {
                $this->repositorio
                    ->confirmarTransaccion();
            } else {
                // En los ensayos nunca se conservan cobros.
                $this->repositorio
                    ->revertirTransaccion();
            }

        } catch (Throwable $error) {
            $this->repositorio->revertirTransaccion();
            throw $error;
        }

        return [
            'periodo' => $periodo,
            'modo' => $modo,
            'creados' => count($creados),
            'omitidosPorDuplicado' => $omitidosPorDuplicado,
            'detalle' => $creados
        ];
    }

    /**
     * Valida la fecha máxima para la automatización.
     */
    private function validarFechaMaximaEmision(
        string $periodo,
        string $fechaMaximaEmision,
        string $fechaActual
    ): void {
        $fecha = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fechaMaximaEmision
        );

        if (
            $fecha === false
            || $fecha->format('Y-m-d') !== $fechaMaximaEmision
            || substr($fechaMaximaEmision, 0, 7) !== $periodo
            || $fechaMaximaEmision > $fechaActual
        ) {
            throw new InvalidArgumentException(
                'La fecha máxima debe pertenecer al periodo actual '
                . 'y no puede ser futura.'
            );
        }
    }

    /**
     * Selecciona la regla específica o la general.
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
     * Ajusta el día configurado al mes correspondiente.
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
     * Normaliza importes DECIMAL(12,2).
     */
    private function normalizarMonto(
        mixed $monto
    ): string {
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
