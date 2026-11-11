function moneda(valor) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN'
  }).format(Number(valor ?? 0))
}

function periodoLegible(periodo) {
  if (!periodo || typeof periodo !== 'string') return 'Sin periodo'

  const [anio, mes] = periodo.split('-')
  const fecha = new Date(Number(anio), Number(mes) - 1, 1)

  return new Intl.DateTimeFormat('es-MX', {
    month: 'short',
    year: '2-digit'
  }).format(fecha)
}

function LineaMorosidad({ registros }) {
  if (registros.length === 0) return null

  const width = 780
  const height = 250
  const paddingX = 45
  const paddingY = 28

  const valores = registros.map((r) =>
    Math.max(0, Math.min(100, Number(r.porcentajeMorosidad ?? 0)))
  )

  const maximo = Math.max(...valores, 1)

  const puntos = registros.map((registro, indice) => {
    const porcentaje = Math.max(
      0,
      Math.min(100, Number(registro.porcentajeMorosidad ?? 0))
    )

    const x =
      registros.length === 1
        ? width / 2
        : paddingX +
          (indice * (width - paddingX * 2)) /
            (registros.length - 1)

    const y =
      height -
      paddingY -
      (porcentaje / maximo) * (height - paddingY * 2)

    return {
      x,
      y,
      porcentaje,
      periodo: registro.periodo
    }
  })

  const polyline = puntos.map((p) => `${p.x},${p.y}`).join(' ')
  const area = [
    `${puntos[0].x},${height - paddingY}`,
    ...puntos.map((p) => `${p.x},${p.y}`),
    `${puntos[puntos.length - 1].x},${height - paddingY}`
  ].join(' ')

  return (
    <svg
      className="kdd-line-chart"
      viewBox={`0 0 ${width} ${height}`}
      role="img"
      aria-label="Gráfica de línea de morosidad por periodo"
    >
      {[0, 25, 50, 75, 100].map((tick) => {
        const y =
          height -
          paddingY -
          (tick / 100) * (height - paddingY * 2)

        return (
          <g key={tick}>
            <line
              x1={paddingX}
              y1={y}
              x2={width - paddingX}
              y2={y}
              stroke="#e6edf8"
              strokeWidth="1"
            />
            <text
              x="8"
              y={y + 4}
              fontSize="11"
              fill="#70809b"
            >
              {tick}%
            </text>
          </g>
        )
      })}

      <polygon
        points={area}
        fill="rgba(36, 87, 197, 0.12)"
      />

      <polyline
        points={polyline}
        fill="none"
        stroke="#2457c5"
        strokeWidth="3"
        strokeLinejoin="round"
        strokeLinecap="round"
      />

      {puntos.map((punto) => (
        <g key={punto.periodo}>
          <circle
            cx={punto.x}
            cy={punto.y}
            r="5"
            fill="#2457c5"
          />
          <text
            x={punto.x}
            y={punto.y - 12}
            textAnchor="middle"
            fontSize="11"
            fontWeight="700"
            fill="#17223b"
          >
            {punto.porcentaje.toFixed(1)}%
          </text>
          <text
            x={punto.x}
            y={height - 7}
            textAnchor="middle"
            fontSize="11"
            fill="#70809b"
          >
            {periodoLegible(punto.periodo)}
          </text>
        </g>
      ))}
    </svg>
  )
}

