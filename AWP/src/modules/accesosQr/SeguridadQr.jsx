import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { apiAccesosQr as api } from "../../services/accesosQr.js";
import { apiIncidencias } from "../../services/incidencias.js";
import "../modulo1/Propiedades.css";
import "./SeguridadQr.css";

const ETIQUETAS_ESTADO_PASE = {
  vigente: "Vigente",
  utilizado: "Utilizado",
  vencido: "Vencido",
  revocado: "Revocado",
};

const ETIQUETAS_ESTADO_VISITA = {
  pendiente: "Pendiente de entrada",
  en_interior: "En el interior",
  finalizada: "Salida registrada",
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

export default function SeguridadQr() {
  const [pestana, setPestana] = useState("caseta");
  const [pases, setPases] = useState([]);
  const [bitacora, setBitacora] = useState([]);
  const [residentes, setResidentes] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [procesando, setProcesando] = useState(false);
  const [error, setError] = useState("");
  const [errorModal, setErrorModal] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [tokenInput, setTokenInput] = useState("");
  const [observacionesInput, setObservacionesInput] = useState("");
  const [ultimoResultado, setUltimoResultado] = useState(null);
  const inputQrRef = useRef(null);

  const [busquedaBitacora, setBusquedaBitacora] = useState("");
  const [filtroEstadoVisita, setFiltroEstadoVisita] = useState("");
  const [busquedaPases, setBusquedaPases] = useState("");
  const [filtroEstadoPase, setFiltroEstadoPase] = useState("");

  const [modalGenerar, setModalGenerar] = useState(false);
  const [formPase, setFormPase] = useState({
    idUsuarioAnfitrion: "",
    nombreVisitante: "",
    horasVigencia: "6",
  });

  const cargarDatos = useCallback(async (silencioso = false) => {
    if (!silencioso) setCargando(true);
    try {
      const [
        { pases: listaPases },
        { bitacora: listaBitacora },
        { residentes: listaResidentes },
      ] = await Promise.all([
        api.listarPases(),
        api.bitacora(),
        apiIncidencias.residentes(),
      ]);
      setPases(listaPases);
      setBitacora(listaBitacora);
      setResidentes(listaResidentes);
    } catch (e) {
      if (!silencioso) setError(e.message);
    } finally {
      if (!silencioso) setCargando(false);
    }
  }, []);

  useEffect(() => {
    cargarDatos(false);
    const intervalo = setInterval(() => {
      cargarDatos(true);
    }, 15000);
    return () => clearInterval(intervalo);
  }, [cargarDatos]);

  useEffect(() => {
    if (pestana === "caseta") {
      inputQrRef.current?.focus();
    }
  }, [pestana]);

  const pasesVigentes = useMemo(
    () => pases.filter((p) => p.estado === "vigente").length,
    [pases],
  );

  const visitasEnInterior = useMemo(
    () => bitacora.filter((b) => b.estadoVisita === "en_interior").length,
    [bitacora],
  );

  const visitasFinalizadas = useMemo(
    () => bitacora.filter((b) => b.estadoVisita === "finalizada").length,
    [bitacora],
  );

  const bitacoraFiltrada = useMemo(() => {
    const q = busquedaBitacora.trim().toLowerCase();
    return bitacora.filter((item) => {
      const coincideTexto =
        !q ||
        (item.nombreVisitante || "").toLowerCase().includes(q) ||
        (item.nombreAnfitrion || "").toLowerCase().includes(q) ||
        (item.numeroUnidad || "").toLowerCase().includes(q) ||
        (item.tokenQr || "").toLowerCase().includes(q);
      const coincideEstado =
        !filtroEstadoVisita || item.estadoVisita === filtroEstadoVisita;
      return coincideTexto && coincideEstado;
    });
  }, [bitacora, busquedaBitacora, filtroEstadoVisita]);

  const pasesFiltrados = useMemo(() => {
    const q = busquedaPases.trim().toLowerCase();
    return pases.filter((item) => {
      const coincideTexto =
        !q ||
        item.nombreVisitante.toLowerCase().includes(q) ||
        item.nombreAnfitrion.toLowerCase().includes(q) ||
        item.numeroUnidad.toLowerCase().includes(q) ||
        item.tokenQr.toLowerCase().includes(q);
      const coincideEstado =
        !filtroEstadoPase || item.estado === filtroEstadoPase;
      return coincideTexto && coincideEstado;
    });
  }, [pases, busquedaPases, filtroEstadoPase]);

  async function validarQrEnCaseta(e) {
    if (e) e.preventDefault();
    if (!tokenInput.trim()) return;
    setProcesando(true);
    setError("");
    setMensaje("");
    try {
      const {
        validacion,
        bitacora: nuevaBitacora,
        pases: nuevosPases,
      } = await api.validarToken({
        tokenQr: tokenInput.trim(),
        observaciones: observacionesInput.trim() || undefined,
      });
      setUltimoResultado(validacion);
      setBitacora(nuevaBitacora);
      setPases(nuevosPases);
      setTokenInput("");
      setObservacionesInput("");
      inputQrRef.current?.focus();
    } catch (err) {
      setError(err.message);
    } finally {
      setProcesando(false);
    }
  }

  function abrirModalGenerar() {
    const anfitrionDefault =
      residentes.find((r) => r.idUnidad) || residentes[0] || {};
    setErrorModal("");
    setFormPase({
      idUsuarioAnfitrion: anfitrionDefault.idUsuario
        ? String(anfitrionDefault.idUsuario)
        : "",
      nombreVisitante: "",
      horasVigencia: "6",
    });
    setModalGenerar(true);
  }

  async function guardarNuevoPase(e) {
    e.preventDefault();
    setProcesando(true);
    setErrorModal("");
    setMensaje("");
    try {
      const { pase } = await api.generarPase({
        idUsuarioAnfitrion: formPase.idUsuarioAnfitrion
          ? Number(formPase.idUsuarioAnfitrion)
          : undefined,
        nombreVisitante: formPase.nombreVisitante,
        horasVigencia: Number(formPase.horasVigencia) || 6,
      });
      await cargarDatos(true);
      setModalGenerar(false);
      setTokenInput(pase.tokenQr);
      setMensaje(
        `Pase QR generado para ${pase.nombreVisitante} (${pase.tokenQr}).`,
      );
    } catch (err) {
      setErrorModal(err.message);
    } finally {
      setProcesando(false);
    }
  }

  async function revocarPase(pase) {
    if (
      !window.confirm(
        `¿Revocar el pase QR de ${pase.nombreVisitante} (${pase.tokenQr})?`,
      )
    )
      return;
    setProcesando(true);
    setError("");
    try {
      await api.revocarPase(pase.idPase);
      await cargarDatos(true);
      setMensaje(`Pase QR de ${pase.nombreVisitante} revocado correctamente.`);
    } catch (err) {
      setError(err.message);
    } finally {
      setProcesando(false);
    }
  }

  return (
    <div className="hu01">
      <div className="hu01-cabecera-pagina">
        <div>
          <h1>Control de acceso QR y bitácora de visitas</h1>
          <p>
            Valida pases temporales en caseta con doble escaneo (entrada y
            salida) y audita las visitas en tiempo real.
          </p>
        </div>
        <span className="hu01-distintivo">Módulo 10</span>
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
          ["Pases emitidos", pases.length, "Total registrados en BD"],
          ["Pases vigentes", pasesVigentes, "Activos para ingreso"],
          [
            "En el interior",
            visitasEnInterior,
            "Con entrada activa en condominio",
          ],
          [
            "Visitas cerradas",
            visitasFinalizadas,
            "Ciclo entrada/salida completado",
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
        aria-label="Secciones del módulo de control de acceso"
      >
        {[
          ["caseta", "Caseta QR (Escaneo)"],
          ["bitacora", "Bitácora de visitas"],
          ["pases", "Pases QR emitidos"],
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

      {!cargando && pestana === "caseta" && (
        <div className="hu01-resumen-grid">
          <Panel
            titulo="Terminal de validación QR en caseta"
            subtitulo="El primer escaneo de un pase vigente registra la ENTRADA; el segundo escaneo registra la SALIDA."
            acciones={
              <button
                type="button"
                className="hu01-boton-secundario"
                onClick={abrirModalGenerar}
              >
                Emitir pase QR
              </button>
            }
          >
            <form onSubmit={validarQrEnCaseta}>
              <div className="hu01-form-grid">
                <Campo etiqueta="Token o código QR del visitante">
                  <input
                    ref={inputQrRef}
                    type="text"
                    placeholder="Ej. ALK-QR-2026... o selecciona uno a la derecha"
                    value={tokenInput}
                    onChange={(e) => setTokenInput(e.target.value)}
                    required
                  />
                </Campo>

                <Campo etiqueta="Observaciones de caseta (opcional)">
                  <input
                    type="text"
                    placeholder="Ej. Ingresa en vehículo placas GUA-123"
                    value={observacionesInput}
                    onChange={(e) => setObservacionesInput(e.target.value)}
                  />
                </Campo>
              </div>

              <div className="hu01-acciones-form">
                <button
                  type="button"
                  className="hu01-boton-secundario"
                  onClick={() => {
                    setTokenInput("");
                    setObservacionesInput("");
                    setUltimoResultado(null);
                    inputQrRef.current?.focus();
                  }}
                >
                  Limpiar lector
                </button>
                <button
                  type="submit"
                  className="hu01-boton-primario"
                  disabled={procesando}
                >
                  {procesando ? "Validando QR..." : "Validar token QR"}
                </button>
              </div>
            </form>

            {ultimoResultado && (
              <div
                className={`hu10-resultado-escaneo ${ultimoResultado.resultado}`}
                role="status"
              >
                <div>
                  <strong style={{ display: "block", fontSize: "14px" }}>
                    Resultado: {ultimoResultado.resultado}
                  </strong>
                  <span style={{ fontSize: "12px" }}>
                    {ultimoResultado.mensaje}
                  </span>
                </div>
                <span className={`hu01-estado ${ultimoResultado.resultado}`}>
                  {ultimoResultado.resultado}
                </span>
              </div>
            )}
          </Panel>

          <Panel
            titulo="Pases rápidos para prueba en caseta"
            subtitulo="Haz clic en cualquier pase para cargarlo en el lector y probar el doble escaneo."
          >
            {pases.length === 0 ? (
              <p className="hu01-vacio">Aún no hay pases registrados.</p>
            ) : (
              <div className="hu01-accesos">
                {pases.slice(0, 5).map((p) => (
                  <button
                    type="button"
                    key={p.idPase}
                    onClick={() => {
                      setTokenInput(p.tokenQr);
                      inputQrRef.current?.focus();
                    }}
                  >
                    <div>
                      <strong style={{ display: "block" }}>
                        {p.nombreVisitante} ({p.numeroUnidad})
                      </strong>
                      <small style={{ color: "#7889a5" }}>{p.tokenQr}</small>
                    </div>
                    <span className={`hu01-estado ${p.estado}`}>
                      {ETIQUETAS_ESTADO_PASE[p.estado] || p.estado}
                    </span>
                  </button>
                ))}
              </div>
            )}
          </Panel>

          <Panel
            titulo="Últimos movimientos en bitácora"
            subtitulo="Actividad reciente de entradas y salidas registradas en la base de datos."
            className="hu01-ancho"
          >
            {bitacora.length === 0 ? (
              <p className="hu01-vacio">
                Aún no hay visitas registradas en la bitácora.
              </p>
            ) : (
              <div className="hu01-tabla-contenedor">
                <table>
                  <thead>
                    <tr>
                      <th>Visitante</th>
                      <th>Anfitrión / Unidad</th>
                      <th>Token QR</th>
                      <th>Entrada</th>
                      <th>Salida</th>
                      <th>Estado</th>
                      <th>Acción rápida</th>
                    </tr>
                  </thead>
                  <tbody>
                    {bitacora.slice(0, 6).map((b) => (
                      <tr key={b.idVisita}>
                        <td>
                          <strong>{b.nombreVisitante}</strong>
                          <small>{b.observaciones || "Sin observaciones"}</small>
                        </td>
                        <td>
                          <strong>{b.numeroUnidad}</strong>
                          <small>{b.nombreAnfitrion || "Residente"}</small>
                        </td>
                        <td>
                          <span className="hu10-token-mono">
                            {b.tokenQr || "S/T"}
                          </span>
                        </td>
                        <td>{b.fechaEntrada || "Pendiente"}</td>
                        <td>{b.fechaSalida || "Sin salida"}</td>
                        <td>
                          <span className={`hu01-estado ${b.estadoVisita}`}>
                            {ETIQUETAS_ESTADO_VISITA[b.estadoVisita] ||
                              b.estadoVisita}
                          </span>
                        </td>
                        <td>
                          {b.tokenQr && b.estadoVisita !== "finalizada" && (
                            <button
                              type="button"
                              className="hu01-enlace"
                              onClick={() => {
                                setTokenInput(b.tokenQr);
                                inputQrRef.current?.focus();
                              }}
                            >
                              {b.estadoVisita === "en_interior"
                                ? "Cargar para salida"
                                : "Cargar para entrada"}
                            </button>
                          )}
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

      {!cargando && pestana === "bitacora" && (
        <Panel
          titulo="Bitácora general de visitas"
          subtitulo="Historial completo de accesos, tiempos de permanencia y guardias en turno."
          acciones={
            <button
              type="button"
              className="hu01-boton-secundario"
              onClick={() => cargarDatos(false)}
            >
              Actualizar bitácora
            </button>
          }
        >
          <div className="hu01-filtros">
            <input
              type="search"
              placeholder="Buscar visitante, anfitrión, unidad o token..."
              value={busquedaBitacora}
              onChange={(e) => setBusquedaBitacora(e.target.value)}
            />
            <select
              value={filtroEstadoVisita}
              onChange={(e) => setFiltroEstadoVisita(e.target.value)}
            >
              <option value="">Todos los estados</option>
              {Object.entries(ETIQUETAS_ESTADO_VISITA).map(
                ([valor, etiqueta]) => (
                  <option key={valor} value={valor}>
                    {etiqueta}
                  </option>
                ),
              )}
            </select>
          </div>

          {bitacoraFiltrada.length === 0 ? (
            <p className="hu01-vacio">
              No se encontraron registros en la bitácora con esos filtros.
            </p>
          ) : (
            <div className="hu01-tabla-contenedor">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Visitante</th>
                    <th>Unidad / Anfitrión</th>
                    <th>Token QR</th>
                    <th>Fecha entrada</th>
                    <th>Fecha salida</th>
                    <th>Guardia</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  {bitacoraFiltrada.map((b) => (
                    <tr key={b.idVisita}>
                      <td>#{b.idVisita}</td>
                      <td>
                        <strong>{b.nombreVisitante}</strong>
                        <small>{b.observaciones}</small>
                      </td>
                      <td>
                        <strong>{b.numeroUnidad}</strong>
                        <small>{b.nombreAnfitrion}</small>
                      </td>
                      <td>
                        <span className="hu10-token-mono">
                          {b.tokenQr || "Manual"}
                        </span>
                      </td>
                      <td>{b.fechaEntrada || "Pendiente"}</td>
                      <td>{b.fechaSalida || "Sin salida"}</td>
                      <td>{b.nombreGuardia}</td>
                      <td>
                        <span className={`hu01-estado ${b.estadoVisita}`}>
                          {ETIQUETAS_ESTADO_VISITA[b.estadoVisita] ||
                            b.estadoVisita}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      )}

      {!cargando && pestana === "pases" && (
        <Panel
          titulo="Padrón de pases QR emitidos"
          subtitulo="Consulta todos los códigos QR generados por residentes y revoca accesos en caso necesario."
          acciones={
            <button
              type="button"
              className="hu01-boton-primario"
              onClick={abrirModalGenerar}
            >
              Generar pase QR
            </button>
          }
        >
          <div className="hu01-filtros">
            <input
              type="search"
              placeholder="Buscar por visitante, residente, unidad o token..."
              value={busquedaPases}
              onChange={(e) => setBusquedaPases(e.target.value)}
            />
            <select
              value={filtroEstadoPase}
              onChange={(e) => setFiltroEstadoPase(e.target.value)}
            >
              <option value="">Todos los estados</option>
              {Object.entries(ETIQUETAS_ESTADO_PASE).map(
                ([valor, etiqueta]) => (
                  <option key={valor} value={valor}>
                    {etiqueta}
                  </option>
                ),
              )}
            </select>
          </div>

          {pasesFiltrados.length === 0 ? (
            <p className="hu01-vacio">
              No se encontraron pases QR con los criterios seleccionados.
            </p>
          ) : (
            <div className="hu01-tabla-contenedor">
              <table>
                <thead>
                  <tr>
                    <th>Folio</th>
                    <th>Visitante</th>
                    <th>Anfitrión / Unidad</th>
                    <th>Token QR</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {pasesFiltrados.map((p) => (
                    <tr key={p.idPase}>
                      <td>#{p.idPase}</td>
                      <td>
                        <strong>{p.nombreVisitante}</strong>
                        <small>Creado: {p.fechaCreacion}</small>
                      </td>
                      <td>
                        <strong>{p.numeroUnidad}</strong>
                        <small>{p.nombreAnfitrion}</small>
                      </td>
                      <td>
                        <span className="hu10-token-mono">{p.tokenQr}</span>
                      </td>
                      <td>
                        <strong>{p.fechaInicioVigencia}</strong>
                        <small>Hasta: {p.fechaFinVigencia}</small>
                      </td>
                      <td>
                        <span className={`hu01-estado ${p.estado}`}>
                          {ETIQUETAS_ESTADO_PASE[p.estado] || p.estado}
                        </span>
                      </td>
                      <td>
                        <button
                          type="button"
                          className="hu01-enlace"
                          onClick={() => {
                            setTokenInput(p.tokenQr);
                            setPestana("caseta");
                          }}
                        >
                          Probar en caseta
                        </button>
                        {p.estado === "vigente" && (
                          <button
                            type="button"
                            className="hu01-enlace hu01-peligro"
                            onClick={() => revocarPase(p)}
                          >
                            Revocar
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      )}

      {modalGenerar && (
        <Modal
          titulo="Generar pase de acceso QR temporal"
          descripcion="Crea un token único en la base de datos con vigencia controlada para un visitante."
          onCerrar={() => setModalGenerar(false)}
        >
          <form onSubmit={guardarNuevoPase}>
            {errorModal && <p className="hu01-error-modal">{errorModal}</p>}
            <div className="hu01-form-grid">
              <Campo etiqueta="Residente anfitrión">
                <select
                  value={formPase.idUsuarioAnfitrion}
                  onChange={(e) =>
                    setFormPase({
                      ...formPase,
                      idUsuarioAnfitrion: e.target.value,
                    })
                  }
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

              <Campo etiqueta="Vigencia del pase">
                <select
                  value={formPase.horasVigencia}
                  onChange={(e) =>
                    setFormPase({ ...formPase, horasVigencia: e.target.value })
                  }
                >
                  <option value="2">2 horas</option>
                  <option value="6">6 horas</option>
                  <option value="12">12 horas</option>
                  <option value="24">24 horas (1 día)</option>
                  <option value="48">48 horas (fin de semana)</option>
                </select>
              </Campo>
            </div>

            <div style={{ marginTop: "14px" }}>
              <Campo etiqueta="Nombre completo del visitante">
                <input
                  type="text"
                  placeholder="Ej. Ing. Fernando Morales"
                  value={formPase.nombreVisitante}
                  onChange={(e) =>
                    setFormPase({
                      ...formPase,
                      nombreVisitante: e.target.value,
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
                onClick={() => setModalGenerar(false)}
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="hu01-boton-primario"
                disabled={procesando}
              >
                {procesando ? "Generando..." : "Generar código QR"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
