<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/repos/ContratosAdministrativosRepositorio.php';

final class ContratosAdministrativosServicio
{
    private const MAX_BYTES_PDF = 12582912;

    public function __construct(
        private ContratosAdministrativosRepositorio $repositorio,
        private PDO $conexion
    ) {
    }

    public function listarContratos(): array
    {
        return $this->repositorio->listarContratos();
    }

    public function listarUnidadesActivas(): array
    {
        return $this->repositorio->listarUnidadesActivas();
    }

    public function listarInquilinosActivos(): array
    {
        return $this->repositorio->listarInquilinosActivos();
    }

    public function listarCobrosPorUnidad(int $idUnidad): array
    {
        if ($idUnidad < 1) {
            throw new InvalidArgumentException(
                'Selecciona una unidad válida.'
            );
        }

        return $this->repositorio->listarCobrosPorUnidad($idUnidad);
    }

    public function crearContrato(array $datos): array
    {
        $idUnidad = $datos['idUnidad'] ?? null;
        $idInquilino = $datos['idInquilino'] ?? null;
        $estadoContrato = $datos['estadoContrato'] ?? 'borrador';

        if (!is_int($idUnidad) || $idUnidad < 1) {
            throw new InvalidArgumentException(
                'Selecciona una unidad válida.'
            );
        }

        if (!is_int($idInquilino) || $idInquilino < 1) {
            throw new InvalidArgumentException(
                'Selecciona un inquilino válido.'
            );
        }

        if (
            !is_string($estadoContrato)
            || !in_array(
                $estadoContrato,
                ['borrador', 'vigente'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El estado del contrato no es válido.'
            );
        }

        $fechaInicio = $this->validarFecha(
            $datos['fechaInicio'] ?? null,
            'fecha de inicio'
        );

        $fechaFin = $datos['fechaFin'] ?? null;

        if ($fechaFin === '') {
            $fechaFin = null;
        }

        if ($fechaFin !== null) {
            $fechaFin = $this->validarFecha(
                $fechaFin,
                'fecha de finalización'
            );

            if ($fechaFin < $fechaInicio) {
                throw new InvalidArgumentException(
                    'La fecha de finalización no puede ser anterior al inicio.'
                );
            }
        }

        $montoRenta = $this->validarMonto(
            $datos['montoRenta'] ?? null,
            'monto de renta'
        );

        $depositoGarantia = $this->validarMonto(
            $datos['depositoGarantia'] ?? '0.00',
            'depósito de garantía'
        );

        if (
            $estadoContrato === 'vigente'
            && $montoRenta === '0.00'
        ) {
            throw new InvalidArgumentException(
                'Un contrato vigente debe tener un monto de renta mayor que cero.'
            );
        }

        $this->conexion->beginTransaction();

        try {
            $unidad = $this->repositorio->bloquearUnidad(
                $idUnidad
            );

            if ($unidad === false) {
                throw new OutOfBoundsException(
                    'La unidad seleccionada no existe.'
                );
            }

            if (
                (int) $unidad['activo'] !== 1
                || $unidad['estadoUnidad'] === 'inactiva'
            ) {
                throw new DomainException(
                    'La unidad seleccionada no está activa.'
                );
            }

            if (
                !$this->repositorio->existeInquilinoActivo(
                    $idInquilino
                )
            ) {
                throw new DomainException(
                    'El inquilino seleccionado no está activo.'
                );
            }

            if ($estadoContrato === 'vigente') {
                if ($unidad['estadoUnidad'] === 'mantenimiento') {
                    throw new DomainException(
                        'No se puede activar un contrato para una unidad en mantenimiento.'
                    );
                }

                if (
                    $this->repositorio->existeContratoVigente(
                        $idUnidad
                    )
                ) {
                    throw new DomainException(
                        'La unidad ya tiene un contrato vigente.'
                    );
                }
            }

            $idContrato = $this->repositorio->crearContrato([
                'idUnidad' => $idUnidad,
                'idInquilino' => $idInquilino,
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'montoRenta' => $montoRenta,
                'depositoGarantia' => $depositoGarantia,
                'estadoContrato' => $estadoContrato,
            ]);

            $this->conexion->commit();

            return [
                'idContrato' => $idContrato,
                'estadoContrato' => $estadoContrato,
            ];

        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            throw $error;
        }
    }

    public function subirDocumento(
        int $idContrato,
        array $archivo
    ): array {
        if ($idContrato < 1) {
            throw new InvalidArgumentException(
                'El contrato seleccionado no es válido.'
            );
        }

        $contrato = $this->repositorio->obtenerContrato(
            $idContrato
        );

        if ($contrato === false) {
            throw new OutOfBoundsException(
                'El contrato no existe.'
            );
        }

        if ($contrato['rutaDocumento'] !== null) {
            throw new DomainException(
                'Este contrato ya tiene un documento registrado.'
            );
        }

        $errorArchivo = $archivo['error'] ?? null;
        $rutaTemporal = $archivo['tmp_name'] ?? null;
        $tamanoArchivo = $archivo['size'] ?? null;

        if ($errorArchivo !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                'No se recibió correctamente el archivo PDF.'
            );
        }

        if (
            !is_string($rutaTemporal)
            || !is_int($tamanoArchivo)
            || $tamanoArchivo < 5
            || $tamanoArchivo > self::MAX_BYTES_PDF
            || !is_uploaded_file($rutaTemporal)
        ) {
            throw new InvalidArgumentException(
                'Selecciona un PDF válido de máximo 12 MB.'
            );
        }

        $detector = new finfo(FILEINFO_MIME_TYPE);
        $tipoArchivo = $detector->file($rutaTemporal);

        if ($tipoArchivo !== 'application/pdf') {
            throw new InvalidArgumentException(
                'Solo se permiten documentos PDF.'
            );
        }

        $contenidoInicial = file_get_contents(
            $rutaTemporal,
            false,
            null,
            0,
            5
        );

        if ($contenidoInicial !== '%PDF-') {
            throw new InvalidArgumentException(
                'El documento no contiene una estructura PDF válida.'
            );
        }

        $directorio = dirname(__DIR__)
            . '/documentos/contratos';

        if (
            !is_dir($directorio)
            && !mkdir($directorio, 0700, true)
            && !is_dir($directorio)
        ) {
            throw new RuntimeException(
                'No fue posible preparar el almacenamiento de contratos.'
            );
        }

        if (!is_writable($directorio)) {
            throw new RuntimeException(
                'El directorio de contratos no tiene permisos de escritura.'
            );
        }

        $nombreArchivo = 'contrato-'
            . $idContrato . '-'
            . bin2hex(random_bytes(16))
            . '.pdf';

        $rutaAbsoluta = $directorio
            . DIRECTORY_SEPARATOR
            . $nombreArchivo;

        $rutaDocumento = 'documentos/contratos/'
            . $nombreArchivo;

        if (!move_uploaded_file(
            $rutaTemporal,
            $rutaAbsoluta
        )) {
            throw new RuntimeException(
                'No fue posible guardar el documento.'
            );
        }

        try {
            if (!chmod($rutaAbsoluta, 0600)) {
                throw new RuntimeException(
                    'No fue posible proteger el documento.'
                );
            }

            $guardado = $this->repositorio
                ->guardarRutaDocumento(
                    $idContrato,
                    $rutaDocumento
                );

            if (!$guardado) {
                throw new DomainException(
                    'El contrato ya tiene un documento registrado.'
                );
            }

            return [
                'idContrato' => $idContrato,
                'documentoRegistrado' => true,
            ];

        } catch (Throwable $error) {
            if (is_file($rutaAbsoluta)) {
                unlink($rutaAbsoluta);
            }

            throw $error;
        }
    }


