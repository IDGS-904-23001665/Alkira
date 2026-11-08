import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiContratosAdministrativos } from '../../services/contratosAdministrativos.js'
import { exportarEstadoCuentaExcel } from '../../services/exportacionEstadosCuenta.js'
import { exportarEstadoCuentaPdf } from '../../services/exportacionEstadosCuentaPdf.js'

const FORMULARIO_INICIAL = {
  idUnidad: '',
  idInquilino: '',
  fechaInicio: '',
  fechaFin: '',
  montoRenta: '',
  depositoGarantia: '0.00',
  estadoContrato: 'borrador',
}

const NOMBRES_ESTADO = {
  borrador: 'Borrador',
  vigente: 'Vigente',
  finalizado: 'Finalizado',
  cancelado: 'Cancelado',
}

function mostrarMoneda(valor) {
  const cantidad = Number(valor)

  return Number.isFinite(cantidad)
    ? cantidad.toLocaleString('es-MX', {
      style: 'currency',
      currency: 'MXN',
    })
    : '—'
}

function mostrarFecha(valor) {
  return valor ? String(valor).slice(0, 10) : 'Sin definir'
}

export default function ContratosAdministrativos() {
  const [contratos, setContratos] = useState([])
  const [unidades, setUnidades] = useState([])
  const [inquilinos, setInquilinos] = useState([])

  const [formulario, setFormulario] = useState(FORMULARIO_INICIAL)
  const [archivoNuevo, setArchivoNuevo] = useState(null)
  const [reinicioArchivo, setReinicioArchivo] = useState(0)

  const [busqueda, setBusqueda] = useState('')
  const [filtroEstado, setFiltroEstado] = useState('todos')

  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [subiendoId, setSubiendoId] = useState(null)

  const [error, setError] = useState('')
  const [aviso, setAviso] = useState('')

  const [idUnidadCobros, setIdUnidadCobros] = useState('')
  const [cobros, setCobros] = useState([])
  const [consultandoCobros, setConsultandoCobros] = useState(false)
  const [consultaRealizada, setConsultaRealizada] = useState(false)
  const [exportandoExcel, setExportandoExcel] = useState(false)
  const [exportandoPdf, setExportandoPdf] = useState(false)

  const cargarDatos = useCallback(async () => {
    setCargando(true)

    try {
      const [
        respuestaContratos,
        respuestaUnidades,
        respuestaInquilinos,
      ] = await Promise.all([
        apiContratosAdministrativos.listarContratos(),
        apiContratosAdministrativos.listarUnidades(),
        apiContratosAdministrativos.listarInquilinos(),
      ])

      setContratos(respuestaContratos.contratos ?? [])
      setUnidades(respuestaUnidades.unidades ?? [])
      setInquilinos(respuestaInquilinos.inquilinos ?? [])
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible consultar los contratos.'
      )
    } finally {
      setCargando(false)
    }
  }, [])

  useEffect(() => {
    cargarDatos()
  }, [cargarDatos])

  const contratosFiltrados = useMemo(() => {
    const texto = busqueda.trim().toLocaleLowerCase('es')

    return contratos.filter((contrato) => {
      if (
        filtroEstado !== 'todos' &&
        contrato.estadoContrato !== filtroEstado
      ) {
        return false
      }

      const contenido = [
        contrato.idContrato,
        contrato.numeroUnidad,
        contrato.nombreInmueble,
        contrato.nombreInquilino,
        contrato.correoInquilino,
      ].join(' ').toLocaleLowerCase('es')

      return contenido.includes(texto)
    })
  }, [contratos, busqueda, filtroEstado])

  function actualizarCampo(campo, valor) {
    setFormulario((actual) => ({
      ...actual,
      [campo]: valor,
    }))
  }

  async function registrarContrato(evento) {
    evento.preventDefault()

    if (guardando) return

    setError('')
    setAviso('')
    setGuardando(true)

    try {
      const datosContrato = {
        idUnidad: Number(formulario.idUnidad),
        idInquilino: Number(formulario.idInquilino),
        fechaInicio: formulario.fechaInicio,
        fechaFin: formulario.fechaFin || null,
        montoRenta: formulario.montoRenta.trim(),
        depositoGarantia: formulario.depositoGarantia.trim(),
        estadoContrato: formulario.estadoContrato,
      }

      const respuesta =
        await apiContratosAdministrativos.crearContrato(
          datosContrato
        )

      const idContrato = respuesta.contrato.idContrato

      let avisoFinal =
        `Contrato #${idContrato} registrado correctamente.`

      if (archivoNuevo) {
        try {
          await apiContratosAdministrativos.subirDocumento(
            idContrato,
            archivoNuevo
          )

          avisoFinal += ' Documento PDF adjuntado.'
        } catch (errorDocumento) {
          avisoFinal +=
            ` El PDF no se guardó: ${errorDocumento.message}. ` +
            'Puedes adjuntarlo desde la tabla.'
        }
      }

      setFormulario({ ...FORMULARIO_INICIAL })
      setArchivoNuevo(null)
      setReinicioArchivo((actual) => actual + 1)

      setAviso(avisoFinal)
      await cargarDatos()
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible registrar el contrato.'
      )
    } finally {
      setGuardando(false)
    }
  }

  async function adjuntarDocumento(idContrato, archivoPdf) {
    if (!archivoPdf || subiendoId !== null) return

    setError('')
    setAviso('')
    setSubiendoId(idContrato)

    try {
      await apiContratosAdministrativos.subirDocumento(
        idContrato,
        archivoPdf
      )

      setAviso(
        `Documento del contrato #${idContrato} guardado correctamente.`
      )

      await cargarDatos()
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible subir el PDF.'
      )
    } finally {
      setSubiendoId(null)
    }
  }

  async function consultarCobros(evento) {
    evento.preventDefault()

    if (!idUnidadCobros) return

    setError('')
    setConsultandoCobros(true)
    setConsultaRealizada(false)

    try {
      const respuesta =
        await apiContratosAdministrativos.listarCobrosPorUnidad(
          idUnidadCobros
        )

      setCobros(respuesta.cobros ?? [])
      setConsultaRealizada(true)
    } catch (errorSolicitud) {
      setCobros([])
      setError(
        errorSolicitud.message ||
        'No fue posible consultar los cobros.'
      )
    } finally {
      setConsultandoCobros(false)
    }
  }


  async function descargarExcel() {
    if (
      exportandoExcel ||
      !consultaRealizada ||
      cobros.length === 0
    ) {
      return
    }

    const unidadSeleccionada = unidades.find(
      (unidad) =>
        Number(unidad.idUnidad) === Number(idUnidadCobros)
    )

    if (!unidadSeleccionada) {
      setError('No se encontró la unidad seleccionada.')
      return
    }

    setError('')
    setExportandoExcel(true)

    try {
      await exportarEstadoCuentaExcel(
        unidadSeleccionada,
        cobros
      )
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible generar el archivo Excel.'
      )
    } finally {
      setExportandoExcel(false)
    }
  }


  async function descargarPdf() {
    if (
      exportandoPdf ||
      exportandoExcel ||
      !consultaRealizada ||
      cobros.length === 0
    ) {
      return
    }

    const unidadSeleccionada = unidades.find(
      (unidad) =>
        Number(unidad.idUnidad) === Number(idUnidadCobros)
    )

    if (!unidadSeleccionada) {
      setError('No se encontró la unidad seleccionada.')
      return
    }

    setError('')
    setExportandoPdf(true)

    try {
      await exportarEstadoCuentaPdf(
        unidadSeleccionada,
        cobros
      )
    } catch (errorSolicitud) {
      setError(
        errorSolicitud.message ||
        'No fue posible generar el archivo PDF.'
      )
    } finally {
      setExportandoPdf(false)
    }
  }


  return (
    <main className="container-fluid px-0 pb-4">
      <div className="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
          <h1 className="h4 fw-bold mb-1">
            Contratos y estados de cuenta
          </h1>
          <p className="text-secondary small mb-0">
            Registro de contratos, archivos PDF y consulta de cobros por unidad · HU02.4
          </p>
        </div>

        <button
          type="button"
          className="btn btn-outline-primary btn-sm"
          onClick={cargarDatos}
          disabled={cargando || guardando}
        >
          {cargando ? 'Actualizando…' : 'Actualizar datos'}
        </button>
      </div>

      {error && (
        <div role="alert" className="alert alert-danger">
          {error}
        </div>
      )}

      {aviso && (
        <div role="status" className="alert alert-success">
          {aviso}
        </div>
      )}

      <div className="row g-3 mb-4">
        <div className="col-12 col-sm-4">
          <div className="card h-100">
            <div className="card-body">
              <div className="text-secondary small">
                Contratos registrados
              </div>
              <div className="fs-3 fw-bold">
                {contratos.length}
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-4">
          <div className="card h-100">
            <div className="card-body">
              <div className="text-secondary small">
                Vigentes
              </div>
              <div className="fs-3 fw-bold">
                {
                  contratos.filter(
                    (contrato) =>
                      contrato.estadoContrato === 'vigente'
                  ).length
                }
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-4">
          <div className="card h-100">
            <div className="card-body">
              <div className="text-secondary small">
                Sin PDF
              </div>
              <div className="fs-3 fw-bold">
                {
                  contratos.filter(
                    (contrato) =>
                      !Number(contrato.documentoRegistrado)
                  ).length
                }
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="row g-4 mb-4">
        <div className="col-12 col-xl-5">
          <section className="card h-100">
            <div className="card-header bg-white py-3">
              <h2 className="h6 fw-bold mb-1">
                Registrar contrato
              </h2>
              <p className="small text-secondary mb-0">
                Selecciona la unidad y el inquilino.
                El PDF puede adjuntarse ahora o después.
              </p>
            </div>

            <div className="card-body">
              <form
                onSubmit={registrarContrato}
                className="row g-3"
              >
                <div className="col-12">
                  <label
                    htmlFor="contrato-unidad"
                    className="form-label small fw-semibold"
                  >
                    Unidad *
                  </label>

                  <select
                    id="contrato-unidad"
                    required
                    className="form-select"
                    value={formulario.idUnidad}
                    onChange={(evento) =>
                      actualizarCampo(
                        'idUnidad',
                        evento.target.value
                      )
                    }
                  >
                    <option value="">
                      Seleccionar unidad
                    </option>

                    {unidades.map((unidad) => (
                      <option
                        key={unidad.idUnidad}
                        value={unidad.idUnidad}
                      >
                        {unidad.nombreInmueble} · {unidad.numeroUnidad}
                        {' '}({unidad.estadoUnidad})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="col-12">
                  <label
                    htmlFor="contrato-inquilino"
                    className="form-label small fw-semibold"
                  >
                    Inquilino *
                  </label>

                  <select
                    id="contrato-inquilino"
                    required
                    className="form-select"
                    value={formulario.idInquilino}
                    onChange={(evento) =>
                      actualizarCampo(
                        'idInquilino',
                        evento.target.value
                      )
                    }
                  >
                    <option value="">
                      Seleccionar inquilino
                    </option>

                    {inquilinos.map((inquilino) => (
                      <option
                        key={inquilino.idInquilino}
                        value={inquilino.idInquilino}
                      >
                        {inquilino.nombreInquilino} · {inquilino.correo}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="col-6">
                  <label
                    htmlFor="contrato-inicio"
                    className="form-label small fw-semibold"
                  >
                    Inicio *
                  </label>

                  <input
                    id="contrato-inicio"
                    type="date"
                    required
                    className="form-control"
                    value={formulario.fechaInicio}
                    onChange={(evento) =>
                      actualizarCampo(
                        'fechaInicio',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-6">
                  <label
                    htmlFor="contrato-fin"
                    className="form-label small fw-semibold"
                  >
                    Finalización
                  </label>

                  <input
                    id="contrato-fin"
                    type="date"
                    className="form-control"
                    min={formulario.fechaInicio || undefined}
                    value={formulario.fechaFin}
                    onChange={(evento) =>
                      actualizarCampo(
                        'fechaFin',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-6">
                  <label
                    htmlFor="contrato-renta"
                    className="form-label small fw-semibold"
                  >
                    Renta mensual (MXN) *
                  </label>

                  <input
                    id="contrato-renta"
                    type="number"
                    required
                    min="0"
                    max="9999999999.99"
                    step="0.01"
                    className="form-control"
                    placeholder="8500.00"
                    value={formulario.montoRenta}
                    onChange={(evento) =>
                      actualizarCampo(
                        'montoRenta',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-6">
                  <label
                    htmlFor="contrato-deposito"
                    className="form-label small fw-semibold"
                  >
                    Depósito (MXN) *
                  </label>

                  <input
                    id="contrato-deposito"
                    type="number"
                    required
                    min="0"
                    max="9999999999.99"
                    step="0.01"
                    className="form-control"
                    value={formulario.depositoGarantia}
                    onChange={(evento) =>
                      actualizarCampo(
                        'depositoGarantia',
                        evento.target.value
                      )
                    }
                  />
                </div>

                <div className="col-12">
                  <label
                    htmlFor="contrato-estado"
                    className="form-label small fw-semibold"
                  >
                    Estado inicial
                  </label>

                  <select
                    id="contrato-estado"
                    className="form-select"
                    value={formulario.estadoContrato}
                    onChange={(evento) =>
                      actualizarCampo(
                        'estadoContrato',
                        evento.target.value
                      )
                    }
                  >
                    <option value="borrador">
                      Borrador
                    </option>
                    <option value="vigente">
                      Vigente
                    </option>
                  </select>
                </div>

                <div className="col-12">
                  <label
                    htmlFor="contrato-pdf"
                    className="form-label small fw-semibold"
                  >
                    Contrato digital (PDF, opcional)
                  </label>

                  <input
                    key={reinicioArchivo}
                    id="contrato-pdf"
                    type="file"
                    accept=".pdf,application/pdf"
                    className="form-control"
                    onChange={(evento) =>
                      setArchivoNuevo(
                        evento.target.files?.[0] ?? null
                      )
                    }
                  />

                  <div className="form-text">
                    Máximo 12 MB. Se almacenará de forma privada en el backend.
                  </div>
                </div>

                <div className="col-12">
                  <button
                    type="submit"
                    className="btn btn-primary w-100"
                    disabled={guardando || cargando}
                  >
                    {guardando
                      ? 'Registrando…'
                      : 'Registrar contrato'}
                  </button>
                </div>
              </form>
            </div>
          </section>
        </div>

        <div className="col-12 col-xl-7">
          <section className="card h-100">
            <div className="card-header bg-white py-3">
              <h2 className="h6 fw-bold mb-1">
                Contratos registrados
              </h2>

              <p className="small text-secondary mb-0">
                Busca contratos y adjunta documentos pendientes.
              </p>
            </div>

            <div className="card-body pb-2">
              <div className="row g-2 mb-3">
                <div className="col-12 col-md-7">
                  <label
                    htmlFor="contratos-busqueda"
                    className="visually-hidden"
                  >
                    Buscar contrato
                  </label>

                  <input
                    id="contratos-busqueda"
                    className="form-control"
                    placeholder="Buscar unidad, inmueble o inquilino"
                    value={busqueda}
                    onChange={(evento) =>
                      setBusqueda(evento.target.value)
                    }
                  />
                </div>

                <div className="col-12 col-md-5">
                  <label
                    htmlFor="contratos-filtro"
                    className="visually-hidden"
                  >
                    Filtrar por estado
                  </label>

                  <select
                    id="contratos-filtro"
                    className="form-select"
                    value={filtroEstado}
                    onChange={(evento) =>
                      setFiltroEstado(evento.target.value)
                    }
                  >
                    <option value="todos">
                      Todos los estados
                    </option>

                    {Object.entries(NOMBRES_ESTADO).map(
                      ([estado, nombre]) => (
                        <option
                          key={estado}
                          value={estado}
                        >
                          {nombre}
                        </option>
                      )
                    )}
                  </select>
                </div>
              </div>
            </div>

            {cargando ? (
              <p className="text-secondary px-3">
                Cargando contratos…
              </p>
            ) : contratosFiltrados.length === 0 ? (
              <p className="text-secondary px-3">
                No hay contratos que coincidan con los filtros.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0 small">
                  <thead>
                    <tr>
                      <th>Contrato</th>
                      <th>Inquilino / Unidad</th>
                      <th>Vigencia</th>
                      <th>Renta</th>
                      <th>Documento</th>
                    </tr>
                  </thead>

                  <tbody>
                    {contratosFiltrados.map((contrato) => (
                      <tr key={contrato.idContrato}>
                        <td>
                          <strong>
                            #{contrato.idContrato}
                          </strong>

                          <div className="text-secondary">
                            {NOMBRES_ESTADO[
                              contrato.estadoContrato
                            ] || contrato.estadoContrato}
                          </div>
                        </td>

                        <td>
                          <strong>
                            {contrato.nombreInquilino}
                          </strong>

                          <div className="text-secondary">
                            {contrato.nombreInmueble} ·{' '}
                            {contrato.numeroUnidad}
                          </div>
                        </td>

                        <td>
                          {mostrarFecha(
                            contrato.fechaInicio
                          )}

                          <div className="text-secondary">
                            a {mostrarFecha(
                              contrato.fechaFin
                            )}
                          </div>
                        </td>

                        <td>
                          {mostrarMoneda(
                            contrato.montoRenta
                          )}
                        </td>

                        <td>
                          {Number(
                            contrato.documentoRegistrado
                          ) ? (
                            <div className="d-flex flex-column align-items-start gap-2">
                              <span className="badge text-bg-success">
                                PDF registrado
                              </span>
                              <a
                                href={`/api/administracion/contratos/${contrato.idContrato}/documento`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="btn btn-outline-primary btn-sm"
                                aria-label={`Ver PDF del contrato ${contrato.idContrato}`}
                              >
                                Ver PDF
                              </a>
                            </div>
                          ) : (
                            <label className="d-block">
                              <span className="small text-primary fw-semibold">
                                {subiendoId === contrato.idContrato
                                  ? 'Subiendo…'
                                  : 'Adjuntar PDF'}
                              </span>

                              <input
                                type="file"
                                accept=".pdf,application/pdf"
                                className="form-control form-control-sm mt-1"
                                aria-label={`PDF del contrato ${contrato.idContrato}`}
                                disabled={subiendoId !== null}
                                onChange={(evento) => {
                                  const archivoPdf =
                                    evento.target.files?.[0]

                                  if (archivoPdf) {
                                    adjuntarDocumento(
                                      contrato.idContrato,
                                      archivoPdf
                                    )
                                  }

                                  evento.target.value = ''
                                }}
                              />
                            </label>
                          )}
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

      <section className="card">
        <div className="card-header bg-white py-3">
          <h2 className="h6 fw-bold mb-1">
            Historial de cobros por unidad
          </h2>

          <p className="small text-secondary mb-0">
            Consulta estados de cuenta y adeudos vinculados
            a los contratos de una unidad.
          </p>
        </div>

        <div className="card-body">
          <form
            onSubmit={consultarCobros}
            className="row g-2 align-items-end mb-3"
          >
            <div className="col-12 col-md-8">
              <label
                htmlFor="cobros-unidad"
                className="form-label small fw-semibold"
              >
                Unidad
              </label>

              <select
                id="cobros-unidad"
                className="form-select"
                required
                value={idUnidadCobros}
                onChange={(evento) => {
                  setIdUnidadCobros(
                    evento.target.value
                  )
                  setCobros([])
                  setConsultaRealizada(false)
                }}
              >
                <option value="">
                  Seleccionar unidad
                </option>

                {unidades.map((unidad) => (
                  <option
                    key={unidad.idUnidad}
                    value={unidad.idUnidad}
                  >
                    {unidad.nombreInmueble} ·{' '}
                    {unidad.numeroUnidad}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-12 col-md-4">
              <button
                type="submit"
                className="btn btn-outline-primary w-100"
                disabled={
                  !idUnidadCobros ||
                  consultandoCobros
                }
              >
                {consultandoCobros
                  ? 'Consultando…'
                  : 'Consultar cobros'}
              </button>
            </div>
          </form>



          {consultaRealizada && cobros.length > 0 && (
            <div className="d-flex flex-wrap justify-content-end gap-2 mb-3">
              <button
                type="button"
                className="btn btn-success btn-sm"
                onClick={descargarExcel}
                disabled={exportandoExcel || exportandoPdf}
              >
                {exportandoExcel
                  ? 'Generando Excel...'
                  : 'Exportar Excel (.xlsx)'}
              </button>

              <button
                type="button"
                className="btn btn-danger btn-sm"
                onClick={descargarPdf}
                disabled={exportandoPdf || exportandoExcel}
              >
                {exportandoPdf
                  ? 'Generando PDF...'
                  : 'Exportar PDF'}
              </button>
            </div>
          )}





          {consultaRealizada && (
            cobros.length === 0 ? (
              <p className="text-secondary small mb-0">
                Esta unidad no tiene cobros registrados.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table table-striped table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Periodo</th>
                      <th>Concepto</th>
                      <th>Fecha límite</th>
                      <th>Principal</th>
                      <th>Recargo</th>
                      <th>Pagado</th>
                      <th>Pendiente</th>
                      <th>Estado</th>
                    </tr>
                  </thead>

                  <tbody>
                    {cobros.map((cobro) => (
                      <tr key={cobro.idEstadoCuenta}>
                        <td>{cobro.periodo}</td>
                        <td>{cobro.concepto}</td>
                        <td>
                          {mostrarFecha(
                            cobro.fechaLimitePago
                          )}
                        </td>
                        <td>
                          {mostrarMoneda(
                            cobro.montoPrincipal
                          )}
                        </td>
                        <td>
                          {mostrarMoneda(
                            cobro.recargoMora
                          )}
                        </td>
                        <td>
                          {mostrarMoneda(
                            cobro.montoPagado
                          )}
                        </td>
                        <td className="fw-semibold">
                          {mostrarMoneda(
                            cobro.montoPendiente
                          )}
                        </td>
                        <td>{cobro.estado}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          )}
        </div>
      </section>
    </main>
  )
}
