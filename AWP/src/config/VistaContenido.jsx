import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Propiedades from '../modules/modulo1/Propiedades.jsx'
import SeguridadQr from '../modules/accesosQr/SeguridadQr.jsx'
import Incidencias from '../modules/incidencias/Incidencias.jsx'

const PANTALLAS = {
  propiedades: Propiedades,
  seguridad: SeguridadQr,
  incidencias: Incidencias,
}

function VistaContenido({ seccion, usuario }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} usuario={usuario} />
}

export default VistaContenido
