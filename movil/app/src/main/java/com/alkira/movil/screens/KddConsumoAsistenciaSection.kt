
package com.alkira.movil.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.KddMovilApi
import com.alkira.movil.red.ResumenKddMovil
import com.alkira.movil.ui.theme.*
import java.math.BigDecimal
import java.text.NumberFormat
import java.util.Locale

private fun formatoCantidad(valor: BigDecimal): String {
    return NumberFormat.getNumberInstance(
        Locale.forLanguageTag("es-MX")
    ).apply {
        maximumFractionDigits = 2
        minimumFractionDigits = 0
    }.format(valor)
}

@Composable
fun KddConsumoAsistenciaSection() {
    var expandido by remember {
        mutableStateOf(false)
    }

    var cargando by remember {
        mutableStateOf(false)
    }

    var error by remember {
        mutableStateOf<String?>(null)
    }

    var resumen by remember {
        mutableStateOf<ResumenKddMovil?>(null)
    }

    var reintentos by remember {
        mutableIntStateOf(0)
    }

    LaunchedEffect(expandido, reintentos) {
        if (!expandido) return@LaunchedEffect

        cargando = true
        error = null

        try {
            resumen = KddMovilApi.consultarResumen()
        } catch (excepcion: Exception) {
            resumen = null
            error = excepcion.message
                ?: "No se pudieron cargar las métricas."
        } finally {
            cargando = false
        }
    }

    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = AlkiraSurfaceWhite
        ),
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(
                    modifier = Modifier.weight(1f)
                ) {
                    Text(
                        text = "Consumo y asistencia",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraDarkNavy
                    )

                    Text(
                        text = "Información personal adicional",
                        fontSize = 11.sp,
                        color = AlkiraTextSecondary
                    )
                }

                TextButton(
                    onClick = {
                        expandido = !expandido
                    }
                ) {
                    Text(
                        if (expandido) {
                            "Ocultar"
                        } else {
                            "Ver"
                        }
                    )
                }
            }

            if (expandido) {
                if (cargando) {
                    LinearProgressIndicator(
                        modifier = Modifier.fillMaxWidth()
                    )
                }

                error?.let { mensaje ->
                    Text(
                        text = mensaje,
                        color = AlkiraDangerRed,
                        fontSize = 12.sp
                    )

                    TextButton(
                        onClick = { reintentos++ }
                    ) {
                        Text("Reintentar")
                    }
                }

                val datos = resumen

                if (!cargando && datos != null) {
                    Text(
                        text = "Periodo: ${datos.periodo}",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary
                    )

                    HorizontalDivider(
                        color = AlkiraBorderSubtle
                    )

                    FilaMetricaKdd(
                        titulo = "Consumo de agua",
                        valor = if (datos.consumoAgua.disponible) {
                            "${formatoCantidad(datos.consumoAgua.total)} m³"
                        } else {
                            "Sin registros"
                        }
                    )

                    HorizontalDivider(
                        color = AlkiraBorderSubtle
                    )

                    FilaMetricaKdd(
                        titulo = "Consumo de electricidad",
                        valor = if (
                            datos.consumoElectricidad.disponible
                        ) {
                            "${formatoCantidad(datos.consumoElectricidad.total)} kWh"
                        } else {
                            "Sin registros"
                        }
                    )

                    HorizontalDivider(
                        color = AlkiraBorderSubtle
                    )

                    FilaMetricaKdd(
                        titulo = "Asistencia a eventos",
                        valor = if (
                            datos.asistenciaEventos.disponible
                        ) {
                            "${datos.asistenciaEventos.cantidad} registrada(s)"
                        } else {
                            "Sin registros"
                        }
                    )

                    Text(
                        text = "Se muestran únicamente registros disponibles del mes actual. " +
                                "No se estiman consumos ni asistencias.",
                        fontSize = 11.sp,
                        color = AlkiraTextSecondary
                    )

                    TextButton(
                        onClick = { reintentos++ }
                    ) {
                        Text("Actualizar")
                    }
                }
            }
        }
    }
}

@Composable
private fun FilaMetricaKdd(
    titulo: String,
    valor: String
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = titulo,
            fontSize = 12.sp,
            color = AlkiraTextSecondary,
            modifier = Modifier.weight(1f)
        )

        Spacer(
            modifier = Modifier.width(12.dp)
        )

        Text(
            text = valor,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = AlkiraDarkNavy
        )
    }
}
