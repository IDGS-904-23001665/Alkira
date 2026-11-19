<?php

declare(strict_types=1);

/**
 * Motor conversacional de ALKIRA.
 * Utiliza FAQs y datos reales del usuario autenticado.
 */
final class ChatbotMotorServicio
{
    public function __construct(private PDO $conexion) {}

    public function responder(
        string $mensaje,
        ?int $idUsuario = null
    ): array {
        $mensaje = trim($mensaje);

        if ($mensaje === '' || strlen($mensaje) > 500) {
            throw new InvalidArgumentException(
                'Escribe una pregunta de 1 a 500 caracteres.'
            );
        }

        $pregunta = self::normalizar($mensaje);

        // Responder saludos sin confundirlos con otras consultas.
if (
    preg_match(
        '/^(hola|buenas|buenos dias|buenas tardes|buenas noches)(?: como estas| que tal)?$/',
        $pregunta
    ) === 1
) {
    return $this->resultado(
        '¡Hola! Soy el asistente virtual de ALKIRA. '
        . 'Puedo ayudarte con dudas sobre pagos, '
        . 'adeudos, incidencias, comunicados y '
        . 'otros servicios del condominio. '
        . '¿En qué puedo ayudarte?',
        'saludo'
    );
}

        // Consultas personales: requieren identidad validada.
        if ($this->esConsultaDeSaldo($pregunta)) {
            if ($idUsuario === null) {
                return $this->resultado(
                    'La vista previa no consulta saldos personales. '
                    . 'En Android, el residente autenticado podrá '
                    . 'preguntar cuánto debe y recibirá únicamente '
                    . 'sus datos.',
                    'requiereSesion'
                );
            }

            return $this->consultarSaldo($idUsuario);
        }

        if ($this->esConsultaDeIncidencias($pregunta)) {
            if ($idUsuario === null) {
                return $this->resultado(
                    'La vista previa no consulta reportes personales. '
                    . 'En Android, el residente autenticado podrá '
                    . 'consultar el estado de sus incidencias.',
                    'requiereSesion'
                );
            }

            return $this->consultarIncidencias($idUsuario);
        }

        // Buscar entre las FAQs activas.
        $faq = $this->buscarFaq($pregunta);

        if ($faq !== null) {
            return $this->resultado(
                (string) $faq['respuesta'],
                'faq',
                (int) $faq['id_faq']
            );
        }

        return $this->resultado(
            'Todavía no tengo una respuesta confirmada para '
            . 'esa pregunta. Puedes consultar la sección '
            . 'correspondiente de ALKIRA o contactar a administración.',
            'sinCoincidencia'
        );
    }

    private function consultarSaldo(int $idUsuario): array
    {
        $consulta = $this->conexion->prepare(<<<'SQL'
SELECT
    COUNT(*) AS cuentasPendientes,
    COALESCE(
        SUM(GREATEST(
            0,
            ec.monto_principal
                + ec.recargo_mora
                - ec.monto_pagado
        )),
        0
    ) AS saldoPendiente,
    COALESCE(
        SUM(
            CASE
                WHEN ec.fecha_limite_pago < CURDATE()
                THEN GREATEST(
                    0,
                    ec.monto_principal
                        + ec.recargo_mora
                        - ec.monto_pagado
                )
                ELSE 0
            END
        ),
        0
    ) AS saldoVencido
FROM estados_cuenta ec
JOIN contratos c
    ON c.id_contrato = ec.id_contrato
WHERE c.id_inquilino = :idUsuario
  AND ec.estado NOT IN ('pagado', 'cancelado')
  AND (
      ec.monto_principal
        + ec.recargo_mora
        - ec.monto_pagado
  ) > 0
SQL);

        $consulta->execute([
            'idUsuario' => $idUsuario
        ]);

        $datos = $consulta->fetch(PDO::FETCH_ASSOC) ?: [];

        $cantidad = (int) (
            $datos['cuentasPendientes'] ?? 0
        );

        $pendiente = (float) (
            $datos['saldoPendiente'] ?? 0
        );

        $vencido = (float) (
            $datos['saldoVencido'] ?? 0
        );

        if ($cantidad === 0) {
            return $this->resultado(
                'No encontré adeudos pendientes asociados '
                . 'a tus contratos en ALKIRA. Si esperabas '
                . 'un cargo, revisa la sección Pagos o '
                . 'consulta con administración.',
                'saldo'
            );
        }

        return $this->resultado(
            'Según tus estados de cuenta registrados, tienes '
            . $cantidad
            . ' cargo(s) pendiente(s), por un total de $'
            . number_format($pendiente, 2, '.', ',')
            . ' MXN. De ese monto, $'
            . number_format($vencido, 2, '.', ',')
            . ' MXN corresponde a cargos vencidos. '
            . 'Consulta Pagos para ver el detalle.',
            'saldo'
        );
    }

    private function consultarIncidencias(
        int $idUsuario
    ): array {
        $consulta = $this->conexion->prepare(<<<'SQL'
SELECT
    id_incidencia,
    titulo,
    estado
FROM incidencias
WHERE id_usuario_reporta = :idUsuario
ORDER BY
    fecha_reporte DESC,
    id_incidencia DESC
LIMIT 5
SQL);

        $consulta->execute([
            'idUsuario' => $idUsuario
        ]);

        $registros = $consulta->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (!$registros) {
            return $this->resultado(
                'No encontré incidencias registradas por '
                . 'tu usuario. Puedes crear una desde '
                . 'la sección Incidencias.',
                'incidencias'
            );
        }

        $lineas = [];

        foreach ($registros as $fila) {
            $titulo = trim((string) $fila['titulo']);

            $titulo = preg_replace(
                '/\s+/u',
                ' ',
                $titulo
            ) ?: $titulo;

            $lineas[] = '#'
                . (int) $fila['id_incidencia']
                . ' — '
                . $titulo
                . ': '
                . str_replace(
                    '_',
                    ' ',
                    (string) $fila['estado']
                );
        }

        return $this->resultado(
            "Estas son tus últimas incidencias:\n"
            . implode("\n", $lineas)
            . "\nConsulta Incidencias para ver "
            . "todos los detalles.",
            'incidencias'
        );
    }

