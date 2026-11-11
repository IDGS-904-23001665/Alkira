import { useEffect, useState } from 'react'

const COLUMNAS_PAGOS = [
  { campo: 'idPago', titulo: 'Pago' },
  { campo: 'unidad', titulo: 'Unidad' },
  { campo: 'periodo', titulo: 'Periodo' },
  { campo: 'fechaPago', titulo: 'Fecha' },
  { campo: 'monto', titulo: 'Monto' },
  { campo: 'metodoPago', titulo: 'Método' },
  { campo: 'estadoPago', titulo: 'Estado' }
]

const COLUMNAS_MANTENIMIENTO = [
  { campo: 'idIncidencia', titulo: 'Incidencia' },
  { campo: 'idTicket', titulo: 'Ticket' },
  { campo: 'titulo', titulo: 'Descripción' },
  { campo: 'categoria', titulo: 'Categoría' },
  { campo: 'urgencia', titulo: 'Urgencia' },
  { campo: 'fechaReporte', titulo: 'Reportada' },
  { campo: 'estadoTicket', titulo: 'Ticket: estado' },
  { campo: 'horasResolucion', titulo: 'Horas de resolución' }
]

const COLUMNAS_ALERTAS = [
  { campo: 'unidad', titulo: 'Unidad' },
  { campo: 'cuentasRegistradas', titulo: 'Cuentas' },
  { campo: 'cuentasVencidas', titulo: 'Vencidas' },
  { campo: 'saldoVencido', titulo: 'Saldo vencido' },
  { campo: 'nivelAlerta', titulo: 'Alerta' }
]

function textoCelda(valor) {
  return valor === null || valor === undefined || valor === ''
    ? '—'
    : String(valor)
}

