function PaginaPendiente({ seccion }) {
  return (
    <>
      <div className="alkira-titulo-pagina">
        <div>
          <h1>{seccion.titulo}</h1>
          <p>{seccion.descripcion}</p>
        </div>
        <span className="alkira-etiqueta-modulo"></span>
      </div>

      <section className="card alkira-tarjeta-pendiente">
        <div className="card-body p-4">
          <h2 className="h5 mb-2"></h2>
          <p className="mb-2">
            
          </p>
          <small className="text-secondary">
           
          </small>
        </div>
      </section>
    </>
  )
}

export default PaginaPendiente
