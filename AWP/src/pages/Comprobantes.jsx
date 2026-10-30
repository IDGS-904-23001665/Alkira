import { useEffect, useState } from 'react'
import './Comprobantes.css'

const etiquetasEstado = {
  pendiente: 'Pendiente',
  aprobado: 'Aprobado',
  rechazado: 'Rechazado',
  preregistrado: 'Preregistrado',
  en_revision: 'En verificación'
}

function Comprobantes() {
  const [comprobantes, setComprobantes] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [filtro, setFiltro] = useState('todos')
  const [comprobanteSeleccionado, setComprobanteSeleccionado] = useState(null)
  const [procesandoRevision, setProcesandoRevision] = useState(false)
  const [mensajeRevision, setMensajeRevision] = useState('')

  useEffect(() => {
    let activo = true

    async function cargarComprobantes() {
      try {
        const respuesta = await fetch('/api/comprobantes', {
          credentials: 'include'
        })

        if (!respuesta.ok) {
          throw new Error('No se pudieron cargar los comprobantes.')
        }

        const datos = await respuesta.json()
        if (activo) {
          setComprobantes(Array.isArray(datos.comprobantes) ? datos.comprobantes : [])
        }
      } catch (err) {
        if (activo) setError(err.message)
      } finally {
        if (activo) setCargando(false)
      }
    }

    cargarComprobantes()
    return () => { activo = false }
  }, [])

  const pendientes = comprobantes.filter(c => c.estado_revision === 'pendiente').length
  const aprobados = comprobantes.filter(c => c.estado_revision === 'aprobado').length
  const rechazados = comprobantes.filter(c => c.estado_revision === 'rechazado').length
  const comprobantesFiltrados = comprobantes.filter(
    c => filtro === 'todos' || c.estado_revision === filtro
  )

  function formatoMoneda(monto) {
    return Number(monto).toLocaleString('es-MX', {
      style: 'currency',
      currency: 'MXN'
    })
  }

  function abrirDetalle(comprobante) {
    setComprobanteSeleccionado(comprobante)
    setMensajeRevision('')
  }

  function cerrarDetalle() {
    setComprobanteSeleccionado(null)
    setMensajeRevision('')
  }

  // HU03.2: confirmar el preregistro de una transacción ya existente.
  async function confirmarPreregistro() {
    if (
      !comprobanteSeleccionado ||
      procesandoRevision ||
      comprobanteSeleccionado.estado_revision !== 'pendiente' ||
      comprobanteSeleccionado.estado_pago !== 'preregistrado'
    ) {
      return
    }

    const idComprobante = Number(comprobanteSeleccionado.id_comprobante)

    if (!window.confirm(
      '¿Confirmas el preregistro de esta transacción y su envío a verificación? Esta acción no aprueba el pago.'
    )) {
      return
    }

    setProcesandoRevision(true)
    setMensajeRevision('')

    try {
      const respuesta = await fetch('/api/comprobantes/preregistrar', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ idComprobante })
      })

      const datos = await respuesta.json()

      if (!respuesta.ok) {
        throw new Error(datos.mensaje || 'No se pudo confirmar el preregistro.')
      }

      const nuevaRespuesta = await fetch('/api/comprobantes', {
        credentials: 'include'
      })

      if (!nuevaRespuesta.ok) {
        setMensajeRevision(
          'El preregistro se guardó, pero no se pudo actualizar la bandeja. Actualiza la página.'
        )
        return
      }

      const nuevosDatos = await nuevaRespuesta.json()
      const nuevaLista = Array.isArray(nuevosDatos.comprobantes)
        ? nuevosDatos.comprobantes
        : []

      setComprobantes(nuevaLista)
      setComprobanteSeleccionado(
        nuevaLista.find(c => Number(c.id_comprobante) === idComprobante) ?? null
      )
      setMensajeRevision(datos.mensaje || 'Preregistro confirmado y enviado a verificación.')
    } catch (err) {
      setMensajeRevision(err.message)
    } finally {
      setProcesandoRevision(false)
    }
  }

  const tipoSeleccionado = String(comprobanteSeleccionado?.tipo_archivo || '').toUpperCase()
  const urlArchivo = comprobanteSeleccionado
    ? `/api/comprobantes/archivo?id=${encodeURIComponent(comprobanteSeleccionado.id_comprobante)}`
    : ''

  return (
    <div className="hu03">
      <header className="hu03-cabecera-pagina">
        <div>
          <h1>Comprobantes de pago</h1>
          <p>Consulta los archivos recibidos y confirma las transacciones para su verificación.</p>
        </div>
        <span className="hu03-distintivo">HU03.2 · Administración</span>
      </header>

      <section className="hu03-indicadores" aria-label="Resumen de comprobantes">
        <div className="hu03-indicador">
          <span>Total recibidos</span>
          <strong>{comprobantes.length}</strong>
          <small>Documentos registrados</small>
        </div>
        <div className="hu03-indicador">
          <span>Pendientes de revisión</span>
          <strong>{pendientes}</strong>
          <small>Por atender</small>
        </div>
        <div className="hu03-indicador">
          <span>Aprobados</span>
          <strong>{aprobados}</strong>
          <small>Historial de estados</small>
        </div>
        <div className="hu03-indicador">
          <span>Rechazados</span>
          <strong>{rechazados}</strong>
          <small>Historial de estados</small>
        </div>
      </section>

      <section className="hu03-panel" aria-label="Bandeja de comprobantes">
        <div className="hu03-panel-cabecera">
          <div>
            <h2>Bandeja de comprobantes</h2>
            <p>Documentos recibidos de los residentes de ALKIRA</p>
          </div>
          <label className="hu03-filtro" htmlFor="filtro-estado">
            <span>Estado</span>
            <select
              id="filtro-estado"
              value={filtro}
              onChange={e => setFiltro(e.target.value)}
            >
              <option value="todos">Todos los estados</option>
              <option value="pendiente">Pendientes</option>
              <option value="aprobado">Aprobados</option>
              <option value="rechazado">Rechazados</option>
            </select>
          </label>
        </div>

        {cargando && <p className="hu03-aviso" role="status">Cargando comprobantes...</p>}
        {error && <p className="hu03-aviso hu03-aviso-error" role="alert">{error}</p>}
        {mensajeRevision && <p className="hu03-aviso" role="status">{mensajeRevision}</p>}

        {!cargando && !error && (
          <div className="hu03-tabla-contenedor">
            <table className="hu03-tabla">
              <thead>
                <tr>
                  <th>Residente</th>
                  <th>Monto</th>
                  <th>Fecha de carga</th>
                  <th>Estado</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                {comprobantesFiltrados.map(comprobante => (
                  <tr key={comprobante.id_comprobante}>
                    <td>
                      <strong>{comprobante.nombres} {comprobante.apellidos}</strong>
                      <small>Comprobante #{comprobante.id_comprobante}</small>
                    </td>
                    <td className="hu03-monto">{formatoMoneda(comprobante.monto)}</td>
                    <td>{comprobante.fecha_carga}</td>
                    <td>
                      <span className={`hu03-estado hu03-estado-${comprobante.estado_revision}`}>
                        {etiquetasEstado[comprobante.estado_revision] || comprobante.estado_revision}
                      </span>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="hu03-enlace"
                        onClick={() => abrirDetalle(comprobante)}
                        aria-label={`Ver detalle del comprobante ${comprobante.id_comprobante}`}
                      >
                        Ver detalle →
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {comprobantesFiltrados.length === 0 && (
              <p className="hu03-vacio">No hay comprobantes con este estado.</p>
            )}
          </div>
        )}
      </section>

      {comprobanteSeleccionado && (
        <section className="hu03-panel hu03-detalle" aria-label="Detalle del comprobante">
          <div className="hu03-panel-cabecera">
            <div>
              <h2>Detalle del comprobante #{comprobanteSeleccionado.id_comprobante}</h2>
              <p>Información de la transferencia y documento adjunto</p>
            </div>
            <button type="button" className="hu03-boton-secundario" onClick={cerrarDetalle}>
              Cerrar detalle
            </button>
          </div>

          <div className="hu03-detalle-grid">
            <div className="hu03-dato">
              <small>Residente</small>
              <strong>{comprobanteSeleccionado.nombres} {comprobanteSeleccionado.apellidos}</strong>
            </div>
            <div className="hu03-dato">
              <small>Monto</small>
              <strong>{formatoMoneda(comprobanteSeleccionado.monto)}</strong>
            </div>
            <div className="hu03-dato">
              <small>Método de pago</small>
              <strong>{comprobanteSeleccionado.metodo_pago || 'No disponible'}</strong>
            </div>
            <div className="hu03-dato">
              <small>Referencia bancaria</small>
              <strong>{comprobanteSeleccionado.referencia_bancaria || 'Sin referencia'}</strong>
            </div>
            <div className="hu03-dato">
              <small>Fecha de carga</small>
              <strong>{comprobanteSeleccionado.fecha_carga}</strong>
            </div>
            <div className="hu03-dato">
              <small>Estado del comprobante</small>
              <span className={`hu03-estado hu03-estado-${comprobanteSeleccionado.estado_revision}`}>
                {etiquetasEstado[comprobanteSeleccionado.estado_revision] || comprobanteSeleccionado.estado_revision}
              </span>
            </div>
            <div className="hu03-dato">
              <small>Estado de la transacción</small>
              <strong>{etiquetasEstado[comprobanteSeleccionado.estado_pago] || comprobanteSeleccionado.estado_pago || 'No disponible'}</strong>
            </div>
            <div className="hu03-dato">
              <small>Archivo registrado</small>
              <strong className="hu03-ruta-archivo">{comprobanteSeleccionado.ruta_archivo || 'Sin archivo'}</strong>
            </div>
          </div>

          {comprobanteSeleccionado.ruta_archivo && (
            <div className="hu03-documento">
              <div className="hu03-documento-cabecera">
                <div>
                  <h3>Vista previa del documento</h3>
                  <p>Comprobante protegido · {tipoSeleccionado || 'Archivo'}</p>
                </div>
                <a
                  className="hu03-boton-secundario"
                  href={urlArchivo}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Abrir en otra pestaña ↗
                </a>
              </div>
              <div className="hu03-visor">
                {tipoSeleccionado === 'PDF' ? (
                  <iframe
                    title={`Vista previa del comprobante ${comprobanteSeleccionado.id_comprobante}`}
                    src={urlArchivo}
                    loading="lazy"
                  />
                ) : tipoSeleccionado === 'JPG' || tipoSeleccionado === 'PNG' ? (
                  <img
                    src={urlArchivo}
                    alt={`Comprobante de pago ${comprobanteSeleccionado.id_comprobante}`}
                    loading="lazy"
                  />
                ) : (
                  <p>Formato sin vista previa disponible.</p>
                )}
              </div>
            </div>
          )}

          {comprobanteSeleccionado.estado_revision === 'pendiente' &&
            comprobanteSeleccionado.estado_pago === 'preregistrado' && (
              <div className="hu03-acciones-preregistro">
                <div>
                  <strong>Listo para enviar a verificación</strong>
                  <p>Esta acción inicia la verificación; no aprueba ni rechaza el pago.</p>
                </div>
                <button
                  type="button"
                  className="hu03-boton-primario"
                  onClick={confirmarPreregistro}
                  disabled={procesandoRevision}
                >
                  {procesandoRevision
                    ? 'Procesando...'
                    : 'Confirmar preregistro'}
                </button>
              </div>
            )}
        </section>
      )}
    </div>
  )
}

export default Comprobantes
