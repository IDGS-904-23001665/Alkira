<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repos/AccesosQrRepositorio.php';

final class AccesosQrServicio
{
    public function __construct(private AccesosQrRepositorio $repositorio) {}

    public function listarTodosPases(): array
    {
        return $this->repositorio->listarTodosPases();
    }

    public function listarPorAnfitrion(int $idUsuario): array
    {
        if ($idUsuario < 1) {
            throw new InvalidArgumentException('El identificador de anfitrión no es válido.');
        }
        return $this->repositorio->listarPorAnfitrion($idUsuario);
    }

    public function listarBitacora(): array
    {
        return $this->repositorio->listarBitacora();
    }

    public function generarPase(array $entrada, int $idUsuarioSesion): array
    {
        $idAnfitrion = isset($entrada['idUsuarioAnfitrion']) && is_numeric($entrada['idUsuarioAnfitrion'])
            ? (int) $entrada['idUsuarioAnfitrion']
            : $idUsuarioSesion;

        if ($idAnfitrion < 1 || !$this->repositorio->anfitrionActivo($idAnfitrion)) {
            throw new InvalidArgumentException('El anfitrión debe ser un inquilino o propietario activo.');
        }

        $nombreVisitante = trim((string) ($entrada['nombreVisitante'] ?? ''));
        if ($nombreVisitante === '' || mb_strlen($nombreVisitante) > 150) {
            throw new InvalidArgumentException('El nombre del visitante es obligatorio (máx. 150 caracteres).');
        }

        $ahora = new DateTimeImmutable($this->repositorio->fechaActual());
        $fechaInicio = !empty($entrada['fechaInicioVigencia'])
            ? $this->normalizarFechaHora((string) $entrada['fechaInicioVigencia'])
            : $ahora->format('Y-m-d H:i:s');

        $horasVigencia = filter_var($entrada['horasVigencia'] ?? 6, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 168]]);
        if ($horasVigencia === false) {
            throw new InvalidArgumentException('La vigencia debe ser un número entero de 1 a 168 horas.');
        }

        $fechaFin = !empty($entrada['fechaFinVigencia'])
            ? $this->normalizarFechaHora((string) $entrada['fechaFinVigencia'])
            : (new DateTimeImmutable($fechaInicio))->modify("+{$horasVigencia} hours")->format('Y-m-d H:i:s');

        if ($fechaFin <= $fechaInicio || $fechaFin <= $ahora->format('Y-m-d H:i:s')) {
            throw new InvalidArgumentException('La vigencia debe terminar después del inicio y de la fecha actual.');
        }

        if ((new DateTimeImmutable($fechaFin))->getTimestamp() - (new DateTimeImmutable($fechaInicio))->getTimestamp() > 168 * 3600) {
            throw new InvalidArgumentException('La vigencia máxima es de 168 horas.');
        }

        $pase = $this->repositorio->generarPase([
            'idAnfitrion' => $idAnfitrion,
            'nombreVisitante' => $nombreVisitante,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
        ]);

        if (!$pase) {
            throw new RuntimeException('No se pudo generar el pase QR.');
        }

        return $pase;
    }

    public function revocarPase(int $idPase, int $idUsuarioSesion): array
    {
        if ($idPase < 1) {
            throw new InvalidArgumentException('Identificador de pase inválido.');
        }
        $resultado = $this->repositorio->revocarPase($idPase, $idUsuarioSesion);
        if (!$resultado) {
            throw new OutOfBoundsException('Pase QR no encontrado.');
        }
        return $resultado;
    }

    public function validarToken(array $entrada, int $idGuardiaSesion): array
    {
        $tokenQr = trim((string) ($entrada['tokenQr'] ?? ''));
        if ($tokenQr === '' || mb_strlen($tokenQr) > 255) {
            throw new InvalidArgumentException('Ingresa un código o token QR válido.');
        }

        $observaciones = isset($entrada['observaciones'])
            ? trim((string) $entrada['observaciones'])
            : null;

        if ($observaciones !== null && mb_strlen($observaciones) > 500) {
            throw new InvalidArgumentException('Las observaciones admiten como máximo 500 caracteres.');
        }
        $resultado = $this->repositorio->validarToken($tokenQr, $idGuardiaSesion, $observaciones);
        if (!$resultado) {
            throw new RuntimeException('No se obtuvo respuesta al validar el código QR.');
        }

        return $resultado;
    }

    private function normalizarFechaHora(string $valor): string
    {
        $limpio = str_replace('T', ' ', trim($valor));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $limpio)) {
            $limpio .= ':00';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $limpio);
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $limpio) {
            throw new InvalidArgumentException('Formato de fecha y hora inválido (use YYYY-MM-DD HH:MM).');
        }
        return $dt->format('Y-m-d H:i:s');
    }
}
