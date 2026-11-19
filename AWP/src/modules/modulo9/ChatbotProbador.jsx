import { useState } from 'react'

const EJEMPLOS = [
  '¿Cómo puedo consultar mi saldo?',
  '¿Dónde puedo ver los comunicados?',
  '¿Cuánto debo?',
  '¿Cuál es el estado de mi reporte?',
]

export default function ChatbotProbador() {
  const [mensaje, setMensaje] = useState(
    '¿Cómo puedo consultar mi saldo?'
  )

  const [conversacion, setConversacion] = useState([])
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState('')

  async function enviar(evento) {
    evento.preventDefault()

    const pregunta = mensaje.trim()

    if (!pregunta || enviando) return

    setError('')
    setEnviando(true)

    try {
      const respuesta = await fetch(
        '/api/chatbot-admin/probar',
        {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Alkira-Admin': '1',
          },
          body: JSON.stringify({
            mensaje: pregunta,
          }),
        }
      )

      const datos = await respuesta.json()
        .catch(() => ({}))

      if (!respuesta.ok) {
        throw new Error(
          datos.mensaje ||
          `Error HTTP ${respuesta.status}`
        )
      }

      setConversacion((anterior) => [
        ...anterior,
        {
          pregunta,
          respuesta: datos.respuesta,
          tipo: datos.tipo,
        },
      ])

      setMensaje('')
    } catch (fallo) {
      setError(fallo.message)
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-body">
        <h3 className="h5 fw-bold">
          Prueba del asistente
        </h3>

        <p className="text-secondary small">
          Comprueba cómo responde el motor usando las
          FAQs activas. Esta vista no consulta datos
          personales ni guarda conversaciones de residentes.
          Los saldos e incidencias reales requieren
          una sesión móvil autenticada.
        </p>

        <div className="d-flex flex-wrap gap-2 mb-3">
          {EJEMPLOS.map((ejemplo) => (
            <button
              key={ejemplo}
              type="button"
              className="btn btn-outline-secondary btn-sm"
              onClick={() => setMensaje(ejemplo)}
            >
              {ejemplo}
            </button>
          ))}
        </div>

        <div
          className="border rounded p-3 mb-3 bg-light"
          style={{ minHeight: 180 }}
        >
          {conversacion.length === 0 && (
            <p className="text-secondary small mb-0">
              Envía una pregunta para comprobar
              la respuesta de ALKIRA.
            </p>
          )}

          {conversacion.map((turno, indice) => (
            <div key={indice} className="mb-3">
              <div className="text-end mb-2">
                <div
                  className="d-inline-block text-start bg-primary text-white rounded p-3"
                  style={{ maxWidth: '90%' }}
                >
                  <strong>
                    Administrador (prueba)
                  </strong>

                  <div
                    style={{
                      whiteSpace: 'pre-wrap',
                      overflowWrap: 'anywhere',
                    }}
                  >
                    {turno.pregunta}
                  </div>
                </div>
              </div>

              <div
                className="d-inline-block bg-white border rounded p-3"
                style={{ maxWidth: '90%' }}
              >
                <strong>Asistente ALKIRA</strong>

                <span className="badge text-bg-secondary ms-2">
                  {turno.tipo}
                </span>

                <div
                  className="mt-1"
                  style={{
                    whiteSpace: 'pre-wrap',
                    overflowWrap: 'anywhere',
                  }}
                >
                  {turno.respuesta}
                </div>
              </div>
            </div>
          ))}
        </div>

        {error && (
          <div className="alert alert-danger" role="alert">
            {error}
          </div>
        )}

        <form
          className="d-flex gap-2"
          onSubmit={enviar}
        >
          <input
            className="form-control"
            aria-label="Pregunta de prueba"
            maxLength={500}
            placeholder="Escribe tu pregunta..."
            value={mensaje}
            onChange={(evento) => {
              setMensaje(evento.target.value)
            }}
          />

          <button
            className="btn btn-primary"
            type="submit"
            disabled={!mensaje.trim() || enviando}
          >
            {enviando ? 'Enviando...' : 'Enviar'}
          </button>
        </form>

        {conversacion.length > 0 && (
          <button
            className="btn btn-link btn-sm mt-2 px-0"
            type="button"
            onClick={() => setConversacion([])}
          >
            Limpiar prueba
          </button>
        )}
      </div>
    </div>
  )
}
