<?php
declare(strict_types=1);

final class FcmHu06Servicio
{
    public function __construct(private int $tiempoMaximoSegundos = 30)
    {
        if ($tiempoMaximoSegundos < 1 || $tiempoMaximoSegundos > 30) {
            throw new InvalidArgumentException('Tiempo de espera FCM inválido.');
        }
    }

    public function enviar(
        string $token,
        string $titulo,
        string $mensaje
    ): void {
        $cuenta = $this->obtenerCuentaServicio();
        $tokenAcceso = $this->obtenerTokenAcceso($cuenta);

        $datos = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $titulo,
                    'body' => $mensaje
                ]
            ]
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/'
            . rawurlencode($cuenta['project_id'])
            . '/messages:send';

        $respuesta = $this->solicitar(
            $url,
            json_encode($datos, JSON_THROW_ON_ERROR),
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $tokenAcceso
            ]
        );

        if (empty($respuesta['name'])) {
            throw new RuntimeException(
                'Firebase no confirmó la aceptación del mensaje.'
            );
        }
    }

    private function obtenerCuentaServicio(): array
    {
        $valor = getenv('ALKIRA_FCM_CUENTA_SERVICIO_B64');

        if (!is_string($valor) || $valor === '') {
            throw new RuntimeException(
                'Falta configurar Firebase en Railway.'
            );
        }

        $contenido = base64_decode($valor, true);

        if ($contenido === false) {
            throw new RuntimeException(
                'La cuenta de servicio tiene un formato inválido.'
            );
        }

        $cuenta = json_decode(
            $contenido,
            true,
            16,
            JSON_THROW_ON_ERROR
        );

        if (
            !is_array($cuenta)
            || ($cuenta['type'] ?? '') !== 'service_account'
            || empty($cuenta['project_id'])
            || empty($cuenta['client_email'])
            || empty($cuenta['private_key'])
        ) {
            throw new RuntimeException(
                'La cuenta de servicio está incompleta.'
            );
        }

        return $cuenta;
    }

    private function obtenerTokenAcceso(array $cuenta): string
    {
        if (!function_exists('openssl_sign')) {
            throw new RuntimeException(
                'OpenSSL no está disponible en PHP.'
            );
        }

        $ahora = time();

        $cabecera = $this->base64Url(
            json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT'
            ], JSON_THROW_ON_ERROR)
        );

        $contenido = $this->base64Url(
            json_encode([
                'iss' => $cuenta['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $ahora,
                'exp' => $ahora + 3000
            ], JSON_THROW_ON_ERROR)
        );

        $texto = $cabecera . '.' . $contenido;

        if (!openssl_sign(
            $texto,
            $firma,
            $cuenta['private_key'],
            OPENSSL_ALGO_SHA256
        )) {
            throw new RuntimeException(
                'No fue posible firmar la solicitud OAuth.'
            );
        }

        $jwt = $texto . '.' . $this->base64Url($firma);

        $respuesta = $this->solicitar(
            'https://oauth2.googleapis.com/token',
            http_build_query([
                'grant_type' =>
                    'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        $tokenAcceso = $respuesta['access_token'] ?? null;

        if (!is_string($tokenAcceso) || $tokenAcceso === '') {
            throw new RuntimeException(
                'Google no devolvió un token OAuth válido.'
            );
        }

        return $tokenAcceso;
    }

    private function solicitar(
        string $url,
        string $cuerpo,
        array $cabeceras
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'La extensión cURL no está disponible.'
            );
        }

        $conexion = curl_init($url);

        if ($conexion === false) {
            throw new RuntimeException(
                'No fue posible iniciar la conexión HTTPS.'
            );
        }

        curl_setopt_array($conexion, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_HTTPHEADER => $cabeceras,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->tiempoMaximoSegundos),
            CURLOPT_TIMEOUT => $this->tiempoMaximoSegundos,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false
        ]);

        $respuesta = curl_exec($conexion);
        $codigo = (int) curl_getinfo(
            $conexion,
            CURLINFO_HTTP_CODE
        );

        if ($respuesta === false) {
            curl_close($conexion);

            throw new RuntimeException(
                'Falló la conexión HTTPS con Google.'
            );
        }

        curl_close($conexion);

        if ($codigo < 200 || $codigo >= 300) {
            $error = json_decode($respuesta, true);
            $estado = is_array($error)
                ? ($error['error']['status'] ?? 'SIN_DETALLE')
                : 'SIN_DETALLE';

            throw new RuntimeException(
                "Google rechazó la solicitud: HTTP $codigo ($estado)."
            );
        }

        $datos = json_decode(
            $respuesta,
            true,
            16,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($datos)) {
            throw new RuntimeException(
                'Google devolvió una respuesta inválida.'
            );
        }

        return $datos;
    }

    private function base64Url(string $contenido): string
    {
        return rtrim(
            strtr(base64_encode($contenido), '+/', '-_'),
            '='
        );
    }
}
