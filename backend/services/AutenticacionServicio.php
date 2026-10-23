<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/UsuariosRepositorio.php';

final class AutenticacionServicio
{
    public function __construct(
        private UsuariosRepositorio $repositorio,
        private string $claveJwt
    ) {
        if (strlen($claveJwt) < 32) {
            throw new RuntimeException('La clave JWT debe tener al menos 32 caracteres.');
        }
    }

    public function ingresar(string $correo, string $contrasena): ?array
    {
        $usuario = $this->repositorio->buscarPorCorreo(strtolower(trim($correo)));

        if (
            !$usuario ||
            $usuario['tipo_usuario'] !== 'administrador' ||
            !password_verify($contrasena, $usuario['contrasena_hash'])
        ) {
            return null;
        }

        return $this->datosPublicos($usuario);
    }

    public function crearToken(int $idUsuario): string
    {
        $encabezado = $this->codificar(['typ' => 'JWT', 'alg' => 'HS256']);
        $ahora = time();
        $cuerpo = $this->codificar([
            'sub' => (string) $idUsuario,
            'iat' => $ahora,
            'exp' => $ahora + 3600,
        ]);
        $contenido = $encabezado . '.' . $cuerpo;
        $firma = $this->base64Url(hash_hmac('sha256', $contenido, $this->claveJwt, true));
        return $contenido . '.' . $firma;
    }

    public function verificarToken(?string $token): ?array
    {
        if (!$token || strlen($token) > 4096) {
            return null;
        }

        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        [$encabezado, $cuerpo, $firma] = $partes;
        $firmaValida = $this->base64Url(
            hash_hmac('sha256', $encabezado . '.' . $cuerpo, $this->claveJwt, true)
        );
        if (!hash_equals($firmaValida, $firma)) {
            return null;
        }

        $cabecera = $this->decodificar($encabezado);
        $datos = $this->decodificar($cuerpo);

        if (
            !is_array($cabecera) || ($cabecera['alg'] ?? '') !== 'HS256' ||
            ($cabecera['typ'] ?? '') !== 'JWT' || !is_array($datos) ||
            !isset($datos['sub'], $datos['exp'], $datos['iat']) ||
            !is_string($datos['sub']) || !ctype_digit($datos['sub']) ||
            !is_int($datos['exp']) || !is_int($datos['iat']) ||
            $datos['exp'] <= time() || $datos['iat'] > time() + 60 ||
            $datos['exp'] - $datos['iat'] > 3600
        ) {
            return null;
        }

        $idUsuario = (int) $datos['sub'];
        if ($idUsuario <= 0) {
            return null;
        }

        $usuario = $this->repositorio->buscarPorId($idUsuario);
        if (!$usuario || $usuario['tipo_usuario'] !== 'administrador') {
            return null;
        }

        return $this->datosPublicos($usuario);
    }

    private function datosPublicos(array $usuario): array
    {
        return [
            'idUsuario' => (int) $usuario['id_usuario'],
            'nombres' => $usuario['nombres'],
            'apellidos' => $usuario['apellidos'],
            'correo' => $usuario['correo'],
            'tipoUsuario' => $usuario['tipo_usuario'],
        ];
    }

    private function codificar(array $datos): string
    {
        return $this->base64Url(json_encode($datos, JSON_THROW_ON_ERROR));
    }

    private function base64Url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    private function decodificar(string $datos): ?array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $datos)) {
            return null;
        }
        $texto = base64_decode(strtr($datos, '-_', '+/'), true);
        if ($texto === false) {
            return null;
        }
        try {
            $resultado = json_decode($texto, true, 16, JSON_THROW_ON_ERROR);
            return is_array($resultado) ? $resultado : null;
        } catch (JsonException) {
            return null;
        }
    }
}
