import PrediccionMorosidadKdd from "./PrediccionMorosidadKdd.jsx";
import { useEffect, useState } from "react";
import SeguimientoCarteraKdd from "./SeguimientoCarteraKdd.jsx";

const PAGOS = [
  ["idPago", "Pago"],
  ["unidad", "Unidad"],
  ["periodo", "Periodo"],
  ["fechaPago", "Fecha"],
  ["monto", "Monto"],
  ["metodoPago", "Método"],
  ["estadoPago", "Estado"],
];

const MANTENIMIENTO = [
  ["idIncidencia", "Incidencia"],
  ["idTicket", "Ticket"],
  ["titulo", "Descripción"],
  ["categoria", "Categoría"],
  ["urgencia", "Urgencia"],
  ["fechaReporte", "Reportada"],
  ["estadoTicket", "Estado del ticket"],
  ["horasResolucion", "Horas de resolución"],
];

const ALERTAS = [
  ["unidad", "Unidad"],
  ["cuentasRegistradas", "Cuentas"],
  ["cuentasVencidas", "Vencidas"],
  ["saldoVencido", "Saldo vencido"],
  ["nivelAlerta", "Alerta"],
];

const SEGUIMIENTO = [
  ["unidad", "Unidad"],
  ["prioridad", "Prioridad"],
  ["puntuacion", "Puntos"],
  ["cuentasVencidas", "Cuentas vencidas"],
  ["periodosVencidos", "Periodos vencidos"],
  ["cuentasProximasAVencer", "Próximas a vencer"],
  ["saldoPendiente", "Saldo pendiente"],
  ["saldoVencido", "Saldo vencido"],
  ["razones", "Señales detectadas"],
];

const COLUMNAS_PREDICCION = [
  ["unidad", "Unidad"],
  ["probabilidadAtraso", "Riesgo estimado (%)"],
  ["nivel", "Nivel de riesgo"],
  ["periodosAnalizados", "Periodos analizados"],
  ["periodosConAtraso", "Periodos con atraso"],
  ["periodosPuntuales", "Periodos puntuales"],
  ["calidad", "Calidad de estimación"],
];

function texto(valor) {
  return valor === null || valor === undefined || valor === ""
    ? "—"
    : String(valor);
}

