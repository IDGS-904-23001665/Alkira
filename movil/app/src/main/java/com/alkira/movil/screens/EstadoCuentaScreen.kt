package com.alkira.movil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.platform.LocalInspectionMode
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.AdeudoPendiente
import com.alkira.movil.red.AdeudosApi
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.ResumenAdeudos
import com.alkira.movil.ui.theme.PantallasMovilTheme
import java.io.IOException
import java.math.BigDecimal
import java.text.NumberFormat
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

private val FONDO = Color(0xFFF8FAFC)
private val TARJETA = Color(0xFFEDF2F7)
private val BORDE = Color(0xFFCBD5E1)
private val TEXTO = Color(0xFF0F172A)
private val TEXTO_SECUNDARIO = Color(0xFF475569)
private val AZUL = Color(0xFF2563EB)
private val TERRACOTA = Color(0xFFC85233)

@Composable
fun EstadoCuentaScreen(modifier: Modifier = Modifier) {
    var resumen by remember { mutableStateOf<ResumenAdeudos?>(null) }
    var cargando by remember { mutableStateOf(true) }
    var mensajeError by remember { mutableStateOf<String?>(null) }
    var numeroConsulta by remember { mutableIntStateOf(0) }
    val vistaPrevia = LocalInspectionMode.current

    LaunchedEffect(numeroConsulta) {
        if (vistaPrevia) {
            cargando = false
            return@LaunchedEffect
        }
        cargando = true
        mensajeError = null
        try {
            resumen = AdeudosApi.consultarPendientes()
        } catch (error: ErrorApi) {
            resumen = null
            mensajeError = when (error.codigoHttp) {
                401 -> "Tu sesión venció. Cierra sesión e inicia nuevamente."
                403 -> "Tu usuario no tiene permiso para consultar adeudos de inquilino."
                else -> error.message ?: "No se pudieron cargar tus adeudos."
            }
        } catch (_: IOException) {
            resumen = null
            mensajeError = "No se pudo consultar el estado de cuenta. Revisa la conexión y el servidor PHP."
        } finally {
            cargando = false
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(FONDO)
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Mi Arrendamiento", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TEXTO)
            TextButton(onClick = { numeroConsulta++ }, enabled = !cargando) {
                Text("Actualizar", color = AZUL)
            }
        }

        if (cargando) {
            Box(Modifier.fillMaxWidth().padding(30.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = AZUL)
            }
        }

        mensajeError?.let { mensaje ->
            Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFFFE4E6))) {
                Text(
                    mensaje,
                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                    color = Color(0xFF9F1239)
                )
            }
        }

        resumen?.let { datos ->
            Card(
                colors = CardDefaults.cardColors(containerColor = TARJETA),
                shape = RoundedCornerShape(20.dp),
                modifier = Modifier.fillMaxWidth().border(1.dp, BORDE, RoundedCornerShape(20.dp))
            ) {
                Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Saldo Total Pendiente", fontSize = 12.sp, color = TEXTO_SECUNDARIO)
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text(
                            mostrarMonto(datos.totalPendiente),
                            fontSize = 28.sp,
                            fontWeight = FontWeight.Bold,
                            color = TEXTO
                        )
                        Spacer(Modifier.width(6.dp))
                        Text("MXN", fontSize = 14.sp, color = TEXTO_SECUNDARIO)
                    }
                    val siguiente = datos.adeudos.minByOrNull { it.fechaLimitePago }
                    if (siguiente != null) {
                        Surface(color = Color(0xFFFEF3C7), shape = RoundedCornerShape(8.dp)) {
                            Text(
                                "Fecha límite: ${mostrarFecha(siguiente.fechaLimitePago)} (${siguiente.concepto})",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = Color(0xFF92400E),
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 7.dp)
                            )
                        }
                    } else {
                        Text("No tienes cuotas pendientes.", color = TEXTO_SECUNDARIO)
                    }
                }
            }

            Card(
                colors = CardDefaults.cardColors(containerColor = TARJETA),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier.fillMaxWidth().border(1.dp, BORDE, RoundedCornerShape(16.dp))
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Detalles de mi unidad", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = TEXTO)
                    val unidades = datos.adeudos.map { it.numeroUnidad }.distinct()
                    Text(
                        if (unidades.isEmpty()) "Sin unidades con adeudos pendientes"
                        else "Unidad: ${unidades.joinToString()}",
                        fontSize = 13.sp,
                        color = TEXTO_SECUNDARIO
                    )
                    HorizontalDivider(color = BORDE)
                    Text(
                        "La vigencia y el documento del contrato se integrarán en la consulta de contratos.",
                        fontSize = 12.sp,
                        color = TEXTO_SECUNDARIO
                    )
                }
            }

            Text("Desglose de cuotas pendientes", fontWeight = FontWeight.Bold, color = TEXTO)
            if (datos.adeudos.isEmpty()) {
                Text("No hay cobros pendientes por mostrar.", color = TEXTO_SECUNDARIO)
            }
            datos.adeudos.forEach { adeudo ->
                TarjetaAdeudo(adeudo)
            }
        }

        HistorialContratosSeccion(numeroConsulta)

    }
}

@Composable
private fun TarjetaAdeudo(adeudo: AdeudoPendiente) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = RoundedCornerShape(14.dp),
        modifier = Modifier.fillMaxWidth().border(1.dp, BORDE, RoundedCornerShape(14.dp))
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(7.dp)) {
            Text(adeudo.concepto, fontWeight = FontWeight.Bold, color = TEXTO)
            Text(
                "Unidad ${adeudo.numeroUnidad} · Periodo ${adeudo.periodo}",
                fontSize = 12.sp,
                color = TEXTO_SECUNDARIO
            )
            Text("Fecha límite: ${mostrarFecha(adeudo.fechaLimitePago)}", color = TEXTO_SECUNDARIO)
            Text("Monto principal: ${mostrarMonto(adeudo.montoPrincipal)} MXN", color = TEXTO)
            Text("Recargo por mora: ${mostrarMonto(adeudo.recargoMora)} MXN", color = TEXTO)
            Text("Monto ya aplicado: ${mostrarMonto(adeudo.montoPagado)} MXN", color = TEXTO_SECUNDARIO)
            HorizontalDivider(color = BORDE)
            Text(
                "Pendiente: ${mostrarMonto(adeudo.montoPendiente)} MXN",
                fontWeight = FontWeight.Bold,
                color = AZUL
            )
            Text("Estado: ${adeudo.estado}", fontSize = 12.sp, color = TEXTO_SECUNDARIO)
        }
    }
}

private fun mostrarMonto(monto: BigDecimal): String {
    val formato = NumberFormat.getNumberInstance(Locale("es", "MX"))
    formato.minimumFractionDigits = 2
    formato.maximumFractionDigits = 2
    return "\$${formato.format(monto)}"
}

private fun mostrarFecha(fecha: String): String = try {
    LocalDate.parse(fecha).format(DateTimeFormatter.ofPattern("dd/MMM/yyyy", Locale("es", "MX")))
} catch (_: Exception) {
    fecha
}

@Preview(showBackground = true)
@Composable
fun VistaPreviaEstadoCuenta() {
    PantallasMovilTheme {
        EstadoCuentaScreen()
    }
}
