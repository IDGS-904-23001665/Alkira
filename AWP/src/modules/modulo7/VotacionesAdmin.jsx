import {useEffect,useState} from 'react'
import {hu07Api} from './hu07Api.js'
import './Hu07.css'

export default function VotacionesAdmin(){
  const [inmuebles,setInmuebles]=useState([]);const [votaciones,setVotaciones]=useState([])
  const [idInmueble,setIdInmueble]=useState('');const [titulo,setTitulo]=useState('');const [descripcion,setDescripcion]=useState('')
  const [inicio,setInicio]=useState('');const [cierre,setCierre]=useState('');const [opciones,setOpciones]=useState(['Sí','No'])
  const [cargando,setCargando]=useState(false);const [mensaje,setMensaje]=useState('');const [error,setError]=useState('');const [auditoria,setAuditoria]=useState({})
  async function refrescar(){try{const [a,b]=await Promise.all([hu07Api.inmuebles(),hu07Api.votaciones()]);setInmuebles(a.inmuebles);setVotaciones(b.votaciones)}catch(e){setError(e.message)}}
  useEffect(()=>{refrescar();const t=setInterval(refrescar,10000);return()=>clearInterval(t)},[])
  async function crear(e){
    e.preventDefault();setMensaje('');setError('');if(!idInmueble){setError('Selecciona el condominio o la torre.');return}
    if(!window.confirm('¿Crear esta votación comunitaria? Cada unidad solo podrá votar una vez.'))return
    setCargando(true)
    try{const r=await hu07Api.crearVotacion({idInmueble:Number(idInmueble),titulo,descripcion,inicio,cierre,opciones});setMensaje(r.mensaje);setTitulo('');setDescripcion('');setOpciones(['Sí','No']);await refrescar()}
    catch(e){setError(e.message)}finally{setCargando(false)}
  }
  async function verAuditoria(id){try{if(auditoria[id]){setAuditoria(x=>({...x,[id]:null}));return}const r=await hu07Api.auditoria(id);setAuditoria(x=>({...x,[id]:r.votos}))}catch(e){setError(e.message)}}
  const estado=(v)=>{const t=Date.now();return v.estado==='cancelada'?'Cancelada':v.estado==='cerrada'||new Date(v.cierre.replace(' ','T')).getTime()<=t?'Cerrada':new Date(v.inicio.replace(' ','T')).getTime()>t?'Programada':'Abierta'}
  return <div className="hu07">
    <div className="intro"><div><h2>Votaciones comunitarias</h2><p className="muted">HU07.4 · Configuración, gráficas y auditoría por unidad</p></div><button className="secondary" onClick={refrescar}>Actualizar resultados</button></div>
    {mensaje&&<div className="notice" role="status">{mensaje}</div>}{error&&<div className="notice error" role="alert">{error}</div>}
    <div className="columns"><section className="panel"><div className="panelhead"><h3>Crear votación</h3><span className="tag">UN VOTO POR UNIDAD</span></div>
      <form onSubmit={crear}>
        <label className="field">Condominio / torre<select required value={idInmueble} onChange={e=>setIdInmueble(e.target.value)}><option value="">Elige una ubicación</option>{inmuebles.map(i=><option key={i.id} value={i.id}>{i.nombre} · {i.tipo}</option>)}</select></label>
        <label className="field">Pregunta de la votación<input required minLength={4} maxLength={180} value={titulo} onChange={e=>setTitulo(e.target.value)} placeholder="Ej. ¿Aprobamos renovar el jardín?"/></label>
        <label className="field">Descripción<textarea maxLength={4000} value={descripcion} onChange={e=>setDescripcion(e.target.value)} placeholder="Información para que los vecinos tomen una decisión..."/></label>
        <div className="split"><label className="field">Apertura<input type="datetime-local" required value={inicio} onChange={e=>setInicio(e.target.value)}/></label><label className="field">Cierre<input type="datetime-local" required min={inicio} value={cierre} onChange={e=>setCierre(e.target.value)}/></label></div>
        <div className="field"><span>Opciones de respuesta</span>{opciones.map((o,i)=><div key={i} style={{display:'flex',gap:8}}><input required maxLength={180} value={o} onChange={e=>setOpciones(x=>x.map((v,j)=>j===i?e.target.value:v))} aria-label={`Opción ${i+1}`}/><button className="secondary" type="button" disabled={opciones.length<=2} onClick={()=>setOpciones(x=>x.filter((_,j)=>j!==i))}>×</button></div>)}<button className="secondary" type="button" disabled={opciones.length>=10} onClick={()=>setOpciones(x=>[...x,''])}>+ Añadir opción</button></div>
        <button className="primary" type="submit" disabled={cargando}>{cargando?'Creando...':'Crear votación'}</button>
      </form></section>
      <section className="panel"><div className="panelhead"><h3>Resultados en vivo</h3><span className="muted">Actualiza cada 10 s</span></div><div className="list">
        {!votaciones.length&&<p className="muted">Todavía no hay votaciones del módulo HU07.</p>}
        {votaciones.map(v=><article className="item" key={v.id}>
          <div className="itemhead"><div><h3>{v.titulo}</h3><p className="meta">{v.inmueble} · #{v.id}</p></div><span className={'tag '+(estado(v)==='Abierta'?'green':'gray')}>{estado(v)}</span></div>
          {v.descripcion&&<p className="meta">{v.descripcion}</p>}
          <p className="meta">Inicio: {v.inicio} · Cierre: {v.cierre}</p><p className="tiny">Total de unidades que han votado: {v.resultados.total}</p>
          {v.resultados.opciones.map(o=><div className="result" key={o.id}><div className="resulttitle"><span>{o.texto}</span><strong>{o.porcentaje}% · {o.votos} votos</strong></div><div className="bar"><div style={{width:`${o.porcentaje}%`}}/></div></div>)}
          <div style={{marginTop:12}}><button className="secondary" type="button" onClick={()=>verAuditoria(v.id)}>{auditoria[v.id]?'Ocultar auditoría':'Ver auditoría por unidad'}</button></div>
          {auditoria[v.id]&&<div className="audit">{auditoria[v.id].length===0?'Todavía no hay votos registrados.':auditoria[v.id].map((a,i)=><div className="auditrow" key={`${a.idUnidad}-${i}`}><span>{a.inmueble} · Unidad {a.unidad} · {v.resultados.opciones.find(o=>o.id===Number(a.idOpcion))?.texto||'Opción'}</span><span>{a.fecha}</span></div>)}</div>}
        </article>)}
      </div></section></div>
  </div>
}
