<?php
declare(strict_types=1);

final class ConciliacionRepositorio
{
    public function __construct(private PDO $conexion) {}

    public function listar(): array
    {
        $consulta = $this->conexion->query("
            SELECT c.id_comprobante AS idComprobante, c.id_pago AS idPago,
                   c.tipo_archivo AS tipoArchivo, c.fecha_carga AS fechaCarga,
                   c.estado_revision AS estadoRevision, c.motivo_revision AS motivoRevision,
                   p.estado AS estadoPago, p.monto, p.metodo_pago AS metodoPago,
                   p.referencia_bancaria AS referenciaBancaria,
                   ec.id_estado_cuenta AS idEstadoCuenta, ec.periodo,
                   ec.monto_principal AS montoPrincipal, ec.recargo_mora AS recargoMora,
                   ec.monto_pagado AS montoPagado, ec.estado AS estadoCuenta,
                   (ec.monto_principal + ec.recargo_mora - ec.monto_pagado) AS saldoPendiente,
                   u.nombres, u.apellidos, pu.numero_unidad AS numeroUnidad,
                   i.nombre AS nombreInmueble,
                   cp.id_conciliacion AS idConciliacion, cp.referencia_recibo AS referenciaRecibo
            FROM comprobantes_pago c
            JOIN pagos_transacciones p ON p.id_pago = c.id_pago
            JOIN estados_cuenta ec ON ec.id_estado_cuenta = p.id_estado_cuenta
            JOIN contratos ct ON ct.id_contrato = ec.id_contrato
            JOIN usuarios u ON u.id_usuario = ct.id_inquilino
            JOIN propiedades_unidades pu ON pu.id_unidad = ct.id_unidad
            JOIN inmuebles i ON i.id_inmueble = pu.id_inmueble
            LEFT JOIN conciliaciones_pago cp ON cp.id_conciliacion = (
                SELECT MAX(cp2.id_conciliacion) FROM conciliaciones_pago cp2 WHERE cp2.id_pago = p.id_pago
            )
            ORDER BY (p.estado = 'en_revision') DESC, c.fecha_carga DESC, c.id_comprobante DESC
        ");
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolver(int $idComprobante, int $idAdministrador, string $decision, ?string $motivo): array
    {
        if ($idComprobante < 1 || $idAdministrador < 1 || !in_array($decision, ['aprobar', 'rechazar'], true)) {
            throw new InvalidArgumentException('Datos de conciliación inválidos.');
        }
        $motivo = trim((string) $motivo);
        if ($decision === 'rechazar' && (strlen($motivo) < 5 || strlen($motivo) > 500)) {
            throw new InvalidArgumentException('El rechazo requiere un motivo de 5 a 500 caracteres.');
        }
        if ($decision === 'aprobar' && strlen($motivo) > 500) {
            throw new InvalidArgumentException('La observación no puede superar 500 caracteres.');
        }
        $this->conexion->beginTransaction();
        try {
            $consulta = $this->conexion->prepare("
                SELECT c.id_pago, c.estado_revision, p.estado AS estado_pago,
                       p.id_estado_cuenta, p.monto, p.referencia_bancaria
                FROM comprobantes_pago c JOIN pagos_transacciones p ON p.id_pago = c.id_pago
                WHERE c.id_comprobante = :id FOR UPDATE
            ");
            $consulta->execute(['id' => $idComprobante]);
            $registro = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$registro) throw new OutOfBoundsException('Comprobante no encontrado.');
            if ($registro['estado_revision'] !== 'pendiente' || $registro['estado_pago'] !== 'en_revision') {
                throw new DomainException('El comprobante ya fue procesado o aún no ha sido preregistrado.');
            }
            $estado = $decision === 'aprobar' ? 'aprobado' : 'rechazado';
            $idPago = (int) $registro['id_pago'];
            $folio = null;
            if ($decision === 'aprobar') {
                $consultaSaldo = $this->conexion->prepare('SELECT monto_principal, recargo_mora, monto_pagado, estado FROM estados_cuenta WHERE id_estado_cuenta = :id FOR UPDATE');
                $consultaSaldo->execute(['id' => (int) $registro['id_estado_cuenta']]);
                $cuenta = $consultaSaldo->fetch(PDO::FETCH_ASSOC);
                if (!$cuenta || $cuenta['estado'] === 'cancelado') {
                    throw new DomainException('El estado de cuenta no admite pagos.');
                }
                // Cálculos en centavos para evitar errores de redondeo en dinero.
                $aCentavos = static fn($monto): int => (int) round((float) $monto * 100);
                $total = $aCentavos($cuenta['monto_principal']) + $aCentavos($cuenta['recargo_mora']);
                $pagado = $aCentavos($cuenta['monto_pagado']);
                $importe = $aCentavos($registro['monto']);
                if ($importe <= 0 || $pagado + $importe > $total) {
                    throw new DomainException('El importe excede el saldo disponible o es inválido.');
                }
                // No autorizar una referencia que ya se utilizó en un pago aprobado.
                // Esto detecta duplicaciones evidentes, sin sustituir la revisión bancaria.
                $referencia = trim((string) ($registro['referencia_bancaria'] ?? ''));
                if ($referencia !== '') {
                    $buscarDuplicado = $this->conexion->prepare("
                        SELECT id_pago FROM pagos_transacciones
                        WHERE referencia_bancaria = :referencia
                          AND estado = 'aprobado'
                          AND id_pago <> :idPago
                        LIMIT 1
                    ");
                    $buscarDuplicado->execute(['referencia' => $referencia, 'idPago' => $idPago]);
                    if ($buscarDuplicado->fetchColumn()) {
                        throw new DomainException('La referencia bancaria ya existe en otro pago aprobado.');
                    }
                }
                $nuevoPagado = $pagado + $importe;
                $nuevoEstado = $nuevoPagado === $total ? 'pagado' : 'parcial';
                $actualizarCuenta = $this->conexion->prepare('UPDATE estados_cuenta SET monto_pagado = :pagado, estado = :estado WHERE id_estado_cuenta = :id');
                $actualizarCuenta->execute([
                    'pagado' => number_format($nuevoPagado / 100, 2, '.', ''),
                    'estado' => $nuevoEstado,
                    'id' => (int) $registro['id_estado_cuenta'],
                ]);
                $folio = sprintf('ALK-%s-%08d', date('Ymd'), $idPago);
            }
            $actualizarPago = $this->conexion->prepare('UPDATE pagos_transacciones SET estado = :estado, observaciones = :motivo WHERE id_pago = :id AND estado = \'en_revision\'');
            $actualizarPago->execute(['estado' => $estado, 'motivo' => $motivo ?: null, 'id' => $idPago]);
            if ($actualizarPago->rowCount() !== 1) throw new DomainException('El pago cambió de estado durante la conciliación.');
            $actualizarComprobante = $this->conexion->prepare('UPDATE comprobantes_pago SET estado_revision = :estado, motivo_revision = :motivo WHERE id_comprobante = :id AND estado_revision = \'pendiente\'');
            $actualizarComprobante->execute(['estado' => $estado, 'motivo' => $motivo ?: null, 'id' => $idComprobante]);
            if ($actualizarComprobante->rowCount() !== 1) throw new DomainException('El comprobante ya fue revisado.');
            $registroConciliacion = $this->conexion->prepare('INSERT INTO conciliaciones_pago (id_pago, id_usuario_revisor, resultado, motivo, referencia_recibo, ruta_recibo) VALUES (:idPago, :revisor, :resultado, :motivo, :folio, :ruta)');
            $registroConciliacion->execute([
                'idPago' => $idPago, 'revisor' => $idAdministrador, 'resultado' => $estado,
                'motivo' => $motivo ?: null, 'folio' => $folio,
                'ruta' => $folio ? '/api/conciliacion/recibo?id=' . $idPago : null,
            ]);
            $historial = $this->conexion->prepare('INSERT INTO historial_estatus_pago (id_pago, estado_anterior, estado_nuevo, id_usuario_responsable, comentario) VALUES (:id, :anterior, :nuevo, :usuario, :comentario)');
            $historial->execute([
                'id' => $idPago, 'anterior' => 'en_revision', 'nuevo' => $estado,
                'usuario' => $idAdministrador, 'comentario' => $motivo ?: 'Validación de pago por administración.',
            ]);
            $this->conexion->commit();
            return ['mensaje' => $estado === 'aprobado' ? 'Pago aprobado y saldo actualizado.' : 'Pago rechazado.', 'estado' => $estado, 'folio' => $folio, 'idPago' => $idPago];
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $error;
        }
    }

    public function recibo(int $idPago): ?array
    {
        $consulta = $this->conexion->prepare("
            SELECT p.id_pago AS idPago, p.monto, p.fecha_pago AS fechaPago,
                   p.metodo_pago AS metodoPago, p.referencia_bancaria AS referenciaBancaria,
                   cp.fecha_revision AS fechaRevision, cp.referencia_recibo AS folio,
                   u.nombres, u.apellidos, ec.periodo, pu.numero_unidad AS numeroUnidad,
                   i.nombre AS inmueble
            FROM pagos_transacciones p
            JOIN conciliaciones_pago cp ON cp.id_pago = p.id_pago AND cp.resultado = 'aprobado'
            JOIN estados_cuenta ec ON ec.id_estado_cuenta = p.id_estado_cuenta
            JOIN contratos ct ON ct.id_contrato = ec.id_contrato
            JOIN usuarios u ON u.id_usuario = ct.id_inquilino
            JOIN propiedades_unidades pu ON pu.id_unidad = ct.id_unidad
            JOIN inmuebles i ON i.id_inmueble = pu.id_inmueble
            WHERE p.id_pago = :id AND p.estado = 'aprobado'
            ORDER BY cp.id_conciliacion DESC LIMIT 1
        ");
        $consulta->execute(['id' => $idPago]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }
}
