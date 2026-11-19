<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/services/ChatbotMotorServicio.php';

final class ChatbotMovilControlador
{
    public function __construct(private PDO $conexion) {}

    public function procesar(
        string $metodo,
        string $ruta,
        array $usuario
    ): void {
        $idUsuario = (int) (
            $usuario['idUsuario'] ?? 0
        );

        if ($idUsuario <= 0) {
            $this->responder(401, [
                'mensaje' => 'Sesión inválida.'
            ]);
            return;
        }

        try {
            if (
                $ruta === '/api/chatbot/mensaje'
                && $metodo === 'POST'
            ) {
                $this->enviarMensaje($idUsuario);
                return;
            }

            if (
                $ruta === '/api/chatbot/conversaciones'
                && $metodo === 'GET'
            ) {
                $this->listarConversaciones($idUsuario);
                return;
            }

            if (
                $metodo === 'GET'
                && preg_match(
                    '#^/api/chatbot/conversaciones/([1-9][0-9]*)/mensajes$#D',
                    $ruta,
                    $partes
                ) === 1
            ) {
                $this->leerMensajes(
                    $idUsuario,
                    (int) $partes[1]
                );
                return;
            }

            $this->responder(405, [
                'mensaje' => 'Método no permitido.'
            ]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, [
                'mensaje' => $error->getMessage()
            ]);
        }
    }

    private function enviarMensaje(
        int $idUsuario
    ): void {
        $tipoContenido = $_SERVER['CONTENT_TYPE']
            ?? '';

        if (
            preg_match(
                '/^application\/json(?:\s*;|\s*$)/i',
                $tipoContenido
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Envía el mensaje como application/json.'
            );
        }

        $entrada = file_get_contents('php://input');

        if (
            $entrada === false
            || strlen($entrada) > 2500
        ) {
            throw new InvalidArgumentException(
                'La solicitud es demasiado grande.'
            );
        }

        try {
            $datos = json_decode(
                $entrada,
                true,
                12,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException(
                'El JSON no es válido.'
            );
        }

        if (
            !is_array($datos)
            || array_is_list($datos)
            || !is_string($datos['mensaje'] ?? null)
        ) {
            throw new InvalidArgumentException(
                'Es necesario enviar un mensaje de texto.'
            );
        }

        $mensaje = trim($datos['mensaje']);

        if (
            $mensaje === ''
            || strlen($mensaje) > 500
        ) {
            throw new InvalidArgumentException(
                'Escribe una pregunta de 1 a 500 caracteres.'
            );
        }

        $idConversacion = null;

        if (
            array_key_exists('idConversacion', $datos)
            && $datos['idConversacion'] !== null
        ) {
            $valor = $datos['idConversacion'];

            if (
                !is_int($valor)
                || $valor <= 0
            ) {
                throw new InvalidArgumentException(
                    'La conversación no es válida.'
                );
            }

            $idConversacion = $valor;
        }

        $resultado = (
            new ChatbotMotorServicio($this->conexion)
        )->responder($mensaje, $idUsuario);

        $this->conexion->beginTransaction();

        try {
            if ($idConversacion === null) {
                $sentencia = $this->conexion->prepare(
                    "INSERT INTO conversaciones_ia
                        (id_usuario, estado)
                     VALUES (?, 'activa')"
                );

                $sentencia->execute([$idUsuario]);

                $idConversacion = (int)
                    $this->conexion->lastInsertId();
            } else {
                // Solo permite continuar conversaciones propias.
                $sentencia = $this->conexion->prepare(
                    "SELECT id_conversacion
                     FROM conversaciones_ia
                     WHERE id_conversacion = ?
                       AND id_usuario = ?
                       AND estado = 'activa'
                     FOR UPDATE"
                );

                $sentencia->execute([
                    $idConversacion,
                    $idUsuario
                ]);

                if (
                    !$sentencia->fetch(PDO::FETCH_ASSOC)
                ) {
                    $this->conexion->rollBack();

                    $this->responder(404, [
                        'mensaje' =>
                            'Conversación no encontrada '
                            . 'o ya finalizada.'
                    ]);
                    return;
                }
            }

            $guardar = $this->conexion->prepare(
                'INSERT INTO mensajes_ia
                    (id_conversacion, emisor, contenido)
                 VALUES (?, ?, ?)'
            );

            $guardar->execute([
                $idConversacion,
                'usuario',
                $mensaje
            ]);

            $guardar->execute([
                $idConversacion,
                'asistente',
                $resultado['respuesta']
            ]);

            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            throw $error;
        }

        $this->responder(200, [
            'idConversacion' => $idConversacion,
            'respuesta' => $resultado['respuesta'],
            'tipo' => $resultado['tipo'],
            'idFaq' => $resultado['idFaq']
        ]);
    }

    private function listarConversaciones(
        int $idUsuario
    ): void {
        $sentencia = $this->conexion->prepare(<<<'SQL'
SELECT
    c.id_conversacion AS idConversacion,
    c.estado,
    c.fecha_inicio AS fechaInicio,
    c.fecha_fin AS fechaFin,
    COUNT(m.id_mensaje) AS totalMensajes
FROM conversaciones_ia c
LEFT JOIN mensajes_ia m
    ON m.id_conversacion = c.id_conversacion
WHERE c.id_usuario = ?
GROUP BY
    c.id_conversacion,
    c.estado,
    c.fecha_inicio,
    c.fecha_fin
ORDER BY
    c.fecha_inicio DESC,
    c.id_conversacion DESC
LIMIT 30
SQL);

        $sentencia->execute([$idUsuario]);

        $this->responder(200, [
            'conversaciones' => $sentencia->fetchAll(
                PDO::FETCH_ASSOC
            )
        ]);
    }

    private function leerMensajes(
        int $idUsuario,
        int $idConversacion
    ): void {
        $sentencia = $this->conexion->prepare(
            'SELECT id_conversacion
             FROM conversaciones_ia
             WHERE id_conversacion = ?
               AND id_usuario = ?'
        );

        $sentencia->execute([
            $idConversacion,
            $idUsuario
        ]);

        if (!$sentencia->fetch(PDO::FETCH_ASSOC)) {
            $this->responder(404, [
                'mensaje' => 'Conversación no encontrada.'
            ]);
            return;
        }

        $sentencia = $this->conexion->prepare(
            'SELECT
                id_mensaje AS id,
                emisor,
                contenido,
                fecha_mensaje AS fecha
             FROM mensajes_ia
             WHERE id_conversacion = ?
             ORDER BY id_mensaje ASC
             LIMIT 200'
        );

        $sentencia->execute([$idConversacion]);

        $this->responder(200, [
            'idConversacion' => $idConversacion,
            'mensajes' => $sentencia->fetchAll(
                PDO::FETCH_ASSOC
            )
        ]);
    }

    private function responder(
        int $codigo,
        array $datos
    ): void {
        http_response_code($codigo);

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
    }
}
