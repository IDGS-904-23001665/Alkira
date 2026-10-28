package com.alkira.movil.red

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.math.BigDecimal

data class AdeudoPendiente(
    val idEstadoCuenta: Int,
    val numeroUnidad: String,
    val concepto: String,
    val periodo: String,
    val fechaLimitePago: String,
    val montoPrincipal: BigDecimal,
    val recargoMora: BigDecimal,
    val montoPagado: BigDecimal,
    val montoPendiente: BigDecimal,
    val estado: String
)

data class ResumenAdeudos(
    val adeudos: List<AdeudoPendiente>,
    val totalPendiente: BigDecimal
)

object AdeudosApi {

    suspend fun consultarPendientes(): ResumenAdeudos = withContext(Dispatchers.IO) {
        val token = SesionMovil.token
            ?: throw ErrorApi(401, "La sesión ha finalizado. Inicia sesión nuevamente.")

        val conexion = (URL("${ConfiguracionApi.URL_BASE}/api/adeudos/pendientes")
            .openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 10_000
            readTimeout = 10_000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Authorization", "Bearer $token")
        }

        try {
            val codigo = conexion.responseCode
            val contenido = (if (codigo in 200..299) conexion.inputStream else conexion.errorStream)
                ?.bufferedReader(Charsets.UTF_8)
                ?.use { it.readText() }
                .orEmpty()

            val respuesta = try {
                JSONObject(contenido)
            } catch (_: Exception) {
                throw IOException("El servidor no devolvió datos JSON válidos.")
            }

            if (codigo !in 200..299) {
                val mensaje = respuesta.optString("mensaje")
                    .ifBlank { "No se pudo consultar el estado de cuenta." }
                throw ErrorApi(codigo, mensaje)
            }

            val lista = respuesta.optJSONArray("adeudos")
                ?: throw IOException("La API no devolvió la lista de adeudos.")

            val adeudos = (0 until lista.length()).map { indice ->
                val datos = lista.getJSONObject(indice)
                AdeudoPendiente(
                    idEstadoCuenta = datos.getInt("idEstadoCuenta"),
                    numeroUnidad = datos.getString("numeroUnidad"),
                    concepto = datos.getString("concepto"),
                    periodo = datos.getString("periodo"),
                    fechaLimitePago = datos.getString("fechaLimitePago"),
                    montoPrincipal = datos.leerMonto("montoPrincipal"),
                    recargoMora = datos.leerMonto("recargoMora"),
                    montoPagado = datos.leerMonto("montoPagado"),
                    montoPendiente = datos.leerMonto("montoPendiente"),
                    estado = datos.getString("estado")
                )
            }

            ResumenAdeudos(
                adeudos = adeudos,
                totalPendiente = respuesta.leerMonto("totalPendiente")
            )
        } catch (error: org.json.JSONException) {
            throw IOException("La respuesta del servidor no tiene el formato esperado.", error)
        } finally {
            conexion.disconnect()
        }
    }

    private fun JSONObject.leerMonto(campo: String): BigDecimal {
        return try {
            BigDecimal(getString(campo))
        } catch (error: Exception) {
            throw IOException("El importe '$campo' no es válido.", error)
        }
    }
}
