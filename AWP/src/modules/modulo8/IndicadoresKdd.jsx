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

function porcentajeSeguro(valor) {
  const numero = Number(valor ?? 0)
  if (!Number.isFinite(numero)) return 0
  return Math.max(0, Math.min(100, numero))
}

function porcentaje(parte, total) {
  const a = Number(parte ?? 0)
  const b = Number(total ?? 0)

  if (!Number.isFinite(a) || !Number.isFinite(b) || b <= 0) {
    return 0
  }

  return (a / b) * 100
}

function TarjetaResumen({
  titulo,
  valor,
  ayuda,
  tono = 'primary'
}) {
  return (
    <div className={`kdd-stat-card kdd-stat-card--${tono}`}>
      <p className="kdd-stat-card__label">{titulo}</p>
      <div className="kdd-stat-card__value">{valor}</div>
      {ayuda && <p className="kdd-stat-card__help">{ayuda}</p>}
    </div>
  )
}

function BalanceFinanciero({ financiero }) {
  if (!financiero) return null

  const totalFacturado = Number(financiero.totalFacturado ?? 0)
  const totalAplicado = Number(financiero.totalAplicado ?? 0)
  const totalPendiente = Number(financiero.totalPendiente ?? 0)
  const totalVencido = Number(financiero.totalVencido ?? 0)
  const porcentajeAplicado = porcentaje(totalAplicado, totalFacturado)
  const porcentajePendiente = Math.max(
    0,
    100 - porcentajeAplicado
  )
  const porcentajeMorosidad = porcentajeSeguro(
    financiero.porcentajeMorosidad
  )

  const donutStyle = {
    background:
      totalFacturado > 0
        ? `conic-gradient(
            #198754 0% ${porcentajeAplicado}%,
            #f0ad00 ${porcentajeAplicado}% 100%
          )`
        : '#edf2f8'
  }

  return (
    <section className="kdd-section-card p-4">
      <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h3 className="h6 fw-bold mb-1">
            Resumen visual del periodo
          </h3>
          <p className="text-secondary small mb-0">
            Aquí se entiende rápido cuánto se cobró y cuánto sigue pendiente.
          </p>
        </div>

        <div className="kdd-inline-chips">
          <span className="kdd-chip">Facturado: {moneda(totalFacturado)}</span>
          <span className="kdd-chip">Pendiente: {moneda(totalPendiente)}</span>
          <span className="kdd-chip">Vencido: {moneda(totalVencido)}</span>
        </div>
      </div>

      <div className="kdd-chart-grid">
        <div className="kdd-chart-card">
          <div className="kdd-chart-card__title">
            Distribución del total facturado
          </div>
          <div className="kdd-chart-card__subtitle">
            La dona compara lo aplicado contra lo que sigue pendiente.
          </div>

          <div className="kdd-balance-layout">
            <div className="kdd-donut-wrap">
              <div className="kdd-donut" style={donutStyle}>
                <div className="kdd-donut__content">
                  <span className="kdd-donut__percent">
                    {porcentajeAplicado.toFixed(1)}%
                  </span>
                  <span className="kdd-donut__label">Aplicado</span>
                </div>
              </div>
            </div>

            <div className="kdd-legend">
              <div className="kdd-legend__item">
                <span className="kdd-legend__dot kdd-legend__dot--success" />
                <span>Aplicado</span>
                <strong>{moneda(totalAplicado)}</strong>
              </div>

              <div className="kdd-legend__item">
                <span className="kdd-legend__dot kdd-legend__dot--warning" />
                <span>Pendiente</span>
                <strong>{moneda(totalPendiente)}</strong>
              </div>

              <div className="kdd-legend__item">
                <span className="kdd-legend__dot kdd-legend__dot--danger" />
                <span>Vencido</span>
                <strong>{moneda(totalVencido)}</strong>
              </div>

              <div className="mt-3 small text-secondary">
                <strong>{porcentajePendiente.toFixed(1)}%</strong>{' '}
                del total facturado sigue sin aplicarse.
              </div>
            </div>
          </div>
        </div>

        <div className="kdd-chart-card">
          <div className="kdd-chart-card__title">
            Medidor de morosidad
          </div>
          <div className="kdd-chart-card__subtitle">
            Este porcentaje muestra qué parte de lo facturado está vencida.
          </div>

          <div className="display-6 fw-bold mb-2">
            {porcentajeMorosidad.toFixed(2)}%
          </div>

          <div className="kdd-meter">
            <div className="kdd-meter__track">
              <div
                className="kdd-meter__fill"
                style={{ width: `${porcentajeMorosidad}%` }}
              />
            </div>

            <div className="kdd-meter__labels">
              <span>Bajo</span>
              <span>Medio</span>
              <span>Alto</span>
            </div>
          </div>

          <div className="mt-4">
            <p className="mb-1 small text-secondary">
              Interpretación rápida
            </p>

            <ul className="small mb-0 text-secondary">
              <li>Si baja este valor, la cobranza mejoró.</li>
              <li>Si sube, hay más saldo vencido acumulado.</li>
              <li>En octubre debería verse mejor que en septiembre.</li>
            </ul>
          </div>
        </div>
      </div>
    </section>
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

function IndicadoresKdd() {
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
        const [respuestaGeneral, respuestaInmuebles] =
          await Promise.all([
            consultarApi('/api/kdd/indicadores', controlador.signal),
            consultarApi('/api/kdd/inmuebles', controlador.signal)
          ])

        if (
          !respuestaGeneral.financiero ||
          !respuestaGeneral.operativo ||
          !Array.isArray(respuestaGeneral.tendenciaMensual) ||
          !Array.isArray(respuestaInmuebles.inmuebles)
        ) {
          throw new Error(
            'La API KDD devolvió información incompleta.'
          )
        }

        if (!controlador.signal.aborted) {
          setGenerales(respuestaGeneral)
          setInmuebles(respuestaInmuebles.inmuebles)
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
    if (!idInmueble && !periodo) return

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

      const consulta = parametros.toString()

      try {
        const [finanzas, operaciones] = await Promise.all([
          consultarApi(`/api/kdd/financiero?${consulta}`, controlador.signal),
          consultarApi(`/api/kdd/operativo?${consulta}`, controlador.signal)
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
  }, [idInmueble, periodo])

  function actualizarInmueble(valor) {
    setFiltrados(null)
    setErrorFiltros('')
    setIdInmueble(valor)
  }

  function actualizarPeriodo(valor) {
    setFiltrados(null)
    setErrorFiltros('')
    setPeriodo(valor)
  }

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
        Cargando indicadores KDD...
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

  const ocupado = filtrosActivos && (cargandoFiltros || !filtrados)

  return (
    <div className="container-fluid px-0 kdd-shell">
      <div className="card kdd-hero">
        <div className="card-body p-4 p-lg-5">
          <div className="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div>
              <h2 className="h3 fw-bold mb-2">
                Analítica e Historiales KDD
              </h2>
              <p className="kdd-hero__subtitle">
                Tablero más claro para leer cobranza, morosidad, mantenimiento e historiales.
              </p>
            </div>

            <div className="kdd-inline-chips">
              <span className="kdd-chip">Módulo 8</span>
              <span className="kdd-chip">Datos desde MySQL</span>
              <span className="kdd-chip">CSV + PDF</span>
            </div>
          </div>
        </div>
      </div>

      <div className="kdd-guide-grid">
        <div className="kdd-guide-card">
          <span className="kdd-guide-card__tag">Paso 1</span>
          <h3 className="h6 fw-bold">Filtra lo que quieres analizar</h3>
          <p className="text-secondary small mb-0">
            Elige inmueble y/o periodo. Todo el tablero cambia con esos filtros.
          </p>
        </div>

        <div className="kdd-guide-card">
          <span className="kdd-guide-card__tag">Paso 2</span>
          <h3 className="h6 fw-bold">Lee los indicadores y gráficas</h3>
          <p className="text-secondary small mb-0">
            Primero revisa montos, luego la tendencia mensual y después mantenimiento.
          </p>
        </div>

        <div className="kdd-guide-card">
          <span className="kdd-guide-card__tag">Paso 59 aclarado</span>
          <h3 className="h6 fw-bold">Qué revisar abajo</h3>
          <p className="text-secondary small mb-0">
            Baja a “Historiales, patrones y reportes”, verifica tablas, alertas, CSV y PDF.
          </p>
        </div>
      </div>

      <div className="kdd-section-card">
        <div className="card-body p-4">
          <h3 className="h6 fw-bold mb-3">
            Filtros del análisis
          </h3>

          <div className="row g-3 align-items-end">
            <div className="col-12 col-md-5">
              <label
                htmlFor="filtroInmuebleKdd"
                className="form-label"
              >
                Inmueble
              </label>

              <select
                id="filtroInmuebleKdd"
                className="form-select"
                value={idInmueble}
                onChange={(e) => actualizarInmueble(e.target.value)}
              >
                <option value="">Todos los inmuebles</option>

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
                htmlFor="filtroPeriodoKdd"
                className="form-label"
              >
                Periodo
              </label>

              <input
                id="filtroPeriodoKdd"
                type="month"
                className="form-control"
                value={periodo}
                onChange={(e) => actualizarPeriodo(e.target.value)}
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

          <p className="kdd-filter-note mt-3 mb-0">
            Los filtros se aplican a indicadores, gráficas, historiales, alertas y exportaciones.
          </p>
        </div>
      </div>

      {errorFiltros && (
        <div className="alert alert-danger" role="alert">
          {errorFiltros}
        </div>
      )}

      {ocupado && !errorFiltros && (
        <div className="alert alert-secondary">
          Aplicando filtros...
        </div>
      )}

      {!ocupado && !errorFiltros && (
        <>
          {financiero && (
            <>
              <section>
                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <h3 className="h5 fw-bold mb-0">
                    Indicadores financieros
                  </h3>
                  <span className="text-secondary small">
                    Resumen general de cobranza y morosidad.
                  </span>
                </div>

                <div className="kdd-summary-grid">
                  <TarjetaResumen
                    titulo="Total facturado"
                    valor={moneda(financiero.totalFacturado)}
                    ayuda="Monto total generado en los estados de cuenta."
                    tono="primary"
                  />
                  <TarjetaResumen
                    titulo="Total aplicado"
                    valor={moneda(financiero.totalAplicado)}
                    ayuda="Monto que ya quedó cubierto o aplicado."
                    tono="success"
                  />
                  <TarjetaResumen
                    titulo="Saldo pendiente"
                    valor={moneda(financiero.totalPendiente)}
                    ayuda="Monto que aún falta por cubrir."
                    tono="warning"
                  />
                  <TarjetaResumen
                    titulo="Saldo vencido"
                    valor={moneda(financiero.totalVencido)}
                    ayuda="Parte pendiente que ya está vencida."
                    tono="danger"
                  />
                  <TarjetaResumen
                    titulo="Morosidad sobre facturación"
                    valor={`${porcentajeSeguro(financiero.porcentajeMorosidad).toFixed(2)}%`}
                    ayuda="Porcentaje del total facturado que está vencido."
                    tono="info"
                  />
                  <TarjetaResumen
                    titulo="Cuentas vencidas"
                    valor={String(financiero.cuentasVencidas ?? 0)}
                    ayuda="Número de estados de cuenta con atraso."
                    tono="warning"
                  />
                </div>
              </section>

              <BalanceFinanciero financiero={financiero} />
            </>
          )}

          <TendenciaMensualKdd registros={tendencia} />

          {operativo && (
            <section>
              <div className="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h3 className="h5 fw-bold mb-0">
                  Indicadores de mantenimiento
                </h3>
                <span className="text-secondary small">
                  Estado de incidencias, tickets y tiempos de resolución.
                </span>
              </div>

              <div className="kdd-summary-grid">
                <TarjetaResumen
                  titulo="Incidencias registradas"
                  valor={String(operativo.totalIncidencias ?? 0)}
                  ayuda="Incidencias detectadas en el filtro actual."
                  tono="primary"
                />
                <TarjetaResumen
                  titulo="Incidencias urgentes"
                  valor={String(operativo.incidenciasUrgentes ?? 0)}
                  ayuda="Reportes marcados con alta urgencia."
                  tono="danger"
                />
                <TarjetaResumen
                  titulo="Tickets registrados"
                  valor={String(operativo.totalTickets ?? 0)}
                  ayuda="Total de tickets creados."
                  tono="info"
                />
                <TarjetaResumen
                  titulo="Tickets abiertos"
                  valor={String(operativo.ticketsAbiertos ?? 0)}
                  ayuda="Tickets todavía en proceso o asignados."
                  tono="warning"
                />
                <TarjetaResumen
                  titulo="Tickets cerrados"
                  valor={String(operativo.ticketsCerrados ?? 0)}
                  ayuda="Tickets ya concluidos."
                  tono="success"
                />
                <TarjetaResumen
                  titulo="Promedio de resolución"
                  valor={
                    operativo.promedioHorasResolucion === null
                      ? 'Sin datos'
                      : `${Number(operativo.promedioHorasResolucion).toFixed(2)} horas`
                  }
                  ayuda="Tiempo promedio para cerrar tickets."
                  tono="primary"
                />
              </div>
            </section>
          )}
        </>
      )}

      <HistorialesKdd
        idInmueble={idInmueble}
        periodo={periodo}
      />

      <div className="alert alert-info small mt-1">
        Información de prueba para el proyecto universitario. Las alertas mostradas son descriptivas y se basan en reglas, no en un modelo predictivo entrenado.
      </div>
    </div>
  )
}

export default IndicadoresKdd