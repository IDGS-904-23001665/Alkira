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

        return [
            'financiero' => $financiero,
            'operativo' => $operativo
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