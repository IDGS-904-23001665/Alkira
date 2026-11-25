
import { useCallback, useEffect, useState } from 'react'
import { obtenerResumenGeneral } from '../../services/resumenGeneral'
import '../modulo1/Propiedades.css'
import './ResumenGeneral.css'

const indicadores = [
  [
    'Propiedades activas',
    'propiedades',
    'Inmuebles registrados',
  ],
  [
    'Unidades ocupadas',
    'ocupadas',
    'Unidades asignadas',
  ],
  [
    'Unidades disponibles',
    'disponibles',
    'Disponibles para asignación',
  ],
  [
    'Residentes activos',
    'residentes',
    'Inquilinos y propietarios',
  ],
]

const pendientes = [
  [
    'Comprobantes por validar',
    'comprobantes',
    'conciliacion',
  ],
  [
    'Incidencias sin atender',
    'incidencias',
    'incidencias',
  ],
  [
    'Mantenimientos pendientes',
    'mantenimiento',
    'mantenimiento',
  ],
  [
    'Contratos próximos a vencer',
    'contratos',
    'pagos',
  ],
]

const accesos = [
  ['Administrar propiedades', 'propiedades'],
  ['Administrar residentes', 'residentes'],
  ['Revisar comprobantes', 'conciliacion'],
  ['Consultar incidencias', 'incidencias'],
  ['Gestionar mantenimiento', 'mantenimiento'],
  ['Crear comunicados', 'comunicados'],
]

function Panel({ titulo, subtitulo, children }) {
  return (
    <section className="hu01-panel">
      <div className="hu01-panel-cabecera">
        <div>
          <h2>{titulo}</h2>
          <p>{subtitulo}</p>
        </div>
      </div>

      {children}
    </section>
  )
}

