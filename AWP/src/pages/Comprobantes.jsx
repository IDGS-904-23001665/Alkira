import { useEffect, useState } from 'react'
import './Comprobantes.css'

function Comprobantes() {
  const [comprobantes, setComprobantes] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [filtro, setFiltro] = useState('todos')

  const [comprobanteSeleccionado, setComprobanteSeleccionado] = useState(null)

  const [procesandoRevision, setProcesandoRevision] = useState(false)
  const [mensajeRevision, setMensajeRevision] = useState('')

  // Obtener comprobantes desde PHP y MySQL
  useEffect(() => {
    async function cargarComprobantes() {
      try {
        const respuesta = await fetch('/api/comprobantes', {
          credentials: 'include'
        })

        if (!respuesta.ok) {
          throw new Error('No se pudieron cargar los comprobantes.')
        }

        const datos = await respuesta.json()

        setComprobantes(
          Array.isArray(datos.comprobantes)
            ? datos.comprobantes
            : []
        )

      } catch (err) {
        setError(err.message)
      } finally {
        setCargando(false)
      }
    }

    cargarComprobantes()
  }, [])

  // Conteos históricos de comprobantes (no realizan conciliación).
  const pendientes = comprobantes.filter(
    c => c.estado_revision === 'pendiente'
  ).length

  const aprobados = comprobantes.filter(
    c => c.estado_revision === 'aprobado'
  ).length

  const rechazados = comprobantes.filter(
    c => c.estado_revision === 'rechazado'
  ).length

  // Filtrar comprobantes
  const comprobantesFiltrados = comprobantes.filter(
    comprobante =>
      filtro === 'todos' ||
      comprobante.estado_revision === filtro
  )

  // Formato de moneda mexicana
  function formatoMoneda(monto) {
    return Number(monto).toLocaleString('es-MX', {
      style: 'currency',
      currency: 'MXN'
    })
  }

  // Abrir el detalle del comprobante.
  function abrirDetalle(comprobante) {
    setComprobanteSeleccionado(comprobante)
    setMensajeRevision('')
  }

  function cerrarDetalle() {
    setComprobanteSeleccionado(null)
    setMensajeRevision('')
  }

  // HU03.2: confirmar el preregistro de un pago ya existente.
  async function confirmarPreregistro() {
    if (
      !comprobanteSeleccionado ||
      procesandoRevision ||
      comprobanteSeleccionado.estado_revision !== 'pendiente' ||
      comprobanteSeleccionado.estado_pago !== 'preregistrado'
    ) {
      return
    }

    const idComprobante = Number(comprobanteSeleccionado.id_comprobante)

    if (!window.confirm(
      '¿Confirmas el preregistro de esta transacción y su envío a verificación? Esta acción no aprueba el pago.'
    )) {
      return
    }

    setProcesandoRevision(true)
    setMensajeRevision('')

    try {
      const respuesta = await fetch('/api/comprobantes/preregistrar', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ idComprobante })
      })

      const datos = await respuesta.json()

      if (!respuesta.ok) {
        throw new Error(datos.mensaje || 'No se pudo confirmar el preregistro.')
      }

      // Actualizar el estado en la bandeja sin recargar la página.
      const nuevaRespuesta = await fetch('/api/comprobantes', {
        credentials: 'include'
      })

      if (!nuevaRespuesta.ok) {
        setMensajeRevision(
          'El preregistro se guardó, pero no se pudo actualizar la bandeja. Actualiza la página.'
        )
        return
      }

      const nuevosDatos = await nuevaRespuesta.json()
      const nuevaLista = Array.isArray(nuevosDatos.comprobantes)
        ? nuevosDatos.comprobantes
        : []

      setComprobantes(nuevaLista)
      setComprobanteSeleccionado(
        nuevaLista.find(c => Number(c.id_comprobante) === idComprobante) ?? null
      )
      setMensajeRevision(datos.mensaje || 'Preregistro confirmado y enviado a verificación.')
    } catch (err) {
      setMensajeRevision(err.message)
    } finally {
      setProcesandoRevision(false)
    }
  }

  return (
    <div>
      <h2>Revisión y preregistro de comprobantes de pago</h2>

      <p>
        Comprobantes enviados por los residentes de ALKIRA.
      </p>

      {/* TARJETAS DE ESTADÍSTICAS */}
      <div className="resumen-comprobantes">

        <div className="tarjeta-comprobante">
          <p>Pendientes de revisión</p>
          <h2>{pendientes}</h2>
        </div>

        <div className="tarjeta-comprobante">
          <p>Comprobantes aprobados</p>
          <h2>{aprobados}</h2>
        </div>

        <div className="tarjeta-comprobante">
          <p>Comprobantes rechazados</p>
          <h2>{rechazados}</h2>
        </div>

      </div>

      {/* FILTRO POR ESTADO */}
      <div className="filtros-comprobantes">

        <label htmlFor="filtro-estado">
          Filtrar por estado:
        </label>

        <select
          id="filtro-estado"
          value={filtro}
          onChange={(e) => setFiltro(e.target.value)}
        >
          <option value="todos">Todos</option>
          <option value="pendiente">Pendientes</option>
          <option value="aprobado">Aprobados</option>
          <option value="rechazado">Rechazados</option>
        </select>

      </div>

      {/* MENSAJES */}
      {cargando && (
        <p>Cargando comprobantes...</p>
      )}

      {error && (
        <p role="alert">{error}</p>
      )}

      {mensajeRevision && (
        <p role="status">{mensajeRevision}</p>
      )}

      {/* TABLA DE COMPROBANTES */}
      {!cargando && !error && (
        <table className="comprobantes-tabla">

          <thead>
            <tr>
              <th>Residente</th>
              <th>Monto</th>
              <th>Fecha</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            {comprobantesFiltrados.map((comprobante) => (
              <tr key={comprobante.id_comprobante}>

                <td>
                  {comprobante.nombres}{' '}
                  {comprobante.apellidos}
                </td>

                <td>
                  {formatoMoneda(comprobante.monto)}
                </td>

                <td>
                  {comprobante.fecha_carga}
                </td>

                <td>
                  <span
                    className={
                      `estado-comprobante estado-${comprobante.estado_revision}`
                    }
                  >
                    {comprobante.estado_revision}
                  </span>
                </td>

                <td>
                  <button
                    type="button"
                    className="boton-ver-detalle"
                    onClick={() => abrirDetalle(comprobante)}
                  >
                    Ver detalle
                  </button>
                </td>

              </tr>
            ))}
          </tbody>

        </table>
      )}

      {/* SIN RESULTADOS */}
      {!cargando &&
        !error &&
        comprobantesFiltrados.length === 0 && (
          <p className="mensaje-sin-comprobantes">
            No hay comprobantes con este estado.
          </p>
        )}

      {/* DETALLE DEL COMPROBANTE */}
      {comprobanteSeleccionado && (
        <div className="detalle-comprobante">

          <h3>Detalle del comprobante</h3>

          <p>
            <strong>Residente:</strong>{' '}
            {comprobanteSeleccionado.nombres}{' '}
            {comprobanteSeleccionado.apellidos}
          </p>

          <p>
            <strong>Monto:</strong>{' '}
            {formatoMoneda(comprobanteSeleccionado.monto)}
          </p>

          <p>
            <strong>Fecha:</strong>{' '}
            {comprobanteSeleccionado.fecha_carga}
          </p>

          <p>
            <strong>Método de pago:</strong>{' '}
            {comprobanteSeleccionado.metodo_pago}
          </p>

          <p>
            <strong>Referencia:</strong>{' '}
            {comprobanteSeleccionado.referencia_bancaria || 'Sin referencia'}
          </p>

          <p>
            <strong>Estado:</strong>{' '}
            <span
              className={
                `estado-comprobante estado-${comprobanteSeleccionado.estado_revision}`
              }
            >
              {comprobanteSeleccionado.estado_revision}
            </span>
          </p>

          <p>
            <strong>Estado de la transacción:</strong>{' '}
            {comprobanteSeleccionado.estado_pago || 'No disponible'}
          </p>

          <p>
            <strong>Archivo registrado:</strong>{' '}
            {comprobanteSeleccionado.ruta_archivo || 'Sin archivo'}
          </p>

          {/* VISTA PREVIA PROTEGIDA DEL DOCUMENTO */}
          {comprobanteSeleccionado.ruta_archivo && (
            <div style={{ margin: '24px 0' }}>
              <h4>Vista previa del comprobante</h4>
              {(() => {
                const tipo = String(comprobanteSeleccionado.tipo_archivo || '').toUpperCase()
                const url = `/api/comprobantes/archivo?id=${encodeURIComponent(
                  comprobanteSeleccionado.id_comprobante
                )}`

                if (tipo === 'PDF') {
                  return (
                    <iframe
                      title={`Vista previa del comprobante ${comprobanteSeleccionado.id_comprobante}`}
                      src={url}
                      loading="lazy"
                      style={{
                        display: 'block',
                        width: '100%',
                        height: '480px',
                        border: '1px solid #d6dce6',
                        borderRadius: '8px',
                        backgroundColor: '#fff'
                      }}
                    />
                  )
                }

                if (tipo === 'JPG' || tipo === 'PNG') {
                  return (
                    <img
                      src={url}
                      alt={`Comprobante de pago ${comprobanteSeleccionado.id_comprobante}`}
                      loading="lazy"
                      style={{
                        display: 'block',
                        maxWidth: '100%',
                        maxHeight: '480px',
                        width: 'auto',
                        height: 'auto',
                        objectFit: 'contain',
                        border: '1px solid #d6dce6',
                        borderRadius: '8px',
                        backgroundColor: '#fff'
                      }}
                    />
                  )
                }

                return <p>Formato sin vista previa disponible.</p>
              })()}

              <p style={{ marginTop: '16px' }}>
                <a
                  className="boton-ver-archivo"
                  href={`/api/comprobantes/archivo?id=${encodeURIComponent(
                    comprobanteSeleccionado.id_comprobante
                  )}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Abrir documento en otra pestaña
                </a>
              </p>
            </div>
          )}

          {/* HU03.2: preregistro administrativo, sin aprobar el pago. */}
          {comprobanteSeleccionado.estado_revision === 'pendiente' &&
            comprobanteSeleccionado.estado_pago === 'preregistrado' && (
              <div className="acciones-revision">
                <button
                  type="button"
                  className="boton-ver-detalle"
                  onClick={confirmarPreregistro}
                  disabled={procesandoRevision}
                >
                  {procesandoRevision
                    ? 'Procesando...'
                    : 'Confirmar preregistro y enviar a verificación'}
                </button>
                <p>Esta acción inicia la verificación, no aprueba el pago.</p>
              </div>
            )}

          {/* CERRAR DETALLE */}
          <button
            type="button"
            className="boton-cerrar-detalle"
            onClick={cerrarDetalle}
          >
            Cerrar detalle
          </button>

        </div>
      )}

    </div>
  )
}

export default Comprobantes
