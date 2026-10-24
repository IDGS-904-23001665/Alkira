import { useEffect, useState } from 'react'

function TarjetaKdd({ titulo, valor }) {
  return (
    <div className="col-12 col-md-6 col-xl-3">
      <div className="card h-100 border-0 shadow-sm">
        <div className="card-body p-4">
          <p className="text-secondary small mb-2">
            {titulo}
          </p>

          <h3 className="h4 fw-bold mb-0">
            {valor}
          </h3>
        </div>
      </div>
    </div>
  )
}

function IndicadoresKdd() {
  const [indicadores, setIndicadores] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const controlador = new AbortController()

    async function consultarIndicadores() {
      try {
        const respuesta = await fetch(
          '/api/kdd/indicadores',
          {
            credentials: 'same-origin',
            signal: controlador.signal,
            headers: {
              Accept: 'application/json'
            }
          }
        )

        if (!respuesta.ok) {
          if (respuesta.status === 401) {
            throw new Error(
              'Tu sesión ha vencido. Inicia sesión nuevamente.'
            )
          }

          throw new Error(
            'No fue posible consultar los indicadores KDD.'
          )
        }

        const datos = await respuesta.json()

        if (!datos.financiero || !datos.operativo) {
          throw new Error(
            'La API no devolvió los indicadores esperados.'
          )
        }

        setIndicadores(datos)

      } catch (errorConsulta) {
        if (!controlador.signal.aborted) {
          setError(errorConsulta.message)
        }
      } finally {
        if (!controlador.signal.aborted) {
          setCargando(false)
        }
      }
    }

    consultarIndicadores()

    return () => controlador.abort()
  }, [])

  function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN'
    }).format(Number(valor ?? 0))
  }

  function formatearNumero(valor) {
    return Number(valor ?? 0).toLocaleString('es-MX')
  }

  if (cargando) {
    return (
      <div className="text-center py-5">
        <div
          className="spinner-border text-primary"
          role="status"
        />
        <p className="mt-3 text-secondary">
          Cargando indicadores KDD...
        </p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="alert alert-danger" role="alert">
        {error}
      </div>
    )
  }

  if (!indicadores) {
    return null
  }

  const { financiero, operativo } = indicadores

  const tarjetasFinancieras = [
    {
      titulo: 'Total facturado',
      valor: formatearMoneda(financiero.totalFacturado)
    },
    {
      titulo: 'Total aplicado',
      valor: formatearMoneda(financiero.totalAplicado)
    },
    {
      titulo: 'Saldo pendiente',
      valor: formatearMoneda(financiero.totalPendiente)
    },
    {
      titulo: 'Saldo vencido',
      valor: formatearMoneda(financiero.totalVencido)
    },
    {
      titulo: 'Morosidad sobre facturación',
      valor: `${Number(financiero.porcentajeMorosidad).toFixed(2)}%`
    },
    {
      titulo: 'Cuentas vencidas',
      valor: formatearNumero(financiero.cuentasVencidas)
    }
  ]

  const tarjetasOperativas = [
    {
      titulo: 'Incidencias registradas',
      valor: formatearNumero(operativo.totalIncidencias)
    },
    {
      titulo: 'Incidencias urgentes',
      valor: formatearNumero(operativo.incidenciasUrgentes)
    },
    {
      titulo: 'Tickets registrados',
      valor: formatearNumero(operativo.totalTickets)
    },
    {
      titulo: 'Tickets abiertos',
      valor: formatearNumero(operativo.ticketsAbiertos)
    },
    {
      titulo: 'Tickets cerrados',
      valor: formatearNumero(operativo.ticketsCerrados)
    },
    {
      titulo: 'Promedio de resolución',
      valor: operativo.promedioHorasResolucion === null
        ? 'Sin datos'
        : `${Number(operativo.promedioHorasResolucion).toFixed(2)} horas`
    }
  ]

  return (
    <div className="container-fluid px-0">

      <div className="mb-4">
        <h2 className="h5 fw-bold">
          Analítica e Historiales KDD
        </h2>
        <p className="text-secondary small">
          Indicadores obtenidos desde la base de datos ALKIRA.
        </p>
      </div>

      <section className="mb-5">
        <h3 className="h6 fw-bold mb-3">
          Indicadores financieros
        </h3>

        <div className="row g-3">
          {tarjetasFinancieras.map((tarjeta) => (
            <TarjetaKdd
              key={tarjeta.titulo}
              titulo={tarjeta.titulo}
              valor={tarjeta.valor}
            />
          ))}
        </div>
      </section>

      <section className="mb-4">
        <h3 className="h6 fw-bold mb-3">
          Indicadores de mantenimiento
        </h3>

        <div className="row g-3">
          {tarjetasOperativas.map((tarjeta) => (
            <TarjetaKdd
              key={tarjeta.titulo}
              titulo={tarjeta.titulo}
              valor={tarjeta.valor}
            />
          ))}
        </div>
      </section>

      <div className="alert alert-info small">
        Los indicadores actuales utilizan datos de prueba.
        Las tendencias históricas, predicciones y reportes
        se integrarán posteriormente.
      </div>

    </div>
  )
}

export default IndicadoresKdd