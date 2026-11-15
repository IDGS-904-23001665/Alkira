
package com.alkira.movil.screens

import android.content.ActivityNotFoundException
import android.content.Intent
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalInspectionMode
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.HistorialContratosApi
import com.alkira.movil.red.PagoRegistrado
import com.alkira.movil.red.RecibosMovilApi
import com.alkira.movil.ui.theme.PantallasMovilTheme
import kotlinx.coroutines.launch
import java.math.BigDecimal
import java.text.NumberFormat
import java.util.Locale

private val bgMain = Color(0xFFF8FAFC)
private val bgCard = Color(0xFFEDF2F7)
private val borderCard = Color(0xFFCBD5E1)
private val textPrimary = Color(0xFF0F172A)
private val textSecondary = Color(0xFF475569)
private val ctaTerracotta = Color(0xFFC85233)

@Composable
fun HU04_Screen(modifier: Modifier = Modifier) {

    val contexto = LocalContext.current
    val alcance = rememberCoroutineScope()
    val vistaPrevia = LocalInspectionMode.current

    var pagos by remember {
        mutableStateOf<List<PagoRegistrado>>(emptyList())
    }

    var cargando by remember {
        mutableStateOf(false)
    }

    var descargando by remember {
        mutableStateOf<Int?>(null)
    }

    var error by remember {
        mutableStateOf<String?>(null)
    }

    var solicitud by remember {
        mutableIntStateOf(0)
    }

    // Consultar pagos del inquilino.
    LaunchedEffect(solicitud) {

        if (vistaPrevia) return@LaunchedEffect

        cargando = true
        error = null

        try {
            pagos = HistorialContratosApi.consultarPagos()

        } catch (excepcion: Exception) {

            error = if (
                excepcion is ErrorApi &&
                excepcion.codigoHttp == 401
            ) {
                "Tu sesión venció. Vuelve a iniciar sesión."
            } else {
                excepcion.message
                    ?: "No se pudo consultar el historial."
            }

        } finally {
            cargando = false
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(bgMain)
            .padding(18.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {

        // Encabezado
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {

            Column(
                modifier = Modifier.weight(1f)
            ) {

                Text(
                    text = "Mis pagos y recibos",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )

                Text(
                    text = "Consulta el resultado de tus comprobantes",
                    fontSize = 12.sp,
                    color = textSecondary
                )
            }

            TextButton(
                onClick = {
                    solicitud += 1
                },
                enabled = !cargando
            ) {
                Text("Actualizar")
            }
        }

        if (cargando) {
            LinearProgressIndicator(
                modifier = Modifier.fillMaxWidth()
            )
        }

        // Mensajes de error
        error?.let { mensaje ->

            Card(
                colors = CardDefaults.cardColors(
                    containerColor = Color(0xFFFEE2E2)
                )
            ) {

                Text(
                    text = mensaje,
                    color = Color(0xFF991B1B),
                    modifier = Modifier.padding(12.dp)
                )
            }
        }

        // Sin registros
        if (
            !cargando &&
            pagos.isEmpty() &&
            error == null
        ) {

            Text(
                text = "Aún no hay pagos registrados.",
                color = textSecondary
            )
        }

        // Lista de pagos
        LazyColumn(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            contentPadding = PaddingValues(bottom = 20.dp)
        ) {

            items(
                items = pagos,
                key = { it.idPago }
            ) { pago ->

                val aprobado = pago.estado.equals(
                    "aprobado",
                    ignoreCase = true
                )

                val rechazado = pago.estado.equals(
                    "rechazado",
                    ignoreCase = true
                )

                val etiqueta = when (
                    pago.estado.lowercase(Locale.ROOT)
                ) {

                    "aprobado" -> "Aprobado"

                    "rechazado" -> "Rechazado"

                    "en_revision" -> "En verificación"

                    "preregistrado" -> "Pendiente de preregistro"

                    else -> pago.estado.replace('_', ' ')
                }

                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = bgCard
                    ),
                    shape = RoundedCornerShape(16.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(
                            1.dp,
                            borderCard,
                            RoundedCornerShape(16.dp)
                        )
                ) {

                    Column(
                        modifier = Modifier.padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(9.dp)
                    ) {

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {

                            Text(
                                text = "Pago #${pago.idPago}",
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )

                            Surface(
                                color = when {
                                    aprobado -> Color(0xFFD1FAE5)
                                    rechazado -> Color(0xFFFEE2E2)
                                    else -> Color(0xFFFEF3C7)
                                },
                                shape = RoundedCornerShape(8.dp)
                            ) {

                                Text(
                                    text = etiqueta,
                                    fontSize = 11.sp,
                                    color = when {
                                        aprobado -> Color(0xFF065F46)
                                        rechazado -> Color(0xFF991B1B)
                                        else -> Color(0xFF92400E)
                                    },
                                    modifier = Modifier.padding(
                                        horizontal = 9.dp,
                                        vertical = 5.dp
                                    )
                                )
                            }
                        }

                        Text(
                            text = "${pago.concepto} · ${pago.periodo}",
                            fontSize = 12.sp,
                            color = textSecondary
                        )

                        Text(
                            text = formatoMonto(pago.monto),
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )

                        Text(
                            text = "Unidad: ${pago.numeroUnidad}",
                            fontSize = 12.sp,
                            color = textSecondary
                        )

                        Text(
                            text = "Referencia: ${
                                pago.referenciaBancaria
                                    ?: "No registrada"
                            }",
                            fontSize = 12.sp,
                            color = textSecondary
                        )

                        HorizontalDivider(
                            color = borderCard
                        )

                        // Recibo disponible únicamente al aprobar.
                        if (aprobado) {

                            Button(
                                onClick = {

                                    descargando = pago.idPago
                                    error = null

                                    alcance.launch {

                                        try {

                                            val archivo =
                                                RecibosMovilApi.descargar(
                                                    contexto,
                                                    pago.idPago
                                                )

                                            val uri =
                                                FileProvider.getUriForFile(
                                                    contexto,
                                                    "${contexto.packageName}.archivos",
                                                    archivo
                                                )

                                            val intento =
                                                Intent(Intent.ACTION_VIEW).apply {

                                                    setDataAndType(
                                                        uri,
                                                        "application/pdf"
                                                    )

                                                    addFlags(
                                                        Intent.FLAG_GRANT_READ_URI_PERMISSION
                                                    )
                                                }

                                            contexto.startActivity(
                                                Intent.createChooser(
                                                    intento,
                                                    "Abrir recibo ALKIRA"
                                                )
                                            )

                                        } catch (
                                            excepcion: ActivityNotFoundException
                                        ) {

                                            error =
                                                "Instala una aplicación para abrir archivos PDF."

                                        } catch (excepcion: Exception) {

                                            error = excepcion.message
                                                ?: "No fue posible descargar el recibo."

                                        } finally {
                                            descargando = null
                                        }
                                    }
                                },
                                enabled = descargando == null,
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = ctaTerracotta
                                ),
                                shape = RoundedCornerShape(12.dp)
                            ) {

                                Text(
                                    text = if (
                                        descargando == pago.idPago
                                    ) {
                                        "Descargando..."
                                    } else {
                                        "Descargar recibo PDF"
                                    },
                                    color = Color.White,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }

                        } else {

                            Text(
                                text = if (rechazado) {
                                    "Pago rechazado. Comunícate con administración para conocer los detalles."
                                } else {
                                    "El comprobante todavía está en proceso de revisión."
                                },
                                fontSize = 12.sp,
                                color = textSecondary
                            )
                        }
                    }
                }
            }
        }
    }
}

private fun formatoMonto(monto: BigDecimal): String {
    return NumberFormat.getCurrencyInstance(
        Locale.forLanguageTag("es-MX")
    ).format(monto) + " MXN"
}

@Preview(showBackground = true)
@Composable
fun HU04_Preview() {
    PantallasMovilTheme {
        HU04_Screen()
    }
}
