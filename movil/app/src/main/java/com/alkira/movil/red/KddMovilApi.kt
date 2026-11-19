
package com.alkira.movil.red

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONException
import org.json.JSONObject
import java.io.IOException
import java.math.BigDecimal
import java.net.HttpURLConnection
import java.net.URL

data class ConsumoKdd(
    val disponible: Boolean,
    val total: BigDecimal,
    val registros: Int
)

data class AsistenciaKdd(
    val disponible: Boolean,
    val cantidad: Int
)

data class ResumenKddMovil(
    val periodo: String,
    val consumoAgua: ConsumoKdd,
    val consumoElectricidad: ConsumoKdd,
    val asistenciaEventos: AsistenciaKdd
)

object KddMovilApi {

    suspend fun consultarResumen(): ResumenKddMovil =
        withContext(Dispatchers.IO) {

            val token = SesionMovil.token
                ?: throw ErrorApi(
                    401,
                    "Tu sesión terminó. Inicia sesión nuevamente."
                )

            val url = URL(
                ConfiguracionApi.URL_BASE +
                        "/api/kdd/movil/resumen"
            )

            val conexion =
                url.openConnection() as HttpURLConnection

            conexion.requestMethod = "GET"
            conexion.connectTimeout = 10_000
            conexion.readTimeout = 20_000

            conexion.setRequestProperty(
                "Accept",
                "application/json"
            )

            conexion.setRequestProperty(
                "Authorization",
                "Bearer $token"
            )

            try {
                val codigo = conexion.responseCode

                val contenido = (
                        if (codigo in 200..299) {
                            conexion.inputStream
                        } else {
                            conexion.errorStream
                        }
                        )?.bufferedReader(Charsets.UTF_8)
                    ?.use { it.readText() }
                    .orEmpty()

                val respuesta = try {
                    JSONObject(contenido)
                } catch (_: JSONException) {
                    throw IOException(
                        "La API KDD no devolvió información válida."
                    )
                }

                if (codigo !in 200..299) {
                    val mensaje = respuesta.optString(
                        "mensaje",
                        "No se pudieron consultar las métricas."
                    )

                    throw ErrorApi(
                        codigo,
                        mensaje
                    )
                }

                val agua = respuesta.getJSONObject(
                    "consumoAgua"
                )

                val electricidad = respuesta.getJSONObject(
                    "consumoElectricidad"
                )

                val eventos = respuesta.getJSONObject(
                    "asistenciaEventos"
                )

                ResumenKddMovil(
                    periodo = respuesta.getString("periodo"),

                    consumoAgua = ConsumoKdd(
                        disponible = agua.getBoolean("disponible"),
                        total = BigDecimal(
                            agua.getString("total")
                        ),
                        registros = agua.getInt("registros")
                    ),

                    consumoElectricidad = ConsumoKdd(
                        disponible = electricidad.getBoolean(
                            "disponible"
                        ),
                        total = BigDecimal(
                            electricidad.getString("total")
                        ),
                        registros = electricidad.getInt("registros")
                    ),

                    asistenciaEventos = AsistenciaKdd(
                        disponible = eventos.getBoolean("disponible"),
                        cantidad = eventos.getInt("cantidad")
                    )
                )

            } catch (error: JSONException) {
                throw IOException(
                    "La respuesta de KDD tiene un formato incorrecto.",
                    error
                )
            } finally {
                conexion.disconnect()
            }
        }
}
