import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import IndicadoresKdd from '../modules/modulo8/IndicadoresKdd.jsx'

const PANTALLAS = {
  reportes: IndicadoresKdd
}

function VistaContenido({ seccion }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} />
}

export default VistaContenido
