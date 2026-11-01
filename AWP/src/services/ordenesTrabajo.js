async function solicitarMantenimiento(ruta, opciones = {}) {
  let respuesta

  try {
    respuesta = await fetch(`/api/mantenimiento${ruta}`, {
      ...opciones,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(opciones.body ? { 'Content-Type': 'application/json' } : {}),
        ...opciones.headers,
      },
    })
  } catch {
    throw new Error('No fue posible conectarse a la API de ALKIRA.')
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

export const apiOrdenesTrabajo = {
  listarTickets: () =>
    solicitarMantenimiento('/tickets'),

  listarTecnicos: () =>
    solicitarMantenimiento('/tecnicos'),

  asignar: (idTicket, datos) =>
    solicitarMantenimiento(
      `/tickets/${encodeURIComponent(idTicket)}/asignar`,
      {
        method: 'POST',
        body: JSON.stringify(datos),
      }
    ),
}
