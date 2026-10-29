<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/ResidentesRepositorio.php';

final class ResidentesServicio
{
    public function __construct(private ResidentesRepositorio $repositorio) {}

    public function listarActivos(): array
    {
        return $this->repositorio->listarActivos();
    }
}
