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