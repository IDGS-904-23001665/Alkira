package com.alkira.movil.screens

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.pm.ActivityInfo
import android.graphics.Bitmap
import android.graphics.Canvas as CanvasAndroid
import android.graphics.Color as ColorAndroid
import android.graphics.Paint
import android.graphics.Path
import android.util.Base64
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawing
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.TicketMantenimiento
import com.alkira.movil.red.TicketsApi
import com.alkira.movil.ui.theme.AlkiraBorderSubtle
import com.alkira.movil.ui.theme.AlkiraPrimaryBlue
import kotlinx.coroutines.launch
import java.io.ByteArrayOutputStream
import java.io.IOException
import kotlin.math.roundToInt

@Composable
fun FirmaConformidadDialog(
    ticket: TicketMantenimiento,
    onCerrar: () -> Unit,
    onFirmaRegistrada: (String) -> Unit
) {
    val actividad = LocalContext.current.buscarActividad()
    val trazos = remember(ticket.idTicket) { mutableStateListOf<List<Offset>>() }
    val trazoActual = remember(ticket.idTicket) { mutableStateListOf<Offset>() }

    // Cada punto se guarda normalizado entre 0 y 1, para que los trazos
    // no cambien de lugar al redimensionar el lienzo.
    var dimensionesFirma by remember(ticket.idTicket) { mutableStateOf(IntSize.Zero) }
    var capturandoFirma by remember(ticket.idTicket) { mutableStateOf(true) }
    var comentario by remember(ticket.idTicket) { mutableStateOf("") }
    var conformidadAceptada by remember(ticket.idTicket) { mutableStateOf(false) }
    var enviando by remember(ticket.idTicket) { mutableStateOf(false) }
    var mensajeError by remember(ticket.idTicket) { mutableStateOf<String?>(null) }
    val alcance = rememberCoroutineScope()

    // Conservamos la orientación previa y la restauramos al salir del flujo.
    // La Activity debe declarar configChanges para que girar no borre la firma.
    DisposableEffect(actividad) {
        val orientacionAnterior = actividad?.requestedOrientation
        onDispose {
            if (actividad != null && orientacionAnterior != null) {
                actividad.requestedOrientation = orientacionAnterior
            }
        }
    }

    LaunchedEffect(actividad, capturandoFirma) {
        actividad?.requestedOrientation = if (capturandoFirma) {
            ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE
        } else {
            ActivityInfo.SCREEN_ORIENTATION_PORTRAIT
        }
    }

    if (capturandoFirma) {
        Dialog(
            onDismissRequest = { if (!enviando) onCerrar() },
            properties = DialogProperties(usePlatformDefaultWidth = false)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .background(MaterialTheme.colorScheme.surface)
                    .windowInsetsPadding(WindowInsets.safeDrawing)
                    .padding(horizontal = 14.dp, vertical = 10.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "Firma de conformidad · Ticket #${ticket.idTicket}",
                        style = MaterialTheme.typography.titleMedium,
                        modifier = Modifier.weight(1f)
                    )
                    TextButton(onClick = onCerrar) { Text("Cancelar") }
                }

                Text(
                    "Firma con tu dedo dentro del recuadro blanco.",
                    style = MaterialTheme.typography.bodySmall
                )

                Canvas(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f)
                        .clip(RoundedCornerShape(12.dp))
                        .background(Color.White)
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(12.dp))
                        .onSizeChanged { dimensionesFirma = it }
                        .pointerInput(ticket.idTicket) {
                            detectDragGestures(
                                onDragStart = { posicion ->
                                    trazoActual.clear()
                                    trazoActual.add(normalizarPunto(posicion, size))
                                },
                                onDrag = { cambio, _ ->
                                    cambio.consume()
                                    trazoActual.add(normalizarPunto(cambio.position, size))
                                },
                                onDragEnd = {
                                    if (trazoActual.size >= 2) {
                                        trazos.add(trazoActual.toList())
                                    }
                                    trazoActual.clear()
                                },
                                onDragCancel = { trazoActual.clear() }
                            )
                        }
                ) {
                    dibujarTrazos(trazos, trazoActual, size.width, size.height)
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TextButton(
                        onClick = {
                            trazos.clear()
                            trazoActual.clear()
                            mensajeError = null
                        }
                    ) { Text("Limpiar firma") }

                    Button(
                        enabled = trazos.isNotEmpty(),
                        onClick = {
                            trazoActual.clear()
                            mensajeError = null
                            capturandoFirma = false
                        }
                    ) { Text("Aceptar firma") }
                }
            }
        }
    } else {
        AlertDialog(
            onDismissRequest = { if (!enviando) onCerrar() },
            title = { Text("Confirmar conformidad · Ticket #${ticket.idTicket}") },
            text = {
                Column(
                    modifier = Modifier.verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text("Revisa tu firma antes de enviarla.")

                    Canvas(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(108.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color.White)
                            .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(8.dp))
                    ) {
                        dibujarTrazos(trazos, emptyList(), size.width, size.height)
                    }

                    TextButton(
                        enabled = !enviando,
                        onClick = {
                            conformidadAceptada = false
                            mensajeError = null
                            capturandoFirma = true
                        }
                    ) { Text("Volver a firmar") }

                    OutlinedTextField(
                        value = comentario,
                        onValueChange = { if (it.length <= 500) comentario = it },
                        label = { Text("Comentario (opcional)") },
                        maxLines = 3,
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !enviando
                    )

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(
                            checked = conformidadAceptada,
                            onCheckedChange = { conformidadAceptada = it },
                            enabled = !enviando
                        )
                        Text(
                            "Confirmo que recibí el trabajo realizado.",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }

                    Text(
                        "Al enviar, el ticket pasará a evaluación. " +
                                "El cierre definitivo corresponde a administración.",
                        style = MaterialTheme.typography.bodySmall
                    )

                    mensajeError?.let {
                        Text(it, color = MaterialTheme.colorScheme.error)
                    }
                    if (enviando) {
                        CircularProgressIndicator()
                        Text("Guardando conformidad…")
                    }
                }
            },
            confirmButton = {
                Button(
                    enabled = !enviando && trazos.isNotEmpty() && conformidadAceptada,
                    onClick = {
                        enviando = true
                        mensajeError = null
                        alcance.launch {
                            try {
                                val imagen = convertirFirmaEnBase64(
                                    trazos.toList(), dimensionesFirma
                                )
                                val mensaje = TicketsApi.registrarFirma(
                                    ticket.idTicket, imagen, comentario
                                )
                                onFirmaRegistrada(mensaje)
                            } catch (error: ErrorApi) {
                                mensajeError = when (error.codigoHttp) {
                                    401 -> "Tu sesión venció. Inicia sesión nuevamente."
                                    403 -> "No tienes permisos para firmar este ticket."
                                    409 -> error.message ?: "El ticket ya no permite firmarse."
                                    else -> error.message ?: "No se pudo guardar la firma."
                                }
                            } catch (_: IOException) {
                                mensajeError = "No se pudo conectar con PHP o enviar la firma."
                            } catch (_: Exception) {
                                mensajeError = "No se pudo procesar la firma."
                            } finally {
                                enviando = false
                            }
                        }
                    }
                ) { Text("Confirmar y enviar") }
            },
            dismissButton = {
                TextButton(onClick = onCerrar, enabled = !enviando) {
                    Text("Cancelar")
                }
            }
        )
    }
}

