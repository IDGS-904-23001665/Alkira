const RUTA_SIMULACION =
  '/api/administracion/adeudos/simulacion'

function validarPeriodo(periodo) {
  if (
    typeof periodo !== 'string' ||
    !/^[0-9]{4}-(0[1-9]|1[0-2])$/.test(periodo)
  ) {
    throw new Error(
      'Selecciona un periodo válido con formato YYYY-MM.'
    )
  }

  return periodo
}

async function simularAdeudos(periodo) {
  const periodoValidado = validarPeriodo(periodo)

  const parametros = new URLSearchParams({
    periodo: periodoValidado,
  })

  let respuesta

  try {
    respuesta = await fetch(
      `${RUTA_SIMULACION}?${parametros.toString()}`,
      {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
        cache: 'no-store',
      }
    )
  } catch {
    throw new Error(
      'No fue posible conectarse con la API de ALKIRA.'
    )
  }

  const datos = await respuesta.json().catch(() => ({}))

  if (!respuesta.ok) {
    if (respuesta.status === 401) {
      throw new Error(
        'Tu sesión venció. Inicia sesión nuevamente.'
      )
    }

    if (respuesta.status === 403) {
      throw new Error(
        'No tienes permisos para simular adeudos.'
      )
    }

    throw new Error(
      datos.mensaje ||
      `No fue posible realizar la simulación. HTTP ${respuesta.status}`
    )
  }

  if (
    !datos.simulacion ||
    datos.simulacion.modo !== 'simulacion' ||
    !Array.isArray(datos.simulacion.detalle)
  ) {
    throw new Error(
      'La API devolvió una simulación incompleta.'
    )
  }

  return datos.simulacion
}

export const apiSimulacionAdeudos = {
  simularAdeudos,
}
