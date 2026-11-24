<?php
declare(strict_types=1);

require_once dirname(__DIR__) .
    '/repos/ResidentesRepositorio.php';

final class ResidentesServicio
{
    public function __construct(
        private ResidentesRepositorio $repositorio
    ) {}

    public function listarActivos(): array
    {
        return $this->repositorio->listarActivos();
    }

    public function listar(): array
    {
        return $this->repositorio->listar();
    }

    public function buscar(int $id): array
    {
        $residente = $this->repositorio->buscar($id);

        if (!$residente) {
            throw new OutOfBoundsException(
                'Residente no encontrado.'
            );
        }

        return $residente;
    }

    public function crear(array $datos): int
    {
        return $this->repositorio->crear(
            $this->validar($datos)
        );
    }

    public function actualizar(int $id, array $datos): void
    {
        $actual = $this->buscar($id);

        $validados = $this->validar($datos, $id);

        // Evita alterar el tipo de cuenta de usuarios
        // vinculados con otros procesos del sistema.
        if ($actual['tipoUsuario'] !== $validados['tipoUsuario']) {
            throw new DomainException(
                'No se permite cambiar el tipo de usuario existente.'
            );
        }

        $this->repositorio->actualizar($id, $validados);
    }

    public function cambiarEstado(
        int $id,
        bool $activo
    ): void {
        $residente = $this->buscar($id);

        if ($residente['estado'] === 'bloqueado') {
            throw new DomainException(
                'La cuenta está bloqueada.'
            );
        }

        $this->repositorio->cambiarEstado($id, $activo);
    }

    private function validar(
        array $datos,
        ?int $id = null
    ): array {
        $nombres = $this->texto($datos, 'nombres');
        $apellidos = $this->texto($datos, 'apellidos');
        $correo = strtolower($this->texto($datos, 'correo'));
        $telefono = $this->texto($datos, 'telefono');
        $tipo = $this->texto($datos, 'tipoUsuario');
        $contrasena = $datos['contrasena'] ?? '';

        if ($nombres === '' || $apellidos === '') {
            throw new InvalidArgumentException(
                'Los nombres y apellidos son obligatorios.'
            );
        }

        if (
            mb_strlen($nombres) > 100 ||
            mb_strlen($apellidos) > 120
        ) {
            throw new InvalidArgumentException(
                'Los nombres o apellidos exceden la longitud permitida.'
            );
        }

        if (
            !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
            strlen($correo) > 150
        ) {
            throw new InvalidArgumentException(
                'Ingresa un correo electrónico válido.'
            );
        }

        if ($this->repositorio->correoExiste($correo, $id)) {
            throw new DomainException(
                'El correo electrónico ya está registrado.'
            );
        }

        if (
            $telefono !== '' &&
            !preg_match('/^[0-9+(). -]{7,25}$/', $telefono)
        ) {
            throw new InvalidArgumentException(
                'Ingresa un teléfono válido.'
            );
        }

        if (!in_array($tipo, ['inquilino', 'propietario'], true)) {
            throw new InvalidArgumentException(
                'Tipo de residente inválido.'
            );
        }

        if (
            !is_string($contrasena) ||
            (($id === null || $contrasena !== '') &&
             (strlen($contrasena) < 8 ||
              strlen($contrasena) > 72 ||
              str_contains($contrasena, "\0")))
        ) {
            throw new InvalidArgumentException(
                'La contraseña debe tener entre 8 y 72 bytes.'
            );
        }

        $resultado = [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $correo,
            'telefono' => $telefono !== '' ? $telefono : null,
            'tipoUsuario' => $tipo
        ];

        if ($contrasena !== '') {
            $resultado['contrasenaHash'] = password_hash(
                $contrasena,
                PASSWORD_DEFAULT
            );
        }

        return $resultado;
    }

    private function texto(array $datos, string $campo): string
    {
        $valor = $datos[$campo] ?? '';

        if (!is_string($valor)) {
            throw new InvalidArgumentException(
                'Formato de campo inválido: ' . $campo
            );
        }

        return trim($valor);
    }
}
