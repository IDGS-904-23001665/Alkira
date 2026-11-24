
import { useCallback, useEffect, useMemo, useState } from "react";

import { apiResidentes as api } from "../../services/residentes.js";
import "../modulo1/Propiedades.css";

const formularioVacio = {
  nombres: "",
  apellidos: "",
  correo: "",
  telefono: "",
  tipoUsuario: "inquilino",
  contrasena: "",
};

const estaActivo = (residente) =>
  Number(residente.activo) === 1 &&
  residente.estado === "activo";

const nombreCompleto = (residente) =>
  `${residente.nombres} ${residente.apellidos}`;

const etiquetaEstado = (residente) => {
  if (residente.estado === "bloqueado") {
    return "Bloqueado";
  }

  return estaActivo(residente)
    ? "Activo"
    : "Inactivo";
};

function Campo({ etiqueta, children }) {
  return (
    <label className="hu01-campo">
      <span>{etiqueta}</span>
      {children}
    </label>
  );
}

function Modal({
  titulo,
  descripcion,
  onCerrar,
  children,
}) {
  useEffect(() => {
    const tecla = (evento) => {
      if (evento.key === "Escape") {
        onCerrar();
      }
    };

    document.addEventListener("keydown", tecla);

    return () =>
      document.removeEventListener("keydown", tecla);
  }, [onCerrar]);

  return (
    <div
      className="hu01-modal-fondo"
      onMouseDown={(evento) => {
        if (evento.target === evento.currentTarget) {
          onCerrar();
        }
      }}
    >
      <section
        className="hu01-modal"
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
      >
        <header>
          <div>
            <h2>{titulo}</h2>
            <p>{descripcion}</p>
          </div>

          <button
            type="button"
            className="hu01-boton-icono"
            onClick={onCerrar}
            aria-label="Cerrar"
          >
            ×
          </button>
        </header>

        {children}
      </section>
    </div>
  );
}

