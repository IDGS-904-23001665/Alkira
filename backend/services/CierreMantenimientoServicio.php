<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/CierreMantenimientoRepositorio.php';

final class CierreMantenimientoServicio
{
    public function __construct(private CierreMantenimientoRepositorio $repositorio)
    {
    }

    public function obtenerMateriales(int $idTicket): array
    {
        return $this->repositorio->obtenerMateriales($idTicket);
    }

    public function cerrar(int $idTicket, array $datos, int $idAdministrador): array
    {
        if ($idAdministrador < 1) {
            throw new InvalidArgumentException('Administrador no válido.');
        }

        $materialesEntrada = $datos['materiales'] ?? null;
        $sinMateriales = $datos['sinMateriales'] ?? false;
        $comentario = $datos['comentarioCierre'] ?? '';

        if (!is_array($materialesEntrada) || !array_is_list($materialesEntrada)
            || count($materialesEntrada) > 40) {
            throw new InvalidArgumentException('Envía una lista de máximo 40 materiales.');
        }
        if (!is_bool($sinMateriales)) {
            throw new InvalidArgumentException('La indicación de materiales no es válida.');
        }
        if ($sinMateriales && count($materialesEntrada) !== 0) {
            throw new InvalidArgumentException('Marca sin materiales solamente si la lista está vacía.');
        }
        if (!$sinMateriales && count($materialesEntrada) === 0) {
            throw new InvalidArgumentException('Registra materiales o indica que no se utilizaron.');
        }
        if (!is_string($comentario) || strlen($comentario) > 1000) {
            throw new InvalidArgumentException('El comentario puede tener máximo 1000 caracteres.');
        }

        $materiales = [];
        $totalCentavos = 0;
        foreach ($materialesEntrada as $indice => $material) {
            if (!is_array($material)) {
                throw new InvalidArgumentException('Material no válido en la posición ' . ($indice + 1) . '.');
            }
            $descripcion = $material['descripcion'] ?? null;
            if (!is_string($descripcion) || strlen(trim($descripcion)) < 3
                || strlen(trim($descripcion)) > 180) {
                throw new InvalidArgumentException('La descripción del material debe tener de 3 a 180 caracteres.');
            }

            $cantidadCentésimas = $this->convertirCentésimas($material['cantidad'] ?? null, 4, 'cantidad');
            $precioCentavos = $this->convertirCentésimas($material['costoUnitario'] ?? null, 6, 'costo unitario');
            if ($cantidadCentésimas < 1) {
                throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
            }
            $subtotalCentavos = intdiv($cantidadCentésimas * $precioCentavos + 50, 100);
            $totalCentavos += $subtotalCentavos;
            if ($totalCentavos > 999999999999) {
                throw new InvalidArgumentException('El total excede el máximo permitido.');
            }
            $materiales[] = [
                'descripcion' => trim($descripcion),
                'cantidad' => $this->formatearCentésimas($cantidadCentésimas),
                'costoUnitario' => $this->formatearCentésimas($precioCentavos),
                'subtotal' => $this->formatearCentésimas($subtotalCentavos),
            ];
        }

        return $this->repositorio->cerrar(
            $idTicket,
            $idAdministrador,
            $materiales,
            $this->formatearCentésimas($totalCentavos),
            trim($comentario)
        );
    }

    private function convertirCentésimas(mixed $valor, int $maxEnteros, string $campo): int
    {
        if (!is_string($valor)
            || preg_match('/^\d{1,' . $maxEnteros . '}(?:\.\d{1,2})?$/D', $valor) !== 1) {
            throw new InvalidArgumentException('Formato inválido de ' . $campo . '. Usa hasta dos decimales.');
        }
        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '');
        return (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
    }

    private function formatearCentésimas(int $valor): string
    {
        return intdiv($valor, 100) . '.' . str_pad((string) ($valor % 100), 2, '0', STR_PAD_LEFT);
    }
}
