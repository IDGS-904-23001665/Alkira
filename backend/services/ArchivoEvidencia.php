<?php
declare(strict_types=1);

final class ArchivoEvidencia
{
    private const FORMATOS = [
        'image/jpeg' => ['jpg', 'JPG'], 'image/png' => ['png', 'PNG'],
        'video/mp4' => ['mp4', 'MP4'], 'application/pdf' => ['pdf', 'PDF'],
    ];

    public static function directorio(): string
    {
        return dirname(__DIR__) . '/documentos/incidencias';
    }

    public static function guardar(array $archivo): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo recibir el archivo. Revisa el límite de carga del servidor.');
        }
        $temporal = $archivo['tmp_name'] ?? '';
        if (!is_string($temporal) || !is_uploaded_file($temporal)) {
            throw new InvalidArgumentException('Archivo de evidencia inválido.');
        }
        $tamano = filesize($temporal);
        if ($tamano === false || $tamano < 1 || $tamano > 20 * 1024 * 1024) {
            throw new InvalidArgumentException('La evidencia debe pesar entre 1 byte y 20 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);
        if (!isset(self::FORMATOS[$mime])) {
            throw new InvalidArgumentException('Solo se permiten archivos JPG, PNG, MP4 y PDF válidos.');
        }
        [$extension, $tipo] = self::FORMATOS[$mime];
        $directorio = self::directorio();
        if (!is_dir($directorio) && !mkdir($directorio, 0700, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento de evidencias.');
        }
        $clave = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($temporal, $directorio . '/' . $clave)) {
            throw new RuntimeException('No se pudo guardar la evidencia.');
        }
        return ['rutaArchivo' => 'privado:' . $clave, 'tipoArchivo' => $tipo];
    }

    public static function ruta(string $referencia): ?string
    {
        if (!preg_match('/^privado:([a-f0-9]{32}\.(?:jpg|png|mp4|pdf))$/D', $referencia, $partes)) {
            return null;
        }
        return self::directorio() . '/' . $partes[1];
    }

    public static function eliminar(string $referencia): void
    {
        $ruta = self::ruta($referencia);
        if ($ruta !== null && is_file($ruta)) {
            unlink($ruta);
        }
    }
}
