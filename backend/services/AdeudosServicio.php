<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/repos/AdeudosRepositorio.php';

final class AdeudosServicio
{
    private AdeudosRepositorio $repositorio;

    public function __construct(AdeudosRepositorio $repositorio)
    {
        $this->repositorio = $repositorio;
    }

    /** @return array<string, mixed> */
    public function consultarPendientes(int $idInquilino): array
    {
        $adeudos = $this->repositorio->obtenerPendientes(
            $idInquilino
        );

        $totalCentavos = 0;

        foreach ($adeudos as $adeudo) {
            $totalCentavos += $this->convertirACentavos(
                (string) $adeudo['montoPendiente']
            );
        }

        return [
            'adeudos' => $adeudos,
            'totalPendiente' => sprintf(
                '%d.%02d',
                intdiv($totalCentavos, 100),
                $totalCentavos % 100
            ),
        ];
    }

    private function convertirACentavos(string $monto): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $monto)) {
            throw new UnexpectedValueException(
                'La base de datos devolvió un monto inválido.'
            );
        }

        $partes = explode('.', $monto, 2);
        $entero = (int) $partes[0];

        $centavos = isset($partes[1])
            ? (int) str_pad($partes[1], 2, '0')
            : 0;

        if ($entero > intdiv(PHP_INT_MAX - $centavos, 100)) {
            throw new OverflowException(
                'El total excede el tamaño numérico disponible.'
            );
        }

        return $entero * 100 + $centavos;
    }
}
