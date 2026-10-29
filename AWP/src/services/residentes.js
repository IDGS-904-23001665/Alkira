export async function listarResidentes() {
  let respuesta;
  try {
    respuesta = await fetch('/api/incidencias/residentes', {
      credentials: 'same-origin', headers: { Accept: 'application/json' },
    });
  } catch {
    throw new Error('No se pudo conectar con la API de Alkira.');
  }
  const datos = await respuesta.json().catch(() => ({}));
  if (!respuesta.ok) {
    const error = new Error(datos.mensaje || `Error HTTP ${respuesta.status}`);
    error.codigo = respuesta.status;
    throw error;
  }
  return datos;
}
