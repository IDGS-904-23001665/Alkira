<?php
declare(strict_types=1);
// Ejecutar desde CLI cada minuto mediante Programador de tareas / cron.
// Nunca accesible como endpoint web.
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/repos/Hu07Repositorio.php';

try {
    $db=Conexion::obtenerConexion();
    $repo=new Hu07Repositorio($db);
    $publicados=$repo->publicarProgramados();
    $rutaConfig=dirname(__DIR__).'/config/hu07.local.php';
    if(!is_file($rutaConfig)) {
        echo "Comunicados publicados: $publicados. Configura hu07.local.php para el correo y push.\n";
        exit(0);
    }
    $config=require $rutaConfig;
    // Entregas externas DESACTIVADAS por defecto. Modo prueba restringido a un usuario.
    $proveedorCorreo=(string)($config['proveedorCorreo']??'brevo');
    $correoBase=($config['habilitarCorreo']??false)===true
        && (bool)filter_var($config['correoDesde']??'',FILTER_VALIDATE_EMAIL);
    $correo=$correoBase && (
        ($proveedorCorreo==='brevo' && is_string($config['brevoApiKey']??null)
            && trim($config['brevoApiKey'])!=='') || $proveedorCorreo==='mail'
    );
    $usuarioPrueba=(int)($config['idUsuarioPrueba']??0);
    $permitirMasivo=($config['permitirEnvioMasivo']??false)===true;
    $idComunicadoDesde=(int)($config['idComunicadoDesde']??0);
    $push=is_string($config['cuentaServicioFcm']??null)&&is_file($config['cuentaServicioFcm'])
        && is_string($config['proyectoFcm']??null) && $config['proyectoFcm']!=='';
    if(!$correo&&!$push) {echo "Comunicados publicados: $publicados. Entregas externas desactivadas.\n";exit(0);}
    // Evita enviar a residentes reales accidentalmente durante las pruebas.
    if($idComunicadoDesde<=0) {
        echo "Comunicados publicados: $publicados. Entregas externas bloqueadas: configura idComunicadoDesde.\n";
        exit(0);
    }
    if(!$permitirMasivo && $usuarioPrueba<=0) {
        echo "Comunicados publicados: $publicados. Entregas externas bloqueadas: configura idUsuarioPrueba.\n";
        exit(0);
    }

    // Solo reclamar trabajos con un único UPDATE; evita procesar el mismo envío en 2 procesos.
    $s=$db->prepare("SELECT e.id_envio,e.canal,e.id_usuario,c.titulo,c.contenido,u.correo
        FROM hu07_envios e JOIN comunicados c ON c.id_comunicado=e.id_comunicado
        JOIN usuarios u ON u.id_usuario=e.id_usuario
        WHERE e.estado='pendiente' AND c.estado='publicado'
        AND e.id_comunicado>=:idComunicadoDesde
        AND (:permitirMasivo=1 OR e.id_usuario=:usuarioPrueba)
        AND ((e.canal='correo' AND :correo=1) OR (e.canal='push' AND :push=1
          AND EXISTS (SELECT 1 FROM hu07_dispositivos d WHERE d.id_usuario=e.id_usuario)))
        ORDER BY e.id_envio LIMIT 60");
    $s->execute(['correo'=>$correo?1:0,'push'=>$push?1:0,
        'permitirMasivo'=>$permitirMasivo?1:0,'usuarioPrueba'=>$usuarioPrueba,
        'idComunicadoDesde'=>$idComunicadoDesde]);$tareas=$s->fetchAll(PDO::FETCH_ASSOC);
    $tomar=$db->prepare("UPDATE hu07_envios SET estado='procesando',intentos=intentos+1 WHERE id_envio=? AND estado='pendiente'");
    $terminar=$db->prepare('UPDATE hu07_envios SET estado=?,ultimo_error=?,fecha_envio=? WHERE id_envio=?');
    $hechos=0;
    foreach($tareas as $t){
        $tomar->execute([$t['id_envio']]);if($tomar->rowCount()!==1)continue;
        try {
            if($t['canal']==='correo'){
                $asunto='=?UTF-8?B?'.base64_encode('ALKIRA · '.$t['titulo']).'?=';
                $texto=html_entity_decode(strip_tags(str_replace(['</p>','<br>','<br/>'],"\n",$t['contenido'])),ENT_QUOTES|ENT_HTML5,'UTF-8');
                $cabeceras=['From: '.$config['correoDesde'],'Content-Type: text/plain; charset=UTF-8','MIME-Version: 1.0'];
                if($proveedorCorreo==='brevo') {
                    require_once dirname(__DIR__).'/services/Hu07CorreoBrevo.php';
                    Hu07CorreoBrevo::enviar($config,(string)$t['correo'],
                        'ALKIRA - '.$t['titulo'],"Aviso de la administración ALKIRA\n\n".$texto);
                } else {
                    if(!mail($t['correo'],$asunto,"Aviso de la administración ALKIRA\n\n".$texto,implode("\r\n",$cabeceras)))
                        throw new RuntimeException('mail() rechazó el envío; configura SMTP/sendmail de PHP.');
                }
            }else{
                $tokens=$db->prepare('SELECT token_fcm FROM hu07_dispositivos WHERE id_usuario=?');
                $tokens->execute([$t['id_usuario']]);$lista=$tokens->fetchAll(PDO::FETCH_COLUMN);
                if(!$lista)throw new RuntimeException('El residente aún no registró un dispositivo FCM.');
                foreach($lista as $token){
                    enviarFcm($config,$token,$t['titulo'],strip_tags($t['contenido']));
                }
            }
            // 'enviado' = aceptado por la API/servidor de envío, no entrega confirmada en bandeja.
            $terminar->execute(['enviado',null,date('Y-m-d H:i:s'),$t['id_envio']]);$hechos++;
        }catch(Throwable $error){
            // No reintentar automáticamente el correo: el proveedor podría haberlo recibido.
            // Los fallidos quedan auditados y pueden revisarse en la BD.
            $terminar->execute(['fallido',substr($error->getMessage(),0,350),null,$t['id_envio']]);
            error_log('HU07 entrega '.$t['id_envio'].' fallida: '.$error->getMessage());
        }
    }
    echo "Publicados: $publicados. Envíos completados: $hechos. Revisar hu07_envios para fallos.\n";
}catch(Throwable $error){error_log('HU07 cron: '.$error->getMessage());fwrite(STDERR,"Error HU07; revisa error_log de PHP.\n");exit(1);}

function enviarFcm(array $config,string $token,string $titulo,string $contenido): void
{
    if(!function_exists('openssl_sign'))throw new RuntimeException('Extensión OpenSSL no disponible.');
    $service=json_decode(file_get_contents($config['cuentaServicioFcm']),true,16,JSON_THROW_ON_ERROR);
    if(($service['type']??'')!=='service_account'||!isset($service['client_email'],$service['private_key']))
        throw new RuntimeException('Cuenta de servicio FCM no válida.');
    $ahora=time();$b64=fn($x)=>rtrim(strtr(base64_encode(json_encode($x,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');
    $texto=$b64(['alg'=>'RS256','typ'=>'JWT']).'.'.$b64([
        'iss'=>$service['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging',
        'aud'=>'https://oauth2.googleapis.com/token','iat'=>$ahora,'exp'=>$ahora+3000]);
    if(!openssl_sign($texto,$firma,$service['private_key'],OPENSSL_ALGO_SHA256))
        throw new RuntimeException('No se pudo firmar el token FCM.');
    $assertion=$texto.'.'.rtrim(strtr(base64_encode($firma),'+/','-_'),'=');
    $resp=httpHu07('https://oauth2.googleapis.com/token',http_build_query([
        'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$assertion
    ]),['Content-Type: application/x-www-form-urlencoded']);
    $access=json_decode($resp,true,16,JSON_THROW_ON_ERROR)['access_token']??null;
    if(!is_string($access)||$access==='')throw new RuntimeException('No se recibió OAuth FCM.');
    $url='https://fcm.googleapis.com/v1/projects/'.rawurlencode($config['proyectoFcm']).'/messages:send';
    $payload=json_encode(['message'=>[
        'token'=>$token,
        'notification'=>['title'=>substr('ALKIRA · '.$titulo,0,100),'body'=>substr($contenido,0,240)],
        'android'=>['priority'=>'high'],
        'data'=>['tipo'=>'comunicado','ruta'=>'avisos'],
    ]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    httpHu07($url,$payload,['Authorization: Bearer '.$access,'Content-Type: application/json']);
}
function httpHu07(string $url,string $body,array $headers): string
{
    $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),
        'content'=>$body,'timeout'=>15,'ignore_errors'=>true]]);
    $respuesta=@file_get_contents($url,false,$context);
    $codigo=0;foreach($http_response_header??[] as $line)if(preg_match('~^HTTP/\S+\s+(\d{3})~',$line,$m))$codigo=(int)$m[1];
    if($respuesta===false||$codigo<200||$codigo>=300)
        throw new RuntimeException('Error HTTP del proveedor externo: '.$codigo);
    return $respuesta;
}
