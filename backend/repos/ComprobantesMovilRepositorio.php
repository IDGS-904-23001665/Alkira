<?php
declare(strict_types=1);

/** HU03.1. Siempre determina la pertenencia del adeudo desde el JWT verificado. */
final class ComprobantesMovilRepositorio
{
    public function __construct(private PDO $db) {}

    public function adeudosDeInquilino(int $idUsuario): array
    {
        $sql = <<<'SQL'
            SELECT ec.id_estado_cuenta AS idEstadoCuenta,
                   cc.nombre AS concepto,
                   ec.periodo,
                   ec.fecha_limite_pago AS fechaLimite,
                   ec.estado,
                   ec.monto_principal + ec.recargo_mora - ec.monto_pagado AS saldo
            FROM estados_cuenta ec
            JOIN contratos ct ON ct.id_contrato = ec.id_contrato
            JOIN conceptos_cobro cc ON cc.id_concepto = ec.id_concepto
            WHERE ct.id_inquilino = :usuario
              AND ct.estado_contrato = 'vigente'
              AND ec.estado IN ('pendiente', 'parcial', 'vencido')
              AND ec.monto_principal + ec.recargo_mora - ec.monto_pagado > 0
            ORDER BY ec.fecha_limite_pago, ec.id_estado_cuenta
        SQL;
        $q = $this->db->prepare($sql);
        $q->execute(['usuario' => $idUsuario]);
        return array_map(static fn(array $r): array => [
            'idEstadoCuenta' => (int) $r['idEstadoCuenta'],
            'concepto' => $r['concepto'],
            'periodo' => $r['periodo'],
            'fechaLimite' => $r['fechaLimite'],
            'estado' => $r['estado'],
            'saldo' => (string) $r['saldo'],
        ], $q->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Guarda el pago preregistrado y el comprobante en una transacción.
     * El archivo ya debe estar guardado en la carpeta privada.
     * No modifica saldos: esa responsabilidad es de la conciliación.
     */
    public function registrar(
        int $usuario,
        int $estadoCuenta,
        string $monto,
        string $referencia,
        string $concepto,
        string $nombreArchivo,
        string $tipoArchivo
    ): array {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare(<<<'SQL'
                SELECT ec.id_estado_cuenta,
                       (ec.monto_principal + ec.recargo_mora - ec.monto_pagado) AS saldo
                FROM estados_cuenta ec
                JOIN contratos ct ON ct.id_contrato = ec.id_contrato
                WHERE ec.id_estado_cuenta = :estadoCuenta
                  AND ct.id_inquilino = :usuario
                  AND ct.estado_contrato = 'vigente'
                  AND ec.estado IN ('pendiente','parcial','vencido')
                FOR UPDATE
            SQL);
            $q->execute(['estadoCuenta' => $estadoCuenta, 'usuario' => $usuario]);
            $adeudo = $q->fetch(PDO::FETCH_ASSOC);
            if (!$adeudo) {
                throw new DomainException('El adeudo no está disponible para este inquilino.');
            }

            // Evitar duplicados por reintento y reservar importes aún en revisión.
            $q = $this->db->prepare(<<<'SQL'
                SELECT COALESCE(SUM(monto),0) AS montoPendiente,
                       COALESCE(SUM(CASE WHEN id_usuario_registro = :usuario
                                        AND referencia_bancaria = :referencia THEN 1 ELSE 0 END),0) AS duplicados
                FROM pagos_transacciones
                WHERE id_estado_cuenta = :estadoCuenta
                  AND estado IN ('preregistrado','en_revision')
            SQL);
            $q->execute([
                'usuario' => $usuario,
                'referencia' => $referencia,
                'estadoCuenta' => $estadoCuenta,
            ]);
            $reservas = $q->fetch(PDO::FETCH_ASSOC);
            if ((int) ($reservas['duplicados'] ?? 0) > 0) {
                throw new DomainException('Ya existe un comprobante pendiente con esa referencia para este adeudo.');
            }
            $disponibleCentavos = (int) round(((float) $adeudo['saldo'] - (float) $reservas['montoPendiente']) * 100);
            $montoCentavos = (int) round((float) $monto * 100);
            if ($montoCentavos > $disponibleCentavos || $disponibleCentavos <= 0) {
                throw new DomainException('El monto supera el saldo disponible o ya hay pagos pendientes de revisión.');
            }

            $q = $this->db->prepare(<<<'SQL'
                INSERT INTO pagos_transacciones
                    (id_estado_cuenta, id_usuario_registro, fecha_pago, monto,
                     metodo_pago, referencia_bancaria, estado, observaciones)
                VALUES (:estadoCuenta, :usuario, NULL, :monto,
                        'transferencia', :referencia, 'preregistrado', :observaciones)
            SQL);
            $q->execute([
                'estadoCuenta' => $estadoCuenta,
                'usuario' => $usuario,
                'monto' => $monto,
                'referencia' => $referencia,
                'observaciones' => 'Concepto declarado en Android: ' . $concepto,
            ]);
            $idPago = (int) $this->db->lastInsertId();

            $q = $this->db->prepare(<<<'SQL'
                INSERT INTO comprobantes_pago
                    (id_pago, ruta_archivo, tipo_archivo, estado_revision)
                VALUES (:pago, :archivo, :tipo, 'pendiente')
            SQL);
            $q->execute(['pago' => $idPago, 'archivo' => $nombreArchivo, 'tipo' => $tipoArchivo]);
            $idComprobante = (int) $this->db->lastInsertId();

            $q = $this->db->prepare(<<<'SQL'
                INSERT INTO historial_estatus_pago
                    (id_pago, estado_anterior, estado_nuevo, id_usuario_responsable, comentario)
                VALUES (:pago, NULL, 'preregistrado', :usuario,
                        'Comprobante enviado desde Android; pendiente de preregistro administrativo.')
            SQL);
            $q->execute(['pago' => $idPago, 'usuario' => $usuario]);

            $this->db->commit();
            return ['idPago' => $idPago, 'idComprobante' => $idComprobante];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
