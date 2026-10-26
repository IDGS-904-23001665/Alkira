<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/repos/InmueblesRepositorio.php';

final class InmueblesServicio
{
    public function __construct(private InmueblesRepositorio $repositorio) {}

    public function listar(): array { return $this->repositorio->listar(); }
    public function buscar(int $id): array|false { return $this->repositorio->buscar($id); }
    public function listarUnidades(int $id): array { return $this->repositorio->listarUnidades($id); }

    public function guardarInmueble(array $entrada, ?int $id = null): int
    {
        $datos = [
            'nombre' => $this->texto($entrada, 'nombre', 150),
            'tipo' => $this->opcion($entrada, 'tipo', ['condominio','torre','bloque','local_comercial','otro'], 'condominio'),
            'direccion' => $this->texto($entrada, 'direccion', 255),
            'ciudad' => $this->texto($entrada, 'ciudad', 100),
            'estadoGeografico' => $this->opcional($entrada, 'estadoGeografico', 100),
            'codigoPostal' => $this->opcional($entrada, 'codigoPostal', 10),
            'descripcion' => $this->opcional($entrada, 'descripcion', 10000),
            'idInmueblePadre' => $this->enteroOpcional($entrada, 'idInmueblePadre'),
        ];
        if ($datos['idInmueblePadre'] !== null) {
            $padre = $this->repositorio->buscar($datos['idInmueblePadre']);
            if (!$padre || $datos['idInmueblePadre'] === $id) {
                throw new InvalidArgumentException('El inmueble padre no es válido.');
            }
            // Evita ciclos en una estructura que ya existe.
            $actual = $padre;
            $visitados = [];
            while ($actual && $actual['idInmueblePadre'] !== null) {
                $siguiente = (int) $actual['idInmueblePadre'];
                if ($siguiente === $id || isset($visitados[$siguiente])) {
                    throw new InvalidArgumentException('La jerarquía de inmuebles contiene un ciclo.');
                }
                $visitados[$siguiente] = true;
                $actual = $this->repositorio->buscar($siguiente);
            }
        }
        if ($id !== null) {
            if (!$this->repositorio->actualizar($id, $datos)) {
                throw new OutOfBoundsException('Inmueble no encontrado.');
            }
            return $id;
        }
        return $this->repositorio->crear($datos);
    }

    public function crearUnidad(array $entrada, int $idInmueble): int
    {
        if (!$this->repositorio->buscar($idInmueble)) {
            throw new OutOfBoundsException('Inmueble no encontrado.');
        }
        $superficie = $entrada['superficieM2'] ?? null;
        if ($superficie !== null && (!is_numeric($superficie) || (float)$superficie < 0 || (float)$superficie > 99999999)) {
            throw new InvalidArgumentException('La superficie no es válida.');
        }
        $cajones = $entrada['cajonesEstacionamiento'] ?? 0;
        if (!is_int($cajones) || $cajones < 0 || $cajones > 255) {
            throw new InvalidArgumentException('Los cajones de estacionamiento no son válidos.');
        }
        if (isset($entrada['tieneBodega']) && !is_bool($entrada['tieneBodega'])) {
            throw new InvalidArgumentException('tieneBodega debe ser booleano.');
        }
        $numero = $this->texto($entrada, 'numeroUnidad', 30);
        if ($this->repositorio->existeNumeroUnidad($idInmueble, $numero)) {
            throw new DomainException('Ya existe una unidad activa con ese número en el inmueble.');
        }
        return $this->repositorio->crearUnidad([
            'idInmueble' => $idInmueble,
            'numeroUnidad' => $numero,
            'tipoUnidad' => $this->opcion($entrada, 'tipoUnidad', ['departamento','casa','local','oficina','otro'], 'departamento'),
            'superficieM2' => $superficie,
            'cajonesEstacionamiento' => $cajones,
            'tieneBodega' => ($entrada['tieneBodega'] ?? false) ? 1 : 0,
            'serviciosIncluidos' => $this->opcional($entrada, 'serviciosIncluidos', 10000),
            'descripcion' => $this->opcional($entrada, 'descripcion', 10000),
            'estadoUnidad' => $this->opcion($entrada, 'estadoUnidad', ['disponible','ocupada','mantenimiento','inactiva'], 'disponible'),
        ]);
    }

