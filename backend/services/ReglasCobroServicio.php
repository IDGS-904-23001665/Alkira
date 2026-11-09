<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/repos/ReglasCobroRepositorio.php';

final class ReglasCobroServicio
{
    private const ALCANCES_PERMITIDOS = [
        'general',
        'departamento',
        'casa',
        'local',
        'oficina',
        'otro'
    ];

    private const TIPOS_RECARGO_PERMITIDOS = [
        'fijo',
        'porcentaje'
    ];

    public function __construct(
        private ReglasCobroRepositorio $repositorio
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarReglas(): array
    {
        return $this->repositorio->listarReglas();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarConceptosMantenimiento(): array
    {
        return $this->repositorio
            ->listarConceptosMantenimiento();
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerRegla(int $idReglaCobro): array
    {
        if ($idReglaCobro <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la regla no es válido.'
            );
        }

        $regla = $this->repositorio->obtenerRegla(
            $idReglaCobro
        );

        if ($regla === false) {
            throw new OutOfBoundsException(
                'La regla de cobro no existe.'
            );
        }

        return $regla;
    }

    /**
     * @return array<string, mixed>
     */
    public function crearRegla(array $datos): array
    {
        $regla = $this->validarDatos($datos, true);

        $this->verificarConflictos($regla);

        $idReglaCobro = $this->repositorio->crearRegla(
            $regla
        );

        return $this->obtenerRegla($idReglaCobro);
    }

    /**
     * @return array<string, mixed>
     */
    public function actualizarRegla(
        int $idReglaCobro,
        array $datos
    ): array {
        // Confirmar que existe antes de editar.
        $this->obtenerRegla($idReglaCobro);

        $regla = $this->validarDatos($datos, false);

        $this->verificarConflictos(
            $regla,
            $idReglaCobro
        );

        $this->repositorio->actualizarRegla(
            $idReglaCobro,
            $regla
        );

        return $this->obtenerRegla($idReglaCobro);
    }

    /**
     * Valida y normaliza los datos recibidos.
     *
     * @return array<string, mixed>
     */
    private function validarDatos(
        array $datos,
        bool $esRegistro
    ): array {
        $idConcepto = $this->validarIdentificador(
            $datos['idConcepto'] ?? null,
            'concepto'
        );

        if (
            !$this->repositorio
                ->existeConceptoMantenimiento($idConcepto)
        ) {
            throw new InvalidArgumentException(
                'Selecciona un concepto activo de mantenimiento mensual.'
            );
        }

        $alcance = $datos['alcance'] ?? null;

        if (
            !is_string($alcance)
            || !in_array(
                $alcance,
                self::ALCANCES_PERMITIDOS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El tipo de unidad seleccionado no es válido.'
            );
        }

        $montoCuota = $this->validarMonto(
            $datos['montoCuota'] ?? null,
            'cuota'
        );

        $tipoRecargo = $datos['tipoRecargo'] ?? null;

        if (
            !is_string($tipoRecargo)
            || !in_array(
                $tipoRecargo,
                self::TIPOS_RECARGO_PERMITIDOS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El tipo de recargo no es válido.'
            );
        }

        $valorRecargo = $this->validarMonto(
            $datos['valorRecargo'] ?? null,
            'recargo'
        );

        if (
            $tipoRecargo === 'porcentaje'
            && (float) $valorRecargo > 100
        ) {
            throw new InvalidArgumentException(
                'El recargo porcentual no puede superar el 100%.'
            );
        }

        $diaCorte = $this->validarDia(
            $datos['diaCorte'] ?? null,
            'corte'
        );

        $diaLimitePago = $this->validarDia(
            $datos['diaLimitePago'] ?? null,
            'límite de pago'
        );

        // Al registrar, una regla es activa por defecto.
        // Al editar, es obligatorio enviar su estado.
        $valorActivo = $esRegistro
            ? ($datos['activo'] ?? 1)
            : ($datos['activo'] ?? null);

        $activo = $this->validarActivo($valorActivo);

        return [
            'idConcepto' => $idConcepto,
            'alcance' => $alcance,
            'montoCuota' => $montoCuota,
            'tipoRecargo' => $tipoRecargo,
            'valorRecargo' => $valorRecargo,
            'diaCorte' => $diaCorte,
            'diaLimitePago' => $diaLimitePago,
            'activo' => $activo
        ];
    }

    /**
     * Evita configuraciones contradictorias.
     *
     * @param array<string, mixed> $regla
     */
    private function verificarConflictos(
        array $regla,
        ?int $idReglaExcluir = null
    ): void {
        // La tabla tiene una restricción única
        // por concepto y alcance, incluso si
        // la regla está inactiva.
        foreach ($this->repositorio->listarReglas() as $existente) {
            if (
                $idReglaExcluir !== null
                && (int) $existente['idReglaCobro']
                    === $idReglaExcluir
            ) {
                continue;
            }

            if (
                (int) $existente['idConcepto']
                    === $regla['idConcepto']
                && $existente['alcance']
                    === $regla['alcance']
            ) {
                throw new DomainException(
                    'Ya existe una regla para ese concepto y alcance. '
                    . 'Edita la regla existente.'
                );
            }
        }

        // Se permite conservar reglas inactivas,
        // pero nunca dos reglas activas de
        // mantenimiento para el mismo alcance.
        if (
            $regla['activo'] === 1
            && $this->repositorio
                ->existeReglaActivaParaAlcance(
                    $regla['alcance'],
                    $idReglaExcluir
                )
        ) {
            throw new DomainException(
                'Ya existe una regla activa para ese alcance. '
                . 'Desactívala antes de activar otra.'
            );
        }
    }

    private function validarIdentificador(
        mixed $valor,
        string $campo
    ): int {
        if (
            (!is_int($valor) && !is_string($valor))
            || (
                is_string($valor)
                && preg_match('/^[1-9][0-9]*$/D', $valor) !== 1
            )
        ) {
            throw new InvalidArgumentException(
                "El identificador del {$campo} no es válido."
            );
        }

        $identificador = filter_var(
            $valor,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1
                ]
            ]
        );

        if ($identificador === false) {
            throw new InvalidArgumentException(
                "El identificador del {$campo} no es válido."
            );
        }

        return $identificador;
    }

