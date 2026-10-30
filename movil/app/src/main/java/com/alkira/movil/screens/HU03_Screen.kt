package com.alkira.movil.screens

import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AttachFile
import androidx.compose.material.icons.outlined.CheckCircleOutline
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.AdeudoComprobante
import com.alkira.movil.red.ComprobantesApi
import com.alkira.movil.ui.theme.*
import kotlinx.coroutines.launch

private const val LIMITE_ARCHIVO_BYTES = 5L * 1024L * 1024L

private data class ArchivoSeleccionado(
    val nombre: String,
    val tamanoBytes: Long,
    val tipoMime: String
)

@Composable
fun HU03_Screen(modifier: Modifier = Modifier) {
    val contexto = LocalContext.current
    val alcance = rememberCoroutineScope()
    var concepto by rememberSaveable { mutableStateOf("") }
    var monto by rememberSaveable { mutableStateOf("") }
    var referencia by rememberSaveable { mutableStateOf("") }
    var archivoUri by remember { mutableStateOf<Uri?>(null) }
    var archivo by remember { mutableStateOf<ArchivoSeleccionado?>(null) }
    var mensaje by rememberSaveable { mutableStateOf("") }
    var adeudos by remember { mutableStateOf<List<AdeudoComprobante>>(emptyList()) }
    var seleccionado by rememberSaveable { mutableIntStateOf(0) }
    var cargandoAdeudos by remember { mutableStateOf(false) }
    var enviando by remember { mutableStateOf(false) }

    fun cargarAdeudos() {
        alcance.launch {
            cargandoAdeudos = true
            try {
                adeudos = ComprobantesApi.adeudos()
                if (adeudos.none { it.idEstadoCuenta == seleccionado }) seleccionado = 0
                if (adeudos.isEmpty()) {
                    mensaje = "No encontramos adeudos vinculados a tu contrato vigente. Solicita al administrador que los registre."
                }
            } catch (error: Exception) {
                mensaje = error.message ?: "No se pudieron cargar los adeudos."
            } finally {
                cargandoAdeudos = false
            }
        }
    }

    LaunchedEffect(Unit) { cargarAdeudos() }

    val selectorDocumento = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenDocument()
    ) { uri ->
        if (uri != null) {
            try {
                val resolver = contexto.contentResolver
                val tipo = resolver.getType(uri).orEmpty().lowercase()
                if (tipo !in setOf("application/pdf", "image/jpeg", "image/png")) {
                    archivoUri = null
                    archivo = null
                    mensaje = "Selecciona un archivo PDF, JPG o PNG."
                } else {
                    var nombre = "Comprobante"
                    var tamano = -1L
                    resolver.query(
                        uri,
                        arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE),
                        null, null, null
                    )?.use { cursor ->
                        if (cursor.moveToFirst()) {
                            val iNombre = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                            val iTamano = cursor.getColumnIndex(OpenableColumns.SIZE)
                            if (iNombre >= 0 && !cursor.isNull(iNombre)) nombre = cursor.getString(iNombre)
                            if (iTamano >= 0 && !cursor.isNull(iTamano)) tamano = cursor.getLong(iTamano)
                        }
                    }
                    when {
                        tamano < 0L -> mensaje = "No se pudo verificar el tamaño del archivo."
                        tamano > LIMITE_ARCHIVO_BYTES -> mensaje = "El comprobante supera los 5 MB."
                        tamano == 0L -> mensaje = "El comprobante está vacío."
                        else -> {
                            archivoUri = uri
                            archivo = ArchivoSeleccionado(nombre, tamano, tipo)
                            mensaje = "Archivo seleccionado correctamente."
                        }
                    }
                    if (tamano < 1 || tamano > LIMITE_ARCHIVO_BYTES) {
                        archivoUri = null
                        archivo = null
                    }
                }
            } catch (_: Exception) {
                archivoUri = null
                archivo = null
                mensaje = "No se pudo leer el archivo."
            }
        }
    }

    // Diseño alineado con Incidencias, Estado de cuenta y Pases QR.
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp, vertical = 18.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Registrar pago",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Sube tu comprobante para revisión administrativa",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(top = 4.dp)
                )
            }
            Surface(color = AlkiraBlueSoft, shape = RoundedCornerShape(20.dp)) {
                Text(
                    text = "HU03.1",
                    color = AlkiraBlueBadgeText,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                )
            }
        }

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            border = BorderStroke(1.dp, AlkiraBorderSubtle)
        ) {
            Column(
                modifier = Modifier.fillMaxWidth().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.ReceiptLong, contentDescription = null,
                        tint = AlkiraPrimaryBlue, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("1. Adeudo a pagar", fontWeight = FontWeight.Bold,
                        fontSize = 15.sp, color = AlkiraTextPrimary)
                }
                Text("Selecciona un adeudo de tu contrato vigente.",
                    fontSize = 12.sp, color = AlkiraTextSecondary)

                if (cargandoAdeudos) {
                    LinearProgressIndicator(
                        modifier = Modifier.fillMaxWidth(), color = AlkiraPrimaryBlue
                    )
                } else if (adeudos.isEmpty()) {
                    Surface(color = AlkiraCanvasBg, shape = RoundedCornerShape(10.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Outlined.Info, contentDescription = null,
                                tint = AlkiraTextSecondary, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("No hay adeudos disponibles.", fontSize = 12.sp,
                                color = AlkiraTextSecondary)
                        }
                    }
                }

                adeudos.forEach { deuda ->
                    val esSeleccionado = seleccionado == deuda.idEstadoCuenta
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable(enabled = !enviando) {
                                seleccionado = deuda.idEstadoCuenta
                                concepto = "${deuda.concepto} ${deuda.periodo}"
                                mensaje = ""
                            },
                        shape = RoundedCornerShape(12.dp),
                        color = if (esSeleccionado) AlkiraBlueSoft else AlkiraCanvasBg,
                        border = BorderStroke(
                            1.dp,
                            if (esSeleccionado) AlkiraPrimaryBlue else AlkiraBorderSubtle
                        )
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            RadioButton(
                                selected = esSeleccionado,
                                onClick = {
                                    seleccionado = deuda.idEstadoCuenta
                                    concepto = "${deuda.concepto} ${deuda.periodo}"
                                    mensaje = ""
                                },
                                enabled = !enviando,
                                colors = RadioButtonDefaults.colors(selectedColor = AlkiraPrimaryBlue)
                            )
                            Spacer(Modifier.width(6.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text("${deuda.concepto} · ${deuda.periodo}",
                                    fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                                    color = AlkiraTextPrimary)
                                Text("Saldo pendiente: \$${deuda.saldo} MXN",
                                    fontSize = 12.sp, color = AlkiraTextSecondary,
                                    modifier = Modifier.padding(top = 3.dp))
                            }
                        }
                    }
                }
                OutlinedButton(
                    onClick = { cargarAdeudos() },
                    enabled = !cargandoAdeudos && !enviando,
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = AlkiraPrimaryBlue),
                    border = BorderStroke(1.dp, AlkiraBorderStrong)
                ) {
                    Text("Actualizar adeudos", fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold)
                }
            }
        }

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            border = BorderStroke(1.dp, AlkiraBorderSubtle)
        ) {
            Column(
                modifier = Modifier.fillMaxWidth().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.AttachFile, contentDescription = null,
                        tint = AlkiraPrimaryBlue, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("2. Comprobante de transferencia",
                        fontSize = 15.sp, fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary)
                }
                Surface(
                    color = AlkiraBlueSoft,
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, AlkiraBorderSubtle),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(
                        modifier = Modifier.fillMaxWidth().padding(18.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Icon(Icons.Outlined.AttachFile, contentDescription = null,
                            tint = AlkiraPrimaryBlue, modifier = Modifier.size(30.dp))
                        Text("PDF, JPG o PNG · máximo 5 MB",
                            fontSize = 12.sp, color = AlkiraTextSecondary)
                        Button(
                            onClick = {
                                selectorDocumento.launch(
                                    arrayOf("application/pdf", "image/jpeg", "image/png")
                                )
                            },
                            enabled = !enviando,
                            shape = RoundedCornerShape(10.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = AlkiraPrimaryBlue,
                                contentColor = Color.White
                            )
                        ) {
                            Text(if (archivo == null) "Seleccionar comprobante" else "Cambiar archivo",
                                fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
                archivo?.let { seleccionadoArchivo ->
                    Row(verticalAlignment = Alignment.Top) {
                        Icon(Icons.Outlined.CheckCircleOutline, contentDescription = null,
                            tint = AlkiraSuccessGreen, modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(8.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(seleccionadoArchivo.nombre, fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AlkiraTextPrimary)
                            Text("${"%.2f".format(seleccionadoArchivo.tamanoBytes / (1024.0 * 1024.0))} MB · Archivo listo",
                                fontSize = 11.sp, color = AlkiraGreenText)
                        }
                    }
                }
            }
        }

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFFEDF2F7)),
            border = BorderStroke(1.dp, AlkiraBorderStrong)
        ) {
            Column(
                modifier = Modifier.fillMaxWidth().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text("3. Datos de la transferencia", fontSize = 15.sp,
                    fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                Text("Usa los datos de tu transferencia bancaria.",
                    fontSize = 12.sp, color = AlkiraTextSecondary)
                OutlinedTextField(
                    value = concepto,
                    onValueChange = { concepto = it; mensaje = "" },
                    label = { Text("Concepto del pago") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    enabled = !enviando,
                    shape = RoundedCornerShape(10.dp),
                    colors = coloresCampoHu03()
                )
                OutlinedTextField(
                    value = monto,
                    onValueChange = { monto = it; mensaje = "" },
                    label = { Text("Monto depositado (MXN)") },
                    modifier = Modifier.fillMaxWidth(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    singleLine = true,
                    enabled = !enviando,
                    shape = RoundedCornerShape(10.dp),
                    colors = coloresCampoHu03()
                )
                OutlinedTextField(
                    value = referencia,
                    onValueChange = { referencia = it; mensaje = "" },
                    label = { Text("Referencia bancaria") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    enabled = !enviando,
                    shape = RoundedCornerShape(10.dp),
                    colors = coloresCampoHu03()
                )
            }
        }

        Button(
            onClick = {
                val montoTexto = monto.trim()
                val montoValido = montoTexto.matches(Regex("[0-9]{1,8}(\\.[0-9]{1,2})?")) &&
                    (montoTexto.toDoubleOrNull() ?: 0.0) > 0.0
                val uri = archivoUri
                val documento = archivo
                when {
                    seleccionado <= 0 -> mensaje = "Selecciona un adeudo real de tu contrato."
                    uri == null || documento == null -> mensaje = "Selecciona primero un comprobante válido."
                    concepto.isBlank() || concepto.toByteArray().size > 300 -> mensaje = "Escribe un concepto de pago corto."
                    !montoValido -> mensaje = "Escribe un monto válido, por ejemplo 750.00."
                    referencia.isBlank() || referencia.trim().length > 100 -> mensaje = "Escribe una referencia bancaria de hasta 100 caracteres."
                    else -> alcance.launch {
                        enviando = true
                        mensaje = "Enviando comprobante..."
                        try {
                            val respuesta = ComprobantesApi.enviar(
                                contexto.contentResolver, uri, documento.tipoMime, seleccionado,
                                montoTexto, referencia.trim(), concepto.trim()
                            )
                            mensaje = respuesta
                            archivoUri = null
                            archivo = null
                            monto = ""
                            referencia = ""
                            cargarAdeudos()
                        } catch (error: Exception) {
                            mensaje = error.message ?: "No se pudo enviar el comprobante."
                        } finally {
                            enviando = false
                        }
                    }
                }
            },
            modifier = Modifier.fillMaxWidth().heightIn(min = 50.dp),
            enabled = !enviando && !cargandoAdeudos,
            colors = ButtonDefaults.buttonColors(
                containerColor = Color(0xFFC85233),
                contentColor = Color.White
            ),
            shape = RoundedCornerShape(12.dp)
        ) {
            if (enviando) {
                CircularProgressIndicator(
                    modifier = Modifier.size(18.dp),
                    color = Color.White,
                    strokeWidth = 2.dp
                )
                Spacer(Modifier.width(10.dp))
            }
            Text(if (enviando) "Enviando comprobante…" else "Enviar comprobante",
                fontWeight = FontWeight.SemiBold)
        }

        if (mensaje.isNotBlank()) {
            val confirmacion = mensaje.contains("Comprobante recibido", ignoreCase = true)
            Surface(
                color = if (confirmacion) AlkiraGreenSoft else AlkiraBlueSoft,
                shape = RoundedCornerShape(10.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.Top
                ) {
                    Icon(
                        if (confirmacion) Icons.Outlined.CheckCircleOutline else Icons.Outlined.Info,
                        contentDescription = null,
                        tint = if (confirmacion) AlkiraGreenText else AlkiraBlueBadgeText,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(mensaje, fontSize = 12.sp,
                        color = if (confirmacion) AlkiraGreenText else AlkiraTextPrimary)
                }
            }
        }
        Text(
            "El envío queda preregistrado y pendiente de revisión. No equivale a un pago aprobado.",
            fontSize = 11.sp, color = AlkiraTextSecondary,
            modifier = Modifier.padding(bottom = 8.dp)
        )
    }
}

@Preview(showBackground = true, showSystemUi = true)
@Composable
fun HU03_Preview() {
    PantallasMovilTheme { HU03_Screen() }
}

@Composable
private fun coloresCampoHu03() = OutlinedTextFieldDefaults.colors(
    focusedContainerColor = AlkiraSurfaceWhite,
    unfocusedContainerColor = AlkiraSurfaceWhite,
    disabledContainerColor = AlkiraSurfaceWhite,
    focusedTextColor = AlkiraTextPrimary,
    unfocusedTextColor = AlkiraTextPrimary,
    focusedBorderColor = AlkiraPrimaryBlue,
    unfocusedBorderColor = AlkiraBorderStrong,
    focusedLabelColor = AlkiraPrimaryBlue,
    unfocusedLabelColor = AlkiraTextSecondary
)
