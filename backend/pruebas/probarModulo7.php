<?php
declare(strict_types=1);
// Diagnóstico no destructivo: comprueba que se aplicó modulo7.sql.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/config/Conexion.php';
try {
  $db=Conexion::obtenerConexion();
  foreach(['comunicados','votaciones','hu07_destinatarios','hu07_opciones','hu07_votos','hu07_dispositivos','hu07_envios'] as $tabla){
    $s=$db->query('SELECT COUNT(*) FROM `'.$tabla.'`');
    echo $tabla.': OK ('.$s->fetchColumn().' registros)'.PHP_EOL;
  }
  $s=$db->query("SHOW INDEX FROM hu07_votos WHERE Key_name='uq_hu07_un_voto_por_unidad'");
  $campos=$s->fetchAll(PDO::FETCH_ASSOC);
  if(count($campos)!==2)throw new RuntimeException('Falta el índice único un voto por unidad.');
  echo 'Restricción un voto por unidad: OK'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'HU07: '.$e->getMessage().PHP_EOL);exit(1);}
