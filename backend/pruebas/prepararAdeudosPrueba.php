<?php
declare(strict_types=1);

// Datos de demostración aislados para HU02.1. No ejecutar en producción.
if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la terminal.' . PHP_EOL);
}

require_once dirname(__DIR__) . '/config/Conexion.php';

const CORREO_INQUILINO_PRUEBA = 'inquilino@alkira.test';
const NOMBRE_INMUEBLE_PRUEBA = 'ALKIRA - Inmueble de prueba HU02';
const NUMERO_UNIDAD_PRUEBA = 'HU02-001';
const CONCEPTO_PRUEBA = 'Mantenimiento de prueba HU02';

function consultarId(PDO $conexion, string $sql, array $parametros): ?int
{
    $sentencia = $conexion->prepare($sql);
    $sentencia->execute($parametros);
    $valor = $sentencia->fetchColumn();
    return $valor === false ? null : (int) $valor;
}

try {
    $conexion = Conexion::obtenerConexion();
    $conexion->beginTransaction();

    $idInquilino = consultarId(
        $conexion,
        'SELECT id_usuario FROM usuarios
         WHERE correo = :correo AND tipo_usuario = :tipoUsuario
           AND estado = :estado AND activo = 1 LIMIT 1',
        ['correo' => CORREO_INQUILINO_PRUEBA, 'tipoUsuario' => 'inquilino', 'estado' => 'activo']
    );
    if ($idInquilino === null) {
        throw new RuntimeException('Primero crea el inquilino con prepararInquilino.php.');
    }

    $idInmueble = consultarId(
        $conexion,
        'SELECT id_inmueble FROM inmuebles WHERE nombre = :nombre LIMIT 1',
        ['nombre' => NOMBRE_INMUEBLE_PRUEBA]
    );
    if ($idInmueble === null) {
        $sentencia = $conexion->prepare(
            'INSERT INTO inmuebles (nombre, tipo, direccion, ciudad, descripcion)
             VALUES (:nombre, :tipo, :direccion, :ciudad, :descripcion)'
        );
        $sentencia->execute([
            'nombre' => NOMBRE_INMUEBLE_PRUEBA,
            'tipo' => 'condominio',
            'direccion' => 'Dirección simulada para pruebas locales',
            'ciudad' => 'Pruebas',
            'descripcion' => 'Registro temporal exclusivo para probar HU02.1',
        ]);
        $idInmueble = (int) $conexion->lastInsertId();
    }

    $idUnidad = consultarId(
        $conexion,
        'SELECT id_unidad FROM propiedades_unidades
         WHERE id_inmueble = :idInmueble AND numero_unidad = :numeroUnidad LIMIT 1',
        ['idInmueble' => $idInmueble, 'numeroUnidad' => NUMERO_UNIDAD_PRUEBA]
    );
    if ($idUnidad === null) {
        $sentencia = $conexion->prepare(
            'INSERT INTO propiedades_unidades (id_inmueble, numero_unidad, tipo_unidad, estado_unidad)
             VALUES (:idInmueble, :numeroUnidad, :tipoUnidad, :estadoUnidad)'
        );
        $sentencia->execute([
            'idInmueble' => $idInmueble,
            'numeroUnidad' => NUMERO_UNIDAD_PRUEBA,
            'tipoUnidad' => 'departamento',
            'estadoUnidad' => 'ocupada',
        ]);
        $idUnidad = (int) $conexion->lastInsertId();
    }

    $idRelacion = consultarId(
        $conexion,
        'SELECT id_usuario_propiedad FROM usuarios_propiedades
         WHERE id_usuario = :idUsuario AND id_unidad = :idUnidad AND relacion = :relacion LIMIT 1',
        ['idUsuario' => $idInquilino, 'idUnidad' => $idUnidad, 'relacion' => 'inquilino']
    );
    if ($idRelacion === null) {
        $sentencia = $conexion->prepare(
            'INSERT INTO usuarios_propiedades (id_usuario, id_unidad, relacion, fecha_inicio)
             VALUES (:idUsuario, :idUnidad, :relacion, :fechaInicio)'
        );
        $sentencia->execute([
            'idUsuario' => $idInquilino,
            'idUnidad' => $idUnidad,
            'relacion' => 'inquilino',
            'fechaInicio' => '2026-10-01',
        ]);
    }

    $idContrato = consultarId(
        $conexion,
        'SELECT id_contrato FROM contratos
         WHERE id_inquilino = :idInquilino AND id_unidad = :idUnidad
           AND estado_contrato = :estadoContrato LIMIT 1',
        ['idInquilino' => $idInquilino, 'idUnidad' => $idUnidad, 'estadoContrato' => 'vigente']
    );
    if ($idContrato === null) {
        $sentencia = $conexion->prepare(
            'INSERT INTO contratos
             (id_unidad, id_inquilino, fecha_inicio, fecha_fin, monto_renta, deposito_garantia, estado_contrato)
             VALUES (:idUnidad, :idInquilino, :fechaInicio, :fechaFin, :montoRenta, :deposito, :estado)'
        );
        $sentencia->execute([
            'idUnidad' => $idUnidad,
            'idInquilino' => $idInquilino,
            'fechaInicio' => '2026-10-01',
            'fechaFin' => '2027-09-30',
            'montoRenta' => '8500.00',
            'deposito' => '8500.00',
            'estado' => 'vigente',
        ]);
        $idContrato = (int) $conexion->lastInsertId();
    }

    $idConcepto = consultarId(
        $conexion,
        'SELECT id_concepto FROM conceptos_cobro WHERE nombre = :nombre LIMIT 1',
        ['nombre' => CONCEPTO_PRUEBA]
    );
    if ($idConcepto === null) {
        $sentencia = $conexion->prepare(
            'INSERT INTO conceptos_cobro (nombre, descripcion, tipo_concepto, monto_base, periodicidad)
             VALUES (:nombre, :descripcion, :tipoConcepto, :montoBase, :periodicidad)'
        );
        $sentencia->execute([
            'nombre' => CONCEPTO_PRUEBA,
            'descripcion' => 'Cuota para verificar el estado de cuenta móvil',
            'tipoConcepto' => 'mantenimiento',
            'montoBase' => '750.00',
            'periodicidad' => 'mensual',
        ]);
        $idConcepto = (int) $conexion->lastInsertId();
    }

    $periodosPrueba = [
        ['periodo' => '2026-10', 'fechaEmision' => '2026-10-01', 'fechaLimite' => '2026-10-10', 'recargo' => '50.00', 'estado' => 'vencido'],
        ['periodo' => '2026-11', 'fechaEmision' => '2026-11-01', 'fechaLimite' => '2026-11-10', 'recargo' => '0.00', 'estado' => 'pendiente'],
    ];

    foreach ($periodosPrueba as $periodo) {
        $idAdeudo = consultarId(
            $conexion,
            'SELECT id_estado_cuenta FROM estados_cuenta
             WHERE id_contrato = :idContrato AND id_concepto = :idConcepto
               AND periodo = :periodo LIMIT 1',
            ['idContrato' => $idContrato, 'idConcepto' => $idConcepto, 'periodo' => $periodo['periodo']]
        );
        if ($idAdeudo !== null) {
            continue;
        }
        $sentencia = $conexion->prepare(
            'INSERT INTO estados_cuenta
             (id_contrato, id_concepto, periodo, fecha_emision, fecha_limite_pago,
              monto_principal, recargo_mora, monto_pagado, estado)
             VALUES (:idContrato, :idConcepto, :periodo, :fechaEmision, :fechaLimite,
                     :principal, :recargo, :pagado, :estado)'
        );
        $sentencia->execute([
            'idContrato' => $idContrato,
            'idConcepto' => $idConcepto,
            'periodo' => $periodo['periodo'],
            'fechaEmision' => $periodo['fechaEmision'],
            'fechaLimite' => $periodo['fechaLimite'],
            'principal' => '750.00',
            'recargo' => $periodo['recargo'],
            'pagado' => '0.00',
            'estado' => $periodo['estado'],
        ]);
    }

    $conexion->commit();
    echo 'Datos de prueba HU02.1 preparados para ' . CORREO_INQUILINO_PRUEBA . PHP_EOL;
    echo 'Unidad: ' . NUMERO_UNIDAD_PRUEBA . PHP_EOL;
    echo 'Total esperado si no existían otros adeudos: 1550.00 MXN' . PHP_EOL;
} catch (Throwable $error) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    fwrite(STDERR, 'No se prepararon los datos: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
