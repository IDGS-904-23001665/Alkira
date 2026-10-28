import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { apiIncidencias as api } from "../../services/incidencias.js";
import "../modulo1/Propiedades.css";
import "./Incidencias.css";

const ETIQUETAS_CATEGORIA = {
  plomeria: "Plomería",
  electricidad: "Electricidad",
  estructura: "Estructura",
  limpieza: "Limpieza",
  seguridad: "Seguridad",
  otro: "Otro",
};

const ETIQUETAS_ESTADO = {
  reportada: "Reportada",
  en_revision: "En revisión",
  asignada: "Asignada (con ticket)",
  en_proceso: "En proceso",
  resuelta: "Resuelta",
  cerrada: "Cerrada",
};

const ETIQUETAS_URGENCIA = {
  alta: "Alta",
  media: "Media",
  baja: "Baja",
};

const vacioReporte = {
  idUsuarioReporta: "",
  idInmueble: "",
  idUnidad: "",
  titulo: "",
  descripcion: "",
  categoria: "plomeria",
  urgencia: "media",
  rutaArchivo: "",
  tipoArchivo: "JPG",
  descripcionEvidencia: "",
};

const vaciaEvidencia = {
  rutaArchivo: "",
  tipoArchivo: "JPG",
  descripcion: "",
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
            {descripcion && <p>{descripcion}</p>}
          </div>
          <button
            type="button"
            className="hu01-boton-icono"
            onClick={onCerrar}
            aria-label="Cerrar"
          >
            x
          </button>
        </header>
        {children}
      </section>
    </div>
  );
}

