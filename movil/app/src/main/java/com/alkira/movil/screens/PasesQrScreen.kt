package com.alkira.movil.screens

import android.content.Intent
import android.graphics.Bitmap
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.alkira.movil.red.PaseResidente
import com.alkira.movil.red.PasesQrApi
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@Composable
fun PasesQrScreen(modifier: Modifier = Modifier, onNavigateBack: () -> Unit = {}) {
    var nombre by rememberSaveable { mutableStateOf("") }
    var horas by rememberSaveable { mutableIntStateOf(6) }
    var lista by remember { mutableStateOf<List<PaseResidente>>(emptyList()) }
    var seleccionado by remember { mutableStateOf<PaseResidente?>(null) }
    var revocar by remember { mutableStateOf<PaseResidente?>(null) }
    var cargando by remember { mutableStateOf(true) }
    var procesando by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var mensaje by remember { mutableStateOf<String?>(null) }
    var menu by remember { mutableStateOf(false) }
    val contexto = LocalContext.current
    val alcance = rememberCoroutineScope()
    suspend fun actualizar() {
        cargando = true
        try { lista = PasesQrApi.listar(); error = null }
        catch (e: CancellationException) { throw e }
        catch (e: Exception) { error = e.message ?: "No se pudieron cargar tus pases." }
        finally { cargando = false }
    }
    LaunchedEffect(Unit) { actualizar() }

    LazyColumn(modifier.fillMaxSize().padding(horizontal = 20.dp), verticalArrangement = Arrangement.spacedBy(12.dp),
        contentPadding = PaddingValues(vertical = 20.dp)) {
        item {
            Text("Pases de acceso QR", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            TextButton(onClick = onNavigateBack) { Text("Volver al inicio") }
            Text("Genera un pase temporal para tu visitante.")
            error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            mensaje?.let { Text(it, color = MaterialTheme.colorScheme.primary) }
            if (cargando) LinearProgressIndicator(Modifier.fillMaxWidth())
        }
        item {
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(nombre, { if (it.length <= 150) nombre = it }, label = { Text("Nombre del visitante") },
                        modifier = Modifier.fillMaxWidth(), enabled = !procesando, singleLine = true)
                    Box {
                        OutlinedButton(onClick = { menu = true }, enabled = !procesando) { Text("Vigencia: $horas horas") }
                        DropdownMenu(menu, { menu = false }) {
                            listOf(2, 6, 12, 24, 48).forEach { valor -> DropdownMenuItem(text = { Text("$valor horas") }, onClick = { horas = valor; menu = false }) }
                        }
                    }
                    Button(onClick = {
                        procesando = true; error = null; mensaje = null
                        alcance.launch {
                            try {
                                val pase = PasesQrApi.generar(nombre, horas)
                                nombre = ""; seleccionado = pase; mensaje = "Pase #${pase.id} generado."
                                actualizar()
                            } catch (e: CancellationException) { throw e }
                            catch (e: Exception) { error = e.message ?: "No se pudo generar el pase." }
                            finally { procesando = false }
                        }
                    }, enabled = !procesando && !cargando && nombre.isNotBlank(), modifier = Modifier.fillMaxWidth()) {
                        Text(if (procesando) "Generando…" else "Generar pase")
                    }
                }
            }
        }
        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Mis pases", style = MaterialTheme.typography.titleMedium)
                TextButton(onClick = { alcance.launch { actualizar() } }, enabled = !procesando && !cargando) { Text("Actualizar") }
            }
            if (!cargando && lista.isEmpty()) Text("Aún no tienes pases registrados.")
        }
        items(lista, key = { it.id }) { pase ->
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp)) {
                    Text(pase.visitante, fontWeight = FontWeight.Bold)
                    Text("Estado: ${pase.estado}"); Text("Hasta: ${pase.fin}")
                    if (pase.estado == "vigente") Row {
                        TextButton(onClick = { seleccionado = pase }, enabled = !procesando) { Text("Ver QR") }
                        TextButton(onClick = { revocar = pase }, enabled = !procesando) { Text("Revocar") }
                    }
                }
            }
        }
    }
    seleccionado?.let { pase ->
        var bitmap by remember(pase.token) { mutableStateOf<Bitmap?>(null) }
        LaunchedEffect(pase.token) { bitmap = withContext(Dispatchers.Default) { QrPase.crear(pase.token) } }
        AlertDialog(onDismissRequest = { seleccionado = null }, title = { Text("QR de ${pase.visitante}") },
            text = { Column {
                bitmap?.let { Image(it.asImageBitmap(), "Código QR del pase", Modifier.fillMaxWidth().aspectRatio(1f)) }
                Text("Válido de ${pase.inicio} a ${pase.fin}.")
                Text("Un ciclo de entrada y salida; la caseta verifica la vigencia.")
            } },
            confirmButton = { TextButton(onClick = {
                alcance.launch {
                    try {
                        val compartir = withContext(Dispatchers.IO) { QrPase.prepararCompartir(contexto, pase) }
                        contexto.startActivity(Intent.createChooser(compartir, "Compartir pase QR"))
                    } catch (e: CancellationException) { throw e }
                    catch (e: Exception) { seleccionado = null; error = e.message ?: "No se pudo compartir el pase." }
                }
            }) { Text("Compartir") } },
            dismissButton = { TextButton(onClick = { seleccionado = null }) { Text("Cerrar") } })
    }
    revocar?.let { pase ->
        AlertDialog(onDismissRequest = { if (!procesando) revocar = null }, title = { Text("¿Revocar este pase?") },
            text = { Text("El pase de ${pase.visitante} dejará de autorizar nuevas entradas.") },
            confirmButton = { TextButton(enabled = !procesando, onClick = {
                procesando = true
                alcance.launch {
                    try { PasesQrApi.revocar(pase.id); revocar = null; mensaje = "Pase revocado."; actualizar() }
                    catch (e: CancellationException) { throw e }
                    catch (e: Exception) { error = e.message ?: "No se pudo revocar el pase."; revocar = null }
                    finally { procesando = false }
                }
            }) { Text("Confirmar revocación") } },
            dismissButton = { TextButton(enabled = !procesando, onClick = { revocar = null }) { Text("Cancelar") } })
    }
}
