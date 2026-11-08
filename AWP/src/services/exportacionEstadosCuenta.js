const FORMATO_MONEDA = '"$"#,##0.00'

function obtenerMonto(valor) {
  const monto = Number(valor)

  if (!Number.isFinite(monto) || monto < 0) {
    throw new Error('Se encontró un importe inválido.')
  }

  return Math.round(monto * 100) / 100
}

function obtenerTexto(valor) {
  const texto = String(valor ?? '').trim()

  // Evita interpretar contenido externo como fórmula.
  return /^[=+\-@]/.test(texto) ? `'${texto}` : texto
}

function guardarArchivo(contenido, nombreArchivo) {
  const archivo = new Blob([contenido], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })

  const direccion = URL.createObjectURL(archivo)
  const enlace = document.createElement('a')

  enlace.href = direccion
  enlace.download = nombreArchivo

  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()

  setTimeout(() => {
    URL.revokeObjectURL(direccion)
  }, 1000)
}

export async function exportarEstadoCuentaExcel(
  unidad,
  cobros
) {
  if (!unidad || !Number.isSafeInteger(Number(unidad.idUnidad))) {
    throw new Error('Selecciona una unidad válida.')
  }

  if (!Array.isArray(cobros) || cobros.length === 0) {
    throw new Error('Esta unidad no tiene cobros para exportar.')
  }

  // Carga la librería solamente cuando se solicita el Excel.
  const modulo = await import('exceljs/dist/exceljs.min.js')
  const ExcelJS = modulo.default ?? modulo

  const libro = new ExcelJS.Workbook()

  libro.creator = 'ALKIRA'
  libro.subject = 'Historial administrativo de cobros'
  libro.created = new Date()

  const hoja = libro.addWorksheet('Estado de cuenta', {
    views: [{ state: 'frozen', ySplit: 6 }],
  })

  hoja.columns = [
    { width: 14 },
    { width: 37 },
    { width: 19 },
    { width: 19 },
    { width: 18 },
    { width: 18 },
    { width: 20 },
    { width: 17 },
  ]

  const nombreUnidad = [
    unidad.nombreInmueble,
    unidad.numeroUnidad,
  ].filter(Boolean).join(' · ')

  hoja.mergeCells('A1:H1')
  hoja.getCell('A1').value = 'ALKIRA | ESTADO DE CUENTA'

  hoja.getCell('A1').font = {
    bold: true,
    size: 17,
    color: { argb: 'FFFFFFFF' },
  }

  hoja.getCell('A1').fill = {
    type: 'pattern',
    pattern: 'solid',
    fgColor: { argb: 'FF143C72' },
  }

  hoja.getCell('A1').alignment = {
    vertical: 'middle',
  }

  hoja.getRow(1).height = 35

  hoja.mergeCells('A2:H2')
  hoja.getCell('A2').value =
    `Unidad: ${obtenerTexto(nombreUnidad)}`

  hoja.getCell('A2').font = {
    bold: true,
    size: 12,
  }

  hoja.mergeCells('A3:H3')
  hoja.getCell('A3').value =
    `Generado: ${new Date().toLocaleString('es-MX')}`

  hoja.getCell('A3').font = {
    italic: true,
    color: { argb: 'FF64748B' },
  }

  const filaEncabezados = 6

  hoja.getRow(filaEncabezados).values = [
    'Periodo',
    'Concepto',
    'Fecha límite',
    'Principal',
    'Recargo',
    'Pagado',
    'Pendiente',
    'Estado',
  ]

  const encabezados = hoja.getRow(filaEncabezados)

  encabezados.height = 28

  encabezados.eachCell((celda) => {
    celda.font = {
      bold: true,
      color: { argb: 'FFFFFFFF' },
    }

    celda.fill = {
      type: 'pattern',
      pattern: 'solid',
      fgColor: { argb: 'FF2563EB' },
    }

    celda.alignment = {
      vertical: 'middle',
    }
  })

  const totales = {
    principal: 0,
    recargo: 0,
    pagado: 0,
    pendiente: 0,
  }

  cobros.forEach((cobro, indice) => {
    const numeroFila = filaEncabezados + indice + 1

    const principal = obtenerMonto(cobro.montoPrincipal)
    const recargo = obtenerMonto(cobro.recargoMora)
    const pagado = obtenerMonto(cobro.montoPagado)
    const pendiente = obtenerMonto(cobro.montoPendiente)

    totales.principal += principal
    totales.recargo += recargo
    totales.pagado += pagado
    totales.pendiente += pendiente

    const fila = hoja.getRow(numeroFila)

    fila.values = [
      obtenerTexto(cobro.periodo),
      obtenerTexto(cobro.concepto),
      obtenerTexto(cobro.fechaLimitePago),
      principal,
      recargo,
      pagado,
      {
        formula: `MAX(0,D${numeroFila}+E${numeroFila}-F${numeroFila})`,
        result: pendiente,
      },
      obtenerTexto(cobro.estado),
    ]

    if (indice % 2 === 1) {
      fila.eachCell((celda) => {
        celda.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFF1F5F9' },
        }
      })
    }

    for (const columna of ['D', 'E', 'F', 'G']) {
      hoja.getCell(`${columna}${numeroFila}`).numFmt =
        FORMATO_MONEDA
    }

    fila.getCell(7).font = { bold: true }
  })

  const ultimaFilaDatos = filaEncabezados + cobros.length
  const numeroFilaTotal = ultimaFilaDatos + 2

  hoja.getCell(`C${numeroFilaTotal}`).value = 'TOTALES'

  const columnasTotales = [
    ['D', totales.principal],
    ['E', totales.recargo],
    ['F', totales.pagado],
    ['G', totales.pendiente],
  ]

  columnasTotales.forEach(([columna, total]) => {
    const celda = hoja.getCell(
      `${columna}${numeroFilaTotal}`
    )

    celda.value = {
      formula: `SUM(${columna}${filaEncabezados + 1}:${columna}${ultimaFilaDatos})`,
      result: Math.round(total * 100) / 100,
    }

    celda.numFmt = FORMATO_MONEDA
  })

  const filaTotales = hoja.getRow(numeroFilaTotal)

  filaTotales.height = 29

  filaTotales.eachCell({ includeEmpty: true }, (celda) => {
    celda.font = {
      bold: true,
      color: { argb: 'FF143C72' },
    }

    celda.fill = {
      type: 'pattern',
      pattern: 'solid',
      fgColor: { argb: 'FFDBEAFE' },
    }
  })

  hoja.autoFilter = {
    from: `A${filaEncabezados}`,
    to: `H${ultimaFilaDatos}`,
  }

  hoja.pageSetup = {
    orientation: 'landscape',
    fitToPage: true,
    fitToWidth: 1,
    fitToHeight: 0,
  }

  const contenido = await libro.xlsx.writeBuffer()

  guardarArchivo(
    contenido,
    `ALKIRA-estado-cuenta-unidad-${unidad.idUnidad}.xlsx`
  )
}
