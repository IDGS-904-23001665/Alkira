package com.alkira.movil.red

import android.content.Context
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID

/** Transporte compartido de las historias del residente; reutiliza la sesión existente. */
object ClienteHistoriasApi {
    suspend fun solicitar(
        ruta: String,
        metodo: String = "GET",
        datos: JSONObject? = null,
        context: Context? = null,
        archivo: Uri? = null
    ): JSONObject = withContext(Dispatchers.IO) {
        val token = SesionMovil.token ?: throw ErrorApi(401, "Tu sesión terminó. Inicia sesión nuevamente.")
        val conexion = (URL(ConfiguracionApi.URL_BASE + ruta).openConnection() as HttpURLConnection).apply {
            requestMethod = metodo
            connectTimeout = 10_000
            readTimeout = 30_000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Authorization", "Bearer $token")
        }
        try {
            if (archivo != null) {
                val resolver = requireNotNull(context).contentResolver
                val bytes = resolver.openInputStream(archivo)?.use { entrada ->
                    val salida = ByteArrayOutputStream()
                    val bloque = ByteArray(8192)
                    var total = 0
                    while (true) {
                        val leidos = entrada.read(bloque)
                        if (leidos < 0) break
                        total += leidos
                        if (total > 20 * 1024 * 1024) throw IOException("El archivo supera los 20 MB.")
                        salida.write(bloque, 0, leidos)
                    }
                    salida.toByteArray()
                } ?: throw IOException("No se pudo leer el archivo seleccionado.")
                if (bytes.isEmpty()) throw IOException("El archivo está vacío.")
                val limite = "Alkira-${UUID.randomUUID()}"
                val campos = buildString {
                    datos?.keys()?.forEach { clave ->
                        if (!datos.isNull(clave)) {
                            append("--$limite\r\nContent-Disposition: form-data; name=\"$clave\"\r\n\r\n")
                            append(datos.get(clave).toString()).append("\r\n")
                        }
                    }
                    append("--$limite\r\nContent-Disposition: form-data; name=\"archivo\"; filename=\"evidencia\"\r\n")
                    append("Content-Type: application/octet-stream\r\n\r\n")
                }.toByteArray(Charsets.UTF_8)
                val fin = "\r\n--$limite--\r\n".toByteArray(Charsets.UTF_8)
                conexion.doOutput = true
                conexion.setRequestProperty("Content-Type", "multipart/form-data; boundary=$limite")
                conexion.setFixedLengthStreamingMode(campos.size + bytes.size + fin.size)
                conexion.outputStream.use { it.write(campos); it.write(bytes); it.write(fin) }
            } else if (datos != null) {
                conexion.doOutput = true
                conexion.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                conexion.outputStream.use { it.write(datos.toString().toByteArray(Charsets.UTF_8)) }
            }
            val codigo = conexion.responseCode
            val texto = (if (codigo in 200..299) conexion.inputStream else conexion.errorStream)
                ?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            val json = try { JSONObject(texto) } catch (_: Exception) {
                throw IOException("El servidor no devolvió una respuesta válida.")
            }
            if (codigo !in 200..299) throw ErrorApi(codigo, json.optString("mensaje", "No se pudo completar la operación."))
            json
        } finally { conexion.disconnect() }
    }
}
