<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/controllers/AutenticacionControlador.php';
require_once dirname(__DIR__) . '/controllers/KddControlador.php';
require_once dirname(__DIR__) . '/repos/ComprobantesRepositorio.php';
require_once dirname(__DIR__) . '/repos/ConciliacionRepositorio.php';
require_once dirname(__DIR__) . '/controllers/ConciliacionControlador.php';
require_once dirname(__DIR__) . '/controllers/ComprobantesMovilControlador.php';
require_once dirname(__DIR__) . '/controllers/InmueblesControlador.php';
require_once dirname(__DIR__) . '/controllers/AdeudosControlador.php';
require_once dirname(__DIR__) . '/controllers/HistorialContratosControlador.php';
require_once dirname(__DIR__) . '/controllers/IncidenciasControlador.php';
require_once dirname(__DIR__) . '/controllers/ResidentesControlador.php';
require_once dirname(__DIR__) . '/services/AutenticacionSolicitud.php';
require_once dirname(__DIR__) . '/controllers/AccesosQrControlador.php';
require_once dirname(__DIR__) . '/controllers/TicketsControlador.php';
require_once dirname(__DIR__) . '/controllers/OrdenesTrabajoControlador.php';
require_once dirname(__DIR__) . '/controllers/CierreMantenimientoControlador.php';
require_once dirname(__DIR__) . '/controllers/Hu07Controlador.php';
require_once dirname(__DIR__) . '/controllers/ContratosAdministrativosControlador.php';
require_once dirname(__DIR__) . '/controllers/ReglasCobroControlador.php';
require_once dirname(__DIR__) . '/controllers/GeneracionAdeudosControlador.php';


$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rutaHu01 = is_string($ruta) && (
    preg_match('#^/api/inmuebles(?:/[1-9][0-9]*(?:/unidades)?)?$#D', $ruta) === 1
    || preg_match('#^/api/unidades/[1-9][0-9]*(?:/asignaciones)?$#D', $ruta) === 1
    || preg_match('#^/api/asignaciones/[1-9][0-9]*/finalizar$#D', $ruta) === 1
    || $ruta === '/api/usuarios/buscar'
);


$rutaTickets = is_string($ruta) && (
    ($metodo === 'GET' && (
        $ruta === '/api/tickets'
        || preg_match(
            '#^/api/tickets/[1-9][0-9]*/historial$#D',
            $ruta
        ) === 1
    ))
    || ($metodo === 'POST' && preg_match(
        '#^/api/tickets/[1-9][0-9]*/(?:firma|iniciar)$#D',
        $ruta
    ) === 1)
);


$rutaOrdenesTrabajo = is_string($ruta) && (
    ($metodo === 'GET' && in_array($ruta, [
        '/api/mantenimiento/tickets',
        '/api/mantenimiento/tecnicos',
    ], true))
    || ($metodo === 'POST'
        && preg_match('#^/api/mantenimiento/tickets/[1-9][0-9]*/asignar$#D', $ruta) === 1)
);

$rutaCierreMantenimiento = is_string($ruta) && (
    ($metodo === 'GET'
        && preg_match('#^/api/mantenimiento/tickets/[1-9][0-9]*/materiales$#D', $ruta) === 1)
    || ($metodo === 'POST'
        && preg_match('#^/api/mantenimiento/tickets/[1-9][0-9]*/cerrar$#D', $ruta) === 1)
);

$rutaHu07 = is_string($ruta) && (
    $ruta === '/api/hu07/inmuebles' || $ruta === '/api/hu07/comunicados'
    || $ruta === '/api/hu07/avisos' || $ruta === '/api/hu07/dispositivos'
    || $ruta === '/api/hu07/votaciones' || $ruta === '/api/hu07/votaciones-movil'
    || preg_match('#^/api/hu07/avisos/[1-9][0-9]*/leer$#D', $ruta) === 1
    || preg_match('#^/api/hu07/votaciones/[1-9][0-9]*/(?:auditoria|votar)$#D', $ruta) === 1
);



