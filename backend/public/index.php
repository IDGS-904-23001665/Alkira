<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/controllers/AutenticacionControlador.php';
require_once dirname(__DIR__) . '/controllers/InmueblesControlador.php';
require_once dirname(__DIR__) . '/controllers/AdeudosControlador.php';
require_once dirname(__DIR__) . '/controllers/HistorialContratosControlador.php';
require_once dirname(__DIR__) . '/controllers/IncidenciasControlador.php';
require_once dirname(__DIR__) . '/controllers/ResidentesControlador.php';
require_once dirname(__DIR__) . '/services/AutenticacionSolicitud.php';
require_once dirname(__DIR__) . '/controllers/AccesosQrControlador.php';
require_once dirname(__DIR__) . '/services/AutenticacionSolicitud.php';

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rutaHu01 = is_string($ruta) && (
    preg_match('#^/api/inmuebles(?:/[1-9][0-9]*(?:/unidades)?)?$#D', $ruta) === 1
    || preg_match('#^/api/unidades/[1-9][0-9]*(?:/asignaciones)?$#D', $ruta) === 1
    || preg_match('#^/api/asignaciones/[1-9][0-9]*/finalizar$#D', $ruta) === 1
    || $ruta === '/api/usuarios/buscar'
);

$rutaResidentes = $ruta === '/api/incidencias/residentes';

$rutaHu05 = is_string($ruta) && (
    $ruta === '/api/incidencias'
    || $ruta === '/api/incidencias/unidades'
    || preg_match('#^/api/incidencias/[1-9][0-9]*(?:/evidencias(?:/[1-9][0-9]*/archivo)?|/estado)?$#D', $ruta) === 1
);

$rutaHu10 = is_string($ruta) && (
    $ruta === '/api/accesos-qr'
    || $ruta === '/api/accesos-qr/bitacora'
    || $ruta === '/api/accesos-qr/validar'
    || preg_match('#^/api/accesos-qr/[1-9][0-9]*/revocar$#D', $ruta) === 1
);

try {
    if ($ruta === '/api/salud' && $metodo === 'GET') {
        echo json_encode(['estado' => 'disponible']);
        exit;
    }

    $rutasAutenticacion = [
        ['POST', '/api/auth/login'],
        ['GET',  '/api/auth/yo'],
        ['POST', '/api/auth/salir'],
        ['POST', '/api/auth/login-movil'],
        ['GET',  '/api/auth/yo-movil'],
    ];

    $rutaAdeudos = $ruta === '/api/adeudos/pendientes' && $metodo === 'GET';

    $rutaHistorialContratos = $metodo === 'GET' && is_string($ruta) && (
        $ruta === '/api/pagos/historial'
        || $ruta === '/api/contratos'
        || preg_match('#^/api/contratos/[1-9][0-9]*/documento$#D', $ruta) === 1
    );

    if (!$rutaHu01 && !$rutaResidentes && !$rutaHu05 && !$rutaHu10 && !$rutaAdeudos && !$rutaHistorialContratos && !in_array([$metodo, $ruta], $rutasAutenticacion, true)) {
        http_response_code(404);
        echo json_encode(['mensaje' => 'Ruta no encontrada.']);
        exit;
    }

    $rutaClave = dirname(__DIR__) . '/config/autenticacion.local.php';
    if (!is_file($rutaClave)) {
        throw new RuntimeException('Falta la configuración de autenticación.');
    }
    $configuracion = require $rutaClave;
    if (
        !is_array($configuracion) ||
        !is_string($configuracion['claveJwt'] ?? null) ||
        $configuracion['claveJwt'] === ''
    ) {
        throw new RuntimeException('La configuración de autenticación es inválida.');
    }

    $conexion = Conexion::obtenerConexion();
    $repositorio = new UsuariosRepositorio($conexion);
    $servicio = new AutenticacionServicio($repositorio, $configuracion['claveJwt']);
    $controlador = new AutenticacionControlador($servicio);

    if ($rutaHu01) {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión no válida o vencida.']);
            exit;
        }
        if (($usuario['tipoUsuario'] ?? null) !== 'administrador') {
            http_response_code(403);
            echo json_encode(['mensaje' => 'No tienes permisos para administrar inmuebles.']);
            exit;
        }
        $controladorInmuebles = new InmueblesControlador(
            new InmueblesServicio(new InmueblesRepositorio($conexion))
        );
        $controladorInmuebles->procesar($metodo, $ruta);
        exit;
    }

    if ($rutaResidentes) {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesion no valida o vencida.']);
            exit;
        }
        if ($usuario['tipoUsuario'] !== 'administrador') {
            http_response_code(403);
            echo json_encode(['mensaje' => 'Solo administracion puede consultar residentes.']);
            exit;
        }
        (new ResidentesControlador(new ResidentesServicio(new ResidentesRepositorio($conexion))))->procesar($metodo);
        exit;
    }

    if ($rutaHu05) {
        $usuario = AutenticacionSolicitud::usuario($servicio);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión no válida o vencida.']);
            exit;
        }
        $controladorIncidencias = new IncidenciasControlador(
            new IncidenciasServicio(new IncidenciasRepositorio($conexion)),
            $usuario
        );
        $controladorIncidencias->procesar($metodo, $ruta);
        exit;
    }

    if ($rutaHu10) {
        $usuario = AutenticacionSolicitud::usuario($servicio);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión no válida o vencida.']);
            exit;
        }
        $controladorAccesosQr = new AccesosQrControlador(
            new AccesosQrServicio(new AccesosQrRepositorio($conexion)),
            $usuario
        );
        $controladorAccesosQr->procesar($metodo, $ruta);
        exit;
    }

    if ($rutaAdeudos) {
        $controladorAdeudos = new AdeudosControlador(
            $servicio,
            new AdeudosServicio(new AdeudosRepositorio($conexion))
        );
        $controladorAdeudos->consultarPendientes();
        exit;
    }

    if ($rutaHistorialContratos) {
        $controladorHistorial = new HistorialContratosControlador(
            $servicio,
            new HistorialContratosServicio(
                new HistorialPagosRepositorio($conexion),
                new ContratosRepositorio($conexion)
            )
        );
        $controladorHistorial->procesar($ruta);
        exit;
    }

    if ($ruta === '/api/auth/login') {
        $controlador->ingresar();
    } elseif ($ruta === '/api/auth/yo') {
        $controlador->sesion();
    } elseif ($ruta === '/api/auth/salir') {
        $controlador->salir();
    } elseif ($ruta === '/api/auth/login-movil') {
        $controlador->ingresarMovil();
    } elseif ($ruta === '/api/auth/yo-movil') {
        $controlador->sesionMovil();
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['mensaje' => 'No fue posible procesar la solicitud.']);
}
