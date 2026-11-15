<?php
declare(strict_types=1);

/**
 * HU02.3: verifica autorización por rol en los controladores financieros.
 * No se conecta a MySQL y no crea ni modifica reglas ni adeudos.
 * No verifica el middleware JWT; eso se comprueba aparte por HTTP.
 *
 * Ejecutar desde la raíz del repositorio:
 * php backend/pruebas/probarPermisosHu023.php
 */

// Mantener los encabezados sin enviar durante las pruebas en CLI.
ob_start();

require_once dirname(__DIR__) . '/controllers/ReglasCobroControlador.php';
require_once dirname(__DIR__) . '/controllers/GeneracionAdeudosControlador.php';

/**
 * Crea una instancia sin acceso a datos: un rol no autorizado debe ser
 * rechazado antes de que el controlador invoque servicios financieros.
 */
$servicioReglas = (new ReflectionClass(ReglasCobroServicio::class))
    ->newInstanceWithoutConstructor();
$servicioGeneracion = (new ReflectionClass(GeneracionAdeudosServicio::class))
    ->newInstanceWithoutConstructor();

$controladorReglas = new ReglasCobroControlador($servicioReglas);
$controladorGeneracion = new GeneracionAdeudosControlador($servicioGeneracion);

$casos = [
    ['controlador' => $controladorReglas, 'metodo' => 'GET',
        'ruta' => '/api/administracion/reglas-cobro'],
    ['controlador' => $controladorReglas, 'metodo' => 'GET',
        'ruta' => '/api/administracion/reglas-cobro/conceptos'],
    ['controlador' => $controladorReglas, 'metodo' => 'GET',
        'ruta' => '/api/administracion/reglas-cobro/1'],
    ['controlador' => $controladorReglas, 'metodo' => 'POST',
        'ruta' => '/api/administracion/reglas-cobro'],
    ['controlador' => $controladorReglas, 'metodo' => 'PUT',
        'ruta' => '/api/administracion/reglas-cobro/1'],
    ['controlador' => $controladorGeneracion, 'metodo' => 'GET',
        'ruta' => '/api/administracion/adeudos/simulacion'],
    ['controlador' => $controladorGeneracion, 'metodo' => 'POST',
        'ruta' => '/api/administracion/adeudos/generar'],
];

$rolesNoAutorizados = ['guardia', 'inquilino', 'propietario', 'tecnico'];
$totalPruebas = 0;

echo "=== HU02.3 / Control de acceso por rol (sin escrituras) ===\n";

foreach ($rolesNoAutorizados as $rol) {
    foreach ($casos as $caso) {
        http_response_code(200);
        ob_start();

        try {
            $caso['controlador']->procesar(
                $caso['metodo'],
                $caso['ruta'],
                ['tipoUsuario' => $rol]
            );
            $respuesta = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $codigo = http_response_code();
        $datos = json_decode($respuesta, true);
        if (
            $codigo !== 403
            || !is_array($datos)
            || !isset($datos['mensaje'])
        ) {
            throw new RuntimeException(sprintf(
                'Fallo en rol %s, %s %s: HTTP %s, respuesta %s',
                $rol,
                $caso['metodo'],
                $caso['ruta'],
                (string) $codigo,
                $respuesta
            ));
        }

        $totalPruebas++;
    }

    echo "[CORRECTO] Rol {$rol}: 7 operaciones rechazadas con HTTP 403.\n";
}

echo "Pruebas correctas: {$totalPruebas}\n";
echo "RESULTADO: permisos de los controladores verificados, sin cambios en MySQL.\n";
ob_end_flush();
