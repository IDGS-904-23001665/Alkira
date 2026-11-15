<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/repos/GeneracionAdeudosRepositorio.php';

final class GeneracionAdeudosServicio
{
    private const ALCANCES_PERMITIDOS = [
        'general',
        'departamento',
        'casa',
        'local',
        'oficina',
        'otro'
    ];

    public function __construct(
        private GeneracionAdeudosRepositorio $repositorio
    ) {
    }

    /**
     * Simula los adeudos del periodo sin escribir en MySQL.
     *
     * Si se indica $fechaMaximaEmision, las cuotas con un
     * corte posterior se marcan como corte_pendiente.
     *
     * Sin ese argumento se conserva el comportamiento
     * de la pantalla administrativa.
     *
     * @return array<string, mixed>
     */
    public function simularPeriodo(
        string $periodo,
        ?string $fechaMaximaEmision = null
    ): array {
        $fechaBase = $this->validarPeriodo($periodo);

        if ($fechaMaximaEmision !== null) {
            $fechaValidada = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $fechaMaximaEmision
            );

            if (
                $fechaValidada === false
                || $fechaValidada->format('Y-m-d') !== $fechaMaximaEmision
            ) {
                throw new InvalidArgumentException(
                    'La fecha máxima de emisión debe tener el formato YYYY-MM-DD.'
                );
            }
        }

        $hoy = new DateTimeImmutable(
            'today',
            new DateTimeZone('America/Mexico_City')
        );

        $periodoActual = $hoy->format('Y-m');
        $fechaActual = $hoy->format('Y-m-d');

        $contratos = $this->repositorio
            ->listarContratosVigentes();

        $reglas = $this->repositorio
            ->listarReglasActivas();

        $reglasPorAlcance = $this->organizarReglas(
            $reglas
        );

        $detalle = [];
        $generables = 0;
        $omitidos = 0;
        $pendientesDefinicion = 0;

        foreach ($contratos as $contrato) {
            $idContrato = (int) $contrato['idContrato'];
            $idUnidad = (int) $contrato['idUnidad'];
            $tipoUnidad = (string) $contrato['tipoUnidad'];

            $regla = $reglasPorAlcance[$tipoUnidad]
                ?? $reglasPorAlcance['general']
                ?? null;

            // No existe una regla aplicable.
            if ($regla === null) {
                $detalle[] = [
                    'idContrato' => $idContrato,
                    'idUnidad' => $idUnidad,
                    'tipoUnidad' => $tipoUnidad,
                    'resultado' => 'sin_regla',
                    'motivo' => 'No existe una regla activa aplicable.'
                ];

                $omitidos++;
                continue;
            }

            $idReglaCobro = (int) $regla['idReglaCobro'];
            $diaCorte = (int) $regla['diaCorte'];
            $diaLimitePago = (int) $regla['diaLimitePago'];

            // La fecha límite debe ser igual
            // o posterior al día de corte.
            if ($diaLimitePago < $diaCorte) {
                $detalle[] = [
                    'idContrato' => $idContrato,
                    'idUnidad' => $idUnidad,
                    'tipoUnidad' => $tipoUnidad,
                    'idReglaCobro' => $idReglaCobro,
                    'resultado' => 'pendiente_definicion',
                    'motivo' => 'El día límite debe ser igual o posterior al día de corte.'
                ];

                $pendientesDefinicion++;
                continue;
            }

            // Ajustar días 29, 30 o 31 cuando
            // el mes tenga menos días.
            $fechaEmision = $this->calcularFecha(
                $fechaBase,
                $diaCorte
            );

            $fechaLimitePago = $this->calcularFecha(
                $fechaBase,
                $diaLimitePago
            );

            $montoPrincipal = $this->validarMonto(
                $regla['montoCuota']
            );

            $registro = [
                'idContrato' => $idContrato,
                'idUnidad' => $idUnidad,
                'tipoUnidad' => $tipoUnidad,
                'idReglaCobro' => $idReglaCobro,
                'idConcepto' => (int) $regla['idConcepto'],
                'periodo' => $periodo,
                'fechaEmision' => $fechaEmision,
                'fechaLimitePago' => $fechaLimitePago,
                'montoPrincipal' => $montoPrincipal,
                'recargoMora' => '0.00'
            ];

            $fechaInicio = (string) $contrato['fechaInicio'];

            $fechaFin = $contrato['fechaFin'] === null
                ? null
                : (string) $contrato['fechaFin'];

            // El contrato debe estar vigente
            // durante su fecha de corte.
            if (
                $fechaInicio > $fechaEmision
                || (
                    $fechaFin !== null
                    && $fechaFin < $fechaEmision
                )
            ) {
                $registro['resultado'] = 'fuera_vigencia';
                $registro['motivo'] =
                    'El contrato no está vigente en la fecha de corte.';

                $detalle[] = $registro;
                $omitidos++;
                continue;
            }

            // Comprobar mantenimientos existentes,
            // incluso si utilizan otro concepto.
            $existeCobro = $this->repositorio
                ->existeCobroMantenimiento(
                    $idContrato,
                    $periodo
                );

            if ($existeCobro) {
                $registro['resultado'] = 'ya_registrado';
                $registro['motivo'] =
                    'Ya existe mantenimiento para este contrato y periodo.';

                $detalle[] = $registro;
                $omitidos++;
                continue;
            }

            // No permitir generación retroactiva.
            if ($periodo < $periodoActual) {
                $registro['resultado'] = 'periodo_pasado';
                $registro['motivo'] =
                    'La generación retroactiva no está permitida.';

                $detalle[] = $registro;
                $omitidos++;
                continue;
            }

            // NUEVO: comprobar si ya llegó el corte.
            //
            // Se utiliza exclusivamente cuando
            // recibimos una fecha máxima de emisión.
            if (
                $fechaMaximaEmision !== null
                && $fechaEmision > $fechaMaximaEmision
            ) {
                $registro['resultado'] = 'corte_pendiente';
                $registro['motivo'] =
                    'Aún no llega la fecha de corte configurada.';

                $detalle[] = $registro;
                $omitidos++;
                continue;
            }

            // No generar cuotas ya vencidas.
            if ($fechaLimitePago < $fechaActual) {
                $registro['resultado'] = 'plazo_vencido';
                $registro['motivo'] =
                    'La fecha límite de pago ya transcurrió.';

                $detalle[] = $registro;
                $omitidos++;
                continue;
            }

            // El contrato puede recibir su adeudo.
            $registro['resultado'] = 'generable';
            $registro['motivo'] =
                'El contrato puede recibir el adeudo sin recargo inicial.';

            $detalle[] = $registro;
            $generables++;
        }

