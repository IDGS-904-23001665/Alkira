
function etiquetaMes(periodo) {
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(periodo ?? '')) {
    return periodo || '—'
  }

  const [anio, mes] = periodo.split('-').map(Number)

  return new Intl.DateTimeFormat('es-MX', {
    month: 'short',
    year: '2-digit'
  }).format(new Date(anio, mes - 1, 1))
}

function TendenciaMensualKdd({ registros = [] }) {
  const periodos = Array.isArray(registros)
    ? [...registros].sort((a, b) =>
        String(a.periodo).localeCompare(String(b.periodo))
      )
    : []

  if (periodos.length === 0) {
    return (
      <section className="kdd-section-card p-4">
        <h3 className="h6 fw-bold mb-2">
          Morosidad por mes
        </h3>

        <p className="text-secondary small mb-0">
          No hay datos para los filtros seleccionados.
        </p>
      </section>
    )
  }

  const ancho = 780
  const alto = 250

  const izquierda = 48
  const derecha = 30
  const superior = 26
  const inferior = 42

  const base = alto - inferior

  const puntos = periodos.map((registro, indice) => {
    const valor = Number(registro.porcentajeMorosidad)

    const porcentaje = Number.isFinite(valor)
      ? Math.max(0, Math.min(100, valor))
      : 0

    const x = periodos.length === 1
      ? ancho / 2
      : izquierda +
        indice * (ancho - izquierda - derecha) /
          (periodos.length - 1)

    const y = base -
      porcentaje * (base - superior) / 100

    return {
      x,
      y,
      porcentaje,
      periodo: registro.periodo
    }
  })

  const linea = puntos
    .map((punto) => `${punto.x},${punto.y}`)
    .join(' ')

  const area = [
    `${puntos[0].x},${base}`,
    linea,
    `${puntos[puntos.length - 1].x},${base}`
  ].join(' ')

  return (
    <section
      className="kdd-section-card p-4"
      aria-label="Morosidad por mes facturado"
    >
      <h3 className="h6 fw-bold mb-1">
        Morosidad por mes facturado
      </h3>

      <p className="text-secondary small mb-3">
        Porcentaje del importe facturado en cada mes que
        actualmente está vencido.
      </p>

      <svg
        className="kdd-line-chart"
        viewBox={`0 0 ${ancho} ${alto}`}
        role="img"
        aria-label="Gráfica de línea: porcentaje de morosidad por mes"
      >
        {[0, 25, 50, 75, 100].map((marca) => {
          const y = base -
            marca * (base - superior) / 100

          return (
            <g key={marca}>
              <line
                x1={izquierda}
                x2={ancho - derecha}
                y1={y}
                y2={y}
                stroke="#e3eaf6"
              />

              <text
                x="9"
                y={y + 4}
                fill="#68778c"
                fontSize="11"
              >
                {marca}%
              </text>
            </g>
          )
        })}

        <polygon
          points={area}
          fill="rgba(36,87,197,0.12)"
        />

        <polyline
          points={linea}
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
              fontWeight="700"
              fontSize="11"
              fill="#17223b"
            >
              {punto.porcentaje.toFixed(1)}%
            </text>

            <text
              x={punto.x}
              y={alto - 12}
              textAnchor="middle"
              fontSize="11"
              fill="#68778c"
            >
              {etiquetaMes(punto.periodo)}
            </text>
          </g>
        ))}
      </svg>

      <p className="text-secondary small mb-0 mt-2">
        Esta gráfica compara saldos actuales por periodo de
        facturación; no muestra cuánto se debía realmente
        al cierre de cada mes.
      </p>
    </section>
  )
}

export default TendenciaMensualKdd
