import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiOrdenesTrabajo } from '../../services/ordenesTrabajo.js'
import './Mantenimiento.css'
import CierreMantenimientoDialog from './CierreMantenimientoDialog.jsx'

const ESTADOS = {
  abierto: 'Abierto',
  asignado: 'Asignado',
  en_proceso: 'En proceso',
  pendiente_revision: 'En evaluación',
  cerrado: 'Cerrado',
  cancelado: 'Cancelado',
}

const URGENCIAS = {
  alta: 'Alta',
  media: 'Media',
  baja: 'Baja',
}

function fechaLegible(valor) {
  if (!valor) return 'Por programar'
  return String(valor).replace('T', ' ').slice(0, 16)
}

function fechaLocalInicial() {
  const ahora = new Date(Date.now() + 24 * 60 * 60 * 1000)
  const parte = (valor) => String(valor).padStart(2, '0')
  return `${ahora.getFullYear()}-${parte(ahora.getMonth() + 1)}-${parte(ahora.getDate())}T09:00`
}

export default function Mantenimiento() {
  const [tickets, setTickets] = useState([])
  const [tecnicos, setTecnicos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState('')
  const [error, setError] = useState('')
  const [filtro, setFiltro] = useState('todos')
  const [busqueda, setBusqueda] = useState('')
  const [ticketSeleccionado, setTicketSeleccionado] = useState(null)
  const [ticketCierre, setTicketCierre] = useState(null)
  const [formulario, setFormulario] = useState({
    idTecnico: '',
    descripcionOrden: '',
    fechaProgramada: fechaLocalInicial(),
  })

  const cargar = useCallback(async () => {
    setCargando(true)
    setError('')
    try {
      const [respuestaTickets, respuestaTecnicos] = await Promise.all([
        apiOrdenesTrabajo.listarTickets(),
        apiOrdenesTrabajo.listarTecnicos(),
      ])
      setTickets(respuestaTickets.tickets || [])
      setTecnicos(respuestaTecnicos.tecnicos || [])
    } catch (errorSolicitud) {
      setError(errorSolicitud.message)
    } finally {
      setCargando(false)
    }
  }, [])

  useEffect(() => {
    cargar()
  }, [cargar])

  const resumen = useMemo(() => ({
    total: tickets.length,
    porAsignar: tickets.filter((ticket) => !Number(ticket.tieneOrdenActiva) && ['abierto', 'asignado'].includes(ticket.estado)).length,
    enProceso: tickets.filter((ticket) => ticket.estado === 'en_proceso').length,
    enEvaluacion: tickets.filter((ticket) => ticket.estado === 'pendiente_revision').length,
  }), [tickets])

  const listaFiltrada = useMemo(() => {
    const texto = busqueda.trim().toLocaleLowerCase('es')
    return tickets.filter((ticket) => {
      const coincideFiltro = filtro === 'todos'
        || (filtro === 'sin_asignar' && !Number(ticket.tieneOrdenActiva) && ['abierto', 'asignado'].includes(ticket.estado))
        || ticket.estado === filtro
      const contenido = `${ticket.idTicket} ${ticket.titulo} ${ticket.nombreResidente} ${ticket.nombreTecnico || ''} ${ticket.numeroUnidad || ''}`.toLocaleLowerCase('es')
      return coincideFiltro && (!texto || contenido.includes(texto))
    })
  }, [tickets, filtro, busqueda])

  function abrirAsignacion(ticket) {
    setMensaje('')
    setError('')
    setTicketSeleccionado(ticket)
    setFormulario({
      idTecnico: '',
      descripcionOrden: ticket.descripcionTrabajo || `Atender incidencia: ${ticket.titulo}`,
      fechaProgramada: fechaLocalInicial(),
    })
  }

  async function enviarAsignacion(evento) {
    evento.preventDefault()
    if (!ticketSeleccionado || guardando) return
    setGuardando(true)
    setError('')
    setMensaje('')
    try {
      const respuesta = await apiOrdenesTrabajo.asignar(ticketSeleccionado.idTicket, {
        idTecnico: Number(formulario.idTecnico),
        descripcionOrden: formulario.descripcionOrden.trim(),
        fechaProgramada: formulario.fechaProgramada,
      })
      setTicketSeleccionado(null)
      setMensaje(respuesta.mensaje || 'Orden creada y asignada correctamente.')
      await cargar()
    } catch (errorSolicitud) {
      setError(errorSolicitud.message)
    } finally {
      setGuardando(false)
    }
  }

  return (
    <main className="alkira-mant">
      <div className="alkira-mant-titulo">
        <div>
          <h1>Órdenes de mantenimiento</h1>
          <p>Asigna técnicos registrados y programa las visitas de los tickets.</p>
        </div>
        <button type="button" className="btn btn-outline-primary" onClick={cargar} disabled={cargando || guardando}>
          {cargando ? 'Actualizando…' : 'Actualizar'}
        </button>
      </div>

      <div className="alkira-mant-indicadores" aria-label="Resumen de mantenimiento">
        <div><span>Tickets</span><strong>{resumen.total}</strong></div>
        <div><span>Por asignar</span><strong>{resumen.porAsignar}</strong></div>
        <div><span>En proceso</span><strong>{resumen.enProceso}</strong></div>
        <div><span>En evaluación</span><strong>{resumen.enEvaluacion}</strong></div>
      </div>

      {mensaje && <div className="alert alert-success" role="status">{mensaje}</div>}
      {error && <div className="alert alert-danger" role="alert">{error}</div>}

      <section className="alkira-mant-panel">
        <div className="alkira-mant-herramientas">
          <label className="alkira-mant-busqueda">
            <span>Buscar ticket, residente o técnico</span>
            <input className="form-control" value={busqueda} onChange={(evento) => setBusqueda(evento.target.value)} placeholder="Ej. plomería, unidad 101…" />
          </label>
          <label>
            <span>Estado</span>
            <select className="form-select" value={filtro} onChange={(evento) => setFiltro(evento.target.value)}>
              <option value="todos">Todos</option>
              <option value="sin_asignar">Por asignar</option>
              <option value="asignado">Asignados</option>
              <option value="en_proceso">En proceso</option>
              <option value="pendiente_revision">En evaluación</option>
              <option value="cerrado">Cerrados</option>
              <option value="cancelado">Cancelados</option>
            </select>
          </label>
        </div>

        {cargando ? (
          <p className="alkira-mant-vacio">Cargando tickets…</p>
        ) : listaFiltrada.length === 0 ? (
          <p className="alkira-mant-vacio">No hay tickets que coincidan con los filtros.</p>
        ) : (
          <div className="alkira-mant-tabla">
            <table className="table table-hover align-middle mb-0">
              <thead><tr><th>Ticket</th><th>Residente / Unidad</th><th>Técnico</th><th>Visita</th><th>Estado</th><th>Acciones</th></tr></thead>
              <tbody>
                {listaFiltrada.map((ticket) => {
                  const asignable = ['abierto', 'asignado'].includes(ticket.estado) && !Number(ticket.tieneOrdenActiva)
                  return (
                    <tr key={ticket.idTicket}>
                      <td>
                        <strong>#{ticket.idTicket} · {ticket.titulo}</strong>
                        <small>{ticket.descripcion}</small>
                        <span className={`alkira-mant-urgencia urgencia-${ticket.urgencia}`}>{URGENCIAS[ticket.urgencia] || ticket.urgencia}</span>
                      </td>
                      <td>{ticket.nombreResidente}<small>{ticket.nombreInmueble || 'Sin inmueble'} / {ticket.numeroUnidad || 'Área común'}</small></td>
                      <td>{ticket.nombreTecnico || 'Sin asignar'}</td>
                      <td>{fechaLegible(ticket.fechaProgramada)}</td>
                      <td><span className={`alkira-mant-estado estado-${ticket.estado}`}>{ESTADOS[ticket.estado] || ticket.estado}</span></td>
                      <td>
                        {asignable ? (
                          <button type="button" className="btn btn-primary btn-sm" onClick={() => abrirAsignacion(ticket)}>
                            Asignar técnico
                          </button>
                        ) : ticket.estado === 'pendiente_revision' ? (
                          <button type="button" className="btn btn-success btn-sm" onClick={() => setTicketCierre(ticket)}>Revisar y cerrar</button>
                        ) : ticket.estado === 'cerrado' ? (
                          <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => setTicketCierre(ticket)}>Ver materiales</button>
                        ) : <span className="text-secondary small">{Number(ticket.tieneOrdenActiva) ? 'Orden registrada' : 'No asignable'}</span>}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {ticketCierre && (
        <CierreMantenimientoDialog
          ticket={ticketCierre}
          onCancelar={() => setTicketCierre(null)}
          onCerrado={async () => {
            setTicketCierre(null)
            await cargar()
            setMensaje('Ticket cerrado administrativamente. Materiales y costos registrados.')
          }}
        />
      )}

      {ticketSeleccionado && (
        <div className="alkira-mant-modal-fondo" onMouseDown={(evento) => {
          if (evento.target === evento.currentTarget && !guardando) setTicketSeleccionado(null)
        }}>
          <section className="alkira-mant-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-asignacion">
            <div className="alkira-mant-modal-cabecera">
              <div>
                <h2 id="titulo-asignacion">Asignar orden de trabajo</h2>
                <p>Ticket #{ticketSeleccionado.idTicket}: {ticketSeleccionado.titulo}</p>
              </div>
              <button type="button" className="btn-close" aria-label="Cerrar" onClick={() => setTicketSeleccionado(null)} disabled={guardando} />
            </div>
            <form onSubmit={enviarAsignacion} className="alkira-mant-formulario">
              <label>
                <span>Técnico responsable</span>
                <select className="form-select" required value={formulario.idTecnico} onChange={(evento) => setFormulario((actual) => ({ ...actual, idTecnico: evento.target.value }))}>
                  <option value="">Selecciona un técnico</option>
                  {tecnicos.map((tecnico) => (
                    <option key={tecnico.idTecnico} value={tecnico.idTecnico}>{tecnico.nombreTecnico} · {tecnico.correo}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Fecha y hora de visita</span>
                <input type="datetime-local" className="form-control" required value={formulario.fechaProgramada} onChange={(evento) => setFormulario((actual) => ({ ...actual, fechaProgramada: evento.target.value }))}/>
              </label>
              <label>
                <span>Instrucciones de la orden</span>
                <textarea className="form-control" required minLength={8} maxLength={1500} rows={4} value={formulario.descripcionOrden} onChange={(evento) => setFormulario((actual) => ({ ...actual, descripcionOrden: evento.target.value }))}/>
              </label>
              {error && <div className="alert alert-danger mb-0" role="alert">{error}</div>}
              <p className="alkira-mant-nota">Al confirmar, se registrará la orden y una notificación pendiente para el técnico. El envío push todavía no está configurado.</p>
              <div className="alkira-mant-acciones">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setTicketSeleccionado(null)} disabled={guardando}>Cancelar</button>
                <button type="submit" className="btn btn-primary" disabled={guardando || tecnicos.length === 0}>{guardando ? 'Guardando…' : 'Crear y asignar orden'}</button>
              </div>
              {tecnicos.length === 0 && <p className="text-danger small mb-0">No hay técnicos activos registrados en MySQL.</p>}
            </form>
          </section>
        </div>
      )}
    </main>
  )
}
