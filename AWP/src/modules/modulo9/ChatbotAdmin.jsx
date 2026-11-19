import ChatbotProbador from './ChatbotProbador.jsx'
import { useCallback, useEffect, useState } from 'react'

const NUEVA_FAQ = {
  pregunta: '',
  respuesta: '',
  categoria: 'General',
  palabrasClave: '',
  prioridad: 5,
  activa: true,
}

async function pedir(ruta, opciones = {}) {
  let respuesta

  try {
    respuesta = await fetch(`/api/chatbot-admin${ruta}`, {
      credentials: 'same-origin',
      ...opciones,
      headers: {
        Accept: 'application/json',
        ...(opciones.body
          ? { 'Content-Type': 'application/json' }
          : {}),
        ...(opciones.method && opciones.method !== 'GET'
          ? { 'X-Alkira-Admin': '1' }
          : {}),
        ...opciones.headers,
      },
    })
  } catch {
    throw new Error(
      'No fue posible conectar con el backend de ALKIRA.'
    )
  }

  const datos = await respuesta.json().catch(() => ({}))

  if (!respuesta.ok) {
    throw new Error(
      datos.mensaje || `Error HTTP ${respuesta.status}`
    )
  }

  return datos
}

function formatoFecha(fecha) {
  if (!fecha) return '—'

  const parseada = new Date(
    String(fecha).replace(' ', 'T')
  )

  return Number.isNaN(parseada.getTime())
    ? String(fecha)
    : parseada.toLocaleString('es-MX')
}

function textoAuditoria(registro) {
  try {
    const valor = JSON.parse(
      registro.despues || registro.antes || '{}'
    )

    return valor.pregunta ||
      `FAQ #${registro.idFaq ?? 'eliminada'}`
  } catch {
    return `FAQ #${registro.idFaq ?? 'eliminada'}`
  }
}

