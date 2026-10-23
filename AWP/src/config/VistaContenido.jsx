import PaginaPendiente from '../pages/PaginaPendiente.jsx'

const PANTALLAS = {}

function VistaContenido({ seccion }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} />
}

export default VistaContenido
