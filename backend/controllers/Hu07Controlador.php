<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/repos/Hu07Repositorio.php';

final class Hu07Controlador
{
    public function __construct(private Hu07Repositorio $repo,private array $usuario,private string $ruta,private string $metodo) {}

    private function responder(array $contenido,int $estado=200): never
    {
        http_response_code($estado);
        echo json_encode($contenido,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    private function administrador(): void
    {
        if(($this->usuario['tipoUsuario']??'')!=='administrador')
            $this->responder(['mensaje'=>'Solo administración puede realizar esta acción.'],403);
    }
    private function residente(): void
    {
        if(!in_array($this->usuario['tipoUsuario']??'', ['inquilino','propietario'],true))
            $this->responder(['mensaje'=>'Esta sección es para residentes.'],403);
    }
    private function json(): array
    {
        $origen=$_SERVER['HTTP_ORIGIN']??'';
        $host=$_SERVER['HTTP_HOST']??'';
        $mismoOrigen=$origen!=='' && parse_url($origen,PHP_URL_HOST)===explode(':',$host)[0]
            && parse_url($origen,PHP_URL_PORT)===(str_contains($host,':')?(int)explode(':',$host)[1]:null);
        // Los métodos web que usan cookies exigen Origin confiable; Android con Bearer no envía Origin.
        if(($this->usuario['tipoUsuario']??'')==='administrador' &&
            !in_array($origen,['http://localhost:5173','http://127.0.0.1:5173'],true) && !$mismoOrigen)
            $this->responder(['mensaje'=>'Origen web no autorizado.'],403);
        $tipo=strtolower($_SERVER['CONTENT_TYPE']??'');
        if(!preg_match('~^application/json(?:\s*;|\s*$)~',$tipo))
            $this->responder(['mensaje'=>'Se requiere JSON.'],415);
        $texto=file_get_contents('php://input');
        if($texto===false||strlen($texto)>35000) $this->responder(['mensaje'=>'Solicitud demasiado grande.'],413);
        try{$datos=json_decode($texto,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){$this->responder(['mensaje'=>'JSON inválido.'],422);}
        if(!is_array($datos))$this->responder(['mensaje'=>'Objeto JSON requerido.'],422);
        return $datos;
    }
    private function entero(mixed $dato): int
    {
        if(!is_int($dato)||$dato<1)$this->responder(['mensaje'=>'Identificador inválido.'],422);
        return $dato;
    }
    private function texto(mixed $dato,int $max,int $min=1): string
    {
        if(!is_string($dato))$this->responder(['mensaje'=>'Campo de texto inválido.'],422);
        $dato=trim($dato);
        if((function_exists('mb_strlen')?mb_strlen($dato):strlen($dato))>$max||(function_exists('mb_strlen')?mb_strlen($dato):strlen($dato))<$min)$this->responder(['mensaje'=>'Revisa la longitud del texto.'],422);
        return $dato;
    }
    private function fecha(mixed $dato): string
    {
        if(!is_string($dato)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$dato))
            $this->responder(['mensaje'=>'Fecha inválida. Usa AAAA-MM-DDTHH:mm.'],422);
        $obj=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$dato);
        if(!$obj||$obj->format('Y-m-d\TH:i')!==$dato)$this->responder(['mensaje'=>'Fecha inexistente.'],422);
        return $obj->format('Y-m-d H:i:s');
    }
    private function htmlSeguro(string $html): string
    {
        if(strlen($html)>20000)$this->responder(['mensaje'=>'El comunicado es demasiado largo.'],422);
        // El servidor controla el HTML: no se aceptan atributos, scripts, URLs ni estilos.
        if(!class_exists('DOMDocument')) {
            // Alternativa segura en entornos sin extensión DOM (sin formato).
            return '<p>'.htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
        }
        $dom=new DOMDocument('1.0','UTF-8');
        $ant=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"?><html><body><div id="raiz">'.$html.'</div></body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();libxml_use_internal_errors($ant);
        $destino=new DOMDocument('1.0','UTF-8');$salida=$destino->createElement('div');
        $permitidos=['p','br','b','strong','em','i','u','ul','ol','li','div','blockquote'];
        $copiar=function(DOMNode $n,DOMNode $padre)use(&$copiar,$destino,$permitidos):void{
            if($n instanceof DOMText){$padre->appendChild($destino->createTextNode($n->nodeValue??''));return;}
            if(!$n instanceof DOMElement)return;
            $tag=strtolower($n->tagName);
            if(in_array($tag,['script','style','iframe','object','svg','math','form'],true))return;
            $p=in_array($tag,$permitidos,true)?$destino->createElement($tag):$padre;
            foreach($n->childNodes as $hijo)$copiar($hijo,$p);
            if($p!==$padre)$padre->appendChild($p);
        };
        $origen=$dom->getElementsByTagName('div')->item(0);
        if($origen)foreach($origen->childNodes as $hijo)$copiar($hijo,$salida);
        $out='';foreach($salida->childNodes as $n)$out.=$destino->saveHTML($n);
        return $out;
    }

    public function procesar(): never
    {
        $usuario=(int)$this->usuario['idUsuario'];
        try{
            if($this->ruta==='/api/hu07/inmuebles'&&$this->metodo==='GET') {
                $this->administrador();$this->responder(['inmuebles'=>$this->repo->inmuebles()]);
            }
            if($this->ruta==='/api/hu07/comunicados'&&$this->metodo==='GET') {
                $this->administrador();$this->responder(['comunicados'=>$this->repo->listarComunicados()]);
            }
            if($this->ruta==='/api/hu07/comunicados'&&$this->metodo==='POST') {
                $this->administrador();$d=$this->json();
                $idInmueble=$this->entero($d['idInmueble']??null);
                $titulo=$this->texto($d['titulo']??null,180,4);
                $contenido=$this->texto($d['contenido']??null,18000,4);
                $limpio=$this->htmlSeguro($contenido);
                if(trim(strip_tags($limpio))==='')$this->responder(['mensaje'=>'Escribe el contenido.'],422);
                $fecha=null;
                if(($d['programar']??false)===true){
                    $fecha=$this->fecha($d['fechaProgramada']??null);
                    if($fecha<=(new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s'))$this->responder(['mensaje'=>'Programa una fecha futura.'],422);
                }
                $id=$this->repo->crearComunicado($usuario,$idInmueble,$titulo,$limpio,$fecha);
                $this->responder(['id'=>$id,'mensaje'=>$fecha?'Comunicado programado.':'Comunicado publicado en el centro de avisos. Correo y push en cola de entrega.'],202);
            }
            if($this->ruta==='/api/hu07/avisos'&&$this->metodo==='GET') {
                $this->residente();$this->responder(['avisos'=>$this->repo->avisosUsuario($usuario)]);
            }
            if(preg_match('#^/api/hu07/avisos/([1-9][0-9]*)/leer$#D',$this->ruta,$m)&&$this->metodo==='POST') {
                $this->residente();$this->json();
                if(!$this->repo->marcarLeido($usuario,(int)$m[1]))$this->responder(['mensaje'=>'Aviso no disponible.'],404);
                $this->responder(['mensaje'=>'Aviso marcado como leído.']);
            }
            if($this->ruta==='/api/hu07/dispositivos'&&$this->metodo==='POST'){
                $this->residente();$d=$this->json();$token=$this->texto($d['token']??null,512,30);
                if(!preg_match('/^[A-Za-z0-9:_-]+$/D',$token))$this->responder(['mensaje'=>'Token push inválido.'],422);
                $this->repo->registrarToken($usuario,$token);$this->responder(['mensaje'=>'Dispositivo asociado.']);
            }
            if($this->ruta==='/api/hu07/dispositivos'&&$this->metodo==='DELETE'){
                $this->residente();$d=$this->json();$token=$this->texto($d['token']??null,512,30);
                $this->repo->eliminarToken($usuario,$token);$this->responder(['mensaje'=>'Dispositivo desvinculado.']);
            }
            if($this->ruta==='/api/hu07/votaciones'&&$this->metodo==='GET'){
                $this->administrador();$this->responder(['votaciones'=>$this->repo->votacionesAdmin()]);
            }
            if($this->ruta==='/api/hu07/votaciones'&&$this->metodo==='POST'){
                $this->administrador();$d=$this->json();
                $inmueble=$this->entero($d['idInmueble']??null);
                $titulo=$this->texto($d['titulo']??null,180,4);
                $descripcion=isset($d['descripcion'])?$this->texto($d['descripcion'],4000,0):'';
                $inicio=$this->fecha($d['inicio']??null);$cierre=$this->fecha($d['cierre']??null);
                if($cierre<=$inicio)$this->responder(['mensaje'=>'El cierre debe ser posterior al inicio.'],422);
                $opciones=$d['opciones']??null;
                if(!is_array($opciones)||count($opciones)<2||count($opciones)>10)$this->responder(['mensaje'=>'Usa de 2 a 10 opciones.'],422);
                $opciones=array_map(fn($o)=>$this->texto($o,180,1),$opciones);
                if(count(array_unique(array_map(static fn($o)=>function_exists('mb_strtolower')?mb_strtolower($o):strtolower($o),$opciones)))!==count($opciones))$this->responder(['mensaje'=>'No repitas opciones.'],422);
                $id=$this->repo->crearVotacion($usuario,$inmueble,$titulo,$descripcion,$inicio,$cierre,$opciones);
                $this->responder(['id'=>$id,'mensaje'=>'Votación configurada. Se abrirá según sus fechas.'],201);
            }
            if(preg_match('#^/api/hu07/votaciones/([1-9][0-9]*)/auditoria$#D',$this->ruta,$m)&&$this->metodo==='GET'){
                $this->administrador();$this->responder(['votos'=>$this->repo->auditoria((int)$m[1])]);
            }
            if($this->ruta==='/api/hu07/votaciones-movil'&&$this->metodo==='GET'){
                $this->residente();$this->responder(['votaciones'=>$this->repo->votacionesUsuario($usuario)]);
            }
            if(preg_match('#^/api/hu07/votaciones/([1-9][0-9]*)/votar$#D',$this->ruta,$m)&&$this->metodo==='POST'){
                $this->residente();$d=$this->json();
                $this->repo->votar($usuario,(int)$m[1],$this->entero($d['idUnidad']??null),$this->entero($d['idOpcion']??null));
                $this->responder(['mensaje'=>'Tu voto fue registrado. Solo se permite uno por unidad.'],201);
            }
            $this->responder(['mensaje'=>'Ruta no disponible.'],404);
        }catch(DomainException $e){$this->responder(['mensaje'=>$e->getMessage()],409);}
         catch(PDOException $e){if($e->getCode()==='23000')$this->responder(['mensaje'=>'Ubicación o referencia inválida.'],422);throw $e;}
    }
}
