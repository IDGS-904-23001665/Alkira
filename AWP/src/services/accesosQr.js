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

export const apiAccesosQr = {
  listarPases: (idUsuario = "") =>
    solicitar(
      idUsuario
        ? `/accesos-qr?idUsuario=${encodeURIComponent(idUsuario)}`
        : "/accesos-qr",
    ),
  bitacora: () => solicitar("/accesos-qr/bitacora"),
  generarPase: (datos) =>
    solicitar("/accesos-qr", { method: "POST", body: cuerpo(datos) }),
  validarToken: (datos) =>
    solicitar("/accesos-qr/validar", { method: "POST", body: cuerpo(datos) }),
  revocarPase: (idPase) =>
    solicitar(`/accesos-qr/${idPase}/revocar`, { method: "PATCH" }),
};
