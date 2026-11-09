async function pedir(ruta, opciones={}) {
  let respuesta
  try {
    respuesta=await fetch('/api/hu07'+ruta, {credentials:'same-origin',...opciones,headers:{Accept:'application/json',...(opciones.body?{'Content-Type':'application/json'}:{}),...opciones.headers}})
  } catch {throw new Error('No se pudo conectar al servidor ALKIRA.')}
  const datos=await respuesta.json().catch(()=>({}))
  if(!respuesta.ok)throw new Error(datos.mensaje||`Error HTTP ${respuesta.status}`)
  return datos
}
export const hu07Api={
  inmuebles:()=>pedir('/inmuebles'),
  comunicados:()=>pedir('/comunicados'),
  crearComunicado:(d)=>pedir('/comunicados',{method:'POST',body:JSON.stringify(d)}),
  votaciones:()=>pedir('/votaciones'),
  crearVotacion:(d)=>pedir('/votaciones',{method:'POST',body:JSON.stringify(d)}),
  auditoria:(id)=>pedir(`/votaciones/${id}/auditoria`),
}
