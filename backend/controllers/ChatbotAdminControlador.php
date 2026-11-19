<?php
declare(strict_types=1);

final class ChatbotAdminControlador
{
    public function __construct(private PDO $conexion) {}

    public function procesar(
        string $metodo,
        string $ruta,
        array $usuario
    ): void {
        if (($usuario['tipoUsuario'] ?? '') !== 'administrador') {
            $this->responder(403, [
                'mensaje' => 'Solo administración puede usar el módulo 9.'
            ]);
            return;
        }

        try {

if ($ruta === '/api/chatbot-admin/probar') {
    if ($metodo !== 'POST') {
        $this->metodoNoPermitido('POST');
        return;
    }

    $this->validarPeticionModificadora();

    $datos = $this->leerJson();

    if (!is_string($datos['mensaje'] ?? null)) {
        throw new InvalidArgumentException(
            'Escribe una pregunta válida.'
        );
    }

    require_once dirname(__DIR__)
        . '/services/ChatbotMotorServicio.php';

    $resultado = (
        new ChatbotMotorServicio($this->conexion)
    )->responder($datos['mensaje']);

    $this->responder(200, $resultado);
    return;
}

            if ($ruta === '/api/chatbot-admin/faqs') {
                if ($metodo === 'GET') {
                    $this->listarFaqs();
                } elseif ($metodo === 'POST') {
                    $this->validarPeticionModificadora();
                    $this->guardarFaq(
                        null,
                        (int) $usuario['idUsuario']
                    );
                } else {
                    $this->metodoNoPermitido('GET, POST');
                }
                return;
            }

            if (
                preg_match(
                    '#^/api/chatbot-admin/faqs/([1-9][0-9]*)$#D',
                    $ruta,
                    $m
                ) === 1
            ) {
                $id = (int) $m[1];

                if ($metodo === 'PUT') {
                    $this->validarPeticionModificadora();
                    $this->guardarFaq(
                        $id,
                        (int) $usuario['idUsuario']
                    );
                } elseif ($metodo === 'DELETE') {
                    $this->validarPeticionModificadora(false);
                    $this->eliminarFaq(
                        $id,
                        (int) $usuario['idUsuario']
                    );
                } else {
                    $this->metodoNoPermitido('PUT, DELETE');
                }
                return;
            }

            if (
                $ruta === '/api/chatbot-admin/conversaciones' &&
                $metodo === 'GET'
            ) {
                $this->listarConversaciones();
                return;
            }

            if (
                preg_match(
                    '#^/api/chatbot-admin/conversaciones/([1-9][0-9]*)/mensajes$#D',
                    $ruta,
                    $m
                ) === 1 &&
                $metodo === 'GET'
            ) {
                $this->leerConversacion((int) $m[1]);
                return;
            }

            if (
                $ruta === '/api/chatbot-admin/auditoria' &&
                $metodo === 'GET'
            ) {
                $this->listarAuditoria();
                return;
            }

            $this->metodoNoPermitido('GET');
        } catch (InvalidArgumentException $error) {
            $this->responder(422, [
                'mensaje' => $error->getMessage()
            ]);
        }
    }

    private function listarFaqs(): void
    {
        $sql = '
            SELECT
                id_faq AS id,
                pregunta,
                respuesta,
                categoria,
                palabras_clave AS palabrasClave,
                prioridad,
                activa,
                fecha_creacion AS fechaCreacion,
                fecha_actualizacion AS fechaActualizacion
            FROM preguntas_frecuentes_ia
            ORDER BY activa DESC, prioridad DESC, id_faq DESC
        ';

        $resultado = $this->conexion
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->responder(200, [
            'faqs' => $resultado
        ]);
    }

