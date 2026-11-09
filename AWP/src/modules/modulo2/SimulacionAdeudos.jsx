import { useState } from 'react'
import { apiSimulacionAdeudos } from '../../services/simulacionAdeudos.js'

const ESTADOS_SIMULACION = {
  generable: {
    nombre: 'Generable',
    clase: 'text-bg-success',
  },
  ya_registrado: {
    nombre: 'Ya registrado',
    clase: 'text-bg-secondary',
  },
  sin_regla: {
    nombre: 'Sin regla activa',
    clase: 'text-bg-warning',
  },
  fuera_vigencia: {
    nombre: 'Fuera de vigencia',
    clase: 'text-bg-secondary',
  },
  pendiente_definicion: {
    nombre: 'Pendiente de definición',
    clase: 'text-bg-warning',
  },
}

function obtenerPeriodoActual() {
  const fechaActual = new Date()

  const anio = fechaActual.getFullYear()
  const mes = String(
    fechaActual.getMonth() + 1
  ).padStart(2, '0')

  return `${anio}-${mes}`
}

function mostrarMoneda(valor) {
  if (valor === null || valor === undefined) {
    return '—'
  }

  const cantidad = Number(valor)

  if (!Number.isFinite(cantidad)) {
    return '—'
  }

  return cantidad.toLocaleString('es-MX', {
    style: 'currency',
    currency: 'MXN',
  })
}

export default function SimulacionAdeudos() {
  const [periodo, setPeriodo] = useState(obtenerPeriodoActual)
  const [simulacion, setSimulacion] = useState(null)

  const [cargando, setCargando] = useState(false)
  const [error, setError] = useState('')

  async function ejecutarSimulacion(evento) {
    evento.preventDefault()

    if (cargando) {
      return
    }

    setCargando(true)
    setError('')
    setSimulacion(null)

    try {
      const resultado = await apiSimulacionAdeudos
        .simularAdeudos(periodo)

      setSimulacion(resultado)
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible realizar la simulación.'
      )
    } finally {
      setCargando(false)
    }
  }

  return (
    <section className="card mt-4">
      <div className="card-header bg-white py-3">
        <h2 className="h6 fw-bold mb-1">
          Simulación de adeudos mensuales
        </h2>

        <p className="text-secondary small mb-0">
          Consulta los cobros que corresponderían
          a cada contrato antes de generarlos.
        </p>
      </div>

      <div className="card-body">
        <form
          className="row g-3 align-items-end mb-4"
          onSubmit={ejecutarSimulacion}
        >
          <div className="col-12 col-md-5">
            <label
              htmlFor="periodo-simulacion"
              className="form-label small fw-semibold"
            >
              Periodo a consultar
            </label>

            <input
              id="periodo-simulacion"
              type="month"
              className="form-control"
              value={periodo}
              required
              disabled={cargando}
              onChange={(evento) =>
                setPeriodo(evento.target.value)
              }
            />
          </div>

          <div className="col-12 col-md-auto">
            <button
              type="submit"
              className="btn btn-primary"
              disabled={cargando || !periodo}
            >
              {cargando
                ? 'Simulando...'
                : 'Simular adeudos'}
            </button>
          </div>
        </form>

        <div className="alert alert-info small">
          <strong>Modo de simulación:</strong> este proceso
          solamente consulta información. No genera
          adeudos, no modifica montos y no aplica recargos.
        </div>

        {error && (
          <div
            className="alert alert-danger"
            role="alert"
          >
            {error}
          </div>
        )}

        {cargando && (
          <p
            className="text-secondary small"
            role="status"
          >
            Consultando contratos y reglas activas...
          </p>
        )}

        {simulacion && (
          <div aria-live="polite">
            <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
              <h3 className="h6 fw-bold mb-0">
                Resultados del periodo {simulacion.periodo}
              </h3>

              <span className="badge text-bg-info">
                Solo lectura
              </span>
            </div>

            <div className="row g-3 mb-4">
              <div className="col-6 col-lg-3">
                <div className="border rounded p-3 h-100">
                  <div className="small text-secondary">
                    Reglas activas
                  </div>

                  <div className="fs-4 fw-bold">
                    {simulacion.reglasActivas}
                  </div>
                </div>
              </div>

              <div className="col-6 col-lg-3">
                <div className="border rounded p-3 h-100">
                  <div className="small text-secondary">
                    Contratos evaluados
                  </div>

                  <div className="fs-4 fw-bold">
                    {simulacion.contratosEvaluados}
                  </div>
                </div>
              </div>

              <div className="col-6 col-lg-3">
                <div className="border rounded p-3 h-100">
                  <div className="small text-secondary">
                    Cobros generables
                  </div>

                  <div className="fs-4 fw-bold text-success">
                    {simulacion.generables}
                  </div>
                </div>
              </div>

              <div className="col-6 col-lg-3">
                <div className="border rounded p-3 h-100">
                  <div className="small text-secondary">
                    Contratos omitidos
                  </div>

                  <div className="fs-4 fw-bold">
                    {simulacion.omitidos}
                  </div>
                </div>
              </div>
            </div>

            {simulacion.pendientesDefinicion > 0 && (
              <div className="alert alert-warning small">
                Hay {simulacion.pendientesDefinicion}
                {' '}contrato(s) con reglas pendientes
                de definición. Revisa sus fechas antes
                de habilitar la generación de cobros.
              </div>
            )}

            {simulacion.reglasActivas === 0 && (
              <div className="alert alert-warning small">
                No existen reglas de cobro activas.
                Puedes registrarlas o activarlas en
                la sección superior.
              </div>
            )}

            <h3 className="h6 fw-bold mb-3">
              Detalle por contrato
            </h3>

            {simulacion.detalle.length === 0 ? (
              <p className="text-secondary small">
                No se encontraron contratos para evaluar.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table table-striped table-hover align-middle small">
                  <thead>
                    <tr>
                      <th>Contrato</th>
                      <th>Tipo de unidad</th>
                      <th>Cuota</th>
                      <th>Emisión</th>
                      <th>Límite</th>
                      <th>Resultado</th>
                      <th>Motivo</th>
                    </tr>
                  </thead>

                  <tbody>
                    {simulacion.detalle.map((registro) => {
                      const estado =
                        ESTADOS_SIMULACION[
                          registro.resultado
                        ] ?? {
                          nombre: registro.resultado,
                          clase: 'text-bg-secondary',
                        }

                      return (
                        <tr key={registro.idContrato}>
                          <td className="fw-semibold">
                            #{registro.idContrato}
                          </td>

                          <td className="text-capitalize">
                            {registro.tipoUnidad}
                          </td>

                          <td>
                            {mostrarMoneda(
                              registro.montoPrincipal
                            )}
                          </td>

                          <td>
                            {registro.fechaEmision ?? '—'}
                          </td>

                          <td>
                            {registro.fechaLimitePago ?? '—'}
                          </td>

                          <td>
                            <span
                              className={`badge ${estado.clase}`}
                            >
                              {estado.nombre}
                            </span>
                          </td>

                          <td>
                            {registro.motivo}
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}

            <p className="text-secondary small mb-0 mt-3">
              La simulación representa los datos al
              momento de la consulta. Ejecuta otra
              simulación si modificas las reglas de cobro.
            </p>
          </div>
        )}
      </div>
    </section>
  )
}
