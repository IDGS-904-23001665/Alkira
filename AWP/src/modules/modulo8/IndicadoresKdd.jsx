
import { useEffect, useState } from 'react'
import './Kdd.css'
import TendenciaMensualKdd from './TendenciaMensualKdd.jsx'
import HistorialesKdd from './HistorialesKdd.jsx'

function moneda(valor) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN'
  }).format(Number(valor ?? 0))
}

function Tarjeta({ titulo, valor, nota, tono = 'primary' }) {
  return (
    <div className={`kdd-stat-card kdd-stat-card--${tono}`}>
      <p className="kdd-stat-card__label">{titulo}</p>
      <div className="kdd-stat-card__value">{valor}</div>
      {nota && <p className="kdd-stat-card__help">{nota}</p>}
    </div>
  )
}

function DistribucionCobranza({ financiero }) {
  const total = Number(financiero.totalFacturado ?? 0)
  const aplicado = Number(financiero.totalAplicado ?? 0)

  const porcentaje = total > 0
    ? Math.max(0, Math.min(100, aplicado / total * 100))
    : 0

  return (
    <div className="kdd-section-card p-4">
      <h3 className="h6 fw-bold mb-1">
        ¿Cuánto se ha cobrado?
      </h3>

      <p className="text-secondary small mb-3">
        Distribución del importe facturado en los filtros seleccionados.
      </p>

      <div className="kdd-balance-layout">
        <div className="kdd-donut-wrap">
          <div
            className="kdd-donut"
            style={{
              background: total > 0
                ? `conic-gradient(#198754 0% ${porcentaje}%, #f0ad00 ${porcentaje}% 100%)`
                : '#edf2f8'
            }}
            role="img"
            aria-label={`${porcentaje.toFixed(1)} por ciento aplicado`}
          >
            <div className="kdd-donut__content">
              <span className="kdd-donut__percent">
                {porcentaje.toFixed(1)}%
              </span>
              <span className="kdd-donut__label">
                Aplicado
              </span>
            </div>
          </div>
        </div>

        <div className="kdd-legend">
          <div className="kdd-legend__item">
            <span className="kdd-legend__dot kdd-legend__dot--success" />
            <span>Aplicado</span>
            <strong>{moneda(financiero.totalAplicado)}</strong>
          </div>

          <div className="kdd-legend__item">
            <span className="kdd-legend__dot kdd-legend__dot--warning" />
            <span>Pendiente</span>
            <strong>{moneda(financiero.totalPendiente)}</strong>
          </div>

          <p className="text-secondary small mb-0">
            El pendiente incluye saldos que aún no han vencido.
          </p>
        </div>
      </div>
    </div>
  )
}

async function consultarApi(ruta, senal) {
  const respuesta = await fetch(ruta, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json'
    },
    signal: senal
  })

  if (!respuesta.ok) {
    throw new Error(
      respuesta.status === 401
        ? 'Tu sesión venció. Inicia sesión nuevamente.'
        : `No fue posible consultar KDD (HTTP ${respuesta.status}).`
    )
  }

  return respuesta.json()
}

const SECCIONES = [
  { clave: 'resumen', texto: 'Resumen' },
  { clave: 'cobranza', texto: 'Cobranza' },
  { clave: 'mantenimiento', texto: 'Mantenimiento' },
  { clave: 'reportes', texto: 'Reportes' }
]

