<?php
declare(strict_types=1);

/**
 * DATOS DE PRUEBA HU05 + HU10. Ejecutar DESPUÉS de schema.sql y modulo5.sql/modulo10.sql.
 * Uso local: php backend/pruebas/insertarDatosPruebaHu05Hu10.php --base=alkira_pruebas_demo
 * No importa el esquema, no borra registros y no contiene credenciales utilizables.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$opciones = getopt('', ['base:', 'ayuda']);
if (isset($opciones['ayuda']) || !isset($opciones['base'])) {
    echo "Inserta datos ficticios de HU05/HU10 en la base local configurada.\n"
        . "Primero carga schema.sql, backend/database/modulo5.sql y modulo10.sql.\n"
        . "Uso: php backend/pruebas/insertarDatosPruebaHu05Hu10.php --base=alkira_pruebas_demo\n"
        . "Solo admite bases locales con prefijo alkira_pruebas_.\n"
        . "Al repetir: renueva claves de las cuentas demo, sin duplicar ni reiniciar ejemplos.\n";
    exit(isset($opciones['ayuda']) ? 0 : 1);
}

$db = null;
try {
    if (!is_string($opciones['base']) || !preg_match('/^alkira_pruebas_[a-zA-Z0-9_]+$/D', $opciones['base'])) {
        throw new RuntimeException('Solo se permiten bases con prefijo alkira_pruebas_.');
    }
    $config = require dirname(__DIR__) . '/config/baseDatos.local.php';
    if (!in_array($config['servidor'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
        || ($config['baseDatos'] ?? '') !== $opciones['base']) {
        throw new RuntimeException('La base indicada debe coincidir con baseDatos.local.php y MySQL debe ser local.');
    }
    require_once dirname(__DIR__) . '/config/Conexion.php';
    $db = Conexion::obtenerConexion();
    // Evita ejecutar con un esquema incompleto antes de insertar cualquier dato.
    $db->query('SELECT id_incidencia FROM view_incidencias LIMIT 0');
    $db->query('SELECT id_pase FROM view_pases_acceso_qr LIMIT 0');
    $bloqueo = $db->query("SELECT GET_LOCK('alkira_demo_hu05_hu10', 0)")->fetchColumn();
    if ((int) $bloqueo !== 1) throw new RuntimeException('Ya hay otro preparador ejecutándose.');

    $buscar = static function (string $sql, array $datos = []) use ($db): mixed {
        $q = $db->prepare($sql);
        $q->execute($datos);
        return $q->fetch(PDO::FETCH_ASSOC);
    };
    $ejecutar = static function (string $sql, array $datos = []) use ($db): int {
        $q = $db->prepare($sql);
        $q->execute($datos);
        return (int) $db->lastInsertId();
    };
    $db->beginTransaction();
    $usuarios = [];
    $accesos = [];
    foreach (['admin' => 'administrador', 'guardia' => 'guardia', 'residente' => 'inquilino'] as $nombre => $rol) {
        $correo = $nombre . '.hu05hu10@alkira.test';
        $usuario = $buscar('SELECT id_usuario, tipo_usuario, apellidos FROM usuarios WHERE correo = ? FOR UPDATE', [$correo]);
        if ($usuario && ($usuario['tipo_usuario'] !== $rol || $usuario['apellidos'] !== 'Demo HU05 HU10')) {
            throw new RuntimeException('Un correo demo ya pertenece a otra cuenta. No se modificó ningún registro.');
        }
        // Doce caracteres sin símbolos ni letras/números visualmente ambiguos.
        $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $clave = '';
        for ($i = 0; $i < 12; ++$i) $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        if ($usuario) {
            $id = (int) $usuario['id_usuario'];
            $ejecutar("UPDATE usuarios SET contrasena_hash = ?, estado = 'activo', activo = 1 WHERE id_usuario = ?", [$hash, $id]);
        } else {
            $id = $ejecutar("INSERT INTO usuarios (nombres, apellidos, correo, contrasena_hash, tipo_usuario)
                VALUES (?, 'Demo HU05 HU10', ?, ?, ?)", [ucfirst($nombre), $correo, $hash, $rol]);
        }
        $usuarios[$nombre] = $id;
        $accesos[] = 'Usuario: ' . $correo . ' | Contrasena: ' . $clave;
    }

    $inmueble = $buscar("SELECT id_inmueble FROM inmuebles WHERE nombre = '[DEMO HU05 HU10]' AND descripcion = 'Datos ficticios HU05 HU10' FOR UPDATE");
    $idInmueble = $inmueble ? (int) $inmueble['id_inmueble'] : $ejecutar("INSERT INTO inmuebles
        (nombre, direccion, ciudad, descripcion) VALUES ('[DEMO HU05 HU10]', 'Direccion ficticia', 'Ciudad demo', 'Datos ficticios HU05 HU10')");
    $unidad = $buscar("SELECT id_unidad FROM propiedades_unidades WHERE id_inmueble = ? AND numero_unidad = 'DEMO-01' FOR UPDATE", [$idInmueble]);
    $idUnidad = $unidad ? (int) $unidad['id_unidad'] : $ejecutar("INSERT INTO propiedades_unidades
        (id_inmueble, numero_unidad, estado_unidad) VALUES (?, 'DEMO-01', 'ocupada')", [$idInmueble]);
    $idResidente = $usuarios['residente'];
    if (!$buscar('SELECT id_usuario_propiedad FROM usuarios_propiedades WHERE id_usuario = ? AND id_unidad = ?', [$idResidente, $idUnidad])) {
        $ejecutar("INSERT INTO usuarios_propiedades (id_usuario, id_unidad, relacion, fecha_inicio)
            VALUES (?, ?, 'inquilino', CURDATE())", [$idResidente, $idUnidad]);
    }
    foreach (['reportada' => 'Fuga de agua', 'en_revision' => 'Luz del pasillo'] as $estado => $titulo) {
        $titulo = '[DEMO HU05] ' . $titulo;
        if (!$buscar('SELECT id_incidencia FROM incidencias WHERE id_usuario_reporta = ? AND titulo = ?', [$idResidente, $titulo])) {
            $ejecutar("INSERT INTO incidencias (id_usuario_reporta, id_unidad, id_inmueble, titulo, descripcion, categoria, urgencia, estado)
                VALUES (?, ?, ?, ?, 'Reporte ficticio para probar HU05; sin evidencia precargada.', ?, 'media', ?)",
                [$idResidente, $idUnidad, $idInmueble, $titulo, $estado === 'reportada' ? 'plomeria' : 'electricidad', $estado]);
        }
    }
    foreach (['vigente', 'vencido', 'revocado'] as $estado) {
        $visitante = '[DEMO HU10] Visita ' . $estado;
        if (!$buscar('SELECT id_pase FROM pases_acceso_qr WHERE id_usuario_anfitrion = ? AND nombre_visitante = ?', [$idResidente, $visitante])) {
            $ejecutar("INSERT INTO pases_acceso_qr (id_usuario_anfitrion, nombre_visitante, token_qr, fecha_inicio_vigencia, fecha_fin_vigencia, estado)
                VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 HOUR), "
                . ($estado === 'vencido' ? 'DATE_SUB(NOW(), INTERVAL 1 MINUTE)' : 'DATE_ADD(NOW(), INTERVAL 24 HOUR)') . ', ?)',
                [$idResidente, $visitante, bin2hex(random_bytes(24)), $estado]);
        }
    }
    $db->commit();
    // Mostrar credenciales solamente cuando TODA la transacción terminó correctamente.
    echo implode(PHP_EOL, $accesos) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    // No imprimir excepciones PDO: podrían revelar configuración o datos internos.
    fwrite(STDERR, $error instanceof PDOException
        ? "No se insertaron datos. Revisa MySQL, la configuracion local y los esquemas requeridos.\n"
        : $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($db instanceof PDO && isset($bloqueo) && (int) $bloqueo === 1) {
        $db->query("SELECT RELEASE_LOCK('alkira_demo_hu05_hu10')");
    }
}
