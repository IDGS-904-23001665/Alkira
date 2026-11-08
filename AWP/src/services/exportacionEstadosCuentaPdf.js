const ANCHO_COLUMNAS = [22, 62, 28, 32, 29, 29, 34, 30]

const ENCABEZADOS = [
  'Periodo',
  'Concepto',
  'Fecha límite',
  'Principal',
  'Recargo',
  'Pagado',
  'Pendiente',
  'Estado',
]

function obtenerTexto(valor) {
  return String(valor ?? '')
    .replace(/\s+/g, ' ')
    .trim()
}

function obtenerCentavos(valor) {
  if (
    valor === null ||
    valor === undefined ||
    valor === ''
  ) {
    throw new Error('Hay un importe vacío en el estado de cuenta.')
  }

  const numero = Number(valor)

  if (!Number.isFinite(numero) || numero < 0) {
    throw new Error('Hay un importe inválido en el estado de cuenta.')
  }

  return Math.round(numero * 100)
}

function mostrarMoneda(centavos) {
  return (centavos / 100).toLocaleString('es-MX', {
    style: 'currency',
    currency: 'MXN',
  })
}

function obtenerPosicionesColumnas() {
  const posiciones = []
  let posicionActual = 15

  ANCHO_COLUMNAS.forEach((ancho) => {
    posiciones.push(posicionActual)
    posicionActual += ancho
  })

  return posiciones
}

