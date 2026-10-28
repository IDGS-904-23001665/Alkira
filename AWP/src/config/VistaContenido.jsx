import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Propiedades from '../modules/modulo1/Propiedades.jsx'
import Incidencias from '../modules/incidencias/Incidencias.jsx'

const PANTALLAS = {
  propiedades: Propiedades,
  incidencias: Incidencias,
}

function VistaContenido({ seccion }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} />
}

export default VistaContenido
