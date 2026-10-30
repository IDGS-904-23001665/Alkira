<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/HistorialContratosServicio.php';
require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class HistorialContratosControlador
{
    public function __construct(
        private AutenticacionServicio $autenticacion,
        private HistorialContratosServicio $consultas
    ) {
    }

    public function procesar(string $ruta): void
    {
        $token = $this->obtenerTokenBearer();
        $usuario = $this->autenticacion->verificarTokenMovil($token);
        if ($usuario === null) {
            $this->responder(401, ['mensaje' => 'Sesión no válida o vencida.']);
            return;
        }

        if ($usuario['tipoUsuario'] !== 'inquilino') {
            $this->responder(403, ['mensaje' => 'Esta consulta es exclusiva para inquilinos.']);
            return;
        }

        $idInquilino = (int) $usuario['idUsuario'];
        if ($ruta === '/api/pagos/historial') {
            $this->responder(200, $this->consultas->consultarHistorial($idInquilino));
            return;
        }

        if ($ruta === '/api/contratos') {
            $this->responder(200, $this->consultas->consultarContratos($idInquilino));
            return;
        }

        if (preg_match('#^/api/contratos/([1-9][0-9]*)/documento$#D', $ruta, $coincidencias) === 1) {
            $archivo = $this->consultas->obtenerArchivoContrato((int) $coincidencias[1], $idInquilino);
            if ($archivo === null) {
                $this->responder(404, ['mensaje' => 'El documento no está disponible.']);
                return;
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="contrato-alkira.pdf"');
            header('Content-Length: ' . filesize($archivo));
            header('X-Content-Type-Options: nosniff');
            readfile($archivo);
            return;
        }

        $this->responder(404, ['mensaje' => 'Ruta no encontrada.']);
    }

    private function obtenerTokenBearer(): ?string
    {
        $autorizacion = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!is_string($autorizacion) || strlen($autorizacion) > 4200) {
            return null;
        }
        if (preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/i', trim($autorizacion), $coincidencias) !== 1) {
            return null;
        }
        return $coincidencias[1];
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
