<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/IncidenciasRepositorio.php';

final class IncidenciasServicio
{
    private const CATEGORIAS = ['plomeria', 'electricidad', 'estructura', 'limpieza', 'seguridad', 'otro'];
    private const URGENCIAS = ['baja', 'media', 'alta'];
    private const ESTADOS = ['reportada', 'en_revision', 'asignada', 'en_proceso', 'resuelta', 'cerrada'];
    private const TIPOS_ARCHIVO = ['JPG', 'PNG', 'MP4', 'PDF', 'otro'];

    public function __construct(private IncidenciasRepositorio $repositorio) {}

    public function listarTodas(): array
    {
        return $this->repositorio->listarTodas();
    }

    public function listarPorUsuario(int $idUsuario): array
    {
        if ($idUsuario < 1) {
            throw new InvalidArgumentException('El identificador de usuario no es válido.');
        }
        return $this->repositorio->listarPorUsuario($idUsuario);
    }

    public function buscar(int $idIncidencia): array
    {
        $incidencia = $this->repositorio->buscar($idIncidencia);
        if (!$incidencia) {
            throw new OutOfBoundsException('Incidencia no encontrada.');
        }
        $incidencia['evidencias'] = $this->repositorio->listarEvidencias($idIncidencia);
        return $incidencia;
    }

    public function listarEvidencias(int $idIncidencia): array
    {
        if (!$this->repositorio->buscar($idIncidencia)) {
            throw new OutOfBoundsException('Incidencia no encontrada.');
        }
        return $this->repositorio->listarEvidencias($idIncidencia);
    }

    public function listarResidentesActivos(): array
    {
        return $this->repositorio->listarResidentesActivos();
    }

    public function crear(array $entrada, int $idUsuarioSesion): array
    {
        $idUsuario = isset($entrada['idUsuarioReporta']) && is_numeric($entrada['idUsuarioReporta'])
            ? (int) $entrada['idUsuarioReporta']
            : $idUsuarioSesion;

        if ($idUsuario < 1) {
            throw new InvalidArgumentException('El usuario que reporta es obligatorio.');
        }

        $titulo = $this->texto($entrada, 'titulo', 150, 'El título de la incidencia es obligatorio.');
        $descripcion = $this->texto($entrada, 'descripcion', 5000, 'La descripción de la incidencia es obligatoria.');
        $categoria = $this->opcion($entrada, 'categoria', self::CATEGORIAS, 'otro');
        $urgencia = $this->opcion($entrada, 'urgencia', self::URGENCIAS, 'media');

        $idInmueble = $this->enteroOpcional($entrada, 'idInmueble');
        $idUnidad = $this->enteroOpcional($entrada, 'idUnidad');
        $this->repositorio->validarUbicacionResidente($idUsuario, $idUnidad, $idInmueble);

        $rutaArchivo = $this->opcional($entrada, 'rutaArchivo', 500);
        $tipoArchivo = $rutaArchivo !== null
            ? $this->opcion($entrada, 'tipoArchivo', self::TIPOS_ARCHIVO, 'JPG')
            : null;
        $descripcionEvidencia = $this->opcional($entrada, 'descripcionEvidencia', 255);

        $idIncidencia = $this->repositorio->crear([
            'idUsuario' => $idUsuario,
            'idInmueble' => $idInmueble,
            'idUnidad' => $idUnidad,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'categoria' => $categoria,
            'urgencia' => $urgencia,
            'rutaArchivo' => $rutaArchivo,
            'tipoArchivo' => $tipoArchivo,
            'descripcionEvidencia' => $descripcionEvidencia,
        ]);

        return $this->buscar($idIncidencia);
    }

    public function agregarEvidencia(int $idIncidencia, array $entrada): array
    {
        if (!$this->repositorio->buscar($idIncidencia)) {
            throw new OutOfBoundsException('Incidencia no encontrada.');
        }

        $rutaArchivo = $this->texto($entrada, 'rutaArchivo', 500, 'La ruta o URL del archivo de evidencia es obligatoria.');
        $tipoArchivo = $this->opcion($entrada, 'tipoArchivo', self::TIPOS_ARCHIVO, 'JPG');
        $descripcion = $this->opcional($entrada, 'descripcion', 255) ?? 'Evidencia multimedia';

        $this->repositorio->agregarEvidencia($idIncidencia, [
            'rutaArchivo' => $rutaArchivo,
            'tipoArchivo' => $tipoArchivo,
            'descripcion' => $descripcion,
        ]);

        return $this->repositorio->listarEvidencias($idIncidencia);
    }

    public function actualizarEstado(int $idIncidencia, array $entrada, int $idAdmin): array
    {
        $actual = $this->repositorio->buscar($idIncidencia);
        if (!$actual) {
            throw new OutOfBoundsException('Incidencia no encontrada.');
        }

        $categoria = isset($entrada['categoria'])
            ? $this->opcion($entrada, 'categoria', self::CATEGORIAS, (string) $actual['categoria'])
            : (string) $actual['categoria'];

        $urgencia = isset($entrada['urgencia'])
            ? $this->opcion($entrada, 'urgencia', self::URGENCIAS, (string) $actual['urgencia'])
            : (string) $actual['urgencia'];

        $estado = isset($entrada['estado'])
            ? $this->opcion($entrada, 'estado', self::ESTADOS, (string) $actual['estado'])
            : (string) $actual['estado'];

        $this->repositorio->actualizarEstado($idIncidencia, [
            'categoria' => $categoria,
            'urgencia' => $urgencia,
            'estado' => $estado,
            'idAdmin' => $idAdmin,
        ]);

        return $this->buscar($idIncidencia);
    }

    private function texto(array $entrada, string $campo, int $maximo, string $mensaje): string
    {
        $valor = trim((string) ($entrada[$campo] ?? ''));
        if ($valor === '' || mb_strlen($valor) > $maximo) {
            throw new InvalidArgumentException($mensaje);
        }
        return $valor;
    }

    private function opcional(array $entrada, string $campo, int $maximo): ?string
    {
        if (!isset($entrada[$campo]) || $entrada[$campo] === null) {
            return null;
        }
        $valor = trim((string) $entrada[$campo]);
        if ($valor === '') {
            return null;
        }
        if (mb_strlen($valor) > $maximo) {
            throw new InvalidArgumentException("El campo {$campo} excede la longitud permitida.");
        }
        return $valor;
    }

    private function opcion(array $entrada, string $campo, array $permitidos, string $porDefecto): string
    {
        $valor = isset($entrada[$campo]) ? trim((string) $entrada[$campo]) : $porDefecto;
        if (!in_array($valor, $permitidos, true)) {
            throw new InvalidArgumentException("El valor del campo {$campo} no es válido.");
        }
        return $valor;
    }

    private function enteroOpcional(array $entrada, string $campo): ?int
    {
        if (!isset($entrada[$campo]) || $entrada[$campo] === '' || $entrada[$campo] === null) {
            return null;
        }
        if (!is_numeric($entrada[$campo]) || (int) $entrada[$campo] < 1) {
            throw new InvalidArgumentException("El campo {$campo} debe ser un entero positivo.");
        }
        return (int) $entrada[$campo];
    }
}
