import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiReglasCobro } from '../../services/reglasCobro.js'
import SimulacionAdeudos from './SimulacionAdeudos.jsx'

const FORMULARIO_INICIAL = {
  idConcepto: '',
  alcance: 'general',
  montoCuota: '',
  tipoRecargo: 'fijo',
  valorRecargo: '0.00',
  diaCorte: '1',
  diaLimitePago: '10',
  activo: false,
}

const ALCANCES = {
  general: 'General',
  departamento: 'Departamento',
  casa: 'Casa',
  local: 'Local',
  oficina: 'Oficina',
  otro: 'Otro',
}

const TIPOS_RECARGO = {
  fijo: 'Monto fijo (MXN)',
  porcentaje: 'Porcentaje (%)',
}

function mostrarMoneda(valor) {
  const cantidad = Number(valor)

  if (!Number.isFinite(cantidad)) {
    return '—'
  }

  return cantidad.toLocaleString('es-MX', {
    style: 'currency',
    currency: 'MXN',
  })
}

function formularioDesdeRegla(regla) {
  return {
    idConcepto: String(regla.idConcepto),
    alcance: regla.alcance,
    montoCuota: String(regla.montoCuota),
    tipoRecargo: regla.tipoRecargo,
    valorRecargo: String(regla.valorRecargo),
    diaCorte: String(regla.diaCorte),
    diaLimitePago: String(regla.diaLimitePago),
    activo: Number(regla.activo) === 1,
  }
}

