
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
  const [sinConexion, setSinConexion] = useState(!navigator.onLine)

  useEffect(() => {
    function actualizarConexion() {
      setSinConexion(!navigator.onLine)
    }

    window.addEventListener('online', actualizarConexion)
    window.addEventListener('offline', actualizarConexion)

    return () => {
      window.removeEventListener('online', actualizarConexion)
      window.removeEventListener('offline', actualizarConexion)
    }
  }, [])

  useEffect(() => {
    if (sinConexion) {
      setVerificando(false)
      return
    }

    let activo = true
    setVerificando(true)
    setErrorConexion('')

    async function verificarSesion() {
      try {
        const usuarioActual = await consultarSesion()

        if (activo) {
          setUsuario(usuarioActual)
        }
      } catch (error) {
        if (activo) {
          setUsuario(null)
          if (error.codigo !== 401) {
            setErrorConexion(error.message)
          }
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
  }, [sinConexion])

  // Cambiar entre las secciones autorizadas del sistema.
  function cambiarSeccion(nuevaSeccion) {
    if (
      usuario?.tipoUsuario === 'guardia' &&
      nuevaSeccion !== 'seguridad'
    ) {
      return
    }

    if (SECCIONES.some(({ id }) => id === nuevaSeccion)) {
      setSeccionActiva(nuevaSeccion)
    }
  }

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

  if (sinConexion) {
    return (
      <main className="alkira-login-pagina">
        <section className="alkira-login-tarjeta bg-white p-4 rounded shadow-sm text-center">
          <img
            src="/logo-alkira.jpg"
            alt="Logotipo de ALKIRA"
            className="alkira-logo-login mb-3"
          />
          <h1 className="h4">ALKIRA está sin conexión</h1>
          <p className="text-secondary">
            La aplicación se puede abrir sin Internet, pero el inicio de sesión,
            los estados de cuenta y las órdenes necesitan conexión con el servidor.
          </p>
          <p className="small text-secondary mb-0">
            Al recuperar Internet, ALKIRA comprobará nuevamente tu sesión.
          </p>
        </section>
      </main>
    )
  }

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

  // Solo los administradores y guardias pueden usar este panel web.
  if (
    usuario.tipoUsuario !== 'administrador' &&
    usuario.tipoUsuario !== 'guardia'
  ) {
    return (
      <main className="d-flex flex-column justify-content-center align-items-center min-vh-100">
        <h2>Acceso restringido</h2>
        <p>
          Este panel está disponible únicamente para personal autorizado.
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

  // Los guardias únicamente pueden abrir Seguridad QR.
  const seccionesPermitidas = usuario.tipoUsuario === 'guardia'
    ? SECCIONES.filter(({ id }) => id === 'seguridad')
    : SECCIONES

  const seccion = seccionesPermitidas.find(
    ({ id }) => id === seccionActiva
  ) ?? seccionesPermitidas[0]

  return (
    <div className="alkira-aplicacion">
      <MenuLateral
        seccionActiva={seccion.id}
        onCambiarSeccion={cambiarSeccion}
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
          <VistaContenido
            seccion={seccion}
            usuario={usuario}
            onCambiarSeccion={cambiarSeccion}
          />
        </main>
      </div>
    </div>
  )
}

export default App
