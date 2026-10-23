function PaginaPendiente({ seccion }) {
  return (
    <>
      <div className="alkira-titulo-pagina">
        <div>
          <h1>{seccion.titulo}</h1>
          <p>{seccion.descripcion}</p>
        </div>
        <span className="alkira-etiqueta-modulo">Estructura inicial</span>
      </div>

      <section className="card alkira-tarjeta-pendiente">
        <div className="card-body p-4">
          <h2 className="h5 mb-2">Pantalla pendiente de integrar</h2>
          <p className="mb-2">
            El menú y el encabezado compartidos ya están disponibles.
            El contenido de esta sección se añadirá en su módulo correspondiente.
          </p>
          <small className="text-secondary">
            Esta es una vista de diseño. No muestra información de usuarios ni datos de MySQL.
          </small>
        </div>
      </section>
    </>
  )
}

export default PaginaPendiente
