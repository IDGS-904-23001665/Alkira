export async function obtenerResumenGeneral(signal) {
  let respuesta

  try {
    respuesta = await fetch('/api/resumen-general', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
      signal,
    })
  } catch (error) {
    if (error.name === 'AbortError') {
      throw error
    }

    throw new Error(
      'No se pudo conectar con el servidor de ALKIRA.'
    )
  }

  const datos = await respuesta.json().catch(() => ({}))

  if (!respuesta.ok) {
    throw new Error(
      datos.mensaje || 'No fue posible cargar el resumen general.'
    )
  }

  return datos
}