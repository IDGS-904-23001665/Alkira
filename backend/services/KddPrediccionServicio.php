<?php
declare(strict_types=1);

/**
 * Prediccion temprana de morosidad.
 *
 * Modelo probabilistico Beta-Binomial:
 * prior Beta(1.5, 1.5).
 *
 * Cuenta una observacion por unidad y periodo.
 * Un periodo tiene atraso cuando alguno de sus cargos
 * no fue cubierto a tiempo mediante pagos aprobados.
 */
final class KddPrediccionServicio
{
    public static function estimar(
        array $registros,
        string $fechaCorte
    ): array {
        $porUnidad = [];

        foreach ($registros as $registro) {
            $id = (int) $registro['idUnidad'];

            if ($id <= 0) {
                continue;
            }

            if (!isset($porUnidad[$id])) {
                $porUnidad[$id] = [
                    'idUnidad' => $id,
                    'unidad' => (string) $registro['unidad'],
                    'periodos' => []
                ];
            }

            if ($registro['idEstadoCuenta'] === null) {
                continue;
            }

            $periodo = (string) $registro['periodo'];
            $fecha = (string) $registro['fechaLimitePago'];
            $principal = (float) $registro['montoPrincipal'];
            $pagadoATiempo = (float) $registro['pagadoATiempo'];

            if ($periodo === '' || $principal <= 0) {
                continue;
            }

            $atraso = $pagadoATiempo + 0.009 < $principal;

            if (!isset($porUnidad[$id]['periodos'][$periodo])) {
                $porUnidad[$id]['periodos'][$periodo] = [
                    'periodo' => $periodo,
                    'fecha' => $fecha,
                    'atraso' => false
                ];
            }

            $item = &$porUnidad[$id]['periodos'][$periodo];

            $item['atraso'] = $item['atraso'] || $atraso;

            if ($fecha > $item['fecha']) {
                $item['fecha'] = $fecha;
            }

            unset($item);
        }

        $conteos = [
            'totalUnidades' => count($porUnidad),
            'conEstimacion' => 0,
            'sinHistorialSuficiente' => 0,
            'alta' => 0,
            'media' => 0,
            'baja' => 0,
            'evaluacionesHistoricas' => 0,
            'errorBrier' => null
        ];

        $errorAcumulado = 0.0;
        $unidades = [];

        foreach ($porUnidad as $datos) {
            $periodos = array_values($datos['periodos']);

            usort(
                $periodos,
                static function (array $a, array $b): int {
                    return strcmp($a['fecha'], $b['fecha'])
                        ?: strcmp($a['periodo'], $b['periodo']);
                }
            );

            $total = count($periodos);
            $atrasos = 0;
            $puntuales = 0;

            // Evaluacion temporal sin consultar periodos futuros.
            foreach ($periodos as $observacion) {
                $anteriores = $atrasos + $puntuales;

                if ($anteriores >= 2) {
                    $p = ($atrasos + 1.5)
                        / ($anteriores + 3.0);

                    $real = $observacion['atraso']
                        ? 1.0
                        : 0.0;

                    $errorAcumulado += ($p - $real) ** 2;

                    $conteos['evaluacionesHistoricas']++;
                }

                if ($observacion['atraso']) {
                    $atrasos++;
                } else {
                    $puntuales++;
                }
            }

            $riesgo = null;
            $nivel = 'sinDatos';
            $calidad = 'Historial insuficiente';

            if ($total >= 2) {
                $riesgo = round(
                    100.0 * ($atrasos + 1.5) / ($total + 3.0),
                    1
                );

                $nivel = $riesgo >= 65
                    ? 'alta'
                    : ($riesgo >= 40 ? 'media' : 'baja');

                $calidad = $total >= 6
                    ? 'Historial moderado'
                    : 'Preliminar';

                $conteos['conEstimacion']++;
                $conteos[$nivel]++;
            } else {
                $conteos['sinHistorialSuficiente']++;
            }

            $unidades[] = [
                'idUnidad' => $datos['idUnidad'],
                'unidad' => $datos['unidad'],
                'periodosAnalizados' => $total,
                'periodosConAtraso' => $atrasos,
                'periodosPuntuales' => $puntuales,
                'probabilidadAtraso' => $riesgo,
                'nivel' => $nivel,
                'calidad' => $calidad
            ];
        }

        usort(
            $unidades,
            static function (array $a, array $b): int {
                return ($b['probabilidadAtraso'] ?? -1)
                    <=> ($a['probabilidadAtraso'] ?? -1)
                    ?: strcmp($a['unidad'], $b['unidad']);
            }
        );

        if ($conteos['evaluacionesHistoricas'] > 0) {
            $conteos['errorBrier'] = round(
                $errorAcumulado
                    / $conteos['evaluacionesHistoricas'],
                4
            );
        }

        return [
            'fechaCorte' => $fechaCorte,
            'horizonte' =>
                'Siguiente periodo de cobro despues del corte',
            'resumen' => $conteos,
            'unidades' => $unidades,
            'metodologia' =>
                'Modelo probabilistico Beta-Binomial '
                . '(prior Beta(1.5, 1.5)). '
                . 'Aprende de periodos con vencimiento anterior '
                . 'al corte y pagos aprobados fechados a mas tardar '
                . 'el dia limite. Dos periodos minimos. '
                . 'Estimacion exploratoria, no calibrada como '
                . 'garantia de impago ni como modelo de IA.'
        ];
    }
}
