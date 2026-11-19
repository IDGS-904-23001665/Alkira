
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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.AdeudosApi
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.HistorialContratosApi
import com.alkira.movil.red.PagoRegistrado
import com.alkira.movil.red.ResumenAdeudos
import com.alkira.movil.ui.theme.*
import java.io.IOException
import java.math.BigDecimal
import java.math.RoundingMode
import java.text.NumberFormat
import java.time.LocalDate
import java.util.Locale

private data class DatosKdd(
    val adeudos: ResumenAdeudos,
    val pagos: List<PagoRegistrado>
)

private fun formatoDinero(valor: BigDecimal): String {
    return NumberFormat.getCurrencyInstance(
        Locale.forLanguageTag("es-MX")
    ).format(valor)
}

private fun convertirFecha(fecha: String): LocalDate? {
    return runCatching {
        LocalDate.parse(fecha.take(10))
    }.getOrNull()
}

private fun fechaCorta(fecha: String?): String {
    return fecha?.take(10)?.takeIf {
        it.isNotBlank()
    } ?: "Sin fecha"
}

@Composable
fun MetricasScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var datos by remember {
        mutableStateOf<DatosKdd?>(null)
    }

    var cargando by remember {
        mutableStateOf(true)
    }

    var error by remember {
        mutableStateOf<String?>(null)
    }

    var intentos by remember {
        mutableIntStateOf(0)
    }

    var mostrarTodos by remember {
        mutableStateOf(false)
    }

    LaunchedEffect(intentos) {
        cargando = true
        error = null
        datos = null

        try {
            val resumen = AdeudosApi.consultarPendientes()
            val historial = HistorialContratosApi.consultarPagos()

            datos = DatosKdd(
                adeudos = resumen,
                pagos = historial.sortedByDescending {
                    it.fechaPago.orEmpty()
                }
            )

        } catch (excepcion: ErrorApi) {
            error = when (excepcion.codigoHttp) {
                401 -> "La sesión venció. Vuelve a iniciar sesión."
                403 -> "Esta información está disponible para inquilinos."
                else -> excepcion.message
                    ?: "No se pudieron consultar las métricas."
            }

        } catch (_: IOException) {
            error = "No fue posible conectar con el servidor ALKIRA."

        } catch (_: Exception) {
            error = "Ocurrió un error al cargar las métricas."

        } finally {
            cargando = false
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .verticalScroll(rememberScrollState())
            .padding(18.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {

        // Encabezado
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Mis métricas",
                    fontSize = 21.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraDarkNavy
                )

                Text(
                    text = "Mi información financiera",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary
                )
            }

            TextButton(
                onClick = onNavigateBack
            ) {
                Text("Volver")
            }
        }

        // Cargando
        if (cargando) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(28.dp),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator()
            }
        }

        // Error de conexión
        error?.let { mensaje ->
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = AlkiraAmberSoft
                )
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text(
                        text = mensaje,
                        color = AlkiraDarkNavy
                    )

                    Button(
                        onClick = {
                            intentos++
                        }
                    ) {
                        Text("Reintentar")
                    }
                }
            }
        }

        val informacion = datos

        if (!cargando && informacion != null) {

            val hoy = LocalDate.now()

            val cuentas = informacion.adeudos.adeudos

            // Cuentas vencidas
            val cuentasVencidas = cuentas.filter { cuenta ->
                val fecha = convertirFecha(
                    cuenta.fechaLimitePago
                )

                fecha != null &&
                        fecha.isBefore(hoy) &&
                        cuenta.montoPendiente > BigDecimal.ZERO
            }

            // Cuentas por vencer
            val cuentasProximas = cuentas.filter { cuenta ->
                val fecha = convertirFecha(
                    cuenta.fechaLimitePago
                )

                fecha != null &&
                        !fecha.isBefore(hoy) &&
                        !fecha.isAfter(hoy.plusDays(15)) &&
                        cuenta.montoPendiente > BigDecimal.ZERO
            }

            // Pagos realmente aprobados
            val pagosAprobados = informacion.pagos.filter {
                it.estado.equals(
                    "aprobado",
                    ignoreCase = true
                )
            }

            val ultimoPago = pagosAprobados.firstOrNull()

            // Indicadores
            Row(
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                TarjetaKdd(
                    titulo = "Saldo pendiente",
                    valor = formatoDinero(
                        informacion.adeudos.totalPendiente
                    ),
                    color = AlkiraDarkNavy,
                    modifier = Modifier.weight(1f)
                )

                TarjetaKdd(
                    titulo = "Cuentas vencidas",
                    valor = cuentasVencidas.size.toString(),
                    color = if (cuentasVencidas.isEmpty()) {
                        AlkiraSuccessGreen
                    } else {
                        AlkiraDangerRed
                    },
                    modifier = Modifier.weight(1f)
                )
            }

            Row(
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                TarjetaKdd(
                    titulo = "Pagos aprobados",
                    valor = pagosAprobados.size.toString(),
                    color = AlkiraPrimaryBlue,
                    modifier = Modifier.weight(1f)
                )

                TarjetaKdd(
                    titulo = "Próximos a vencer",
                    valor = cuentasProximas.size.toString(),
                    color = AlkiraWarningAmber,
                    modifier = Modifier.weight(1f)
                )
            }

            // Resumen
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = AlkiraSurfaceWhite
                ),
                shape = RoundedCornerShape(14.dp)
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = "Mi situación actual",
                        fontWeight = FontWeight.Bold,
                        color = AlkiraDarkNavy
                    )

                    Text(
                        text = if (cuentasVencidas.isEmpty()) {
                            "No tienes cuentas vencidas."
                        } else {
                            "Tienes ${cuentasVencidas.size} cuenta(s) vencida(s)."
                        },
                        fontSize = 13.sp,
                        color = if (cuentasVencidas.isEmpty()) {
                            AlkiraSuccessGreen
                        } else {
                            AlkiraDangerRed
                        }
                    )

                    if (cuentasProximas.isNotEmpty()) {
                        Text(
                            text = "${cuentasProximas.size} cuenta(s) vencerán en los próximos 15 días.",
                            fontSize = 12.sp,
                            color = AlkiraWarningAmber
                        )
                    }

                    HorizontalDivider(
                        color = AlkiraBorderSubtle
                    )

                    Text(
                        text = if (ultimoPago == null) {
                            "Aún no tienes pagos aprobados."
                        } else {
                            "Último pago aprobado: ${
                                formatoDinero(ultimoPago.monto)
                            }"
                        },
                        fontSize = 13.sp,
                        color = AlkiraTextSecondary
                    )

                    if (ultimoPago != null) {
                        Text(
                            text = "Fecha: ${
                                fechaCorta(ultimoPago.fechaPago)
                            }",
                            fontSize = 12.sp,
                            color = AlkiraTextSecondary
                        )
                    }
                }
            }

            // Gráfica de pagos
            GraficaKdd(
                pagos = pagosAprobados
            )

            // Información adicional KDD
            KddConsumoAsistenciaSection()

            // Historial
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
                    Text(
                        text = "Mi historial de pagos",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraDarkNavy
                    )

                    if (informacion.pagos.isEmpty()) {
                        Text(
                            text = "Todavía no tienes pagos registrados.",
                            fontSize = 13.sp,
                            color = AlkiraTextSecondary
                        )
                    } else {
                        val pagosVisibles = if (mostrarTodos) {
                            informacion.pagos
                        } else {
                            informacion.pagos.take(4)
                        }

                        pagosVisibles.forEachIndexed { indice, pago ->
                            if (indice > 0) {
                                HorizontalDivider(
                                    color = AlkiraBorderSubtle
                                )
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text(
                                        text = pago.concepto,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = AlkiraTextPrimary
                                    )

                                    Text(
                                        text = "${pago.periodo} · ${
                                            fechaCorta(pago.fechaPago)
                                        }",
                                        fontSize = 11.sp,
                                        color = AlkiraTextSecondary
                                    )

                                    Text(
                                        text = "Estado: ${pago.estado}",
                                        fontSize = 11.sp,
                                        color = AlkiraTextSecondary
                                    )
                                }

                                Text(
                                    text = formatoDinero(pago.monto),
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = AlkiraDarkNavy
                                )
                            }
                        }

                        if (informacion.pagos.size > 4) {
                            TextButton(
                                onClick = {
                                    mostrarTodos = !mostrarTodos
                                }
                            ) {
                                Text(
                                    if (mostrarTodos) {
                                        "Mostrar menos"
                                    } else {
                                        "Ver todos los pagos"
                                    }
                                )
                            }
                        }
                    }
                }
            }

            // Actualizar
            OutlinedButton(
                onClick = {
                    intentos++
                },
                modifier = Modifier.fillMaxWidth()
            ) {
                Text("Actualizar información")
            }

            Text(
                text = "Se muestran únicamente los datos del usuario autenticado. La gráfica incluye solo pagos aprobados.",
                fontSize = 11.sp,
                color = AlkiraTextSecondary
            )
        }
    }
}

