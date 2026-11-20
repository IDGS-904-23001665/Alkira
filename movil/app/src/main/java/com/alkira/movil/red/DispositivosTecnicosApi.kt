package com.alkira.movil.red

import org.json.JSONObject

object DispositivosTecnicosApi {

    suspend fun registrarToken(token: String) {
        ClienteHistoriasApi.solicitar(
            ruta = "/api/mantenimiento/dispositivos",
            metodo = "POST",
            datos = JSONObject().put("token", token)
        )
    }

    suspend fun eliminarToken(token: String) {
        ClienteHistoriasApi.solicitar(
            ruta = "/api/mantenimiento/dispositivos",
            metodo = "DELETE",
            datos = JSONObject().put("token", token)
        )
    }
}
