<?php
declare(strict_types=1);

final class KddRepositorio
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerResumenFinanciero(): array
    {
        return $this->consultarResumen(
            'CALL sp_kdd_resumen_financiero()'
        );
    }

    public function obtenerResumenOperativo(): array
    {
        return $this->consultarResumen(
            'CALL sp_kdd_resumen_operativo()'
        );
    }

    public function obtenerTendenciaMensual(): array
    {
    $sentencia = $this->conexion->prepare(
        'CALL sp_kdd_tendencia_mensual()'
    );

    $sentencia->execute();

    try {
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $sentencia->closeCursor();
    }
    }

    public function obtenerInmuebles(): array
    {
    $sentencia = $this->conexion->prepare(
        'SELECT idInmueble, nombreInmueble, tipo, idInmueblePadre
         FROM view_kdd_inmuebles
         ORDER BY nombreInmueble ASC'
    );

    $sentencia->execute();

    try {
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $sentencia->closeCursor();
    }
    }

    public function obtenerResumenFinancieroFiltrado(
    ?int $idInmueble,
    ?string $periodo
    ): array {
    $sentencia = $this->conexion->prepare(
        'CALL sp_kdd_resumen_financiero_filtrado(
            :idInmueble,
            :periodo
        )'
    );

    $sentencia->bindValue(
        ':idInmueble',
        $idInmueble,
        $idInmueble === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_INT
    );

    $sentencia->bindValue(
        ':periodo',
        $periodo,
        $periodo === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_STR
    );

    $sentencia->execute();

    try {
        $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);

        if (!is_array($resultado)) {
            throw new UnexpectedValueException(
                'No se obtuvo el resumen financiero.'
            );
        }

        return $resultado;
    } finally {
        $sentencia->closeCursor();
    }
    }

    public function obtenerTendenciaMensualFiltrada(
    ?int $idInmueble,
    ?string $periodo
): array {
    $sentencia = $this->conexion->prepare(
        'CALL sp_kdd_tendencia_mensual_filtrada(
            :idInmueble,
            :periodo
        )'
    );

    $sentencia->bindValue(
        ':idInmueble',
        $idInmueble,
        $idInmueble === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_INT
    );

    $sentencia->bindValue(
        ':periodo',
        $periodo,
        $periodo === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_STR
    );

    $sentencia->execute();

    try {
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $sentencia->closeCursor();
    }
}

    public function obtenerResumenOperativoFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    $sentencia = $this->conexion->prepare(
        'CALL sp_kdd_resumen_operativo_filtrado(
            :idInmueble,
            :periodo
        )'
    );

    $sentencia->bindValue(
        ':idInmueble',
        $idInmueble,
        $idInmueble === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_INT
    );

    $sentencia->bindValue(
        ':periodo',
        $periodo,
        $periodo === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_STR
    );

    $sentencia->execute();

    try {
        $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);

        if (!is_array($resultado)) {
            throw new UnexpectedValueException(
                'No se obtuvo el resumen operativo KDD.'
            );
        }

        return $resultado;
    } finally {
        $sentencia->closeCursor();
    }
}

    public function obtenerHistorialPagosFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    return $this->consultarListaKdd(
        'sp_kdd_historial_pagos_filtrado',
        $idInmueble,
        $periodo
    );
}

public function obtenerHistorialOperativoFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    return $this->consultarListaKdd(
        'sp_kdd_historial_operativo_filtrado',
        $idInmueble,
        $periodo
    );
}

public function obtenerAlertasMorosidadFiltradas(
    ?int $idInmueble,
    ?string $periodo
): array {
    return $this->consultarListaKdd(
        'sp_kdd_alertas_morosidad_filtradas',
        $idInmueble,
        $periodo
    );
}