@Composable
private fun TarjetaKdd(
    titulo: String,
    valor: String,
    color: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.border(
            width = 1.dp,
            color = AlkiraBorderSubtle,
            shape = RoundedCornerShape(14.dp)
        ),
        colors = CardDefaults.cardColors(
            containerColor = AlkiraSurfaceWhite
        ),
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text(
                text = titulo,
                fontSize = 11.sp,
                color = AlkiraTextSecondary
            )

            Text(
                text = valor,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = color
            )
        }
    }
}

@Composable
private fun GraficaKdd(
    pagos: List<PagoRegistrado>
) {
    val pagosPorPeriodo = pagos
        .filter {
            Regex(
                "^\\d{4}-(0[1-9]|1[0-2])$"
            ).matches(it.periodo)
        }
        .groupBy { it.periodo }
        .mapValues { (_, lista) ->
            lista.fold(BigDecimal.ZERO) { total, pago ->
                total.add(pago.monto)
            }
        }

    val periodos = pagosPorPeriodo.keys
        .sorted()
        .takeLast(6)

    val maximo = periodos.maxOfOrNull {
        pagosPorPeriodo.getValue(it)
    } ?: BigDecimal.ZERO

    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = AlkiraSurfaceWhite
        ),
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Text(
                text = "Pagos aprobados por periodo",
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = AlkiraDarkNavy
            )

            if (periodos.isEmpty()) {
                Text(
                    text = "No hay pagos aprobados para mostrar.",
                    fontSize = 13.sp,
                    color = AlkiraTextSecondary
                )
            } else {
                periodos.forEach { periodo ->

                    val monto = pagosPorPeriodo.getValue(
                        periodo
                    )

                    val proporcion = if (
                        maximo > BigDecimal.ZERO
                    ) {
                        monto.divide(
                            maximo,
                            4,
                            RoundingMode.HALF_UP
                        ).toFloat().coerceIn(0f, 1f)
                    } else {
                        0f
                    }

                    Column(
                        verticalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = periodo,
                                fontSize = 12.sp,
                                color = AlkiraTextSecondary
                            )

                            Text(
                                text = formatoDinero(monto),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = AlkiraDarkNavy
                            )
                        }

                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(12.dp)
                                .background(
                                    AlkiraBorderSubtle,
                                    RoundedCornerShape(6.dp)
                                )
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth(proporcion)
                                    .fillMaxHeight()
                                    .background(
                                        AlkiraPrimaryBlue,
                                        RoundedCornerShape(6.dp)
                                    )
                            )
                        }
                    }
                }
            }

            Text(
                text = "Los pagos están agrupados por mes de facturación, no por fecha del depósito.",
                fontSize = 11.sp,
                color = AlkiraTextSecondary
            )
        }
    }
}
