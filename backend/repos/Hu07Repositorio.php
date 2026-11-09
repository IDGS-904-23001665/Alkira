<?php
declare(strict_types=1);

/** HU07: todas las consultas parametrizadas; los destinatarios se fijan al publicar. */
final class Hu07Repositorio
{
    public function __construct(private PDO $db) {}

    public function inmuebles(): array
    {
        return $this->db->query("SELECT id_inmueble AS id, nombre, tipo, id_inmueble_padre AS padre
            FROM inmuebles WHERE activo=1 AND estado_registro='activo' ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function unidadesDelUsuario(int $idUsuario, int $idInmueble): array
    {
        // Se consideran tanto relaciones de unidad como contratos vigentes.
        $sql = "WITH RECURSIVE ubicaciones AS (
          SELECT id_inmueble FROM inmuebles WHERE id_inmueble=:destino AND activo=1
          UNION ALL
          SELECT h.id_inmueble FROM inmuebles h JOIN ubicaciones p ON h.id_inmueble_padre=p.id_inmueble WHERE h.activo=1
        )
        SELECT DISTINCT u.id_unidad AS id, CONCAT(i.nombre, ' · ',u.numero_unidad) AS etiqueta
        FROM propiedades_unidades u JOIN inmuebles i ON i.id_inmueble=u.id_inmueble
        WHERE u.activo=1 AND u.id_inmueble IN (SELECT id_inmueble FROM ubicaciones)
        AND (
          EXISTS (SELECT 1 FROM usuarios_propiedades up WHERE up.id_unidad=u.id_unidad
                  AND up.id_usuario=:usuario1 AND up.fecha_inicio<=CURDATE()
                  AND (up.fecha_fin IS NULL OR up.fecha_fin>=CURDATE())
                  AND up.relacion IN ('inquilino','propietario','residente'))
          OR EXISTS (SELECT 1 FROM contratos c WHERE c.id_unidad=u.id_unidad
                  AND c.id_inquilino=:usuario2 AND c.estado_contrato='vigente'
                  AND c.fecha_inicio<=CURDATE() AND (c.fecha_fin IS NULL OR c.fecha_fin>=CURDATE()))
        ) ORDER BY etiqueta";
        $s=$this->db->prepare($sql);
        $s->execute(['destino'=>$idInmueble,'usuario1'=>$idUsuario,'usuario2'=>$idUsuario]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unidadesElegibles(int $idUsuario, int $idInmueble): array
    {
        return $this->unidadesDelUsuario($idUsuario, $idInmueble);
    }

    private function destinatarios(int $idInmueble): array
    {
        $sql="WITH RECURSIVE ubicaciones AS (
            SELECT id_inmueble FROM inmuebles WHERE id_inmueble=:destino AND activo=1
            UNION ALL
            SELECT i.id_inmueble FROM inmuebles i JOIN ubicaciones a ON i.id_inmueble_padre=a.id_inmueble WHERE i.activo=1
        )
        SELECT DISTINCT x.id_usuario FROM (
            SELECT up.id_usuario FROM usuarios_propiedades up
            JOIN propiedades_unidades un ON un.id_unidad=up.id_unidad
            WHERE un.activo=1 AND un.id_inmueble IN (SELECT id_inmueble FROM ubicaciones)
              AND up.fecha_inicio<=CURDATE() AND (up.fecha_fin IS NULL OR up.fecha_fin>=CURDATE())
              AND up.relacion IN ('inquilino','propietario','residente')
            UNION
            SELECT c.id_inquilino FROM contratos c
            JOIN propiedades_unidades un ON un.id_unidad=c.id_unidad
            WHERE un.activo=1 AND un.id_inmueble IN (SELECT id_inmueble FROM ubicaciones)
              AND c.estado_contrato='vigente' AND c.fecha_inicio<=CURDATE()
              AND (c.fecha_fin IS NULL OR c.fecha_fin>=CURDATE())
        ) x JOIN usuarios usuario ON usuario.id_usuario=x.id_usuario
        WHERE usuario.activo=1 AND usuario.estado='activo'
          AND usuario.tipo_usuario IN ('inquilino','propietario')";
        $s=$this->db->prepare($sql);$s->execute(['destino'=>$idInmueble]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }

    public function crearComunicado(int $autor, int $inmueble, string $titulo, string $contenido, ?string $fecha): int
    {
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("INSERT INTO comunicados
                (id_inmueble,id_usuario_autor,titulo,contenido,fecha_programada,estado)
                VALUES (:i,:a,:t,:c,:f,:e)");
            $s->execute(['i'=>$inmueble,'a'=>$autor,'t'=>$titulo,'c'=>$contenido,
                'f'=>$fecha,'e'=>$fecha===null?'publicado':'programado']);
            $id=(int)$this->db->lastInsertId();
            if ($fecha===null) $this->publicarDestinatarios($id,$inmueble);
            $this->db->commit();return $id;
        } catch(Throwable $e) {$this->db->rollBack();throw $e;}
    }

    private function publicarDestinatarios(int $idComunicado,int $idInmueble): void
    {
        $usuarios=$this->destinatarios($idInmueble);
        $recepcion=$this->db->prepare('INSERT IGNORE INTO hu07_destinatarios (id_comunicado,id_usuario) VALUES (?,?)');
        $envio=$this->db->prepare("INSERT IGNORE INTO hu07_envios (id_comunicado,id_usuario,canal) VALUES (?,?,?)");
        foreach($usuarios as $usuario) {
            $recepcion->execute([$idComunicado,$usuario]);
            $envio->execute([$idComunicado,$usuario,'correo']);
            $envio->execute([$idComunicado,$usuario,'push']);
        }
    }

    public function publicarProgramados(int $limite=30): int
    {
        $s=$this->db->query("SELECT id_comunicado,id_inmueble FROM comunicados
          WHERE estado='programado' AND fecha_programada<=NOW()
          ORDER BY fecha_programada LIMIT ".max(1,min(100,$limite)));
        $filas=$s->fetchAll(PDO::FETCH_ASSOC);$cuenta=0;
        foreach($filas as $fila) {
            $this->db->beginTransaction();
            try {
                $u=$this->db->prepare("UPDATE comunicados SET estado='publicado',fecha_publicacion=NOW()
                   WHERE id_comunicado=? AND estado='programado' AND fecha_programada<=NOW()");
                $u->execute([$fila['id_comunicado']]);
                if($u->rowCount()===1) {
                    $this->publicarDestinatarios((int)$fila['id_comunicado'],(int)$fila['id_inmueble']);
                    $cuenta++;
                }
                $this->db->commit();
            } catch(Throwable $e) {$this->db->rollBack();throw $e;}
        }
        return $cuenta;
    }

    public function listarComunicados(): array
    {
        $s=$this->db->query("SELECT c.id_comunicado AS id,c.titulo,c.contenido,c.estado,
            c.fecha_publicacion AS fechaPublicacion,c.fecha_programada AS fechaProgramada,
            i.nombre AS inmueble,c.id_inmueble AS idInmueble,
            (SELECT COUNT(*) FROM hu07_destinatarios d WHERE d.id_comunicado=c.id_comunicado) AS destinatarios,
            (SELECT COUNT(*) FROM hu07_destinatarios d WHERE d.id_comunicado=c.id_comunicado AND d.fecha_lectura IS NOT NULL) AS leidos,
            (SELECT COUNT(*) FROM hu07_envios e WHERE e.id_comunicado=c.id_comunicado AND e.estado='enviado' AND e.canal='correo') AS correosEnviados,
            (SELECT COUNT(*) FROM hu07_envios e WHERE e.id_comunicado=c.id_comunicado AND e.estado='enviado' AND e.canal='push') AS pushesEnviados
            FROM comunicados c JOIN inmuebles i ON i.id_inmueble=c.id_inmueble
            ORDER BY c.id_comunicado DESC LIMIT 100");
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function avisosUsuario(int $usuario): array
    {
        $s=$this->db->prepare("SELECT c.id_comunicado AS id,c.titulo,c.contenido,
            c.fecha_publicacion AS fecha,i.nombre AS inmueble,d.fecha_lectura AS fechaLectura
            FROM hu07_destinatarios d JOIN comunicados c ON c.id_comunicado=d.id_comunicado
            JOIN inmuebles i ON i.id_inmueble=c.id_inmueble
            WHERE d.id_usuario=? AND c.estado='publicado'
            ORDER BY c.fecha_publicacion DESC,c.id_comunicado DESC LIMIT 150");
        $s->execute([$usuario]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarLeido(int $usuario,int $comunicado): bool
    {
        $s=$this->db->prepare('UPDATE hu07_destinatarios SET fecha_lectura=COALESCE(fecha_lectura,NOW()) WHERE id_usuario=? AND id_comunicado=?');
        $s->execute([$usuario,$comunicado]);return $s->rowCount()>0 || $this->yaDestinatario($usuario,$comunicado);
    }
    private function yaDestinatario(int $usuario,int $comunicado): bool
    {
        $s=$this->db->prepare('SELECT 1 FROM hu07_destinatarios WHERE id_usuario=? AND id_comunicado=?');
        $s->execute([$usuario,$comunicado]);return (bool)$s->fetchColumn();
    }

    public function registrarToken(int $usuario,string $token): void
    {
        $s=$this->db->prepare("INSERT INTO hu07_dispositivos(id_usuario,token_fcm)
          VALUES (?,?) ON DUPLICATE KEY UPDATE id_usuario=VALUES(id_usuario), actualizado_en=NOW()");
        $s->execute([$usuario,$token]);
    }
    public function eliminarToken(int $usuario,string $token): void
    {
        $s=$this->db->prepare('DELETE FROM hu07_dispositivos WHERE id_usuario=? AND token_fcm=?');
        $s->execute([$usuario,$token]);
    }

    public function crearVotacion(int $autor,int $inmueble,string $titulo,string $descripcion,string $inicio,string $cierre,array $opciones): int
    {
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("INSERT INTO votaciones(id_inmueble,id_usuario_creador,titulo,descripcion,fecha_inicio,fecha_cierre,estado)
                VALUES (?,?,?,?,?,?,'activa')");
            $s->execute([$inmueble,$autor,$titulo,$descripcion,$inicio,$cierre]);
            $id=(int)$this->db->lastInsertId();
            $op=$this->db->prepare('INSERT INTO hu07_opciones(id_votacion,texto,posicion) VALUES (?,?,?)');
            foreach($opciones as $i=>$texto) $op->execute([$id,$texto,$i+1]);
            $this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function votacionesAdmin(): array
    {
        $s=$this->db->query("SELECT v.id_votacion AS id,v.id_inmueble AS idInmueble,i.nombre AS inmueble,v.titulo,v.descripcion,
            v.fecha_inicio AS inicio,v.fecha_cierre AS cierre,v.estado
            FROM votaciones v JOIN inmuebles i ON i.id_inmueble=v.id_inmueble
            WHERE EXISTS (SELECT 1 FROM hu07_opciones op WHERE op.id_votacion=v.id_votacion)
            ORDER BY v.id_votacion DESC LIMIT 100");
        $resultado=$s->fetchAll(PDO::FETCH_ASSOC);
        foreach($resultado as &$v) $v['resultados']=$this->resultados((int)$v['id']);
        return $resultado;
    }

    public function resultados(int $votacion): array
    {
        $s=$this->db->prepare("SELECT o.id_opcion AS id,o.texto,o.posicion,COUNT(v.id_voto) AS votos
            FROM hu07_opciones o LEFT JOIN hu07_votos v ON v.id_opcion=o.id_opcion
            WHERE o.id_votacion=? GROUP BY o.id_opcion,o.texto,o.posicion ORDER BY o.posicion");
        $s->execute([$votacion]);$opciones=$s->fetchAll(PDO::FETCH_ASSOC);
        $total=array_sum(array_map(fn($x)=>(int)$x['votos'],$opciones));
        return ['total'=>$total,'opciones'=>array_map(static function($x)use($total){
            return ['id'=>(int)$x['id'],'texto'=>$x['texto'],'votos'=>(int)$x['votos'],
              'porcentaje'=>$total ? round(100*(int)$x['votos']/$total,1):0];
        },$opciones)];
    }

    public function auditoria(int $votacion): array
    {
        $s=$this->db->prepare("SELECT v.id_unidad AS idUnidad,u.numero_unidad AS unidad,
            i.nombre AS inmueble,v.id_opcion AS idOpcion,v.fecha_voto AS fecha
            FROM hu07_votos v JOIN propiedades_unidades u ON u.id_unidad=v.id_unidad
            JOIN inmuebles i ON i.id_inmueble=u.id_inmueble
            WHERE v.id_votacion=? ORDER BY v.fecha_voto DESC LIMIT 500");
        $s->execute([$votacion]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function votacionesUsuario(int $usuario): array
    {
        $s=$this->db->query("SELECT v.id_votacion AS id,v.id_inmueble AS idInmueble,i.nombre AS inmueble,
            v.titulo,v.descripcion,v.fecha_inicio AS inicio,v.fecha_cierre AS cierre,v.estado
            FROM votaciones v JOIN inmuebles i ON i.id_inmueble=v.id_inmueble
            WHERE EXISTS (SELECT 1 FROM hu07_opciones o WHERE o.id_votacion=v.id_votacion)
              AND v.estado IN ('activa','cerrada')
            ORDER BY v.id_votacion DESC LIMIT 100");
        $salida=[];
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $v){
            $unidades=$this->unidadesDelUsuario($usuario,(int)$v['idInmueble']);
            if (!$unidades) continue;
            $v['unidades']=$unidades;
            $v['resultados']=$this->resultados((int)$v['id']);
            $v['misVotos']=$this->votosUnidad((int)$v['id'],array_column($unidades,'id'));
            $salida[]=$v;
        }
        return $salida;
    }
    private function votosUnidad(int $id,array $unidades): array
    {
        if (!$unidades) return [];
        $s=$this->db->prepare('SELECT id_unidad AS idUnidad,id_opcion AS idOpcion FROM hu07_votos WHERE id_votacion=? AND id_unidad IN (' . implode(',',array_fill(0,count($unidades),'?')) . ')');
        $s->execute(array_merge([$id],$unidades));return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function votar(int $usuario,int $votacion,int $unidad,int $opcion): void
    {
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("SELECT id_inmueble,estado,fecha_inicio,fecha_cierre FROM votaciones WHERE id_votacion=? FOR UPDATE");
            $s->execute([$votacion]);$v=$s->fetch(PDO::FETCH_ASSOC);
            if(!$v) throw new DomainException('La votación no existe.');
            if($v['estado']!=='activa'||$v['fecha_inicio']>date('Y-m-d H:i:s')||$v['fecha_cierre']<=date('Y-m-d H:i:s'))
                throw new DomainException('La votación no está abierta.');
            $unidades=$this->unidadesDelUsuario($usuario,(int)$v['id_inmueble']);
            if(!in_array($unidad,array_map('intval',array_column($unidades,'id')),true))
                throw new DomainException('La unidad no corresponde a tu residencia.');
            $op=$this->db->prepare('SELECT 1 FROM hu07_opciones WHERE id_opcion=? AND id_votacion=?');
            $op->execute([$opcion,$votacion]);
            if(!$op->fetchColumn()) throw new DomainException('Opción de voto inválida.');
            $s=$this->db->prepare('INSERT INTO hu07_votos(id_votacion,id_opcion,id_unidad,id_usuario) VALUES (?,?,?,?)');
            try{$s->execute([$votacion,$opcion,$unidad,$usuario]);}
            catch(PDOException $e) {
                if($e->getCode()==='23000') throw new DomainException('Esta unidad ya emitió su voto.');
                throw $e;
            }
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
