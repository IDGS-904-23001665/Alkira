async function solicitarReglasCobro(ruta, opciones = {}) {
  let respuesta

  try {
    respuesta = await fetch(
      `/api/administracion/reglas-cobro${ruta}`,
      {
        credentials: 'same-origin',
        ...opciones,
        headers: {
          Accept: 'application/json',
          ...opciones.headers,
        },
      }
    )
  } catch {
    throw new Error(
      'No fue posible conectarse con la API de ALKIRA.'
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

function validarIdentificador(valor) {
  const identificador = Number(valor)

  if (
    !Number.isSafeInteger(identificador) ||
    identificador <= 0
  ) {
    throw new Error(
      'El identificador de la regla de cobro no es válido.'
    )
  }

  return identificador
}

export const apiReglasCobro = {

  // Consultar las reglas registradas.
  listarReglas: () =>
    solicitarReglasCobro(''),

  // Consultar conceptos mensuales de mantenimiento.
  listarConceptos: () =>
    solicitarReglasCobro('/conceptos'),

  // Consultar una regla por su ID.
  obtenerRegla: (idReglaCobro) => {
    const identificador = validarIdentificador(
      idReglaCobro
    )

    return solicitarReglasCobro(
      `/${identificador}`
    )
  },

  // Registrar una regla nueva.
  crearRegla: (datosRegla) =>
    solicitarReglasCobro('', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(datosRegla),
    }),

  // Modificar una regla existente.
  actualizarRegla: (idReglaCobro, datosRegla) => {
    const identificador = validarIdentificador(
      idReglaCobro
    )

    return solicitarReglasCobro(
      `/${identificador}`,
      {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(datosRegla),
      }
    )
  },
}
