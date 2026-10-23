import { useRef, useState } from 'react'
import { iniciarSesion } from '../services/autenticacion.js'

function Login({ onIniciarSesion, errorConexion = '' }) {
  const [correo, setCorreo] = useState('')
  const [contrasena, setContrasena] = useState('')
  const [mostrarContrasena, setMostrarContrasena] = useState(false)
  const [mensaje, setMensaje] = useState('')
  const [cargando, setCargando] = useState(false)
  const correoReferencia = useRef(null)
  const contrasenaReferencia = useRef(null)

  const ingresar = async (evento) => {
    evento.preventDefault()
    setMensaje('')

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim())) {
      setMensaje('Ingresa un correo electrónico válido.')
      correoReferencia.current?.focus()
      return
    }

    if (!contrasena.trim()) {
      setMensaje('Ingresa tu contraseña.')
      contrasenaReferencia.current?.focus()
      return
    }

    setCargando(true)
    try {
      const usuario = await iniciarSesion(correo.trim(), contrasena)
      onIniciarSesion(usuario)
    } catch (error) {
      setMensaje(error.message)
    } finally {
      setCargando(false)
    }
  }

  return (
    <main className="alkira-login-pagina">
      <div className="alkira-login-contenedor container-fluid p-0">
        <div className="row g-0 h-100">
          <section className="col-lg-6 alkira-login-presentacion">
            <div className="alkira-login-marca">ALKIRA <span>Property OS</span></div>
            <div>
              <p className="alkira-login-categoria">ADMINISTRACIÓN INMOBILIARIA</p>
              <h1>Todo tu espacio, mejor administrado.</h1>
              <p className="alkira-login-descripcion">
                Gestión de propiedades, estados de cuenta y mantenimiento desde un mismo lugar.
              </p>
            </div>
            <small>Plataforma administrativa ALKIRA</small>
          </section>

          <section className="col-lg-6 alkira-login-formulario-seccion">
            <div className="alkira-login-tarjeta">
              <div className="alkira-login-espacio-logo" aria-label="Espacio reservado para el logotipo">
                <div className="alkira-login-logo-marcador">Logo ALKIRA</div>
                <small>Coloca tu imagen en este espacio</small>
              </div>

              <h2>Iniciar sesión</h2>
              <p className="alkira-login-instruccion">Accede al panel de administración.</p>

              <form onSubmit={ingresar} noValidate>
                <div className="mb-3">
                  <label className="form-label" htmlFor="correo">Correo electrónico</label>
                  <input
                    id="correo"
                    ref={correoReferencia}
                    type="email"
                    className="form-control alkira-input"
                    placeholder="nombre@correo.com"
                    autoComplete="username"
                    value={correo}
                    onChange={(evento) => setCorreo(evento.target.value)}
                    required
                  />
                </div>

                <div className="mb-3">
                  <label className="form-label" htmlFor="contrasena">Contraseña</label>
                  <div className="input-group">
                    <input
                      id="contrasena"
                      ref={contrasenaReferencia}
                      type={mostrarContrasena ? 'text' : 'password'}
                      className="form-control alkira-input"
                      placeholder="Ingresa tu contraseña"
                      autoComplete="current-password"
                      value={contrasena}
                      onChange={(evento) => setContrasena(evento.target.value)}
                      required
                    />
                    <button
                      className="btn btn-outline-secondary"
                      type="button"
                      aria-label={mostrarContrasena ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                      onClick={() => setMostrarContrasena(!mostrarContrasena)}
                    >
                      {mostrarContrasena ? 'Ocultar' : 'Mostrar'}
                    </button>
                  </div>
                </div>

                {(mensaje || errorConexion) && (
                  <div role="status" className="alert alert-info py-2 alkira-login-mensaje">
                    {mensaje || errorConexion}
                  </div>
                )}

                <button type="submit" disabled={cargando} className="btn btn-primary w-100 alkira-login-ingresar">
                  {cargando ? 'Ingresando...' : 'Ingresar'}
                </button>
              </form>

            </div>
          </section>
        </div>
      </div>
    </main>
  )
}

export default Login
