
import { useState } from 'react'

import PaginaPendiente from '../pages/PaginaPendiente.jsx'
import Mantenimiento from '../modules/modulo6/Mantenimiento.jsx'
import IndicadoresKdd from '../modules/modulo8/IndicadoresKdd.jsx'
import Propiedades from '../modules/modulo1/Propiedades.jsx'
import Comprobantes from '../pages/Comprobantes.jsx'
import Conciliacion from '../modules/modulo4/Conciliacion.jsx'
import SeguridadQr from '../modules/accesosQr/SeguridadQr.jsx'
import Incidencias from '../modules/incidencias/Incidencias.jsx'
import Comunicados from '../modules/modulo7/Comunicados.jsx'
import VotacionesAdmin from '../modules/modulo7/VotacionesAdmin.jsx'

import ContratosAdministrativos from '../modules/modulo2/ContratosAdministrativos.jsx'
import ReglasCobro from '../modules/modulo2/ReglasCobro.jsx'

// HU02: agrupa las pantallas financieras
// sin modificar el menú principal de ALKIRA.
function PagosAdministrativos({
  seccion,
  usuario,
  onCambiarSeccion
}) {
  const [pestanaActiva, setPestanaActiva] = useState('contratos')

  return (
    <div>
      <div
        className="d-flex flex-wrap gap-2 mb-4"
        role="group"
        aria-label="Secciones del módulo financiero"
      >
        <button
          type="button"
          className={
            pestanaActiva === 'contratos'
              ? 'btn btn-primary'
              : 'btn btn-outline-primary'
          }
          aria-pressed={pestanaActiva === 'contratos'}
          onClick={() => setPestanaActiva('contratos')}
        >
          Contratos y estados de cuenta
        </button>

        <button
          type="button"
          className={
            pestanaActiva === 'reglas'
              ? 'btn btn-primary'
              : 'btn btn-outline-primary'
          }
          aria-pressed={pestanaActiva === 'reglas'}
          onClick={() => setPestanaActiva('reglas')}
        >
          Cuotas y recargos
        </button>
      </div>

      {pestanaActiva === 'contratos' ? (
        <ContratosAdministrativos
          seccion={seccion}
          usuario={usuario}
          onCambiarSeccion={onCambiarSeccion}
        />
      ) : (
        <ReglasCobro
          seccion={seccion}
          usuario={usuario}
          onCambiarSeccion={onCambiarSeccion}
        />
      )}
    </div>
  )
}

const PANTALLAS = {
  propiedades: Propiedades,
  pagos: PagosAdministrativos,
  mantenimiento: Mantenimiento,
  comprobantes: Comprobantes,
  conciliacion: Conciliacion,
  seguridad: SeguridadQr,
  incidencias: Incidencias,
  reportes: IndicadoresKdd,
  comunicados: Comunicados,
  votaciones: VotacionesAdmin,
}

function VistaContenido({
  seccion,
  usuario,
  onCambiarSeccion
}) {
  const Componente = PANTALLAS[seccion.id] ?? PaginaPendiente

  return (
    <Componente
      seccion={seccion}
      usuario={usuario}
      onCambiarSeccion={onCambiarSeccion}
    />
  )
}

export default VistaContenido
