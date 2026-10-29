import { useEffect, useState } from 'react'
import MenuLateral from './components/MenuLateral.jsx'
import Encabezado from './components/Encabezado.jsx'
import Login from './pages/Login.jsx'
import VistaContenido from './config/VistaContenido.jsx'
import { SECCIONES } from './config/secciones.js'
import { consultarSesion, cerrarSesion } from './services/autenticacion.js'
import './App.css'

function App() {
  const [usuario, setUsuario] = useState(null)
  const [verificando, setVerificando] = useState(true)
  const [seccionActiva, setSeccionActiva] = useState('resumen')
  const [errorConexion, setErrorConexion] = useState('')

  useEffect(() => {
    let activo = true

    async function verificarSesion() {
      try {
        const usuarioActual = await consultarSesion()
        if (activo) setUsuario(usuarioActual)
      } catch (error) {
        if (activo && error.codigo !== 401) {
          setErrorConexion(error.message)
        }
      } finally {
        if (activo) setVerificando(false)
      }
    }

    verificarSesion()
    return () => { activo = false }
  }, [])

  async function salir() {
    try {
      await cerrarSesion()
    } catch (error) {
      setErrorConexion(error.message)
    } finally {
      setUsuario(null)
      setSeccionActiva('resumen')
    }
  }

  if (verificando) {
    return (
      <main className="d-flex justify-content-center align-items-center min-vh-100">
        <p className="text-secondary">Verificando sesión...</p>
      </main>
    )
  }

  if (!usuario) {
    return (
      <Login
        onIniciarSesion={(usuarioActual) => {
          setUsuario(usuarioActual)
          setErrorConexion('')
        }}
        errorConexion={errorConexion}
      />
    )
  }

  const seccionesPermitidas = usuario.tipoUsuario === 'guardia'
    ? SECCIONES.filter(({ id }) => id === 'seguridad') : SECCIONES
  const seccion = seccionesPermitidas.find(({ id }) => id === seccionActiva) ?? seccionesPermitidas[0]

  return (
    <div className="alkira-aplicacion">
      <MenuLateral
        seccionActiva={seccionActiva}
        onCambiarSeccion={setSeccionActiva}
        onSalir={salir}
        usuario={usuario}
      />
      <div className="alkira-zona-principal">
        <Encabezado
          titulo={seccion.titulo}
          descripcion={seccion.descripcion}
          usuario={usuario}
        />
        <main className="alkira-contenido">
          <VistaContenido seccion={seccion} usuario={usuario} />
        </main>
      </div>
    </div>
  )
}

export default App
