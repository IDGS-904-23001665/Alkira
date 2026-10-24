<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/KddRepositorio.php';

final class KddServicio
{
    private KddRepositorio $repositorio;

    public function __construct(KddRepositorio $repositorio)
    {
        $this->repositorio = $repositorio;
    }

    public function consultarIndicadores(): array
    {
        $financiero = $this->repositorio
            ->obtenerResumenFinanciero();

        $operativo = $this->repositorio
            ->obtenerResumenOperativo();

        $this->validarResultado(
            $financiero,
            ['totalEstadosCuenta', 'totalFacturado',
             'totalAplicado', 'totalPendiente',
             'totalVencido', 'cuentasVencidas',
             'porcentajeMorosidad']
        );

        $this->validarResultado(
            $operativo,
            ['totalIncidencias', 'incidenciasUrgentes',
             'totalTickets', 'ticketsCerrados',
             'ticketsAbiertos', 'promedioHorasResolucion']
        );

        $tendenciaMensual = $this->repositorio
            ->obtenerTendenciaMensual();

        return [
            'financiero' => $financiero,
            'operativo' => $operativo,
            'tendenciaMensual' => $tendenciaMensual
        ];
    }

    public function consultarInmuebles(): array
{
    return $this->repositorio->obtenerInmuebles();
}

public function consultarFinancieroFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    if ($idInmueble !== null && $idInmueble <= 0) {
        throw new InvalidArgumentException(
            'El identificador del inmueble no es válido.'
        );
    }

    if (
        $periodo !== null &&
        !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)
    ) {
        throw new InvalidArgumentException(
            'El periodo debe utilizar el formato AAAA-MM.'
        );
    }

    $resultado = $this->repositorio
        ->obtenerResumenFinancieroFiltrado(
            $idInmueble,
            $periodo
        );

    $this->validarResultado(
        $resultado,
        [
            'totalEstadosCuenta',
            'totalFacturado',
            'totalAplicado',
            'totalPendiente',
            'totalVencido',
            'cuentasVencidas',
            'porcentajeMorosidad'
        ]
    );

    return $resultado;
}


public function consultarAnalisisFinancieroFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    $financiero = $this->consultarFinancieroFiltrado(
        $idInmueble,
        $periodo
    );

    $tendenciaMensual = $this->repositorio
        ->obtenerTendenciaMensualFiltrada(
            $idInmueble,
            $periodo
        );

    return [
        'financiero' => $financiero,
        'tendenciaMensual' => $tendenciaMensual
    ];
}

    public function consultarOperativoFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    if ($idInmueble !== null && $idInmueble <= 0) {
        throw new InvalidArgumentException(
            'El identificador del inmueble no es válido.'
        );
    }

    if (
        $periodo !== null &&
        !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)
    ) {
        throw new InvalidArgumentException(
            'El periodo debe utilizar el formato AAAA-MM.'
        );
    }

    $resultado = $this->repositorio
        ->obtenerResumenOperativoFiltrado(
            $idInmueble,
            $periodo
        );

    $this->validarResultado(
        $resultado,
        [
            'totalIncidencias',
            'incidenciasUrgentes',
            'totalTickets',
            'ticketsCerrados',
            'ticketsAbiertos',
            'promedioHorasResolucion'
        ]
    );

    return $resultado;
}

    public function consultarReportes(
    ?int $idInmueble,
    ?string $periodo
): array {
    $financiero = $this->consultarFinancieroFiltrado(
        $idInmueble,
        $periodo
    );

    $operativo = $this->consultarOperativoFiltrado(
        $idInmueble,
        $periodo
    );

    $pagos = $this->repositorio
        ->obtenerHistorialPagosFiltrado(
            $idInmueble,
            $periodo
        );

    $mantenimiento = $this->repositorio
        ->obtenerHistorialOperativoFiltrado(
            $idInmueble,
            $periodo
        );

    $alertas = $this->repositorio
        ->obtenerAlertasMorosidadFiltradas(
            $idInmueble,
            $periodo
        );

    $patrones = [];

    if ((float) $financiero['totalVencido'] > 0) {
        $patrones[] = [
            'tipo' => 'financiero',
            'titulo' => 'Cartera vencida detectada',
            'detalle' =>
                'Hay saldos vencidos. Conviene revisar los contratos y su conciliación.'
        ];
    }

    if ((int) $operativo['incidenciasUrgentes'] > 0) {
        $patrones[] = [
            'tipo' => 'operativo',
            'titulo' => 'Incidencias de prioridad alta',
            'detalle' =>
                'Hay reportes urgentes dentro de los filtros seleccionados.'
        ];
    }

    if (
        $operativo['promedioHorasResolucion'] !== null &&
        (float) $operativo['promedioHorasResolucion'] > 72
    ) {
        $patrones[] = [
            'tipo' => 'operativo',
            'titulo' => 'Resolución promedio mayor a 72 horas',
            'detalle' =>
                'Revisar la atención de mantenimiento y la capacidad operativa.'
        ];
    }

    return [
        'resumen' => [
            'financiero' => $financiero,
            'operativo' => $operativo
        ],

        'pagos' => $pagos,
        'mantenimiento' => $mantenimiento,
        'alertas' => $alertas,
        'patrones' => $patrones,

        'limitePorLista' => 200,

        'nota' =>
            'Alertas descriptivas basadas en reglas; no son predicciones de IA.'
    ];
}

    private function validarResultado(
        array $resultado,
        array $campos
    ): void {
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $resultado)) {
                throw new UnexpectedValueException(
                    'Falta el indicador KDD: ' . $campo
                );
            }
        }
    }
}