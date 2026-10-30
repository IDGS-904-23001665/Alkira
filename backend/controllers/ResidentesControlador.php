<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/ResidentesServicio.php';

final class ResidentesControlador
{
    public function __construct(private ResidentesServicio $servicio) {}

    public function procesar(string $metodo): void
    {
        if ($metodo !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            echo json_encode(['mensaje' => 'Método no permitido.']);
            return;
        }
        echo json_encode(['residentes' => $this->servicio->listarActivos()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