private tailrec fun Context.buscarActividad(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.buscarActividad()
    else -> null
}

// También limita la coordenada aunque Android siga enviando eventos
// al arrastrar el dedo fuera del área que inició el gesto.
private fun normalizarPunto(posicion: Offset, tamanio: IntSize): Offset {
    if (tamanio.width <= 0 || tamanio.height <= 0) return Offset(0.5f, 0.5f)
    val margenX = (5f / tamanio.width).coerceAtMost(0.20f)
    val margenY = (5f / tamanio.height).coerceAtMost(0.20f)
    return Offset(
        (posicion.x / tamanio.width).coerceIn(margenX, 1f - margenX),
        (posicion.y / tamanio.height).coerceIn(margenY, 1f - margenY)
    )
}

// Ambas vistas dibujan exactamente los mismos puntos normalizados.
private fun androidx.compose.ui.graphics.drawscope.DrawScope.dibujarTrazos(
    trazos: List<List<Offset>>,
    trazoActual: List<Offset>,
    ancho: Float,
    alto: Float
) {
    (trazos + listOf(trazoActual)).forEach { trazo ->
        trazo.zipWithNext().forEach { (anterior, siguiente) ->
            drawLine(
                color = AlkiraPrimaryBlue,
                start = Offset(anterior.x * ancho, anterior.y * alto),
                end = Offset(siguiente.x * ancho, siguiente.y * alto),
                strokeWidth = 4.dp.toPx(),
                cap = StrokeCap.Round
            )
        }
    }
}

private fun convertirFirmaEnBase64(
    trazos: List<List<Offset>>,
    dimensionesFirma: IntSize
): String {
    if (trazos.isEmpty() || dimensionesFirma.width <= 0 || dimensionesFirma.height <= 0) {
        throw IllegalArgumentException("No hay una firma válida.")
    }

    val ancho = 960
    val alto = (ancho.toFloat() * dimensionesFirma.height / dimensionesFirma.width)
        .roundToInt().coerceIn(1, 960)
    val imagen = Bitmap.createBitmap(ancho, alto, Bitmap.Config.ARGB_8888)
    try {
        val lienzo = CanvasAndroid(imagen)
        lienzo.drawColor(ColorAndroid.WHITE)
        lienzo.clipRect(0f, 0f, ancho.toFloat(), alto.toFloat())

        val pincel = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = ColorAndroid.rgb(15, 100, 170)
            style = Paint.Style.STROKE
            strokeCap = Paint.Cap.ROUND
            strokeJoin = Paint.Join.ROUND
            strokeWidth = 5f
        }

        trazos.forEach { trazo ->
            if (trazo.size >= 2) {
                val ruta = Path()
                ruta.moveTo(trazo.first().x * ancho, trazo.first().y * alto)
                trazo.drop(1).forEach { punto ->
                    ruta.lineTo(punto.x * ancho, punto.y * alto)
                }
                lienzo.drawPath(ruta, pincel)
            }
        }

        val salida = ByteArrayOutputStream()
        if (!imagen.compress(Bitmap.CompressFormat.PNG, 100, salida)) {
            throw IOException("No se pudo generar la imagen de la firma.")
        }
        val bytes = salida.toByteArray()
        if (bytes.size > 550000) {
            throw IOException("La firma supera el tamaño permitido.")
        }
        return Base64.encodeToString(bytes, Base64.NO_WRAP)
    } finally {
        imagen.recycle()
    }
}
