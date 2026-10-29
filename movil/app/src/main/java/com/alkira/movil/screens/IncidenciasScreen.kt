package com.alkira.movil.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.alkira.movil.red.IncidenciasApi
import com.alkira.movil.red.ReporteResidente
import com.alkira.movil.red.UnidadReporte
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

@Composable
fun IncidenciasScreen(modifier: Modifier = Modifier) {
    var titulo by rememberSaveable { mutableStateOf("") }
    var descripcion by rememberSaveable { mutableStateOf("") }
    var categoria by rememberSaveable { mutableStateOf("otro") }
    var urgencia by rememberSaveable { mutableStateOf("media") }
    var archivo by rememberSaveable { mutableStateOf<String?>(null) }
    var unidadId by rememberSaveable { mutableStateOf<Int?>(null) }
    var unidades by remember { mutableStateOf<List<UnidadReporte>>(emptyList()) }
    var reportes by remember { mutableStateOf<List<ReporteResidente>>(emptyList()) }
    var cargando by remember { mutableStateOf(true) }
    var guardando by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var mensaje by remember { mutableStateOf<String?>(null) }
    val contexto = LocalContext.current
    val alcance = rememberCoroutineScope()
    val selector = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri != null) archivo = uri.toString()
    }
    suspend fun actualizar() {
        cargando = true
        try { unidades = IncidenciasApi.unidades(); reportes = IncidenciasApi.listar(); error = null }
        catch (e: CancellationException) { throw e }
        catch (e: Exception) { error = e.message ?: "No se pudieron cargar tus reportes." }
        finally { cargando = false }
    }
    LaunchedEffect(Unit) { actualizar() }

    LazyColumn(modifier.fillMaxSize().padding(horizontal = 20.dp), verticalArrangement = Arrangement.spacedBy(12.dp),
        contentPadding = PaddingValues(vertical = 20.dp)) {
        item {
            Text("Reportar incidencia", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text("Describe una falla y adjunta su evidencia.")
        }
        item {
            error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            mensaje?.let { Text(it, color = MaterialTheme.colorScheme.primary) }
            if (cargando) LinearProgressIndicator(Modifier.fillMaxWidth())
        }
        item {
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(titulo, { if (it.length <= 150) titulo = it }, label = { Text("Título") },
                        modifier = Modifier.fillMaxWidth(), enabled = !guardando, singleLine = true)
                    OutlinedTextField(descripcion, { if (it.length <= 5000) descripcion = it }, label = { Text("Descripción") },
                        modifier = Modifier.fillMaxWidth(), minLines = 3, enabled = !guardando)
                    OpcionIncidencia("Ubicación", unidadId?.toString() ?: "", listOf("" to "Área común / sin unidad") +
                        unidades.map { it.id.toString() to it.etiqueta }, !guardando) { unidadId = it.toIntOrNull() }
                    OpcionIncidencia("Categoría", categoria, listOf("plomeria" to "Plomería", "electricidad" to "Electricidad",
                        "estructura" to "Estructura", "limpieza" to "Limpieza", "seguridad" to "Seguridad", "otro" to "Otro"), !guardando) { categoria = it }
                    OpcionIncidencia("Urgencia", urgencia, listOf("baja" to "Baja", "media" to "Media", "alta" to "Alta"), !guardando) { urgencia = it }
                    OutlinedButton(onClick = { selector.launch(arrayOf("image/jpeg", "image/png", "video/mp4", "application/pdf")) }, enabled = !guardando) {
                        Text(if (archivo == null) "Adjuntar evidencia" else "Cambiar evidencia seleccionada")
                    }
                    Text("JPG, PNG, MP4 o PDF · máximo 20 MB", style = MaterialTheme.typography.bodySmall)
                    if (archivo != null) TextButton(onClick = { archivo = null }, enabled = !guardando) { Text("Quitar archivo") }
                    Button(onClick = {
                        guardando = true; error = null; mensaje = null
                        alcance.launch {
                            try {
                                val id = IncidenciasApi.crear(titulo, descripcion, categoria, urgencia,
                                    unidades.find { it.id == unidadId }, archivo?.let(Uri::parse), contexto)
                                titulo = ""; descripcion = ""; archivo = null
                                mensaje = "Reporte #$id enviado correctamente."
                                actualizar()
                            } catch (e: CancellationException) { throw e }
                            catch (e: Exception) { error = e.message ?: "No se pudo enviar el reporte." }
                            finally { guardando = false }
                        }
                    }, enabled = !guardando && !cargando && titulo.isNotBlank() && descripcion.isNotBlank(), modifier = Modifier.fillMaxWidth()) {
                        Text(if (guardando) "Enviando…" else "Enviar reporte")
                    }
                }
            }
        }
        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Mis reportes", style = MaterialTheme.typography.titleMedium)
                TextButton(onClick = { alcance.launch { actualizar() } }, enabled = !guardando && !cargando) { Text("Actualizar") }
            }
            if (!cargando && reportes.isEmpty()) Text("Aún no tienes reportes registrados.")
        }
        items(reportes, key = { it.id }) { reporte ->
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp)) {
                    Text("#${reporte.id} · ${reporte.titulo}", fontWeight = FontWeight.Bold)
                    Text(reporte.estado.replace('_', ' ')); Text(reporte.fecha, style = MaterialTheme.typography.bodySmall)
                }
            }
        }
    }
}

@Composable
private fun OpcionIncidencia(titulo: String, valor: String, opciones: List<Pair<String, String>>, habilitado: Boolean, cambiar: (String) -> Unit) {
    var abierto by remember { mutableStateOf(false) }
    Box {
        OutlinedButton(onClick = { abierto = true }, enabled = habilitado, modifier = Modifier.fillMaxWidth()) {
            Text("$titulo: ${opciones.find { it.first == valor }?.second.orEmpty()}")
        }
        DropdownMenu(expanded = abierto, onDismissRequest = { abierto = false }) {
            opciones.forEach { (clave, etiqueta) -> DropdownMenuItem(text = { Text(etiqueta) }, onClick = { cambiar(clave); abierto = false }) }
        }
    }
}
