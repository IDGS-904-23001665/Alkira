package com.alkira.movil.screens

import android.graphics.Bitmap
import android.graphics.Color as ColorAndroid
import android.graphics.pdf.PdfRenderer
import android.os.ParcelFileDescriptor
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalInspectionMode
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.alkira.movil.red.ContratoInquilino
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.HistorialContratosApi
import com.alkira.movil.red.PagoRegistrado
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.io.IOException
import java.math.BigDecimal
import java.text.NumberFormat
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale
import kotlin.math.roundToInt

private val COLOR_TEXTO = Color(0xFF0F172A)
private val COLOR_SECUNDARIO = Color(0xFF475569)
private val COLOR_BORDE = Color(0xFFCBD5E1)
private val COLOR_AZUL = Color(0xFF2563EB)

private enum class FiltroHistorial(val titulo: String) {
    TODOS("Todos"),
    APROBADOS("Aprobados"),
    REVISION("En revisión"),
    RECHAZADOS("Rechazados")
}

@Composable
fun HistorialContratosSeccion(numeroConsulta: Int) {
    var historial by remember { mutableStateOf<List<PagoRegistrado>>(emptyList()) }
    var contratos by remember { mutableStateOf<List<ContratoInquilino>>(emptyList()) }
    var cargando by remember { mutableStateOf(true) }
    var mensajeError by remember { mutableStateOf<String?>(null) }
    var filtro by remember { mutableStateOf(FiltroHistorial.TODOS) }
    var contratoDescargando by remember { mutableStateOf<Int?>(null) }
    var archivoPdf by remember { mutableStateOf<File?>(null) }
    var mensajePdf by remember { mutableStateOf<String?>(null) }
    val contexto = LocalContext.current
    val alcance = rememberCoroutineScope()
    val vistaPrevia = LocalInspectionMode.current

    LaunchedEffect(numeroConsulta) {
        if (vistaPrevia) {
            cargando = false
            return@LaunchedEffect
        }
        cargando = true
        mensajeError = null
        try {
            historial = HistorialContratosApi.consultarPagos()
            contratos = HistorialContratosApi.consultarContratos()
        } catch (error: ErrorApi) {
            mensajeError = when (error.codigoHttp) {
                401 -> "Tu sesión venció. Cierra sesión e inicia nuevamente."
                403 -> "Tu usuario no tiene permisos para consultar esta información."
                else -> error.message ?: "No se pudo consultar tu historial."
            }
        } catch (_: IOException) {
            mensajeError = "No fue posible consultar tus pagos y contratos. Revisa tu conexión."
        } finally {
            cargando = false
        }
    }

    DisposableEffect(Unit) {
        onDispose { archivoPdf?.delete() }
    }

    HorizontalDivider(color = COLOR_BORDE)
    Text("Historial de pagos", fontWeight = FontWeight.Bold, color = COLOR_TEXTO, fontSize = 17.sp)

    if (cargando) {
        Box(Modifier.fillMaxWidth().padding(20.dp), contentAlignment = Alignment.Center) {
            CircularProgressIndicator(color = COLOR_AZUL)
        }
    }

    mensajeError?.let {
        Text(it, color = Color(0xFF9F1239), fontSize = 13.sp)
    }

    if (!cargando && mensajeError == null) {
        Row(
            modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            FiltroHistorial.entries.forEach { opcion ->
                FilterChip(
                    selected = filtro == opcion,
                    onClick = { filtro = opcion },
                    label = { Text(opcion.titulo) }
                )
            }
        }

        val pagosFiltrados = historial.filter { pago ->
            when (filtro) {
                FiltroHistorial.TODOS -> true
                FiltroHistorial.APROBADOS -> pago.estado == "aprobado"
                FiltroHistorial.REVISION -> pago.estado in listOf("en_revision", "preregistrado")
                FiltroHistorial.RECHAZADOS -> pago.estado == "rechazado"
            }
        }

        if (pagosFiltrados.isEmpty()) {
            Text("No hay pagos para este filtro.", fontSize = 13.sp, color = COLOR_SECUNDARIO)
        }
        pagosFiltrados.forEach { pago -> TarjetaPagoRegistrado(pago) }
    }

    HorizontalDivider(color = COLOR_BORDE)
    Text("Mis contratos", fontWeight = FontWeight.Bold, color = COLOR_TEXTO, fontSize = 17.sp)
    mensajePdf?.let { Text(it, color = Color(0xFF9F1239), fontSize = 13.sp) }

    if (!cargando && mensajeError == null && contratos.isEmpty()) {
        Text("Aún no tienes contratos registrados.", color = COLOR_SECUNDARIO)
    }

    if (!cargando && mensajeError == null) {
        contratos.forEach { contrato ->
            Card(
                modifier = Modifier.fillMaxWidth().border(1.dp, COLOR_BORDE, RoundedCornerShape(14.dp)),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(7.dp)) {
                    Text("Unidad ${contrato.numeroUnidad}", color = COLOR_TEXTO, fontWeight = FontWeight.Bold)
                    Text("Estado: ${contrato.estadoContrato}", color = COLOR_SECUNDARIO, fontSize = 13.sp)
                    Text("Inicio: ${mostrarFechaContrato(contrato.fechaInicio)}", color = COLOR_SECUNDARIO)
                    Text(
                        "Fin: ${contrato.fechaFin?.let(::mostrarFechaContrato) ?: "Sin fecha definida"}",
                        color = COLOR_SECUNDARIO
                    )
                    Text("Renta: ${mostrarMontoContrato(contrato.montoRenta)} MXN", color = COLOR_TEXTO)
                    Button(
                        onClick = {
                            mensajePdf = null
                            contratoDescargando = contrato.idContrato
                            alcance.launch {
                                try {
                                    val archivo = HistorialContratosApi.descargarContrato(contexto, contrato.idContrato)
                                    archivoPdf?.delete()
                                    archivoPdf = archivo
                                } catch (error: ErrorApi) {
                                    mensajePdf = error.message ?: "No se pudo abrir el contrato."
                                } catch (_: IOException) {
                                    mensajePdf = "No fue posible descargar el contrato PDF."
                                } finally {
                                    contratoDescargando = null
                                }
                            }
                        },
                        enabled = contrato.documentoDisponible && contratoDescargando == null,
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = COLOR_AZUL)
                    ) {
                        if (contratoDescargando == contrato.idContrato) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                color = Color.White,
                                strokeWidth = 2.dp
                            )
                        } else {
                            Text(if (contrato.documentoDisponible) "Ver contrato PDF" else "Contrato PDF no disponible")
                        }
                    }
                }
            }
        }
    }

    archivoPdf?.let { documento ->
        VisorContratoPdf(
            archivo = documento,
            onCerrar = {
                archivoPdf = null
                documento.delete()
            }
        )
    }
}

