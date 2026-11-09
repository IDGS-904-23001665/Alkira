async function solicitarContratos(ruta, opciones = {}) {
  let respuesta

  try {
    respuesta = await fetch(`/api/administracion${ruta}`, {
      credentials: 'same-origin',
      ...opciones,
      headers: {
        Accept: 'application/json',
        ...opciones.headers,
      },
    })
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

function validarIdentificador(valor, nombreCampo) {
  const identificador = Number(valor)

  if (
    !Number.isSafeInteger(identificador) ||
    identificador <= 0
  ) {
    throw new Error(
      `El identificador de ${nombreCampo} no es válido.`
    )
  }

  return identificador
}

export const apiContratosAdministrativos = {
  // Consultar los contratos registrados.
  listarContratos: () =>
    solicitarContratos('/contratos'),

  // Consultar las unidades para el formulario.
  listarUnidades: () =>
    solicitarContratos('/contratos/unidades'),

  // Consultar los inquilinos activos.
  listarInquilinos: () =>
    solicitarContratos('/contratos/inquilinos'),

  // Registrar un contrato nuevo.
  crearContrato: (datosContrato) =>
    solicitarContratos('/contratos', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(datosContrato),
    }),

  // Subir el documento PDF de un contrato.
  subirDocumento: (idContrato, archivoPdf) => {
    const identificador = validarIdentificador(
      idContrato,
      'contrato'
    )

    if (
      !archivoPdf ||
      typeof archivoPdf.name !== 'string' ||
      !archivoPdf.name.toLowerCase().endsWith('.pdf')
    ) {
      throw new Error(
        'Debes seleccionar un archivo PDF.'
      )
    }

    if (
      archivoPdf.size <= 0 ||
      archivoPdf.size > 12582912
    ) {
      throw new Error(
        'El archivo PDF debe tener un tamaño máximo de 12 MB.'
      )
    }

    const formulario = new FormData()
    formulario.append('documento', archivoPdf)

    return solicitarContratos(
      `/contratos/${identificador}/documento`,
      {
        method: 'POST',
        body: formulario,
      }
    )
  },

  // Consultar los cobros de una unidad.
  listarCobrosPorUnidad: (idUnidad) => {
    const identificador = validarIdentificador(
      idUnidad,
      'unidad'
    )

    return solicitarContratos(
      `/unidades/${identificador}/cobros`
    )
  },
}