function formatearFecha(valor) {
  if (!valor) return 'Sin fecha'

  const fecha = new Date(String(valor).replace(' ', 'T'))

  if (Number.isNaN(fecha.getTime())) {
    return 'Sin fecha'
  }

  return fecha.toLocaleDateString('es-MX', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function ResumenGeneral({ usuario, onCambiarSeccion }) {
  const [datos, setDatos] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  const cargarResumen = useCallback(async (signal) => {
    setCargando(true)

    try {
      const respuesta = await obtenerResumenGeneral(signal)

      if (!signal?.aborted) {
        setDatos(respuesta)
        setError('')
      }
    } catch (error) {
      if (error.name !== 'AbortError' && !signal?.aborted) {
        setError(error.message)
      }
    } finally {
      if (!signal?.aborted) {
        setCargando(false)
      }
    }
  }, [])

  useEffect(() => {
    const controlador = new AbortController()

    cargarResumen(controlador.signal)

    const intervalo = setInterval(() => {
      cargarResumen(controlador.signal)
    }, 60000)

    return () => {
      controlador.abort()
      clearInterval(intervalo)
    }
  }, [cargarResumen])

  function navegar(seccion) {
    onCambiarSeccion?.(seccion)
  }

  return (
    <div className="hu01 resumen-general">

      <div className="hu01-cabecera-pagina">
        <div>
          <h1>Resumen general</h1>
          <p>
            Consulta el estado actual de las propiedades,
            residentes y actividades administrativas.
          </p>
        </div>

        <button
          type="button"
          className="hu01-boton-secundario"
          onClick={() => cargarResumen()}
          disabled={cargando}
        >
          {cargando ? 'Actualizando...' : 'Actualizar'}
        </button>
      </div>

      {error && (
        <div
          className="hu01-alerta hu01-alerta-error"
          role="alert"
        >
          <span>{error}</span>

          <button
            type="button"
            onClick={() => setError('')}
          >
            Cerrar
          </button>
        </div>
      )}

      {cargando && !datos && (
        <p className="hu01-cargando">
          Cargando información...
        </p>
      )}

      <div className="hu01-indicadores">
        {indicadores.map(([titulo, clave, descripcion]) => (
          <div className="hu01-indicador" key={clave}>
            <span>{titulo}</span>

            <strong>
              {datos?.indicadores?.[clave] ?? '—'}
            </strong>

            <small>{descripcion}</small>
          </div>
        ))}
      </div>

      <div className="resumen-grid">

        <Panel
          titulo="Pendientes de atención"
          subtitulo="Actividades que requieren seguimiento."
        >
          <div className="hu01-tabla-contenedor">
            <table>
              <thead>
                <tr>
                  <th>Actividad</th>
                  <th>Cantidad</th>
                  <th>Acción</th>
                </tr>
              </thead>

              <tbody>
                {pendientes.map(([titulo, clave, seccion]) => (
                  <tr key={clave}>
                    <td>
                      <strong>{titulo}</strong>
                    </td>

                    <td>
                      <span
                        className={`hu01-estado ${
                          datos?.pendientes?.[clave] == null
                            ? 'inactivo'
                            : datos.pendientes[clave] > 0
                              ? 'mantenimiento'
                              : 'activo'
                        }`}
                      >
                        {datos?.pendientes?.[clave] ?? '—'}
                      </span>
                    </td>

                    <td>
                      <button
                        type="button"
                        className="hu01-enlace"
                        onClick={() => navegar(seccion)}
                      >
                        Consultar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Panel>

        <Panel
          titulo="Accesos rápidos"
          subtitulo="Funciones principales de administración."
        >
          <div className="hu01-accesos">
            {accesos.map(([titulo, seccion]) => (
              <button
                type="button"
                key={seccion}
                onClick={() => navegar(seccion)}
              >
                {titulo}
                <span>Ir</span>
              </button>
            ))}
          </div>
        </Panel>

        <Panel
          titulo="Agenda administrativa"
          subtitulo="Próximos eventos y vencimientos."
        >
          {!datos && (
            <p className="hu01-vacio">
              {cargando
                ? 'Cargando eventos...'
                : 'Información no disponible.'}
            </p>
          )}

          {datos?.agenda?.length === 0 && (
            <p className="hu01-vacio">
              No hay eventos próximos programados.
            </p>
          )}

          {datos?.agenda?.length > 0 && (
            <div className="hu01-tabla-contenedor">
              <table>
                <thead>
                  <tr>
                    <th>Evento</th>
                    <th>Fecha</th>
                    <th>Acción</th>
                  </tr>
                </thead>

                <tbody>
                  {datos.agenda.map((evento, indice) => (
                    <tr key={`${evento.seccion}-${indice}`}>
                      <td>{evento.titulo}</td>

                      <td>
                        {formatearFecha(evento.fecha)}
                      </td>

                      <td>
                        <button
                          type="button"
                          className="hu01-enlace"
                          onClick={() => navegar(evento.seccion)}
                        >
                          Ver
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>

        <Panel
          titulo="Actividad reciente"
          subtitulo="Últimos movimientos registrados en el sistema."
        >
          {!datos && (
            <p className="hu01-vacio">
              {cargando
                ? 'Cargando actividad...'
                : 'Información no disponible.'}
            </p>
          )}

          {datos?.actividad?.length === 0 && (
            <p className="hu01-vacio">
              No hay movimientos registrados.
            </p>
          )}

          {datos?.actividad?.length > 0 && (
            <div className="hu01-tabla-contenedor">
              <table>
                <thead>
                  <tr>
                    <th>Actividad</th>
                    <th>Fecha</th>
                  </tr>
                </thead>

                <tbody>
                  {datos.actividad.map((actividad, indice) => (
                    <tr key={`${actividad.seccion}-${indice}`}>
                      <td>{actividad.titulo}</td>

                      <td>
                        {formatearFecha(actividad.fecha)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>

      </div>

      {datos?.actualizado && (
        <div className="resumen-actualizacion">
          Última actualización: {datos.actualizado}
        </div>
      )}

    </div>
  )
}

export default ResumenGeneral
