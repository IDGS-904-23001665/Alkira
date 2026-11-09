<?php
declare(strict_types=1);

final class ConciliacionControlador
{
    public function __construct(private ConciliacionRepositorio $repositorio) {}

    public function procesar(string $metodo, string $ruta, array $usuario): void
    {
        if (($usuario['tipoUsuario'] ?? '') !== 'administrador') {
            http_response_code(403);
            echo json_encode(['mensaje' => 'Solo administración puede conciliar pagos.']);
            return;
        }
        if ($metodo === 'GET' && $ruta === '/api/conciliacion') {
            echo json_encode(['comprobantes' => $this->repositorio->listar()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return;
        }
        if ($metodo === 'GET' && $ruta === '/api/conciliacion/recibo') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id || !($datos = $this->repositorio->recibo($id))) {
                http_response_code(404);
                echo json_encode(['mensaje' => 'Recibo no encontrado.']);
                return;
            }
            $this->emitirPdf($datos);
            return;
        }
        if ($metodo !== 'POST' || !preg_match('#^/api/conciliacion/(aprobar|rechazar)$#D', $ruta, $coincidencias)) {
            http_response_code(405);
            echo json_encode(['mensaje' => 'Método no permitido.']);
            return;
        }
        // Defensa CSRF para sesiones por cookie: origen de mismo sitio.
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referencia = $_SERVER['HTTP_REFERER'] ?? '';
        $hostActual = $_SERVER['HTTP_HOST'] ?? '';
        $hostOrigen = parse_url($origen, PHP_URL_HOST);
        $hostReferencia = parse_url($referencia, PHP_URL_HOST);
        $hostEsperado = explode(':', $hostActual)[0];
        if ((!$origen && !$referencia) || ($origen && $hostOrigen !== $hostEsperado && !in_array($origen, ['http://localhost:5173', 'http://127.0.0.1:5173'], true)) || (!$origen && $hostReferencia !== $hostEsperado && !in_array($hostReferencia, ['localhost', '127.0.0.1'], true))) {
            http_response_code(403);
            echo json_encode(['mensaje' => 'Origen no permitido.']);
            return;
        }
        if (!preg_match('~^application/json(?:\s*;|\s*$)~i', $_SERVER['CONTENT_TYPE'] ?? '')) {
            http_response_code(415);
            echo json_encode(['mensaje' => 'Se requiere JSON.']);
            return;
        }
        $cuerpo = file_get_contents('php://input');
        if ($cuerpo === false || strlen($cuerpo) > 4096) {
            http_response_code(422);
            echo json_encode(['mensaje' => 'Solicitud inválida.']);
            return;
        }
        $datos = json_decode($cuerpo, true);
        if (!is_array($datos) || !is_int($datos['idComprobante'] ?? null) || $datos['idComprobante'] <= 0 || !is_string($datos['motivo'] ?? '')) {
            http_response_code(422);
            echo json_encode(['mensaje' => 'Se requieren idComprobante y motivo.']);
            return;
        }
        try {
            echo json_encode($this->repositorio->resolver($datos['idComprobante'], (int) $usuario['idUsuario'], $coincidencias[1], $datos['motivo']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            echo json_encode(['mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (OutOfBoundsException $error) {
            http_response_code(404);
            echo json_encode(['mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (DomainException $error) {
            http_response_code(409);
            echo json_encode(['mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Recibo PDF administrativo con diseño ALKIRA.
     * Sin dependencias externas; conserva el mismo endpoint y los mismos datos.
     * No es un CFDI ni contiene una firma electrónica certificada.
     */
    private function emitirPdf(array $datos): void
    {
        $folio = (string) ($datos['folio'] ?? '');
        $importe = number_format((float) ($datos['monto'] ?? 0), 2, '.', ',');
        $residente = trim((string) ($datos['nombres'] ?? '') . ' ' . (string) ($datos['apellidos'] ?? ''));
        $inmueble = (string) ($datos['inmueble'] ?? '');
        $unidad = (string) ($datos['numeroUnidad'] ?? '');
        $periodo = (string) ($datos['periodo'] ?? '');
        $metodo = (string) ($datos['metodoPago'] ?? '');
        $referencia = trim((string) ($datos['referenciaBancaria'] ?? ''));
        $fecha = (string) ($datos['fechaRevision'] ?? '');
        $idPago = (string) ($datos['idPago'] ?? '');

        // PDF tamaño A4 aproximado: 612 x 842 puntos. Origen abajo a la izquierda.
        $instrucciones = [];
        $normalizar = static function (string $valor): string {
            $valor = preg_replace('/[\r\n\t]+/u', ' ', $valor) ?? '';
            $valor = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $valor);
            return (string) $valor;
        };
        $escapar = static function (string $valor): string {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $valor);
        };
        $texto = static function (
            float $x, float $y, string $valor, float $tamano = 10,
            string $fuente = 'F1', string $color = '0.18 0.24 0.32'
        ) use (&$instrucciones, $normalizar, $escapar): void {
            $valor = $escapar($normalizar($valor));
            $instrucciones[] = "$color rg BT /$fuente $tamano Tf 1 0 0 1 $x $y Tm ($valor) Tj ET";
        };
        $caja = static function (
            float $x, float $y, float $ancho, float $alto, string $color
        ) use (&$instrucciones): void {
            $instrucciones[] = "$color rg $x $y $ancho $alto re f";
        };
        $linea = static function (
            float $x1, float $y1, float $x2, float $y2,
            string $color = '0.86 0.89 0.92', float $grosor = 0.7
        ) use (&$instrucciones): void {
            $instrucciones[] = "$color RG $grosor w $x1 $y1 m $x2 $y2 l S";
        };
        // Evitar desbordamientos por nombres y referencias inusualmente largos.
        $recortar = static function (string $valor, int $limite = 49): string {
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                return mb_strlen($valor, 'UTF-8') > $limite
                    ? mb_substr($valor, 0, $limite - 3, 'UTF-8') . '...'
                    : $valor;
            }
            return strlen($valor) > $limite ? substr($valor, 0, $limite - 3) . '...' : $valor;
        };
        $dato = static function (float $x, float $y, string $etiqueta, string $valor)
            use ($texto, $recortar): void {
            $texto($x, $y, strtoupper($etiqueta), 8, 'F2', '0.42 0.50 0.60');
            $texto($x, $y - 21, $recortar($valor), 11, 'F2', '0.10 0.17 0.27');
        };

        // Fondo y cabecera de marca.
        $caja(0, 0, 612, 842, '1 1 1');
        $caja(0, 690, 612, 152, '0.07 0.16 0.28');
        $caja(0, 686, 612, 4, '0.14 0.76 0.70');
        $texto(42, 780, 'ALKIRA', 29, 'F2', '1 1 1');
        $texto(44, 754, 'ADMINISTRACION INMOBILIARIA', 9, 'F1', '0.70 0.82 0.88');
        $texto(42, 714, 'RECIBO DE PAGO', 18, 'F2', '1 1 1');
        $texto(405, 778, 'PAGO VALIDADO', 10, 'F2', '0.51 0.95 0.87');
        $texto(405, 758, 'APROBADO Y CONCILIADO', 8, 'F1', '1 1 1');

        // Identificación del recibo.
        $dato(42, 655, 'Folio del recibo', $folio);
        $dato(330, 655, 'Fecha de conciliacion', $fecha);
        $linea(42, 603, 570, 603);

        // Importe con jerarquía visual.
        $caja(42, 475, 528, 106, '0.93 0.98 0.97');
        $caja(42, 475, 5, 106, '0.10 0.68 0.62');
        $texto(64, 552, 'IMPORTE RECIBIDO', 10, 'F2', '0.18 0.43 0.45');
        $texto(63, 504, 'MXN $' . $importe, 29, 'F2', '0.06 0.27 0.32');

        // Información comprobable del movimiento.
        $texto(42, 438, 'DETALLES DEL PAGO', 11, 'F2', '0.08 0.18 0.30');
        $linea(42, 427, 570, 427);
        $dato(42, 397, 'Residente', $residente);
        $dato(330, 397, 'Inmueble', $inmueble);
        $dato(42, 330, 'Unidad', $unidad);
        $dato(330, 330, 'Periodo', $periodo);
        $dato(42, 263, 'Metodo de pago', $metodo);
        $dato(330, 263, 'Referencia bancaria', $referencia !== '' ? $referencia : 'No registrada');
        $linea(42, 205, 570, 205);
        $dato(42, 180, 'Identificador interno del pago', '#' . $idPago);

        // Nota informativa, sin afirmar certificaciones inexistentes.
        $caja(42, 65, 528, 64, '0.96 0.97 0.98');
        $texto(56, 109, 'COMPROBANTE ADMINISTRATIVO', 9, 'F2', '0.16 0.25 0.36');
        $texto(56, 91, 'Emitido a partir de un pago aprobado en ALKIRA.', 9);
        $texto(56, 76, 'No es un CFDI ni acredita el pago de otros cargos pendientes.', 8.5);
        $texto(42, 36, 'ALKIRA  |  Control de pagos y conciliacion', 8, 'F1', '0.48 0.54 0.62');

        $contenido = implode("\n", $instrucciones) . "\n";
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . strlen($contenido) . ">>\nstream\n" . $contenido . 'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $posiciones = [0];
        foreach ($objetos as $indice => $objeto) {
            $posiciones[] = strlen($pdf);
            $pdf .= ($indice + 1) . " 0 obj\n" . $objeto . "\nendobj\n";
        }
        $inicioTabla = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        foreach (array_slice($posiciones, 1) as $posicion) {
            $pdf .= sprintf('%010d 00000 n ', $posicion) . "\n";
        }
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $inicioTabla . "\n%%EOF\n";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="recibo-' . preg_replace('/[^A-Za-z0-9-]/', '', $folio) . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }
}
