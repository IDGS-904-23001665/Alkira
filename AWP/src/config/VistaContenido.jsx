
import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Comprobantes from '../pages/Comprobantes.jsx'

const PANTALLAS = {
  comprobantes: Comprobantes,
}

function VistaContenido({ seccion }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente

  return <Componente seccion={seccion} />
}

export default VistaContenido
