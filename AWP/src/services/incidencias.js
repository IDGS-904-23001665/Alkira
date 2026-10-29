import { listarResidentes } from "./residentes.js";

async function solicitar(ruta, opciones = {}) {
  let respuesta;
  try {
    respuesta = await fetch(`/api${ruta}`, {
      ...opciones,
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(opciones.body && !(opciones.body instanceof FormData) ? { "Content-Type": "application/json" } : {}),
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

const cuerpo = (datos) => {
  if (!datos.archivo) return JSON.stringify(datos);
  const formulario = new FormData();
  Object.entries(datos).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== "") formulario.append(clave, valor);
  });
  return formulario;
};

export const apiIncidencias = {
  listar: (idUsuario = "") =>
    solicitar(
      idUsuario
        ? `/incidencias?idUsuario=${encodeURIComponent(idUsuario)}`
        : "/incidencias",
    ),
  detalle: (idIncidencia) => solicitar(`/incidencias/${idIncidencia}`),
  evidencias: (idIncidencia) =>
    solicitar(`/incidencias/${idIncidencia}/evidencias`),
  residentes: listarResidentes,
  crear: (datos) =>
    solicitar("/incidencias", { method: "POST", body: cuerpo(datos) }),
  agregarEvidencia: (idIncidencia, datos) =>
    solicitar(`/incidencias/${idIncidencia}/evidencias`, {
      method: "POST",
      body: cuerpo(datos),
    }),
  actualizarEstado: (idIncidencia, datos) =>
    solicitar(`/incidencias/${idIncidencia}/estado`, {
      method: "PATCH",
      body: cuerpo(datos),
    }),
};
