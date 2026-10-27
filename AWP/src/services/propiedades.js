async function solicitar(ruta, opciones = {}) {
  let respuesta;
  try {
    respuesta = await fetch(`/api${ruta}`, {
      ...opciones,
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(opciones.body ? { "Content-Type": "application/json" } : {}),
        ...opciones.headers,
      },
    });
  } catch {
    throw new Error("No se pudo conectar con la API de Alkira.");
  }
  const datos = await respuesta.json().catch(() => ({}));
  if (!respuesta.ok) {
    const error = new Error(datos.mensaje || `Error HTTP ${respuesta.status}`);
    error.codigo = respuesta.status;
    throw error;
  }
  return datos;
}
const cuerpo = (datos) => JSON.stringify(datos);
export const apiPropiedades = {
  inmuebles: () => solicitar("/inmuebles"),
  crearInmueble: (datos) =>
    solicitar("/inmuebles", { method: "POST", body: cuerpo(datos) }),
  editarInmueble: (id, datos) =>
    solicitar(`/inmuebles/${id}`, { method: "PUT", body: cuerpo(datos) }),
  desactivarInmueble: (id) =>
    solicitar(`/inmuebles/${id}`, { method: "DELETE" }),
  unidades: (id) => solicitar(`/inmuebles/${id}/unidades`),
  unidad: (id) => solicitar(`/unidades/${id}`),
  crearUnidad: (id, datos) =>
    solicitar(`/inmuebles/${id}/unidades`, {
      method: "POST",
      body: cuerpo(datos),
    }),
  editarUnidad: (id, datos) =>
    solicitar(`/unidades/${id}`, { method: "PUT", body: cuerpo(datos) }),
  desactivarUnidad: (id) => solicitar(`/unidades/${id}`, { method: "DELETE" }),
  buscarUsuarios: (q) =>
    solicitar(`/usuarios/buscar?q=${encodeURIComponent(q)}`),
  asignaciones: (id) => solicitar(`/unidades/${id}/asignaciones`),
  asignar: (id, datos) =>
    solicitar(`/unidades/${id}/asignaciones`, {
      method: "POST",
      body: cuerpo(datos),
    }),
  finalizar: (id, datos) =>
    solicitar(`/asignaciones/${id}/finalizar`, {
      method: "PATCH",
      body: cuerpo(datos),
    }),
};
