<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/FcmHu06Servicio.php';

const TITULO_NOTIFICACION_HU06 = 'Nueva orden de mantenimiento';
const NOMBRE_BLOQUEO_HU06 = 'alkira_hu06_fcm';

$codigoSalida = 0;
$conexion = null;
$bloqueoAdquirido = false;

try {
    // El proceso permanece desactivado hasta habilitarlo en Railway.
    if (getenv('ALKIRA_FCM_HU06_ACTIVO') !== '1') {
        echo "HU06: envío automático desactivado.\n";
        exit(0);
    }

    // Protege contra el envío de notificaciones antiguas.
    $valorMinimo = getenv('ALKIRA_FCM_HU06_DESDE_ID');

    if (
        !is_string($valorMinimo)
        || !ctype_digit($valorMinimo)
        || (int) $valorMinimo <= 0
    ) {
        throw new RuntimeException(
            'Falta configurar ALKIRA_FCM_HU06_DESDE_ID.'
        );
    }

    $idMinimo = (int) $valorMinimo;

    $conexion = Conexion::obtenerConexion();
    $conexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // Solo una ejecución puede procesar la cola al mismo tiempo.
    $consultaBloqueo = $conexion->prepare(
        'SELECT GET_LOCK(:nombreBloqueo, 0)'
    );

    $consultaBloqueo->execute([
        'nombreBloqueo' => NOMBRE_BLOQUEO_HU06
    ]);

    $bloqueoAdquirido =
        (int) $consultaBloqueo->fetchColumn() === 1;

    if (!$bloqueoAdquirido) {
        throw new RuntimeException(
            'Ya existe otra ejecución de HU06 activa.'
        );
    }

    // Solo procesamos avisos nuevos de órdenes de mantenimiento.
    $consultaPendientes = $conexion->prepare("
        SELECT
            id_notificacion,
            id_usuario,
            titulo,
            mensaje
        FROM notificaciones_push_log
        WHERE estado_envio = 'pendiente'
          AND titulo = :titulo
          AND id_notificacion > :idMinimo
        ORDER BY id_notificacion ASC
        LIMIT 5
    ");

    $consultaPendientes->bindValue(
        ':titulo',
        TITULO_NOTIFICACION_HU06,
        PDO::PARAM_STR
    );

    $consultaPendientes->bindValue(
        ':idMinimo',
        $idMinimo,
        PDO::PARAM_INT
    );

    $consultaPendientes->execute();

    $notificaciones = $consultaPendientes->fetchAll(
        PDO::FETCH_ASSOC
    );

    echo "=== PROCESADOR AUTOMÁTICO HU06 ===\n";
    echo "Notificaciones encontradas: "
        . count($notificaciones)
        . PHP_EOL;

    $consultaDispositivos = $conexion->prepare("
        SELECT token_fcm
        FROM hu07_dispositivos
        WHERE id_usuario = :idUsuario
        ORDER BY actualizado_en DESC
        LIMIT 3
    ");

    // Reservar el aviso antes de llamar a un servicio externo.
    // En caso de interrupción queda 'fallida' para revisión manual.
    $consultaReservar = $conexion->prepare("
        UPDATE notificaciones_push_log
        SET estado_envio = 'fallida'
        WHERE id_notificacion = :idNotificacion
          AND estado_envio = 'pendiente'
    ");

    $consultaConfirmar = $conexion->prepare("
        UPDATE notificaciones_push_log
        SET estado_envio = 'enviada',
            fecha_envio = NOW()
        WHERE id_notificacion = :idNotificacion
          AND estado_envio = 'fallida'
    ");

    $servicioFirebase = new FcmHu06Servicio();

    foreach ($notificaciones as $notificacion) {
        $idNotificacion = (int) $notificacion['id_notificacion'];
        $idUsuario = (int) $notificacion['id_usuario'];

        $consultaDispositivos->execute([
            'idUsuario' => $idUsuario
        ]);

        $tokens = $consultaDispositivos->fetchAll(
            PDO::FETCH_COLUMN
        );

        // Si todavía no tiene dispositivo, conservamos pendiente.
        if (count($tokens) === 0) {
            echo "Aviso #$idNotificacion: "
                . "usuario sin dispositivo registrado.\n";
            continue;
        }

        $consultaReservar->execute([
            'idNotificacion' => $idNotificacion
        ]);

        if ($consultaReservar->rowCount() !== 1) {
            echo "Aviso #$idNotificacion: "
                . "ya fue procesado.\n";
            continue;
        }

        $aceptados = 0;
        $fallidos = 0;

        foreach ($tokens as $token) {
            try {
                $servicioFirebase->enviar(
                    (string) $token,
                    (string) $notificacion['titulo'],
                    (string) $notificacion['mensaje']
                );

                $aceptados++;
            } catch (Throwable $error) {
                $fallidos++;

                fwrite(
                    STDERR,
                    "Aviso #$idNotificacion: "
                    . "falló el envío a un dispositivo. "
                    . $error->getMessage()
                    . PHP_EOL
                );
            }
        }

        if ($aceptados > 0) {
            $consultaConfirmar->execute([
                'idNotificacion' => $idNotificacion
            ]);

            if ($consultaConfirmar->rowCount() !== 1) {
                throw new RuntimeException(
                    "Aviso #$idNotificacion: "
                    . "FCM aceptó el mensaje, "
                    . "pero MySQL no confirmó el estado."
                );
            }

            echo "Aviso #$idNotificacion: "
                . "$aceptados dispositivo(s) aceptado(s) "
                . "por Firebase.\n";

            if ($fallidos > 0) {
                echo "Aviso #$idNotificacion: "
                    . "$fallidos dispositivo(s) "
                    . "requieren revisión.\n";
            }
        } else {
            $codigoSalida = 1;

            echo "Aviso #$idNotificacion: "
                . "envío fallido; requiere revisión manual.\n";
        }
    }

} catch (Throwable $error) {
    $codigoSalida = 1;

    fwrite(
        STDERR,
        "ERROR HU06: " . $error->getMessage() . PHP_EOL
    );

} finally {
    if ($bloqueoAdquirido && $conexion instanceof PDO) {
        try {
            $consultaLiberar = $conexion->prepare(
                'SELECT RELEASE_LOCK(:nombreBloqueo)'
            );

            $consultaLiberar->execute([
                'nombreBloqueo' => NOMBRE_BLOQUEO_HU06
            ]);
        } catch (Throwable $error) {
            fwrite(
                STDERR,
                "HU06: no se pudo liberar el bloqueo.\n"
            );

            $codigoSalida = 1;
        }
    }
}

exit($codigoSalida);