    public function buscarUnidad(int $id): array|false { return $this->repositorio->buscarUnidad($id); }
    public function buscarUsuarios(string $termino): array
    {
        $termino = trim($termino);
        if (mb_strlen($termino) < 2 || mb_strlen($termino) > 100) throw new InvalidArgumentException('La búsqueda debe tener entre 2 y 100 caracteres.');
        return $this->repositorio->buscarUsuarios($termino);
    }
    public function listarAsignaciones(int $idUnidad): array
    {
        if (!$this->repositorio->buscarUnidad($idUnidad)) throw new OutOfBoundsException('Unidad no encontrada.');
        return $this->repositorio->listarAsignaciones($idUnidad);
    }
    public function asignarUsuario(int $idUnidad, array $entrada): int
    {
        if (!$this->repositorio->buscarUnidad($idUnidad)) throw new OutOfBoundsException('Unidad no encontrada.');
        $idUsuario = $entrada['idUsuario'] ?? null;
        if (!is_int($idUsuario) || $idUsuario < 1) throw new InvalidArgumentException('idUsuario debe ser entero positivo.');
        $usuario = $this->repositorio->buscarUsuario($idUsuario);
        if (!$usuario) throw new InvalidArgumentException('Usuario no encontrado o inactivo.');
        $relacion = $this->opcion($entrada,'relacion',['propietario','inquilino','residente'],'residente');
        if ($relacion === 'propietario' && $usuario['tipoUsuario'] !== 'propietario') throw new InvalidArgumentException('El usuario no tiene rol de propietario.');
        if ($relacion === 'inquilino' && $usuario['tipoUsuario'] !== 'inquilino') throw new InvalidArgumentException('El usuario no tiene rol de inquilino.');
        $inicio = $this->fecha($entrada['fechaInicio'] ?? null);
        $fin = isset($entrada['fechaFin']) ? $this->fecha($entrada['fechaFin']) : null;
        if ($fin !== null && $fin < $inicio) throw new InvalidArgumentException('fechaFin debe ser posterior o igual a fechaInicio.');
        $principal = $entrada['esPrincipal'] ?? true;
        if (!is_bool($principal)) throw new InvalidArgumentException('esPrincipal debe ser booleano.');
        if ($this->repositorio->tieneAsignacionSolapada($idUsuario,$idUnidad,$inicio,$fin)) throw new DomainException('Ya existe una asignación de este usuario en fechas superpuestas.');
        return $this->repositorio->crearAsignacion(['idUsuario'=>$idUsuario,'idUnidad'=>$idUnidad,'relacion'=>$relacion,'fechaInicio'=>$inicio,'fechaFin'=>$fin,'esPrincipal'=>$principal?1:0]);
    }
    public function finalizarAsignacion(int $id, array $entrada): void
    {
        $asignacion = $this->repositorio->buscarAsignacion($id);
        if (!$asignacion) throw new OutOfBoundsException('Asignación no encontrada.');
        $fechaFin = $this->fecha($entrada['fechaFin'] ?? null);
        if ($fechaFin < $asignacion['fechaInicio']) throw new InvalidArgumentException('fechaFin no puede ser anterior al inicio.');
        if ($asignacion['fechaFin'] !== null && $fechaFin > $asignacion['fechaFin']) throw new DomainException('No se puede prolongar una asignación ya finalizada.');
        if (!$this->repositorio->cerrarAsignacion($id,$fechaFin)) throw new DomainException('La asignación ya está finalizada para esa fecha.');
    }
    public function actualizarUnidad(int $id, array $entrada): array
    {
        $actual = $this->repositorio->buscarUnidad($id);
        if (!$actual) throw new OutOfBoundsException('Unidad no encontrada.');
        $entrada = array_replace($actual, $entrada);
        $superficie = $entrada['superficieM2'];
        if ($superficie !== null && (!is_numeric($superficie) || (float)$superficie < 0 || (float)$superficie > 99999999)) throw new InvalidArgumentException('Superficie inválida.');
        $cajones = $entrada['cajonesEstacionamiento'];
        if (!is_numeric($cajones) || (int)$cajones != $cajones || $cajones < 0 || $cajones > 255) throw new InvalidArgumentException('Cajones inválidos.');
        $bodega = $entrada['tieneBodega'];
        if (!is_bool($bodega) && !in_array($bodega,[0,1,'0','1'],true)) throw new InvalidArgumentException('tieneBodega inválido.');
        $numero = $this->texto($entrada,'numeroUnidad',30);
        if ($this->repositorio->existeNumeroUnidad((int)$actual['idInmueble'],$numero,$id)) throw new DomainException('Número de unidad duplicado.');
        $datos=['numeroUnidad'=>$numero,'tipoUnidad'=>$this->opcion($entrada,'tipoUnidad',['departamento','casa','local','oficina','otro'],'departamento'), 'superficieM2'=>$superficie, 'cajonesEstacionamiento'=>(int)$cajones,'tieneBodega'=>$bodega?1:0,'serviciosIncluidos'=>$this->opcional($entrada,'serviciosIncluidos',10000),'descripcion'=>$this->opcional($entrada,'descripcion',10000),'estadoUnidad'=>$this->opcion($entrada,'estadoUnidad',['disponible','ocupada','mantenimiento','inactiva'],'disponible')];
        $this->repositorio->actualizarUnidad($id,$datos);
        return $this->repositorio->buscarUnidad($id);
    }
    public function desactivarUnidad(int $id): void
    {
        if (!$this->repositorio->buscarUnidad($id)) throw new OutOfBoundsException('Unidad no encontrada.');
        if ($this->repositorio->contarRelacionesVigentes($id) || $this->repositorio->contarContratosVigentes($id)) throw new DomainException('No se puede desactivar una unidad con ocupantes o contratos vigentes.');
        $this->repositorio->desactivarUnidad($id);
    }
    public function desactivarInmueble(int $id): void
    {
        if (!$this->repositorio->buscar($id)) throw new OutOfBoundsException('Inmueble no encontrado.');
        if ($this->repositorio->contarHijosActivos($id) || $this->repositorio->contarUnidadesActivas($id)) throw new DomainException('Desactiva antes las unidades y los inmuebles dependientes.');
        $this->repositorio->desactivarInmueble($id);
    }
    private function fecha(mixed $valor): string
    {
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$valor)) throw new InvalidArgumentException('Fecha inválida: utiliza AAAA-MM-DD.');
        $fecha=DateTimeImmutable::createFromFormat('!Y-m-d',$valor);
        if (!$fecha || $fecha->format('Y-m-d')!==$valor) throw new InvalidArgumentException('Fecha inválida.');
        return $valor;
    }

    private function texto(array $datos, string $campo, int $maximo): string
    {
        $valor = $datos[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === '' || mb_strlen(trim($valor)) > $maximo) {
            throw new InvalidArgumentException("El campo $campo es obligatorio o excede su longitud.");
        }
        return trim($valor);
    }
    private function opcional(array $datos, string $campo, int $maximo): ?string
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === null || $valor === '') return null;
        if (!is_string($valor) || mb_strlen($valor) > $maximo) throw new InvalidArgumentException("El campo $campo no es válido.");
        return trim($valor);
    }
    private function opcion(array $datos, string $campo, array $permitidas, string $defecto): string
    {
        $valor = $datos[$campo] ?? $defecto;
        if (!is_string($valor) || !in_array($valor, $permitidas, true)) throw new InvalidArgumentException("El campo $campo no es válido.");
        return $valor;
    }
    private function enteroOpcional(array $datos, string $campo): ?int
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === null) return null;
        if (!is_int($valor) || $valor <= 0) throw new InvalidArgumentException("El campo $campo no es válido.");
        return $valor;
    }
}