export default function ChatbotAdmin() {
  const [pestana, setPestana] = useState('faqs')
  const [faqs, setFaqs] = useState([])
  const [conversaciones, setConversaciones] = useState([])
  const [auditoria, setAuditoria] = useState([])
  const [seleccion, setSeleccion] = useState(null)

  const [formulario, setFormulario] = useState(NUEVA_FAQ)
  const [idEdicion, setIdEdicion] = useState(null)
  const [busqueda, setBusqueda] = useState('')

  const [cargando, setCargando] = useState(false)
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')
  const [exito, setExito] = useState('')

  const actualizar = useCallback(async (actual = 'faqs') => {
    setCargando(true)
    setError('')

    try {
      if (actual === 'faqs') {
        const datos = await pedir('/faqs')
        setFaqs(datos.faqs ?? [])
      } else if (actual === 'conversaciones') {
        const datos = await pedir('/conversaciones')
        setConversaciones(datos.conversaciones ?? [])
      } else {
        const datos = await pedir('/auditoria')
        setAuditoria(datos.auditoria ?? [])
      }
    } catch (e) {
      setError(e.message)
    } finally {
      setCargando(false)
    }
  }, [])

  useEffect(() => {
  if (pestana !== 'probar') {
    actualizar(pestana)
  }
}, [pestana, actualizar])

  function comenzarEdicion(faq) {
    setIdEdicion(Number(faq.id))

    setFormulario({
      pregunta: faq.pregunta,
      respuesta: faq.respuesta,
      categoria: faq.categoria,
      palabrasClave: faq.palabrasClave,
      prioridad: Number(faq.prioridad),
      activa: Number(faq.activa) === 1,
    })

    setError('')
    setExito('')

    document.getElementById('formulario-faq-alkira')
      ?.scrollIntoView({ behavior: 'smooth' })
  }

  function limpiar() {
    setIdEdicion(null)
    setFormulario({ ...NUEVA_FAQ })
  }

  async function guardar(evento) {
    evento.preventDefault()

    setGuardando(true)
    setError('')
    setExito('')

    try {
      const ruta = idEdicion
        ? `/faqs/${idEdicion}`
        : '/faqs'

      const datos = await pedir(ruta, {
        method: idEdicion ? 'PUT' : 'POST',
        body: JSON.stringify({
          ...formulario,
          prioridad: Number(formulario.prioridad),
        }),
      })

      setExito(datos.mensaje)
      limpiar()
      await actualizar('faqs')
    } catch (e) {
      setError(e.message)
    } finally {
      setGuardando(false)
    }
  }

  async function eliminar(faq) {
    if (!window.confirm(
      `¿Eliminar la FAQ "${faq.pregunta}"? Esta acción quedará auditada.`
    )) {
      return
    }

    setError('')
    setExito('')

    try {
      const datos = await pedir(
        `/faqs/${faq.id}`,
        { method: 'DELETE' }
      )

      setExito(datos.mensaje)

      if (idEdicion === Number(faq.id)) {
        limpiar()
      }

      await actualizar('faqs')
    } catch (e) {
      setError(e.message)
    }
  }

  async function verMensajes(conversacion) {
    setError('')

    if (seleccion?.conversacion?.id === conversacion.id) {
      setSeleccion(null)
      return
    }

    try {
      const datos = await pedir(
        `/conversaciones/${conversacion.id}/mensajes`
      )

      setSeleccion(datos)
    } catch (e) {
      setError(e.message)
    }
  }

  const filtradas = faqs.filter((faq) =>
    `${faq.pregunta} ${faq.categoria} ${faq.palabrasClave} ${faq.respuesta}`
      .toLocaleLowerCase('es-MX')
      .includes(busqueda.toLocaleLowerCase('es-MX'))
  )

  return (
    <section
      className="container-fluid px-0"
      aria-label="Administración del chatbot ALKIRA"
    >
      <div className="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
        <div>
          <h2 className="h4 fw-bold mb-1">
            Asistente virtual ALKIRA
          </h2>

          <p className="text-secondary mb-0">
            HU09.2 · Administración de preguntas frecuentes,
            reglas y conversaciones.
          </p>
        </div>

        {pestana !== 'probar' && (
  <button
    className="btn btn-outline-primary"
    disabled={cargando}
    onClick={() => actualizar(pestana)}
    type="button"
  >
    {cargando ? 'Actualizando...' : 'Actualizar datos'}
  </button>
)}
      </div>

      <div
        className="d-flex flex-wrap gap-2 mb-4"
        role="tablist"
        aria-label="Secciones del asistente"
      >
        {[
          ['faqs', 'Base de conocimiento'],
          ['conversaciones', 'Conversaciones'],
          ['auditoria', 'Auditoría de cambios'],
          ['probar', 'Probar asistente'],
        ].map(([id, titulo]) => (
          <button
            key={id}
            role="tab"
            aria-selected={pestana === id}
            type="button"
            className={`btn ${
              pestana === id
                ? 'btn-primary'
                : 'btn-outline-primary'
            }`}
            onClick={() => {
              setPestana(id)
              setError('')
              setExito('')
            }}
          >
            {titulo}
          </button>
        ))}
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      {exito && (
        <div className="alert alert-success" role="status">
          {exito}
        </div>
      )}

      {pestana === 'probar' && (
        <ChatbotProbador />
        )}

      {pestana === 'faqs' && (
        <div className="row g-4">
          <div className="col-12 col-xl-5">
            <div
              id="formulario-faq-alkira"
              className="card border-0 shadow-sm"
            >
              <div className="card-body">
                <h3 className="h5 mb-3">
                  {idEdicion
                    ? `Editar pregunta #${idEdicion}`
                    : 'Nueva pregunta frecuente'}
                </h3>

                <form onSubmit={guardar}>
                  <label className="form-label w-100">
                    Pregunta

                    <input
                      className="form-control mt-1"
                      required
                      minLength={5}
                      maxLength={255}
                      value={formulario.pregunta}
                      onChange={(e) => setFormulario({
                        ...formulario,
                        pregunta: e.target.value,
                      })}
                      placeholder="Ej. ¿Cómo consulto mi saldo?"
                    />
                  </label>

                  <label className="form-label w-100">
                    Respuesta del asistente

                    <textarea
                      className="form-control mt-1"
                      rows={5}
                      required
                      minLength={5}
                      maxLength={8000}
                      value={formulario.respuesta}
                      onChange={(e) => setFormulario({
                        ...formulario,
                        respuesta: e.target.value,
                      })}
                      placeholder="Escribe la respuesta que recibirá el residente."
                    />
                  </label>

                  <label className="form-label w-100">
                    Categoría

                    <input
                      className="form-control mt-1"
                      maxLength={80}
                      value={formulario.categoria}
                      onChange={(e) => setFormulario({
                        ...formulario,
                        categoria: e.target.value,
                      })}
                      placeholder="Pagos, mantenimiento, acceso..."
                    />
                  </label>

                  <label className="form-label w-100">
                    Palabras clave (separadas por coma)

                    <input
                      className="form-control mt-1"
                      maxLength={500}
                      value={formulario.palabrasClave}
                      onChange={(e) => setFormulario({
                        ...formulario,
                        palabrasClave: e.target.value,
                      })}
                      placeholder="saldo, adeudo, cuánto debo"
                    />
                  </label>

                  <div className="row g-3 my-1">
                    <div className="col-sm-6">
                      <label className="form-label w-100">
                        Prioridad de la regla (1 a 10)

                        <input
                          type="number"
                          min="1"
                          max="10"
                          required
                          className="form-control mt-1"
                          value={formulario.prioridad}
                          onChange={(e) => setFormulario({
                            ...formulario,
                            prioridad: e.target.value,
                          })}
                        />
                      </label>
                    </div>

                    <div className="col-sm-6 d-flex align-items-center">
                      <label className="form-check-label d-flex align-items-center gap-2">
                        <input
                          type="checkbox"
                          className="form-check-input"
                          checked={formulario.activa}
                          onChange={(e) => setFormulario({
                            ...formulario,
                            activa: e.target.checked,
                          })}
                        />
                        Respuesta activa
                      </label>
                    </div>
                  </div>

                  <div className="d-flex gap-2 flex-wrap mt-2">
                    <button
                      type="submit"
                      disabled={guardando}
                      className="btn btn-primary"
                    >
                      {guardando
                        ? 'Guardando...'
                        : idEdicion
                          ? 'Guardar cambios'
                          : 'Crear FAQ'}
                    </button>

                    {idEdicion && (
                      <button
                        type="button"
                        className="btn btn-outline-secondary"
                        onClick={limpiar}
                      >
                        Cancelar edición
                      </button>
                    )}
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div className="col-12 col-xl-7">
            <div className="card border-0 shadow-sm">
              <div className="card-body">
                <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <h3 className="h5 m-0">
                    Preguntas frecuentes ({filtradas.length})
                  </h3>

                  <input
                    className="form-control"
                    style={{ maxWidth: 280 }}
                    aria-label="Buscar preguntas frecuentes"
                    placeholder="Buscar preguntas..."
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                  />
                </div>

                {!filtradas.length && (
                  <p className="text-secondary">
                    No hay FAQs para esta búsqueda.
                  </p>
                )}

                <div className="d-flex flex-column gap-3">
                  {filtradas.map((faq) => (
                    <article
                      key={faq.id}
                      className="border rounded p-3"
                    >
                      <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                          <span
                            className={`badge ${
                              Number(faq.activa) === 1
                                ? 'text-bg-success'
                                : 'text-bg-secondary'
                            } me-2`}
                          >
                            {Number(faq.activa) === 1
                              ? 'Activa'
                              : 'Inactiva'}
                          </span>

                          <span className="badge text-bg-light border">
                            {faq.categoria} · prioridad {faq.prioridad}
                          </span>
                        </div>

                        <div className="d-flex gap-2">
                          <button
                            type="button"
                            className="btn btn-outline-primary btn-sm"
                            onClick={() => comenzarEdicion(faq)}
                          >
                            Editar
                          </button>

                          <button
                            type="button"
                            className="btn btn-outline-danger btn-sm"
                            onClick={() => eliminar(faq)}
                          >
                            Eliminar
                          </button>
                        </div>
                      </div>

                      <h4 className="h6 fw-bold mt-3">
                        {faq.pregunta}
                      </h4>

                      <p
                        className="mb-2"
                        style={{ whiteSpace: 'pre-wrap' }}
                      >
                        {faq.respuesta}
                      </p>

                      <p className="small text-secondary mb-0">
                        Claves: {faq.palabrasClave || 'Sin palabras clave'}
                        {' · '}
                        Actualizada: {formatoFecha(faq.fechaActualizacion)}
                      </p>
                    </article>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {pestana === 'conversaciones' && (
        <div className="card border-0 shadow-sm">
          <div className="card-body">
            <h3 className="h5">
              Historial de conversaciones
            </h3>

            <p className="small text-secondary">
              Últimas 50 conversaciones registradas.
              Lectura administrativa; no es posible modificar mensajes.
            </p>

            {!conversaciones.length && (
              <p className="text-secondary">
                Todavía no hay conversaciones registradas.
              </p>
            )}

            {conversaciones.map((conv) => (
              <div
                className="border rounded p-3 mb-3"
                key={conv.id}
              >
                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div>
                    <strong>
                      #{conv.id} · {conv.residente}
                    </strong>

                    <div className="small text-secondary">
                      {conv.correo} · {formatoFecha(conv.fechaInicio)}
                      {' · '}{conv.estado}
                      {' · '}{conv.totalMensajes} mensajes
                    </div>
                  </div>

                  <button
                    className="btn btn-outline-primary btn-sm"
                    type="button"
                    onClick={() => verMensajes(conv)}
                  >
                    {seleccion?.conversacion?.id === conv.id
                      ? 'Ocultar mensajes'
                      : 'Ver mensajes'}
                  </button>
                </div>

                {seleccion?.conversacion?.id === conv.id && (
                  <div className="mt-3 border-top pt-3">
                    <p className="small text-secondary">
                      Se muestran hasta {seleccion.limite}
                      {' '}mensajes en orden de registro.
                    </p>

                    {!seleccion.mensajes.length && (
                      <p>
                        Esta conversación no tiene mensajes.
                      </p>
                    )}

                    {seleccion.mensajes.map((m) => (
                      <div
                        className="mb-2 p-3 rounded border"
                        key={m.id}
                      >
                        <div className="small fw-bold mb-1">
                          {m.emisor === 'usuario'
                            ? 'Residente'
                            : 'Asistente'}
                          {' · '}
                          {formatoFecha(m.fecha)}
                        </div>

                        <div style={{
                          whiteSpace: 'pre-wrap',
                          overflowWrap: 'anywhere',
                        }}>
                          {m.contenido}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {pestana === 'auditoria' && (
        <div className="card border-0 shadow-sm">
          <div className="card-body">
            <h3 className="h5">
              Auditoría de la base de conocimiento
            </h3>

            <p className="small text-secondary">
              Últimos 100 cambios; se conserva quién
              modificó las FAQs y su versión anterior.
            </p>

            {!auditoria.length && (
              <p className="text-secondary">
                Todavía no hay cambios registrados.
              </p>
            )}

            <div className="table-responsive">
              <table className="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Administrador</th>
                    <th>Acción</th>
                    <th>Pregunta</th>
                    <th>Detalle</th>
                  </tr>
                </thead>

                <tbody>
                  {auditoria.map((item) => (
                    <tr key={item.id}>
                      <td>{formatoFecha(item.fecha)}</td>
                      <td>{item.administrador}</td>
                      <td>{item.accion}</td>
                      <td>{textoAuditoria(item)}</td>

                      <td>
                        <details>
                          <summary
                            className="small"
                            style={{ cursor: 'pointer' }}
                          >
                            Ver versiones
                          </summary>

                          <div className="small mt-2">
                            Antes:
                          </div>

                          <pre
                            className="small border rounded p-2"
                            style={{
                              whiteSpace: 'pre-wrap',
                              overflowWrap: 'anywhere',
                            }}
                          >
                            {item.antes || '—'}
                          </pre>

                          <div className="small">
                            Después:
                          </div>

                          <pre
                            className="small border rounded p-2"
                            style={{
                              whiteSpace: 'pre-wrap',
                              overflowWrap: 'anywhere',
                            }}
                          >
                            {item.despues || '—'}
                          </pre>
                        </details>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
