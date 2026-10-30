package com.alkira.movil.red

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.File
import java.io.IOException
import java.math.BigDecimal
import java.net.HttpURLConnection
import java.net.URL

data class PagoRegistrado(
    val idPago: Int,
    val fechaPago: String?,
    val monto: BigDecimal,
    val metodoPago: String,
    val referenciaBancaria: String?,
    val estado: String,
    val concepto: String,
    val periodo: String,
    val numeroUnidad: String
)

data class ContratoInquilino(
    val idContrato: Int,
    val numeroUnidad: String,
    val fechaInicio: String,
    val fechaFin: String?,
    val montoRenta: BigDecimal,
    val depositoGarantia: BigDecimal,
    val estadoContrato: String,
    val documentoDisponible: Boolean
)

object HistorialContratosApi {
    private const val MAX_BYTES_PDF = 12 * 1024 * 1024

    suspend fun consultarPagos(): List<PagoRegistrado> = withContext(Dispatchers.IO) {
        val respuesta = solicitarJson("/api/pagos/historial")
        val pagos = respuesta.optJSONArray("pagos")
            ?: throw IOException("La API no devolvió el historial de pagos.")
        try {
            (0 until pagos.length()).map { indice ->
                val datos = pagos.getJSONObject(indice)
                PagoRegistrado(
                    idPago = datos.getInt("idPago"),
                    fechaPago = datos.textoOpcional("fechaPago"),
                    monto = datos.monto("monto"),
                    metodoPago = datos.getString("metodoPago"),
                    referenciaBancaria = datos.textoOpcional("referenciaBancaria"),
                    estado = datos.getString("estado"),
                    concepto = datos.getString("concepto"),
                    periodo = datos.getString("periodo"),
                    numeroUnidad = datos.getString("numeroUnidad")
                )
            }
        } catch (error: Exception) {
            throw IOException("La respuesta de pagos no tiene el formato esperado.", error)
        }
    }

    suspend fun consultarContratos(): List<ContratoInquilino> = withContext(Dispatchers.IO) {
        val respuesta = solicitarJson("/api/contratos")
        val contratos = respuesta.optJSONArray("contratos")
            ?: throw IOException("La API no devolvió la lista de contratos.")
        try {
            (0 until contratos.length()).map { indice ->
                val datos = contratos.getJSONObject(indice)
                ContratoInquilino(
                    idContrato = datos.getInt("idContrato"),
                    numeroUnidad = datos.getString("numeroUnidad"),
                    fechaInicio = datos.getString("fechaInicio"),
                    fechaFin = datos.textoOpcional("fechaFin"),
                    montoRenta = datos.monto("montoRenta"),
                    depositoGarantia = datos.monto("depositoGarantia"),
                    estadoContrato = datos.getString("estadoContrato"),
                    documentoDisponible = datos.getBoolean("documentoDisponible")
                )
            }
        } catch (error: Exception) {
            throw IOException("La respuesta de contratos no tiene el formato esperado.", error)
        }
    }

    suspend fun descargarContrato(contexto: Context, idContrato: Int): File =
        withContext(Dispatchers.IO) {
            require(idContrato > 0) { "El identificador del contrato no es válido." }
            val conexion = abrirConexion("/api/contratos/$idContrato/documento")
            var archivo: File? = null
            try {
                val codigo = conexion.responseCode
                if (codigo !in 200..299) {
                    throw leerError(conexion, codigo)
                }
                if (!conexion.contentType.orEmpty().startsWith("application/pdf", true)) {
                    throw IOException("La API no devolvió un documento PDF.")
                }
                val tamanoInformado = conexion.contentLengthLong
                if (tamanoInformado > MAX_BYTES_PDF) {
                    throw IOException("El contrato supera el tamaño permitido.")
                }

                archivo = File.createTempFile("contrato_alkira_", ".pdf", contexto.cacheDir)
                var bytesRecibidos = 0L
                conexion.inputStream.use { entrada ->
                    archivo!!.outputStream().use { salida ->
                        val bloque = ByteArray(8192)
                        while (true) {
                            val cantidad = entrada.read(bloque)
                            if (cantidad == -1) break
                            bytesRecibidos += cantidad
                            if (bytesRecibidos > MAX_BYTES_PDF) {
                                throw IOException("El contrato supera el tamaño permitido.")
                            }
                            salida.write(bloque, 0, cantidad)
                        }
                    }
                }

                val encabezado = ByteArray(5)
                val cantidadLeida = archivo!!.inputStream().use { lector -> lector.read(encabezado) }
                if (bytesRecibidos < 5 || cantidadLeida != 5 ||
                    String(encabezado, Charsets.US_ASCII) != "%PDF-") {
                    throw IOException("El archivo recibido no es un PDF válido.")
                }
                archivo!!
            } catch (error: Exception) {
                archivo?.delete()
                throw error
            } finally {
                conexion.disconnect()
            }
        }

    private fun solicitarJson(ruta: String): JSONObject {
        val conexion = abrirConexion(ruta)
        try {
            val codigo = conexion.responseCode
            val contenido = (if (codigo in 200..299) conexion.inputStream else conexion.errorStream)
                ?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            val datos = try {
                JSONObject(contenido)
            } catch (error: Exception) {
                throw IOException("El servidor no devolvió una respuesta JSON válida.", error)
            }
            if (codigo !in 200..299) {
                throw ErrorApi(codigo, datos.optString("mensaje", "No fue posible consultar la información."))
            }
            return datos
        } finally {
            conexion.disconnect()
        }
    }

    private fun abrirConexion(ruta: String): HttpURLConnection {
        val token = SesionMovil.token
            ?: throw ErrorApi(401, "La sesión ha finalizado. Inicia sesión nuevamente.")
        return (URL(ConfiguracionApi.URL_BASE + ruta).openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 10_000
            readTimeout = 20_000
            setRequestProperty("Accept", "application/json, application/pdf")
            setRequestProperty("Authorization", "Bearer $token")
        }
    }

    private fun leerError(conexion: HttpURLConnection, codigo: Int): ErrorApi {
        val mensaje = try {
            val texto = conexion.errorStream?.bufferedReader(Charsets.UTF_8)
                ?.use { it.readText() }.orEmpty()
            JSONObject(texto).optString("mensaje").ifBlank { "No se pudo abrir el contrato." }
        } catch (_: Exception) {
            "No se pudo abrir el contrato."
        }
        return ErrorApi(codigo, mensaje)
    }

    private fun JSONObject.textoOpcional(nombre: String): String? =
        if (isNull(nombre)) null else optString(nombre).takeIf { it.isNotBlank() }

    private fun JSONObject.monto(nombre: String): BigDecimal =
        try {
            BigDecimal(getString(nombre))
        } catch (error: Exception) {
            throw IOException("El monto '$nombre' no es válido.", error)
        }
}