export default function Residentes({
  onCambiarSeccion,
}) {
  const [residentes, setResidentes] = useState([]);

  const [filtro, setFiltro] = useState("");
  const [filtroTipo, setFiltroTipo] = useState("");
  const [filtroEstado, setFiltroEstado] = useState("");

  const [modal, setModal] = useState(null);
  const [seleccionado, setSeleccionado] = useState(null);

  const [formulario, setFormulario] =
    useState({ ...formularioVacio });

  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);

  const [error, setError] = useState("");
  const [errorModal, setErrorModal] = useState("");
  const [mensaje, setMensaje] = useState("");

  const cargarResidentes = useCallback(async () => {
    setCargando(true);
    setError("");

    try {
      const datos = await api.listar();

      setResidentes(datos.residentes || []);
    } catch (error) {
      setError(error.message);
    } finally {
      setCargando(false);
    }
  }, []);

  useEffect(() => {
    cargarResidentes();
  }, [cargarResidentes]);

  const indicadores = useMemo(
    () => ({
      total: residentes.length,

      activos: residentes.filter(estaActivo).length,

      inquilinos: residentes.filter(
        (r) => r.tipoUsuario === "inquilino"
      ).length,

      propietarios: residentes.filter(
        (r) => r.tipoUsuario === "propietario"
      ).length,
    }),
    [residentes]
  );

  const residentesFiltrados = useMemo(() => {
    const texto = filtro.trim().toLowerCase();

    return residentes.filter((residente) => {
      const propiedades = (
        residente.asignaciones || []
      )
        .map(
          (a) =>
            `${a.nombreInmueble} ${a.numeroUnidad}`
        )
        .join(" ");

      const contenido = [
        nombreCompleto(residente),
        residente.correo,
        residente.telefono,
        propiedades,
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

      const coincideTexto = contenido.includes(texto);

      const coincideTipo =
        !filtroTipo ||
        residente.tipoUsuario === filtroTipo;

      const coincideEstado =
        !filtroEstado ||
        etiquetaEstado(residente).toLowerCase() ===
          filtroEstado;

      return (
        coincideTexto &&
        coincideTipo &&
        coincideEstado
      );
    });
  }, [
    residentes,
    filtro,
    filtroTipo,
    filtroEstado,
  ]);

  function cambiarCampo(campo, valor) {
    setFormulario((anterior) => ({
      ...anterior,
      [campo]: valor,
    }));
  }

  function abrirNuevo() {
    setSeleccionado(null);
    setFormulario({ ...formularioVacio });
    setErrorModal("");
    setMensaje("");
    setModal("formulario");
  }

  function abrirEdicion(residente) {
    setSeleccionado(residente);

    setFormulario({
      nombres: residente.nombres,
      apellidos: residente.apellidos,
      correo: residente.correo,
      telefono: residente.telefono || "",
      tipoUsuario: residente.tipoUsuario,
      contrasena: "",
    });

    setErrorModal("");
    setMensaje("");
    setModal("formulario");
  }

  function abrirDetalle(residente) {
    setSeleccionado(residente);
    setModal("detalle");
  }

  function cerrarModal() {
    if (guardando) return;

    setModal(null);
    setSeleccionado(null);
    setErrorModal("");
    setFormulario({ ...formularioVacio });
  }

  async function guardarResidente(evento) {
    evento.preventDefault();

    if (guardando) return;

    setGuardando(true);
    setErrorModal("");
    setMensaje("");

    try {
      const datos = {
        ...formulario,
        nombres: formulario.nombres.trim(),
        apellidos: formulario.apellidos.trim(),
        correo: formulario.correo.trim(),
        telefono: formulario.telefono.trim(),
      };

      const respuesta = seleccionado
        ? await api.actualizar(
            seleccionado.idUsuario,
            datos
          )
        : await api.crear(datos);

      setMensaje(respuesta.mensaje);
      setModal(null);
      setSeleccionado(null);
      setFormulario({ ...formularioVacio });

      await cargarResidentes();
    } catch (error) {
      setErrorModal(error.message);
    } finally {
      setGuardando(false);
    }
  }

  async function cambiarEstado(residente) {
    if (guardando) return;

    const activo = estaActivo(residente);

    const confirmar = window.confirm(
      activo
        ? `¿Deseas desactivar a ${nombreCompleto(residente)}?`
        : `¿Deseas reactivar a ${nombreCompleto(residente)}?`
    );

    if (!confirmar) return;

    setGuardando(true);
    setError("");
    setMensaje("");

    try {
      const respuesta = activo
        ? await api.desactivar(residente.idUsuario)
        : await api.reactivar(residente.idUsuario);

      setMensaje(respuesta.mensaje);

      await cargarResidentes();
    } catch (error) {
      setError(error.message);
    } finally {
      setGuardando(false);
    }
  }

  return (
    <div className="hu01">
      <div className="hu01-cabecera-pagina">
        <div>
          <h1>Gestión de residentes</h1>

          <p>
            Administra propietarios, inquilinos y sus
            propiedades vinculadas.
          </p>
        </div>

        <span className="hu01-distintivo">
          Residentes
        </span>
      </div>

      {error && (
        <div
          className="hu01-alerta hu01-alerta-error"
          role="alert"
        >
          {error}

          <button
            type="button"
            onClick={() => setError("")}
          >
            ×
          </button>
        </div>
      )}

      {mensaje && (
        <div
          className="hu01-alerta hu01-alerta-exito"
          role="status"
        >
          {mensaje}
        </div>
      )}

      <div className="hu01-indicadores">
        {[
          ["Total de residentes", indicadores.total],
          ["Residentes activos", indicadores.activos],
          ["Inquilinos", indicadores.inquilinos],
          ["Propietarios", indicadores.propietarios],
        ].map(([etiqueta, valor]) => (
          <div
            className="hu01-indicador"
            key={etiqueta}
          >
            <span>{etiqueta}</span>
            <strong>{valor}</strong>
            <small>Usuarios registrados</small>
          </div>
        ))}
      </div>

      <section className="hu01-panel">
        <div className="hu01-panel-cabecera">
          <div>
            <h2>Registro de residentes</h2>

            <p>
              Consulta y administra los usuarios
              registrados.
            </p>
          </div>

          <button
            type="button"
            className="hu01-boton-primario"
            onClick={abrirNuevo}
            disabled={guardando}
          >
            ＋ Nuevo residente
          </button>
        </div>

        <div className="hu01-filtros">
          <input
            aria-label="Buscar residente"
            placeholder="Buscar nombre, correo o unidad..."
            value={filtro}
            onChange={(evento) =>
              setFiltro(evento.target.value)
            }
          />

          <select
            aria-label="Filtrar por tipo"
            value={filtroTipo}
            onChange={(evento) =>
              setFiltroTipo(evento.target.value)
            }
          >
            <option value="">Todos los tipos</option>

            <option value="inquilino">
              Inquilinos
            </option>

            <option value="propietario">
              Propietarios
            </option>
          </select>

          <select
            aria-label="Filtrar por estado"
            value={filtroEstado}
            onChange={(evento) =>
              setFiltroEstado(evento.target.value)
            }
          >
            <option value="">Todos los estados</option>
            <option value="activo">Activos</option>
            <option value="inactivo">Inactivos</option>
            <option value="bloqueado">Bloqueados</option>
          </select>

          <button
            type="button"
            className="hu01-boton-secundario"
            onClick={cargarResidentes}
            disabled={cargando || guardando}
          >
            Actualizar
          </button>
        </div>

        {cargando ? (
          <p className="hu01-cargando">
            Cargando residentes…
          </p>
        ) : residentesFiltrados.length === 0 ? (
          <p className="hu01-vacio">
            No se encontraron residentes.
          </p>
        ) : (
          <div className="hu01-tabla-contenedor">
            <table>
              <thead>
                <tr>
                  <th>Residente</th>
                  <th>Teléfono</th>
                  <th>Tipo</th>
                  <th>Propiedad / Unidad</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody>
                {residentesFiltrados.map((residente) => (
                  <tr key={residente.idUsuario}>
                    <td>
                      <strong>
                        {nombreCompleto(residente)}
                      </strong>

                      <small>{residente.correo}</small>
                    </td>

                    <td>
                      {residente.telefono || "—"}
                    </td>

                    <td>
                      {residente.tipoUsuario ===
                      "inquilino"
                        ? "Inquilino"
                        : "Propietario"}
                    </td>

                    <td>
                      {residente.asignaciones?.length ? (
                        residente.asignaciones.map(
                          (asignacion) => (
                            <div
                              key={asignacion.idAsignacion}
                              className="hu01-asignacion-resumen"
                            >
                              <strong>
                                {asignacion.numeroUnidad}
                              </strong>

                              <small>
                                {asignacion.nombreInmueble}
                              </small>
                            </div>
                          )
                        )
                      ) : (
                        <span className="hu01-sin-asignacion">
                          Sin asignación
                        </span>
                      )}
                    </td>

                    <td>
                      <span
                        className={`hu01-estado ${
                          estaActivo(residente)
                            ? "disponible"
                            : "inactiva"
                        }`}
                      >
                        {etiquetaEstado(residente)}
                      </span>
                    </td>

                    <td>
                      <button
                        type="button"
                        className="hu01-enlace"
                        onClick={() =>
                          abrirDetalle(residente)
                        }
                      >
                        Ver detalle
                      </button>

                      <button
                        type="button"
                        className="hu01-enlace"
                        disabled={guardando}
                        onClick={() =>
                          abrirEdicion(residente)
                        }
                      >
                        Editar
                      </button>

                      {residente.estado !== "bloqueado" && (
                        <button
                          type="button"
                          className={`hu01-enlace ${
                            estaActivo(residente)
                              ? "hu01-peligro"
                              : ""
                          }`}
                          disabled={guardando}
                          onClick={() =>
                            cambiarEstado(residente)
                          }
                        >
                          {estaActivo(residente)
                            ? "Desactivar"
                            : "Reactivar"}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {modal === "formulario" && (
        <Modal
          titulo={
            seleccionado
              ? "Editar residente"
              : "Registrar residente"
          }
          descripcion="Información personal y datos de acceso."
          onCerrar={cerrarModal}
        >
          <form onSubmit={guardarResidente}>
            {errorModal && (
              <p
                className="hu01-error-modal"
                role="alert"
              >
                {errorModal}
              </p>
            )}

            <div className="hu01-form-grid">
              <Campo etiqueta="Nombres">
                <input
                  required
                  maxLength={100}
                  value={formulario.nombres}
                  placeholder="Nombres"
                  onChange={(evento) =>
                    cambiarCampo(
                      "nombres",
                      evento.target.value
                    )
                  }
                />
              </Campo>

              <Campo etiqueta="Apellidos">
                <input
                  required
                  maxLength={120}
                  value={formulario.apellidos}
                  placeholder="Apellidos"
                  onChange={(evento) =>
                    cambiarCampo(
                      "apellidos",
                      evento.target.value
                    )
                  }
                />
              </Campo>

              <Campo etiqueta="Correo electrónico">
                <input
                  required
                  type="email"
                  maxLength={150}
                  value={formulario.correo}
                  placeholder="correo@ejemplo.com"
                  onChange={(evento) =>
                    cambiarCampo(
                      "correo",
                      evento.target.value
                    )
                  }
                />
              </Campo>

              <Campo etiqueta="Teléfono">
                <input
                  type="tel"
                  maxLength={25}
                  value={formulario.telefono}
                  placeholder="Número telefónico"
                  onChange={(evento) =>
                    cambiarCampo(
                      "telefono",
                      evento.target.value
                    )
                  }
                />
              </Campo>

              <Campo etiqueta="Tipo de residente">
                <select
                  value={formulario.tipoUsuario}
                  disabled={Boolean(seleccionado)}
                  onChange={(evento) =>
                    cambiarCampo(
                      "tipoUsuario",
                      evento.target.value
                    )
                  }
                >
                  <option value="inquilino">
                    Inquilino
                  </option>

                  <option value="propietario">
                    Propietario
                  </option>
                </select>
              </Campo>

              <Campo
                etiqueta={
                  seleccionado
                    ? "Nueva contraseña (opcional)"
                    : "Contraseña"
                }
              >
                <input
                  type="password"
                  autoComplete="new-password"
                  required={!seleccionado}
                  minLength={8}
                  maxLength={72}
                  value={formulario.contrasena}
                  placeholder={
                    seleccionado
                      ? "Dejar vacía para conservar"
                      : "Mínimo 8 caracteres"
                  }
                  onChange={(evento) =>
                    cambiarCampo(
                      "contrasena",
                      evento.target.value
                    )
                  }
                />
              </Campo>
            </div>

            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={cerrarModal}
                disabled={guardando}
              >
                Cancelar
              </button>

              <button
                type="submit"
                className="hu01-boton-primario"
                disabled={guardando}
              >
                {guardando
                  ? "Guardando…"
                  : "Guardar residente"}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {modal === "detalle" && seleccionado && (
        <Modal
          titulo={nombreCompleto(seleccionado)}
          descripcion="Información del residente."
          onCerrar={cerrarModal}
        >
          <div className="hu01-detalle-resumen">
            <div>
              <small>Tipo de usuario</small>
              <strong>
                {seleccionado.tipoUsuario}
              </strong>
            </div>

            <div>
              <small>Estado</small>
              <strong>
                {etiquetaEstado(seleccionado)}
              </strong>
            </div>

            <div>
              <small>Teléfono</small>
              <strong>
                {seleccionado.telefono || "—"}
              </strong>
            </div>
          </div>

          <div className="hu01-persona">
            <div>
              <strong>Correo electrónico</strong>
              <small>{seleccionado.correo}</small>
            </div>
          </div>

          <div className="hu01-panel-cabecera">
            <div>
              <h2>Propiedades vinculadas</h2>

              <p>
                Asignaciones vigentes del residente.
              </p>
            </div>
          </div>

          {!seleccionado.asignaciones?.length && (
            <p className="hu01-vacio">
              El residente no tiene propiedades
              asignadas.
            </p>
          )}

          {(seleccionado.asignaciones || []).map(
            (asignacion) => (
              <div
                className="hu01-persona"
                key={asignacion.idAsignacion}
              >
                <div>
                  <strong>
                    {asignacion.nombreInmueble}
                    {" · "}
                    Unidad {asignacion.numeroUnidad}
                  </strong>

                  <small>
                    {asignacion.relacion}
                    {" · Desde "}
                    {asignacion.fechaInicio}
                  </small>
                </div>

                <span className="hu01-estado ocupada">
                  Vigente
                </span>
              </div>
            )
          )}

          <div className="hu01-acciones-form">
            <button
              type="button"
              className="hu01-boton-secundario"
              onClick={cerrarModal}
            >
              Cerrar
            </button>

            <button
              type="button"
              className="hu01-boton-secundario"
              onClick={() =>
                abrirEdicion(seleccionado)
              }
            >
              Editar residente
            </button>

            <button
              type="button"
              className="hu01-boton-primario"
              onClick={() =>
                onCambiarSeccion("propiedades")
              }
            >
              Gestionar propiedades
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}