function TendenciaMensualKdd({ registros = [] }) {
  const periodos = Array.isArray(registros)
    ? [...registros].sort((a, b) =>
        a.periodo.localeCompare(b.periodo)
      )
    : []

  if (periodos.length === 0) {
    return (
      <section className="kdd-section-card p-4">
        <h3 className="h5 fw-bold mb-2">
          Tendencia mensual
        </h3>

        <div className="alert alert-secondary mb-0" role="status">
          No existen estados de cuenta para los filtros seleccionados.
        </div>
      </section>
    )
  }

  const porcentajes = periodos.map((r) =>
    Math.max(0, Math.min(100, Number(r.porcentajeMorosidad ?? 0)))
  )

  const promedio =
    porcentajes.reduce((acc, valor) => acc + valor, 0) /
    porcentajes.length

  const ultimo = periodos[periodos.length - 1]

  const mayorMorosidad = periodos.reduce((maximo, actual) => {
    const valorActual = Number(actual.porcentajeMorosidad ?? 0)
    const valorMaximo = Number(maximo.porcentajeMorosidad ?? 0)
    return valorActual > valorMaximo ? actual : maximo
  }, periodos[0])

  const maximoVencido = Math.max(
    ...periodos.map((r) => Number(r.totalVencido ?? 0)),
    1
  )

  return (
    <section className="kdd-section-card p-4">
      <div className="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h3 className="h5 fw-bold mb-1">
            Tendencia mensual
          </h3>
          <p className="text-secondary small mb-0">
            Ahora ya no solo hay barras: también verás una línea de evolución de la morosidad.
          </p>
        </div>
      </div>

      <div className="kdd-insights-grid mb-4">
        <div className="kdd-insight">
          <div className="kdd-insight__title">
            Promedio de morosidad
          </div>
          <div className="kdd-insight__value">
            {promedio.toFixed(2)}%
          </div>
          <p className="text-secondary small mb-0">
            Promedio de todos los periodos visibles.
          </p>
        </div>

        <div className="kdd-insight">
          <div className="kdd-insight__title">
            Periodo con mayor morosidad
          </div>
          <div className="kdd-insight__value">
            {periodoLegible(mayorMorosidad.periodo)}
          </div>
          <p className="text-secondary small mb-0">
            {Number(mayorMorosidad.porcentajeMorosidad ?? 0).toFixed(2)}% de morosidad.
          </p>
        </div>

        <div className="kdd-insight">
          <div className="kdd-insight__title">
            Último periodo visible
          </div>
          <div className="kdd-insight__value">
            {periodoLegible(ultimo.periodo)}
          </div>
          <p className="text-secondary small mb-0">
            Saldo vencido: {moneda(ultimo.totalVencido)}
          </p>
        </div>
      </div>

      <div className="kdd-chart-grid mb-4">
        <div className="kdd-chart-card">
          <div className="kdd-chart-card__title">
            Línea de morosidad por periodo
          </div>
          <div className="kdd-chart-card__subtitle">
            Entre más alta esté la línea, mayor es el porcentaje de saldo vencido.
          </div>

          <LineaMorosidad registros={periodos} />
        </div>

        <div className="kdd-chart-card">
          <div className="kdd-chart-card__title">
            Saldo vencido por periodo
          </div>
          <div className="kdd-chart-card__subtitle">
            Monto vencido asociado a cada periodo facturado.
          </div>

          <div className="kdd-mini-bars">
            {periodos.map((registro) => {
              const totalVencido = Number(registro.totalVencido ?? 0)
              const altura = (totalVencido / maximoVencido) * 100

              return (
                <div
                  key={registro.periodo}
                  className="kdd-mini-bar"
                >
                  <div className="kdd-mini-bar__track">
                    <div
                      className="kdd-mini-bar__fill"
                      style={{
                        height: `${Math.max(altura, totalVencido > 0 ? 8 : 0)}%`
                      }}
                    />
                  </div>

                  <div className="kdd-mini-bar__label">
                    {registro.periodo}
                  </div>

                  <div className="kdd-mini-bar__value">
                    {moneda(totalVencido)}
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      </div>

      <div className="kdd-help-box">
        <h4 className="h6 fw-bold">Cómo leer esta parte</h4>
        <ol className="small text-secondary">
          <li>Si cambias el periodo, la línea y las barras deben reaccionar.</li>
          <li>La línea representa porcentaje de morosidad.</li>
          <li>Las barras representan monto vencido.</li>
          <li>Si octubre tiene pago aplicado, debería verse mejor que septiembre.</li>
        </ol>
      </div>
    </section>
  )
}

export default TendenciaMensualKdd