$rutaContratosAdministrativos = is_string($ruta) && (
    ($metodo === 'GET' && (
        in_array($ruta, [
            '/api/administracion/contratos',
            '/api/administracion/contratos/unidades',
            '/api/administracion/contratos/inquilinos'
        ], true)
        || preg_match(
            '#^/api/administracion/unidades/[1-9][0-9]*/cobros$#D',
            $ruta
        ) === 1
        || preg_match(
            '#^/api/administracion/contratos/[1-9][0-9]*/documento$#D',
            $ruta
        ) === 1
    ))
    || ($metodo === 'POST' && (
        $ruta === '/api/administracion/contratos'
        || preg_match(
            '#^/api/administracion/contratos/[1-9][0-9]*/documento$#D',
            $ruta
        ) === 1
    ))
);


$rutaReglasCobro = is_string($ruta) && (
    (
        $metodo === 'GET'
        && (
            in_array(
                $ruta,
                [
                    '/api/administracion/reglas-cobro',
                    '/api/administracion/reglas-cobro/conceptos'
                ],
                true
            )
            || preg_match(
                '#^/api/administracion/reglas-cobro/[1-9][0-9]*$#D',
                $ruta
            ) === 1
        )
    )
    || (
        $metodo === 'POST'
        && $ruta === '/api/administracion/reglas-cobro'
    )
    || (
        $metodo === 'PUT'
        && preg_match(
            '#^/api/administracion/reglas-cobro/[1-9][0-9]*$#D',
            $ruta
        ) === 1
    )
);

$rutaSimulacionAdeudos = is_string($ruta)
    && $metodo === 'GET'
    && $ruta === '/api/administracion/adeudos/simulacion';



$rutaResidentes = $ruta === '/api/incidencias/residentes';

// HU04.2: rutas adicionales de conciliación de pagos.
$rutaConciliacion = ($metodo === 'GET' && in_array($ruta, ['/api/conciliacion', '/api/conciliacion/recibo'], true))
    || ($metodo === 'POST' && in_array($ruta, ['/api/conciliacion/aprobar', '/api/conciliacion/rechazar'], true));

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

    $rutasKdd = [
        ['GET', '/api/kdd/indicadores'],
        ['GET', '/api/kdd/inmuebles'],
        ['GET', '/api/kdd/financiero'],
        ['GET', '/api/kdd/operativo'],
        ['GET', '/api/kdd/reportes'],
    ];

    $rutaAdeudos = $ruta === '/api/adeudos/pendientes' && $metodo === 'GET';

    $rutaHistorialContratos = $metodo === 'GET' && is_string($ruta) && (
        $ruta === '/api/pagos/historial'
        || $ruta === '/api/contratos'
        || preg_match('#^/api/contratos/[1-9][0-9]*/documento$#D', $ruta) === 1
    );

    if (!$rutaHu07 && !$rutaConciliacion && !$rutaHu01 && !$rutaResidentes && !$rutaCierreMantenimiento && !$rutaOrdenesTrabajo && !$rutaTickets && !$rutaHu05 && !$rutaHu10 && !$rutaAdeudos && !$rutaHistorialContratos  && !$rutaContratosAdministrativos && !$rutaReglasCobro && !$rutaSimulacionAdeudos && !in_array([$metodo, $ruta], $rutasAutenticacion, true) && !in_array([$metodo, $ruta], $rutasKdd, true)) {
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
        !is_string($configuracion['claveJwt'] ?? null) ||
        $configuracion['claveJwt'] === ''
    ) {
        throw new RuntimeException('La configuración de autenticación es inválida.');
    }

    $conexion = Conexion::obtenerConexion();

    $repositorio = new UsuariosRepositorio($conexion);

    $servicio = new AutenticacionServicio(
        $repositorio,
        $configuracion['claveJwt']
    );

    $controlador = new AutenticacionControlador($servicio);

    // HU07: autenticar cada solicitud con el mecanismo web/movil existente.
    if ($rutaHu07) {
        $usuario = AutenticacionSolicitud::usuario($servicio);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesion no valida o vencida.']);
            exit;
        }
        (new Hu07Controlador(new Hu07Repositorio($conexion), $usuario, $ruta, $metodo))->procesar();
        exit;
    }

