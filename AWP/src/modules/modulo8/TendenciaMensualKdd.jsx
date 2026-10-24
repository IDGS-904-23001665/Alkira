function TendenciaMensualKdd({ registros = [] }) {
  const periodos = Array.isArray(registros)
    ? [...registros].sort((a, b) =>
        a.periodo.localeCompare(b.periodo)
      )
    : []

  function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN'
    }).format(Number(valor ?? 0))
  }

  if (periodos.length === 0) {
  return (
    <section className="mb-5">
      <h3 className="h6 fw-bold mb-3">
        Morosidad por periodo
      </h3>

      <div className="alert alert-secondary" role="status">
        No existen estados de cuenta para los
        filtros seleccionados.
      </div>
    </section>
  )
}

  return (
    <section className="mb-5">
      <h3 className="h6 fw-bold mb-3">
        Morosidad por periodo
      </h3>

      <div className="card border-0 shadow-sm">
        <div className="card-body p-4">

          <p className="text-secondary small mb-4">
            Porcentaje del importe facturado que
            actualmente permanece vencido.
          </p>

          <div
            className="d-flex align-items-end gap-4 overflow-auto pb-3"
            style={{ minHeight: '270px' }}
          >
            {periodos.map((registro) => {
              const porcentajeOriginal = Number(
                registro.porcentajeMorosidad
              )

              const porcentaje = Number.isFinite(porcentajeOriginal)
                ? Math.max(0, Math.min(100, porcentajeOriginal))
                : 0

              return (
                <div
                  key={registro.periodo}
                  className="d-flex flex-column align-items-center"
                  style={{ minWidth: '120px' }}
                >
                  <span className="fw-bold small mb-2">
                    {porcentaje.toFixed(2)}%
                  </span>

                  <div
                    className="bg-light rounded-top d-flex align-items-end"
                    style={{
                      width: '75px',
                      height: '180px'
                    }}
                    role="img"
                    aria-label={`Morosidad de ${registro.periodo}: ${porcentaje.toFixed(2)} por ciento`}
                  >
                    <div
                      className="bg-primary rounded-top w-100"
                      style={{
                        height: `${porcentaje}%`
                      }}
                    />
                  </div>

                  <span className="fw-semibold small mt-3">
                    {registro.periodo}
                  </span>

                  <span className="text-secondary small text-center mt-1">
                    {formatearMoneda(registro.totalVencido)}
                    {' '}vencido
                  </span>
                </div>
              )
            })}
          </div>

          <hr />

          <p className="text-secondary small mb-0">
            Los indicadores se calculan desde MySQL.
            Cada barra representa un periodo de facturación.
          </p>

        </div>
      </div>
    </section>
  )
}

export default TendenciaMensualKdd