function descargarCsv(nombre, columnas, filas) {
  const sanear = (valor) => {
    const texto = String(valor ?? '')
    const seguro = /^\s*[=+\-@]/.test(texto)
      ? `'${texto}`
      : texto

    return `"${seguro.replaceAll('"', '""')}"`
  }

  const lineas = [
    columnas.map((columna) => sanear(columna.titulo)).join(','),
    ...filas.map((fila) =>
      columnas.map((columna) =>
        sanear(fila[columna.campo])
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
  enlace.download = nombre

  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()

  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function escaparHtml(valor) {
  return textoCelda(valor).replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  })[caracter])
}

function imprimirInforme(reporte, periodo, idInmueble) {
  const tabla = (titulo, columnas, filas) => `
    <h2>${escaparHtml(titulo)}</h2>
    <table>
      <thead>
        <tr>
          ${columnas.map((c) => `<th>${escaparHtml(c.titulo)}</th>`).join('')}
        </tr>
      </thead>
      <tbody>
        ${filas.map((fila) => `
          <tr>
            ${columnas.map((c) => `<td>${escaparHtml(fila[c.campo])}</td>`).join('')}
          </tr>
        `).join('')}
      </tbody>
    </table>
  `

  const patrones = reporte.patrones.map((p) => `
    <li>
      <b>${escaparHtml(p.titulo)}:</b>
      ${escaparHtml(p.detalle)}
    </li>
  `).join('')

  const contenido = `
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8" />
      <title>Informe KDD - ALKIRA</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          padding: 22px;
          color: #17223b;
        }
        h1 { font-size: 22px; }
        h2 { font-size: 16px; margin-top: 25px; }
        table {
          border-collapse: collapse;
          width: 100%;
          font-size: 11px;
        }
        th, td {
          border: 1px solid #bbb;
          padding: 6px;
          text-align: left;
          overflow-wrap: anywhere;
        }
        th { background: #eee; }
        small { color: #555; }
        li { margin: 5px 0; }
        @page {
          size: landscape;
          margin: 12mm;
        }
      </style>
    </head>
    <body>
      <h1>ALKIRA — Informe KDD</h1>
      <p>
        Inmueble: ${escaparHtml(idInmueble || 'Todos')}
        · Periodo: ${escaparHtml(periodo || 'Todos')}
      </p>
      <p>
        <small>
          Reporte descriptivo. Máximo 200 filas por tabla.
          No es una predicción de IA.
        </small>
      </p>

      <h2>Hallazgos</h2>
      <ul>
        ${patrones || '<li>Sin hallazgos en los filtros.</li>'}
      </ul>

      ${tabla('Pagos registrados', COLUMNAS_PAGOS, reporte.pagos)}
      ${tabla('Incidencias y mantenimiento', COLUMNAS_MANTENIMIENTO, reporte.mantenimiento)}
      ${tabla('Alertas de morosidad', COLUMNAS_ALERTAS, reporte.alertas)}
    </body>
    </html>
  `

  const ventana = window.open('', '_blank')

  if (!ventana) {
    window.alert(
      'El navegador bloqueó la ventana de impresión. Permite ventanas emergentes para localhost.'
    )
    return
  }

  ventana.document.open()
  ventana.document.write(contenido)
  ventana.document.close()
  ventana.focus()
  ventana.print()
}

function colorPatron(patron) {
  const texto = `${patron?.titulo ?? ''} ${patron?.detalle ?? ''}`.toLowerCase()

  if (
    texto.includes('venc') ||
    texto.includes('moros') ||
    texto.includes('urgente') ||
    texto.includes('alta')
  ) {
    return 'warning'
  }

  if (texto.includes('pago') || texto.includes('aplic')) {
    return 'success'
  }

  return 'info'
}

function TablaKdd({
  titulo,
  columnas,
  filas,
  nombreArchivo
}) {
  return (
    <div className="card border-0 shadow-sm mb-4">
      <div className="card-body">
        <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h3 className="h6 fw-bold mb-0">
            {titulo} ({filas.length})
          </h3>

          <button
            type="button"
            className="btn btn-outline-primary btn-sm"
            onClick={() => descargarCsv(nombreArchivo, columnas, filas)}
          >
            Descargar CSV
          </button>
        </div>

        {filas.length === 0 ? (
          <p className="text-secondary small mb-0">
            No hay registros para los filtros seleccionados.
          </p>
        ) : (
          <div className="table-responsive">
            <table className="table table-striped table-sm align-middle">
              <thead>
                <tr>
                  {columnas.map((columna) => (
                    <th key={columna.campo} scope="col">
                      {columna.titulo}
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody>
                {filas.map((fila, indice) => (
                  <tr key={`${nombreArchivo}-${indice}`}>
                    {columnas.map((columna) => (
                      <td key={columna.campo}>
                        {textoCelda(fila[columna.campo])}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

function HistorialesKdd({ idInmueble, periodo }) {
  const [reporte, setReporte] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const controlador = new AbortController()

    async function cargarReportes() {
      setCargando(true)
      setError('')
      setReporte(null)

      try {
        const parametros = new URLSearchParams()

        if (idInmueble) {
          parametros.set('idInmueble', idInmueble)
        }

        if (periodo) {
          parametros.set('periodo', periodo)
        }

        const consulta = parametros.toString()

        const ruta = `/api/kdd/reportes${
          consulta ? `?${consulta}` : ''
        }`

        const respuesta = await fetch(ruta, {
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json'
          },
          signal: controlador.signal
        })

        if (!respuesta.ok) {
          throw new Error(
            respuesta.status === 401
              ? 'Tu sesión venció. Inicia sesión nuevamente.'
              : 'No fue posible consultar los historiales KDD.'
          )
        }

        const datos = await respuesta.json()

        if (
          !Array.isArray(datos.pagos) ||
          !Array.isArray(datos.mantenimiento) ||
          !Array.isArray(datos.alertas) ||
          !Array.isArray(datos.patrones)
        ) {
          throw new Error('La API devolvió un reporte incompleto.')
        }

        if (!controlador.signal.aborted) {
          setReporte(datos)
        }
      } catch (errorConsulta) {
        if (!controlador.signal.aborted) {
          setError(errorConsulta.message)
        }
      } finally {
        if (!controlador.signal.aborted) {
          setCargando(false)
        }
      }
    }

    cargarReportes()
    return () => controlador.abort()
  }, [idInmueble, periodo])

  return (
    <section className="mt-2" aria-label="Historiales y reportes KDD">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 className="h5 fw-bold mb-1">
            Historiales, patrones y reportes
          </h2>

          <p className="text-secondary small mb-0">
            Esta es la parte del paso 59: aquí validas tablas, alertas y exportaciones.
          </p>
        </div>

        {reporte && (
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => imprimirInforme(reporte, periodo, idInmueble)}
          >
            Imprimir / Guardar PDF
          </button>
        )}
      </div>

      <div className="kdd-help-box mb-4">
        <h4 className="h6 fw-bold">Qué debes revisar aquí</h4>
        <ol className="small text-secondary">
          <li>Que las tablas cambien según el periodo o inmueble seleccionado.</li>
          <li>Que los pagos, incidencias y alertas coincidan con MySQL.</li>
          <li>Que el CSV descargue el contenido correcto.</li>
          <li>Que “Imprimir / Guardar PDF” genere el reporte final.</li>
        </ol>
      </div>

      {cargando && (
        <div className="alert alert-secondary">
          Cargando historiales...
        </div>
      )}

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      {reporte && !cargando && (
        <>
          <div className="kdd-insights-grid mb-4">
            <div className="kdd-insight">
              <div className="kdd-insight__title">Pagos encontrados</div>
              <div className="kdd-insight__value">{reporte.pagos.length}</div>
              <p className="text-secondary small mb-0">
                Registros de pago dentro del filtro actual.
              </p>
            </div>

            <div className="kdd-insight">
              <div className="kdd-insight__title">Incidencias / mantenimiento</div>
              <div className="kdd-insight__value">{reporte.mantenimiento.length}</div>
              <p className="text-secondary small mb-0">
                Registros operativos relacionados al análisis.
              </p>
            </div>

            <div className="kdd-insight">
              <div className="kdd-insight__title">Alertas de morosidad</div>
              <div className="kdd-insight__value">{reporte.alertas.length}</div>
              <p className="text-secondary small mb-0">
                Reglas descriptivas activadas en el periodo seleccionado.
              </p>
            </div>
          </div>

          <div className="mb-4">
            <h3 className="h6 fw-bold mb-3">Hallazgos KDD</h3>

            {reporte.patrones.length === 0 ? (
              <div className="card border-0 shadow-sm">
                <div className="card-body">
                  <p className="text-secondary small mb-0">
                    No se detectaron alertas con estas reglas y filtros.
                  </p>
                </div>
              </div>
            ) : (
              <div className="kdd-pattern-grid">
                {reporte.patrones.map((patron, indice) => {
                  const tono = colorPatron(patron)

                  return (
                    <div
                      key={`${patron.tipo}-${indice}`}
                      className="kdd-pattern-card"
                    >
                      <span className={`kdd-pattern-badge kdd-pattern-badge--${tono}`}>
                        {patron.tipo || 'Hallazgo'}
                      </span>

                      <h4 className="h6 fw-bold mb-2">
                        {patron.titulo}
                      </h4>

                      <p className="text-secondary small mb-0">
                        {patron.detalle}
                      </p>
                    </div>
                  )
                })}
              </div>
            )}
          </div>

          <TablaKdd
            titulo="Pagos registrados"
            columnas={COLUMNAS_PAGOS}
            filas={reporte.pagos}
            nombreArchivo="kdd_pagos.csv"
          />

          <TablaKdd
            titulo="Incidencias y mantenimiento"
            columnas={COLUMNAS_MANTENIMIENTO}
            filas={reporte.mantenimiento}
            nombreArchivo="kdd_mantenimiento.csv"
          />

          <TablaKdd
            titulo="Alertas descriptivas de morosidad"
            columnas={COLUMNAS_ALERTAS}
            filas={reporte.alertas}
            nombreArchivo="kdd_alertas.csv"
          />

          <div className="alert alert-info small">
            Cada tabla muestra un máximo de {reporte.limitePorLista} registros.
            Los pagos en revisión no equivalen a pagos aprobados.
            Las alertas son descriptivas, no un modelo predictivo entrenado.
            Para guardar el PDF usa el botón “Imprimir / Guardar PDF”.
          </div>
        </>
      )}
    </section>
  )
}

export default HistorialesKdd 