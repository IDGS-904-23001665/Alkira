<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/OrdenesTrabajoRepositorio.php';

final class OrdenesTrabajoServicio
{
    public function __construct(private OrdenesTrabajoRepositorio $repositorio)
    {
    }

    public function listarTickets(): array
    {
        return $this->repositorio->listarTickets();
    }

    public function listarTecnicos(): array
    {
        return $this->repositorio->listarTecnicos();
    }

    public function asignar(int $idTicket, array $datos, int $idAdministrador): array
    {
        $idTecnico = $datos['idTecnico'] ?? null;
        $descripcionOrden = $datos['descripcionOrden'] ?? null;
        $fechaProgramada = $datos['fechaProgramada'] ?? null;

        if (!is_int($idTecnico) || $idTecnico < 1) {
            throw new InvalidArgumentException('Selecciona un técnico válido.');
        }
        if (!is_string($descripcionOrden) || strlen(trim($descripcionOrden)) < 8
            || strlen($descripcionOrden) > 1500) {
            throw new InvalidArgumentException('Describe el trabajo entre 8 y 1500 caracteres.');
        }
        if (!is_string($fechaProgramada)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $fechaProgramada) !== 1) {
            throw new InvalidArgumentException('Especifica una fecha y hora de visita válida.');
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $fechaProgramada);
        if ($fecha === false || $fecha->format('Y-m-d\TH:i') !== $fechaProgramada) {
            throw new InvalidArgumentException('La fecha programada es inválida.');
        }

        $idAsignacion = $this->repositorio->asignar(
            $idTicket,
            $idTecnico,
            trim($descripcionOrden),
            $fecha->format('Y-m-d H:i:s'),
            $idAdministrador
        );

        return [
            'idAsignacion' => $idAsignacion,
            'idTicket' => $idTicket,
            'estadoTicket' => 'asignado',
            'estadoNotificacion' => 'pendiente',
        ];
    }
}
