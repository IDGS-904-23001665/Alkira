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
     * Simula la generación mensual de adeudos.
     *
     * No inserta, actualiza ni elimina registros.
     *
     * @return array<string, mixed>
     */
    public function simularPeriodo(string $periodo): array
    {
        $fechaBase = $this->validarPeriodo($periodo);

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

            $tipoUnidad = (string) $contrato['tipoUnidad'];

            $regla = $reglasPorAlcance[$tipoUnidad]
                ?? $reglasPorAlcance['general']
                ?? null;

            if ($regla === null) {
                $detalle[] = [
                    'idContrato' => $idContrato,
                    'tipoUnidad' => $tipoUnidad,
                    'resultado' => 'sin_regla',
                    'motivo' => 'No existe una regla activa aplicable.'
                ];

                $omitidos++;
                continue;
            }

            $fechaEmision = $this->calcularFecha(
                $fechaBase,
                (int) $regla['diaCorte']
            );

            $fechaLimitePago = $this->calcularFecha(
                $fechaBase,
                (int) $regla['diaLimitePago']
            );

            // Todavía no definimos si el vencimiento
            // puede pasar al mes siguiente.
            if ($fechaLimitePago < $fechaEmision) {
                $detalle[] = [
                    'idContrato' => $idContrato,
                    'tipoUnidad' => $tipoUnidad,
                    'idReglaCobro' => (int) $regla['idReglaCobro'],
                    'resultado' => 'pendiente_definicion',
                    'motivo' => 'La fecha límite resulta anterior al corte.'
                ];

                $pendientesDefinicion++;
                continue;
            }

            $fechaInicio = (string) $contrato['fechaInicio'];

            $fechaFin = $contrato['fechaFin'] === null
                ? null
                : (string) $contrato['fechaFin'];

            // Criterio provisional:
            // el contrato debe estar vigente
            // durante la fecha de emisión.
            if (
                $fechaInicio > $fechaEmision
                || (
                    $fechaFin !== null
                    && $fechaFin < $fechaEmision
                )
            ) {
                $detalle[] = [
                    'idContrato' => $idContrato,
                    'tipoUnidad' => $tipoUnidad,
                    'resultado' => 'fuera_vigencia',
                    'motivo' => 'El contrato no cubre la fecha de emisión.'
                ];

                $omitidos++;
                continue;
            }

            $existeCobro = $this->repositorio
                ->existeCobroMantenimiento(
                    $idContrato,
                    $periodo
                );

            if ($existeCobro) {
                $detalle[] = [
                    'idContrato' => $idContrato,
                    'tipoUnidad' => $tipoUnidad,
                    'resultado' => 'ya_registrado',
                    'motivo' => 'Ya existe mantenimiento para este periodo.'
                ];

                $omitidos++;
                continue;
            }

            $montoPrincipal = $this->validarMonto(
                $regla['montoCuota']
            );

            $detalle[] = [
                'idContrato' => $idContrato,
                'idUnidad' => (int) $contrato['idUnidad'],
                'tipoUnidad' => $tipoUnidad,
                'idReglaCobro' => (int) $regla['idReglaCobro'],
                'idConcepto' => (int) $regla['idConcepto'],
                'periodo' => $periodo,
                'fechaEmision' => $fechaEmision,
                'fechaLimitePago' => $fechaLimitePago,
                'montoPrincipal' => $montoPrincipal,
                'recargoMora' => '0.00',
                'resultado' => 'generable',
                'motivo' => 'El contrato puede recibir el adeudo.'
            ];

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
     * Organiza las reglas por alcance.
     *
     * No permite dos reglas activas
     * para el mismo tipo de unidad.
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
                    'Existen varias reglas activas para '
                    . "el alcance {$alcance}."
                );
            }

            $reglasPorAlcance[$alcance] = $regla;
        }

        return $reglasPorAlcance;
    }

    /**
     * Calcula una fecha del periodo.
     *
     * Criterio provisional:
     * si el día configurado no existe,
     * utiliza el último día del mes.
     *
     * Ejemplo: día 31 en febrero -> 28 o 29.
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

        $ultimoDia = (int) $fechaBase->format('t');

        $diaAplicable = min($dia, $ultimoDia);

        return $fechaBase
            ->setDate(
                (int) $fechaBase->format('Y'),
                (int) $fechaBase->format('m'),
                $diaAplicable
            )
            ->format('Y-m-d');
    }

    /**
     * Valida el formato DECIMAL(12,2).
     */
    private function validarMonto(
        mixed $monto
    ): string {
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

        return $partes[0] . '.'
            . str_pad(
                $partes[1] ?? '',
                2,
                '0',
                STR_PAD_RIGHT
            );
    }
}
