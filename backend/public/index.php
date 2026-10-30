<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/controllers/AutenticacionControlador.php';
require_once dirname(__DIR__) . '/repos/ComprobantesRepositorio.php';
require_once dirname(__DIR__) . '/controllers/ComprobantesMovilControlador.php';
require_once dirname(__DIR__) . '/controllers/InmueblesControlador.php';
require_once dirname(__DIR__) . '/controllers/AdeudosControlador.php';
require_once dirname(__DIR__) . '/controllers/HistorialContratosControlador.php';
require_once dirname(__DIR__) . '/controllers/IncidenciasControlador.php';
require_once dirname(__DIR__) . '/controllers/ResidentesControlador.php';
require_once dirname(__DIR__) . '/services/AutenticacionSolicitud.php';
require_once dirname(__DIR__) . '/controllers/AccesosQrControlador.php';

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
        ['GET',  '/api/comprobantes'],
        ['GET',  '/api/comprobantes/archivo'],
        ['POST', '/api/comprobantes/preregistrar'],
        ['GET', '/api/comprobantes/adeudos-movil'],
        ['POST', '/api/comprobantes/enviar-movil'],
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

    // HU03.1: endpoints móviles. La identidad proviene del JWT Bearer.
    if (($ruta === '/api/comprobantes/adeudos-movil' && $metodo === 'GET') ||
        ($ruta === '/api/comprobantes/enviar-movil' && $metodo === 'POST')) {
        $headerAutorizacion = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = null;
        if (is_string($headerAutorizacion) &&
            preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/i', trim($headerAutorizacion), $coincidencias) === 1) {
            $token = $coincidencias[1];
        }
        $usuario = $servicio->verificarTokenMovil($token);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión móvil inválida o vencida.']);
            exit;
        }
        $controladorMovil = new ComprobantesMovilControlador(
            new ComprobantesMovilRepositorio($conexion)
        );
        if ($metodo === 'GET') {
            $controladorMovil->obtenerAdeudos($usuario);
        } else {
            $controladorMovil->enviar($usuario);
        }
        exit;
    }

    // HU03: visualizar PDF, JPG y PNG de forma protegida.
    if ($ruta === '/api/comprobantes/archivo' && $metodo === 'GET') {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Debes iniciar sesión.']);
            exit;
        }
        if ($usuario['tipoUsuario'] !== 'administrador') {
            http_response_code(403);
            echo json_encode(['mensaje' => 'No tienes permiso para visualizar comprobantes.']);
            exit;
        }
        $idComprobante = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if (!$idComprobante) {
            http_response_code(422);
            echo json_encode(['mensaje' => 'ID de comprobante inválido.']);
            exit;
        }
        $consulta = $conexion->prepare('
            SELECT ruta_archivo
            FROM comprobantes_pago
            WHERE id_comprobante = :id
        ');
        $consulta->execute(['id' => $idComprobante]);
        $registro = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$registro || !is_string($registro['ruta_archivo'])) {
            http_response_code(404);
            echo json_encode(['mensaje' => 'Archivo no disponible.']);
            exit;
        }
        // Utilizar solo el nombre del archivo, nunca una ruta del navegador.
        $nombre = basename(str_replace('\\', '/', $registro['ruta_archivo']));
        // Solo se admiten nombres seguros y los formatos solicitados.
        if (!preg_match('/^[a-zA-Z0-9._-]+\.(pdf|jpg|jpeg|png)$/i', $nombre)) {
            http_response_code(404);
            echo json_encode(['mensaje' => 'Archivo no disponible.']);
            exit;
        }
        // Leer la ubicacion de los archivos desde una configuracion local privada.
        $rutaConfiguracionComprobantes =
            dirname(__DIR__) . '/config/comprobantes.local.php';
        if (!is_file($rutaConfiguracionComprobantes)) {
            throw new RuntimeException(
                'Falta la configuración local de comprobantes.'
            );
        }
        $configuracionComprobantes = require $rutaConfiguracionComprobantes;
        if (
            !is_array($configuracionComprobantes) ||
            !is_string($configuracionComprobantes['directorioPrivado'] ?? null) ||
            trim($configuracionComprobantes['directorioPrivado']) === ''
        ) {
            throw new RuntimeException(
                'La configuración de comprobantes es inválida.'
            );
        }
        $carpeta = realpath(
            $configuracionComprobantes['directorioPrivado']
        );
        $archivo = $carpeta
            ? realpath($carpeta . DIRECTORY_SEPARATOR . $nombre)
            : false;
        // No permitir acceder a archivos fuera de la carpeta privada.
        if (
            !$carpeta ||
            !$archivo ||
            !str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR) ||
            !is_file($archivo) ||
            !is_readable($archivo)
        ) {
            http_response_code(404);
            echo json_encode(['mensaje' => 'Archivo no disponible.']);
            exit;
        }
        $tiposPermitidos = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $tipoReal = (new finfo(FILEINFO_MIME_TYPE))->file($archivo);
        if (
            !isset($tiposPermitidos[$extension]) ||
            $tipoReal !== $tiposPermitidos[$extension]
        ) {
            http_response_code(404);
            echo json_encode(['mensaje' => 'Archivo no disponible.']);
            exit;
        }
        // No establecer Content-Length: causó fallos con el proxy local de Vite.
        header('Content-Type: ' . $tipoReal);
        header('Content-Disposition: inline; filename="comprobante.' . $extension . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($archivo);
        exit;
    }
    // HU03.2: confirmar el preregistro y enviar el pago a verificación.
    if ($ruta === '/api/comprobantes/preregistrar' && $metodo === 'POST') {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Debes iniciar sesión.']);
            exit;
        }
        if ($usuario['tipoUsuario'] !== 'administrador') {
            http_response_code(403);
            echo json_encode(['mensaje' => 'No tienes permiso para preregistrar pagos.']);
            exit;
        }
        // Solo se permiten solicitudes desde el portal web local de ALKIRA.
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!in_array($origen, [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ], true)) {
            http_response_code(403);
            echo json_encode(['mensaje' => 'Origen no permitido.']);
            exit;
        }
        $tipoContenido = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (!preg_match('~^application/json(?:\s*;|\s*$)~', $tipoContenido)) {
            http_response_code(415);
            echo json_encode(['mensaje' => 'Se requiere formato JSON.']);
            exit;
        }
        $contenido = file_get_contents('php://input');
        if ($contenido === false || strlen($contenido) > 4096) {
            http_response_code(422);
            echo json_encode(['mensaje' => 'Solicitud inválida.']);
            exit;
        }
        $datos = json_decode($contenido, true);
        if (
            !is_array($datos) ||
            !is_int($datos['idComprobante'] ?? null) ||
            $datos['idComprobante'] <= 0
        ) {
            http_response_code(422);
            echo json_encode(['mensaje' => 'ID de comprobante inválido.']);
            exit;
        }
        $repositorioComprobantes = new ComprobantesRepositorio($conexion);
        try {
            $confirmado = $repositorioComprobantes->confirmarPreregistro(
                $datos['idComprobante'],
                (int) $usuario['idUsuario']
            );
            if (!$confirmado) {
                http_response_code(404);
                echo json_encode(['mensaje' => 'Comprobante no encontrado.']);
                exit;
            }
            echo json_encode([
                'mensaje' => 'Preregistro confirmado. Pago enviado a verificación.'
            ], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            echo json_encode(['mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (DomainException $error) {
            http_response_code(409);
            echo json_encode(['mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    // HU03: listado completo (solo administración).
    if ($ruta === '/api/comprobantes' && $metodo === 'GET') {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Debes iniciar sesión.']);
            exit;
        }
        if ($usuario['tipoUsuario'] !== 'administrador') {
            http_response_code(403);
            echo json_encode(['mensaje' => 'No tienes permiso para consultar comprobantes.']);
            exit;
        }
        $repositorioComprobantes = new ComprobantesRepositorio($conexion);
        $comprobantes = $repositorioComprobantes->obtenerTodos();
        echo json_encode(
            ['comprobantes' => $comprobantes],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
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