    public function obtenerArchivoContrato(int $idContrato): string
    {
        if ($idContrato < 1) {
            throw new InvalidArgumentException(
                'El identificador del contrato no es válido.'
            );
        }

        $contrato = $this->repositorio->obtenerContrato(
            $idContrato
        );

        if ($contrato === false) {
            throw new OutOfBoundsException(
                'El contrato solicitado no existe.'
            );
        }

        $rutaDocumento = $contrato['rutaDocumento'] ?? null;

        if (
            !is_string($rutaDocumento)
            || $rutaDocumento === ''
        ) {
            throw new OutOfBoundsException(
                'El contrato todavía no tiene un PDF registrado.'
            );
        }

        // Solo aceptar documentos dentro del directorio
        // privado de contratos.
        if (
            preg_match(
                '#^documentos/contratos/([A-Za-z0-9][A-Za-z0-9._-]*\.pdf)$#iD',
                $rutaDocumento,
                $coincidencias
            ) !== 1
            || str_contains($coincidencias[1], '..')
        ) {
            throw new RuntimeException(
                'La ruta del documento no es válida.'
            );
        }

        $directorio = realpath(
            dirname(__DIR__) . '/documentos/contratos'
        );

        if ($directorio === false || !is_dir($directorio)) {
            throw new RuntimeException(
                'No se encontró el directorio privado de contratos.'
            );
        }

        $rutaArchivo = realpath(
            $directorio
            . DIRECTORY_SEPARATOR
            . $coincidencias[1]
        );

        // Evitar accesos fuera de la carpeta autorizada.
        if (
            $rutaArchivo === false
            || dirname($rutaArchivo) !== $directorio
            || !is_file($rutaArchivo)
            || !is_readable($rutaArchivo)
        ) {
            throw new OutOfBoundsException(
                'El documento PDF no está disponible.'
            );
        }

        $tamanoArchivo = filesize($rutaArchivo);

        if (
            $tamanoArchivo === false
            || $tamanoArchivo < 5
            || $tamanoArchivo > self::MAX_BYTES_PDF
        ) {
            throw new RuntimeException(
                'El documento no cumple con el tamaño permitido.'
            );
        }

        $detector = new finfo(FILEINFO_MIME_TYPE);

        if ($detector->file($rutaArchivo) !== 'application/pdf') {
            throw new RuntimeException(
                'El documento almacenado no es un PDF válido.'
            );
        }

        $lector = fopen($rutaArchivo, 'rb');

        if ($lector === false) {
            throw new RuntimeException(
                'No fue posible leer el documento.'
            );
        }

        try {
            if (fread($lector, 5) !== '%PDF-') {
                throw new RuntimeException(
                    'El documento almacenado no es un PDF válido.'
                );
            }
        } finally {
            fclose($lector);
        }

        return $rutaArchivo;
    }


    private function validarFecha(
        mixed $valor,
        string $nombreCampo
    ): string {
        if (
            !is_string($valor)
            || preg_match(
                '/^\d{4}-\d{2}-\d{2}$/D',
                $valor
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'La ' . $nombreCampo . ' no es válida.'
            );
        }

        $fecha = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $valor
        );

        if (
            $fecha === false
            || $fecha->format('Y-m-d') !== $valor
        ) {
            throw new InvalidArgumentException(
                'La ' . $nombreCampo . ' no existe.'
            );
        }

        return $valor;
    }

    private function validarMonto(
        mixed $valor,
        string $nombreCampo
    ): string {
        if (is_int($valor)) {
            $valor = (string) $valor;
        }

        if (!is_string($valor)) {
            throw new InvalidArgumentException(
                'El ' . $nombreCampo . ' debe enviarse como texto decimal.'
            );
        }

        $valor = trim($valor);

        if (
            preg_match(
                '/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/D',
                $valor
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'El ' . $nombreCampo . ' no es válido.'
            );
        }

        $partes = explode('.', $valor, 2);
        $decimales = str_pad(
            $partes[1] ?? '',
            2,
            '0'
        );

        return $partes[0] . '.' . $decimales;
    }
}
