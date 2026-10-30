package com.alkira.movil.red

import org.json.JSONObject

data class PaseResidente(val id: Int, val visitante: String, val token: String, val inicio: String, val fin: String, val estado: String)

object PasesQrApi {
    private fun leer(json: JSONObject) = PaseResidente(json.getInt("idPase"), json.getString("nombreVisitante"),
        json.getString("tokenQr"), json.getString("fechaInicioVigencia"), json.getString("fechaFinVigencia"), json.getString("estado"))

    suspend fun listar(): List<PaseResidente> {
        val lista = ClienteHistoriasApi.solicitar("/api/accesos-qr").getJSONArray("pases")
        return (0 until lista.length()).map { leer(lista.getJSONObject(it)) }
    }

    suspend fun generar(nombre: String, horas: Int): PaseResidente {
        val datos = JSONObject().put("nombreVisitante", nombre.trim()).put("horasVigencia", horas)
        return leer(ClienteHistoriasApi.solicitar("/api/accesos-qr", "POST", datos).getJSONObject("pase"))
    }

    suspend fun revocar(id: Int) {
        ClienteHistoriasApi.solicitar("/api/accesos-qr/$id/revocar", "POST", JSONObject())
    }
}