export default function ReglasCobro() {
  const [reglas, setReglas] = useState([])
  const [conceptos, setConceptos] = useState([])

  const [formulario, setFormulario] = useState(
    FORMULARIO_INICIAL
  )

  const [idReglaEditando, setIdReglaEditando] = useState(null)

  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  const [busqueda, setBusqueda] = useState('')
  const [filtroEstado, setFiltroEstado] = useState('todos')

  const [error, setError] = useState('')
  const [aviso, setAviso] = useState('')

  const cargarDatos = useCallback(async () => {
    setCargando(true)

    try {
      const [
        respuestaReglas,
        respuestaConceptos,
      ] = await Promise.all([
        apiReglasCobro.listarReglas(),
        apiReglasCobro.listarConceptos(),
      ])

      setReglas(respuestaReglas.reglas ?? [])
      setConceptos(respuestaConceptos.conceptos ?? [])
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible consultar las reglas.'
      )
    } finally {
      setCargando(false)
    }
  }, [])

  useEffect(() => {
    cargarDatos()
  }, [cargarDatos])

  const resumen = useMemo(() => ({
    total: reglas.length,
    activas: reglas.filter(
      (regla) => Number(regla.activo) === 1
    ).length,
    inactivas: reglas.filter(
      (regla) => Number(regla.activo) === 0
    ).length,
  }), [reglas])

  const reglasFiltradas = useMemo(() => {
    const texto = busqueda.trim().toLocaleLowerCase('es')

    return reglas.filter((regla) => {
      const activa = Number(regla.activo) === 1

      if (filtroEstado === 'activas' && !activa) {
        return false
      }

      if (filtroEstado === 'inactivas' && activa) {
        return false
      }

      const contenido = [
        regla.idReglaCobro,
        regla.nombreConcepto,
        regla.alcance,
      ].join(' ').toLocaleLowerCase('es')

      return contenido.includes(texto)
    })
  }, [reglas, busqueda, filtroEstado])

  function actualizarCampo(campo, valor) {
    setFormulario((actual) => ({
      ...actual,
      [campo]: valor,
    }))
  }

  function limpiarFormulario() {
    setFormulario({ ...FORMULARIO_INICIAL })
    setIdReglaEditando(null)
    setError('')
  }

  function editarRegla(regla) {
    setIdReglaEditando(Number(regla.idReglaCobro))
    setFormulario(formularioDesdeRegla(regla))
    setError('')
    setAviso('')
  }

  async function guardarRegla(evento) {
    evento.preventDefault()

    if (guardando) {
      return
    }

    setError('')
    setAviso('')
    setGuardando(true)

    try {
      const datosRegla = {
        idConcepto: Number(formulario.idConcepto),
        alcance: formulario.alcance,
        montoCuota: formulario.montoCuota.trim(),
        tipoRecargo: formulario.tipoRecargo,
        valorRecargo: formulario.valorRecargo.trim(),
        diaCorte: Number(formulario.diaCorte),
        diaLimitePago: Number(formulario.diaLimitePago),
        activo: formulario.activo ? 1 : 0,
      }

      if (idReglaEditando === null) {
        await apiReglasCobro.crearRegla(datosRegla)

        setAviso(
          'Regla de cobro registrada correctamente.'
        )
      } else {
        await apiReglasCobro.actualizarRegla(
          idReglaEditando,
          datosRegla
        )

        setAviso(
          `Regla #${idReglaEditando} actualizada correctamente.`
        )
      }

      setFormulario({ ...FORMULARIO_INICIAL })
      setIdReglaEditando(null)

      await cargarDatos()
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible guardar la regla de cobro.'
      )
    } finally {
      setGuardando(false)
    }
  }

  return (
    <main className="container-fluid px-0 pb-4">
      <div className="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
          <h1 className="h4 fw-bold mb-1">
            Configuración de cuotas y recargos
          </h1>

          <p className="text-secondary small mb-0">
            Administración de reglas de mantenimiento · HU02.3
          </p>
        </div>

        <button
          type="button"
          className="btn btn-outline-primary btn-sm"
          onClick={() => {
            setError('')
            setAviso('')
            cargarDatos()
          }}
          disabled={cargando || guardando}
        >
          {cargando ? 'Actualizando…' : 'Actualizar datos'}
        </button>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      {aviso && (
        <div className="alert alert-success" role="status">
          {aviso}
        </div>
      )}

      <div className="row g-3 mb-4">
        <div className="col-12 col-sm-4">
          <div className="card h-100">
            <div className="card-body">
              <div className="text-secondary small">
                Reglas registradas
              </div>
              <div className="fs-3 fw-bold">
                {resumen.total}
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-4">
          <div className="card h-100">
            <div className="card-body">
              <div className="text-secondary small">
                Activas
              </div>
              <div className="fs-3 fw-bold text-success">
                {resumen.activas}
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-4">
          <div className="card h-100">
            <div className="card-body">
              <div className="text-secondary small">
                Inactivas
              </div>
              <div className="fs-3 fw-bold">
                {resumen.inactivas}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="row g-4">
        <div className="col-12 col-xl-5">
          <section className="card">
            <div className="card-header bg-white py-3">
              <h2 className="h6 fw-bold mb-1">
                {idReglaEditando === null
                  ? 'Registrar regla de cobro'
                  : `Editar regla #${idReglaEditando}`}
              </h2>

              <p className="small text-secondary mb-0">
                Define la cuota, el recargo y las fechas.
              </p>
            </div>

            <div className="card-body">
              <form
                className="row g-3"
                onSubmit={guardarRegla}
              >
                <div className="col-12">
                  <label
                    htmlFor="regla-concepto"
                    className="form-label small fw-semibold"
                  >
                    Concepto de mantenimiento *
                  </label>

                  <select
                    id="regla-concepto"
                    className="form-select"
                    required
                    value={formulario.idConcepto}
                    onChange={(evento) =>
                      actualizarCampo(
                        'idConcepto',
                        evento.target.value
                      )
                    }
                  >
                    <option value="">
                      Seleccionar concepto
                    </option>

                    {conceptos.map((concepto) => (
                      <option
                        key={concepto.idConcepto}
                        value={concepto.idConcepto}
                      >
                        {concepto.nombre}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="col-12">
                  <label
                    htmlFor="regla-alcance"
                    className="form-label small fw-semibold"
                  >
                    Aplicar a *
                  </label>

                  <select
                    id="regla-alcance"
                    className="form-select"
                    value={formulario.alcance}
                    onChange={(evento) =>
                      actualizarCampo(
                        'alcance',
                        evento.target.value
                      )
                    }
                  >
                    {Object.entries(ALCANCES).map(
                      ([valor, nombre]) => (
                        <option key={valor} value={valor}>
                          {nombre}
                        </option>
                      )
                    )}
                  </select>

                  <div className="form-text">
                    General aplica cuando no existe una regla
                    específica para el tipo de unidad.
                  </div>
                </div>

                <div className="col-12">
                  <label
                    htmlFor="regla-monto"
                    className="form-label small fw-semibold"
                  >
                    Cuota mensual (MXN) *
                  </label>

                  <input
                    id="regla-monto"
                    className="form-control"
                    type="number"
                    min="0"
                    max="9999999999.99"
                    step="0.01"
                    required
                    placeholder="800.00"
                    value={formulario.montoCuota}
                    onChange={(evento) =>
                      actualizarCampo(
                        'montoCuota',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-6">
                  <label
                    htmlFor="regla-tipo-recargo"
                    className="form-label small fw-semibold"
                  >
                    Tipo de recargo *
                  </label>

                  <select
                    id="regla-tipo-recargo"
                    className="form-select"
                    value={formulario.tipoRecargo}
                    onChange={(evento) =>
                      actualizarCampo(
                        'tipoRecargo',
                        evento.target.value
                      )
                    }
                  >
                    {Object.entries(TIPOS_RECARGO).map(
                      ([valor, nombre]) => (
                        <option key={valor} value={valor}>
                          {nombre}
                        </option>
                      )
                    )}
                  </select>
                </div>

                <div className="col-6">
                  <label
                    htmlFor="regla-valor-recargo"
                    className="form-label small fw-semibold"
                  >
                    Valor del recargo *
                  </label>

                  <input
                    id="regla-valor-recargo"
                    className="form-control"
                    type="number"
                    min="0"
                    max={
                      formulario.tipoRecargo === 'porcentaje'
                        ? '100'
                        : '9999999999.99'
                    }
                    step="0.01"
                    required
                    value={formulario.valorRecargo}
                    onChange={(evento) =>
                      actualizarCampo(
                        'valorRecargo',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-6">
                  <label
                    htmlFor="regla-corte"
                    className="form-label small fw-semibold"
                  >
                    Día de corte *
                  </label>

                  <input
                    id="regla-corte"
                    className="form-control"
                    type="number"
                    min="1"
                    max="31"
                    step="1"
                    required
                    value={formulario.diaCorte}
                    onChange={(evento) =>
                      actualizarCampo(
                        'diaCorte',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-6">
                  <label
                    htmlFor="regla-limite"
                    className="form-label small fw-semibold"
                  >
                    Día límite de pago *
                  </label>

                  <input
                    id="regla-limite"
                    className="form-control"
                    type="number"
                    min="1"
                    max="31"
                    step="1"
                    required
                    value={formulario.diaLimitePago}
                    onChange={(evento) =>
                      actualizarCampo(
                        'diaLimitePago',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-12">
                  <div className="form-check form-switch">
                    <input
                      id="regla-activa"
                      className="form-check-input"
                      type="checkbox"
                      checked={formulario.activo}
                      onChange={(evento) =>
                        actualizarCampo(
                          'activo',
                          evento.target.checked
                        )
                      }
                    />

                    <label
                      htmlFor="regla-activa"
                      className="form-check-label"
                    >
                      Regla activa
                    </label>
                  </div>

                  <div className="form-text">
                    Una regla inactiva se conserva en el
                    sistema, pero no se utilizará para
                    generar nuevos adeudos.
                  </div>
                </div>

                <div className="col-12">
                  <button
                    type="submit"
                    className="btn btn-primary w-100"
                    disabled={
                      guardando ||
                      cargando ||
                      conceptos.length === 0
                    }
                  >
                    {guardando
                      ? 'Guardando…'
                      : idReglaEditando === null
                        ? 'Registrar regla'
                        : 'Guardar cambios'}
                  </button>
                </div>

                {idReglaEditando !== null && (
                  <div className="col-12">
                    <button
                      type="button"
                      className="btn btn-outline-secondary w-100"
                      onClick={limpiarFormulario}
                      disabled={guardando}
                    >
                      Cancelar edición
                    </button>
                  </div>
                )}
              </form>
            </div>
          </section>
        </div>

        <div className="col-12 col-xl-7">
          <section className="card h-100">
            <div className="card-header bg-white py-3">
              <h2 className="h6 fw-bold mb-1">
                Reglas registradas
              </h2>

              <p className="small text-secondary mb-0">
                Consulta y modifica las configuraciones
                existentes.
              </p>
            </div>

            <div className="card-body pb-2">
              <div className="row g-2 mb-3">
                <div className="col-12 col-md-7">
                  <input
                    className="form-control"
                    aria-label="Buscar reglas"
                    placeholder="Buscar concepto o alcance"
                    value={busqueda}
                    onChange={(evento) =>
                      setBusqueda(evento.target.value)
                    }
                  />
                </div>

                <div className="col-12 col-md-5">
                  <select
                    className="form-select"
                    aria-label="Filtrar reglas por estado"
                    value={filtroEstado}
                    onChange={(evento) =>
                      setFiltroEstado(evento.target.value)
                    }
                  >
                    <option value="todos">
                      Todos los estados
                    </option>
                    <option value="activas">
                      Activas
                    </option>
                    <option value="inactivas">
                      Inactivas
                    </option>
                  </select>
                </div>
              </div>
            </div>

            {cargando ? (
              <p className="text-secondary px-3">
                Cargando reglas…
              </p>
            ) : reglasFiltradas.length === 0 ? (
              <p className="text-secondary px-3">
                No hay reglas que coincidan con los filtros.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0 small">
                  <thead>
                    <tr>
                      <th>Concepto</th>
                      <th>Alcance</th>
                      <th>Cuota</th>
                      <th>Recargo</th>
                      <th>Fechas</th>
                      <th>Estado</th>
                      <th>Acción</th>
                    </tr>
                  </thead>

                  <tbody>
                    {reglasFiltradas.map((regla) => (
                      <tr key={regla.idReglaCobro}>
                        <td>
                          <strong>
                            {regla.nombreConcepto}
                          </strong>

                          <div className="text-secondary">
                            Regla #{regla.idReglaCobro}
                          </div>
                        </td>

                        <td>
                          {ALCANCES[regla.alcance]
                            || regla.alcance}
                        </td>

                        <td className="fw-semibold">
                          {mostrarMoneda(regla.montoCuota)}
                        </td>

                        <td>
                          {regla.tipoRecargo === 'fijo'
                            ? mostrarMoneda(regla.valorRecargo)
                            : `${regla.valorRecargo}%`}
                        </td>

                        <td>
                          Corte: {regla.diaCorte}

                          <div className="text-secondary">
                            Límite: {regla.diaLimitePago}
                          </div>
                        </td>

                        <td>
                          {Number(regla.activo) === 1 ? (
                            <span className="badge text-bg-success">
                              Activa
                            </span>
                          ) : (
                            <span className="badge text-bg-secondary">
                              Inactiva
                            </span>
                          )}
                        </td>

                        <td>
                          <button
                            type="button"
                            className="btn btn-outline-primary btn-sm"
                            onClick={() => editarRegla(regla)}
                            disabled={guardando}
                          >
                            Editar
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>
      </div>

      
      {/* HU02.3: simulación mensual sin generar cobros */}
      <SimulacionAdeudos />

      <div className="alert alert-info small mt-4 mb-0">
        <strong>Importante:</strong> la simulación permite
        consultar los adeudos que corresponderían a cada
        contrato, pero todavía no genera cobros reales.
        Los estados de cuenta existentes no se modifican.
      </div>
    </main>
  )
}

