import { useCallback, useEffect, useMemo, useState } from "react";
import { apiPropiedades as api } from "../../services/propiedades.js";
import "./Propiedades.css";

const vacioInmueble = {
  nombre: "",
  tipo: "condominio",
  direccion: "",
  ciudad: "",
  estadoGeografico: "",
  codigoPostal: "",
  descripcion: "",
  idInmueblePadre: "",
};
const vacioUnidad = {
  numeroUnidad: "",
  tipoUnidad: "departamento",
  superficieM2: "",
  cajonesEstacionamiento: 0,
  tieneBodega: false,
  serviciosIncluidos: "",
  descripcion: "",
  estadoUnidad: "disponible",
};
const fechaHoy = () => {
  const hoy = new Date();
  const anio = hoy.getFullYear();
  const mes = String(hoy.getMonth() + 1).padStart(2, "0");
  const dia = String(hoy.getDate()).padStart(2, "0");
  return `${anio}-${mes}-${dia}`;
};

const asignacionVigente = (asignacion, hoy = fechaHoy()) =>
  asignacion.fechaInicio <= hoy &&
  (asignacion.fechaFin === null || asignacion.fechaFin > hoy);

const personaAsignada = (asignacion) =>
  `${asignacion.nombres} ${asignacion.apellidos}`;

const nombres = (usuario) => `${usuario.nombres} ${usuario.apellidos}`;

// Calcula el estado de la unidad según sus asignaciones vigentes.
const estadoUnidadCalculado = (unidad, asignaciones = []) => {
  if (unidad.estadoUnidad === "inactiva" || Number(unidad.activo) === 0) {
    return "inactiva";
  }

  if (unidad.estadoUnidad === "mantenimiento") {
    return "mantenimiento";
  }

  const tieneOcupantes = asignaciones.some(
    (asignacion) =>
      asignacionVigente(asignacion) &&
      ["inquilino", "residente"].includes(asignacion.relacion),
  );

  return tieneOcupantes ? "ocupada" : "disponible";
};

// Convierte el estado en un texto para mostrarlo.
const estadoActivo = (unidad, asignaciones = []) => {
  const estado = estadoUnidadCalculado(unidad, asignaciones);

  const etiquetas = {
    ocupada: "Ocupada",
    disponible: "Disponible",
    mantenimiento: "Mantenimiento",
    inactiva: "Inactiva",
  };

  return etiquetas[estado];
};

