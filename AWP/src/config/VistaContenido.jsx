import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Mantenimiento from '../modules/modulo6/Mantenimiento.jsx'
import IndicadoresKdd from '../modules/modulo8/IndicadoresKdd.jsx'

import Propiedades from '../modules/modulo1/Propiedades.jsx'
import Comprobantes from '../pages/Comprobantes.jsx'
import SeguridadQr from '../modules/accesosQr/SeguridadQr.jsx'
import Incidencias from '../modules/incidencias/Incidencias.jsx'
import Comunicados from '../modules/modulo7/Comunicados.jsx'
import VotacionesAdmin from '../modules/modulo7/VotacionesAdmin.jsx'

const PANTALLAS = {
  propiedades: Propiedades,
  mantenimiento: Mantenimiento,
  comprobantes: Comprobantes,
  seguridad: SeguridadQr,
  incidencias: Incidencias,
  reportes: IndicadoresKdd,
  comunicados: Comunicados,
  votaciones: VotacionesAdmin,
}

function VistaContenido({ seccion, usuario }) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente
  return <Componente seccion={seccion} usuario={usuario} />
}

export default VistaContenido