if ($rutaContratosAdministrativos) {
    $usuario = $servicio->verificarTokenWeb(
        $_COOKIE['alkira_token'] ?? null
    );

    if ($usuario === null) {
        http_response_code(401);

        echo json_encode([
            'mensaje' => 'Sesión administrativa no válida o vencida.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $repositorioContratos = new ContratosAdministrativosRepositorio(
        $conexion
    );

    $servicioContratos = new ContratosAdministrativosServicio(
        $repositorioContratos,
        $conexion
    );

    $controladorContratos = new ContratosAdministrativosControlador(
        $servicioContratos
    );

    $controladorContratos->procesar(
        $metodo,
        $ruta,
        $usuario
    );

    exit;
}


if ($rutaReglasCobro) {
    $usuario = $servicio->verificarTokenWeb(
        $_COOKIE['alkira_token'] ?? null
    );

    if ($usuario === null) {
        http_response_code(401);

        echo json_encode([
            'mensaje' => 'Sesión administrativa no válida o vencida.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $repositorioReglas = new ReglasCobroRepositorio(
        $conexion
    );

    $servicioReglas = new ReglasCobroServicio(
        $repositorioReglas
    );

    $controladorReglas = new ReglasCobroControlador(
        $servicioReglas
    );

    $controladorReglas->procesar(
        $metodo,
        $ruta,
        $usuario
    );

    exit;
}


if ($rutaSimulacionAdeudos) {
    // Validar la sesión administrativa existente.
    $usuario = $servicio->verificarTokenWeb(
        $_COOKIE['alkira_token'] ?? null
    );

    if ($usuario === null) {
        http_response_code(401);

        echo json_encode([
            'mensaje' => 'Sesión administrativa no válida o vencida.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Construir las capas del Módulo 02.
    $repositorioGeneracion = new GeneracionAdeudosRepositorio(
        $conexion
    );

    $servicioGeneracion = new GeneracionAdeudosServicio(
        $repositorioGeneracion
    );

    $controladorGeneracion = new GeneracionAdeudosControlador(
        $servicioGeneracion
    );

    // El controlador verifica el rol del administrador.
    $controladorGeneracion->procesar(
        $metodo,
        $ruta,
        $usuario
    );

    exit;
}




    // HU04.2: procesar conciliación sin modificar el preregistro de HU03.2.
    if ($rutaConciliacion) {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión no válida o vencida.']);
            exit;
        }
        (new ConciliacionControlador(new ConciliacionRepositorio($conexion)))->procesar($metodo, $ruta, $usuario);
        exit;
    }

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

    if ($rutaOrdenesTrabajo) {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión administrativa no válida o vencida.']);
            exit;
        }
        $controladorOrdenes = new OrdenesTrabajoControlador(
            new OrdenesTrabajoServicio(new OrdenesTrabajoRepositorio($conexion))
        );
        $controladorOrdenes->procesar($metodo, $ruta, $usuario);
        exit;
    }

    if ($rutaCierreMantenimiento) {
        $usuario = $servicio->verificarTokenWeb($_COOKIE['alkira_token'] ?? null);
        if ($usuario === null) {
            http_response_code(401);
            echo json_encode(['mensaje' => 'Sesión administrativa no válida o vencida.']);
            exit;
        }
        (new CierreMantenimientoControlador(
            new CierreMantenimientoServicio(new CierreMantenimientoRepositorio($conexion))
        ))->procesar($metodo, $ruta, $usuario);
        exit;
    }

    if ($rutaTickets) {
        $controladorTickets = new TicketsControlador(
            $servicio,
            new TicketsServicio(new TicketsRepositorio($conexion))
        );
        $controladorTickets->procesar($metodo, $ruta);
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

    echo json_encode([
        'mensaje' => 'No fue posible procesar la solicitud.'
    ]);
}