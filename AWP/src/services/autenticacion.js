
async function solicitar(ruta, opciones = {}) {
  let respuesta

  try {
    respuesta = await fetch(`/api/auth/${ruta}`, {
      ...opciones,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(opciones.body ? { 'Content-Type': 'application/json' } : {}),
        ...opciones.headers,
      },
    })
  } catch {
    throw new Error('No se pudo conectar con el servidor PHP.')
  }

  let datos

  try {
    datos = await respuesta.json()
  } catch {
    throw new Error('El servidor no devolvió una respuesta JSON válida.')
  }

  if (!respuesta.ok) {
    const error = new Error(
      datos.mensaje || 'No fue posible completar la solicitud.'
    )
    error.codigo = respuesta.status
    throw error
  }

  return datos
}

export async function iniciarSesion(correo, contrasena) {
  const { usuario } = await solicitar('login', {
    method: 'POST',
    body: JSON.stringify({ correo, contrasena }),
  })

  return usuario
}

export async function consultarSesion() {
  const { usuario } = await solicitar('yo')
  return usuario
}

export async function cerrarSesion() {
  await solicitar('salir', { method: 'POST' })
}