@Composable
private fun TarjetaPagoRegistrado(pago: PagoRegistrado) {
    Card(
        modifier = Modifier.fillMaxWidth().border(1.dp, COLOR_BORDE, RoundedCornerShape(14.dp)),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(7.dp)) {
            Text(pago.concepto, fontWeight = FontWeight.Bold, color = COLOR_TEXTO)
            Text("Unidad ${pago.numeroUnidad} · Periodo ${pago.periodo}", color = COLOR_SECUNDARIO, fontSize = 12.sp)
            Text("Monto: ${mostrarMontoContrato(pago.monto)} MXN", color = COLOR_TEXTO)
            Text("Fecha: ${pago.fechaPago?.let(::mostrarFechaContrato) ?: "Pendiente de registro"}", color = COLOR_SECUNDARIO)
            Text("Método: ${pago.metodoPago}", color = COLOR_SECUNDARIO)
            Text("Estado: ${nombreEstadoPago(pago.estado)}", fontWeight = FontWeight.SemiBold, color =
                if (pago.estado == "aprobado") Color(0xFF166534) else COLOR_SECUNDARIO)
            pago.referenciaBancaria?.let {
                Text("Referencia: $it", color = COLOR_SECUNDARIO, fontSize = 12.sp)
            }
        }
    }
}

