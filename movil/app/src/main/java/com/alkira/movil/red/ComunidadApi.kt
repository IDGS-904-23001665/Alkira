package com.alkira.movil.red

import org.json.JSONObject

data class AvisoComunidad(
    val id: Int, val titulo: String, val contenido: String, val inmueble: String,
    val fecha: String, val leido: Boolean
)
data class UnidadVoto(val id: Int, val etiqueta: String)
data class OpcionComunidad(val id: Int, val texto: String, val votos: Int, val porcentaje: Double)
data class EncuestaComunidad(
    val id: Int, val titulo: String, val descripcion: String, val inmueble: String,
    val inicio: String, val cierre: String, val estado: String,
    val unidades: List<UnidadVoto>, val opciones: List<OpcionComunidad>,
    val misVotos: Map<Int,Int>, val total: Int
)

object ComunidadApi {
    suspend fun avisos(): List<AvisoComunidad> {
        val r=ClienteHistoriasApi.solicitar("/api/hu07/avisos").getJSONArray("avisos")
        return (0 until r.length()).map { index -> r.getJSONObject(index).let { x ->
            AvisoComunidad(x.getInt("id"),x.optString("titulo"),x.optString("contenido"),
                x.optString("inmueble"),x.optString("fecha"),!x.isNull("fechaLectura"))
        } }
    }
    suspend fun marcarLeido(id: Int) {
        ClienteHistoriasApi.solicitar("/api/hu07/avisos/$id/leer","POST",JSONObject())
    }
    suspend fun votaciones(): List<EncuestaComunidad> {
        val r=ClienteHistoriasApi.solicitar("/api/hu07/votaciones-movil").getJSONArray("votaciones")
        return (0 until r.length()).map { index -> r.getJSONObject(index).let { x ->
            val unidades=x.getJSONArray("unidades").let { arr -> (0 until arr.length()).map { i ->
                arr.getJSONObject(i).let { u -> UnidadVoto(u.getInt("id"),u.getString("etiqueta")) }
            } }
            val votos=x.getJSONArray("misVotos").let { arr -> (0 until arr.length()).associate { i ->
                val v=arr.getJSONObject(i);v.getInt("idUnidad") to v.getInt("idOpcion")
            } }
            val resultado=x.getJSONObject("resultados")
            val opciones=resultado.getJSONArray("opciones").let { arr -> (0 until arr.length()).map { i ->
                arr.getJSONObject(i).let { o -> OpcionComunidad(o.getInt("id"),o.getString("texto"),o.getInt("votos"),o.getDouble("porcentaje")) }
            } }
            EncuestaComunidad(x.getInt("id"),x.optString("titulo"),x.optString("descripcion"),
                x.optString("inmueble"),x.optString("inicio"),x.optString("cierre"),x.optString("estado"),
                unidades,opciones,votos,resultado.optInt("total"))
        } }
    }
    suspend fun votar(id: Int, unidad: Int, opcion: Int) {
        ClienteHistoriasApi.solicitar("/api/hu07/votaciones/$id/votar","POST",JSONObject().apply {
            put("idUnidad",unidad);put("idOpcion",opcion)
        })
    }
    suspend fun registrarToken(token: String) {
        ClienteHistoriasApi.solicitar("/api/hu07/dispositivos","POST",JSONObject().put("token",token))
    }
    suspend fun eliminarToken(token: String) {
        ClienteHistoriasApi.solicitar("/api/hu07/dispositivos","DELETE",JSONObject().put("token",token))
    }
}
