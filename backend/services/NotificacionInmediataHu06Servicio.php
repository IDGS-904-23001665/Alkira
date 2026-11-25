<?php
declare(strict_types=1);

require_once __DIR__ . '/FcmHu06Servicio.php';

/**
 * Envía de inmediato el aviso de una orden ya confirmada en MySQL.
 * Comparte el bloqueo de MySQL con el cron HU06 para evitar envíos paralelos.
 */
final class NotificacionInmediataHu06Servicio
{
    private const BLOQUEO_HU06 = 'alkira_hu06_fcm';
    private const TITULO_HU06 = 'Nueva orden de mantenimiento';

    public function __construct(private PDO $conexion)
    {
    }

    /**
     * @return string enviada|pendiente|fallida
     */
    public function enviarPendiente(int $idNotificacion): string
    {
        // Activación explícita en API-PHP: no afecta al worker actual.
        if (getenv('ALKIRA_FCM_HU06_INMEDIATO') !== '1') {
            return 'pendiente';
        }

        if ($idNotificacion < 1) {
            throw new InvalidArgumentException('La notificación no es válida.');
        }

        // El cron utiliza este mismo bloqueo. Si ya está ocupado,
        // dejamos la notificación pendiente para su siguiente ejecución.
        $consultaBloqueo = $this->conexion->prepare(
            'SELECT GET_LOCK(:nombreBloqueo, 0)'
        );
        $consultaBloqueo->execute([
            'nombreBloqueo' => self::BLOQUEO_HU06,
        ]);

        if ((int) $consultaBloqueo->fetchColumn() !== 1) {
            return 'pendiente';
        }

        try {
            $consultaNotificacion = $this->conexion->prepare(
                "SELECT id_usuario, titulo, mensaje, estado_envio
                 FROM notificaciones_push_log
                 WHERE id_notificacion = :idNotificacion
                   AND titulo = :titulo
                 LIMIT 1"
            );
            $consultaNotificacion->execute([
                'idNotificacion' => $idNotificacion,
                'titulo' => self::TITULO_HU06,
            ]);
            $notificacion = $consultaNotificacion->fetch(PDO::FETCH_ASSOC);

            if ($notificacion === false) {
                return 'pendiente';
            }

            if ($notificacion['estado_envio'] !== 'pendiente') {
                return (string) $notificacion['estado_envio'];
            }

            $consultaDispositivos = $this->conexion->prepare(
                'SELECT token_fcm FROM hu07_dispositivos
                 WHERE id_usuario = :idUsuario
                 ORDER BY actualizado_en DESC
                 LIMIT 3'
            );
            $consultaDispositivos->execute([
                'idUsuario' => (int) $notificacion['id_usuario'],
            ]);
            $tokens = $consultaDispositivos->fetchAll(PDO::FETCH_COLUMN);

            // Si aún no se registró el dispositivo, el cron podrá
            // enviarlo después cuando el técnico inicie sesión.
            if ($tokens === []) {
                return 'pendiente';
            }

            // Misma reserva utilizada por el worker. En caso de un
            // resultado incierto, no reintentamos automáticamente.
            $consultaReservar = $this->conexion->prepare(
                "UPDATE notificaciones_push_log
                 SET estado_envio = 'fallida'
                 WHERE id_notificacion = :idNotificacion
                   AND estado_envio = 'pendiente'"
            );
            $consultaReservar->execute([
                'idNotificacion' => $idNotificacion,
            ]);

            if ($consultaReservar->rowCount() !== 1) {
                return 'pendiente';
            }

            $servicioFirebase = new FcmHu06Servicio(8);
            $aceptados = 0;

            foreach ($tokens as $token) {
                try {
                    $servicioFirebase->enviar(
                        (string) $token,
                        (string) $notificacion['titulo'],
                        (string) $notificacion['mensaje']
                    );
                    $aceptados++;
                } catch (Throwable $error) {
                    // No registrar tokens, JWT ni contenido de credenciales.
                    error_log(
                        'HU06 FCM inmediato: fallo para aviso #'
                        . $idNotificacion . ' (' . get_class($error) . ')'
                    );
                }
            }

            if ($aceptados === 0) {
                return 'fallida';
            }

            $consultaConfirmar = $this->conexion->prepare(
                "UPDATE notificaciones_push_log
                 SET estado_envio = 'enviada', fecha_envio = NOW()
                 WHERE id_notificacion = :idNotificacion
                   AND estado_envio = 'fallida'"
            );
            $consultaConfirmar->execute([
                'idNotificacion' => $idNotificacion,
            ]);

            if ($consultaConfirmar->rowCount() !== 1) {
                throw new RuntimeException(
                    'FCM aceptó el aviso, pero no se confirmó el estado en MySQL.'
                );
            }

            return 'enviada';
        } finally {
            $consultaLiberar = $this->conexion->prepare(
                'SELECT RELEASE_LOCK(:nombreBloqueo)'
            );
            $consultaLiberar->execute([
                'nombreBloqueo' => self::BLOQUEO_HU06,
            ]);
        }
    }
}