private data class PaginaContrato(val imagen: ImageBitmap, val totalPaginas: Int)

@Composable
private fun VisorContratoPdf(archivo: File, onCerrar: () -> Unit) {
    var paginaActual by remember(archivo) { mutableIntStateOf(0) }
    var paginaRenderizada by remember(archivo) { mutableStateOf<PaginaContrato?>(null) }
    var errorPdf by remember(archivo) { mutableStateOf<String?>(null) }

    LaunchedEffect(archivo, paginaActual) {
        paginaRenderizada = null
        errorPdf = null
        try {
            paginaRenderizada = withContext(Dispatchers.IO) {
                ParcelFileDescriptor.open(archivo, ParcelFileDescriptor.MODE_READ_ONLY).use { descriptor ->
                    PdfRenderer(descriptor).use { pdf ->
                        if (pdf.pageCount == 0) throw IOException("El PDF no contiene páginas.")
                        pdf.openPage(paginaActual).use { pagina ->
                            val ancho = (pagina.width * 2).coerceIn(700, 1300)
                            val alto = (pagina.height.toFloat() * ancho / pagina.width)
                                .roundToInt().coerceIn(1, 2000)
                            val imagen = Bitmap.createBitmap(ancho, alto, Bitmap.Config.ARGB_8888)
                            imagen.eraseColor(ColorAndroid.WHITE)
                            pagina.render(imagen, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
                            PaginaContrato(imagen.asImageBitmap(), pdf.pageCount)
                        }
                    }
                }
            }
        } catch (_: Exception) {
            errorPdf = "No se pudo mostrar este documento PDF."
        }
    }

    Dialog(onDismissRequest = onCerrar, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Surface(
            modifier = Modifier.fillMaxWidth().fillMaxHeight(0.94f).padding(8.dp),
            shape = RoundedCornerShape(14.dp),
            color = Color.White
        ) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Contrato digital", fontWeight = FontWeight.Bold, color = COLOR_TEXTO, modifier = Modifier.weight(1f))
                    TextButton(onClick = onCerrar) { Text("Cerrar") }
                }
                Box(Modifier.weight(1f).fillMaxWidth().background(Color(0xFFF1F5F9)), contentAlignment = Alignment.Center) {
                    when {
                        errorPdf != null -> Text(errorPdf!!, color = Color(0xFF9F1239))
                        paginaRenderizada == null -> CircularProgressIndicator(color = COLOR_AZUL)
                        else -> Image(
                            bitmap = paginaRenderizada!!.imagen,
                            contentDescription = "Página ${paginaActual + 1} del contrato",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier.fillMaxSize()
                        )
                    }
                }
                val total = paginaRenderizada?.totalPaginas ?: 1
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TextButton(onClick = { paginaActual-- }, enabled = paginaActual > 0 && paginaRenderizada != null) {
                        Text("Anterior")
                    }
                    Text("${paginaActual + 1} / $total", color = COLOR_SECUNDARIO)
                    TextButton(onClick = { paginaActual++ }, enabled = paginaActual < total - 1 && paginaRenderizada != null) {
                        Text("Siguiente")
                    }
                }
            }
        }
    }
}

private fun nombreEstadoPago(estado: String): String = when (estado) {
    "preregistrado" -> "Preregistrado"
    "en_revision" -> "En revisión"
    "aprobado" -> "Aprobado"
    "rechazado" -> "Rechazado"
    else -> estado
}

private fun mostrarMontoContrato(monto: BigDecimal): String =
    NumberFormat.getNumberInstance(Locale("es", "MX")).apply {
        minimumFractionDigits = 2
        maximumFractionDigits = 2
    }.format(monto).let { "\$$it" }

private fun mostrarFechaContrato(texto: String): String = try {
    val fecha = if (texto.contains(' ')) LocalDateTime.parse(texto.replace(' ', 'T')).toLocalDate()
    else LocalDate.parse(texto)
    fecha.format(DateTimeFormatter.ofPattern("dd/MMM/yyyy", Locale("es", "MX")))
} catch (_: Exception) {
    texto
}
