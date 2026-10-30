<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/ComprobantesMovilRepositorio.php';

/** HU03.1: recepción autenticada de comprobantes de inquilinos. */
final class ComprobantesMovilControlador
{
    private const LIMITE = 5242880; // 5 MiB

    public function __construct(private ComprobantesMovilRepositorio $repositorio) {}

    public function obtenerAdeudos(array $usuario): void
    {
        if (!$this->autorizado($usuario)) return;
        $this->json(200, ['adeudos' => $this->repositorio->adeudosDeInquilino((int) $usuario['idUsuario'])]);
    }

    public function enviar(array $usuario): void
    {
        if (!$this->autorizado($usuario)) return;
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data;')) {
            $this->json(415, ['mensaje' => 'Se requiere enviar un formulario con archivo.']);
            return;
        }
        // Cuando PHP excede post_max_size, puede dejar vacíos POST y FILES.
        if (empty($_POST) && empty($_FILES)) {
            $this->json(413, ['mensaje' => 'La solicitud es demasiado grande o está vacía.']);
            return;
        }
        $idTexto = $_POST['idEstadoCuenta'] ?? null;
        $monto = $_POST['monto'] ?? null;
        $referencia = $_POST['referencia'] ?? null;
        $concepto = $_POST['concepto'] ?? null;
        if (!is_string($idTexto) || !preg_match('/^[1-9][0-9]{0,9}$/D', $idTexto) ||
            !is_string($monto) || !preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $monto) || (float) $monto <= 0 ||
            !is_string($referencia) || trim($referencia) === '' || strlen(trim($referencia)) > 100 ||
            !is_string($concepto) || trim($concepto) === '' || strlen(trim($concepto)) > 300) {
            $this->json(422, ['mensaje' => 'Revisa el adeudo, monto, concepto y referencia bancaria.']);
            return;
        }
        $idEstado = (int) $idTexto;
        if ($idEstado <= 0) {
            $this->json(422, ['mensaje' => 'Adeudo inválido.']);
            return;
        }
        $archivo = $_FILES['comprobante'] ?? null;
        if (!is_array($archivo) || is_array($archivo['error'] ?? null) ||
            (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
            !isset($archivo['tmp_name'], $archivo['size']) ||
            !is_string($archivo['tmp_name']) ||
            !is_uploaded_file($archivo['tmp_name']) ||
            !is_numeric($archivo['size']) ||
            (int) $archivo['size'] < 1 || (int) $archivo['size'] > self::LIMITE) {
            $this->json(422, ['mensaje' => 'Adjunta un PDF, JPG o PNG válido de máximo 5 MB.']);
            return;
        }

        // El MIME se obtiene del contenido real, nunca de los metadatos del celular.
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $permitidos = [
            'application/pdf' => ['pdf', 'PDF'],
            'image/jpeg' => ['jpg', 'JPG'],
            'image/png' => ['png', 'PNG'],
        ];
        if (!is_string($mime) || !isset($permitidos[$mime])) {
            $this->json(422, ['mensaje' => 'Formato no permitido. Usa PDF, JPG o PNG.']);
            return;
        }
        [$extension, $tipo] = $permitidos[$mime];
        $configArchivo = dirname(__DIR__) . '/config/comprobantes.local.php';
        if (!is_file($configArchivo)) {
            throw new RuntimeException('Falta configuración local de almacenamiento de comprobantes.');
        }
        $config = require $configArchivo;
        $directorio = is_array($config) ? ($config['directorioPrivado'] ?? null) : null;
        if (!is_string($directorio) || !is_dir($directorio) || !is_writable($directorio)) {
            throw new RuntimeException('El directorio privado de comprobantes no está disponible.');
        }
        $nombre = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = rtrim($directorio, '/\\') . DIRECTORY_SEPARATOR . $nombre;
        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new RuntimeException('No se pudo almacenar el archivo.');
        }
        try {
            $resultado = $this->repositorio->registrar(
                (int) $usuario['idUsuario'], $idEstado, $monto,
                trim($referencia), trim($concepto), $nombre, $tipo
            );
        } catch (Throwable $e) {
            @unlink($destino);
            if ($e instanceof DomainException) {
                $this->json(409, ['mensaje' => $e->getMessage()]);
                return;
            }
            throw $e;
        }
        $this->json(201, [
            'mensaje' => 'Comprobante recibido. Pendiente de revisión administrativa.',
            'idPago' => $resultado['idPago'],
            'idComprobante' => $resultado['idComprobante'],
            'estado' => 'preregistrado',
        ]);
    }

    private function autorizado(array $usuario): bool
    {
        if (($usuario['tipoUsuario'] ?? '') !== 'inquilino') {
            $this->json(403, ['mensaje' => 'Solo un inquilino puede registrar pagos.']);
            return false;
        }
        return true;
    }

    private function json(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
