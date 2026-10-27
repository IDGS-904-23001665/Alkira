<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/controllers/AutenticacionControlador.php';
require_once dirname(__DIR__) . '/repos/ComprobantesRepositorio.php';
require_once dirname(__DIR__) . '/controllers/InmueblesControlador.php';
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// HU01: identificar las rutas del módulo de propiedades e inmuebles.
$rutaHu01 = is_string($ruta) && (
    preg_match('#^/api/inmuebles(?:/[1-9][0-9]*(?:/unidades)?)?$#D', $ruta) === 1
    || preg_match('#^/api/unidades/[1-9][0-9]*(?:/asignaciones)?$#D', $ruta) === 1
    || preg_match('#^/api/asignaciones/[1-9][0-9]*/finalizar$#D', $ruta) === 1
    || $ruta === '/api/usuarios/buscar'
);
try {
    if ($ruta === '/api/salud' && $metodo === 'GET') {
        echo json_encode(['estado' => 'disponible']);
        exit;
    }
    if (
        !$rutaHu01 &&
        !in_array([$metodo, $ruta], [
            ['POST', '/api/auth/login'],
            ['GET', '/api/auth/yo'],
            ['POST', '/api/auth/salir'],
            ['GET', '/api/comprobantes'],
            ['POST', '/api/comprobantes/preregistrar'],
            ['GET', '/api/comprobantes/archivo'],
        ], true)
    ) {
        http_response_code(404);
        echo json_encode(['mensaje' => 'Ruta no encontrada.']);
        exit;
    }
    $rutaClave = dirname(__DIR__) . '/config/autenticacion.local.php';
    if (!is_file($rutaClave)) {
        throw new RuntimeException('Falta la configuración de autenticación.');
    }
    $configuracion = require $rutaClave;
    if (!is_array($configuracion) || !is_string($configuracion['claveJwt'] ?? null)) {
        throw new RuntimeException('La configuración de autenticación es inválida.');
    }
    $conexion = Conexion::obtenerConexion();
    $repositorio = new UsuariosRepositorio($conexion);
    $servicio = new AutenticacionServicio($repositorio, $configuracion['claveJwt']);
    $controlador = new AutenticacionControlador($servicio);

    // HU01: conservar intacto el controlador del módulo de propiedades.
    if ($rutaHu01) {
        $usuario = $servicio->verificarToken($_COOKIE['alkira_token'] ?? null);
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
    // HU03: visualizar PDF, JPG y PNG de forma protegida.
    if ($ruta === '/api/comprobantes/archivo' && $metodo === 'GET') {
        $usuario = $servicio->verificarToken($_COOKIE['alkira_token'] ?? null);
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
        $usuario = $servicio->verificarToken($_COOKIE['alkira_token'] ?? null);
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
        $usuario = $servicio->verificarToken($_COOKIE['alkira_token'] ?? null);
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
    // Rutas de autenticación ya existentes.
    if ($ruta === '/api/auth/login') {
        $controlador->ingresar();
    } elseif ($ruta === '/api/auth/yo') {
        $controlador->sesion();
    } else {
        $controlador->salir();
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['mensaje' => 'No fue posible procesar la solicitud.']);
}