export default function Incidencias() {
  const [pestana, setPestana] = useState("resumen");
  const [incidencias, setIncidencias] = useState([]);
  const [residentes, setResidentes] = useState([]);
  const [evidenciasActivas, setEvidenciasActivas] = useState([]);
  const [idIncidenciaSeleccionada, setIdIncidenciaSeleccionada] = useState("");
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState("");
  const [errorModal, setErrorModal] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [busqueda, setBusqueda] = useState("");
  const [filtroCategoria, setFiltroCategoria] = useState("");
  const [filtroUrgencia, setFiltroUrgencia] = useState("");
  const [filtroEstado, setFiltroEstado] = useState("");

  const [modalNueva, setModalNueva] = useState(false);
  const [formNueva, setFormNueva] = useState(vacioReporte);
  const [modalEstado, setModalEstado] = useState(null);
  const [formEstado, setFormEstado] = useState({
    categoria: "plomeria",
    urgencia: "media",
    estado: "en_revision",
  });
  const [modalEvidencia, setModalEvidencia] = useState(false);
  const [formEvidencia, setFormEvidencia] = useState(vaciaEvidencia);

  const inputBusquedaRef = useRef(null);

  const cargarTodo = useCallback(async () => {
    setCargando(true);
    setError("");
    try {
      const [{ incidencias: listaIncidencias }, { residentes: listaResidentes }] =
        await Promise.all([api.listar(), api.residentes()]);
      setIncidencias(listaIncidencias);
      setResidentes(listaResidentes);

      if (listaIncidencias.length > 0) {
        const idInicial =
          idIncidenciaSeleccionada &&
          listaIncidencias.some(
            (i) => Number(i.idIncidencia) === Number(idIncidenciaSeleccionada),
          )
            ? idIncidenciaSeleccionada
            : String(listaIncidencias[0].idIncidencia);
        setIdIncidenciaSeleccionada(idInicial);
        const { evidencias } = await api.evidencias(idInicial);
        setEvidenciasActivas(evidencias);
      } else {
        setEvidenciasActivas([]);
      }
    } catch (e) {
      setError(e.message);
    } finally {
      setCargando(false);
    }
  }, [idIncidenciaSeleccionada]);

  useEffect(() => {
    cargarTodo();
  }, [cargarTodo]);

  const seleccionarIncidenciaEvidencias = useCallback(async (idIncidencia) => {
    setIdIncidenciaSeleccionada(String(idIncidencia));
    if (!idIncidencia) {
      setEvidenciasActivas([]);
      return;
    }
    try {
      const { evidencias } = await api.evidencias(idIncidencia);
      setEvidenciasActivas(evidencias);
    } catch (e) {
      setError(e.message);
    }
  }, []);

  const reportadasPendientes = useMemo(
    () =>
      incidencias.filter((i) =>
        ["reportada", "en_revision"].includes(i.estado),
      ).length,
    [incidencias],
  );

  const urgenciaAlta = useMemo(
    () => incidencias.filter((i) => i.urgencia === "alta").length,
    [incidencias],
  );

  const canalizadasATicket = useMemo(
    () =>
      incidencias.filter(
        (i) =>
          ["asignada", "en_proceso", "resuelta", "cerrada"].includes(i.estado) ||
          Boolean(i.idTicket),
      ).length,
    [incidencias],
  );

  const distribucionCategorias = useMemo(() => {
    return Object.keys(ETIQUETAS_CATEGORIA).map((clave) => {
      const cantidad = incidencias.filter((i) => i.categoria === clave).length;
      const porcentaje = incidencias.length
        ? Math.round((cantidad / incidencias.length) * 100)
        : 0;
      return {
        clave,
        etiqueta: ETIQUETAS_CATEGORIA[clave],
        cantidad,
        porcentaje,
      };
    });
  }, [incidencias]);

  const incidenciasFiltradas = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    return incidencias.filter((item) => {
      const coincideTexto =
        !q ||
        item.titulo.toLowerCase().includes(q) ||
        item.descripcion.toLowerCase().includes(q) ||
        item.nombreReporta.toLowerCase().includes(q) ||
        item.numeroUnidad.toLowerCase().includes(q) ||
        String(item.idIncidencia).includes(q);
      const coincideCat = !filtroCategoria || item.categoria === filtroCategoria;
      const coincideUrg = !filtroUrgencia || item.urgencia === filtroUrgencia;
      const coincideEst = !filtroEstado || item.estado === filtroEstado;
      return coincideTexto && coincideCat && coincideUrg && coincideEst;
    });
  }, [
    incidencias,
    busqueda,
    filtroCategoria,
    filtroUrgencia,
    filtroEstado,
  ]);

  const incidenciaSeleccionadaObj = useMemo(
    () =>
      incidencias.find(
        (i) => Number(i.idIncidencia) === Number(idIncidenciaSeleccionada),
      ) || null,
    [incidencias, idIncidenciaSeleccionada],
  );

  async function ejecutar(accion, exito, cerrar) {
    setGuardando(true);
    setError("");
    setErrorModal("");
    setMensaje("");
    try {
      await accion();
      await cargarTodo();
      if (cerrar) cerrar();
      setMensaje(exito);
    } catch (e) {
      if (cerrar) setErrorModal(e.message);
      else setError(e.message);
    } finally {
      setGuardando(false);
    }
  }

  function abrirModalNueva() {
    const primerResidente =
      residentes.find((r) => r.idUnidad) || residentes[0] || {};
    setErrorModal("");
    setFormNueva({
      ...vacioReporte,
      idUsuarioReporta: primerResidente.idUsuario
        ? String(primerResidente.idUsuario)
        : "",
      idInmueble: primerResidente.idInmueble
        ? String(primerResidente.idInmueble)
        : "",
      idUnidad: primerResidente.idUnidad ? String(primerResidente.idUnidad) : "",
    });
    setModalNueva(true);
  }

  function cambiarResidenteForm(idUsuario) {
    const r = residentes.find(
      (item) => Number(item.idUsuario) === Number(idUsuario),
    );
    setFormNueva((prev) => ({
      ...prev,
      idUsuarioReporta: idUsuario,
      idInmueble: r?.idInmueble ? String(r.idInmueble) : "",
      idUnidad: r?.idUnidad ? String(r.idUnidad) : "",
    }));
  }

  function guardarNuevaIncidencia(e) {
    e.preventDefault();
    ejecutar(
      () =>
        api.crear({
          idUsuarioReporta: formNueva.idUsuarioReporta
            ? Number(formNueva.idUsuarioReporta)
            : undefined,
          idInmueble: formNueva.idInmueble
            ? Number(formNueva.idInmueble)
            : null,
          idUnidad: formNueva.idUnidad ? Number(formNueva.idUnidad) : null,
          titulo: formNueva.titulo,
          descripcion: formNueva.descripcion,
          categoria: formNueva.categoria,
          urgencia: formNueva.urgencia,
          rutaArchivo: formNueva.rutaArchivo || null,
          tipoArchivo: formNueva.tipoArchivo,
          descripcionEvidencia: formNueva.descripcionEvidencia || null,
        }),
      "Incidencia registrada correctamente en la base de datos.",
      () => setModalNueva(false),
    );
  }

  function abrirModalCategorizar(incidencia) {
    setErrorModal("");
    setModalEstado(incidencia);
    setFormEstado({
      categoria: incidencia.categoria,
      urgencia: incidencia.urgencia,
      estado: incidencia.estado,
    });
  }

  function guardarCategorizacion(e) {
    e.preventDefault();
    if (!modalEstado) return;
    ejecutar(
      () => api.actualizarEstado(modalEstado.idIncidencia, formEstado),
      formEstado.estado === "asignada"
        ? `Incidencia #${modalEstado.idIncidencia} categorizada y asignada (Ticket generado en BD).`
        : `Incidencia #${modalEstado.idIncidencia} actualizada correctamente.`,
      () => setModalEstado(null),
    );
  }

  function guardarEvidencia(e) {
    e.preventDefault();
    if (!idIncidenciaSeleccionada) return;
    ejecutar(
      () => api.agregarEvidencia(idIncidenciaSeleccionada, formEvidencia),
      "Evidencia multimedia vinculada a la incidencia.",
      () => {
        setFormEvidencia(vaciaEvidencia);
        setModalEvidencia(false);
      },
    );
  }

  return (
    <div className="hu01">
      <div className="hu01-cabecera-pagina">
        <div>
          <h1>Recepción y categorización de incidencias</h1>
          <p>
            Administra los reportes enviados por residentes, valida evidencias
            multimedia y canaliza tickets a mantenimiento.
          </p>
        </div>
        <span className="hu01-distintivo">Módulo 05</span>
      </div>

      {error && (
        <div className="hu01-alerta hu01-alerta-error" role="alert">
          <span>{error}</span>
          <button type="button" onClick={() => setError("")}>
            Cerrar
          </button>
        </div>
      )}

      {mensaje && (
        <div className="hu01-alerta hu01-alerta-exito" role="status">
          <span>{mensaje}</span>
          <button type="button" onClick={() => setMensaje("")}>
            Cerrar
          </button>
        </div>
      )}

      <div className="hu01-indicadores">
        {[
          [
            "Incidencias totales",
            incidencias.length,
            "Reportes registrados en BD",
          ],
          [
            "Por revisar",
            reportadasPendientes,
            "En espera de categorización",
          ],
          [
            "Urgencia alta",
            urgenciaAlta,
            "Atención prioritaria requerida",
          ],
          [
            "Canalizadas a ticket",
            canalizadasATicket,
            "Vinculadas con mantenimiento",
          ],
        ].map(([etiqueta, valor, ayuda]) => (
          <div className="hu01-indicador" key={etiqueta}>
            <span>{etiqueta}</span>
            <strong>{valor}</strong>
            <small>{ayuda}</small>
          </div>
        ))}
      </div>

      <nav
        className="hu01-pestanas"
        aria-label="Secciones del módulo de incidencias"
      >
        {[
          ["resumen", "Resumen"],
          ["bandeja", "Bandeja de incidencias"],
          ["evidencias", "Evidencias multimedia"],
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

      {cargando && <p className="hu01-cargando">Cargando información...</p>}

      {!cargando && pestana === "resumen" && (
        <div className="hu01-resumen-grid">
          <Panel
            titulo="Distribución por categoría"
            subtitulo="Proporción de reportes recibidos según su especialidad."
          >
            <div className="hu01-barras">
              {distribucionCategorias.map((item) => (
                <div key={item.clave}>
                  <div className="hu01-barra-texto">
                    <strong>{item.etiqueta}</strong>
                    <span>
                      {item.cantidad}{" "}
                      {item.cantidad === 1 ? "reporte" : "reportes"} ({item.porcentaje}%)
                    </span>
                  </div>
                  <div className="hu01-barra">
                    <div style={{ width: `${item.porcentaje}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </Panel>

          <Panel
            titulo="Accesos rápidos"
            subtitulo="Operaciones habituales de recepción y seguimiento."
          >
            <div className="hu01-accesos">
              <button type="button" onClick={abrirModalNueva}>
                Registrar reporte de incidencia <span>Ir</span>
              </button>
              <button
                type="button"
                onClick={() => {
                  setPestana("bandeja");
                  setTimeout(() => inputBusquedaRef.current?.focus(), 60);
                }}
              >
                Filtrar y categorizar bandeja <span>Ir</span>
              </button>
              <button
                type="button"
                onClick={() => {
                  setFiltroUrgencia("alta");
                  setPestana("bandeja");
                }}
              >
                Ver incidencias de urgencia alta <span>Ir</span>
              </button>
              <button type="button" onClick={() => setPestana("evidencias")}>
                Inspeccionar evidencias multimedia <span>Ir</span>
              </button>
            </div>
          </Panel>

          <Panel
            titulo="Incidencias recientes"
            subtitulo="Últimos reportes registrados en la base de datos."
            className="hu01-ancho"
            acciones={
              <button
                type="button"
                className="hu01-boton-primario"
                onClick={abrirModalNueva}
              >
                Nuevo reporte
              </button>
            }
          >
            {incidencias.length === 0 ? (
              <p className="hu01-vacio">Aún no hay incidencias registradas.</p>
            ) : (
              <div className="hu01-tabla-contenedor">
                <table>
                  <thead>
                    <tr>
                      <th>Folio</th>
                      <th>Incidencia</th>
                      <th>Unidad / Inmueble</th>
                      <th>Reporta</th>
                      <th>Categoría</th>
                      <th>Urgencia</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {incidencias.slice(0, 6).map((inc) => (
                      <tr key={inc.idIncidencia}>
                        <td>
                          <strong>#{inc.idIncidencia}</strong>
                          <small>{inc.fechaReporte}</small>
                        </td>
                        <td>
                          <strong>{inc.titulo}</strong>
                          <small>{inc.descripcion}</small>
                        </td>
                        <td>
                          <strong>{inc.numeroUnidad}</strong>
                          <small>{inc.nombreInmueble}</small>
                        </td>
                        <td>
                          <strong>{inc.nombreReporta}</strong>
                          <small>{inc.correoReporta}</small>
                        </td>
                        <td>
                          {ETIQUETAS_CATEGORIA[inc.categoria] || inc.categoria}
                        </td>
                        <td>
                          <span className={`hu01-estado ${inc.urgencia}`}>
                            {ETIQUETAS_URGENCIA[inc.urgencia] || inc.urgencia}
                          </span>
                        </td>
                        <td>
                          <span className={`hu01-estado ${inc.estado}`}>
                            {ETIQUETAS_ESTADO[inc.estado] || inc.estado}
                          </span>
                        </td>
                        <td>
                          <button
                            type="button"
                            className="hu01-enlace"
                            onClick={() => abrirModalCategorizar(inc)}
                          >
                            Categorizar
                          </button>
                          <button
                            type="button"
                            className="hu01-enlace"
                            onClick={() => {
                              seleccionarIncidenciaEvidencias(inc.idIncidencia);
                              setPestana("evidencias");
                            }}
                          >
                            Evidencias ({inc.totalEvidencias})
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        </div>
      )}

      {!cargando && pestana === "bandeja" && (
        <Panel
          titulo="Bandeja de recepción de incidencias"
          subtitulo="Filtra, categoriza y asigna los reportes enviados por los inquilinos."
          acciones={
            <button
              type="button"
              className="hu01-boton-primario"
              onClick={abrirModalNueva}
            >
              Registrar incidencia
            </button>
          }
        >
          <div className="hu01-filtros">
            <input
              ref={inputBusquedaRef}
              type="search"
              placeholder="Buscar por folio, título, unidad o residente..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
            />
            <select
              value={filtroCategoria}
              onChange={(e) => setFiltroCategoria(e.target.value)}
            >
              <option value="">Todas las categorías</option>
              {Object.entries(ETIQUETAS_CATEGORIA).map(([valor, etiqueta]) => (
                <option key={valor} value={valor}>
                  {etiqueta}
                </option>
              ))}
            </select>
            <select
              value={filtroUrgencia}
              onChange={(e) => setFiltroUrgencia(e.target.value)}
            >
              <option value="">Todas las urgencias</option>
              {Object.entries(ETIQUETAS_URGENCIA).map(([valor, etiqueta]) => (
                <option key={valor} value={valor}>
                  {etiqueta}
                </option>
              ))}
            </select>
            <select
              value={filtroEstado}
              onChange={(e) => setFiltroEstado(e.target.value)}
            >
              <option value="">Todos los estados</option>
              {Object.entries(ETIQUETAS_ESTADO).map(([valor, etiqueta]) => (
                <option key={valor} value={valor}>
                  {etiqueta}
                </option>
              ))}
            </select>
          </div>

          {incidenciasFiltradas.length === 0 ? (
            <p className="hu01-vacio">
              No se encontraron incidencias con los filtros seleccionados.
            </p>
          ) : (
            <div className="hu01-tabla-contenedor">
              <table>
                <thead>
                  <tr>
                    <th>Folio</th>
                    <th>Reporte</th>
                    <th>Ubicación</th>
                    <th>Residente</th>
                    <th>Categoría</th>
                    <th>Urgencia</th>
                    <th>Estado</th>
                    <th>Ticket</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {incidenciasFiltradas.map((inc) => (
                    <tr key={inc.idIncidencia}>
                      <td>
                        <strong>#{inc.idIncidencia}</strong>
                        <small>{inc.fechaReporte}</small>
                      </td>
                      <td>
                        <strong>{inc.titulo}</strong>
                        <small>{inc.descripcion}</small>
                      </td>
                      <td>
                        <strong>{inc.numeroUnidad}</strong>
                        <small>{inc.nombreInmueble}</small>
                      </td>
                      <td>
                        <strong>{inc.nombreReporta}</strong>
                        <small>{inc.telefonoReporta || inc.correoReporta}</small>
                      </td>
                      <td>
                        {ETIQUETAS_CATEGORIA[inc.categoria] || inc.categoria}
                      </td>
                      <td>
                        <span className={`hu01-estado ${inc.urgencia}`}>
                          {ETIQUETAS_URGENCIA[inc.urgencia] || inc.urgencia}
                        </span>
                      </td>
                      <td>
                        <span className={`hu01-estado ${inc.estado}`}>
                          {ETIQUETAS_ESTADO[inc.estado] || inc.estado}
                        </span>
                      </td>
                      <td>
                        {inc.idTicket ? (
                          <strong>Ticket #{inc.idTicket}</strong>
                        ) : (
                          <small>Sin asignar</small>
                        )}
                      </td>
                      <td>
                        <button
                          type="button"
                          className="hu01-enlace"
                          onClick={() => abrirModalCategorizar(inc)}
                        >
                          Categorizar / Estado
                        </button>
                        <button
                          type="button"
                          className="hu01-enlace"
                          onClick={() => {
                            seleccionarIncidenciaEvidencias(inc.idIncidencia);
                            setPestana("evidencias");
                          }}
                        >
                          Evidencias ({inc.totalEvidencias})
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      )}

      {!cargando && pestana === "evidencias" && (
        <Panel
          titulo="Evidencias multimedia por incidencia"
          subtitulo="Consulta los archivos adjuntos al reporte o vincula nueva evidencia."
          acciones={
            <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
              <select
                value={idIncidenciaSeleccionada}
                onChange={(e) =>
                  seleccionarIncidenciaEvidencias(e.target.value)
                }
              >
                {incidencias.map((inc) => (
                  <option key={inc.idIncidencia} value={inc.idIncidencia}>
                    #{inc.idIncidencia} - {inc.titulo} ({inc.numeroUnidad})
                  </option>
                ))}
              </select>
              {incidenciaSeleccionadaObj && (
                <button
                  type="button"
                  className="hu01-boton-primario"
                  onClick={() => {
                    setErrorModal("");
                    setModalEvidencia(true);
                  }}
                >
                  Adjuntar evidencia
                </button>
              )}
            </div>
          }
        >
          {incidenciaSeleccionadaObj && (
            <div className="hu01-detalle-resumen">
              <div>
                <small>INCIDENCIA SELECCIONADA</small>
                <strong>
                  #{incidenciaSeleccionadaObj.idIncidencia} -{" "}
                  {incidenciaSeleccionadaObj.titulo}
                </strong>
              </div>
              <div>
                <small>UBICACIÓN Y RESIDENTE</small>
                <strong>
                  {incidenciaSeleccionadaObj.numeroUnidad} -{" "}
                  {incidenciaSeleccionadaObj.nombreReporta}
                </strong>
              </div>
              <div>
                <small>CATEGORÍA Y ESTADO ACTUAL</small>
                <strong>
                  {ETIQUETAS_CATEGORIA[incidenciaSeleccionadaObj.categoria]} -{" "}
                  {ETIQUETAS_ESTADO[incidenciaSeleccionadaObj.estado]}
                </strong>
              </div>
            </div>
          )}

          {evidenciasActivas.length === 0 ? (
            <p className="hu01-vacio">
              Esta incidencia aún no tiene evidencias multimedia registradas.
            </p>
          ) : (
            <div className="hu05-evidencias-grid">
              {evidenciasActivas.map((ev) => (
                <article key={ev.idEvidencia} className="hu05-evidencia-card">
                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-between",
                      alignItems: "center",
                    }}
                  >
                    <span className="hu01-estado disponible">
                      {ev.tipoArchivo}
                    </span>
                    <small>{ev.fechaCarga}</small>
                  </div>
                  <strong>{ev.descripcion || "Evidencia adjunta"}</strong>
                  <small>{ev.rutaArchivo}</small>
                </article>
              ))}
            </div>
          )}
        </Panel>
      )}

      {modalNueva && (
        <Modal
          titulo="Registrar reporte de incidencia"
          descripcion="Guarda el reporte en la base de datos junto con su evidencia inicial opcional."
          onCerrar={() => setModalNueva(false)}
        >
          <form onSubmit={guardarNuevaIncidencia}>
            {errorModal && <p className="hu01-error-modal">{errorModal}</p>}
            <div className="hu01-form-grid">
              <Campo etiqueta="Residente que reporta">
                <select
                  value={formNueva.idUsuarioReporta}
                  onChange={(e) => cambiarResidenteForm(e.target.value)}
                  required
                >
                  {residentes.map((r) => (
                    <option key={r.idUsuario} value={r.idUsuario}>
                      {r.nombreCompleto}{" "}
                      {r.numeroUnidad ? `(${r.numeroUnidad})` : ""}
                    </option>
                  ))}
                </select>
              </Campo>

              <Campo etiqueta="Título de la incidencia">
                <input
                  type="text"
                  placeholder="Ej. Fuga en tubería de lavabo"
                  value={formNueva.titulo}
                  onChange={(e) =>
                    setFormNueva({ ...formNueva, titulo: e.target.value })
                  }
                  required
                />
              </Campo>

              <Campo etiqueta="Categoría">
                <select
                  value={formNueva.categoria}
                  onChange={(e) =>
                    setFormNueva({ ...formNueva, categoria: e.target.value })
                  }
                >
                  {Object.entries(ETIQUETAS_CATEGORIA).map(
                    ([valor, etiqueta]) => (
                      <option key={valor} value={valor}>
                        {etiqueta}
                      </option>
                    ),
                  )}
                </select>
              </Campo>

              <Campo etiqueta="Nivel de urgencia">
                <select
                  value={formNueva.urgencia}
                  onChange={(e) =>
                    setFormNueva({ ...formNueva, urgencia: e.target.value })
                  }
                >
                  {Object.entries(ETIQUETAS_URGENCIA).map(
                    ([valor, etiqueta]) => (
                      <option key={valor} value={valor}>
                        {etiqueta}
                      </option>
                    ),
                  )}
                </select>
              </Campo>

              <Campo etiqueta="Ruta o URL de evidencia (opcional)">
                <input
                  type="text"
                  placeholder="documentos/incidencias/evidencia.jpg"
                  value={formNueva.rutaArchivo}
                  onChange={(e) =>
                    setFormNueva({ ...formNueva, rutaArchivo: e.target.value })
                  }
                />
              </Campo>

              <Campo etiqueta="Formato de evidencia">
                <select
                  value={formNueva.tipoArchivo}
                  onChange={(e) =>
                    setFormNueva({ ...formNueva, tipoArchivo: e.target.value })
                  }
                >
                  <option value="JPG">JPG</option>
                  <option value="PNG">PNG</option>
                  <option value="MP4">MP4</option>
                  <option value="PDF">PDF</option>
                </select>
              </Campo>
            </div>

            <div style={{ marginTop: "14px" }}>
              <Campo etiqueta="Descripción detallada">
                <textarea
                  rows={3}
                  placeholder="Describe el problema detectado en la unidad o área común..."
                  value={formNueva.descripcion}
                  onChange={(e) =>
                    setFormNueva({ ...formNueva, descripcion: e.target.value })
                  }
                  required
                />
              </Campo>
            </div>

            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={() => setModalNueva(false)}
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="hu01-boton-primario"
                disabled={guardando}
              >
                {guardando ? "Guardando..." : "Guardar incidencia"}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {modalEstado && (
        <Modal
          titulo={`Categorizar incidencia #${modalEstado.idIncidencia}`}
          descripcion={`${modalEstado.titulo} - ${modalEstado.numeroUnidad} (${modalEstado.nombreReporta})`}
          onCerrar={() => setModalEstado(null)}
        >
          <form onSubmit={guardarCategorizacion}>
            {errorModal && <p className="hu01-error-modal">{errorModal}</p>}

            <div className="hu01-detalle-resumen">
              <div>
                <small>REPORTE</small>
                <strong>{modalEstado.titulo}</strong>
              </div>
              <div>
                <small>UNIDAD</small>
                <strong>{modalEstado.numeroUnidad}</strong>
              </div>
              <div>
                <small>TICKET VINCULADO</small>
                <strong>
                  {modalEstado.idTicket
                    ? `Ticket #${modalEstado.idTicket}`
                    : "Se creará al pasar a Asignada"}
                </strong>
              </div>
            </div>

            <div className="hu01-form-grid">
              <Campo etiqueta="Categoría técnica">
                <select
                  value={formEstado.categoria}
                  onChange={(e) =>
                    setFormEstado({ ...formEstado, categoria: e.target.value })
                  }
                >
                  {Object.entries(ETIQUETAS_CATEGORIA).map(
                    ([valor, etiqueta]) => (
                      <option key={valor} value={valor}>
                        {etiqueta}
                      </option>
                    ),
                  )}
                </select>
              </Campo>

              <Campo etiqueta="Urgencia validada">
                <select
                  value={formEstado.urgencia}
                  onChange={(e) =>
                    setFormEstado({ ...formEstado, urgencia: e.target.value })
                  }
                >
                  {Object.entries(ETIQUETAS_URGENCIA).map(
                    ([valor, etiqueta]) => (
                      <option key={valor} value={valor}>
                        {etiqueta}
                      </option>
                    ),
                  )}
                </select>
              </Campo>
            </div>

            <div style={{ marginTop: "14px" }}>
              <Campo etiqueta="Estado de la incidencia (pasar a 'Asignada' genera el ticket en automático)">
                <select
                  value={formEstado.estado}
                  onChange={(e) =>
                    setFormEstado({ ...formEstado, estado: e.target.value })
                  }
                >
                  {Object.entries(ETIQUETAS_ESTADO).map(([valor, etiqueta]) => (
                    <option key={valor} value={valor}>
                      {etiqueta}
                    </option>
                  ))}
                </select>
              </Campo>
            </div>

            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={() => setModalEstado(null)}
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="hu01-boton-primario"
                disabled={guardando}
              >
                {guardando ? "Actualizando..." : "Guardar cambios"}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {modalEvidencia && (
        <Modal
          titulo={`Adjuntar evidencia a incidencia #${idIncidenciaSeleccionada}`}
          descripcion="Registra la ruta o URL del archivo multimedia en la base de datos."
          onCerrar={() => setModalEvidencia(false)}
        >
          <form onSubmit={guardarEvidencia}>
            {errorModal && <p className="hu01-error-modal">{errorModal}</p>}
            <div className="hu01-form-grid">
              <Campo etiqueta="Ruta o URL del archivo">
                <input
                  type="text"
                  placeholder="documentos/incidencias/foto_evidencia.jpg"
                  value={formEvidencia.rutaArchivo}
                  onChange={(e) =>
                    setFormEvidencia({
                      ...formEvidencia,
                      rutaArchivo: e.target.value,
                    })
                  }
                  required
                />
              </Campo>

              <Campo etiqueta="Tipo de archivo">
                <select
                  value={formEvidencia.tipoArchivo}
                  onChange={(e) =>
                    setFormEvidencia({
                      ...formEvidencia,
                      tipoArchivo: e.target.value,
                    })
                  }
                >
                  <option value="JPG">JPG</option>
                  <option value="PNG">PNG</option>
                  <option value="MP4">MP4</option>
                  <option value="PDF">PDF</option>
                  <option value="otro">Otro</option>
                </select>
              </Campo>
            </div>

            <div style={{ marginTop: "14px" }}>
              <Campo etiqueta="Descripción de la evidencia">
                <input
                  type="text"
                  placeholder="Ej. Fotografía cercana de la llave angular dañada"
                  value={formEvidencia.descripcion}
                  onChange={(e) =>
                    setFormEvidencia({
                      ...formEvidencia,
                      descripcion: e.target.value,
                    })
                  }
                  required
                />
              </Campo>
            </div>

            <div className="hu01-acciones-form">
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={() => setModalEvidencia(false)}
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="hu01-boton-primario"
                disabled={guardando}
              >
                {guardando ? "Guardando..." : "Guardar evidencia"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
