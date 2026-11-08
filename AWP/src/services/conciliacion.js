async function solicitar(ruta, opciones = {}) {
  const respuesta = await fetch(`/api/conciliacion${ruta}`, {
    credentials: 'include',
    ...opciones,
    headers: { Accept: 'application/json', ...(opciones.body ? { 'Content-Type': 'application/json' } : {}) },
  })
  const datos = await respuesta.json().catch(() => ({}))
  if (!respuesta.ok) throw new Error(datos.mensaje || `Error ${respuesta.status}`)
  return datos
}

export const apiConciliacion = {
  listar: () => solicitar(''),
  aprobar: (idComprobante, motivo = '') => solicitar('/aprobar', {
    method: 'POST', body: JSON.stringify({ idComprobante, motivo }),
  }),
  rechazar: (idComprobante, motivo) => solicitar('/rechazar', {
    method: 'POST', body: JSON.stringify({ idComprobante, motivo }),
  }),
  urlArchivo: (idComprobante) => `/api/comprobantes/archivo?id=${encodeURIComponent(idComprobante)}`,
  urlRecibo: (idPago) => `/api/conciliacion/recibo?id=${encodeURIComponent(idPago)}`,
}
