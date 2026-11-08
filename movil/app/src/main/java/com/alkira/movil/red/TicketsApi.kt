
package com.alkira.movil.red

import org.json.JSONObject

data class TicketMantenimiento(
    val idTicket: Int,
    val titulo: String,
    val descripcion: String,
    val urgencia: String,
    val estado: String,
    val fechaReporte: String,
    val fechaProgramada: String?,
    val nombreInmueble: String?,
    val numeroUnidad: String?,
    val nombreTecnico: String?,
    val descripcionTrabajo: String?,
    val estadoOrden: String?,
    val tieneFirma: Boolean = false,
    val puedeFirmar: Boolean = false,
    val puedeIniciar: Boolean = false
)

data class MovimientoTicket(
    val idMovimiento: Int,
    val estadoAnterior: String?,
    val estadoNuevo: String,
    val fechaCambio: String
)

private fun JSONObject.textoOpcional(clave: String): String? =
    if (isNull(clave)) null else optString(clave).takeIf { it.isNotBlank() }

object TicketsApi {

    suspend fun listar(): List<TicketMantenimiento> {
        val respuesta = ClienteHistoriasApi.solicitar("/api/tickets")
        val registros = respuesta.getJSONArray("tickets")

        return (0 until registros.length()).map { indice ->
            val registro = registros.getJSONObject(indice)

            TicketMantenimiento(
                idTicket = registro.getInt("idTicket"),
                titulo = registro.getString("titulo"),
                descripcion = registro.optString("descripcion"),
                urgencia = registro.optString("urgencia"),
                estado = registro.getString("estado"),
                fechaReporte = registro.optString("fechaReporte"),
                fechaProgramada = registro.textoOpcional("fechaProgramada"),
                nombreInmueble = registro.textoOpcional("nombreInmueble"),
                numeroUnidad = registro.textoOpcional("numeroUnidad"),
                nombreTecnico = registro.textoOpcional("nombreTecnico"),
                descripcionTrabajo = registro.textoOpcional("descripcionTrabajo"),
                estadoOrden = registro.textoOpcional("estadoOrden"),
                tieneFirma = registro.optBoolean("tieneFirma", false),
                puedeFirmar = registro.optBoolean("puedeFirmar", false),
                puedeIniciar = registro.optBoolean("puedeIniciar", false)
            )
        }
    }

    suspend fun historial(idTicket: Int): List<MovimientoTicket> {
        require(idTicket > 0) {
            "El identificador del ticket no es válido."
        }

        val respuesta = ClienteHistoriasApi.solicitar(
            "/api/tickets/$idTicket/historial"
        )

        val registros = respuesta.getJSONArray("historial")

        return (0 until registros.length()).map { indice ->
            val registro = registros.getJSONObject(indice)

            MovimientoTicket(
                idMovimiento = registro.getInt("idMovimiento"),
                estadoAnterior = registro.textoOpcional("estadoAnterior"),
                estadoNuevo = registro.getString("estadoNuevo"),
                fechaCambio = registro.optString("fechaCambio")
            )
        }
    }

    // HU06.3: El técnico asignado inicia el trabajo.
    suspend fun iniciarTrabajo(idTicket: Int): String {
        require(idTicket > 0) {
            "El identificador del ticket no es válido."
        }

        val respuesta = ClienteHistoriasApi.solicitar(
            ruta = "/api/tickets/$idTicket/iniciar",
            metodo = "POST"
        )

        return respuesta.optString(
            "mensaje",
            "Trabajo iniciado correctamente."
        )
    }

    // HU06.2: Registrar firma digital del residente.
    suspend fun registrarFirma(
        idTicket: Int,
        firmaBase64: String,
        comentario: String
    ): String {
        require(idTicket > 0) {
            "El identificador del ticket no es válido."
        }

        val datos = JSONObject().apply {
            put("firmaBase64", firmaBase64)
            put("comentario", comentario.trim())
        }

        val respuesta = ClienteHistoriasApi.solicitar(
            ruta = "/api/tickets/$idTicket/firma",
            metodo = "POST",
            datos = datos
        )

        return respuesta.optString(
            "mensaje",
            "Conformidad registrada."
        )
    }
}
