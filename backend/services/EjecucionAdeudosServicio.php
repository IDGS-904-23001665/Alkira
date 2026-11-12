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
     * Generación real: confirma los adeudos creados.
     * Esta operación aún no está habilitada en la API.
     *
     * @return array<string, mixed>
     */
    public function generarPeriodo(string $periodo): array
    {
        return $this->procesarPeriodo($periodo, true);
    }

    /**
     * Ensayo integral: ejecuta las inserciones,
     * pero siempre revierte la transacción.
     *
     * @return array<string, mixed>
     */
    public function probarPeriodo(string $periodo): array
    {
        return $this->procesarPeriodo($periodo, false);
    }

    /**
     * Procesa un periodo con las mismas reglas
     * para pruebas y ejecución real.
     *
     * @return array<string, mixed>
     */
    private function procesarPeriodo(
        string $periodo,
        bool $confirmarCambios
    ): array {
        $hoy = new DateTimeImmutable(
            'today',
            new DateTimeZone('America/Mexico_City')
        );

        $fechaActual = $hoy->format('Y-m-d');

        $simulacion = $this->simulador
            ->simularPeriodo($periodo);

        if ($periodo < $hoy->format('Y-m')) {
            throw new DomainException(
                'No se permite generar adeudos de meses anteriores. '
                . 'Los periodos retroactivos requieren revisión.'
            );
        }

        if (
            (int) $simulacion['pendientesDefinicion'] > 0
        ) {
            throw new DomainException(
                'Existen fechas pendientes de definición. '
                . 'No se generó ningún adeudo.'
            );
        }

        if ((int) $simulacion['reglasActivas'] === 0) {
            throw new DomainException(
                'No existen reglas activas. '
                . 'No se generó ningún adeudo.'
            );
        }

        $candidatos = array_values(
            array_filter(
                $simulacion['detalle'],
                static fn (array $registro): bool =>
                    $registro['resultado'] === 'generable'
            )
        );

        // Ordenar para bloquear contratos
        // siempre en el mismo orden.
        usort(
            $candidatos,
            static fn (
                array $primero,
                array $segundo
            ): int =>
                (int) $primero['idContrato']
                <=>
                (int) $segundo['idContrato']
        );

        // No procesar cuotas cuyo plazo terminó.
        foreach ($candidatos as $registro) {
            if (
                (string) $registro['fechaLimitePago']
                < $fechaActual
            ) {
                throw new DomainException(
                    'Hay cuotas cuya fecha límite ya venció. '
                    . 'No se generó ningún adeudo.'
                );
            }
        }

        $modo = $confirmarCambios
            ? 'generacion_controlada'
            : 'prueba_revertida';

        // No hace falta abrir una transacción
        // cuando no hay cobros generables.
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
        $omitidosPorDuplicado = [];

        $this->repositorio->iniciarTransaccion();

        try {
            foreach ($candidatos as $registro) {
                $idContrato = (int) $registro['idContrato'];

                // Bloquear el contrato.
                $contrato = $this->repositorio
                    ->bloquearContrato($idContrato);

                if ($contrato === false) {
                    throw new DomainException(
                        "El contrato #{$idContrato} "
                        . 'dejó de estar vigente. '
                        . 'Vuelve a ejecutar la simulación.'
                    );
                }

                if (
                    (string) $contrato['tipoUnidad']
                    !== (string) $registro['tipoUnidad']
                ) {
                    throw new DomainException(
                        "Cambió el tipo de unidad del "
                        . "contrato #{$idContrato}. "
                        . 'Vuelve a simular.'
                    );
                }

                // Consultar las reglas actuales
                // después del bloqueo.
                $reglasActuales = $this->repositorio
                    ->listarReglasActivas();

                $regla = $this->seleccionarRegla(
                    $reglasActuales,
                    (string) $contrato['tipoUnidad']
                );

                if ($regla === null) {
                    throw new DomainException(
                        "El contrato #{$idContrato} "
                        . 'ya no tiene una regla aplicable.'
                    );
                }

                // Verificar el orden de las fechas.
                if (
                    (int) $regla['diaLimitePago']
                    < (int) $regla['diaCorte']
                ) {
                    throw new DomainException(
                        "La regla del contrato #{$idContrato} "
                        . 'tiene el día límite anterior '
                        . 'al día de corte.'
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

                // Impedir cambios de configuración
                // entre la simulación y la ejecución.
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
                        "La configuración del contrato "
                        . "#{$idContrato} cambió. "
                        . 'Vuelve a simular.'
                    );
                }

                $fechaInicio = (string) $contrato[
                    'fechaInicio'
                ];

                $fechaFin = $contrato['fechaFin'] === null
                    ? null
                    : (string) $contrato['fechaFin'];

                // Comprobar vigencia del contrato.
                if (
                    $fechaInicio > $fechaEmision
                    || (
                        $fechaFin !== null
                        && $fechaFin < $fechaEmision
                    )
                    || $fechaLimitePago < $fechaEmision
                ) {
                    throw new DomainException(
                        "Las fechas del contrato "
                        . "#{$idContrato} requieren revisión."
                    );
                }

                if ($fechaLimitePago < $fechaActual) {
                    throw new DomainException(
                        "La cuota del contrato #{$idContrato} "
                        . 'ya venció. '
                        . 'No se generó ningún adeudo.'
                    );
                }

                // Comprobar nuevamente si ya existe
                // mantenimiento para ese contrato y mes.
                if (
                    $this->repositorio
                        ->existeCobroMantenimiento(
                            $idContrato,
                            $periodo
                        )
                ) {
                    $omitidosPorDuplicado[] = $idContrato;
                    continue;
                }

                // Inserción mediante el repositorio.
                // El recargo comienza en 0.00.
                $idEstadoCuenta = $this->repositorio
                    ->crearAdeudo(
                        $idContrato,
                        (int) $regla['idConcepto'],
                        $periodo,
                        $fechaEmision,
                        $fechaLimitePago,
                        $montoPrincipal
                    );

                $cobro = [
                    'idContrato' => $idContrato,
                    'idConcepto' => (int) $regla['idConcepto'],
                    'montoPrincipal' => $montoPrincipal
                ];

                // No mostrar como persistente un ID
                // generado durante una prueba.
                if ($confirmarCambios) {
                    $cobro['idEstadoCuenta'] = $idEstadoCuenta;
                }

                $creados[] = $cobro;
            }

            if ($confirmarCambios) {
                // Modo real: guardar los cobros.
                $this->repositorio
                    ->confirmarTransaccion();
            } else {
                // Modo de prueba: deshacer todo.
                $this->repositorio
                    ->revertirTransaccion();
            }

        } catch (Throwable $error) {
            // Ante cualquier error, revertir.
            $this->repositorio
                ->revertirTransaccion();

            throw $error;
        }

        return [
            'periodo' => $periodo,
            'modo' => $modo,

            // En modo de prueba representa
            // cobros ensayados, no persistidos.
            'creados' => count($creados),

            'omitidosPorDuplicado' => count(
                $omitidosPorDuplicado
            ),
            'detalle' => $creados
        ];
    }

    /**
     * Da prioridad a la regla específica
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
                    'Existen dos reglas activas '
                    . "para el alcance {$alcance}."
                );
            }

            $porAlcance[$alcance] = $regla;
        }

        return $porAlcance[$tipoUnidad]
            ?? $porAlcance['general']
            ?? null;
    }

    /**
     * Ajusta los días al último día
     * disponible del mes.
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
     * Normaliza los importes DECIMAL(12,2).
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
