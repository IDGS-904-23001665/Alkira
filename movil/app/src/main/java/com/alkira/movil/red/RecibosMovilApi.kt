
package com.alkira.movil.red

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.File
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

object RecibosMovilApi {

    private const val MAX_PDF = 12 * 1024 * 1024L

    suspend fun descargar(
        contexto: Context,
        idPago: Int
    ): File = withContext(Dispatchers.IO) {

        require(idPago > 0) {
            "El identificador del pago no es válido."
        }

        val token = SesionMovil.token
            ?: throw ErrorApi(
                401,
                "Debes iniciar sesión."
            )

        val url = URL(
            "${ConfiguracionApi.URL_BASE}/api/pagos/$idPago/recibo-movil"
        )

        val conexion = url.openConnection() as HttpURLConnection

        var archivo: File? = null

        try {

            conexion.requestMethod = "GET"
            conexion.connectTimeout = 10_000
            conexion.readTimeout = 25_000

            conexion.setRequestProperty(
                "Authorization",
                "Bearer $token"
            )

            conexion.setRequestProperty(
                "Accept",
                "application/pdf"
            )

            val codigo = conexion.responseCode

            if (codigo !in 200..299) {

                val respuesta = conexion.errorStream
                    ?.bufferedReader()
                    ?.use { it.readText() }
                    .orEmpty()

                val mensaje = runCatching {
                    JSONObject(respuesta).optString("mensaje")
                }.getOrNull()
                    .orEmpty()
                    .ifBlank {
                        "No se pudo descargar el recibo."
                    }

                throw ErrorApi(codigo, mensaje)
            }

            if (
                !conexion.contentType.orEmpty()
                    .startsWith(
                        "application/pdf",
                        ignoreCase = true
                    )
            ) {

                throw IOException(
                    "El servidor no devolvió un PDF."
                )
            }

            if (conexion.contentLengthLong > MAX_PDF) {

                throw IOException(
                    "El PDF supera el tamaño permitido."
                )
            }

            val carpeta = File(
                contexto.cacheDir,
                "recibos"
            ).apply {
                mkdirs()
            }

            archivo = File(
                carpeta,
                "recibo_alkira_$idPago.pdf"
            )

            var total = 0L

            conexion.inputStream.use { entrada ->

                archivo!!.outputStream().use { salida ->

                    val bloque = ByteArray(8192)

                    while (true) {

                        val cantidad = entrada.read(bloque)

                        if (cantidad == -1) break

                        total += cantidad

                        if (total > MAX_PDF) {
                            throw IOException(
                                "El PDF supera el tamaño permitido."
                            )
                        }

                        salida.write(
                            bloque,
                            0,
                            cantidad
                        )
                    }
                }
            }

            val pdfValido = archivo!!.inputStream().use {
                entrada ->

                val cabecera = ByteArray(5)

                entrada.read(cabecera) == 5 &&
                    String(
                        cabecera,
                        Charsets.US_ASCII
                    ) == "%PDF-"
            }

            if (total < 5 || !pdfValido) {

                throw IOException(
                    "El archivo recibido no es un PDF válido."
                )
            }

            archivo!!

        } catch (excepcion: Exception) {

            archivo?.delete()

            throw excepcion

        } finally {

            conexion.disconnect()
        }
    }
}
