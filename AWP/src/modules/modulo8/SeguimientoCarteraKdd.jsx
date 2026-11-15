
function moneda(valor) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN'
  }).format(Number(valor ?? 0))
}

function SeguimientoCarteraKdd({ datos }) {
  if (!datos) {
    return (
      <p className="text-secondary small mb-0">
        No hay información de cobranza disponible.
      </p>
    )
  }

  const unidades = Array.isArray(datos.unidades)
    ? datos.unidades
    : []

  // Cuentas que vencerán próximamente
  const proximas = unidades.filter(
    (unidad) =>
      Number(unidad.cuentasProximasAVencer ?? 0) > 0
  )

  // Unidades que ya tienen deuda vencida
  const vencidas = unidades.filter(
    (unidad) =>
      Number(unidad.cuentasVencidas ?? 0) > 0
  )

  const cuentasProximas = proximas.reduce(
    (total, unidad) =>
      total + Number(unidad.cuentasProximasAVencer ?? 0),
    0
  )

  return (
    <div className="px-3 pb-3">

      {/* Resumen preventivo */}
      <div className="row g-3 mb-3">
        <div className="col-12 col-md-6">
          <div className="border rounded-3 p-3 h-100">
            <p className="text-secondary small mb-1">
              Avisos preventivos
            </p>

            <h4 className="h4 mb-1">
              {cuentasProximas}
            </h4>

            <p className="text-secondary small mb-0">
              Cuentas pendientes que vencerán
              en los próximos 15 días.
            </p>
          </div>
        </div>

        <div className="col-12 col-md-6">
          <div className="border rounded-3 p-3 h-100">
            <p className="text-secondary small mb-1">
              Deudas por atender
            </p>

            <h4 className="h4 mb-1">
              {vencidas.length}
            </h4>

            <p className="text-secondary small mb-0">
              Unidades que ya tienen al menos
              una cuenta vencida.
            </p>
          </div>
        </div>
      </div>

      {/* Vencimientos próximos */}
      {proximas.length > 0 ? (
        <div className="mb-3">
          <h4 className="h6 fw-bold mb-2">
            Vencimientos próximos
          </h4>

          {proximas.slice(0, 5).map((unidad) => (
            <div
              key={unidad.idUnidad}
              className="border rounded-3 p-3 mb-2"
            >
              <div className="d-flex justify-content-between gap-2 flex-wrap">
                <strong>
                  Unidad {unidad.unidad}
                </strong>

                <span className="text-secondary small">
                  {unidad.cuentasProximasAVencer}
                  {' '}cuenta(s) por vencer
                </span>
              </div>

              <p className="text-secondary small mt-2 mb-0">
                {Number(unidad.cuentasVencidas) > 0
                  ? 'También tiene adeudos vencidos. Conviene contactar a la unidad antes del próximo vencimiento.'
                  : 'No tiene cuentas vencidas en esta selección. Puede enviarse un recordatorio oportuno.'}
              </p>
            </div>
          ))}

          {proximas.length > 5 && (
            <p className="text-secondary small">
              Se muestran cinco unidades.
              Consulta la tabla completa para ver las demás.
            </p>
          )}
        </div>
      ) : (
        <p className="text-secondary small mb-3">
          No hay cuentas pendientes que venzan en
          los próximos 15 días para los filtros
          seleccionados. Esto no descarta deudas
          que ya están vencidas.
        </p>
      )}

      {/* Deudas actuales: ocultas inicialmente */}
      <details className="border rounded-3 p-3 mb-3">
        <summary
          className="fw-semibold"
          style={{ cursor: 'pointer' }}
        >
          Ver unidades con deuda vencida ({vencidas.length})
        </summary>

        <div className="d-grid gap-2 mt-3">
          {vencidas.length === 0 ? (
            <p className="small text-secondary mb-0">
              No hay unidades con deuda vencida
              en esta selección.
            </p>
          ) : (
            vencidas.slice(0, 5).map((unidad) => (
              <div
                key={unidad.idUnidad}
                className="border rounded-3 p-3"
              >
                <div className="d-flex justify-content-between gap-2 flex-wrap mb-2">
                  <strong>
                    Unidad {unidad.unidad}
                  </strong>

                  <span className="small fw-semibold">
                    Prioridad {unidad.prioridad}
                    {' · '}
                    {unidad.puntuacion} puntos
                  </span>
                </div>

                <p className="fw-semibold mb-1">
                  {moneda(unidad.saldoVencido)}
                  {' '}vencido
                </p>

                <p className="small text-secondary mb-0">
                  {unidad.razones}
                </p>
              </div>
            ))
          )}
        </div>

        {vencidas.length > 5 && (
          <p className="text-secondary small mt-2 mb-0">
            La tabla completa permite consultar
            las demás unidades.
          </p>
        )}
      </details>

      {/* Metodología: disponible bajo demanda */}
      <details className="small">
        <summary
          className="fw-semibold"
          style={{ cursor: 'pointer' }}
        >
          ¿Cómo se calculan los avisos y prioridades?
        </summary>

        <p className="text-secondary mt-2 mb-0">
          El aviso preventivo identifica cuentas
          pendientes cuyo vencimiento está entre
          hoy y los siguientes 15 días.

          La prioridad de seguimiento suma
          35 puntos por cuentas vencidas,
          25 por adeudos de varios periodos,
          20 por más de 30 días de atraso,
          20 por saldo vencido desde $5,000
          y 20 por cuentas próximas a vencer.

          El máximo es de 100 puntos.

          Alta: 70–100; media: 35–69;
          baja: 1–34.

          Es una clasificación por reglas,
          no una probabilidad de impago
          ni un modelo de aprendizaje automático.

          Los días se calculan respecto de la
          fecha actual, aunque se seleccione
          un periodo de facturación anterior.
        </p>
      </details>

    </div>
  )
}

export default SeguimientoCarteraKdd
