<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AutenticacionServicio.php';

final class KddMovilControlador
{
    public function __construct(
        private PDO $conexion,
        private AutenticacionServicio $autenticacion
    ) {
    }

    public function consultar(): void
    {
        $cabecera = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        $token = null;

        if (
            is_string($cabecera)
            && strlen($cabecera) <= 4200
            && preg_match(
                '/^Bearer ([A-Za-z0-9_.-]+)$/iD',
                trim($cabecera),
                $partes
            ) === 1
        ) {
            $token = $partes[1];
        }

        $usuario = $this->autenticacion->verificarTokenMovil($token);

        if ($usuario === null) {
            $this->responder(401, [
                'mensaje' => 'Sesión móvil inválida o vencida.'
            ]);
            return;
        }

        if (($usuario['tipoUsuario'] ?? null) !== 'inquilino') {
            $this->responder(403, [
                'mensaje' => 'Solo los inquilinos pueden consultar sus métricas personales.'
            ]);
            return;
        }

        $sql = <<<'SQL'
SELECT
    tipo_registro,

    COUNT(
        CASE
            WHEN valor_registro IS NOT NULL
                AND valor_registro >= 0
            THEN 1
        END
    ) AS registros_validos,

    COALESCE(
        SUM(
            CASE
                WHEN valor_registro >= 0
                THEN valor_registro
                ELSE 0
            END
        ),
        0
    ) AS total,

    COUNT(
        CASE
            WHEN valor_registro >= 1
            THEN 1
        END
    ) AS asistencias_validas

FROM registros_kdd_movil

WHERE id_usuario = :idUsuario

AND tipo_registro IN (
    'consumo_agua_m3',
    'consumo_electricidad_kwh',
    'asistencia_evento'
)

AND fecha_registro >=
    DATE_FORMAT(CURDATE(), '%Y-%m-01')

AND fecha_registro <
    DATE_FORMAT(
        DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
        '%Y-%m-01'
    )

GROUP BY tipo_registro
SQL;

        $consulta = $this->conexion->prepare($sql);

        $consulta->bindValue(
            ':idUsuario',
            (int) $usuario['idUsuario'],
            PDO::PARAM_INT
        );

        $consulta->execute();

        $resultado = [
            'periodo' => date('Y-m'),

            'consumoAgua' => [
                'disponible' => false,
                'total' => '0',
                'registros' => 0
            ],

            'consumoElectricidad' => [
                'disponible' => false,
                'total' => '0',
                'registros' => 0
            ],

            'asistenciaEventos' => [
                'disponible' => false,
                'cantidad' => 0
            ]
        ];

        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $tipo = (string) $fila['tipo_registro'];
            $registrosValidos = (int) $fila['registros_validos'];

            if ($tipo === 'consumo_agua_m3') {
                $resultado['consumoAgua'] = [
                    'disponible' => $registrosValidos > 0,
                    'total' => (string) $fila['total'],
                    'registros' => $registrosValidos
                ];
            }

            if ($tipo === 'consumo_electricidad_kwh') {
                $resultado['consumoElectricidad'] = [
                    'disponible' => $registrosValidos > 0,
                    'total' => (string) $fila['total'],
                    'registros' => $registrosValidos
                ];
            }

            if ($tipo === 'asistencia_evento') {
                $asistencias = (int) $fila['asistencias_validas'];

                $resultado['asistenciaEventos'] = [
                    'disponible' => $asistencias > 0,
                    'cantidad' => $asistencias
                ];
            }
        }

        $this->responder(200, $resultado);
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
