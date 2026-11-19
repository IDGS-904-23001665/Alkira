<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/KddRepositorio.php';
require_once __DIR__ . '/KddPrediccionServicio.php';

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

    $senales = $this->repositorio
        ->obtenerSeguimientoCarteraFiltrado(
        $idInmueble,
        $periodo
    );

    $seguimientoCartera = $this->evaluarSeguimientoCartera($senales);


    // Prediccion de morosidad basada en historial anterior
    // a la fecha de corte seleccionada.
    $fechaCorte = $periodo !== null
        ? $periodo . '-01'
        : date('Y-m-d');

    $registrosPrediccion = $this->repositorio
        ->obtenerDatosPrediccion(
            $idInmueble,
            $fechaCorte
        );

    $prediccionMorosidad = KddPrediccionServicio::estimar(
        $registrosPrediccion,
        $fechaCorte
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
        'seguimientoCartera' => $seguimientoCartera,
        'prediccionMorosidad' => $prediccionMorosidad,
        'patrones' => $patrones,

        'limitePorLista' => 200,

        'nota' =>
            'Alertas descriptivas basadas en reglas; no son predicciones de IA.'
    ];
}


private function evaluarSeguimientoCartera(array $senales): array
{
    $conteos = [
        'alta' => 0,
        'media' => 0,
        'baja' => 0,
        'sinAlerta' => 0,
        'unidadesProximasAVencer' => 0,
        'periodosMultiples' => 0,
        'totalUnidades' => 0
    ];

    $unidades = [];

    foreach ($senales as $fila) {
        $vencidas = (int) $fila['cuentasVencidas'];
        $periodos = (int) $fila['periodosVencidos'];
        $proximas = (int) $fila['cuentasProximasAVencer'];
        $dias = max(0, (int) $fila['diasMaximoAtraso']);
        $vencido = max(0, (float) $fila['saldoVencido']);

        $puntos = 0;
        $motivos = [];

        if ($vencidas > 0) {
            $puntos += 35;
            $motivos[] = $vencidas . ' cuenta(s) vencida(s)';
        }

        if ($periodos >= 2) {
            $puntos += 25;
            $motivos[] = 'Adeudos de ' . $periodos . ' periodos distintos';
            $conteos['periodosMultiples']++;
        }

        if ($dias > 30) {
            $puntos += 20;
            $motivos[] = 'Atraso de hasta ' . $dias . ' días';
        }

        if ($vencido >= 5000) {
            $puntos += 20;
            $motivos[] = 'Saldo vencido de $5,000 o más';
        }

        if ($proximas > 0) {
            $puntos += 20;
            $motivos[] = $proximas . ' cuenta(s) por vencer en 15 días';
            $conteos['unidadesProximasAVencer']++;
        }

        $puntos = min(100, $puntos);

        $prioridad = $puntos >= 70
            ? 'alta'
            : ($puntos >= 35
                ? 'media'
                : ($puntos > 0 ? 'baja' : 'sinAlerta'));

        $conteos[$prioridad]++;

        $unidades[] = [
            'idUnidad' => (int) $fila['idUnidad'],
            'unidad' => (string) $fila['unidad'],
            'cuentasPendientes' => (int) $fila['cuentasPendientes'],
            'cuentasVencidas' => $vencidas,
            'periodosVencidos' => $periodos,
            'cuentasProximasAVencer' => $proximas,
            'diasMaximoAtraso' => $dias,
            'saldoPendiente' => (float) $fila['saldoPendiente'],
            'saldoVencido' => $vencido,
            'puntuacion' => $puntos,
            'prioridad' => $prioridad,
            'razones' => implode('; ', $motivos) ?: 'Sin señales de alerta'
        ];
    }

    usort($unidades, static function (array $a, array $b): int {
        return ($b['puntuacion'] <=> $a['puntuacion'])
            ?: ($b['saldoVencido'] <=> $a['saldoVencido']);
    });

    $conteos['totalUnidades'] = count($unidades);

    return [
        'resumen' => $conteos,
        'unidades' => $unidades,
        'limite' => 200,
        'metodologia' =>
            'Evaluación descriptiva de prioridad de seguimiento. '
            . 'No es un modelo predictivo entrenado.'
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