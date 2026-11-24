async function solicitar(ruta, opciones = {}) {
  let respuesta;

  try {
    respuesta = await fetch(`/api${ruta}`, {
      ...opciones,
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(opciones.body
          ? { "Content-Type": "application/json" }
          : {}),
        ...opciones.headers,
      },
    });
  } catch {
    throw new Error(
      "No se pudo conectar con la API de Alkira."
    );
  }

  const datos = await respuesta.json().catch(() => ({}));

  if (!respuesta.ok) {
    const error = new Error(
      datos.mensaje || `Error HTTP ${respuesta.status}`
    );

    error.codigo = respuesta.status;

    throw error;
  }

  return datos;
}

// Función original utilizada por incidencias.
export async function listarResidentes() {
  return solicitar("/incidencias/residentes");
}

// CRUD administrativo.
const ruta = "/administracion/residentes";

export const apiResidentes = {
  listar: () => solicitar(ruta),

  buscar: (id) => solicitar(`${ruta}/${id}`),

  crear: (datos) =>
    solicitar(ruta, {
      method: "POST",
      body: JSON.stringify(datos),
    }),

  actualizar: (id, datos) =>
    solicitar(`${ruta}/${id}`, {
      method: "PUT",
      body: JSON.stringify(datos),
    }),

  desactivar: (id) =>
    solicitar(`${ruta}/${id}`, {
      method: "DELETE",
    }),

  reactivar: (id) =>
    solicitar(`${ruta}/${id}/reactivar`, {
      method: "PATCH",
    }),
};
