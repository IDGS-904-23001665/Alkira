import { useEffect, useMemo, useState } from 'react'
import { apiCierreMantenimiento } from '../../services/cierreMantenimiento.js'
import './CierreMantenimientoDialog.css'

const FORMULARIO_INICIAL = { descripcion: '', cantidad: '1', costoUnitario: '0' }

function formatoMoneda(valor) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency', currency: 'MXN', minimumFractionDigits: 2,
  }).format(Number(valor) || 0)
}

export default function CierreMantenimientoDialog({ ticket, onCancelar, onCerrado }) {
  const soloLectura = ticket.estado === 'cerrado'
  const [materiales, setMateriales] = useState([{ ...FORMULARIO_INICIAL }])
  const [sinMateriales, setSinMateriales] = useState(false)
  const [comentarioCierre, setComentarioCierre] = useState('')
  const [confirmarCierre, setConfirmarCierre] = useState(false)
  const [guardando, setGuardando] = useState(false)
  const [cargando, setCargando] = useState(soloLectura)
  const [error, setError] = useState('')
  const [detalle, setDetalle] = useState(null)

  useEffect(() => {
    if (!soloLectura) return
    let vigente = true
    apiCierreMantenimiento.obtenerMateriales(ticket.idTicket)
      .then((respuesta) => { if (vigente) setDetalle(respuesta) })
      .catch((errorSolicitud) => { if (vigente) setError(errorSolicitud.message) })
      .finally(() => { if (vigente) setCargando(false) })
    return () => { vigente = false }
  }, [soloLectura, ticket.idTicket])

  const total = useMemo(() => materiales.reduce((suma, material) => {
    const cantidad = Number(material.cantidad)
    const costoUnitario = Number(material.costoUnitario)
    if (!Number.isFinite(cantidad) || !Number.isFinite(costoUnitario)) return suma
    return suma + Math.round(cantidad * costoUnitario * 100) / 100
  }, 0), [materiales])

  function actualizarMaterial(indice, campo, valor) {
    setConfirmarCierre(false)
    setMateriales((anteriores) => anteriores.map((material, posicion) =>
      posicion === indice ? { ...material, [campo]: valor } : material
    ))
  }

  function agregarMaterial() {
    setMateriales((actuales) => [...actuales, { ...FORMULARIO_INICIAL }])
    setConfirmarCierre(false)
  }

  function eliminarMaterial(indice) {
    setMateriales((actuales) => actuales.filter((_, posicion) => posicion !== indice))
    setConfirmarCierre(false)
  }

  async function enviar(evento) {
    evento.preventDefault()
    if (!confirmarCierre || guardando) return
    setError('')
    if (!sinMateriales && materiales.length === 0) {
      setError('Agrega al menos un material o marca que no se utilizaron.');
      return
    }
    if (sinMateriales && materiales.length > 0) {
      setError('Si no se utilizaron materiales, elimina los renglones registrados.');
      return
    }
    setGuardando(true)
    try {
      await apiCierreMantenimiento.cerrarTicket(ticket.idTicket, {
        sinMateriales,
        comentarioCierre: comentarioCierre.trim(),
        materiales: sinMateriales ? [] : materiales.map((material) => ({
          descripcion: material.descripcion.trim(),
          cantidad: String(material.cantidad).trim(),
          costoUnitario: String(material.costoUnitario).trim(),
        })),
      })
      await onCerrado()
    } catch (errorSolicitud) {
      setError(errorSolicitud.message)
    } finally {
      setGuardando(false)
    }
  }

  return (
    <div className="alkira-mant-modal-fondo">
      <section className="alkira-mant-modal alkira-cierre-modal" role="dialog" aria-modal="true" aria-labelledby="alkira-cierre-titulo">
        <div className="alkira-mant-modal-cabecera">
          <div>
            <h2 id="alkira-cierre-titulo">{soloLectura ? 'Desglose de materiales' : 'Cierre administrativo'}</h2>
            <p>Ticket #{ticket.idTicket} · {ticket.titulo}</p>
          </div>
          <button type="button" className="btn-close" aria-label="Cerrar" onClick={onCancelar} disabled={guardando} />
        </div>

        {soloLectura ? (
          <div className="alkira-cierre-contenido">
            {cargando && <p>Consultando materiales registrados…</p>}
            {error && <div className="alert alert-danger" role="alert">{error}</div>}
            {detalle && (
              <>
                <p className="text-secondary small mb-0">Fecha de cierre: {detalle.ticket.fechaCierre || 'No registrada'}</p>
                <div className="table-responsive">
                  <table className="table table-sm align-middle">
                    <thead><tr><th>Material</th><th>Cantidad</th><th>Costo unitario</th><th>Subtotal</th></tr></thead>
                    <tbody>
                      {detalle.materiales.length === 0 ? (
                        <tr><td colSpan={4}>No se utilizaron materiales.</td></tr>
                      ) : detalle.materiales.map((material) => (
                        <tr key={material.idMaterial}>
                          <td>{material.descripcion}</td><td>{material.cantidad}</td>
                          <td>{formatoMoneda(material.costoUnitario)}</td>
                          <td>{formatoMoneda(material.subtotal)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="alkira-cierre-total">Total de materiales: {formatoMoneda(detalle.ticket.costoTotal)}</p>
              </>
            )}
            <div className="alkira-mant-acciones"><button type="button" className="btn btn-secondary" onClick={onCancelar}>Cerrar</button></div>
          </div>
        ) : (
          <form className="alkira-cierre-contenido" onSubmit={enviar}>
            <p className="alkira-mant-nota">El residente ya firmó su conformidad. El cierre es definitivo: después no se podrán editar los materiales desde esta pantalla.</p>
            <div className="alkira-cierre-encabezado">
              <strong>Materiales y refacciones utilizadas</strong>
              <button type="button" className="btn btn-outline-primary btn-sm" onClick={agregarMaterial} disabled={guardando || sinMateriales || materiales.length >= 40}>Agregar material</button>
            </div>
            {!sinMateriales && materiales.map((material, indice) => (
              <div className="alkira-cierre-material" key={indice}>
                <label className="alkira-cierre-descripcion">
                  <span>Descripción</span>
                  <input className="form-control" maxLength={180} minLength={3} required value={material.descripcion} onChange={(evento) => actualizarMaterial(indice, 'descripcion', evento.target.value)} placeholder="Ej. Tubo PVC 1/2 pulgada" disabled={guardando} />
                </label>
                <label>
                  <span>Cantidad</span>
                  <input className="form-control" type="number" min="0.01" max="9999.99" step="0.01" required value={material.cantidad} onChange={(evento) => actualizarMaterial(indice, 'cantidad', evento.target.value)} disabled={guardando} />
                </label>
                <label>
                  <span>Costo unitario (MXN)</span>
                  <input className="form-control" type="number" min="0" max="999999.99" step="0.01" required value={material.costoUnitario} onChange={(evento) => actualizarMaterial(indice, 'costoUnitario', evento.target.value)} disabled={guardando} />
                </label>
                <div className="alkira-cierre-renglon-acciones">
                  <span>{formatoMoneda(Number(material.cantidad) * Number(material.costoUnitario))}</span>
                  <button className="btn btn-outline-danger btn-sm" type="button" onClick={() => eliminarMaterial(indice)} disabled={guardando}>Quitar</button>
                </div>
              </div>
            ))}
            <label className="alkira-cierre-checkbox">
              <input type="checkbox" checked={sinMateriales} disabled={guardando} onChange={(evento) => {
                setSinMateriales(evento.target.checked)
                setMateriales(evento.target.checked ? [] : [{ ...FORMULARIO_INICIAL }])
                setConfirmarCierre(false)
              }}/>
              No se utilizaron materiales ni refacciones
            </label>
            <p className="alkira-cierre-total">Total de materiales: {formatoMoneda(sinMateriales ? 0 : total)}</p>
            <label className="alkira-cierre-comentario">
              <span>Observaciones del cierre (opcional)</span>
              <textarea className="form-control" rows={3} maxLength={1000} value={comentarioCierre} onChange={(evento) => setComentarioCierre(evento.target.value)} disabled={guardando} />
            </label>
            <label className="alkira-cierre-checkbox alkira-cierre-confirmacion">
              <input type="checkbox" checked={confirmarCierre} onChange={(evento) => setConfirmarCierre(evento.target.checked)} disabled={guardando} required />
              Confirmo que revisé los materiales, los costos y la firma del residente, y autorizo el cierre definitivo.
            </label>
            {error && <div className="alert alert-danger" role="alert">{error}</div>}
            <div className="alkira-mant-acciones">
              <button type="button" className="btn btn-outline-secondary" onClick={onCancelar} disabled={guardando}>Cancelar</button>
              <button type="submit" className="btn btn-success" disabled={guardando || !confirmarCierre || (!sinMateriales && materiales.length === 0)}>{guardando ? 'Cerrando…' : 'Cerrar ticket definitivamente'}</button>
            </div>
          </form>
        )}
      </section>
    </div>
  )
}
