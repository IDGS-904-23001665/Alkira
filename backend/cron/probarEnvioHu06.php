<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/services/FcmHu06Servicio.php';

const ID_NOTIFICACION_PRUEBA = 2;
const CORREO_TECNICO_PRUEBA = 'tecnico@alkira.test';

try {
    if (getenv('CONFIRMAR_ENVIO_HU06') !== '1') {
        throw new RuntimeException(
            'Envío bloqueado. Falta confirmación explícita.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    if ($conexion->query('SELECT DATABASE()')->fetchColumn() !== 'railway') {
        throw new RuntimeException(
            'La base de datos no corresponde a Railway.'
        );
    }

    $consulta = $conexion->prepare("
        SELECT
            n.id_notificacion,
            n.titulo,
            n.mensaje,
            n.estado_envio,
            d.token_fcm
        FROM notificaciones_push_log n
        INNER JOIN usuarios u
            ON u.id_usuario = n.id_usuario
        LEFT JOIN hu07_dispositivos d
            ON d.id_usuario = u.id_usuario
        WHERE n.id_notificacion = :idNotificacion
          AND u.correo = :correoTecnico
    ");

    $consulta->execute([
        'idNotificacion' => ID_NOTIFICACION_PRUEBA,
        'correoTecnico' => CORREO_TECNICO_PRUEBA
    ]);

    $registros = $consulta->fetchAll(PDO::FETCH_ASSOC);

    if (count($registros) !== 1) {
        throw new RuntimeException(
            'Se esperaba exactamente un dispositivo del técnico de prueba.'
        );
    }

    $notificacion = $registros[0];

    if ($notificacion['estado_envio'] !== 'pendiente') {
        throw new RuntimeException(
            'La notificación no está pendiente. No se reenviará.'
        );
    }

    if (empty($notificacion['token_fcm'])) {
        throw new RuntimeException(
            'El técnico no tiene un token FCM registrado.'
        );
    }

    echo "=== HU06 | PRUEBA FCM ===\n";
    echo "Notificación: #" . ID_NOTIFICACION_PRUEBA . "\n";
    echo "Destinatario: técnico de prueba\n";

    $servicio = new FcmHu06Servicio();

    $servicio->enviar(
        $notificacion['token_fcm'],
        $notificacion['titulo'],
        $notificacion['mensaje']
    );

    // 'enviada' significa aceptada por FCM,
    // no entrega confirmada por Android.
    $actualizar = $conexion->prepare("
        UPDATE notificaciones_push_log
        SET estado_envio = 'enviada',
            fecha_envio = NOW()
        WHERE id_notificacion = :idNotificacion
          AND estado_envio = 'pendiente'
    ");

    $actualizar->execute([
        'idNotificacion' => ID_NOTIFICACION_PRUEBA
    ]);

    if ($actualizar->rowCount() !== 1) {
        throw new RuntimeException(
            'Firebase aceptó el aviso, pero no se pudo actualizar su estado.'
        );
    }

    echo "FCM: MENSAJE ACEPTADO\n";
    echo "MySQL: NOTIFICACIÓN MARCADA COMO ENVIADA\n";

} catch (Throwable $error) {
    fwrite(
        STDERR,
        "ERROR HU06: " . $error->getMessage() . PHP_EOL
    );

    exit(1);
}
