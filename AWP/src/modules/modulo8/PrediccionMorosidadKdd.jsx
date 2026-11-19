
const COLUMNAS = [
  ['unidad', 'Unidad'],
  ['probabilidadAtraso', 'Riesgo estimado (%)'],
  ['nivel', 'Nivel'],
  ['periodosAnalizados', 'Periodos analizados'],
  ['periodosConAtraso', 'Con atraso'],
  ['periodosPuntuales', 'Puntuales'],
  ['calidad', 'Calidad de datos']
]

function exportarPrediccion(unidades) {
  const celda = (valor) => {
    const texto = String(valor ?? '')
    const seguro = /^\s*[=+\-@]/.test(texto)
      ? `'${texto}`
      : texto

    return `"${seguro.replaceAll('"', '""')}"`
  }

  const lineas = [
    COLUMNAS.map(([, titulo]) => celda(titulo)).join(','),
    ...unidades.map((unidad) =>
      COLUMNAS.map(([campo]) =>
        celda(unidad[campo])
      ).join(',')
    )
  ]

  const archivo = new Blob(
    ['\uFEFF' + lineas.join('\r\n')],
    { type: 'text/csv;charset=utf-8' }
  )

  const url = URL.createObjectURL(archivo)
  const enlace = document.createElement('a')

  enlace.href = url
  enlace.download = 'kdd_prediccion_morosidad.csv'

  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()

  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function PrediccionMorosidadKdd({ datos }) {
  if (!datos) {
    return (
      <div className="alert alert-secondary small">
        No se recibio el analisis predictivo del backend KDD.
      </div>
    )
  }

  const unidades = Array.isArray(datos.unidades)
    ? datos.unidades
    : []

  const resumen = datos.resumen ?? {}

  const clases = {
    alta: 'danger',
    media: 'warning',
    baja: 'success',
    sinDatos: 'secondary'
  }

  return (
    <section
      className="card border-0 shadow-sm mb-4"
      aria-label="Prediccion temprana de morosidad"
    >
      <div className="card-body">
        <div className="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h3 className="h5 fw-bold mb-1">
              Predicción temprana de morosidad
            </h3>

            <p className="small text-secondary mb-0">
              Riesgo estimado de atraso en el siguiente periodo
              de cobro, calculado con el historial hasta el{' '}
              {datos.fechaCorte}.
            </p>
          </div>

          <button
            type="button"
            className="btn btn-outline-primary btn-sm"
            onClick={() => exportarPrediccion(unidades)}
          >
            Descargar predicciones CSV
          </button>
        </div>

        <div className="row g-2 mb-3">
          {[
            [
              'Unidades con estimación',
              resumen.conEstimacion ?? 0,
              'text-primary'
            ],
            [
              'Riesgo alto',
              resumen.alta ?? 0,
              'text-danger'
            ],
            [
              'Riesgo medio',
              resumen.media ?? 0,
              'text-warning'
            ],
            [
              'Riesgo bajo',
              resumen.baja ?? 0,
              'text-success'
            ],
            [
              'Sin historial suficiente',
              resumen.sinHistorialSuficiente ?? 0,
              'text-secondary'
            ]
          ].map(([titulo, cantidad, clase]) => (
            <div className="col-6 col-lg" key={titulo}>
              <div className="border rounded p-3 h-100">
                <div className={`fs-4 fw-bold ${clase}`}>
                  {cantidad}
                </div>
                <div className="small text-secondary">
                  {titulo}
                </div>
              </div>
            </div>
          ))}
        </div>

        {unidades.length === 0 ? (
          <p className="small text-secondary">
            No hay unidades con contrato vigente dentro
            del inmueble seleccionado.
          </p>
        ) : (
          <div className="table-responsive">
            <table className="table table-striped table-sm align-middle">
              <thead>
                <tr>
                  <th>Unidad</th>
                  <th>Riesgo estimado</th>
                  <th>Nivel</th>
                  <th>Periodos</th>
                  <th>Atrasados</th>
                  <th>Puntuales</th>
                  <th>Calidad</th>
                </tr>
              </thead>

              <tbody>
                {unidades.map((unidad) => (
                  <tr key={unidad.idUnidad}>
                    <td>{unidad.unidad}</td>

                    <td className="fw-semibold">
                      {unidad.probabilidadAtraso === null
                        ? 'Sin estimación'
                        : `${Number(
                            unidad.probabilidadAtraso
                          ).toFixed(1)}%`}
                    </td>

                    <td>
                      <span
                        className={`badge text-bg-${
                          clases[unidad.nivel] ?? 'secondary'
                        }`}
                      >
                        {unidad.nivel === 'sinDatos'
                          ? 'Sin datos'
                          : unidad.nivel}
                      </span>
                    </td>

                    <td>{unidad.periodosAnalizados}</td>
                    <td>{unidad.periodosConAtraso}</td>
                    <td>{unidad.periodosPuntuales}</td>
                    <td>{unidad.calidad}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <p className="small text-secondary mb-1">
          {datos.metodologia}
        </p>

        <p className="small text-secondary mb-0">
          {Number(
            resumen.evaluacionesHistoricas ?? 0
          ) > 0
            ? `Evaluaciones históricas: ${
                resumen.evaluacionesHistoricas
              }. Error de Brier: ${
                resumen.errorBrier
              } (0 es mejor).`
            : 'Aún no hay suficientes periodos para evaluar el error predictivo en datos posteriores.'}
          {' '}
          El periodo seleccionado determina la fecha de
          corte; se consulta el historial anterior a esa fecha.
        </p>
      </div>
    </section>
  )
}

export default PrediccionMorosidadKdd
