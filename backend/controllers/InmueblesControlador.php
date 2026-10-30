<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/services/InmueblesServicio.php';

final class InmueblesControlador
{
    public function __construct(private InmueblesServicio $servicio) {}

    public function procesar(string $metodo, string $ruta): void
    {
        try {
            if ($metodo === 'GET' && $ruta === '/api/inmuebles') {
                $this->responder(200, ['inmuebles' => $this->servicio->listar()]);
                return;
            }
            if ($metodo === 'POST' && $ruta === '/api/inmuebles') {
                $id = $this->servicio->guardarInmueble($this->leerJson());
                $this->responder(201, ['inmueble' => $this->servicio->buscar($id)]);
                return;
            }
            if (preg_match('#^/api/inmuebles/([1-9][0-9]*)(/unidades)?$#D', $ruta, $coincidencias)) {
                $id = (int) $coincidencias[1];
                $unidades = isset($coincidencias[2]) && $coincidencias[2] === '/unidades';
                if ($unidades && $metodo === 'GET') {
                    if (!$this->servicio->buscar($id)) throw new OutOfBoundsException('Inmueble no encontrado.');
                    $this->responder(200, ['unidades' => $this->servicio->listarUnidades($id)]);
                    return;
                }
                if ($unidades && $metodo === 'POST') {
                    $idUnidad = $this->servicio->crearUnidad($this->leerJson(), $id);
                    $this->responder(201, ['idUnidad' => $idUnidad]);
                    return;
                }
                if (!$unidades && $metodo === 'GET') {
                    $inmueble = $this->servicio->buscar($id);
                    if (!$inmueble) throw new OutOfBoundsException('Inmueble no encontrado.');
                    $this->responder(200, ['inmueble' => $inmueble]);
                    return;
                }
                if (!$unidades && $metodo === 'PUT') {
                    $this->servicio->guardarInmueble($this->leerJson(), $id);
                    $this->responder(200, ['inmueble' => $this->servicio->buscar($id)]);
                    return;
                }
            }
            if ($metodo === 'GET' && $ruta === '/api/usuarios/buscar') {
                $this->responder(200, ['usuarios'=>$this->servicio->buscarUsuarios((string)($_GET['q'] ?? ''))]);
                return;
            }
            if (preg_match('#^/api/asignaciones/([1-9][0-9]*)/finalizar$#D',$ruta,$coincidencias) && $metodo === 'PATCH') {
                $this->servicio->finalizarAsignacion((int)$coincidencias[1],$this->leerJson());
                $this->responder(200,['mensaje'=>'Asignación finalizada.']);
                return;
            }
            if (preg_match('#^/api/unidades/([1-9][0-9]*)(/asignaciones)?$#D',$ruta,$coincidencias)) {
                $id=(int)$coincidencias[1];
                if (isset($coincidencias[2]) && $coincidencias[2]==='/asignaciones') {
                    if ($metodo==='GET') { $this->responder(200,['asignaciones'=>$this->servicio->listarAsignaciones($id)]); return; }
                    if ($metodo==='POST') { $this->responder(201,['idAsignacion'=>$this->servicio->asignarUsuario($id,$this->leerJson())]); return; }
                } else {
                    if ($metodo==='GET') {
                        $unidad=$this->servicio->buscarUnidad($id);
                        if (!$unidad) throw new OutOfBoundsException('Unidad no encontrada.');
                        $this->responder(200,['unidad'=>$unidad]); return;
                    }
                    if ($metodo==='PUT') { $this->responder(200,['unidad'=>$this->servicio->actualizarUnidad($id,$this->leerJson())]); return; }
                    if ($metodo==='DELETE') { $this->servicio->desactivarUnidad($id); $this->responder(200,['mensaje'=>'Unidad desactivada.']); return; }
                }
            }
            if ($metodo==='DELETE' && preg_match('#^/api/inmuebles/([1-9][0-9]*)$#D',$ruta,$coincidencias)) {
                $this->servicio->desactivarInmueble((int)$coincidencias[1]);
                $this->responder(200,['mensaje'=>'Inmueble desactivado.']);
                return;
            }
            $this->responder(404, ['mensaje' => 'Ruta no encontrada.']);
        } catch (DomainException $error) {
            $this->responder(409, ['mensaje' => $error->getMessage()]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, ['mensaje' => $error->getMessage()]);
        } catch (OutOfBoundsException $error) {
            $this->responder(404, ['mensaje' => $error->getMessage()]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->responder($error->getCode() === '23000' ? 409 : 500, ['mensaje' => 'No fue posible guardar los datos.']);
        }
    }

    private function leerJson(): array
    {
        $contenido = file_get_contents('php://input');
        if ($contenido === false || strlen($contenido) > 65536) throw new InvalidArgumentException('Cuerpo de solicitud inválido.');
        try { $datos = json_decode($contenido, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new InvalidArgumentException('JSON inválido.'); }
        if (!is_array($datos) || array_is_list($datos)) throw new InvalidArgumentException('Se espera un objeto JSON.');
        return $datos;
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
