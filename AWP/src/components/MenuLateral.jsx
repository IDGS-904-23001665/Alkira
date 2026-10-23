import { useState } from 'react'
import { SECCIONES } from '../config/secciones.js'

const TRAZOS = {
  cuadricula: <><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></>,
  edificio: <><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1M10 21v-4h4v4"/></>,
  usuarios: <><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6m1 3a5 5 0 0 1 3 5v1"/></>,
  tarjeta: <><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-14 5h4"/></>,
  herramienta: <><path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18a2 2 0 0 0 3 3l5.3-5.3a5 5 0 0 0 6.4-6.4l-3.4 3.4-3-3z"/></>,
  documento: <><path d="M5 3h10l4 4v14H5zM15 3v5h4M8 12h8M8 16h8"/></>,
  validar: <path d="m4 12 5 5L20 6"/>,
  grafica: <><path d="M4 20h16M6 16V9m6 7V4m6 12v-5"/></>,
  escudo: <><path d="m12 2 8 4v6c0 5-3 8-8 10-5-2-8-5-8-10V6z"/><path d="m9 12 2 2 4-4"/></>,
  ajustes: <><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9l2.2 2.2m9.8 9.8 2.2 2.2m0-14.2-2.2 2.2m-9.8 9.8-2.2 2.2"/></>,
  menu: <path d="M4 6h16M4 12h16M4 18h16"/>,
  cerrar: <path d="M5 5l14 14M19 5 5 19"/>,
  salir: <><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></>,
}

function Icono({ nombre, tamano = 17 }) {
  return (
    <svg width={tamano} height={tamano} viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"
      strokeLinejoin="round" aria-hidden="true">
      {TRAZOS[nombre]}
    </svg>
  )
}

function MenuLateral({ seccionActiva, onCambiarSeccion, onSalir, usuario }) {
  const [abierto, setAbierto] = useState(false)

  const seleccionar = (id) => {
    onCambiarSeccion(id)
    setAbierto(false)
  }

  return (
    <>
      <button
        type="button"
        className="alkira-boton-menu-movil"
        aria-label={abierto ? 'Cerrar menú' : 'Abrir menú'}
        aria-expanded={abierto}
        onClick={() => setAbierto(!abierto)}
      >
        <Icono nombre={abierto ? 'cerrar' : 'menu'} tamano={22} />
      </button>

      {abierto && (
        <button type="button" className="alkira-fondo-menu"
          aria-label="Cerrar menú" onClick={() => setAbierto(false)} />
      )}

      <aside className={`alkira-menu-lateral ${abierto ? 'alkira-menu-abierto' : ''}`}>
        <div className="alkira-marca">
          <span className="alkira-marca-icono"><Icono nombre="edificio" tamano={19} /></span>
          <span><strong>ALKIRA</strong><small>Property OS</small></span>
        </div>

        <div className="alkira-menu-seccion">ESPACIO DE TRABAJO</div>
        <nav className="alkira-menu-opciones" aria-label="Navegación principal">
          {SECCIONES.map(({ id, texto, icono }) => (
            <button
              key={id}
              type="button"
              className={`alkira-opcion ${seccionActiva === id ? 'alkira-opcion-activa' : ''}`}
              aria-current={seccionActiva === id ? 'page' : undefined}
              onClick={() => seleccionar(id)}
            >
              <Icono nombre={icono} /> <span>{texto}</span>
            </button>
          ))}
        </nav>

        <div className="alkira-menu-pie">
          <button type="button" className="alkira-opcion" disabled
            title="Configuración pendiente de integrar">
            <Icono nombre="ajustes" /> <span>Configuración</span>
          </button>
          <button type="button" className="alkira-opcion"
            onClick={onSalir}>
            <Icono nombre="salir" /> <span>Cerrar sesión</span>
          </button>
          <div className="alkira-perfil">
            <span className="alkira-avatar">{usuario.nombres.charAt(0).toUpperCase()}</span>
            <span><strong>{usuario.nombres} {usuario.apellidos}</strong><small>{usuario.tipoUsuario}</small></span>
          </div>
        </div>
      </aside>
    </>
  )
}

export default MenuLateral
