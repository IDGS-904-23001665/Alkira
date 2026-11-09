<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
// Diagnostico de solo lectura: NO envia correo, push ni publica comunicados.
require_once dirname(__DIR__).'/config/Conexion.php';
$cfg=dirname(__DIR__).'/config/hu07.local.php';
$config=is_file($cfg) ? require $cfg : [];
$modo=($config['permitirEnvioMasivo']??false)===true ? 'MASIVO' : 'SOLO PRUEBA';
$correo=($config['habilitarCorreo']??false)===true;
$api=trim((string)($config['brevoApiKey']??''))!=='';
$ruta=(string)($config['cuentaServicioFcm']??'');
$push=$ruta!=='' && is_file($ruta) && trim((string)($config['proyectoFcm']??''))!=='';
echo "Zona horaria de PHP: ",date_default_timezone_get(),"
";
echo "Archivo hu07.local.php: ",(is_file($cfg)?'PRESENTE':'AUSENTE'),"
";
echo "Modo de envio: ",$modo,"
";
echo "Usuario de prueba configurado: ",((int)($config['idUsuarioPrueba']??0)>0?'SI':'NO'),"
";
echo "Correo activado: ",($correo?'SI':'NO'),"; API Brevo configurada: ",($api?'SI':'NO'),"
";
echo "Extension cURL: ",(extension_loaded('curl')?'SI':'NO'),"
";
echo "FCM: ",($push?'CONFIGURADO':'SIN CONFIGURAR'),"; OpenSSL: ",(extension_loaded('openssl')?'SI':'NO'),"
";
try {
  $db=Conexion::obtenerConexion();
  echo "MySQL NOW(): ",$db->query('SELECT NOW()')->fetchColumn(),"
";
  echo "Dispositivos FCM registrados: ",$db->query('SELECT COUNT(*) FROM hu07_dispositivos')->fetchColumn(),"
";
  $s=$db->query("SELECT canal,estado,COUNT(*) total FROM hu07_envios GROUP BY canal,estado ORDER BY canal,estado");
  foreach($s->fetchAll(PDO::FETCH_ASSOC) as $x) echo 'Cola ', $x['canal'], ' / ', $x['estado'], ': ', $x['total'], "
";
} catch(Throwable $e) {
  fwrite(STDERR,"No se pudo consultar la base de datos (revisa conexion).
");
  exit(1);
}
