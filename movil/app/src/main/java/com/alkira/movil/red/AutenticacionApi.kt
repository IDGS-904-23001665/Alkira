package com.alkira.movil.red

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

/** Contrato de datos entregado por la API PHP de ALKIRA. */
data class UsuarioMovil(
    val idUsuario: Int,
    val nombres: String,
    val apellidos: String,
    val correo: String,
    val tipoUsuario: String
)

data class RespuestaLoginMovil(
    val usuario: UsuarioMovil,
    val token: String
)

class ErrorApi(val codigoHttp: Int, mensaje: String) : IOException(mensaje)

/**
 * Mantiene el JWT solo en memoria. Al cerrar la aplicación por completo
 * será necesario iniciar sesión nuevamente.
 */
object SesionMovil {
    var token: String? = null
        private set

    fun iniciar(tokenNuevo: String) {
        token = tokenNuevo
    }

    fun cerrar() {
        token = null
    }
}

object AutenticacionApi {

    suspend fun iniciarSesion(correo: String, contrasena: String): RespuestaLoginMovil =
        withContext(Dispatchers.IO) {
            val datos = JSONObject().apply {
                put("correo", correo.trim())
                put("contrasena", contrasena)
            }
            val respuesta = solicitar(
                ruta = "/api/auth/login-movil",
                metodo = "POST",
                cuerpo = datos.toString()
            )
            val token = respuesta.optString("token")
            val usuario = respuesta.optJSONObject("usuario")
                ?: throw IOException("La API no devolvió los datos del usuario.")

            if (token.isBlank()) {
                throw IOException("La API no devolvió el token de acceso.")
            }
            RespuestaLoginMovil(leerUsuario(usuario), token)
        }

    suspend fun verificarSesion(token: String): UsuarioMovil = withContext(Dispatchers.IO) {
        val respuesta = solicitar(
            ruta = "/api/auth/yo-movil",
            metodo = "GET",
            token = token
        )
        val usuario = respuesta.optJSONObject("usuario")
            ?: throw IOException("La API no devolvió la sesión del usuario.")
        leerUsuario(usuario)
    }

    private fun leerUsuario(datos: JSONObject): UsuarioMovil {
        val idUsuario = datos.optInt("idUsuario", 0)
        val nombres = datos.optString("nombres")
        val correo = datos.optString("correo")
        val tipoUsuario = datos.optString("tipoUsuario")

        if (idUsuario <= 0 || nombres.isBlank() || correo.isBlank() || tipoUsuario.isBlank()) {
            throw IOException("Los datos recibidos del usuario están incompletos.")
        }
        return UsuarioMovil(
            idUsuario = idUsuario,
            nombres = nombres,
            apellidos = datos.optString("apellidos"),
            correo = correo,
            tipoUsuario = tipoUsuario
        )
    }

    private fun solicitar(
        ruta: String,
        metodo: String,
        cuerpo: String? = null,
        token: String? = null
    ): JSONObject {
        val conexion = (URL(ConfiguracionApi.URL_BASE + ruta).openConnection() as HttpURLConnection).apply {
            requestMethod = metodo
            connectTimeout = 10_000
            readTimeout = 10_000
            setRequestProperty("Accept", "application/json")
            if (token != null) {
                setRequestProperty("Authorization", "Bearer $token")
            }
            if (cuerpo != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json; charset=utf-8")
            }
        }

        try {
            if (cuerpo != null) {
                conexion.outputStream.use { salida ->
                    salida.write(cuerpo.toByteArray(Charsets.UTF_8))
                }
            }

            val codigo = conexion.responseCode
            val contenido = (if (codigo in 200..299) conexion.inputStream else conexion.errorStream)
                ?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()

            val respuesta = try {
                JSONObject(contenido)
            } catch (_: Exception) {
                throw IOException("El servidor no devolvió una respuesta JSON válida.")
            }

            if (codigo !in 200..299) {
                val mensaje = respuesta.optString("mensaje")
                    .ifBlank { "No fue posible completar la solicitud." }
                throw ErrorApi(codigo, mensaje)
            }

            return respuesta
        } finally {
            conexion.disconnect()
        }
    }
}
