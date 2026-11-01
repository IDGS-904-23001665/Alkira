
async function consultarCierre(ruta, opciones = {}) {
  let respuesta

  try {
    respuesta = await fetch(`/api/mantenimiento${ruta}`, {
      credentials: 'same-origin',
      ...opciones,
      headers: {
        Accept: 'application/json',
        ...(opciones.body
          ? { 'Content-Type': 'application/json' }
          : {}),
        ...opciones.headers,
      },
    })
  } catch {
    throw new Error(
      'No fue posible comunicarse con la API de ALKIRA.'
    )
  }

  const datos = await respuesta.json().catch(() => ({}))

  if (!respuesta.ok) {
    const error = new Error(
      datos.mensaje || `Error HTTP ${respuesta.status}`
    )

    error.codigo = respuesta.status
    throw error
  }

  return datos
}

export const apiCierreMantenimiento = {
  obtenerMateriales: (idTicket) =>
    consultarCierre(
      `/tickets/${encodeURIComponent(idTicket)}/materiales`
    ),

  cerrarTicket: (idTicket, datos) =>
    consultarCierre(
      `/tickets/${encodeURIComponent(idTicket)}/cerrar`,
      {
        method: 'POST',
        body: JSON.stringify(datos),
      }
    ),
}