        return [
            'periodo' => $periodo,
            'modo' => 'simulacion',
            'reglasActivas' => count($reglas),
            'contratosEvaluados' => count($contratos),
            'generables' => $generables,
            'omitidos' => $omitidos,
            'pendientesDefinicion' => $pendientesDefinicion,
            'detalle' => $detalle
        ];
    }

    /**
     * Valida el periodo YYYY-MM.
     */
    private function validarPeriodo(
        string $periodo
    ): DateTimeImmutable {
        if (
            preg_match(
                '/^[0-9]{4}-(0[1-9]|1[0-2])$/D',
                $periodo
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'El periodo debe tener el formato YYYY-MM.'
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
                'El periodo indicado no es válido.'
            );
        }

        return $fecha;
    }

    /**
     * Organiza reglas por alcance.
     *
     * @param array<int, array<string, mixed>> $reglas
     * @return array<string, array<string, mixed>>
     */
    private function organizarReglas(
        array $reglas
    ): array {
        $reglasPorAlcance = [];

        foreach ($reglas as $regla) {
            $alcance = (string) $regla['alcance'];

            if (
                !in_array(
                    $alcance,
                    self::ALCANCES_PERMITIDOS,
                    true
                )
            ) {
                throw new DomainException(
                    'Existe una regla con alcance no permitido.'
                );
            }

            if (isset($reglasPorAlcance[$alcance])) {
                throw new DomainException(
                    'Existen varias reglas activas para el alcance '
                    . $alcance . '.'
                );
            }

            $reglasPorAlcance[$alcance] = $regla;
        }

        return $reglasPorAlcance;
    }

    /**
     * Calcula una fecha válida dentro del mes.
     * Si el día no existe, utiliza el último.
     */
    private function calcularFecha(
        DateTimeImmutable $fechaBase,
        int $dia
    ): string {
        if ($dia < 1 || $dia > 31) {
            throw new DomainException(
                'La regla contiene un día de cobro inválido.'
            );
        }

        $diaAplicable = min(
            $dia,
            (int) $fechaBase->format('t')
        );

        return $fechaBase->setDate(
            (int) $fechaBase->format('Y'),
            (int) $fechaBase->format('m'),
            $diaAplicable
        )->format('Y-m-d');
    }

    /**
     * Valida y normaliza DECIMAL(12,2).
     */
    private function validarMonto(mixed $monto): string
    {
        if (
            !is_string($monto)
            && !is_int($monto)
            && !is_float($monto)
        ) {
            throw new DomainException(
                'La cuota configurada no es válida.'
            );
        }

        $valor = trim((string) $monto);

        if (
            preg_match(
                '/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',
                $valor
            ) !== 1
        ) {
            throw new DomainException(
                'La cuota contiene un importe inválido.'
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