function descargarCsv(nombre, columnas, filas) {
  const celda = (valor) => {
    const original = String(valor ?? "");

    const seguro = /^\s*[=+\-@]/.test(original) ? `'${original}` : original;

    return `"${seguro.replaceAll('"', '""')}"`;
  };

  const lineas = [
    columnas.map(([, titulo]) => celda(titulo)).join(","),
    ...filas.map((fila) =>
      columnas.map(([campo]) => celda(fila[campo])).join(","),
    ),
  ];

  const blob = new Blob(["\uFEFF" + lineas.join("\r\n")], {
    type: "text/csv;charset=utf-8",
  });

  const url = URL.createObjectURL(blob);
  const enlace = document.createElement("a");

  enlace.href = url;
  enlace.download = nombre;

  document.body.appendChild(enlace);
  enlace.click();
  enlace.remove();

  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function escaparHtml(valor) {
  return texto(valor).replace(
    /[&<>"']/g,
    (c) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[c],
  );
}

function imprimirInforme(reporte, periodo, inmueble) {
  const tabla = (titulo, columnas, filas) => `
    <h2>${escaparHtml(titulo)}</h2>

    <table>
      <thead>
        <tr>
          ${columnas
            .map(
              ([, tituloColumna]) => `<th>${escaparHtml(tituloColumna)}</th>`,
            )
            .join("")}
        </tr>
      </thead>

      <tbody>
        ${filas
          .map(
            (fila) => `
          <tr>
            ${columnas
              .map(([campo]) => `<td>${escaparHtml(fila[campo])}</td>`)
              .join("")}
          </tr>
        `,
          )
          .join("")}
      </tbody>
    </table>
  `;

  const hallazgos = reporte.patrones
    .map(
      (patron) => `
    <li>
      <strong>${escaparHtml(patron.titulo)}</strong>:
      ${escaparHtml(patron.detalle)}
    </li>
  `,
    )
    .join("");

  const html = `
    <!doctype html>
    <html lang="es">
      <head>
        <meta charset="utf-8" />
        <title>Informe KDD - ALKIRA</title>

        <style>
          body {
            font-family: Arial, sans-serif;
            color: #17223b;
            padding: 15px;
          }

          h1 {
            font-size: 22px;
          }

          h2 {
            font-size: 16px;
            margin-top: 24px;
          }

          table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
          }

          th, td {
            border: 1px solid #bbb;
            padding: 6px;
            text-align: left;
            overflow-wrap: anywhere;
          }

          th {
            background: #eee;
          }

          li {
            margin: 6px 0;
          }

          @page {
            size: landscape;
            margin: 12mm;
          }
        </style>
      </head>

      <body>
        <h1>ALKIRA — Informe KDD</h1>

        <p>
          Inmueble: ${escaparHtml(inmueble || "Todos")}
          · Periodo: ${escaparHtml(periodo || "Todos")}
        </p>

        <p>
        Reporte KDD, máximo 200 filas por tabla.
        Incluye análisis descriptivo y una estimación
        estadística exploratoria de morosidad.
        </p>

        <h2>Hallazgos</h2>

        <ul>
          ${hallazgos || "<li>Sin hallazgos.</li>"}
        </ul>

        ${
          reporte.seguimientoCartera
            ? tabla(
                "Prioridad de seguimiento",
                SEGUIMIENTO,
                reporte.seguimientoCartera.unidades ?? [],
              )
            : ""
        }

        ${tabla("Pagos", PAGOS, reporte.pagos)}

        ${tabla("Mantenimiento", MANTENIMIENTO, reporte.mantenimiento)}

          ${
            reporte.prediccionMorosidad
              ? tabla(
                  "Predicción temprana de morosidad (modelo estadístico exploratorio)",
                  COLUMNAS_PREDICCION,
                  reporte.prediccionMorosidad.unidades,
                )
              : ""
          }

        ${tabla("Alertas de morosidad", ALERTAS, reporte.alertas)}
      </body>
    </html>
  `;

  const ventana = window.open("", "_blank");

  if (!ventana) {
    window.alert(
      "Permite ventanas emergentes para localhost y vuelve a intentarlo.",
    );
    return;
  }

  ventana.document.open();
  ventana.document.write(html);
  ventana.document.close();

  ventana.focus();
  ventana.print();
}

function TablaKdd({ titulo, columnas, filas, archivo }) {
  return (
    <div className="mt-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 className="h6 fw-bold mb-0">
          {titulo} ({filas.length})
        </h4>

        <button
          type="button"
          className="btn btn-outline-primary btn-sm"
          onClick={() => descargarCsv(archivo, columnas, filas)}
        >
          Descargar CSV
        </button>
      </div>

      {filas.length === 0 ? (
        <p className="text-secondary small">
          No hay registros con estos filtros.
        </p>
      ) : (
        <div className="table-responsive">
          <table className="table table-sm table-striped align-middle">
            <thead>
              <tr>
                {columnas.map(([campo, tituloCol]) => (
                  <th key={campo} scope="col">
                    {tituloCol}
                  </th>
                ))}
              </tr>
            </thead>

            <tbody>
              {filas.map((fila, indice) => (
                <tr key={`${archivo}-${indice}`}>
                  {columnas.map(([campo]) => (
                    <td key={campo}>{texto(fila[campo])}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function Apartado({ titulo, cantidad, children, abierto = false }) {
  return (
    <details className="card border-0 shadow-sm mb-3" open={abierto}>
      <summary className="card-body fw-bold" style={{ cursor: "pointer" }}>
        {titulo} · {cantidad}
      </summary>

      <div className="px-3 pb-3">{children}</div>
    </details>
  );
}

function HistorialesKdd({ idInmueble, periodo }) {
  const [reporte, setReporte] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const controlador = new AbortController();

    async function cargar() {
      setCargando(true);
      setError("");
      setReporte(null);

      const parametros = new URLSearchParams();

      if (idInmueble) {
        parametros.set("idInmueble", idInmueble);
      }

      if (periodo) {
        parametros.set("periodo", periodo);
      }

      const consulta = parametros.toString();

      try {
        const respuesta = await fetch(
          `/api/kdd/reportes${consulta ? `?${consulta}` : ""}`,
          {
            credentials: "same-origin",
            headers: {
              Accept: "application/json",
            },
            signal: controlador.signal,
          },
        );

        if (!respuesta.ok) {
          throw new Error(
            respuesta.status === 401
              ? "Tu sesión venció. Inicia sesión nuevamente."
              : `No fue posible consultar los reportes (HTTP ${respuesta.status}).`,
          );
        }

        const datos = await respuesta.json();

        if (
          !Array.isArray(datos.pagos) ||
          !Array.isArray(datos.mantenimiento) ||
          !Array.isArray(datos.alertas) ||
          !Array.isArray(datos.patrones)
        ) {
          throw new Error("La API devolvió un reporte incompleto.");
        }

        if (!controlador.signal.aborted) {
          setReporte(datos);
        }
      } catch (errorConsulta) {
        if (!controlador.signal.aborted) {
          setError(errorConsulta.message);
        }
      } finally {
        if (!controlador.signal.aborted) {
          setCargando(false);
        }
      }
    }

    cargar();

    return () => controlador.abort();
  }, [idInmueble, periodo]);

  return (
    <section aria-label="Reportes KDD">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h3 className="h5 fw-bold mb-1">Reportes y seguimiento</h3>

          <p className="small text-secondary mb-0">
            Abre solamente la información que necesites.
          </p>
        </div>

        {reporte && !cargando && (
          <button
            className="btn btn-primary btn-sm"
            type="button"
            onClick={() => imprimirInforme(reporte, periodo, idInmueble)}
          >
            Imprimir / Guardar PDF
          </button>
        )}
      </div>

      {cargando && (
        <div className="alert alert-secondary">Cargando reportes...</div>
      )}

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      {reporte && !cargando && (
        <>
          <Apartado
            titulo="Seguimiento de cobranza"
            cantidad={`${
              reporte.seguimientoCartera?.unidades?.length ?? 0
            } unidades`}
            abierto
          >
            <SeguimientoCarteraKdd datos={reporte.seguimientoCartera} />
            <PrediccionMorosidadKdd datos={reporte.prediccionMorosidad} />

            {reporte.seguimientoCartera && (
              <details className="px-3 pb-2">
                <summary
                  className="small fw-semibold"
                  style={{ cursor: "pointer" }}
                >
                  Ver tabla completa y descargar CSV
                </summary>

                <TablaKdd
                  titulo="Prioridades por unidad"
                  columnas={SEGUIMIENTO}
                  filas={reporte.seguimientoCartera.unidades ?? []}
                  archivo="kdd_prioridades_cobranza.csv"
                />
              </details>
            )}
          </Apartado>

          <Apartado titulo="Pagos registrados" cantidad={reporte.pagos.length}>
            <TablaKdd
              titulo="Pagos"
              columnas={PAGOS}
              filas={reporte.pagos}
              archivo="kdd_pagos.csv"
            />
          </Apartado>

          <Apartado
            titulo="Mantenimiento"
            cantidad={reporte.mantenimiento.length}
          >
            <TablaKdd
              titulo="Incidencias y tickets"
              columnas={MANTENIMIENTO}
              filas={reporte.mantenimiento}
              archivo="kdd_mantenimiento.csv"
            />
          </Apartado>

          <Apartado
            titulo="Alertas descriptivas"
            cantidad={reporte.alertas.length}
          >
            <TablaKdd
              titulo="Alertas de morosidad"
              columnas={ALERTAS}
              filas={reporte.alertas}
              archivo="kdd_alertas.csv"
            />
          </Apartado>

          <Apartado
            titulo="Hallazgos del análisis"
            cantidad={reporte.patrones.length}
          >
            {reporte.patrones.length === 0 ? (
              <p className="small text-secondary mb-0">
                Sin hallazgos para estos filtros.
              </p>
            ) : (
              <ul className="mb-0">
                {reporte.patrones.map((patron, indice) => (
                  <li key={`${patron.tipo}-${indice}`} className="mb-2">
                    <strong>{patron.titulo}:</strong> {patron.detalle}
                  </li>
                ))}
              </ul>
            )}
          </Apartado>

          <p className="text-secondary small">
          Máximo {reporte.limitePorLista ?? 200} filas por tabla.
          Las prioridades de cobranza son descriptivas.
          La predicción de morosidad utiliza un modelo estadístico
          basado en el historial de pagos.
          </p>
        </>
      )}
    </section>
  );
}

export default HistorialesKdd;
