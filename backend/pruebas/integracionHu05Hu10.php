<?php
declare(strict_types=1);

// Prueba aislada: crea una BD efímera y una copia temporal del backend.
// Nunca importa SQL ni modifica usuarios en la base configurada del proyecto.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$raiz = dirname(__DIR__, 2);
$hu05 = is_file($raiz . '/backend/controllers/IncidenciasControlador.php');
$hu10 = is_file($raiz . '/backend/controllers/AccesosQrControlador.php');
$config = require $raiz . '/backend/config/baseDatos.local.php';
$nombre = 'alkira_pruebas_' . bin2hex(random_bytes(6));
$temporal = sys_get_temp_dir() . '/' . $nombre;
$servidor = null;
$db = null;
$creada = false;
$ok = 0;
$salida = 0;

function comprobar(bool $condicion, string $nombre): void
{
    global $ok;
    if (!$condicion) throw new RuntimeException($nombre);
    ++$ok;
    echo "OK $nombre\n";
}

function copiar(string $origen, string $destino): void
{
    if (!is_dir($destino)) mkdir($destino, 0700, true);
    foreach (new DirectoryIterator($origen) as $item) {
        if ($item->isDot() || in_array($item->getFilename(), ['documentos', 'pruebas'], true)) continue;
        $ruta = $destino . '/' . $item->getFilename();
        if ($item->isDir()) copiar($item->getPathname(), $ruta);
        else copy($item->getPathname(), $ruta);
    }
}

function sql(PDO $db, string $texto): void
{
    $texto = preg_replace('/CREATE DATABASE IF NOT EXISTS alkira\s+CHARACTER SET utf8mb4\s+COLLATE utf8mb4_unicode_ci;/i', '', $texto);
    $texto = preg_replace('/^\s*USE\s+alkira\s*;\s*$/mi', '', $texto);
    $delimitador = ';';
    $consulta = '';
    foreach (preg_split('/\R/', $texto) as $linea) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)/i', $linea, $partes)) { $delimitador = $partes[1]; continue; }
        if (str_starts_with(ltrim($linea), '--') || trim($linea) === '') continue;
        $consulta .= $linea . "\n";
        if (str_ends_with(rtrim($consulta), $delimitador)) {
            $db->exec(substr(rtrim($consulta), 0, -strlen($delimitador)));
            $consulta = '';
        }
    }
    if (trim($consulta) !== '') throw new RuntimeException('SQL sin terminar.');
}

function solicitar(string $metodo, string $ruta, mixed $datos = null, ?string $cookie = null, ?string $bearer = null): array
{
    global $base;
    $c = curl_init($base . $ruta);
    $headers = [];
    $conArchivo = is_array($datos) && (bool) array_filter($datos, fn($dato) => $dato instanceof CURLFile);
    if ($conArchivo) $headers[] = 'Expect:';
    if ($datos !== null && !is_array($datos)) {
        $headers[] = 'Content-Type: application/json';
    } elseif (is_array($datos) && !array_filter($datos, fn($dato) => $dato instanceof CURLFile)) {
        $datos = json_encode($datos, JSON_THROW_ON_ERROR);
        $headers[] = 'Content-Type: application/json';
    }
    if ($cookie !== null) $headers[] = 'Cookie: alkira_token=' . $cookie;
    if ($bearer !== null) $headers[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($c, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $conArchivo ? 60 : 15]);
    if ($datos !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $datos);
    $resultado = curl_exec($c);
    if ($resultado === false) throw new RuntimeException(curl_error($c));
    $estado = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $cabecera = substr($resultado, 0, curl_getinfo($c, CURLINFO_HEADER_SIZE));
    $cuerpo = substr($resultado, curl_getinfo($c, CURLINFO_HEADER_SIZE));
    curl_close($c);
    return [$estado, json_decode($cuerpo, true), $cabecera, $cuerpo];
}

