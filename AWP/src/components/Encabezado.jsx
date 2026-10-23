function Encabezado({ titulo, descripcion, usuario }) {
  return (
    <header className="alkira-barra-superior">
      <div className="alkira-barra-titulos">
        <strong>{titulo}</strong>
        <small>Administración / {descripcion}</small>
      </div>
      <div className="alkira-barra-usuario">
        <span className="alkira-etiqueta-vista">Sesión activa</span>
        <div className="alkira-usuario-datos">
          <strong>{usuario.nombres} {usuario.apellidos}</strong>
          <small>{usuario.correo}</small>
        </div>
      </div>
    </header>
  )
}

export default Encabezado
