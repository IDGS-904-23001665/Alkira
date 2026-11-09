import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiConciliacion } from '../../services/conciliacion.js'
import './Conciliacion.css'

const moneda = (valor) => Number(valor || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
const etiquetas = { preregistrado: 'Sin preregistro', en_revision: 'Requiere validación', aprobado: 'Aprobado', rechazado: 'Rechazado' }

export default function Conciliacion({ onCambiarSeccion }) {
  const [comprobantes, setComprobantes] = useState([])
  const [seleccionado, setSeleccionado] = useState(null)
  const [busqueda, setBusqueda] = useState('')
  const [filtro, setFiltro] = useState('en_revision')
  const [cargando, setCargando] = useState(true)
  const [procesando, setProcesando] = useState(false)
  const [error, setError] = useState('')
  const [aviso, setAviso] = useState('')
  const [motivo, setMotivo] = useState('')
  const [mostrarRechazo, setMostrarRechazo] = useState(false)
  const [comprobaciones, setComprobaciones] = useState({ archivo: false, monto: false, cuenta: false, referencia: false })

  const cargar = useCallback(async (idPreferido = null) => {
    const resultado = await apiConciliacion.listar()
    const lista = Array.isArray(resultado.comprobantes) ? resultado.comprobantes : []
    setComprobantes(lista)
    setSeleccionado(actual => lista.find(item => item.idComprobante === (idPreferido ?? actual?.idComprobante))?.idComprobante ?? lista.find(item => item.estadoPago === 'en_revision')?.idComprobante ?? null)
  }, [])

  useEffect(() => {
    let activo = true
    apiConciliacion.listar().then(datos => {
      if (!activo) return
      const lista = datos.comprobantes || []
      setComprobantes(lista)
      setSeleccionado(lista.find(item => item.estadoPago === 'en_revision')?.idComprobante ?? lista[0]?.idComprobante ?? null)
    }).catch(err => { if (activo) setError(err.message) }).finally(() => { if (activo) setCargando(false) })
    return () => { activo = false }
  }, [])

  const actual = comprobantes.find(c => c.idComprobante === seleccionado)
  const pendientes = comprobantes.filter(c => c.estadoPago === 'en_revision').length
  const filtrados = useMemo(() => comprobantes.filter(c => {
    if (filtro !== 'todos' && c.estadoPago !== filtro) return false
    const texto = `${c.nombres} ${c.apellidos} ${c.nombreInmueble} ${c.numeroUnidad} ${c.referenciaBancaria || ''} ${c.idComprobante}`.toLowerCase()
    return texto.includes(busqueda.toLowerCase().trim())
  }), [comprobantes, busqueda, filtro])
  const verificacionesCompletas = Object.values(comprobaciones).every(Boolean)
  const editable = actual?.estadoPago === 'en_revision' && actual?.estadoRevision === 'pendiente'
  const tipo = String(actual?.tipoArchivo || '').toUpperCase()
  const url = actual ? apiConciliacion.urlArchivo(actual.idComprobante) : ''

  function seleccionar(id) {
    setSeleccionado(id)
    setMotivo('')
    setMostrarRechazo(false)
    setComprobaciones({ archivo: false, monto: false, cuenta: false, referencia: false })
    setError('')
    setAviso('')
  }

  async function resolver(decision) {
    if (!actual || procesando || !editable) return
    if (decision === 'aprobar' && !verificacionesCompletas) {
      setError('Confirma los cuatro puntos de verificación antes de aprobar.')
      return
    }
    if (decision === 'rechazar' && motivo.trim().length < 5) {
      setError('Escribe un motivo de rechazo de al menos 5 caracteres.')
      return
    }
    if (!window.confirm(`¿Deseas ${decision === 'aprobar' ? 'aprobar y aplicar al saldo' : 'rechazar'} este pago de ${moneda(actual.monto)}?`)) return
    setProcesando(true)
    setError('')
    setAviso('')
    try {
      const resultado = decision === 'aprobar'
        ? await apiConciliacion.aprobar(Number(actual.idComprobante), motivo.trim())
        : await apiConciliacion.rechazar(Number(actual.idComprobante), motivo.trim())
      await cargar(actual.idComprobante)
      setAviso(`${resultado.mensaje}${resultado.folio ? ` Folio: ${resultado.folio}` : ''}`)
      setMostrarRechazo(false)
      setMotivo('')
    } catch (err) { setError(err.message) }
    finally { setProcesando(false) }
  }

  return (
    <main className="hu04">
      <header className="hu04-titulo"><div><h1>Validación de pagos</h1><p>Finanzas / Conciliación bancaria · HU04.2</p></div><span className="hu04-insignia">{pendientes} por validar</span></header>
      {error && <div role="alert" className="hu04-alerta hu04-error">{error}</div>}
      {aviso && <div role="status" className="hu04-alerta hu04-exito">{aviso}</div>}
      <div className="hu04-columnas">
        <section className="hu04-bandeja">
          <div className="hu04-panel-titulo"><div><h2>Comprobantes recibidos</h2><p>{pendientes} pagos esperan atención</p></div><span className="hu04-insignia">{filtrados.length} resultados</span></div>
          <div className="hu04-herramientas"><input aria-label="Buscar comprobante" placeholder="Buscar residente, unidad o referencia" value={busqueda} onChange={e => setBusqueda(e.target.value)} /><select aria-label="Filtrar por estado" value={filtro} onChange={e => setFiltro(e.target.value)}><option value="en_revision">Pendientes de validar</option><option value="todos">Todos</option><option value="aprobado">Aprobados</option><option value="rechazado">Rechazados</option><option value="preregistrado">Sin preregistro</option></select></div>
          {cargando ? <p className="hu04-vacio">Cargando comprobantes...</p> : filtrados.length === 0 ? <p className="hu04-vacio">No hay comprobantes con estos filtros.</p> : <div className="hu04-listado">{filtrados.map(c => <button type="button" key={c.idComprobante} className={`hu04-item ${seleccionado === c.idComprobante ? 'seleccionado' : ''}`} onClick={() => seleccionar(c.idComprobante)}><span className="hu04-avatar">{(c.nombres?.[0] || '') + (c.apellidos?.[0] || '')}</span><span className="hu04-item-info"><strong>{c.nombres} {c.apellidos}</strong><small>{c.nombreInmueble} · Unidad {c.numeroUnidad}</small><em className={`hu04-estado estado-${c.estadoPago}`}>{etiquetas[c.estadoPago] || c.estadoPago}</em></span><span className="hu04-item-monto"><strong>{moneda(c.monto)}</strong><small>#{c.idComprobante}</small></span></button>)}</div>}
        </section>
        <section className="hu04-revision">
          {!actual ? <div className="hu04-vacio">Selecciona un comprobante para revisar sus datos.</div> : <>
            <div className="hu04-panel-titulo"><div><h2>Validar comprobante <span className="hu04-folio">RCP-{String(actual.idComprobante).padStart(6, '0')}</span></h2><p>Enviado por {actual.nombres} {actual.apellidos} · {actual.fechaCarga}</p></div></div>
            <div className="hu04-detalle">
              <div className="hu04-visor"><div className="hu04-visor-cabecera"><span>Documento · {tipo}</span><a href={url} target="_blank" rel="noreferrer">Abrir original ↗</a></div>{tipo === 'PDF' ? <iframe title="Vista previa del comprobante" src={url} /> : ['JPG', 'PNG'].includes(tipo) ? <img src={url} alt="Comprobante adjunto" /> : <p>Formato no compatible con vista previa.</p>}</div>
              <div className="hu04-verificacion"><h3>Datos para conciliar</h3><dl><dt>Importe declarado</dt><dd>{moneda(actual.monto)}</dd><dt>Saldo del estado de cuenta</dt><dd>{moneda(actual.saldoPendiente)}</dd><dt>Periodo</dt><dd>{actual.periodo}</dd><dt>Cuenta</dt><dd>#{actual.idEstadoCuenta}</dd><dt>Referencia bancaria</dt><dd>{actual.referenciaBancaria || 'Sin referencia'}</dd><dt>Método</dt><dd>{actual.metodoPago}</dd><dt>Estado</dt><dd>{etiquetas[actual.estadoPago]}</dd></dl><h3>Lista de verificación manual</h3><p className="hu04-nota">Revisa el archivo y confirma contra el movimiento bancario real. No se realiza OCR ni verificación bancaria automática.</p>
                {[
                  ['archivo', 'El archivo es legible y corresponde al pago'],
                  ['monto', 'El monto coincide con el movimiento bancario'],
                  ['cuenta', 'La cuenta de destino y el inquilino son correctos'],
                  ['referencia', 'La referencia bancaria fue verificada'],
                ].map(([clave, texto]) => <label className="hu04-check" key={clave}><input type="checkbox" checked={comprobaciones[clave]} disabled={!editable || procesando} onChange={e => setComprobaciones(anterior => ({ ...anterior, [clave]: e.target.checked }))} />{texto}</label>)}
              </div>
            </div>
            {editable ? <div className="hu04-acciones"><div className="hu04-acciones-botones"><button className="hu04-rechazar" type="button" disabled={procesando} onClick={() => setMostrarRechazo(valor => !valor)}>Rechazar</button><button className="hu04-aprobar" type="button" disabled={procesando || !verificacionesCompletas} onClick={() => resolver('aprobar')}>{procesando ? 'Procesando...' : 'Aprobar y conciliar'}</button></div>{mostrarRechazo && <div className="hu04-motivo"><label htmlFor="hu04-motivo">Motivo del rechazo (obligatorio)</label><textarea id="hu04-motivo" value={motivo} maxLength={500} onChange={e => setMotivo(e.target.value)} placeholder="Describe por qué el comprobante no es válido..." /><button type="button" className="hu04-rechazar" disabled={procesando || motivo.trim().length < 5} onClick={() => resolver('rechazar')}>Confirmar rechazo</button></div>}</div> : <div className="hu04-resuelto"><strong>Pago {etiquetas[actual.estadoPago] || actual.estadoPago}</strong>{actual.motivoRevision && <p>Observación: {actual.motivoRevision}</p>}{actual.estadoPago === 'aprobado' && <a className="hu04-aprobar" href={apiConciliacion.urlRecibo(actual.idPago)} target="_blank" rel="noreferrer">Descargar recibo PDF · {actual.referenciaRecibo}</a>}{actual.estadoPago === 'preregistrado' && <p>Primero confirma el preregistro en el módulo Comprobantes. <button className="hu04-rechazar" type="button" onClick={() => onCambiarSeccion?.("comprobantes")}>Ir a HU03.2</button></p>}</div>}
          </>}
        </section>
      </div>
    </main>
  )
}