    private function buscarFaq(
        string $pregunta
    ): ?array {
        $consulta = $this->conexion->query(<<<'SQL'
SELECT
    id_faq,
    pregunta,
    respuesta,
    palabras_clave,
    prioridad
FROM preguntas_frecuentes_ia
WHERE activa = 1
ORDER BY prioridad DESC, id_faq DESC
LIMIT 500
SQL);

        $faqs = $consulta->fetchAll(
            PDO::FETCH_ASSOC
        );

        $mejor = null;
        $mejorPuntuacion = 0;

        foreach ($faqs as $faq) {
            $preguntaFaq = self::normalizar(
                (string) $faq['pregunta']
            );

            $puntos = 0;

            // Coincidencia exacta.
            if ($pregunta === $preguntaFaq) {
                $puntos += 100;
            } elseif (
                strlen($preguntaFaq) > 8
                && (
                    str_contains(
                        ' ' . $pregunta . ' ',
                        ' ' . $preguntaFaq . ' '
                    )
                    || str_contains(
                        ' ' . $preguntaFaq . ' ',
                        ' ' . $pregunta . ' '
                    )
                )
            ) {
                $puntos += 35;
            }

            // Palabras significativas compartidas.
            $palabras = array_filter(
                array_unique(
                    preg_split(
                        '/\s+/',
                        $pregunta
                    ) ?: []
                ),
                static fn (string $p): bool =>
                    strlen($p) >= 4
                    && !in_array(
                        $p,
                        [
                            'como', 'donde', 'puedo',
                            'quiero', 'para', 'sobre',
                            'tengo', 'hacer', 'cual',
                            'esta', 'este', 'cuando',
                            'favor', 'cuanto'
                        ],
                        true
                    )
            );

            $palabrasFaq = array_filter(
                array_unique(
                    preg_split(
                        '/\s+/',
                        $preguntaFaq
                    ) ?: []
                ),
                static fn (string $p): bool =>
                    strlen($p) >= 4
            );

            $coincidencias = array_intersect(
                $palabras,
                $palabrasFaq
            );

            if (count($coincidencias) >= 2) {
                $puntos += count($coincidencias) * 3;
            }

            // Reglas definidas por el administrador.
            $claves = explode(
                ',',
                (string) $faq['palabras_clave']
            );

            foreach ($claves as $clave) {
                $clave = self::normalizar($clave);

                if (
                    $clave !== ''
                    && strlen($clave) >= 4
                    && str_contains(
                        ' ' . $pregunta . ' ',
                        ' ' . $clave . ' '
                    )
                ) {
                    $puntos += str_contains($clave, ' ')
                        ? 16
                        : 10;
                }
            }

            $puntos += min(
                10,
                max(0, (int) $faq['prioridad'])
            );

            // La prioridad sola no activa una respuesta.
            if (
                $puntos >= 15
                && $puntos > $mejorPuntuacion
            ) {
                $mejorPuntuacion = $puntos;
                $mejor = $faq;
            }
        }

        return $mejor;
    }

    private function esConsultaDeSaldo(
    string $texto
): bool {
    // Preguntas que solicitan orientación general
    // deben buscarse primero entre las FAQs.
    if (
        preg_match(
            '/\b(como puedo|como consultar|donde puedo|donde veo|donde consulto|que puedo hacer|que hago|como pagar|que opciones tengo)\b/',
            $texto
        ) === 1
    ) {
        return false;
    }

    // Preguntas sobre información financiera personal.
    return preg_match(
        '/\b(cuanto debo|cuanto me falta|mis adeudos|mi adeudo|mi deuda|saldo pendiente|cuanto es mi saldo|cual es mi saldo|tengo pagos pendientes|tengo adeudos|debo algo|mi saldo actual)\b/',
        $texto
    ) === 1;
}

    private function esConsultaDeIncidencias(
        string $texto
    ): bool {
        if (
            preg_match(
                '/\b(como puedo|como consultar|donde puedo|donde veo|donde consulto)\b/',
                $texto
            ) === 1
        ) {
            return false;
        }

        return preg_match(
            '/\b(mis incidencias|mi incidencia|mis reportes|mi reporte|mis tickets|estado de (mi|mis) (reporte|reportes|incidencia|incidencias)|estatus de (mi|mis) (reporte|reportes|incidencia|incidencias))\b/',
            $texto
        ) === 1;
    }

    private static function normalizar(
        string $texto
    ): string {
        $texto = strtr($texto, [
            'Á' => 'a', 'É' => 'e',
            'Í' => 'i', 'Ó' => 'o',
            'Ú' => 'u', 'Ü' => 'u',
            'Ñ' => 'n', 'á' => 'a',
            'é' => 'e', 'í' => 'i',
            'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        $texto = strtolower($texto);

        $texto = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $texto
        ) ?? '';

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $texto
            ) ?? ''
        );
    }

    private function resultado(
        string $respuesta,
        string $tipo,
        ?int $idFaq = null
    ): array {
        return [
            'respuesta' => $respuesta,
            'tipo' => $tipo,
            'idFaq' => $idFaq
        ];
    }
}