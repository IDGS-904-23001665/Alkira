<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/controllers/AutenticacionControlador.php';
require_once dirname(__DIR__) . '/controllers/KddControlador.php';

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($ruta === '/api/salud' && $metodo === 'GET') {
        echo json_encode(['estado' => 'disponible']);
        exit;
    }

    if (!in_array([$metodo, $ruta], [
        ['POST', '/api/auth/login'],
        ['GET', '/api/auth/yo'],
        ['POST', '/api/auth/salir'],
        ['GET', '/api/kdd/indicadores'],
        ['GET', '/api/kdd/inmuebles'],
        ['GET', '/api/kdd/financiero'],
        ['GET', '/api/kdd/operativo'],
        ['GET', '/api/kdd/reportes'],
    ], true)) {
        http_response_code(404);
        echo json_encode(['mensaje' => 'Ruta no encontrada.']);
        exit;
    }

    $rutaClave = dirname(__DIR__)
        . '/config/autenticacion.local.php';

    if (!is_file($rutaClave)) {
        throw new RuntimeException(
            'Falta la configuración de autenticación.'
        );
    }

    $configuracion = require $rutaClave;

    if (
        !is_array($configuracion) ||
        !is_string($configuracion['claveJwt'] ?? null)
    ) {
        throw new RuntimeException(
            'La configuración de autenticación es inválida.'
        );
    }

    $conexion = Conexion::obtenerConexion();

    $repositorio = new UsuariosRepositorio($conexion);

    $servicio = new AutenticacionServicio(
        $repositorio,
        $configuracion['claveJwt']
    );

    $controlador = new AutenticacionControlador($servicio);

    // ============================================
    // RUTAS DEL MODULO KDD
    // ============================================

    if (in_array($ruta, [
        '/api/kdd/indicadores',
        '/api/kdd/inmuebles',
        '/api/kdd/financiero',
        '/api/kdd/operativo',
        '/api/kdd/reportes'
    ], true)) {
        $repositorioKdd = new KddRepositorio($conexion);

        $servicioKdd = new KddServicio($repositorioKdd);

        $controladorKdd = new KddControlador(
            $servicioKdd,
            $servicio
        );

        if ($ruta === '/api/kdd/indicadores') {
            $controladorKdd->indicadores();

        } elseif ($ruta === '/api/kdd/inmuebles') {
            $controladorKdd->inmuebles();

        } elseif ($ruta === '/api/kdd/financiero') {
            $controladorKdd->financieroFiltrado();

        } elseif ($ruta === '/api/kdd/operativo') {
            $controladorKdd->operativoFiltrado();

        } elseif ($ruta === '/api/kdd/reportes') {
            $controladorKdd->reportes();
        }

        exit;
    }

    // ============================================
    // RUTAS DE AUTENTICACION
    // ============================================

    if ($ruta === '/api/auth/login') {
        $controlador->ingresar();

    } elseif ($ruta === '/api/auth/yo') {
        $controlador->sesion();

    } elseif ($ruta === '/api/auth/salir') {
        $controlador->salir();
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    http_response_code(500);

    echo json_encode([
        'mensaje' => 'No fue posible procesar la solicitud.'
    ]);
}