function borrarTemporal(string $ruta, string $raiz): void
{
    $real = realpath($ruta);
    $base = realpath($raiz);
    if ($real === false || $base === false || !($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Ruta temporal fuera de alcance.');
    }
    foreach (new DirectoryIterator($ruta) as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir() && !$item->isLink()) borrarTemporal($item->getPathname(), $raiz);
        else unlink($item->getPathname());
    }
    rmdir($ruta);
}

try {
    $adminDb = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['servidor'], $config['puerto']),
        $config['usuario'], $config['contrasena'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $adminDb->exec("CREATE DATABASE `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $creada = true;
    $config['baseDatos'] = $nombre;
    $db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['servidor'], $config['puerto'], $nombre),
        $config['usuario'], $config['contrasena'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['schema.sql', 'backend/database/modulo2.sql', 'backend/database/modulo5.sql', 'backend/database/modulo10.sql'] as $archivo) {
        if (is_file($raiz . '/' . $archivo)) sql($db, file_get_contents($raiz . '/' . $archivo));
    }
    copiar($raiz . '/backend', $temporal . '/backend');
    file_put_contents($temporal . '/backend/config/baseDatos.local.php', '<?php return ' . var_export($config, true) . ';');
    $clave = bin2hex(random_bytes(32));
    file_put_contents($temporal . '/backend/config/autenticacion.local.php', '<?php return ' . var_export(['claveJwt' => $clave], true) . ';');
    $password = bin2hex(random_bytes(16));
    $q = $db->prepare('UPDATE usuarios SET contrasena_hash = ?');
    $q->execute([password_hash($password, PASSWORD_DEFAULT)]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $direccion = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $direccion;
    $conWeb = in_array('--web', $argv, true);
    if ($conWeb) {
        if (!is_file($raiz . '/AWP/dist/index.html')) throw new RuntimeException('Ejecuta npm run build en AWP antes de --web.');
        copiar($raiz . '/AWP/dist', $temporal . '/backend/public');
        file_put_contents($temporal . '/backend/public/router-test.php', '<?php $p=parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH); if(str_starts_with($p,"/api/")){require __DIR__."/index.php";} elseif(is_file(__DIR__.$p)){return false;} else {header("Content-Type: text/html");readfile(__DIR__."/index.html");}');
    }
    $servidor = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=20M', '-d', 'post_max_size=22M', '-S', $direccion,
        '-t', $temporal . '/backend/public', $temporal . '/backend/public/' . ($conWeb ? 'router-test.php' : 'router.php')],
        [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/servidor.log', 'a'], 2 => ['file', $temporal . '/servidor.log', 'a']], $pipes);
    fclose($pipes[0]);
    for ($i = 0; $i < 40; $i++) {
        $listo = @fsockopen(explode(':', $direccion)[0], (int) explode(':', $direccion)[1]);
        if ($listo) { fclose($listo); break; }
        usleep(100000);
    }
    if (!in_array('--solo-android', $argv, true)) {
    comprobar(solicitar('GET', '/api/salud')[0] === 200, 'salud HTTP');
    foreach (($hu10 ? ['admin' => 'admin', 'guardia' => 'guardia'] : ['admin' => 'admin']) as $rol => $correo) {
        $respuesta = solicitar('POST', '/api/auth/login', ['correo' => $correo . '@alkira.test', 'contrasena' => $password]);
        comprobar($respuesta[0] === 200, 'login ' . $rol);
        preg_match('/alkira_token=([^;]+)/', $respuesta[2], $partes);
        $tokens[$rol] = $partes[1] ?? '';
        comprobar(str_contains($respuesta[2], 'HttpOnly') && str_contains($respuesta[2], 'SameSite=Strict'), 'cookie protegida ' . $rol);
    }
    $admin = $tokens['admin']; $guardia = $tokens['guardia'] ?? 'sin-acceso-web';
    if ($hu10) comprobar(solicitar('GET', '/api/auth/yo', null, $guardia)[0] === 200, 'sesión guardia');
    comprobar(solicitar('POST', '/api/auth/login', ['correo' => 'residente@alkira.test', 'contrasena' => $password])[0] === 401, 'residente sin acceso web');
    if ($hu05) comprobar(solicitar('GET', '/api/incidencias')[0] === 401, 'incidencias sin sesión');
    if ($hu05) comprobar(solicitar('GET', '/api/incidencias', null, 'token-invalido')[0] === 401, 'token alterado rechazado');
    if ($hu05) comprobar(solicitar('GET', '/api/incidencias', null, $guardia)[0] === ($hu10 ? 403 : 401), 'guardia sin permisos incidencias');
    comprobar(solicitar('GET', '/api/inmuebles', null, $guardia)[0] === ($hu10 ? 403 : 401), 'guardia sin permisos inmuebles');
    comprobar(solicitar('GET', '/api/inmuebles', null, $admin)[0] === 200, 'regresión inmuebles administrador');
    [$estado, $datos] = solicitar('GET', '/api/incidencias/residentes', null, $admin);
    comprobar($estado === 200 && count($datos['residentes']) === 2 && array_diff(array_column($datos['residentes'], 'tipoUsuario'), ['inquilino', 'propietario']) === [], 'catálogo solo residentes');
    if ($hu05) {
    $reporte = ['idUsuarioReporta' => 3, 'idUnidad' => 1, 'idInmueble' => 2, 'titulo' => 'QA HU05 fuga', 'descripcion' => 'Prueba aislada', 'categoria' => 'plomeria', 'urgencia' => 'media'];
    comprobar(solicitar('POST', '/api/incidencias', array_replace($reporte, ['idUnidad' => 2]), $admin)[0] === 422, 'rechazo unidad ajena');
    comprobar(solicitar('POST', '/api/incidencias', array_replace($reporte, ['idUsuarioReporta' => 6]), $admin)[0] === 422, 'rechazo reportante guardia');
    comprobar(solicitar('POST', '/api/incidencias', ['titulo' => ''], $admin)[0] === 422, 'rechazo reporte vacío');
    [$estado, $datos] = solicitar('POST', '/api/incidencias', $reporte, $admin);
    comprobar($estado === 201 && $datos['incidencia']['estado'] === 'reportada', 'crear incidencia HTTP');
    $id = (int) $datos['incidencia']['idIncidencia'];
    $png = $temporal . '/evidencia.png';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
    [$estado, $datos] = solicitar('POST', "/api/incidencias/$id/evidencias", ['archivo' => new CURLFile($png, 'image/png', 'foto.png'), 'descripcion' => 'QA archivo'], $admin);
    comprobar($estado === 201 && count($datos['evidencias']) === 1, 'carga multipart PNG');
    $evidencia = (int) $datos['evidencias'][0]['idEvidencia'];
    $archivoUrl = "/api/incidencias/$id/evidencias/$evidencia/archivo";
    comprobar(solicitar('GET', $archivoUrl, null, $admin)[3] === file_get_contents($png), 'descarga evidencia auténtica');
    comprobar(solicitar('GET', $archivoUrl, null, $guardia)[0] === ($hu10 ? 403 : 401) && solicitar('GET', $archivoUrl)[0] === 401, 'evidencia protegida por permisos');
    $malo = $temporal . '/malo.php'; file_put_contents($malo, '<?php echo "no";');
    comprobar(solicitar('POST', "/api/incidencias/$id/evidencias", ['archivo' => new CURLFile($malo, 'image/png', 'foto.png')], $admin)[0] === 422, 'rechazo archivo PHP disfrazado');
    $antesArchivos = count(glob($temporal . '/backend/documentos/incidencias/*') ?: []);
    comprobar(solicitar('POST', '/api/incidencias', ['archivo' => new CURLFile($png, 'image/png', 'foto.png'), 'titulo' => ''], $admin)[0] === 422
        && count(glob($temporal . '/backend/documentos/incidencias/*') ?: []) === $antesArchivos, 'limpieza de archivo si falla el reporte');
    $grande = $temporal . '/grande.png';
    $f = fopen($grande, 'wb'); fwrite($f, file_get_contents($png)); fseek($f, 21 * 1024 * 1024 - 1); fwrite($f, '0'); fclose($f);
    comprobar(solicitar('POST', "/api/incidencias/$id/evidencias", ['archivo' => new CURLFile($grande, 'image/png', 'foto.png')], $admin)[0] === 422, 'rechazo evidencia mayor a 20 MB');
    [$estado, $datos] = solicitar('PATCH', "/api/incidencias/$id/estado", ['estado' => 'asignada', 'categoria' => 'electricidad', 'urgencia' => 'alta'], $admin);
    comprobar($estado === 200 && !empty($datos['incidencia']['idTicket']) && $datos['incidencia']['categoria'] === 'electricidad', 'categorizar y generar ticket');
    solicitar('PATCH', "/api/incidencias/$id/estado", ['estado' => 'en_revision'], $admin);
    solicitar('PATCH', "/api/incidencias/$id/estado", ['estado' => 'asignada'], $admin);
    comprobar((int) $db->query('SELECT COUNT(*) FROM tickets_mantenimiento WHERE id_incidencia=' . $id)->fetchColumn() === 1, 'ticket no duplicado');
    comprobar(solicitar('PATCH', "/api/incidencias/$id/estado", ['estado' => 'inventado'], $admin)[0] === 422, 'rechazo estado inválido');
    comprobar(solicitar('GET', '/api/incidencias/99999999', null, $admin)[0] === 404, 'incidencia inexistente');
    }

    if ($hu10) {
    $pase = ['idUsuarioAnfitrion' => 3, 'nombreVisitante' => 'QA visita', 'horasVigencia' => 6];
    comprobar(solicitar('POST', '/api/accesos-qr', $pase, $guardia)[0] === 403, 'guardia no emite pases');
    comprobar(solicitar('POST', '/api/accesos-qr', array_replace($pase, ['idUsuarioAnfitrion' => 6]), $admin)[0] === 422, 'rechazo anfitrión guardia');
    comprobar(solicitar('POST', '/api/accesos-qr', array_replace($pase, ['fechaInicioVigencia' => '2026-02-30 10:00:00']), $admin)[0] === 422, 'rechazo fecha imposible');
    comprobar(solicitar('POST', '/api/accesos-qr', array_replace($pase, ['horasVigencia' => 0]), $admin)[0] === 422, 'rechazo vigencia cero');
    comprobar(solicitar('POST', '/api/accesos-qr', array_replace($pase, ['horasVigencia' => 169]), $admin)[0] === 422, 'rechazo vigencia excesiva');
    [$estado, $datos] = solicitar('POST', '/api/accesos-qr', $pase, $admin);
    comprobar($estado === 201 && str_starts_with($datos['pase']['tokenQr'], 'ALK-QR-'), 'generar pase HTTP');
    $token = $datos['pase']['tokenQr']; $idPase = (int) $datos['pase']['idPase'];
    comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $token, 'observaciones' => str_repeat('a', 501)], $guardia)[0] === 422, 'rechazo observaciones excesivas');
    [$estado, $datos] = solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $token], $guardia);
    comprobar($estado === 200 && $datos['validacion']['resultado'] === 'ENTRADA', 'guardia registra entrada');
    comprobar(solicitar('PATCH', "/api/accesos-qr/$idPase/revocar", [], $admin)[0] === 409, 'no revocar visita abierta');
    $db->exec("UPDATE pases_acceso_qr SET fecha_fin_vigencia=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id_pase=$idPase");
    solicitar('GET', '/api/accesos-qr', null, $guardia);
    [$estado, $datos] = solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $token], $guardia);
    comprobar($estado === 200 && $datos['validacion']['resultado'] === 'SALIDA', 'salida permitida después de vencer');
    comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $token], $guardia)[1]['validacion']['resultado'] === 'RECHAZADO', 'tercer escaneo rechazado');
    comprobar((int) $db->query("SELECT COUNT(*) FROM bitacora_visitas WHERE id_pase=$idPase AND fecha_salida IS NOT NULL")->fetchColumn() === 1, 'bitácora única de visita');
    $nuevo = solicitar('POST', '/api/accesos-qr', $pase, $admin)[1]['pase'];
    comprobar(solicitar('PATCH', '/api/accesos-qr/' . $nuevo['idPase'] . '/revocar', [], $guardia)[0] === 403, 'guardia no revoca');
    comprobar(solicitar('PATCH', '/api/accesos-qr/' . $nuevo['idPase'] . '/revocar', [], $admin)[0] === 200, 'administrador revoca');
    comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $nuevo['tokenQr']], $guardia)[1]['validacion']['resultado'] === 'RECHAZADO', 'pase revocado rechazado');
    $vencido = solicitar('POST', '/api/accesos-qr', $pase, $admin)[1]['pase'];
    $db->exec('UPDATE pases_acceso_qr SET fecha_fin_vigencia=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id_pase=' . (int) $vencido['idPase']);
    comprobar(solicitar('PATCH', '/api/accesos-qr/' . $vencido['idPase'] . '/revocar', [], $admin)[0] === 409, 'no revocar pase vencido');
    comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $vencido['tokenQr']], $guardia)[1]['validacion']['resultado'] === 'RECHAZADO', 'pase vencido rechazado');
    $futuro = solicitar('POST', '/api/accesos-qr', $pase, $admin)[1]['pase'];
    $db->exec('UPDATE pases_acceso_qr SET fecha_inicio_vigencia=DATE_ADD(NOW(), INTERVAL 1 DAY), fecha_fin_vigencia=DATE_ADD(NOW(), INTERVAL 2 DAY) WHERE id_pase=' . (int) $futuro['idPase']);
    comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $futuro['tokenQr']], $guardia)[1]['validacion']['resultado'] === 'RECHAZADO', 'pase futuro rechazado');
    comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => 'no-existe'], $guardia)[1]['validacion']['resultado'] === 'RECHAZADO', 'token inexistente rechazado');
    // Dos procesos reales, dos conexiones, el mismo pase.
    $concurrente = solicitar('POST', '/api/accesos-qr', $pase, $admin)[1]['pase'];
    $worker = $temporal . '/worker.php';
    file_put_contents($worker, '<?php require __DIR__."/backend/config/Conexion.php"; require __DIR__."/backend/services/AccesosQrServicio.php"; $s=new AccesosQrServicio(new AccesosQrRepositorio(Conexion::obtenerConexion())); echo json_encode($s->validarToken(["tokenQr"=>trim(stream_get_contents(STDIN))],6));');
    $procesos = [];
    for ($i = 0; $i < 2; ++$i) {
        $proceso = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $canales);
        fwrite($canales[0], $concurrente['tokenQr']); fclose($canales[0]);
        $procesos[] = [$proceso, $canales];
    }
    $resultados = [];
    foreach ($procesos as [$proceso, $canales]) {
        $resultados[] = json_decode(stream_get_contents($canales[1]), true)['resultado'] ?? 'ERROR';
        stream_get_contents($canales[2]); fclose($canales[1]); fclose($canales[2]); proc_close($proceso);
    }
    sort($resultados);
    comprobar($resultados === ['ENTRADA', 'SALIDA'] && (int) $db->query('SELECT COUNT(*) FROM bitacora_visitas WHERE id_pase=' . (int) $concurrente['idPase'])->fetchColumn() === 1, 'validación concurrente sin duplicados');
    }

    $movil = solicitar('POST', '/api/auth/login-movil', ['correo' => 'residente@alkira.test', 'contrasena' => $password]);
    comprobar($movil[0] === 200, 'regresión autenticación Bearer existente');
    foreach (['/api/auth/yo-movil', '/api/adeudos/pendientes', '/api/pagos/historial', '/api/contratos'] as $ruta) {
        comprobar(solicitar('GET', $ruta, null, null, $movil[1]['token'])[0] === 200, 'regresión ' . $ruta);
    }
    if ($hu05) {
        $residente = $movil[1]['token'];
        $otro = solicitar('POST', '/api/auth/login-movil', ['correo' => 'propietario@alkira.test', 'contrasena' => $password])[1]['token'];
        $tecnico = solicitar('POST', '/api/auth/login-movil', ['correo' => 'tecnico@alkira.test', 'contrasena' => $password])[1]['token'];
        comprobar(solicitar('GET', '/api/incidencias', null, null, $residente)[0] === 200, 'HU05 Bearer residente');
        comprobar(solicitar('GET', '/api/incidencias?idUsuario=4', null, null, $residente)[0] === 403, 'HU05 no consultar otro residente');
        comprobar(solicitar('GET', '/api/incidencias', null, null, $tecnico)[0] === 403, 'HU05 tecnico sin acceso');
        $unidades = solicitar('GET', '/api/incidencias/unidades', null, null, $residente);
        comprobar($unidades[0] === 200 && count($unidades[1]['unidades']) === 1 && (int) $unidades[1]['unidades'][0]['idUnidad'] === 1, 'HU05 unidades propias');
        comprobar(solicitar('POST', '/api/incidencias', array_replace($reporte, ['idUsuarioReporta' => 4]), null, $residente)[0] === 403, 'HU05 no suplantar reportante');
        $movilReporte = ['titulo' => 'QA movil propio', 'descripcion' => 'Desde Bearer', 'idUnidad' => 1, 'idInmueble' => 2, 'archivo' => new CURLFile($png, 'image/png', 'foto.png')];
        $creado = solicitar('POST', '/api/incidencias', $movilReporte, null, $residente);
        comprobar($creado[0] === 201 && (int) $creado[1]['incidencia']['idUsuarioReporta'] === 3, 'HU05 reporte multipart desde movil');
        $idMovil = (int) $creado[1]['incidencia']['idIncidencia'];
        comprobar(solicitar('GET', "/api/incidencias/$idMovil", null, null, $otro)[0] === 403, 'HU05 detalle ajeno protegido');
        comprobar(solicitar('POST', "/api/incidencias/$idMovil/evidencias", ['rutaArchivo' => 'https://example.invalid/a.png'], null, $otro)[0] === 403, 'HU05 evidencia ajena protegida');
        comprobar(solicitar('PATCH', "/api/incidencias/$idMovil/estado", ['estado' => 'cerrada'], null, $residente)[0] === 403, 'HU05 residente no cambia estado');
        $misReportes = solicitar('GET', '/api/incidencias', null, null, $otro)[1]['incidencias'];
        comprobar(count(array_filter($misReportes, fn($r) => (int) $r['idUsuarioReporta'] !== 4)) === 0, 'HU05 listado sin datos de otros');
    }
    if ($hu10) {
        $residente = $movil[1]['token'];
        $otro = solicitar('POST', '/api/auth/login-movil', ['correo' => 'propietario@alkira.test', 'contrasena' => $password])[1]['token'];
        $tecnico = solicitar('POST', '/api/auth/login-movil', ['correo' => 'tecnico@alkira.test', 'contrasena' => $password])[1]['token'];
        comprobar(solicitar('GET', '/api/accesos-qr', null, null, $residente)[0] === 200, 'HU10 Bearer residente');
        comprobar(solicitar('GET', '/api/accesos-qr?idUsuario=4', null, null, $residente)[0] === 403, 'HU10 listado ajeno protegido');
        comprobar(solicitar('GET', '/api/accesos-qr/bitacora', null, null, $residente)[0] === 403, 'HU10 residente sin bitacora global');
        comprobar(solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => 'no-existe'], null, $residente)[0] === 403, 'HU10 residente no valida accesos');
        comprobar(solicitar('POST', '/api/accesos-qr', ['nombreVisitante' => 'Suplantacion', 'idUsuarioAnfitrion' => 4], null, $residente)[0] === 403, 'HU10 no suplantar anfitrion');
        comprobar(solicitar('GET', '/api/accesos-qr', null, null, $tecnico)[0] === 403, 'HU10 tecnico sin acceso');
        $creado = solicitar('POST', '/api/accesos-qr', ['nombreVisitante' => 'QA movil propio', 'horasVigencia' => 2], null, $residente);
        comprobar($creado[0] === 201 && (int) $creado[1]['pase']['idUsuarioAnfitrion'] === 3, 'HU10 creacion con identidad del JWT');
        $paseAjeno = solicitar('POST', '/api/accesos-qr', ['nombreVisitante' => 'QA propietario', 'horasVigencia' => 2], null, $otro)[1]['pase'];
        comprobar(solicitar('POST', '/api/accesos-qr/' . $paseAjeno['idPase'] . '/revocar', [], null, $residente)[0] === 403, 'HU10 no revocar pase ajeno');
        comprobar(solicitar('POST', '/api/accesos-qr/' . $creado[1]['pase']['idPase'] . '/revocar', [], null, $residente)[0] === 200, 'HU10 revocar pase propio');
    }
    }
    if (in_array('--android', $argv, true)) {
        $sdk = getenv('ANDROID_HOME') ?: (getenv('LOCALAPPDATA') . '/Android/Sdk');
        $adb = $sdk . '/platform-tools/adb.exe';
        $serial = getenv('ANDROID_SERIAL');
        if (!$serial) throw new RuntimeException('Define ANDROID_SERIAL para probar solamente el dispositivo autorizado.');
        $puerto = (int) explode(':', $direccion)[1];
        $reverso = proc_open([$adb, '-s', $serial, 'reverse', 'tcp:8000', 'tcp:' . $puerto], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $p);
        if (proc_close($reverso) !== 0) throw new RuntimeException('No se pudo conectar Android al backend efimero por USB.');
        try {
            $env = getenv();
            $ejecutar = function (array $comando, string $tarea) use ($temporal, $raiz, $env): string {
                $log = $temporal . '/android.log';
                $proceso = proc_open($comando, [0 => STDIN, 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $canales, $raiz . '/movil', $env);
                $codigo = proc_close($proceso);
                $texto = file_get_contents($log);
                if ($codigo !== 0) { echo implode("\n", array_slice(explode("\n", $texto), -35)) . "\n"; throw new RuntimeException($tarea); }
                return $texto;
            };
            $ejecutar(['cmd', '/c', $raiz . '/movil/gradlew.bat', ':app:assembleDebug', ':app:assembleDebugAndroidTest',
                '-PalkiraPruebas=true', '-PalkiraApiUrl=http://127.0.0.1:8000', '--offline', '--console=plain'], 'Compilar APKs de prueba Android');
            $ejecutar([$adb, '-s', $serial, 'install', '-r', '-t', $raiz . '/movil/app/build/outputs/apk/debug/app-debug.apk'], 'Instalar app de pruebas separada');
            $ejecutar([$adb, '-s', $serial, 'install', '-r', '-t', $raiz . '/movil/app/build/outputs/apk/androidTest/debug/app-debug-androidTest.apk'], 'Instalar instrumentacion');
            $ejecutar([$adb, '-s', $serial, 'reverse', 'tcp:8000', 'tcp:' . $puerto], 'Restablecer conexion USB despues de instalar');
            $seleccionAndroid = in_array('--android-integral', $argv, true)
                ? ['-e', 'class', 'com.alkira.movil.HistoriasNavegacionTest'] : [];
            $resultado = $ejecutar([$adb, '-s', $serial, 'shell', 'am', 'instrument', '-w', '-r', ...$seleccionAndroid, '-e', 'alkiraPrueba', 'true', '-e', 'alkiraClave', $password,
                'com.alkira.movil.pruebas.test/androidx.test.runner.AndroidJUnitRunner'], 'Ejecutar pruebas en Android');
            if (preg_match('/OK \((\d+) tests?\)/', $resultado, $resumen)) echo 'Android: ' . $resumen[1] . " pruebas correctas.\n";
            else {
                foreach (explode("\n", $resultado) as $linea) if (preg_match('/Exception:|Error:|Caused by:|failed to connect|FAILURES|Tests run:|AssertionError|HistoriasNavegacionTest.kt/', $linea)) echo $linea . "\n";
            }
            comprobar(str_contains($resultado, 'OK (') && !str_contains($resultado, 'FAILURES!!!') && !str_contains($resultado, 'INSTRUMENTATION_FAILED'), 'Android fisico: interfaz y API real');
            if ($hu10) {
                $tokenNativo = $db->query("SELECT token_qr FROM pases_acceso_qr WHERE nombre_visitante = 'QA Samsung caseta HU10' ORDER BY id_pase DESC LIMIT 1")->fetchColumn();
                $loginCaseta = solicitar('POST', '/api/auth/login', ['correo' => 'guardia@alkira.test', 'contrasena' => $password]);
                preg_match('/alkira_token=([^;]+)/', $loginCaseta[2], $cookieCaseta);
                foreach (['ENTRADA', 'SALIDA', 'RECHAZADO'] as $esperado) {
                    comprobar($tokenNativo && solicitar('POST', '/api/accesos-qr/validar', ['tokenQr' => $tokenNativo], $cookieCaseta[1])[1]['validacion']['resultado'] === $esperado, 'Android a caseta: ' . $esperado);
                }
            }

        } finally {
            foreach (['com.alkira.movil.pruebas.test', 'com.alkira.movil.pruebas'] as $paquete) {
                $borrar = proc_open([$adb, '-s', $serial, 'uninstall', $paquete], [0 => STDIN, 1 => ['file', $temporal . '/limpieza-android.log', 'a'], 2 => ['file', $temporal . '/limpieza-android.log', 'a']], $p);
                proc_close($borrar);
            }
            $limpieza = proc_open([$adb, '-s', $serial, 'reverse', '--remove', 'tcp:8000'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $p);
            proc_close($limpieza);
        }
    }
    if ($conWeb) {
        $env = getenv(); $env['CYPRESS_TEST_PASSWORD'] = $password; $env['CYPRESS_TEST_BASE_URL'] = $base; $env['CYPRESS_HU05_PRESENT'] = $hu05 ? 'true' : 'false';
        $browser = proc_open(['node', $raiz . '/AWP/node_modules/cypress/bin/cypress', 'run', '--project', $raiz . '/AWP', '--spec',
            implode(',', array_filter([$hu05 ? 'cypress/e2e/hu05.cy.js' : null, $hu10 ? 'cypress/e2e/hu10.cy.js' : null])), '--config', 'baseUrl=' . $base . ',supportFile=false,video=false'],
            [0 => STDIN, 1 => STDOUT, 2 => STDERR], $browserPipes, $raiz . '/AWP', $env);
        comprobar(proc_close($browser) === 0, 'navegador Cypress');
    }
    echo "RESULTADO: $ok comprobaciones correctas.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FALLO: ' . $error->getMessage() . "\n");
    if (in_array('--android-integral', $argv, true) && is_file($temporal . '/servidor.log')) {
        echo implode('', array_slice(file($temporal . '/servidor.log'), -18));
    }
    $salida = 1;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    $db = null;
    if ($creada && preg_match('/^alkira_pruebas_[a-f0-9]{12}$/D', $nombre)) {
        $adminDb->exec("DROP DATABASE `$nombre`");
        echo "BD temporal eliminada; base de trabajo conservada.\n";
    }
    if (is_dir($temporal)) borrarTemporal($temporal, $temporal);
}
exit($salida);