function Panel({ titulo, subtitulo, acciones, children, className = "" }) {
  return (
    <section className={`hu01-panel ${className}`}>
      <div className="hu01-panel-cabecera">
        <div>
          <h2>{titulo}</h2>
          {subtitulo && <p>{subtitulo}</p>}
        </div>
        {acciones}
      </div>
      {children}
    </section>
  );
}
function Campo({ etiqueta, children }) {
  return (
    <label className="hu01-campo">
      <span>{etiqueta}</span>
      {children}
    </label>
  );
}
function Modal({ titulo, descripcion, onCerrar, children }) {
  useEffect(() => {
    const tecla = (e) => {
      if (e.key === "Escape") onCerrar();
    };
    document.addEventListener("keydown", tecla);
    return () => document.removeEventListener("keydown", tecla);
  }, [onCerrar]);
  return (
    <div
      className="hu01-modal-fondo"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onCerrar();
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

export default function Propiedades() {
  const [pestana, setPestana] = useState("resumen");
  const [inmuebles, setInmuebles] = useState([]);
  const [unidades, setUnidades] = useState([]);
  const [asignaciones, setAsignaciones] = useState({});
  const [cargando, setCargando] = useState(true);
  const [mensaje, setMensaje] = useState("");
  const [error, setError] = useState("");
  const [modal, setModal] = useState(null);
  const [formInmueble, setFormInmueble] = useState(vacioInmueble);
  const [formUnidad, setFormUnidad] = useState(vacioUnidad);
  const [idInmueble, setIdInmueble] = useState("");
  const [filtro, setFiltro] = useState("");
  const [filtroCondominio, setFiltroCondominio] = useState("");
  const [unidadSeleccionada, setUnidadSeleccionada] = useState(null);
  const [busquedaUsuario, setBusquedaUsuario] = useState("");
  const [resultadosUsuarios, setResultadosUsuarios] = useState([]);
  const [usuarioAsignado, setUsuarioAsignado] = useState(null);
  const [relacion, setRelacion] = useState("inquilino");
  const [fechaInicio, setFechaInicio] = useState(fechaHoy());
  const [fechaFin, setFechaFin] = useState("");
  const [guardando, setGuardando] = useState(false);

  const recargar = useCallback(async () => {
    setCargando(true);
    setError("");
    try {
      const respuesta = await api.inmuebles();
      const lista = respuesta.inmuebles || [];
      const resultados = await Promise.all(
        lista.map(async (inmueble) => {
          try {
            return (await api.unidades(inmueble.idInmueble)).unidades || [];
          } catch {
            return [];
          }
        }),
      );
      const listaUnidades = resultados.flat();
      // Una asignación no debe confundirse con el estado físico de la unidad.
      // Las fechas de fin son exclusivas: finalizar hoy deja de estar vigente hoy.
      const asignacionesPorUnidad = await Promise.all(
        listaUnidades.map(async (unidad) => {
          const datos = await api.asignaciones(unidad.idUnidad);
          return [unidad.idUnidad, datos.asignaciones || []];
        }),
      );
      setInmuebles(lista);
      setUnidades(listaUnidades);
      setAsignaciones(Object.fromEntries(asignacionesPorUnidad));
    } catch (e) {
      setError(e.message);
    } finally {
      setCargando(false);
    }
  }, []);
  useEffect(() => {
    recargar();
  }, [recargar]);

  const activos = useMemo(
    () => inmuebles.filter((i) => i.estadoRegistro !== "inactivo"),
    [inmuebles],
  );
  const condominios = activos.filter((i) => i.tipo === "condominio");
  const torres = activos.filter(
    (i) => i.tipo === "torre" || i.tipo === "bloque",
  );

  const unidadesActivas = unidades.filter(
    (unidad) =>
      unidad.estadoUnidad !== "inactiva" &&
      unidad.activo !== 0 &&
      unidad.activo !== "0",
  );

  const ocupadas = unidadesActivas.filter(
    (unidad) =>
      estadoUnidadCalculado(unidad, asignaciones[unidad.idUnidad] || []) ===
      "ocupada",
  ).length;

  const porcentajeOcupacion =
    unidadesActivas.length > 0
      ? ((ocupadas / unidadesActivas.length) * 100).toFixed(1)
      : "0.0";

  const mapaInmuebles = useMemo(
    () => Object.fromEntries(inmuebles.map((i) => [i.idInmueble, i])),
    [inmuebles],
  );
  const descendienteDe = (inmueble, id) => {
    let actual = inmueble,
      pasos = 0;
    while (actual && pasos < inmuebles.length) {
      if (Number(actual.idInmueble) === Number(id)) return true;
      actual = mapaInmuebles[actual.idInmueblePadre];
      pasos++;
    }
    return false;
  };
  const unidadesFiltradas = unidades.filter((u) => {
    const inmueble = mapaInmuebles[u.idInmueble];
    const texto =
      `${u.numeroUnidad} ${inmueble?.nombre || ""} ${u.tipoUnidad}`.toLowerCase();
    return (
      texto.includes(filtro.toLowerCase()) &&
      (!filtroCondominio || descendienteDe(inmueble, filtroCondominio))
    );
  });
  const torresResumen = torres.filter(
    (t) => !filtroCondominio || descendienteDe(t, filtroCondominio),
  );

  function aviso(texto) {
    setMensaje(texto);
    setError("");
    setTimeout(() => setMensaje(""), 4500);
  }
  function abrirInmueble(inmueble = null, tipo = "condominio") {
    setError("");
    setFormInmueble(
      inmueble
        ? { ...inmueble, idInmueblePadre: inmueble.idInmueblePadre ?? "" }
        : { ...vacioInmueble, tipo },
    );
    setModal({ tipo: "inmueble", id: inmueble?.idInmueble });
  }
  function abrirUnidad(unidad = null, inmueble = "") {
    setError("");
    setFormUnidad(
      unidad
        ? {
            ...unidad,
            tieneBodega: Boolean(Number(unidad.tieneBodega)),
            superficieM2: unidad.superficieM2 ?? "",
          }
        : { ...vacioUnidad },
    );
    setIdInmueble(unidad ? String(unidad.idInmueble) : String(inmueble));
    setModal({ tipo: "unidad", id: unidad?.idUnidad });
  }
  async function ejecutar(accion, texto) {
    setGuardando(true);
    setError("");
    try {
      await accion();
      setModal(null);
      aviso(texto);
      await recargar();
    } catch (e) {
      setError(e.message);
    } finally {
      setGuardando(false);
    }
  }
  function guardarInmueble(e) {
    e.preventDefault();
    const datos = {
      ...formInmueble,
      idInmueblePadre: formInmueble.idInmueblePadre
        ? Number(formInmueble.idInmueblePadre)
        : null,
    };
    delete datos.idInmueble;
    delete datos.estadoRegistro;
    ejecutar(
      () =>
        modal.id
          ? api.editarInmueble(modal.id, datos)
          : api.crearInmueble(datos),
      modal.id ? "Inmueble actualizado." : "Inmueble registrado.",
    );
  }
  function guardarUnidad(e) {
    e.preventDefault();
    const datos = {
      ...formUnidad,
      superficieM2:
        formUnidad.superficieM2 === "" ? null : Number(formUnidad.superficieM2),
      cajonesEstacionamiento: Number(formUnidad.cajonesEstacionamiento),
    };
    delete datos.idUnidad;
    delete datos.idInmueble;
    ejecutar(
      () =>
        modal.id
          ? api.editarUnidad(modal.id, datos)
          : api.crearUnidad(Number(idInmueble), datos),
      modal.id ? "Unidad actualizada." : "Unidad registrada.",
    );
  }
  async function abrirDetalle(unidad) {
    setUnidadSeleccionada(unidad);
    setModal({ tipo: "detalle" });
    setError("");
    try {
      const { asignaciones: lista } = await api.asignaciones(unidad.idUnidad);
      setAsignaciones((prev) => ({ ...prev, [unidad.idUnidad]: lista }));
    } catch (e) {
      setError(e.message);
    }
  }
  function abrirAsignacion(unidad) {
    setError("");
    setUnidadSeleccionada(unidad);
    setUsuarioAsignado(null);
    setBusquedaUsuario("");
    setResultadosUsuarios([]);
    setRelacion("inquilino");
    setFechaInicio(fechaHoy());
    setFechaFin("");
    setModal({ tipo: "asignacion" });
  }
  async function buscarUsuarios() {
    setError("");
    try {
      setResultadosUsuarios(
        (await api.buscarUsuarios(busquedaUsuario.trim())).usuarios || [],
      );
    } catch (e) {
      setError(e.message);
    }
  }
  function guardarAsignacion(e) {
    e.preventDefault();
    if (!usuarioAsignado) {
      setError("Selecciona un usuario de los resultados.");
      return;
    }
    ejecutar(
      () =>
        api.asignar(unidadSeleccionada.idUnidad, {
          idUsuario: usuarioAsignado.idUsuario,
          relacion,
          fechaInicio,
          esPrincipal: true,
        }),
      "Persona asignada a la unidad.",
    );
  }
  function finalizarAsignacion(asignacion) {
    if (
      !window.confirm(
        `¿Finalizar la asignación de ${asignacion.nombres} ${asignacion.apellidos}?`,
      )
    )
      return;
    ejecutar(
      () =>
        api.finalizar(asignacion.idAsignacion, {
          fechaFin: fechaFin || fechaHoy(),
        }),
      "Asignación finalizada.",
    );
  }
  function desactivarUnidad(unidad) {
    if (
      !window.confirm(
        `¿Desactivar la unidad ${unidad.numeroUnidad}? No se eliminará su historial.`,
      )
    )
      return;
    ejecutar(
      () => api.desactivarUnidad(unidad.idUnidad),
      "Unidad desactivada.",
    );
  }
  function desactivarInmueble(inmueble) {
    if (
      !window.confirm(
        `¿Desactivar ${inmueble.nombre}? Debe estar libre de dependencias activas.`,
      )
    )
      return;
    ejecutar(
      () => api.desactivarInmueble(inmueble.idInmueble),
      "Inmueble desactivado.",
    );
  }

  return (
    <div className="hu01">
      <div className="hu01-cabecera-pagina">
        <div>
          <h1>Gestión de propiedades</h1>
          <p>
            Administra condominios, torres, unidades y asignaciones de
            residentes.
          </p>
        </div>
        <span className="hu01-distintivo">Módulo 01</span>
      </div>
      {error && (
        <div className="hu01-alerta hu01-alerta-error" role="alert">
          {error}
          <button type="button" onClick={() => setError("")}>
            ×
          </button>
        </div>
      )}
      {mensaje && (
        <div className="hu01-alerta hu01-alerta-exito" role="status">
          {mensaje}
        </div>
      )}
      <div className="hu01-indicadores">
        {[
          ["Condominios", condominios.length, "Edificios principales"],
          ["Torres y bloques", torres.length, "Estructuras registradas"],
          ["Unidades activas", unidadesActivas.length, "Inventario disponible"],
          [
            "Ocupación",
            `${porcentajeOcupacion}%`,
            `${ocupadas} ${ocupadas === 1 ? "unidad ocupada" : "unidades ocupadas"}`,
          ],
        ].map(([etiqueta, valor, ayuda]) => (
          <div className="hu01-indicador" key={etiqueta}>
            <span>{etiqueta}</span>
            <strong>{valor}</strong>
            <small>● {ayuda}</small>
          </div>
        ))}
      </div>
      <nav
        className="hu01-pestanas"
        aria-label="Secciones del módulo de propiedades"
      >
        {[
          ["resumen", "Resumen"],
          ["inmuebles", "Inmuebles"],
          ["unidades", "Unidades"],
          ["asignaciones", "Asignaciones"],
        ].map(([id, etiqueta]) => (
          <button
            type="button"
            key={id}
            className={pestana === id ? "activo" : ""}
            onClick={() => setPestana(id)}
          >
            {etiqueta}
          </button>
        ))}
      </nav>
      {cargando && <p className="hu01-cargando">Cargando información…</p>}
      {!cargando && pestana === "resumen" && (
        <div className="hu01-resumen-grid">
          <Panel
            titulo="Distribución por torre"
            subtitulo="Ocupación de las unidades registradas."
            acciones={
              <select
                aria-label="Filtrar condominio"
                value={filtroCondominio}
                onChange={(e) => setFiltroCondominio(e.target.value)}
              >
                <option value="">Todos los condominios</option>
                {condominios.map((i) => (
                  <option key={i.idInmueble} value={i.idInmueble}>
                    {i.nombre}
                  </option>
                ))}
              </select>
            }
          >
            {torresResumen.length === 0 && (
              <p className="hu01-vacio">Aún no hay torres registradas.</p>
            )}
            <div className="hu01-barras">
              {torresResumen.map((torre) => {
                const lista = unidadesActivas.filter(
                  (u) => Number(u.idInmueble) === Number(torre.idInmueble),
                );
                const cantidad = lista.filter(
                  (unidad) =>
                    estadoUnidadCalculado(
                      unidad,
                      asignaciones[unidad.idUnidad] || [],
                    ) === "ocupada",
                ).length;
                const porcentaje = lista.length
                  ? Math.round((cantidad / lista.length) * 100)
                  : 0;
                return (
                  <div key={torre.idInmueble}>
                    <div className="hu01-barra-texto">
                      <strong>{torre.nombre}</strong>
                      <span>
                        {cantidad} / {lista.length} unidades · {porcentaje}%
                      </span>
                    </div>
                    <div className="hu01-barra">
                      <div style={{ width: `${porcentaje}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          </Panel>
          <Panel
            titulo="Accesos rápidos"
            subtitulo="Las operaciones más habituales de propiedades."
          >
            <div className="hu01-accesos">
              <button onClick={() => abrirInmueble()}>
                ＋ Registrar condominio <span>›</span>
              </button>
              <button onClick={() => abrirInmueble(null, "torre")}>
                ＋ Agregar torre <span>›</span>
              </button>
              <button onClick={() => abrirUnidad()}>
                ＋ Registrar departamento <span>›</span>
              </button>
              <button onClick={() => setPestana("asignaciones")}>
                ♙ Gestionar asignaciones <span>›</span>
              </button>
            </div>
          </Panel>
          <Panel
            titulo="Unidades recientes"
            subtitulo="Últimas unidades registradas."
            className="hu01-ancho"
          >
            <TablaUnidades
              unidades={unidadesActivas.slice(-5).reverse()}
              inmuebles={mapaInmuebles}
              asignaciones={asignaciones}
              onVer={abrirDetalle}
            />
          </Panel>
        </div>
      )}
      {!cargando && pestana === "inmuebles" && (
        <Panel
          titulo="Condominios, torres e inmuebles"
          subtitulo="Gestiona la estructura de tus propiedades."
          acciones={
            <button
              className="hu01-boton-primario"
              onClick={() => abrirInmueble()}
            >
              ＋ Nuevo inmueble
            </button>
          }
        >
          <div className="hu01-tabla-contenedor">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Ubicación</th>
                  <th>Depende de</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {inmuebles.map((i) => (
                  <tr key={i.idInmueble}>
                    <td>
                      <strong>{i.nombre}</strong>
                    </td>
                    <td>{i.tipo.replace("_", " ")}</td>
                    <td>{i.ciudad}</td>
                    <td>{mapaInmuebles[i.idInmueblePadre]?.nombre || "—"}</td>
                    <td>
                      <span
                        className={`hu01-estado ${i.estadoRegistro === "activo" ? "disponible" : "inactiva"}`}
                      >
                        {i.estadoRegistro}
                      </span>
                    </td>
                    <td>
                      <button
                        className="hu01-enlace"
                        onClick={() => abrirInmueble(i)}
                      >
                        Editar
                      </button>
                      {i.estadoRegistro === "activo" && (
                        <button
                          className="hu01-enlace hu01-peligro"
                          onClick={() => desactivarInmueble(i)}
                        >
                          Desactivar
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Panel>
      )}
      {!cargando && pestana === "unidades" && (
        <Panel
          titulo="Directorio de unidades"
          subtitulo="Consulta y administra las propiedades registradas."
          acciones={
            <button
              className="hu01-boton-primario"
              onClick={() => abrirUnidad()}
            >
              ＋ Nueva unidad
            </button>
          }
        >
          <div className="hu01-filtros">
            <input
              aria-label="Buscar unidad"
              placeholder="Buscar unidad o torre..."
              value={filtro}
              onChange={(e) => setFiltro(e.target.value)}
            />
            <select
              aria-label="Filtrar por condominio"
              value={filtroCondominio}
              onChange={(e) => setFiltroCondominio(e.target.value)}
            >
              <option value="">Todos los condominios</option>
              {condominios.map((i) => (
                <option key={i.idInmueble} value={i.idInmueble}>
                  {i.nombre}
                </option>
              ))}
            </select>
          </div>
          <TablaUnidades
            unidades={unidadesFiltradas}
            inmuebles={mapaInmuebles}
            asignaciones={asignaciones}
            onVer={abrirDetalle}
            onEditar={(u) => abrirUnidad(u)}
            onAsignar={abrirAsignacion}
            onDesactivar={desactivarUnidad}
          />
        </Panel>
      )}
      {!cargando && pestana === "asignaciones" && (
        <Panel
          titulo="Asignaciones de propietarios e inquilinos"
          subtitulo="Selecciona una unidad para consultar sus ocupantes actuales e historial."
        >
          <div className="hu01-filtros">
            <input
              aria-label="Buscar unidad"
              placeholder="Buscar unidad..."
              value={filtro}
              onChange={(e) => setFiltro(e.target.value)}
            />
          </div>
          <div className="hu01-asignacion-lista">
            {unidadesFiltradas
              .filter((u) => u.estadoUnidad !== "inactiva")
              .map((unidad) => (
                <div className="hu01-asignacion-fila" key={unidad.idUnidad}>
                  <div>
                    <strong>{unidad.numeroUnidad}</strong>
                    <small>{mapaInmuebles[unidad.idInmueble]?.nombre}</small>
                  </div>
                  <div>
                    <button
                      className="hu01-boton-secundario"
                      onClick={() => abrirDetalle(unidad)}
                    >
                      Ver historial
                    </button>
                    <button
                      className="hu01-boton-primario"
                      onClick={() => abrirAsignacion(unidad)}
                    >
                      Asignar persona
                    </button>
                  </div>
                </div>
              ))}
          </div>
        </Panel>
      )}

      {modal?.tipo === "inmueble" && (
        <Modal
          titulo={modal.id ? "Editar inmueble" : "Registrar inmueble"}
          descripcion="Organiza condominios, torres y bloques."
          onCerrar={() => setModal(null)}
        >
          <form onSubmit={guardarInmueble}>
            {error && (
              <p className="hu01-error-modal" role="alert">
                {error}
              </p>
            )}
            <div className="hu01-form-grid">
              <Campo etiqueta="Nombre *">
                <input
                  required
                  maxLength={150}
                  value={formInmueble.nombre}
                  onChange={(e) =>
                    setFormInmueble({ ...formInmueble, nombre: e.target.value })
                  }
                />
              </Campo>
              <Campo etiqueta="Tipo de inmueble">
                <select
                  value={formInmueble.tipo}
                  onChange={(e) =>
                    setFormInmueble({ ...formInmueble, tipo: e.target.value })
                  }
                >
                  {[
                    ["condominio", "Condominio"],
                    ["torre", "Torre"],
                    ["bloque", "Bloque"],
                    ["local_comercial", "Local comercial"],
                    ["otro", "Otro"],
                  ].map(([v, t]) => (
                    <option value={v} key={v}>
                      {t}
                    </option>
                  ))}
                </select>
              </Campo>
              <Campo etiqueta="Inmueble padre">
                <select
                  value={formInmueble.idInmueblePadre}
                  onChange={(e) =>
                    setFormInmueble({
                      ...formInmueble,
                      idInmueblePadre: e.target.value,
                    })
                  }
                >
                  <option value="">Sin inmueble padre</option>
                  {activos
                    .filter((i) => i.idInmueble !== modal.id)
                    .map((i) => (
                      <option key={i.idInmueble} value={i.idInmueble}>
                        {i.nombre}
                      </option>
                    ))}
                </select>
              </Campo>
              <Campo etiqueta="Dirección *">
                <input
                  required
                  maxLength={255}
                  value={formInmueble.direccion}
                  onChange={(e) =>
                    setFormInmueble({
                      ...formInmueble,
                      direccion: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Ciudad *">
                <input
                  required
                  maxLength={100}
                  value={formInmueble.ciudad}
                  onChange={(e) =>
                    setFormInmueble({ ...formInmueble, ciudad: e.target.value })
                  }
                />
              </Campo>
              <Campo etiqueta="Estado">
                <input
                  maxLength={100}
                  value={formInmueble.estadoGeografico || ""}
                  onChange={(e) =>
                    setFormInmueble({
                      ...formInmueble,
                      estadoGeografico: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Código postal">
                <input
                  maxLength={10}
                  value={formInmueble.codigoPostal || ""}
                  onChange={(e) =>
                    setFormInmueble({
                      ...formInmueble,
                      codigoPostal: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Descripción">
                <textarea
                  rows={3}
                  value={formInmueble.descripcion || ""}
                  onChange={(e) =>
                    setFormInmueble({
                      ...formInmueble,
                      descripcion: e.target.value,
                    })
                  }
                />
              </Campo>
            </div>
            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={() => setModal(null)}
              >
                Cancelar
              </button>
              <button className="hu01-boton-primario" disabled={guardando}>
                {guardando ? "Guardando…" : "Guardar inmueble"}
              </button>
            </div>
          </form>
        </Modal>
      )}
      {modal?.tipo === "unidad" && (
        <Modal
          titulo={modal.id ? "Editar unidad" : "Registrar unidad"}
          descripcion="Captura las características del departamento, local o vivienda."
          onCerrar={() => setModal(null)}
        >
          <form onSubmit={guardarUnidad}>
            {error && (
              <p className="hu01-error-modal" role="alert">
                {error}
              </p>
            )}
            <div className="hu01-form-grid">
              {!modal.id && (
                <Campo etiqueta="Inmueble o torre *">
                  <select
                    required
                    value={idInmueble}
                    onChange={(e) => setIdInmueble(e.target.value)}
                  >
                    <option value="">Seleccionar inmueble...</option>
                    {activos.map((i) => (
                      <option key={i.idInmueble} value={i.idInmueble}>
                        {i.nombre}
                      </option>
                    ))}
                  </select>
                </Campo>
              )}
              <Campo etiqueta="Número de unidad *">
                <input
                  required
                  maxLength={30}
                  value={formUnidad.numeroUnidad}
                  onChange={(e) =>
                    setFormUnidad({
                      ...formUnidad,
                      numeroUnidad: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Tipo de unidad">
                <select
                  value={formUnidad.tipoUnidad}
                  onChange={(e) =>
                    setFormUnidad({ ...formUnidad, tipoUnidad: e.target.value })
                  }
                >
                  {["departamento", "casa", "local", "oficina", "otro"].map(
                    (i) => (
                      <option value={i} key={i}>
                        {i}
                      </option>
                    ),
                  )}
                </select>
              </Campo>
              <Campo etiqueta="Superficie (m²)">
                <input
                  type="number"
                  min="0"
                  max="99999999"
                  step="0.01"
                  value={formUnidad.superficieM2 ?? ""}
                  onChange={(e) =>
                    setFormUnidad({
                      ...formUnidad,
                      superficieM2: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Cajones de estacionamiento">
                <input
                  type="number"
                  min="0"
                  max="255"
                  value={formUnidad.cajonesEstacionamiento}
                  onChange={(e) =>
                    setFormUnidad({
                      ...formUnidad,
                      cajonesEstacionamiento: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Estado">
                <select
                  value={formUnidad.estadoUnidad}
                  onChange={(e) =>
                    setFormUnidad({
                      ...formUnidad,
                      estadoUnidad: e.target.value,
                    })
                  }
                >
                  {["disponible", "ocupada", "mantenimiento", "inactiva"].map(
                    (i) => (
                      <option value={i} key={i}>
                        {i}
                      </option>
                    ),
                  )}
                </select>
              </Campo>
              <Campo etiqueta="Bodega">
                <span className="hu01-check">
                  <input
                    type="checkbox"
                    checked={formUnidad.tieneBodega}
                    onChange={(e) =>
                      setFormUnidad({
                        ...formUnidad,
                        tieneBodega: e.target.checked,
                      })
                    }
                  />{" "}
                  Incluye bodega
                </span>
              </Campo>
              <Campo etiqueta="Servicios incluidos">
                <textarea
                  rows={2}
                  value={formUnidad.serviciosIncluidos || ""}
                  onChange={(e) =>
                    setFormUnidad({
                      ...formUnidad,
                      serviciosIncluidos: e.target.value,
                    })
                  }
                />
              </Campo>
              <Campo etiqueta="Observaciones">
                <textarea
                  rows={2}
                  value={formUnidad.descripcion || ""}
                  onChange={(e) =>
                    setFormUnidad({
                      ...formUnidad,
                      descripcion: e.target.value,
                    })
                  }
                />
              </Campo>
            </div>
            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={() => setModal(null)}
              >
                Cancelar
              </button>
              <button className="hu01-boton-primario" disabled={guardando}>
                {guardando ? "Guardando…" : "Guardar unidad"}
              </button>
            </div>
          </form>
        </Modal>
      )}
      {modal?.tipo === "detalle" && unidadSeleccionada && (
        <Modal
          titulo={`Unidad ${unidadSeleccionada.numeroUnidad}`}
          descripcion={
            mapaInmuebles[unidadSeleccionada.idInmueble]?.nombre ||
            "Detalles de propiedad"
          }
          onCerrar={() => setModal(null)}
        >
          <div className="hu01-detalle-resumen">
            <div>
              <small>Superficie</small>
              <strong>{unidadSeleccionada.superficieM2 ?? "—"} m²</strong>
            </div>
            <div>
              <small>Estacionamiento</small>
              <strong>
                {unidadSeleccionada.cajonesEstacionamiento} cajones
              </strong>
            </div>
            <div>
              <small>Estado</small>
              <strong>{estadoActivo(unidadSeleccionada)}</strong>
            </div>
          </div>
          <div className="hu01-panel-cabecera">
            <div>
              <h2>Personas vinculadas</h2>
              <p>Propietarios, inquilinos y residentes.</p>
            </div>
            <button
              className="hu01-boton-primario"
              onClick={() => abrirAsignacion(unidadSeleccionada)}
            >
              ＋ Asignar persona
            </button>
          </div>
          {!(asignaciones[unidadSeleccionada.idUnidad] || []).length && (
            <p className="hu01-vacio">No hay asignaciones registradas.</p>
          )}
          {(asignaciones[unidadSeleccionada.idUnidad] || []).map((a) => (
            <div className="hu01-persona" key={a.idAsignacion}>
              <div>
                <strong>
                  {a.nombres} {a.apellidos}
                </strong>
                <small>
                  {a.relacion} · {a.correo} · Desde {a.fechaInicio}
                  {a.fechaFin ? ` hasta ${a.fechaFin}` : ""}
                </small>
              </div>
              <span
                className={`hu01-estado ${asignacionVigente(a) ? "ocupada" : "inactiva"}`}
              >
                {asignacionVigente(a) ? "Vigente" : "Finalizada"}
              </span>
              {asignacionVigente(a) && (
                <button
                  className="hu01-enlace"
                  disabled={guardando}
                  onClick={() => finalizarAsignacion(a)}
                >
                  Finalizar
                </button>
              )}
            </div>
          ))}
        </Modal>
      )}
      {modal?.tipo === "asignacion" && unidadSeleccionada && (
        <Modal
          titulo="Asignar propietario o inquilino"
          descripcion={`Unidad ${unidadSeleccionada.numeroUnidad} · ${mapaInmuebles[unidadSeleccionada.idInmueble]?.nombre || ""}`}
          onCerrar={() => setModal(null)}
        >
          <form onSubmit={guardarAsignacion}>
            {error && (
              <p className="hu01-error-modal" role="alert">
                {error}
              </p>
            )}
            <div className="hu01-form-grid">
              <Campo etiqueta="Relación">
                <select
                  value={relacion}
                  onChange={(e) => setRelacion(e.target.value)}
                >
                  <option value="inquilino">Inquilino</option>
                  <option value="propietario">Propietario</option>
                  <option value="residente">Residente</option>
                </select>
              </Campo>
              <Campo etiqueta="Fecha de inicio">
                <input
                  required
                  type="date"
                  value={fechaInicio}
                  onChange={(e) => setFechaInicio(e.target.value)}
                />
              </Campo>
            </div>
            <div className="hu01-buscador-usuarios">
              <Campo etiqueta="Buscar usuario por nombre o correo">
                <input
                  value={busquedaUsuario}
                  onChange={(e) => {
                    setBusquedaUsuario(e.target.value);
                    setUsuarioAsignado(null);
                  }}
                  placeholder="Ej. residente@alkira.test"
                />
              </Campo>
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={buscarUsuarios}
                disabled={!busquedaUsuario.trim()}
              >
                Buscar
              </button>
            </div>
            <div className="hu01-resultados">
              {resultadosUsuarios.map((u) => (
                <button
                  type="button"
                  key={u.idUsuario}
                  onClick={() => setUsuarioAsignado(u)}
                  className={
                    usuarioAsignado?.idUsuario === u.idUsuario
                      ? "seleccionado"
                      : ""
                  }
                >
                  <strong>{nombres(u)}</strong>
                  <small>
                    {u.correo} · {u.tipoUsuario}
                  </small>
                </button>
              ))}
            </div>
            {usuarioAsignado && (
              <p className="hu01-seleccionado">
                Seleccionado: {nombres(usuarioAsignado)}
              </p>
            )}
            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={() => setModal(null)}
              >
                Cancelar
              </button>
              <button
                className="hu01-boton-primario"
                disabled={guardando || !usuarioAsignado}
              >
                {guardando ? "Guardando…" : "Guardar asignación"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

function TablaUnidades({
  unidades,
  inmuebles,
  asignaciones = {},
  onVer,
  onEditar,
  onAsignar,
  onDesactivar,
}) {
  if (!unidades.length) {
    return <p className="hu01-vacio">No se encontraron unidades.</p>;
  }

  return (
    <div className="hu01-tabla-contenedor">
      <table>
        <thead>
          <tr>
            <th>Unidad</th>
            <th>Condominio / Torre</th>
            <th>Superficie</th>
            <th>Estado de unidad</th>
            <th>Asignaciones vigentes</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {unidades.map((u) => {
            const asignacionesUnidad = asignaciones[u.idUnidad] || [];

            const vigentes = asignacionesUnidad.filter((asignacion) =>
              asignacionVigente(asignacion),
            );

            const estado = estadoUnidadCalculado(u, asignacionesUnidad);

            return (
              <tr key={u.idUnidad}>
                <td>
                  <strong>{u.numeroUnidad}</strong>
                  <small>{u.tipoUnidad}</small>
                </td>

                <td>{inmuebles[u.idInmueble]?.nombre || "—"}</td>

                <td>{u.superficieM2 ?? "—"} m²</td>

                <td>
                  <span className={`hu01-estado ${estado}`}>
                    {estadoActivo(u, asignacionesUnidad)}
                  </span>
                </td>

                <td>
                  {vigentes.length > 0 ? (
                    vigentes.map((asignacion) => (
                      <div
                        key={asignacion.idAsignacion}
                        className="hu01-asignacion-resumen"
                      >
                        <strong>{personaAsignada(asignacion)}</strong>

                        <small>{asignacion.relacion} · Vigente</small>
                      </div>
                    ))
                  ) : (
                    <span className="hu01-sin-asignacion">
                      Sin asignaciones vigentes
                    </span>
                  )}
                </td>

                <td>
                  <button className="hu01-enlace" onClick={() => onVer(u)}>
                    Ver detalle
                  </button>

                  {onEditar && (
                    <button className="hu01-enlace" onClick={() => onEditar(u)}>
                      Editar
                    </button>
                  )}

                  {onAsignar && estado !== "inactiva" && (
                    <button
                      className="hu01-enlace"
                      onClick={() => onAsignar(u)}
                    >
                      Asignar
                    </button>
                  )}

                  {onDesactivar && estado !== "inactiva" && (
                    <button
                      className="hu01-enlace hu01-peligro"
                      onClick={() => onDesactivar(u)}
                    >
                      Desactivar
                    </button>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
