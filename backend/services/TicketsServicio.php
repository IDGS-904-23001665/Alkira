<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/TicketsRepositorio.php';

final class TicketsServicio
{
    public function __construct(private TicketsRepositorio $repositorio)
    {
    }

    public function listar(int $idUsuario, string $tipoUsuario): array
    {
        $tickets = $this->repositorio->listarPorUsuario(
            $idUsuario,
            $tipoUsuario === 'tecnico'
        );

        foreach ($tickets as &$ticket) {
            $ticket['tieneFirma'] = (bool) $ticket['tieneFirma'];

            $ticket['puedeIniciar'] = $tipoUsuario === 'tecnico'
                && $ticket['estado'] === 'asignado'
                && in_array(
                    $ticket['estadoOrden'],
                    ['asignada', 'aceptada'],
                    true
                );

            $ticket['puedeFirmar'] = in_array(
                $tipoUsuario,
                ['inquilino', 'propietario'],
                true
            )
                && $ticket['estado'] === 'en_proceso'
                && !$ticket['tieneFirma'];
        }

        unset($ticket);

        return $tickets;
    }

    public function consultarHistorial(
        int $idTicket,
        int $idUsuario,
        string $tipoUsuario
    ): array {
        $ticket = $this->repositorio->buscarPorUsuario(
            $idTicket,
            $idUsuario,
            $tipoUsuario === 'tecnico'
        );

        if ($ticket === false) {
            throw new OutOfBoundsException('Ticket no encontrado.');
        }

        return $this->repositorio->historial($idTicket);
    }

    public function iniciarTrabajo(
        int $idTicket,
        int $idUsuario,
        string $tipoUsuario
    ): void {
        if ($tipoUsuario !== 'tecnico') {
            throw new DomainException(
                'Solo el técnico asignado puede iniciar el trabajo.'
            );
        }

        if ($idTicket < 1 || $idUsuario < 1) {
            throw new InvalidArgumentException(
                'La solicitud no identifica un ticket o técnico válido.'
            );
        }

        $this->repositorio->iniciarTrabajo(
            $idTicket,
            $idUsuario
        );
    }

    public function firmarConformidad(
        int $idTicket,
        int $idUsuario,
        string $tipoUsuario,
        string $firmaBase64,
        ?string $comentario
    ): void {
        if (!in_array(
            $tipoUsuario,
            ['inquilino', 'propietario'],
            true
        )) {
            throw new DomainException(
                'Solo el residente puede firmar la conformidad.'
            );
        }

        if (
            strlen($firmaBase64) < 100
            || strlen($firmaBase64) > 750000
            || preg_match(
                '~^[A-Za-z0-9+/]+={0,2}$~D',
                $firmaBase64
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'La firma enviada no es válida.'
            );
        }

        $imagenPng = base64_decode($firmaBase64, true);

        if (
            $imagenPng === false
            || strlen($imagenPng) > 550000
        ) {
            throw new InvalidArgumentException(
                'La firma supera el tamaño permitido.'
            );
        }

        $informacion = @getimagesizefromstring($imagenPng);

        if (
            !is_array($informacion)
            || $informacion[2] !== IMAGETYPE_PNG
            || $informacion[0] < 200
            || $informacion[0] > 2000
            || $informacion[1] < 100
            || $informacion[1] > 1000
        ) {
            throw new InvalidArgumentException(
                'La firma debe ser una imagen PNG válida.'
            );
        }

        $comentario = $comentario === null
            ? null
            : trim($comentario);

        if (
            $comentario !== null
            && strlen($comentario) > 500
        ) {
            throw new InvalidArgumentException(
                'El comentario supera los 500 caracteres.'
            );
        }

        $this->repositorio->registrarConformidad(
            $idTicket,
            $idUsuario,
            $imagenPng,
            $comentario === '' ? null : $comentario
        );
    }
}
