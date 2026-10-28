import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Propiedades from '../modules/modulo1/Propiedades.jsx'
import SeguridadQr from '../modules/accesosQr/SeguridadQr.jsx'

const PANTALLAS = {
  propiedades: Propiedades,
  seguridad: SeguridadQr,
}

function VistaContenido({ seccion }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} />
}

export default VistaContenido
