package com.alkira.movil.red

import org.json.JSONObject

data class ConversacionChat(
    val id: Long,
    val estado: String,
    val fecha: String,
    val totalMensajes: Int
)

data class MensajeChat(
    val contenido: String,
    val esUsuario: Boolean,
    val fecha: String = ""
)

data class RespuestaChat(
    val idConversacion: Long,
    val respuesta: String
)

object ChatbotApi {

    suspend fun enviar(
        mensaje: String,
        idConversacion: Long?
    ): RespuestaChat {

        val datos = JSONObject()
            .put("mensaje", mensaje)

        if (idConversacion != null) {
            datos.put("idConversacion", idConversacion)
        }

        val json = ClienteHistoriasApi.solicitar(
            "/api/chatbot/mensaje",
            "POST",
            datos
        )

        return RespuestaChat(
            idConversacion = json.getLong("idConversacion"),
            respuesta = json.getString("respuesta")
        )
    }

    suspend fun conversaciones(): List<ConversacionChat> {

        val lista = ClienteHistoriasApi.solicitar(
            "/api/chatbot/conversaciones"
        ).getJSONArray("conversaciones")

        return List(lista.length()) { indice ->

            val dato = lista.getJSONObject(indice)

            ConversacionChat(
                id = dato.getLong("idConversacion"),
                estado = dato.getString("estado"),
                fecha = dato.getString("fechaInicio"),
                totalMensajes = dato.getInt("totalMensajes")
            )
        }
    }

    suspend fun mensajes(id: Long): List<MensajeChat> {

        val lista = ClienteHistoriasApi.solicitar(
            "/api/chatbot/conversaciones/$id/mensajes"
        ).getJSONArray("mensajes")

        return List(lista.length()) { indice ->

            val dato = lista.getJSONObject(indice)

            MensajeChat(
                contenido = dato.getString("contenido"),
                esUsuario = dato.getString("emisor") == "usuario",
                fecha = dato.optString("fecha")
            )
        }
    }
}