<?php
declare(strict_types=1);

/**
 * HU02.3 - Prueba segura de alta, edicion y validaciones de reglas.
 * Ejecutar: php backend/pruebas/probarReglasCobroTransaccion.php
 *
 * Todas las escrituras se realizan dentro de una transaccion que se revierte.
 * No se usa DDL ni se modifican los estados de cuenta existentes.
 */

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/repos/ReglasCobroRepositorio.php';
require_once dirname(__DIR__) . '/services/ReglasCobroServicio.php';

$conexion = Conexion::obtenerConexion();

if ($conexion->query('SELECT DATABASE()')->fetchColumn() !== 'alkira') {
    throw new RuntimeException('La prueba exige la base de datos local alkira.');
}

$configuracion = require dirname(__DIR__) . '/config/baseDatos.local.php';
if (!is_array($configuracion) || !in_array(
    $configuracion['servidor'] ?? null,
    ['localhost', '127.0.0.1', '::1'],
    true
)) {
    throw new RuntimeException('La prueba solo se permite en MySQL local.');
}

$repositorio = new ReglasCobroRepositorio($conexion);
$servicio = new ReglasCobroServicio($repositorio);
$reglasIniciales = $servicio->listarReglas();
$totalAdeudosInicial = (int) $conexion->query(
    'SELECT COUNT(*) FROM estados_cuenta'
)->fetchColumn();

$reglaGeneral = null;
foreach ($reglasIniciales as $regla) {
    if ($regla['alcance'] === 'general' && (int) $regla['activo'] === 1) {
        $reglaGeneral = $regla;
        break;
    }
}
if ($reglaGeneral === null) {
    throw new RuntimeException('No se encontro la regla general activa.');
}

foreach ($reglasIniciales as $regla) {
    if ($regla['alcance'] === 'departamento') {
        throw new RuntimeException(
            'Ya existe una regla para departamento: se cancela la prueba sin cambios.'
        );
    }
}

$datosRegla = [
    'idConcepto' => (int) $reglaGeneral['idConcepto'],
    'alcance' => 'departamento',
    'montoCuota' => '950.00',
    'tipoRecargo' => 'porcentaje',
    'valorRecargo' => '10.00',
    'diaCorte' => 15,
    'diaLimitePago' => 25,
    'activo' => 1,
];

// La aplicacion debe rechazar estos datos incluso sin formulario HTML.
$casosInvalidos = [
    'Cuota vacia' => ['montoCuota' => ''],
    'Cuota negativa' => ['montoCuota' => '-5.00'],
    'Cuota cero' => ['montoCuota' => '0.00'],
    'Porcentaje superior a 100' => ['valorRecargo' => '150.00'],
    'Dia de corte fuera de rango' => ['diaCorte' => 32],
    'Dia limite fuera de rango' => ['diaLimitePago' => 32],
];

$exito = false;
$conexion->beginTransaction();

try {
    echo "=== HU02.3 / Reglas de cobro con ROLLBACK ===\n";
    echo 'Reglas iniciales: ' . count($reglasIniciales) . "\n";
    echo 'Adeudos iniciales: ' . $totalAdeudosInicial . "\n";

    foreach ($casosInvalidos as $nombreCaso => $cambio) {
        try {
            $servicio->crearRegla(array_replace($datosRegla, $cambio));
            throw new RuntimeException(
                "FALLO: se acepto un valor invalido: {$nombreCaso}"
            );
        } catch (InvalidArgumentException $error) {
            echo "[CORRECTO] Rechazado: {$nombreCaso}.\n";
        }
    }

    $reglaCreada = $servicio->crearRegla($datosRegla);
    $idReglaPrueba = (int) $reglaCreada['idReglaCobro'];

    if (
        $reglaCreada['alcance'] !== 'departamento'
        || (string) $reglaCreada['montoCuota'] !== '950.00'
        || (string) $reglaCreada['valorRecargo'] !== '10.00'
        || $reglaCreada['tipoRecargo'] !== 'porcentaje'
    ) {
        throw new RuntimeException('La regla creada no coincide con los datos.');
    }
    echo "[CORRECTO] Alta temporal: departamento, $950.00, mora 10 %.\n";

    $datosActualizados = array_replace($datosRegla, [
        'montoCuota' => '975.00',
        'diaCorte' => 16,
    ]);
    $reglaEditada = $servicio->actualizarRegla($idReglaPrueba, $datosActualizados);

    if (
        (string) $reglaEditada['montoCuota'] !== '975.00'
        || (int) $reglaEditada['diaCorte'] !== 16
    ) {
        throw new RuntimeException('No se guardo la edicion temporal.');
    }
    echo "[CORRECTO] Edicion temporal: $975.00, corte 16.\n";

    try {
        $servicio->crearRegla($datosRegla);
        throw new RuntimeException('FALLO: se permitio una regla duplicada.');
    } catch (DomainException $error) {
        echo "[CORRECTO] Regla duplicada rechazada.\n";
    }

    if ((int) $conexion->query('SELECT COUNT(*) FROM estados_cuenta')->fetchColumn()
        !== $totalAdeudosInicial
    ) {
        throw new RuntimeException('Los estados de cuenta cambiaron durante la prueba.');
    }

    $exito = true;
} finally {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
}

$totalReglasFinal = count($servicio->listarReglas());
$totalAdeudosFinal = (int) $conexion->query(
    'SELECT COUNT(*) FROM estados_cuenta'
)->fetchColumn();

if (
    !$exito
    || $totalReglasFinal !== count($reglasIniciales)
    || $totalAdeudosFinal !== $totalAdeudosInicial
) {
    throw new RuntimeException('El estado final de MySQL no coincide con el inicial.');
}

echo 'Reglas finales: ' . $totalReglasFinal . "\n";
echo 'Adeudos finales: ' . $totalAdeudosFinal . "\n";
echo "RESULTADO: prueba superada; sin reglas ni adeudos permanentes.\n";
