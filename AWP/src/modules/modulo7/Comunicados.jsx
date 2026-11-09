import {createElement,useEffect,useRef,useState} from 'react'
import {hu07Api} from './hu07Api.js'
import './Hu07.css'

// Renderiza contenido histórico SIN inyectar HTML: React escapa todo texto y no acepta atributos.
function contenidoSeguro(html){
  const permitido=new Set(['p','br','b','strong','em','i','u','ul','ol','li','div','blockquote'])
  const doc=new DOMParser().parseFromString(html||'','text/html')
  function nodo(n,i){
    if(n.nodeType===3)return n.textContent
    if(n.nodeType!==1)return null
    const tag=n.tagName.toLowerCase()
    if(['script','style','iframe','object','svg','math'].includes(tag))return null
    const hijos=Array.from(n.childNodes).map(nodo)
    return permitido.has(tag)?createElement(tag,{key:i},...hijos):createElement('span',{key:i},...hijos)
  }
  return Array.from(doc.body.childNodes).map(nodo)
}
export default function Comunicados(){
  const editor=useRef(null)
  const [inmuebles,setInmuebles]=useState([])
  const [historial,setHistorial]=useState([])
  const [titulo,setTitulo]=useState('')
  const [idInmueble,setIdInmueble]=useState('')
  const [programar,setProgramar]=useState(false)
  const [fecha,setFecha]=useState('')
  const [contenido,setContenido]=useState('')
  const [mensaje,setMensaje]=useState('')
  const [error,setError]=useState('')
  const [guardando,setGuardando]=useState(false)
  async function refrescar(){
    try {const [a,b]=await Promise.all([hu07Api.inmuebles(),hu07Api.comunicados()]);setInmuebles(a.inmuebles);setHistorial(b.comunicados)}
    catch(e){setError(e.message)}
  }
  useEffect(()=>{refrescar();const timer=setInterval(refrescar,15000);return()=>clearInterval(timer)},[])
  function formato(cmd){editor.current?.focus();document.execCommand(cmd,false,null);setContenido(editor.current?.innerHTML||'')}
  async function enviar(e){
    e.preventDefault();setError('');setMensaje('')
    if(!idInmueble||!titulo.trim()||!editor.current?.innerText.trim()){setError('Completa el destino, el título y el contenido.');return}
    if(!window.confirm(programar?'¿Programar este boletín para la fecha elegida?':'¿Publicar el boletín ahora para todos los residentes del destino?'))return
    setGuardando(true)
    try{
      const r=await hu07Api.crearComunicado({idInmueble:Number(idInmueble),titulo,contenido,programar,fechaProgramada:fecha})
      setMensaje(r.mensaje);setTitulo('');setContenido('');if(editor.current)editor.current.innerHTML='';await refrescar()
    }catch(e){setError(e.message)}finally{setGuardando(false)}
  }
  return <div className="hu07">
    <div className="intro"><div><h2>Comunicados masivos</h2><p className="muted">HU07.3 · Avisos de la administración, segmentación y programación</p></div><button className="secondary" onClick={refrescar}>Actualizar historial</button></div>
    {mensaje&&<div className="notice" role="status">{mensaje}</div>}{error&&<div className="notice error" role="alert">{error}</div>}
    <div className="columns">
      <section className="panel"><div className="panelhead"><h3>Redactar boletín</h3><span className="tag">ADMINISTRACIÓN</span></div>
        <form onSubmit={enviar}>
          <label className="field">Condominio o torre destinataria<select required value={idInmueble} onChange={e=>setIdInmueble(e.target.value)}><option value="">Selecciona una ubicación</option>{inmuebles.map(i=><option value={i.id} key={i.id}>{i.nombre} · {i.tipo}</option>)}</select></label>
          <label className="field">Asunto<input required maxLength={180} value={titulo} onChange={e=>setTitulo(e.target.value)} placeholder="Ej. Mantenimiento de áreas comunes"/></label>
          <div className="field"><span>Mensaje con formato</span><div className="toolbar" aria-label="Formato del boletín"><button type="button" title="Negritas" onClick={()=>formato('bold')}>B</button><button type="button" title="Cursiva" onClick={()=>formato('italic')}><i>I</i></button><button type="button" title="Subrayado" onClick={()=>formato('underline')}><u>U</u></button><button type="button" title="Viñetas" onClick={()=>formato('insertUnorderedList')}>• Lista</button><button type="button" title="Lista numerada" onClick={()=>formato('insertOrderedList')}>1. Lista</button></div>
          <div className="editor" ref={editor} contentEditable suppressContentEditableWarning role="textbox" aria-multiline="true" aria-label="Contenido del comunicado" onInput={e=>setContenido(e.currentTarget.innerHTML)} data-testid="editor-comunicados"/></div>
          <label className="switch"><input type="checkbox" checked={programar} onChange={e=>setProgramar(e.target.checked)}/> Programar publicación (en vez de publicar ahora)</label>
          {programar&&<label className="field">Fecha y hora local del servidor<input required type="datetime-local" value={fecha} onChange={e=>setFecha(e.target.value)}/></label>}
          <p className="muted" style={{marginBottom:12}}>El aviso se publica en el centro de avisos de los residentes. Correo y push se procesan con el trabajador de envíos configurado.</p>
          <button type="submit" className="primary" disabled={guardando}>{guardando?'Guardando...':programar?'Programar boletín':'Publicar ahora'}</button>
        </form>
      </section>
      <section className="panel"><div className="panelhead"><h3>Boletines e historial</h3><span className="muted">{historial.length} registros</span></div>
        <div className="list">{historial.length===0&&<p className="muted">Todavía no hay boletines registrados.</p>}
          {historial.map(a=><article key={a.id} className="item"><div className="itemhead"><div><h3>{a.titulo}</h3><p className="meta">{a.inmueble} · #{a.id}</p></div><span className={'tag '+(a.estado==='publicado'?'green':'gray')}>{a.estado}</span></div>
              <div className="rich">{contenidoSeguro(a.contenido)}</div>
              <p className="meta">{a.estado==='programado'?'Programado: '+a.fechaProgramada:'Publicado: '+a.fechaPublicacion}</p>
              <p className="tiny">Destinatarios: {a.destinatarios} · Leídos: {a.leidos} · Correos enviados: {a.correosEnviados} · Push enviados: {a.pushesEnviados}</p>
            </article>)}
        </div>
      </section>
    </div>
  </div>
}
