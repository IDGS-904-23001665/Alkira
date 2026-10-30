<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/HistorialPagosRepositorio.php';
require_once dirname(__DIR__) . '/repos/ContratosRepositorio.php';

final class HistorialContratosServicio
{
    private const MAX_BYTES_PDF = 12582912;

    public function __construct(
        private HistorialPagosRepositorio $pagos,
        private ContratosRepositorio $contratos
    ) {
    }

    /** @return array<string, mixed> */
    public function consultarHistorial(int $idInquilino): array
    {
        return ['pagos' => $this->pagos->obtenerPorInquilino($idInquilino)];
    }

    /** @return array<string, mixed> */
    public function consultarContratos(int $idInquilino): array
    {
        $contratos = $this->contratos->obtenerPorInquilino($idInquilino);
        $respuesta = [];

        foreach ($contratos as $contrato) {
            $respuesta[] = [
                'idContrato' => (int) $contrato['idContrato'],
                'numeroUnidad' => (string) $contrato['numeroUnidad'],
                'fechaInicio' => (string) $contrato['fechaInicio'],
                'fechaFin' => $contrato['fechaFin'],
                'montoRenta' => (string) $contrato['montoRenta'],
                'depositoGarantia' => (string) $contrato['depositoGarantia'],
                'estadoContrato' => (string) $contrato['estadoContrato'],
                'documentoDisponible' => $this->ubicarDocumento($contrato['rutaDocumento']) !== null,
            ];
        }

        return ['contratos' => $respuesta];
    }

    public function obtenerArchivoContrato(int $idContrato, int $idInquilino): ?string
    {
        if ($idContrato <= 0 || $idInquilino <= 0) {
            return null;
        }

        $contrato = $this->contratos->obtenerDocumento($idContrato, $idInquilino);
        if ($contrato === false) {
            return null;
        }

        return $this->ubicarDocumento($contrato['ruta_documento']);
    }

    private function ubicarDocumento(mixed $ruta): ?string
    {
        if (!is_string($ruta) ||
            preg_match('#^documentos/contratos/([A-Za-z0-9][A-Za-z0-9._-]*\.pdf)$#iD', $ruta, $coincidencias) !== 1 ||
            str_contains($coincidencias[1], '..')) {
            return null;
        }

        // Los contratos se guardan fuera de backend/public, sin acceso HTTP directo.
        $directorio = realpath(dirname(__DIR__) . '/documentos/contratos');
        if ($directorio === false || !is_dir($directorio)) {
            return null;
        }

        $archivo = realpath($directorio . DIRECTORY_SEPARATOR . $coincidencias[1]);
        if ($archivo === false || dirname($archivo) !== $directorio || !is_file($archivo)) {
            return null;
        }

        $tamano = filesize($archivo);
        if ($tamano === false || $tamano < 5 || $tamano > self::MAX_BYTES_PDF) {
            return null;
        }

        $lector = fopen($archivo, 'rb');
        if ($lector === false) {
            return null;
        }
        try {
            if (fread($lector, 5) !== '%PDF-') {
                return null;
            }
        } finally {
            fclose($lector);
        }
        return $archivo;
    }
}
