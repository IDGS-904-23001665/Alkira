import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Propiedades from '../modules/modulo1/Propiedades.jsx'
import Comprobantes from '../pages/Comprobantes.jsx'

const PANTALLAS = {
  propiedades: Propiedades,
  comprobantes: Comprobantes,
}

function VistaContenido({ seccion }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} />
}

export default VistaContenido
