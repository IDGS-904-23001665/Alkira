
import { useEffect, useState } from 'react'
import TendenciaMensualKdd from './TendenciaMensualKdd.jsx'
import HistorialesKdd from './HistorialesKdd.jsx'

function moneda(valor) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN'
  }).format(Number(valor ?? 0))
}

function TarjetaKdd({ titulo, valor }) {
  return (
    <div className="col-12 col-md-6 col-xl-3">
      <div className="card h-100 border-0 shadow-sm">
        <div className="card-body p-4">
          <p className="text-secondary small mb-2">
            {titulo}
          </p>

          <h3 className="h4 fw-bold mb-0">
            {valor}
          </h3>
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
            consultarApi(
              '/api/kdd/indicadores',
              controlador.signal
            ),
            consultarApi(
              '/api/kdd/inmuebles',
              controlador.signal
            )
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

  const ocupado = filtrosActivos &&
    (cargandoFiltros || !filtrados)

  const tarjetasFinancieras = financiero ? [
    [
      'Total facturado',
      moneda(financiero.totalFacturado)
    ],
    [
      'Total aplicado',
      moneda(financiero.totalAplicado)
    ],
    [
      'Saldo pendiente',
      moneda(financiero.totalPendiente)
    ],
    [
      'Saldo vencido',
      moneda(financiero.totalVencido)
    ],
    [
      'Morosidad sobre facturación',
      `${Number(financiero.porcentajeMorosidad).toFixed(2)}%`
    ],
    [
      'Cuentas vencidas',
      String(financiero.cuentasVencidas)
    ]
  ] : []

  const tarjetasOperativas = operativo ? [
    [
      'Incidencias registradas',
      String(operativo.totalIncidencias)
    ],
    [
      'Incidencias urgentes',
      String(operativo.incidenciasUrgentes)
    ],
    [
      'Tickets registrados',
      String(operativo.totalTickets)
    ],
    [
      'Tickets abiertos',
      String(operativo.ticketsAbiertos)
    ],
    [
      'Tickets cerrados',
      String(operativo.ticketsCerrados)
    ],
    [
      'Promedio de resolución',
      operativo.promedioHorasResolucion === null
        ? 'Sin datos'
        : `${Number(operativo.promedioHorasResolucion).toFixed(2)} horas`
    ]
  ] : []

  return (
    <div className="container-fluid px-0">

      <div className="mb-4">
        <h2 className="h5 fw-bold">
          Analítica e Historiales KDD
        </h2>

        <p className="text-secondary small">
          Indicadores, historiales y alertas desde MySQL.
        </p>
      </div>

      <div className="card border-0 shadow-sm mb-4">
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
                onChange={(e) =>
                  actualizarInmueble(e.target.value)
                }
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
                onChange={(e) =>
                  actualizarPeriodo(e.target.value)
                }
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

          <p className="text-secondary small mt-3 mb-0">
            Los filtros se aplican a indicadores, gráfica,
            historiales y alertas.
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
          <section className="mb-5">
            <h3 className="h6 fw-bold mb-3">
              Indicadores financieros
            </h3>

            <div className="row g-3">
              {tarjetasFinancieras.map(([titulo, valor]) => (
                <TarjetaKdd
                  key={titulo}
                  titulo={titulo}
                  valor={valor}
                />
              ))}
            </div>
          </section>

          <TendenciaMensualKdd
            registros={tendencia}
          />

          <section className="mb-4">
            <h3 className="h6 fw-bold mb-3">
              Indicadores de mantenimiento
            </h3>

            <div className="row g-3">
              {tarjetasOperativas.map(([titulo, valor]) => (
                <TarjetaKdd
                  key={titulo}
                  titulo={titulo}
                  valor={valor}
                />
              ))}
            </div>
          </section>
        </>
      )}

      <HistorialesKdd
        idInmueble={idInmueble}
        periodo={periodo}
      />

      <div className="alert alert-info small mt-4">
        Información de prueba. La tendencia muestra saldos
        actuales agrupados por periodo, no una reconstrucción
        de los saldos que existían al cierre de cada mes.

        Las alertas descriptivas no son un modelo
        predictivo entrenado.
      </div>

    </div>
  )
}

export default IndicadoresKdd
