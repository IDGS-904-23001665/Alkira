<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/IncidenciasServicio.php';
require_once dirname(__DIR__) . '/services/ArchivoEvidencia.php';

final class IncidenciasControlador
{
    public function __construct(
        private IncidenciasServicio $servicio,
        private array $usuarioSesion
    ) {}

    public function procesar(string $metodo, string $ruta): void
    {
        $archivoGuardado = null;
        try {
            $idSesion = (int) ($this->usuarioSesion['idUsuario'] ?? 0);

            $esAdmin = ($this->usuarioSesion['tipoUsuario'] ?? '') === 'administrador';
            $esResidente = in_array($this->usuarioSesion['tipoUsuario'] ?? '', ['inquilino', 'propietario'], true);
            if ($idSesion < 1 || (!$esAdmin && !$esResidente)) {
                $this->responder(403, ['mensaje' => 'No tienes permisos para gestionar incidencias.']);
                return;
            }
            if (!$esAdmin && isset($_GET['idUsuario']) && (string) $_GET['idUsuario'] !== (string) $idSesion) {
                $this->responder(403, ['mensaje' => 'Solo puedes consultar tus propios reportes.']);
                return;
            }
            if ($metodo === 'GET' && $ruta === '/api/incidencias/unidades') {
                $this->responder(200, ['unidades' => $this->servicio->listarUnidadesPorUsuario($idSesion)]);
                return;
            }
            if (!$esAdmin && preg_match('#^/api/incidencias/([1-9][0-9]*)#D', $ruta, $identificador)) {
                if (!in_array($metodo, ['GET', 'POST'], true) || str_ends_with($ruta, '/estado')) {
                    $this->responder(403, ['mensaje' => 'Solo administración puede categorizar o cambiar estados.']);
                    return;
                }
                $incidencia = $this->servicio->buscar((int) $identificador[1]);
                if ((int) $incidencia['idUsuarioReporta'] !== $idSesion) {
                    $this->responder(403, ['mensaje' => 'No puedes acceder a un reporte de otro residente.']);
                    return;
                }
            }
            if ($metodo === 'GET' && preg_match('#^/api/incidencias/([1-9][0-9]*)/evidencias/([1-9][0-9]*)/archivo$#D', $ruta, $ids)) {
                foreach ($this->servicio->listarEvidencias((int) $ids[1]) as $evidencia) {
                    if ((int) $evidencia['idEvidencia'] !== (int) $ids[2]) continue;
                    $archivo = ArchivoEvidencia::ruta((string) $evidencia['rutaArchivo']);
                    if ($archivo === null || !is_file($archivo)) break;
                    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($archivo));
                    header('Content-Length: ' . filesize($archivo));
                    header('Content-Disposition: inline; filename="evidencia.' . pathinfo($archivo, PATHINFO_EXTENSION) . '"');
                    header("Content-Security-Policy: sandbox; default-src 'none'");
                    readfile($archivo);
                    return;
                }
                throw new OutOfBoundsException('Archivo de evidencia no disponible.');
            }

            if ($metodo === 'GET' && $ruta === '/api/incidencias') {
                if (!$esAdmin) {
                    $this->responder(200, ['incidencias' => $this->servicio->listarPorUsuario($idSesion)]);
                    return;
                }
                if (isset($_GET['idUsuario']) && is_numeric($_GET['idUsuario'])) {
                    $this->responder(200, [
                        'incidencias' => $this->servicio->listarPorUsuario((int) $_GET['idUsuario']),
                    ]);
                    return;
                }
                $this->responder(200, [
                    'incidencias' => $this->servicio->listarTodas(),
                ]);
                return;
            }

            if ($metodo === 'GET' && $ruta === '/api/incidencias/residentes') {
                $this->responder(200, [
                    'residentes' => $this->servicio->listarResidentesActivos(),
                ]);
                return;
            }

            if ($metodo === 'POST' && $ruta === '/api/incidencias') {
                $entrada = $this->leerEntrada($archivoGuardado);
                if (!$esAdmin) {
                    if (isset($entrada['idUsuarioReporta']) && (string) $entrada['idUsuarioReporta'] !== (string) $idSesion) {
                        $this->responder(403, ['mensaje' => 'No puedes reportar en nombre de otro residente.']);
                        return;
                    }
                    $entrada['idUsuarioReporta'] = $idSesion;
                }
                $incidencia = $this->servicio->crear($entrada, $idSesion);
                $archivoGuardado = null;
                $this->responder(201, ['incidencia' => $incidencia]);
                return;
            }

            if (preg_match('#^/api/incidencias/([1-9][0-9]*)(/evidencias|/estado)?$#D', $ruta, $coincidencias)) {
                $idIncidencia = (int) $coincidencias[1];
                $subruta = $coincidencias[2] ?? '';

                if ($subruta === '' && $metodo === 'GET') {
                    $this->responder(200, [
                        'incidencia' => $this->servicio->buscar($idIncidencia),
                    ]);
                    return;
                }

                if ($subruta === '/evidencias' && $metodo === 'GET') {
                    $this->responder(200, [
                        'evidencias' => $this->servicio->listarEvidencias($idIncidencia),
                    ]);
                    return;
                }

                if ($subruta === '/evidencias' && $metodo === 'POST') {
                    $entrada = $this->leerEntrada($archivoGuardado);
                    $evidencias = $this->servicio->agregarEvidencia($idIncidencia, $entrada);
                    $archivoGuardado = null;
                    $this->responder(201, ['evidencias' => $evidencias]);
                    return;
                }

                if (($subruta === '/estado' || $subruta === '') && in_array($metodo, ['PUT', 'PATCH'], true)) {
                    $incidencia = $this->servicio->actualizarEstado($idIncidencia, $this->leerJson(), $idSesion);
                    $this->responder(200, ['incidencia' => $incidencia]);
                    return;
                }
            }

            $this->responder(404, ['mensaje' => 'Ruta de incidencias no encontrada.']);
        } catch (DomainException $error) {
            $this->responder(409, ['mensaje' => $error->getMessage()]);
        } catch (InvalidArgumentException $error) {
            $this->responder(422, ['mensaje' => $error->getMessage()]);
        } catch (OutOfBoundsException $error) {
            $this->responder(404, ['mensaje' => $error->getMessage()]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->responder(500, ['mensaje' => 'No fue posible procesar la operación de incidencias.']);
        } finally {
            if ($archivoGuardado !== null) ArchivoEvidencia::eliminar($archivoGuardado);
        }
    }

    private function leerEntrada(?string &$archivoGuardado): array
    {
        if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data')) {
            return $this->leerJson();
        }
        $entrada = $_POST;
        if (isset($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $archivo = ArchivoEvidencia::guardar($_FILES['archivo']);
            $archivoGuardado = $archivo['rutaArchivo'];
            $entrada = array_merge($entrada, $archivo);
        }
        return $entrada;
    }

    private function leerJson(): array
    {
        $contenido = file_get_contents('php://input');
        if ($contenido === false || strlen($contenido) > 65536) {
            throw new InvalidArgumentException('Cuerpo de solicitud inválido.');
        }
        try {
            $datos = json_decode($contenido, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('JSON inválido.');
        }
        if (!is_array($datos) || array_is_list($datos)) {
            throw new InvalidArgumentException('Se espera un objeto JSON.');
        }
        return $datos;
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
