package com.alkira.movil.red

import android.content.Context
import android.net.Uri
import org.json.JSONObject

data class UnidadReporte(val id: Int, val inmueble: Int, val etiqueta: String)
data class ReporteResidente(val id: Int, val titulo: String, val estado: String, val fecha: String)

object IncidenciasApi {
    suspend fun unidades(): List<UnidadReporte> {
        val lista = ClienteHistoriasApi.solicitar("/api/incidencias/unidades").getJSONArray("unidades")
        return (0 until lista.length()).map { indice -> lista.getJSONObject(indice).let {
            UnidadReporte(it.getInt("idUnidad"), it.getInt("idInmueble"), "${it.getString("nombreInmueble")} · ${it.getString("numeroUnidad")}")
        } }
    }

    suspend fun listar(): List<ReporteResidente> {
        val lista = ClienteHistoriasApi.solicitar("/api/incidencias").getJSONArray("incidencias")
        return (0 until lista.length()).map { indice -> lista.getJSONObject(indice).let {
            ReporteResidente(it.getInt("idIncidencia"), it.getString("titulo"), it.getString("estado"), it.getString("fechaReporte"))
        } }
    }

    suspend fun crear(titulo: String, descripcion: String, categoria: String, urgencia: String,
                      unidad: UnidadReporte?, archivo: Uri?, context: Context): Int {
        val datos = JSONObject().apply {
            put("titulo", titulo.trim()); put("descripcion", descripcion.trim())
            put("categoria", categoria); put("urgencia", urgencia)
            if (unidad != null) { put("idUnidad", unidad.id); put("idInmueble", unidad.inmueble) }
        }
        return ClienteHistoriasApi.solicitar("/api/incidencias", "POST", datos, context, archivo)
            .getJSONObject("incidencia").getInt("idIncidencia")
    }
}
