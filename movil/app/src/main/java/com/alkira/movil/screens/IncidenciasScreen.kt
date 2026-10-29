package com.alkira.movil.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AttachFile
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.IncidenciasApi
import com.alkira.movil.red.ReporteResidente
import com.alkira.movil.red.UnidadReporte
import com.alkira.movil.ui.theme.*
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
    var clasificar by rememberSaveable { mutableStateOf(false) }
    var historial by rememberSaveable { mutableStateOf(false) }
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

    Column(modifier.fillMaxSize().background(AlkiraCanvasBg).padding(20.dp)) {
        LazyColumn(Modifier.weight(1f).fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item {
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Text(if (historial) "Mis reportes" else "Reportar Incidencia", fontSize = 18.sp,
                        fontWeight = FontWeight.Bold, color = AlkiraTextPrimary, modifier = Modifier.weight(1f))
                    TextButton(onClick = { historial = !historial }, enabled = !guardando && !cargando,
                        colors = ButtonDefaults.textButtonColors(contentColor = AlkiraPrimaryBlue)) {
                        Text(if (historial) "Nuevo reporte" else "Mis reportes", fontSize = 12.sp)
                    }
                }
                if (!historial) Text("Notifica fallas o problemas en áreas comunes o tu propiedad",
                    fontSize = 12.sp, color = AlkiraTextSecondary, modifier = Modifier.padding(bottom = 8.dp))
            }
            if (error != null || mensaje != null || cargando) item {
                error?.let { Text(it, color = AlkiraDangerRed, fontSize = 12.sp) }
                mensaje?.let { Text(it, color = AlkiraPrimaryBlue, fontSize = 12.sp) }
                if (cargando) LinearProgressIndicator(Modifier.fillMaxWidth(), color = AlkiraPrimaryBlue)
            }
            if (historial) {
                item {
                    TextButton(onClick = { alcance.launch { actualizar() } }, enabled = !cargando,
                        colors = ButtonDefaults.textButtonColors(contentColor = AlkiraPrimaryBlue)) { Text("Actualizar") }
                    if (!cargando && reportes.isEmpty()) Text("Aún no tienes reportes registrados.", color = AlkiraTextSecondary)
                }
                items(reportes, key = { it.id }) { reporte ->
                    Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                        Column(Modifier.padding(16.dp)) {
                            Text("#${reporte.id} · ${reporte.titulo}", fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                            Text(reporte.estado.replace('_', ' '), color = AlkiraTextSecondary)
                            Text(reporte.fecha, fontSize = 12.sp, color = AlkiraTextSecondary)
                        }
                    }
                }
            } else item {
                Card(Modifier.fillMaxWidth().border(1.dp, AlkiraBorderStrong, RoundedCornerShape(16.dp)),
                    shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFEDF2F7))) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        CampoReporte("Título", titulo, !guardando) { titulo = it }
                        CampoReporte("Descripción", descripcion, !guardando, multilinea = true) { descripcion = it }
                        Box(Modifier.fillMaxWidth().height(100.dp).background(Color.White, RoundedCornerShape(12.dp))
                            .border(1.dp, AlkiraBorderStrong, RoundedCornerShape(12.dp))
                            .clickable(enabled = !guardando) { selector.launch(arrayOf("image/jpeg", "image/png", "video/mp4", "application/pdf")) },
                            contentAlignment = Alignment.Center) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(Icons.Outlined.AttachFile, contentDescription = null, tint = AlkiraPrimaryBlue)
                                Text(if (archivo == null) "Adjuntar Evidencia Fotográfica" else "Evidencia seleccionada · Cambiar",
                                    fontSize = 12.sp, fontWeight = FontWeight.Medium, color = AlkiraPrimaryBlue)
                                Text("JPG, PNG, MP4 o PDF · máximo 20 MB", fontSize = 10.sp, color = AlkiraTextSecondary)
                            }
                        }
                        if (archivo != null) TextButton(onClick = { archivo = null }, enabled = !guardando) { Text("Quitar archivo", color = AlkiraPrimaryBlue) }
                        TextButton(onClick = { clasificar = !clasificar }, enabled = !guardando,
                            contentPadding = PaddingValues(0.dp)) { Text(if (clasificar) "Ocultar ubicación y clasificación" else "Ubicación y clasificación", color = AlkiraPrimaryBlue, fontSize = 12.sp) }
                        if (clasificar) {
                            OpcionIncidencia("Ubicación", unidadId?.toString() ?: "", listOf("" to "Área común / sin unidad") +
                                unidades.map { it.id.toString() to it.etiqueta }, !guardando) { unidadId = it.toIntOrNull() }
                            OpcionIncidencia("Categoría", categoria, listOf("plomeria" to "Plomería", "electricidad" to "Electricidad",
                                "estructura" to "Estructura", "limpieza" to "Limpieza", "seguridad" to "Seguridad", "otro" to "Otro"), !guardando) { categoria = it }
                            OpcionIncidencia("Urgencia", urgencia, listOf("baja" to "Baja", "media" to "Media", "alta" to "Alta"), !guardando) { urgencia = it }
                        }
                    }
                }
            }
        }
        if (!historial) Button(onClick = {
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
        }, enabled = !guardando && !cargando && titulo.isNotBlank() && descripcion.isNotBlank(),
            modifier = Modifier.fillMaxWidth().padding(top = 12.dp), shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFC85233), disabledContainerColor = Color(0xFFC85233).copy(alpha = .45f))) {
            Text(if (guardando) "Enviando…" else "Enviar Reporte", color = Color.White, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun CampoReporte(etiqueta: String, valor: String, habilitado: Boolean, multilinea: Boolean = false, cambiar: (String) -> Unit) {
    Column {
        Text(if (multilinea) "DESCRIPCIÓN DETALLADA" else "TÍTULO DE LA INCIDENCIA", fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold, color = AlkiraTextSecondary, modifier = Modifier.padding(bottom = 4.dp))
        OutlinedTextField(valor, { if (it.length <= if (multilinea) 5000 else 150) cambiar(it) }, enabled = habilitado,
            singleLine = !multilinea, modifier = Modifier.fillMaxWidth().then(if (multilinea) Modifier.height(100.dp) else Modifier)
                .semantics { contentDescription = etiqueta },
            colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = Color.White, unfocusedContainerColor = Color.White,
                disabledContainerColor = Color.White, focusedBorderColor = AlkiraBorderStrong, unfocusedBorderColor = AlkiraBorderStrong,
                focusedTextColor = AlkiraTextPrimary, unfocusedTextColor = AlkiraTextPrimary))
    }
}

@Composable
private fun OpcionIncidencia(titulo: String, valor: String, opciones: List<Pair<String, String>>, habilitado: Boolean, cambiar: (String) -> Unit) {
    var abierto by remember { mutableStateOf(false) }
    Box {
        OutlinedButton(onClick = { abierto = true }, enabled = habilitado, modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = AlkiraPrimaryBlue, containerColor = Color.White)) {
            Text("$titulo: ${opciones.find { it.first == valor }?.second.orEmpty()}")
        }
        DropdownMenu(expanded = abierto, onDismissRequest = { abierto = false }, modifier = Modifier.background(Color.White)) {
            opciones.forEach { (clave, etiqueta) -> DropdownMenuItem(text = { Text(etiqueta, color = AlkiraTextPrimary) }, onClick = { cambiar(clave); abierto = false }) }
        }
    }
}
