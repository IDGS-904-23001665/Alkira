<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/KddServicio.php';
require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class KddControlador
{
    public function __construct(
        private KddServicio $servicio,
        private AutenticacionServicio $autenticacion
    ) {
    }

    public function indicadores(): void
    {
        $token = $_COOKIE['alkira_token'] ?? null;

        $usuario = $this->autenticacion->verificarToken(
            is_string($token) ? $token : null
        );

        if ($usuario === null) {
            http_response_code(401);

            echo json_encode([
                'mensaje' => 'Sesión no válida o vencida.'
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return;
        }

        if ($usuario['tipoUsuario'] !== 'administrador') {
            http_response_code(403);

            echo json_encode([
                'mensaje' => 'No tienes permiso para consultar KDD.'
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return;
        }

        $indicadores = $this->servicio->consultarIndicadores();

        http_response_code(200);

        echo json_encode(
            $indicadores,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
    

    public function inmuebles(): void
{
    if (!$this->accesoAdministrador()) {
        return;
    }

    $inmuebles = $this->servicio->consultarInmuebles();

    http_response_code(200);

    echo json_encode(
        ['inmuebles' => $inmuebles],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

public function financieroFiltrado(): void
{
    if (!$this->accesoAdministrador()) {
        return;
    }

    $valorInmueble = $_GET['idInmueble'] ?? null;
    $periodo = $_GET['periodo'] ?? null;
    $idInmueble = null;

    if ($valorInmueble !== null) {
        if (!is_string($valorInmueble)) {
            http_response_code(422);
            echo json_encode([
                'mensaje' => 'El inmueble no es válido.'
            ]);
            return;
        }

        $idInmueble = filter_var(
            $valorInmueble,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idInmueble === false) {
            http_response_code(422);
            echo json_encode([
                'mensaje' => 'El inmueble no es válido.'
            ]);
            return;
        }
    }

    if (
        $periodo !== null &&
        (
            !is_string($periodo) ||
            !preg_match(
                '/^\d{4}-(0[1-9]|1[0-2])$/',
                $periodo
            )
        )
    ) {
        http_response_code(422);
        echo json_encode([
            'mensaje' => 'El periodo no es válido.'
        ]);
        return;
    }

    $analisis = $this->servicio
    ->consultarAnalisisFinancieroFiltrado(
        $idInmueble,
        $periodo
    );

        http_response_code(200);

        echo json_encode(
        $analisis,
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

public function operativoFiltrado(): void
{
    if (!$this->accesoAdministrador()) {
        return;
    }

    $valorInmueble = $_GET['idInmueble'] ?? null;
    $periodo = $_GET['periodo'] ?? null;
    $idInmueble = null;

    if ($valorInmueble !== null) {
        if (!is_string($valorInmueble)) {
            http_response_code(422);

            echo json_encode([
                'mensaje' => 'El inmueble no es válido.'
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return;
        }

        $idInmueble = filter_var(
            $valorInmueble,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idInmueble === false) {
            http_response_code(422);

            echo json_encode([
                'mensaje' => 'El inmueble no es válido.'
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return;
        }
    }

    if (
        $periodo !== null &&
        (
            !is_string($periodo) ||
            !preg_match(
                '/^\d{4}-(0[1-9]|1[0-2])$/',
                $periodo
            )
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'mensaje' => 'El periodo no es válido.'
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return;
    }

    $operativo = $this->servicio
        ->consultarOperativoFiltrado(
            $idInmueble,
            $periodo
        );

    http_response_code(200);

    echo json_encode(
        ['operativo' => $operativo],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

public function reportes(): void
{
    if (!$this->accesoAdministrador()) {
        return;
    }

    $valorInmueble = $_GET['idInmueble'] ?? null;
    $periodo = $_GET['periodo'] ?? null;
    $idInmueble = null;

    if ($valorInmueble !== null) {
        if (!is_string($valorInmueble)) {
            http_response_code(422);

            echo json_encode([
                'mensaje' => 'El inmueble no es válido.'
            ]);

            return;
        }

        $idInmueble = filter_var(
            $valorInmueble,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idInmueble === false) {
            http_response_code(422);

            echo json_encode([
                'mensaje' => 'El inmueble no es válido.'
            ]);

            return;
        }
    }

    if (
        $periodo !== null &&
        (
            !is_string($periodo) ||
            !preg_match(
                '/^\d{4}-(0[1-9]|1[0-2])$/',
                $periodo
            )
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'mensaje' => 'El periodo no es válido.'
        ]);

        return;
    }

    $resultado = $this->servicio->consultarReportes(
        $idInmueble,
        $periodo
    );

    http_response_code(200);

    echo json_encode(
        $resultado,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

private function accesoAdministrador(): bool
{
    $token = $_COOKIE['alkira_token'] ?? null;

    $usuario = $this->autenticacion->verificarToken(
        is_string($token) ? $token : null
    );

    if ($usuario === null) {
        http_response_code(401);

        echo json_encode([
            'mensaje' => 'Sesión no válida o vencida.'
        ]);

        return false;
    }

    if (($usuario['tipoUsuario'] ?? null) !== 'administrador') {
        http_response_code(403);

        echo json_encode([
            'mensaje' => 'No tienes permiso para consultar KDD.'
        ]);

        return false;
    }

    return true;
}


}