    private function guardarFaq(
        ?int $id,
        int $admin
    ): void {
        $datos = $this->leerJson();

        $pregunta = trim(
            $this->cadena($datos, 'pregunta', 255, true)
        );
        $respuesta = trim(
            $this->cadena($datos, 'respuesta', 8000, true)
        );
        $categoria = trim(
            $this->cadena($datos, 'categoria', 80)
        );
        $palabras = trim(
            $this->cadena($datos, 'palabrasClave', 500)
        );

        $prioridad = filter_var(
            $datos['prioridad'] ?? 5,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 10
                ]
            ]
        );

        $activa = $datos['activa'] ?? true;

        if (
            strlen($pregunta) < 5 ||
            strlen($respuesta) < 5 ||
            $prioridad === false ||
            !is_bool($activa)
        ) {
            throw new InvalidArgumentException(
                'Pregunta y respuesta requieren 5 caracteres; '
                . 'prioridad de 1 a 10 y estado válido.'
            );
        }

        if ($categoria === '') {
            $categoria = 'General';
        }

        $this->conexion->beginTransaction();

        try {
            $anterior = null;

            if ($id !== null) {
                $stmt = $this->conexion->prepare(
                    'SELECT * FROM preguntas_frecuentes_ia
                     WHERE id_faq = ? FOR UPDATE'
                );
                $stmt->execute([$id]);
                $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$anterior) {
                    $this->conexion->rollBack();
                    $this->responder(404, [
                        'mensaje' => 'Pregunta frecuente no encontrada.'
                    ]);
                    return;
                }

                $sql = '
                    UPDATE preguntas_frecuentes_ia
                    SET
                        pregunta = ?,
                        respuesta = ?,
                        categoria = ?,
                        palabras_clave = ?,
                        prioridad = ?,
                        activa = ?
                    WHERE id_faq = ?
                ';

                $this->conexion->prepare($sql)->execute([
                    $pregunta,
                    $respuesta,
                    $categoria,
                    $palabras,
                    (int) $prioridad,
                    (int) $activa,
                    $id
                ]);

                $accion = 'actualizar';
            } else {
                $sql = '
                    INSERT INTO preguntas_frecuentes_ia (
                        pregunta,
                        respuesta,
                        categoria,
                        palabras_clave,
                        prioridad,
                        activa,
                        id_administrador
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ';

                $this->conexion->prepare($sql)->execute([
                    $pregunta,
                    $respuesta,
                    $categoria,
                    $palabras,
                    (int) $prioridad,
                    (int) $activa,
                    $admin
                ]);

                $id = (int) $this->conexion->lastInsertId();
                $accion = 'crear';
            }

            $stmt = $this->conexion->prepare(
                'SELECT * FROM preguntas_frecuentes_ia
                 WHERE id_faq = ?'
            );
            $stmt->execute([$id]);

            $nuevo = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->auditar(
                $id,
                $admin,
                $accion,
                $anterior ?: null,
                $nuevo ?: null
            );

            $this->conexion->commit();

            $this->responder(
                $accion === 'crear' ? 201 : 200,
                [
                    'mensaje' => $accion === 'crear'
                        ? 'FAQ creada.'
                        : 'FAQ actualizada.',
                    'id' => $id
                ]
            );
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    private function eliminarFaq(
        int $id,
        int $admin
    ): void {
        $this->conexion->beginTransaction();

        try {
            $stmt = $this->conexion->prepare(
                'SELECT * FROM preguntas_frecuentes_ia
                 WHERE id_faq = ? FOR UPDATE'
            );
            $stmt->execute([$id]);

            $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$anterior) {
                $this->conexion->rollBack();
                $this->responder(404, [
                    'mensaje' => 'FAQ no encontrada.'
                ]);
                return;
            }

            $this->auditar(
                $id,
                $admin,
                'eliminar',
                $anterior,
                null
            );

            $this->conexion
                ->prepare(
                    'DELETE FROM preguntas_frecuentes_ia
                     WHERE id_faq = ?'
                )
                ->execute([$id]);

            $this->conexion->commit();

            $this->responder(200, [
                'mensaje' => 'FAQ eliminada y acción auditada.'
            ]);
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    private function listarConversaciones(): void
    {
        $sql = '
            SELECT
                c.id_conversacion AS id,
                c.id_usuario AS idUsuario,
                CONCAT(u.nombres, " ", u.apellidos) AS residente,
                u.correo,
                c.estado,
                c.fecha_inicio AS fechaInicio,
                c.fecha_fin AS fechaFin,
                (
                    SELECT COUNT(*)
                    FROM mensajes_ia m
                    WHERE m.id_conversacion = c.id_conversacion
                ) AS totalMensajes
            FROM conversaciones_ia c
            JOIN usuarios u
                ON u.id_usuario = c.id_usuario
            ORDER BY
                c.fecha_inicio DESC,
                c.id_conversacion DESC
            LIMIT 50
        ';

        $filas = $this->conexion
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->responder(200, [
            'conversaciones' => $filas,
            'limite' => 50
        ]);
    }

    private function leerConversacion(int $id): void
    {
        $stmt = $this->conexion->prepare('
            SELECT
                c.id_conversacion AS id,
                c.id_usuario AS idUsuario,
                CONCAT(u.nombres, " ", u.apellidos) AS residente,
                c.estado,
                c.fecha_inicio AS fechaInicio
            FROM conversaciones_ia c
            JOIN usuarios u
                ON u.id_usuario = c.id_usuario
            WHERE c.id_conversacion = ?
        ');

        $stmt->execute([$id]);
        $conversacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conversacion) {
            $this->responder(404, [
                'mensaje' => 'Conversación no encontrada.'
            ]);
            return;
        }

        $stmt = $this->conexion->prepare('
            SELECT
                id_mensaje AS id,
                emisor,
                contenido,
                fecha_mensaje AS fecha
            FROM mensajes_ia
            WHERE id_conversacion = ?
            ORDER BY id_mensaje ASC
            LIMIT 500
        ');

        $stmt->execute([$id]);

        $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->responder(200, [
            'conversacion' => $conversacion,
            'mensajes' => $mensajes,
            'limite' => 500
        ]);
    }

    private function listarAuditoria(): void
    {
        $sql = '
            SELECT
                a.id_auditoria AS id,
                a.id_faq AS idFaq,
                a.accion,
                a.antes_json AS antes,
                a.despues_json AS despues,
                a.fecha_accion AS fecha,
                CONCAT(u.nombres, " ", u.apellidos)
                    AS administrador
            FROM auditoria_faq_ia a
            JOIN usuarios u
                ON u.id_usuario = a.id_administrador
            ORDER BY a.id_auditoria DESC
            LIMIT 100
        ';

        $filas = $this->conexion
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->responder(200, [
            'auditoria' => $filas,
            'limite' => 100
        ]);
    }

    private function auditar(
        ?int $id,
        int $admin,
        string $accion,
        ?array $anterior,
        ?array $nuevo
    ): void {
        $sql = '
            INSERT INTO auditoria_faq_ia (
                id_faq,
                id_administrador,
                accion,
                antes_json,
                despues_json
            )
            VALUES (?, ?, ?, ?, ?)
        ';

        $this->conexion->prepare($sql)->execute([
            $id,
            $admin,
            $accion,
            $anterior === null
                ? null
                : json_encode(
                    $anterior,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
            $nuevo === null
                ? null
                : json_encode(
                    $nuevo,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
        ]);
    }

    private function validarPeticionModificadora(
        bool $requiereJson = true
    ): void {
        if (
            ($_SERVER['HTTP_X_ALKIRA_ADMIN'] ?? '') !== '1'
        ) {
            throw new InvalidArgumentException(
                'Falta la cabecera administrativa.'
            );
        }

        if (
            $requiereJson &&
            !preg_match(
                '~^application/json(?:\s*;|\s*$)~i',
                $_SERVER['CONTENT_TYPE'] ?? ''
            )
        ) {
            throw new InvalidArgumentException(
                'Se requiere application/json.'
            );
        }
    }

    private function leerJson(): array
    {
        $contenido = file_get_contents('php://input');

        if (
            $contenido === false ||
            strlen($contenido) > 12000
        ) {
            throw new InvalidArgumentException(
                'Solicitud demasiado grande.'
            );
        }

        $datos = json_decode($contenido, true);

        if (!is_array($datos) || array_is_list($datos)) {
            throw new InvalidArgumentException(
                'El cuerpo debe ser un objeto JSON válido.'
            );
        }

        return $datos;
    }

    private function cadena(
        array $datos,
        string $clave,
        int $maximo,
        bool $obligatoria = false
    ): string {
        $valor = $datos[$clave]
            ?? ($obligatoria ? null : '');

        if (
            !is_string($valor) ||
            strlen($valor) > $maximo
        ) {
            throw new InvalidArgumentException(
                'Campo inválido: ' . $clave
            );
        }

        return $valor;
    }

    private function responder(
        int $codigo,
        array $cuerpo
    ): void {
        http_response_code($codigo);

        echo json_encode(
            $cuerpo,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private function metodoNoPermitido(
        string $permitidos
    ): void {
        header('Allow: ' . $permitidos);

        $this->responder(405, [
            'mensaje' => 'Método no permitido.'
        ]);
    }
}