    private function validarMonto(
        mixed $valor,
        string $campo
    ): string {
        if (
            !is_string($valor)
            && !is_int($valor)
            && !is_float($valor)
        ) {
            throw new InvalidArgumentException(
                "El monto de {$campo} no es válido."
            );
        }

        $monto = trim((string) $valor);

        // DECIMAL(12,2):
        // Hasta 10 dígitos enteros y dos decimales.
        if (
            preg_match(
                '/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',
                $monto
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "El monto de {$campo} debe ser positivo o cero "
                . 'y contener como máximo dos decimales.'
            );
        }

        $partes = explode('.', $monto);

        $enteros = $partes[0];

        $decimales = str_pad(
            $partes[1] ?? '',
            2,
            '0',
            STR_PAD_RIGHT
        );

        return $enteros . '.' . $decimales;
    }

    private function validarDia(
        mixed $valor,
        string $campo
    ): int {
        if (
            (!is_int($valor) && !is_string($valor))
            || (
                is_string($valor)
                && preg_match('/^[0-9]{1,2}$/D', $valor) !== 1
            )
        ) {
            throw new InvalidArgumentException(
                "El día de {$campo} no es válido."
            );
        }

        $dia = (int) $valor;

        if ($dia < 1 || $dia > 31) {
            throw new InvalidArgumentException(
                "El día de {$campo} debe estar entre 1 y 31."
            );
        }

        return $dia;
    }

    private function validarActivo(mixed $valor): int
    {
        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        if (
            $valor === 0
            || $valor === 1
            || $valor === '0'
            || $valor === '1'
        ) {
            return (int) $valor;
        }

        throw new InvalidArgumentException(
            'El estado de la regla debe ser activo o inactivo.'
        );
    }
}