function IndicadoresKdd() {
  const [vista, setVista] = useState('resumen')

  const [generales, setGenerales] = useState(null)
  const [inmuebles, setInmuebles] = useState([])

  const [idInmueble, setIdInmueble] = useState('')
  const [periodo, setPeriodo] = useState('')
  const [filtrados, setFiltrados] = useState(null)

  const [cargandoInicial, setCargandoInicial] = useState(true)
  const [cargandoFiltros, setCargandoFiltros] = useState(false)

  const [errorInicial, setErrorInicial] = useState('')
  const [errorFiltros, setErrorFiltros] = useState('')

  useEffect(() => {
    const controlador = new AbortController()

    async function cargar() {
      try {
        const [resumen, lista] = await Promise.all([
          consultarApi('/api/kdd/indicadores', controlador.signal),
          consultarApi('/api/kdd/inmuebles', controlador.signal)
        ])

        if (
          !resumen.financiero ||
          !resumen.operativo ||
          !Array.isArray(resumen.tendenciaMensual) ||
          !Array.isArray(lista.inmuebles)
        ) {
          throw new Error(
            'La API KDD devolvió información incompleta.'
          )
        }

        if (!controlador.signal.aborted) {
          setGenerales(resumen)
          setInmuebles(lista.inmuebles)
        }
      } catch (error) {
        if (!controlador.signal.aborted) {
          setErrorInicial(error.message)
        }
      } finally {
        if (!controlador.signal.aborted) {
          setCargandoInicial(false)
        }
      }
    }

    cargar()

    return () => controlador.abort()
  }, [])

  const filtrosActivos = Boolean(idInmueble || periodo)

  useEffect(() => {
    if (!filtrosActivos) return

    const controlador = new AbortController()

    async function cargarFiltros() {
      setCargandoFiltros(true)
      setErrorFiltros('')

      const parametros = new URLSearchParams()

      if (idInmueble) {
        parametros.set('idInmueble', idInmueble)
      }

      if (periodo) {
        parametros.set('periodo', periodo)
      }

      try {
        const consulta = parametros.toString()

        const [finanzas, operaciones] = await Promise.all([
          consultarApi(
            `/api/kdd/financiero?${consulta}`,
            controlador.signal
          ),
          consultarApi(
            `/api/kdd/operativo?${consulta}`,
            controlador.signal
          )
        ])

        if (
          !finanzas.financiero ||
          !Array.isArray(finanzas.tendenciaMensual) ||
          !operaciones.operativo
        ) {
          throw new Error(
            'La API no devolvió los filtros KDD esperados.'
          )
        }

        if (!controlador.signal.aborted) {
          setFiltrados({
            financiero: finanzas.financiero,
            tendenciaMensual: finanzas.tendenciaMensual,
            operativo: operaciones.operativo
          })
        }
      } catch (error) {
        if (!controlador.signal.aborted) {
          setErrorFiltros(error.message)
        }
      } finally {
        if (!controlador.signal.aborted) {
          setCargandoFiltros(false)
        }
      }
    }

    cargarFiltros()

    return () => controlador.abort()
  }, [idInmueble, periodo, filtrosActivos])

  function limpiarFiltros() {
    setIdInmueble('')
    setPeriodo('')
    setFiltrados(null)
    setErrorFiltros('')
    setCargandoFiltros(false)
  }

  if (cargandoInicial) {
    return (
      <div className="alert alert-secondary">
        Cargando KDD...
      </div>
    )
  }

  if (errorInicial) {
    return (
      <div className="alert alert-danger" role="alert">
        {errorInicial}
      </div>
    )
  }

  if (!generales) return null

  const datos = filtrosActivos ? filtrados : generales

  const financiero = datos?.financiero
  const operativo = datos?.operativo
  const tendencia = datos?.tendenciaMensual ?? []

  const ocupado = filtrosActivos &&
    (cargandoFiltros || !filtrados)

  const totalTickets = Number(operativo?.totalTickets ?? 0)
  const cerrados = Number(operativo?.ticketsCerrados ?? 0)

  const porcentajeCierre = totalTickets > 0
    ? Math.max(0, Math.min(100, cerrados / totalTickets * 100))
    : 0

  return (
    <div className="container-fluid px-0 kdd-shell">

      <header className="kdd-hero p-4">
        <h2 className="h4 fw-bold mb-1">
          Analítica KDD
        </h2>

        <p className="kdd-hero__subtitle">
          Revisa lo importante y consulta los detalles cuando los necesites.
        </p>
      </header>

      <nav
        className="d-flex flex-wrap gap-2"
        aria-label="Secciones de analítica"
      >
        {SECCIONES.map((seccion) => (
          <button
            key={seccion.clave}
            type="button"
            className={`btn ${
              vista === seccion.clave
                ? 'btn-primary'
                : 'btn-outline-secondary'
            }`}
            aria-pressed={vista === seccion.clave}
            onClick={() => setVista(seccion.clave)}
          >
            {seccion.texto}
          </button>
        ))}
      </nav>

      <div className="kdd-section-card p-3">
        <div className="row g-3 align-items-end">

          <div className="col-12 col-md-5">
            <label
              className="form-label small"
              htmlFor="filtroInmuebleKdd"
            >
              Inmueble
            </label>

            <select
              id="filtroInmuebleKdd"
              className="form-select"
              value={idInmueble}
              onChange={(evento) => {
                setFiltrados(null)
                setIdInmueble(evento.target.value)
              }}
            >
              <option value="">
                Todos los inmuebles
              </option>

              {inmuebles.map((inmueble) => (
                <option
                  key={inmueble.idInmueble}
                  value={inmueble.idInmueble}
                >
                  {inmueble.nombreInmueble}
                </option>
              ))}
            </select>
          </div>

          <div className="col-12 col-md-4">
            <label
              className="form-label small"
              htmlFor="filtroPeriodoKdd"
            >
              Mes de facturación
            </label>

            <input
              id="filtroPeriodoKdd"
              type="month"
              className="form-control"
              value={periodo}
              onChange={(evento) => {
                setFiltrados(null)
                setPeriodo(evento.target.value)
              }}
            />
          </div>

          <div className="col-12 col-md-3">
            <button
              type="button"
              className="btn btn-outline-secondary w-100"
              onClick={limpiarFiltros}
            >
              Limpiar filtros
            </button>
          </div>
        </div>
      </div>

      {errorFiltros && (
        <div className="alert alert-danger" role="alert">
          {errorFiltros}
        </div>
      )}

      {ocupado && !errorFiltros && (
        <div className="alert alert-secondary" role="status">
          Aplicando filtros...
        </div>
      )}

      {!ocupado &&
        !errorFiltros &&
        vista === 'resumen' &&
        financiero &&
        operativo && (
          <>
            <section aria-label="Indicadores principales">
              <h3 className="h6 fw-bold mb-3">
                Situación actual
              </h3>

              <div className="kdd-summary-grid">
                <Tarjeta
                  titulo="Saldo vencido"
                  valor={moneda(financiero.totalVencido)}
                  tono="danger"
                />

                <Tarjeta
                  titulo="Morosidad"
                  valor={`${Number(
                    financiero.porcentajeMorosidad ?? 0
                  ).toFixed(2)}%`}
                  tono="warning"
                />

                <Tarjeta
                  titulo="Total aplicado"
                  valor={moneda(financiero.totalAplicado)}
                  tono="success"
                />

                <Tarjeta
                  titulo="Tickets abiertos"
                  valor={String(operativo.ticketsAbiertos ?? 0)}
                  tono="primary"
                />
              </div>
            </section>

            <TendenciaMensualKdd registros={tendencia} />

            <p className="text-secondary small mb-0">
              Para ver unidades con atrasos, entra a{' '}
              <strong>Reportes</strong>. Para consultar los
              importes completos, entra a{' '}
              <strong>Cobranza</strong>.
            </p>
          </>
        )}

      {!ocupado &&
        !errorFiltros &&
        vista === 'cobranza' &&
        financiero && (
          <>
            <h3 className="h6 fw-bold mb-0">
              Detalle de cobranza
            </h3>

            <div className="kdd-summary-grid">
              <Tarjeta
                titulo="Facturado"
                valor={moneda(financiero.totalFacturado)}
              />

              <Tarjeta
                titulo="Aplicado"
                valor={moneda(financiero.totalAplicado)}
                tono="success"
              />

              <Tarjeta
                titulo="Pendiente"
                valor={moneda(financiero.totalPendiente)}
                tono="warning"
              />

              <Tarjeta
                titulo="Vencido"
                valor={moneda(financiero.totalVencido)}
                tono="danger"
              />
            </div>

            <DistribucionCobranza financiero={financiero} />

            <p className="text-secondary small mb-0">
              Las unidades con prioridad de seguimiento
              están en <strong>Reportes</strong>.
            </p>
          </>
        )}

      {!ocupado &&
        !errorFiltros &&
        vista === 'mantenimiento' &&
        operativo && (
          <>
            <h3 className="h6 fw-bold mb-0">
              Estado de mantenimiento
            </h3>

            <div className="kdd-summary-grid">
              <Tarjeta
                titulo="Tickets abiertos"
                valor={String(operativo.ticketsAbiertos ?? 0)}
                tono="warning"
              />

              <Tarjeta
                titulo="Tickets cerrados"
                valor={String(operativo.ticketsCerrados ?? 0)}
                tono="success"
              />

              <Tarjeta
                titulo="Tiempo promedio de resolución"
                valor={
                  operativo.promedioHorasResolucion == null
                    ? 'Sin datos'
                    : `${Number(
                        operativo.promedioHorasResolucion
                      ).toFixed(2)} h`
                }
                tono="primary"
              />
            </div>

            <div className="kdd-section-card p-4">
              <div className="d-flex justify-content-between mb-2">
                <strong className="small">
                  Tickets cerrados
                </strong>

                <span className="small">
                  {cerrados} de {totalTickets}
                </span>
              </div>

              <div className="progress" style={{ height: '14px' }}>
                <div
                  className="progress-bar bg-success"
                  role="progressbar"
                  style={{
                    width: `${porcentajeCierre}%`
                  }}
                  aria-valuenow={porcentajeCierre}
                  aria-valuemin="0"
                  aria-valuemax="100"
                  aria-label="Porcentaje de tickets cerrados"
                />
              </div>

              <details className="mt-3">
                <summary
                  className="small fw-semibold"
                  style={{ cursor: 'pointer' }}
                >
                  Ver más indicadores de mantenimiento
                </summary>

                <p className="small text-secondary mt-2 mb-0">
                  Incidencias: {operativo.totalIncidencias ?? 0}
                  {' · '}
                  Urgentes: {operativo.incidenciasUrgentes ?? 0}
                  {' · '}
                  Tickets registrados: {operativo.totalTickets ?? 0}
                </p>
              </details>
            </div>
          </>
        )}

      {vista === 'reportes' && (
        <HistorialesKdd
          idInmueble={idInmueble}
          periodo={periodo}
        />
      )}

      <p className="text-secondary small mb-0">
        Los saldos se agrupan por periodo de facturación.
        Las prioridades de cobranza son reglas descriptivas,
        no predicciones de IA.
      </p>

    </div>
  )
}

export default IndicadoresKdd
