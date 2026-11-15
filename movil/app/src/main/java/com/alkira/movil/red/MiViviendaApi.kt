package com.alkira.movil.red

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

data class ViviendaHu01(
    val idUnidad: Int,
    val condominio: String,
    val inmueble: String,
    val tipoInmueble: String,
    val numeroUnidad: String,
    val tipoUnidad: String,
    val direccion: String,
    val ciudad: String,
    val superficie: String,
    val estacionamientos: Int,
    val tieneBodega: Boolean,
    val estadoUnidad: String,
    val descripcion: String,
    val serviciosIncluidos: String,
    val fechaInicio: String,
    val fechaFin: String
)

data class NotificacionHu01(
    val id: Int,
    val titulo: String,
    val mensaje: String,
    val fecha: String,
    val estado: String
)

data class RespuestaViviendaHu01(
    val viviendas: List<ViviendaHu01>,
    val notificaciones: List<NotificacionHu01>
)

object MiViviendaApi {

    suspend fun consultar(): RespuestaViviendaHu01 =
        withContext(Dispatchers.IO) {

            val token = SesionMovil.token
                ?: throw ErrorApi(
                    401,
                    "Debes iniciar sesión nuevamente."
                )

            val url = URL(
                "${ConfiguracionApi.URL_BASE}/api/movil/mi-vivienda"
            )

            val conexion = (
                url.openConnection() as HttpURLConnection
            ).apply {
                requestMethod = "GET"
                connectTimeout = 10000
                readTimeout = 10000
                setRequestProperty(
                    "Accept",
                    "application/json"
                )
                setRequestProperty(
                    "Authorization",
                    "Bearer $token"
                )
            }

            try {
                val codigo = conexion.responseCode

                val contenido = (
                    if (codigo in 200..299)
                        conexion.inputStream
                    else
                        conexion.errorStream
                )?.bufferedReader(Charsets.UTF_8)
                    ?.use { it.readText() }
                    .orEmpty()

                val respuesta = try {
                    JSONObject(contenido)
                } catch (e: Exception) {
                    throw IOException(
                        "El servidor no devolvió JSON válido.",
                        e
                    )
                }

                if (codigo !in 200..299) {
                    throw ErrorApi(
                        codigo,
                        respuesta.optString("mensaje")
                            .ifBlank {
                                "No fue posible consultar tu vivienda."
                            }
                    )
                }

                val viviendasJson =
                    respuesta.getJSONArray("viviendas")

                val viviendas = (
                    0 until viviendasJson.length()
                ).map { indice ->

                    val item = viviendasJson.getJSONObject(indice)

                    ViviendaHu01(
                        idUnidad = item.getInt("idUnidad"),
                        condominio = item.optString("condominio"),
                        inmueble = item.optString("inmueble"),
                        tipoInmueble = item.optString("tipoInmueble"),
                        numeroUnidad = item.optString("numeroUnidad"),
                        tipoUnidad = item.optString("tipoUnidad"),
                        direccion = item.optString("direccion"),
                        ciudad = item.optString("ciudad"),
                        superficie = item.optString("superficie"),
                        estacionamientos =
                            item.optInt("estacionamientos"),
                        tieneBodega =
                            item.optInt("tieneBodega") == 1,
                        estadoUnidad =
                            item.optString("estadoUnidad"),
                        descripcion =
                            item.optString("descripcion"),
                        serviciosIncluidos =
                            item.optString("serviciosIncluidos"),
                        fechaInicio =
                            item.optString("fechaInicio"),
                        fechaFin =
                            item.optString("fechaFin")
                    )
                }

                val notificacionesJson =
                    respuesta.getJSONArray("notificaciones")

                val notificaciones = (
                    0 until notificacionesJson.length()
                ).map { indice ->

                    val item = notificacionesJson.getJSONObject(indice)

                    NotificacionHu01(
                        id = item.getInt("id"),
                        titulo = item.optString("titulo"),
                        mensaje = item.optString("mensaje"),
                        fecha = item.optString("fecha"),
                        estado = item.optString("estado")
                    )
                }

                RespuestaViviendaHu01(
                    viviendas = viviendas,
                    notificaciones = notificaciones
                )

            } finally {
                conexion.disconnect()
            }
        }
}