export async function exportarEstadoCuentaPdf(unidad, cobros) {
  const idUnidad = Number(unidad?.idUnidad)

  if (!Number.isSafeInteger(idUnidad) || idUnidad < 1) {
    throw new Error('Selecciona una unidad válida.')
  }

  if (!Array.isArray(cobros) || cobros.length === 0) {
    throw new Error('No existen cobros para generar el PDF.')
  }

  const { jsPDF } = await import('jspdf')

  const documento = new jsPDF({
    orientation: 'landscape',
    unit: 'mm',
    format: 'a4',
  })

  const anchoPagina = documento.internal.pageSize.getWidth()
  const altoPagina = documento.internal.pageSize.getHeight()

  const posiciones = obtenerPosicionesColumnas()
  const anchoTabla = ANCHO_COLUMNAS.reduce(
    (total, ancho) => total + ancho,
    0
  )

  const nombreUnidad = [
    unidad.nombreInmueble,
    unidad.numeroUnidad,
  ]
    .filter(Boolean)
    .map(obtenerTexto)
    .join(' - ')

  const fechaReporte = new Date().toLocaleString('es-MX')

  const totales = {
    principal: 0,
    recargo: 0,
    pagado: 0,
    pendiente: 0,
  }

  documento.setProperties({
    title: `ALKIRA - Estado de cuenta - Unidad ${idUnidad}`,
    subject: 'Historial administrativo de cobros',
    author: 'ALKIRA',
    creator: 'ALKIRA AWP',
  })

  function dibujarEncabezado() {
    // Franja superior.
    documento.setFillColor(15, 23, 42)
    documento.rect(
      0,
      0,
      anchoPagina,
      25,
      'F'
    )

    documento.setTextColor(255, 255, 255)
    documento.setFont('helvetica', 'bold')
    documento.setFontSize(17)

    documento.text(
      'ALKIRA | ESTADO DE CUENTA',
      15,
      14
    )

    documento.setFont('helvetica', 'normal')
    documento.setFontSize(9)

    documento.text(
      'Historial administrativo de cobros',
      15,
      20
    )

    // Información de la unidad.
    documento.setTextColor(30, 41, 59)
    documento.setFont('helvetica', 'bold')
    documento.setFontSize(10)

    documento.text(
      `Unidad: ${nombreUnidad}`,
      15,
      33,
      { maxWidth: anchoPagina - 30 }
    )

    documento.setFont('helvetica', 'normal')
    documento.setFontSize(8)

    documento.text(
      `Generado: ${fechaReporte}`,
      15,
      39
    )

    // Encabezados de la tabla.
    documento.setFillColor(37, 99, 235)

    documento.rect(
      15,
      43,
      anchoTabla,
      10,
      'F'
    )

    documento.setFont('helvetica', 'bold')
    documento.setFontSize(8)
    documento.setTextColor(255, 255, 255)

    ENCABEZADOS.forEach((encabezado, indice) => {
      const posicionX = posiciones[indice]

      documento.text(
        encabezado,
        posicionX + 2,
        49
      )
    })

    documento.setTextColor(30, 41, 59)
    documento.setFont('helvetica', 'normal')

    return 53
  }

  let posicionY = dibujarEncabezado()

  cobros.forEach((cobro, indice) => {
    const principal = obtenerCentavos(
      cobro.montoPrincipal
    )

    const recargo = obtenerCentavos(
      cobro.recargoMora
    )

    const pagado = obtenerCentavos(
      cobro.montoPagado
    )

    const pendiente = obtenerCentavos(
      cobro.montoPendiente
    )

    totales.principal += principal
    totales.recargo += recargo
    totales.pagado += pagado
    totales.pendiente += pendiente

    documento.setFont('helvetica', 'normal')
    documento.setFontSize(8)

    // El concepto puede ocupar varias líneas.
    const concepto = obtenerTexto(cobro.concepto)

    const lineasConcepto = documento.splitTextToSize(
      concepto,
      ANCHO_COLUMNAS[1] - 4
    ).slice(0, 3)

    const altoFila = Math.max(
      9,
      lineasConcepto.length * 4 + 4
    )

    // Crear una página adicional cuando haga falta.
    if (posicionY + altoFila > altoPagina - 20) {
      documento.addPage()
      posicionY = dibujarEncabezado()
    }

    // Filas alternadas para facilitar la lectura.
    if (indice % 2 === 0) {
      documento.setFillColor(241, 245, 249)
    } else {
      documento.setFillColor(255, 255, 255)
    }

    documento.rect(
      15,
      posicionY,
      anchoTabla,
      altoFila,
      'F'
    )

    documento.setDrawColor(226, 232, 240)

    documento.line(
      15,
      posicionY + altoFila,
      15 + anchoTabla,
      posicionY + altoFila
    )

    documento.setTextColor(30, 41, 59)

    const valores = [
      obtenerTexto(cobro.periodo),
      '',
      obtenerTexto(cobro.fechaLimitePago).slice(0, 10),
      mostrarMoneda(principal),
      mostrarMoneda(recargo),
      mostrarMoneda(pagado),
      mostrarMoneda(pendiente),
      obtenerTexto(cobro.estado),
    ]

    valores.forEach((valor, columna) => {
      if (columna === 1) return

      const columnaMonetaria = columna >= 3 && columna <= 6

      if (columnaMonetaria) {
        documento.text(
          valor,
          posiciones[columna] + ANCHO_COLUMNAS[columna] - 2,
          posicionY + 6,
          { align: 'right' }
        )
      } else {
        documento.text(
          valor,
          posiciones[columna] + 2,
          posicionY + 6,
          {
            maxWidth: ANCHO_COLUMNAS[columna] - 4,
          }
        )
      }
    })

    // Concepto con ajuste de línea.
    documento.text(
      lineasConcepto,
      posiciones[1] + 2,
      posicionY + 5
    )

    posicionY += altoFila
  })

  // Espacio para los totales.
  if (posicionY + 17 > altoPagina - 20) {
    documento.addPage()
    posicionY = dibujarEncabezado()
  }

  posicionY += 4

  documento.setFillColor(219, 234, 254)

  documento.rect(
    15,
    posicionY,
    anchoTabla,
    12,
    'F'
  )

  documento.setFont('helvetica', 'bold')
  documento.setFontSize(9)
  documento.setTextColor(15, 23, 42)

  documento.text(
    'TOTALES',
    posiciones[2] + 2,
    posicionY + 8
  )

  const columnasTotales = [
    [3, totales.principal],
    [4, totales.recargo],
    [5, totales.pagado],
    [6, totales.pendiente],
  ]

  columnasTotales.forEach(([columna, centavos]) => {
    documento.text(
      mostrarMoneda(centavos),
      posiciones[columna] + ANCHO_COLUMNAS[columna] - 2,
      posicionY + 8,
      { align: 'right' }
    )
  })

  // Numeración de todas las páginas.
  const cantidadPaginas = documento.getNumberOfPages()

  for (let numeroPagina = 1; numeroPagina <= cantidadPaginas; numeroPagina++) {
    documento.setPage(numeroPagina)

    documento.setDrawColor(203, 213, 225)
    documento.line(
      15,
      altoPagina - 15,
      anchoPagina - 15,
      altoPagina - 15
    )

    documento.setFont('helvetica', 'normal')
    documento.setFontSize(8)
    documento.setTextColor(100, 116, 139)

    documento.text(
      'ALKIRA - Reporte administrativo de estado de cuenta',
      15,
      altoPagina - 10
    )

    documento.text(
      `Página ${numeroPagina} de ${cantidadPaginas}`,
      anchoPagina - 15,
      altoPagina - 10,
      { align: 'right' }
    )
  }

  // Descargar el documento.
  documento.save(
    `ALKIRA-estado-cuenta-unidad-${idUnidad}.pdf`
  )
}