public function obtenerSeguimientoCarteraFiltrado(
    ?int $idInmueble,
    ?string $periodo
): array {
    $sql = <<<'SQL'
WITH RECURSIVE inmueblesRelacionados AS (
    SELECT id_inmueble
    FROM inmuebles
    WHERE id_inmueble = ?

    UNION ALL

    SELECT hijo.id_inmueble
    FROM inmuebles hijo
    INNER JOIN inmueblesRelacionados padre
        ON hijo.id_inmueble_padre = padre.id_inmueble
)
SELECT
    pu.id_unidad AS idUnidad,
    pu.numero_unidad AS unidad,

    COUNT(*) AS cuentasRegistradas,

    SUM(
        CASE WHEN vc.monto_pendiente > 0
        THEN 1 ELSE 0 END
    ) AS cuentasPendientes,

    SUM(
        CASE WHEN vc.monto_vencido > 0
        THEN 1 ELSE 0 END
    ) AS cuentasVencidas,

    COUNT(DISTINCT CASE
        WHEN vc.monto_vencido > 0
        THEN vc.periodo
        ELSE NULL
    END) AS periodosVencidos,

    SUM(
        CASE
            WHEN vc.monto_pendiente > 0
             AND vc.fecha_limite_pago >= CURDATE()
             AND vc.fecha_limite_pago <=
                 DATE_ADD(CURDATE(), INTERVAL 15 DAY)
            THEN 1 ELSE 0
        END
    ) AS cuentasProximasAVencer,

    ROUND(SUM(vc.monto_pendiente), 2) AS saldoPendiente,
    ROUND(SUM(vc.monto_vencido), 2) AS saldoVencido,

    COALESCE(MAX(
        CASE
            WHEN vc.monto_vencido > 0
            THEN DATEDIFF(CURDATE(), vc.fecha_limite_pago)
            ELSE NULL
        END
    ), 0) AS diasMaximoAtraso

FROM view_kdd_cartera vc

INNER JOIN estados_cuenta ec
    ON vc.id_estado_cuenta = ec.id_estado_cuenta

INNER JOIN contratos c
    ON ec.id_contrato = c.id_contrato

INNER JOIN propiedades_unidades pu
    ON c.id_unidad = pu.id_unidad

WHERE (
    ? IS NULL
    OR vc.id_inmueble IN (
        SELECT id_inmueble
        FROM inmueblesRelacionados
    )
)

AND (
    ? IS NULL
    OR vc.periodo = ?
)

GROUP BY pu.id_unidad, pu.numero_unidad

HAVING SUM(vc.monto_pendiente) > 0

ORDER BY saldoVencido DESC, saldoPendiente DESC

LIMIT 200
SQL;

    $sentencia = $this->conexion->prepare($sql);

    $valores = [
        $idInmueble,
        $idInmueble,
        $periodo,
        $periodo
    ];

    foreach ($valores as $indice => $valor) {
        $sentencia->bindValue(
            $indice + 1,
            $valor,
            $valor === null
                ? PDO::PARAM_NULL
                : (is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR)
        );
    }

    $sentencia->execute();

    try {
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $sentencia->closeCursor();
    }
}


private function consultarListaKdd(
    string $procedimiento,
    ?int $idInmueble,
    ?string $periodo
): array {
    $permitidos = [
        'sp_kdd_historial_pagos_filtrado',
        'sp_kdd_historial_operativo_filtrado',
        'sp_kdd_alertas_morosidad_filtradas'
    ];

    if (!in_array($procedimiento, $permitidos, true)) {
        throw new InvalidArgumentException(
            'Procedimiento KDD no permitido.'
        );
    }

    $sentencia = $this->conexion->prepare(
        "CALL {$procedimiento}(:idInmueble, :periodo)"
    );

    $sentencia->bindValue(
        ':idInmueble',
        $idInmueble,
        $idInmueble === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_INT
    );

    $sentencia->bindValue(
        ':periodo',
        $periodo,
        $periodo === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_STR
    );

    $sentencia->execute();

    try {
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $sentencia->closeCursor();
    }
}

    private function consultarResumen(string $consulta): array
    {
        $sentencia = $this->conexion->prepare($consulta);

        $sentencia->execute();

        try {
            $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);

            if (!is_array($resultado)) {
                throw new UnexpectedValueException(
                    'No se encontraron resultados KDD.'
                );
            }

            return $resultado;
        } finally {
            $sentencia->closeCursor();
        }
    }
}