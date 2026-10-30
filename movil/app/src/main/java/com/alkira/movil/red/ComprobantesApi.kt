package com.alkira.movil.red

import android.content.ContentResolver
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID

data class AdeudoComprobante(
    val idEstadoCuenta: Int,
    val concepto: String,
    val periodo: String,
    val fechaLimite: String,
    val saldo: String
)

object ComprobantesApi {
    suspend fun adeudos(): List<AdeudoComprobante> = withContext(Dispatchers.IO) {
        val conexion = abrirConexion("/api/comprobantes/adeudos-movil", "GET")
        try {
            val json = respuesta(conexion)
            val lista = json.optJSONArray("adeudos") ?: return@withContext emptyList()
            (0 until lista.length()).mapNotNull { indice ->
                val item = lista.optJSONObject(indice) ?: return@mapNotNull null
                val id = item.optInt("idEstadoCuenta")
                if (id <= 0) return@mapNotNull null
                AdeudoComprobante(
                    idEstadoCuenta = id,
                    concepto = item.optString("concepto"),
                    periodo = item.optString("periodo"),
                    fechaLimite = item.optString("fechaLimite"),
                    saldo = item.optString("saldo")
                )
            }
        } finally {
            conexion.disconnect()
        }
    }

    suspend fun enviar(
        resolver: ContentResolver,
        uri: Uri,
        tipoMime: String,
        idEstadoCuenta: Int,
        monto: String,
        referencia: String,
        concepto: String
    ): String = withContext(Dispatchers.IO) {
        val extension = when (tipoMime.lowercase()) {
            "application/pdf" -> "pdf"
            "image/jpeg" -> "jpg"
            "image/png" -> "png"
            else -> throw IOException("El formato de archivo no está permitido.")
        }
        val limiteBytes = 5L * 1024L * 1024L
        val boundary = "Alkira-${UUID.randomUUID()}"
        val conexion = abrirConexion("/api/comprobantes/enviar-movil", "POST")
        try {
            conexion.doOutput = true
            conexion.setChunkedStreamingMode(8192)
            conexion.setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
            conexion.outputStream.use { salida ->
                fun escribir(valor: String) = salida.write(valor.toByteArray(Charsets.UTF_8))
                fun campo(nombre: String, valor: String) {
                    escribir("--$boundary\r\n")
                    escribir("Content-Disposition: form-data; name=\"$nombre\"\r\n\r\n")
                    escribir(valor)
                    escribir("\r\n")
                }
                campo("idEstadoCuenta", idEstadoCuenta.toString())
                campo("monto", monto)
                campo("referencia", referencia)
                campo("concepto", concepto)
                escribir("--$boundary\r\n")
                escribir("Content-Disposition: form-data; name=\"comprobante\"; filename=\"comprobante.$extension\"\r\n")
                escribir("Content-Type: $tipoMime\r\n\r\n")
                val entrada = resolver.openInputStream(uri)
                    ?: throw IOException("No se pudo abrir el comprobante seleccionado.")
                entrada.use { archivo ->
                    val buffer = ByteArray(8192)
                    var total = 0L
                    while (true) {
                        val bytes = archivo.read(buffer)
                        if (bytes < 0) break
                        total += bytes
                        if (total > limiteBytes) {
                            throw IOException("El comprobante supera los 5 MB.")
                        }
                        salida.write(buffer, 0, bytes)
                    }
                    if (total == 0L) throw IOException("El comprobante está vacío.")
                }
                escribir("\r\n--$boundary--\r\n")
            }
            val json = respuesta(conexion)
            json.optString("mensaje").ifBlank { "Comprobante recibido para revisión." }
        } finally {
            conexion.disconnect()
        }
    }

    private fun abrirConexion(ruta: String, metodo: String): HttpURLConnection {
        val token = SesionMovil.token
            ?: throw IOException("Tu sesión terminó. Inicia sesión nuevamente.")
        return (URL(ConfiguracionApi.URL_BASE.trimEnd('/') + ruta).openConnection() as HttpURLConnection).apply {
            requestMethod = metodo
            connectTimeout = 15_000
            readTimeout = 30_000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Authorization", "Bearer $token")
        }
    }

    private fun respuesta(conexion: HttpURLConnection): JSONObject {
        val codigo = conexion.responseCode
        val cuerpo = (if (codigo in 200..299) conexion.inputStream else conexion.errorStream)
            ?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
        val json = try {
            JSONObject(cuerpo)
        } catch (_: Exception) {
            throw IOException("El servidor no devolvió una respuesta JSON válida (HTTP $codigo).")
        }
        if (codigo !in 200..299) {
            throw ErrorApi(codigo, json.optString("mensaje").ifBlank { "Error HTTP $codigo" })
        }
        return json
    }
}
