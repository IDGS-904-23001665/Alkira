package com.alkira.movil.screens

import android.content.Intent
import android.graphics.Bitmap
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.alkira.movil.red.PaseResidente
import com.alkira.movil.red.PasesQrApi
import com.alkira.movil.ui.theme.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@Composable
fun PasesQrScreen(modifier: Modifier = Modifier, onNavigateBack: () -> Unit = {}) {
    var nombre by rememberSaveable { mutableStateOf("") }
    var horas by rememberSaveable { mutableIntStateOf(6) }
    var historial by rememberSaveable { mutableStateOf(false) }
    var lista by remember { mutableStateOf<List<PaseResidente>>(emptyList()) }
    var seleccionado by remember { mutableStateOf<PaseResidente?>(null) }
    var revocar by remember { mutableStateOf<PaseResidente?>(null) }
    var cargando by remember { mutableStateOf(true) }
    var procesando by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var mensaje by remember { mutableStateOf<String?>(null) }
    val contexto = LocalContext.current
    val teclado = LocalSoftwareKeyboardController.current
    val alcance = rememberCoroutineScope()
    val activo = lista.firstOrNull { it.estado == "vigente" }
    suspend fun actualizar() {
        cargando = true
        try { lista = PasesQrApi.listar(); error = null }
        catch (e: CancellationException) { throw e }
        catch (e: Exception) { error = e.message ?: "No se pudieron cargar tus pases." }
        finally { cargando = false }
    }
    fun compartir(pase: PaseResidente) {
        alcance.launch {
            try {
                val intent = withContext(Dispatchers.IO) { QrPase.prepararCompartir(contexto, pase) }
                contexto.startActivity(Intent.createChooser(intent, "Compartir pase QR"))
            } catch (e: CancellationException) { throw e }
            catch (e: Exception) { error = e.message ?: "No se pudo compartir el pase." }
        }
    }
    LaunchedEffect(Unit) { actualizar() }

    LazyColumn(modifier.fillMaxSize().background(AlkiraCanvasBg).padding(horizontal = 20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp), contentPadding = PaddingValues(vertical = 16.dp)) {
        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(if (historial) "Mis pases" else "Control de Accesos QR", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                    Text("Pases Temporales y Dinámicos", fontSize = 12.sp, color = AlkiraTextSecondary)
                }
                InsigniaPase(if (historial) "Nuevo pase" else "Mis pases", Modifier.clickable(enabled = !procesando && !cargando) { historial = !historial })
            }
        }
        if (error != null || mensaje != null || cargando) item {
            error?.let { Text(it, color = AlkiraDangerRed, fontSize = 12.sp) }
            mensaje?.let { Text(it, color = AlkiraPrimaryBlue, fontSize = 12.sp) }
            if (cargando) LinearProgressIndicator(Modifier.fillMaxWidth(), color = AlkiraPrimaryBlue)
        }
        if (historial) {
            item {
                TextButton(onClick = { alcance.launch { actualizar() } }, enabled = !procesando && !cargando,
                    colors = ButtonDefaults.textButtonColors(contentColor = AlkiraPrimaryBlue)) { Text("Actualizar") }
                if (!cargando && lista.isEmpty()) Text("Aún no tienes pases registrados.", color = AlkiraTextSecondary)
            }
            items(lista, key = { it.id }) { pase ->
                TarjetaPase {
                    Text(pase.visitante, fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                    Text("Estado: ${pase.estado}", color = AlkiraTextSecondary, fontSize = 12.sp)
                    Text("Hasta: ${pase.fin}", color = AlkiraTextSecondary, fontSize = 12.sp)
                    if (pase.estado == "vigente") Row {
                        TextButton(onClick = { seleccionado = pase }, enabled = !procesando) { Text("Ver QR", color = AlkiraPrimaryBlue) }
                        TextButton(onClick = { revocar = pase }, enabled = !procesando) { Text("Revocar", color = AlkiraDangerRed) }
                    }
                }
            }
        } else {
            item {
                TarjetaPase(centrar = true) {
                    Text("Pase de Acceso Activo", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                    Text(activo?.visitante ?: "Aún no hay un pase vigente", fontSize = 12.sp, color = AlkiraTextSecondary)
                    CodigoPase(activo?.token, 170.dp, "Código QR del pase activo", Modifier.clickable(enabled = activo != null) { seleccionado = activo })
                    if (activo != null) {
                        Surface(color = AlkiraBlueSoft, shape = RoundedCornerShape(8.dp)) {
                            Text("TOKEN: ${activo.token}", fontFamily = FontFamily.Monospace, fontSize = 11.sp,
                                color = AlkiraBlueBadgeText, modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp))
                        }
                        Text("Hasta: ${activo.fin} · Un ciclo de entrada y salida", fontSize = 11.sp, color = AlkiraTextSecondary)
                    }
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = { seleccionado = activo }, enabled = activo != null && !procesando,
                            modifier = Modifier.weight(1f), shape = RoundedCornerShape(8.dp)) { Text("Ver Ampliado", fontSize = 12.sp, color = AlkiraDarkNavy) }
                        Button(onClick = { activo?.let(::compartir) }, enabled = activo != null && !procesando,
                            modifier = Modifier.weight(1f), shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue, contentColor = Color.White)) { Text("Compartir", fontSize = 12.sp) }
                    }
                }
            }
            item {
                TarjetaPase {
                    Text("Registrar Nueva Visita", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                    OutlinedTextField(nombre, { if (it.length <= 150) nombre = it }, label = { Text("Nombre del visitante") },
                        modifier = Modifier.fillMaxWidth(), enabled = !procesando, singleLine = true,
                        colors = OutlinedTextFieldDefaults.colors(focusedTextColor = AlkiraTextPrimary, unfocusedTextColor = AlkiraTextPrimary,
                            focusedContainerColor = Color.White, unfocusedContainerColor = Color.White, focusedBorderColor = AlkiraPrimaryBlue,
                            unfocusedBorderColor = AlkiraBorderSubtle, focusedLabelColor = AlkiraPrimaryBlue, unfocusedLabelColor = AlkiraTextSecondary))
                    Text("Vigencia del pase:", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AlkiraTextPrimary)
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        listOf(2, 6, 12, 24, 48).forEach { valor ->
                            Surface(shape = RoundedCornerShape(20.dp), color = if (horas == valor) AlkiraDarkNavy else AlkiraCanvasBg,
                                border = BorderStroke(1.dp, if (horas == valor) AlkiraDarkNavy else AlkiraBorderSubtle),
                                modifier = Modifier.clickable(enabled = !procesando) { horas = valor }) {
                                Text("$valor h", fontSize = 11.sp, fontWeight = if (horas == valor) FontWeight.Bold else FontWeight.Medium,
                                    color = if (horas == valor) Color.White else AlkiraTextSecondary, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                            }
                        }
                    }
                    Button(onClick = {
                        teclado?.hide(); procesando = true; error = null; mensaje = null
                        alcance.launch {
                            try {
                                val pase = PasesQrApi.generar(nombre, horas)
                                nombre = ""; seleccionado = pase; mensaje = "Pase #${pase.id} generado."
                                actualizar()
                            } catch (e: CancellationException) { throw e }
                            catch (e: Exception) { error = e.message ?: "No se pudo generar el pase." }
                            finally { procesando = false }
                        }
                    }, enabled = !procesando && !cargando && nombre.isNotBlank(), modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue, contentColor = Color.White)) {
                        Text(if (procesando) "Generando…" else "Generar pase", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
        item { TextButton(onClick = onNavigateBack, colors = ButtonDefaults.textButtonColors(contentColor = AlkiraPrimaryBlue)) { Text("Volver al inicio") } }
    }
    seleccionado?.let { pase ->
        Dialog(onDismissRequest = { seleccionado = null }) {
            Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(20.dp), color = Color.White) {
                Column(Modifier.padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    InsigniaPase("PASE ${pase.estado.uppercase()}")
                    CodigoPase(pase.token, 200.dp, "Código QR del pase")
                    Text(pase.visitante, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                    Text("Válido de ${pase.inicio} a ${pase.fin}.", fontSize = 12.sp, color = AlkiraTextSecondary)
                    Text("La caseta confirma la vigencia y registra entrada y salida.", fontSize = 11.sp, color = AlkiraTextSecondary)
                    Button(onClick = { seleccionado = null }, modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraDarkNavy, contentColor = Color.White)) { Text("Cerrar") }
                }
            }
        }
    }
    revocar?.let { pase ->
        AlertDialog(onDismissRequest = { if (!procesando) revocar = null }, containerColor = Color.White,
            title = { Text("¿Revocar este pase?", color = AlkiraTextPrimary) },
            text = { Text("El pase de ${pase.visitante} dejará de autorizar nuevas entradas.", color = AlkiraTextSecondary) },
            confirmButton = { TextButton(enabled = !procesando, onClick = {
                procesando = true
                alcance.launch {
                    try { PasesQrApi.revocar(pase.id); revocar = null; mensaje = "Pase revocado."; actualizar() }
                    catch (e: CancellationException) { throw e }
                    catch (e: Exception) { error = e.message ?: "No se pudo revocar el pase."; revocar = null }
                    finally { procesando = false }
                }
            }) { Text("Confirmar revocación", color = AlkiraDangerRed) } },
            dismissButton = { TextButton(enabled = !procesando, onClick = { revocar = null }) { Text("Cancelar", color = AlkiraPrimaryBlue) } })
    }
}

@Composable
private fun TarjetaPase(centrar: Boolean = false, contenido: @Composable ColumnScope.() -> Unit) {
    Card(Modifier.fillMaxWidth().border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(16.dp)),
        shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.fillMaxWidth().padding(if (centrar) 20.dp else 18.dp),
            horizontalAlignment = if (centrar) Alignment.CenterHorizontally else Alignment.Start,
            verticalArrangement = Arrangement.spacedBy(10.dp), content = contenido)
    }
}

@Composable
private fun InsigniaPase(texto: String, modifier: Modifier = Modifier) {
    Surface(modifier, color = AlkiraGreenSoft, shape = RoundedCornerShape(20.dp), border = BorderStroke(1.dp, AlkiraSuccessGreen.copy(alpha = .3f))) {
        Text(texto, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = AlkiraGreenText, modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp))
    }
}

@Composable
private fun CodigoPase(token: String?, tamano: Dp, descripcion: String, modifier: Modifier = Modifier) = key(token) {
    val bitmap by produceState<Bitmap?>(null) { value = withContext(Dispatchers.Default) { token?.let(QrPase::crear) } }
    Box(modifier.size(tamano).background(Color.White, RoundedCornerShape(12.dp)).border(2.dp, AlkiraDarkNavy, RoundedCornerShape(12.dp)), contentAlignment = Alignment.Center) {
        bitmap?.let { Image(it.asImageBitmap(), descripcion, Modifier.fillMaxSize().padding(4.dp)) }
            ?: Text(if (token == null) "Genera tu primer pase" else "Preparando QR…", fontSize = 12.sp, color = AlkiraTextSecondary)
    }
}
