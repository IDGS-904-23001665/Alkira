
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

  // Verificar la sesión administrativa al iniciar ALKIRA.
  useEffect(() => {
    let activo = true

    async function verificarSesion() {
      try {
        const usuarioActual = await consultarSesion()

        if (activo) {
          setUsuario(usuarioActual)
        }
      } catch (error) {
        if (activo && error.codigo !== 401) {
          setErrorConexion(error.message)
        }
      } finally {
        if (activo) {
          setVerificando(false)
        }
      }
    }

    verificarSesion()

    return () => {
      activo = false
    }
  }, [])

  // Cerrar sesión del administrador.
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

  // Esperar la comprobación de la sesión administrativa.
  if (verificando) {
    return (
      <main className="d-flex justify-content-center align-items-center min-vh-100">
        <p className="text-secondary">
          Verificando sesión...
        </p>
      </main>
    )
  }

  // Mostrar el inicio de sesión administrativo.
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

  // Proteger el panel administrativo por rol.
  if (usuario.tipoUsuario !== 'administrador') {
    return (
      <main className="d-flex flex-column justify-content-center align-items-center min-vh-100">
        <h2>Acceso restringido</h2>

        <p>
          Este panel está disponible únicamente
          para administradores.
        </p>

        <button
          type="button"
          className="btn btn-primary"
          onClick={salir}
        >
          Cerrar sesión
        </button>
      </main>
    )
  }

  // Buscar la sección activa del panel administrativo.
  const seccion =
    SECCIONES.find(({ id }) => id === seccionActiva) ??
    SECCIONES[0]

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
          <VistaContenido seccion={seccion} />
        </main>
      </div>
    </div>
  )
}

export default App
