<?php
declare(strict_types=1);

/** Correo transaccional mediante Brevo. Sin Composer ni SMTP local.
 * No registrar claves API, destinatarios ni contenido completo en logs.
 */
final class Hu07CorreoBrevo
{
    public static function enviar(array $config, string $destinatario, string $asunto, string $texto): void
    {
        $apiKey=trim((string)($config['brevoApiKey']??''));
        $remitente=trim((string)($config['correoDesde']??''));
        if ($apiKey==='' || !filter_var($remitente,FILTER_VALIDATE_EMAIL)
            || !filter_var($destinatario,FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configuración de correo incompleta o dirección inválida.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('La extensión PHP cURL debe estar habilitada para Brevo.');
        }
        $cuerpo=json_encode([
            'sender'=>['name'=>'Administración ALKIRA','email'=>$remitente],
            'to'=>[['email'=>$destinatario]],
            'subject'=>$asunto,
            'textContent'=>$texto,
        ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $ch=curl_init('https://api.brevo.com/v3/smtp/email');
        if($ch===false)throw new RuntimeException('No se pudo iniciar conexión de correo.');
        try {
            curl_setopt_array($ch,[
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>$cuerpo,
                CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.$apiKey,'content-type: application/json'],
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>8,
                CURLOPT_TIMEOUT=>20,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
            ]);
            $respuesta=curl_exec($ch);
            $codigo=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            if ($respuesta===false || $codigo<200 || $codigo>=300) {
                // No incluir cuerpos de error ni secretos en logs.
                throw new RuntimeException('El proveedor de correo no aceptó el envío. HTTP '.$codigo.'.');
            }
            // Brevo devuelve un id de mensaje cuando acepta el envío.
            $json=json_decode((string)$respuesta,true);
            if(!is_array($json) || empty($json['messageId'])) {
                throw new RuntimeException('El proveedor no devolvió confirmación de aceptación.');
            }
        } finally {
            curl_close($ch);
        }
    }
}
