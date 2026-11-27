package com.alkira.movil.screens

import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.os.Handler
import android.os.Looper
import android.speech.RecognizerIntent
import android.speech.tts.TextToSpeech
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.VolumeUp
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.ChatbotApi
import com.alkira.movil.red.ConversacionChat
import com.alkira.movil.red.MensajeChat
import com.alkira.movil.red.SesionMovil
import com.alkira.movil.ui.theme.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun ChatScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {

    val contexto = LocalContext.current
    val alcance = rememberCoroutineScope()
    val token = SesionMovil.token
    val estadoLista = rememberLazyListState()

    var textoEntrada by remember(token) {
        mutableStateOf("")
    }

    var idConversacion by remember(token) {
        mutableStateOf<Long?>(null)
    }

    var conversacionActiva by remember(token) {
        mutableStateOf(true)
    }

    var cargando by remember(token) {
        mutableStateOf(false)
    }

    var mensajeError by remember(token) {
        mutableStateOf<String?>(null)
    }

    var mostrarHistorial by remember(token) {
        mutableStateOf(false)
    }

    var conversaciones by remember(token) {
        mutableStateOf<List<ConversacionChat>>(emptyList())
    }

    val mensajes = remember(token) {
        mutableStateListOf<MensajeChat>()
    }

    var lectorVoz by remember {
        mutableStateOf<TextToSpeech?>(null)
    }

    var vozDisponible by remember {
        mutableStateOf(false)
    }

    // ==========================================
    // CONFIGURACIÓN DE LECTURA POR VOZ
    // ==========================================

    DisposableEffect(contexto) {

        var vigente = true

        val motorVoz = TextToSpeech(contexto) { estado ->

            Handler(Looper.getMainLooper()).post {

                if (vigente && estado == TextToSpeech.SUCCESS) {

                    val resultado = lectorVoz?.setLanguage(
                        Locale("es", "MX")
                    )

                    vozDisponible =
                        resultado != null &&
                        resultado >= TextToSpeech.LANG_AVAILABLE
                }
            }
        }

        lectorVoz = motorVoz

        onDispose {
            vigente = false
            motorVoz.stop()
            motorVoz.shutdown()
        }
    }

    // ==========================================
    // RECONOCIMIENTO DE VOZ
    // ==========================================

    val reconocimientoVoz =
        rememberLauncherForActivityResult(
            contract =
                ActivityResultContracts.StartActivityForResult()
        ) { resultado ->

            if (resultado.resultCode == Activity.RESULT_OK) {

                val textoReconocido = resultado.data
                    ?.getStringArrayListExtra(
                        RecognizerIntent.EXTRA_RESULTS
                    )
                    ?.firstOrNull()

                if (!textoReconocido.isNullOrBlank()) {
                    textoEntrada = textoReconocido
                }
            }
        }

    fun iniciarReconocimientoVoz() {

        try {

            val solicitud = Intent(
                RecognizerIntent.ACTION_RECOGNIZE_SPEECH
            ).apply {

                putExtra(
                    RecognizerIntent.EXTRA_LANGUAGE_MODEL,
                    RecognizerIntent.LANGUAGE_MODEL_FREE_FORM
                )

                putExtra(
                    RecognizerIntent.EXTRA_LANGUAGE,
                    "es-MX"
                )

                putExtra(
                    RecognizerIntent.EXTRA_PROMPT,
                    "Realiza tu consulta a ALKIRA"
                )
            }

            reconocimientoVoz.launch(solicitud)

        } catch (error: ActivityNotFoundException) {

            mensajeError =
                "Tu dispositivo no dispone de reconocimiento de voz."

        } catch (error: SecurityException) {

            mensajeError =
                "No fue posible acceder al reconocimiento de voz."
        }
    }

    fun obtenerHoraActual(): String {

        return SimpleDateFormat(
            "hh:mm a",
            Locale("es", "MX")
        ).format(Date())
    }

    // ==========================================
    // EJECUCIÓN DE PETICIONES
    // ==========================================

    fun ejecutarPeticion(
        accion: suspend () -> Unit
    ) {

        if (cargando) return

        cargando = true
        mensajeError = null

        alcance.launch {

            try {

                accion()

            } catch (error: CancellationException) {

                throw error

            } catch (error: Exception) {

                mensajeError = error.message
                    ?: "No fue posible conectar con ALKIRA."

            } finally {

                cargando = false
            }
        }
    }

    // ==========================================
    // ENVÍO DE MENSAJES AL BACKEND
    // ==========================================

    fun enviarMensaje(texto: String) {

        val pregunta = texto.trim()

        if (
            pregunta.isBlank() ||
            cargando ||
            !conversacionActiva
        ) {
            return
        }

        if (pregunta.toByteArray(Charsets.UTF_8).size > 500) {

            mensajeError =
                "La consulta es demasiado larga."

            return
        }

        textoEntrada = pregunta

        ejecutarPeticion {

            val respuesta = ChatbotApi.enviar(
                mensaje = pregunta,
                idConversacion = idConversacion
            )

            idConversacion = respuesta.idConversacion

            mensajes.add(
                MensajeChat(
                    contenido = pregunta,
                    esUsuario = true,
                    fecha = obtenerHoraActual()
                )
            )

            mensajes.add(
                MensajeChat(
                    contenido = respuesta.respuesta,
                    esUsuario = false,
                    fecha = obtenerHoraActual()
                )
            )

            textoEntrada = ""
        }
    }

    // ==========================================
    // HISTORIAL DE CONVERSACIONES
    // ==========================================

    fun cargarHistorial() {

        ejecutarPeticion {

            conversaciones = ChatbotApi.conversaciones()

            mostrarHistorial = true
        }
    }

    fun abrirConversacion(
        conversacion: ConversacionChat
    ) {

        mostrarHistorial = false

        ejecutarPeticion {

            val mensajesRecuperados =
                ChatbotApi.mensajes(conversacion.id)

            lectorVoz?.stop()

            mensajes.clear()

            mensajes.addAll(mensajesRecuperados)

            idConversacion = conversacion.id

            conversacionActiva =
                conversacion.estado == "activa"

            textoEntrada = ""
        }
    }

    fun nuevaConversacion() {

        if (cargando) return

        lectorVoz?.stop()

        mensajes.clear()

        idConversacion = null

        conversacionActiva = true

        textoEntrada = ""

        mensajeError = null
    }

    // Desplazamiento automático al último mensaje.

    LaunchedEffect(mensajes.size) {

        if (mensajes.isNotEmpty()) {

            estadoLista.animateScrollToItem(
                mensajes.lastIndex
            )
        }
    }

    // ==========================================
    // INTERFAZ PRINCIPAL
    // ==========================================

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
    ) {

        // ======================================
        // ENCABEZADO
        // ======================================

        Surface(
            color = AlkiraSurfaceWhite,
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle)
        ) {

            Column(
                modifier = Modifier.padding(
                    horizontal = 20.dp,
                    vertical = 14.dp
                )
            ) {

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement =
                        Arrangement.SpaceBetween,
                    verticalAlignment =
                        Alignment.CenterVertically
                ) {

                    Column(
                        modifier = Modifier.weight(1f)
                    ) {

                        Text(
                            text = "Asistente Virtual ALKIRA",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraTextPrimary
                        )

                        Text(
                            text = "Consultas Operativas y Financieras",
                            fontSize = 12.sp,
                            color = AlkiraTextSecondary
                        )
                    }

                    Surface(
                        color = AlkiraGreenSoft,
                        shape = RoundedCornerShape(20.dp),
                        border = BorderStroke(
                            1.dp,
                            AlkiraSuccessGreen.copy(alpha = 0.3f)
                        )
                    ) {

                        Row(
                            modifier = Modifier.padding(
                                horizontal = 10.dp,
                                vertical = 5.dp
                            ),
                            verticalAlignment =
                                Alignment.CenterVertically
                        ) {

                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .background(
                                        AlkiraSuccessGreen,
                                        CircleShape
                                    )
                            )

                            Spacer(
                                modifier = Modifier.width(6.dp)
                            )

                            Text(
                                text = if (cargando)
                                    "Consultando"
                                else
                                    "Asistente IA",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AlkiraGreenText
                            )
                        }
                    }
                }

                Spacer(
                    modifier = Modifier.height(6.dp)
                )

                Row(
                    horizontalArrangement =
                        Arrangement.spacedBy(8.dp)
                ) {

                    TextButton(
                        enabled = !cargando,
                        onClick = {
                            nuevaConversacion()
                        }
                    ) {

                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = AlkiraPrimaryBlue
                        )

                        Spacer(
                            modifier = Modifier.width(4.dp)
                        )

                        Text(
                            text = "Nuevo chat",
                            color = AlkiraPrimaryBlue,
                            fontSize = 12.sp
                        )
                    }

                    TextButton(
                        enabled = !cargando,
                        onClick = {
                            cargarHistorial()
                        }
                    ) {

                        Icon(
                            imageVector = Icons.Default.History,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = AlkiraPrimaryBlue
                        )

                        Spacer(
                            modifier = Modifier.width(4.dp)
                        )

                        Text(
                            text = "Historial",
                            color = AlkiraPrimaryBlue,
                            fontSize = 12.sp
                        )
                    }
                }
            }
        }

        // ======================================
        // LISTA DE MENSAJES
        // ======================================

        LazyColumn(
            state = estadoLista,
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(
                    horizontal = 16.dp,
                    vertical = 10.dp
                ),
            verticalArrangement =
                Arrangement.spacedBy(12.dp)
        ) {

            if (mensajes.isEmpty()) {

                item {

                    BurbujaMensajeChat(
                        mensaje = MensajeChat(
                            contenido =
                                "¡Hola! Soy el asistente virtual " +
                                "de ALKIRA Property OS. " +
                                "¿En qué puedo orientarte hoy?",
                            esUsuario = false
                        ),
                        vozDisponible = vozDisponible,
                        alEscuchar = { texto ->

                            lectorVoz?.speak(
                                texto,
                                TextToSpeech.QUEUE_FLUSH,
                                null,
                                "bienvenida"
                            )
                        }
                    )
                }
            }

            items(mensajes) { mensaje ->

                BurbujaMensajeChat(
                    mensaje = mensaje,
                    vozDisponible = vozDisponible,
                    alEscuchar = { texto ->

                        lectorVoz?.speak(
                            texto,
                            TextToSpeech.QUEUE_FLUSH,
                            null,
                            "respuesta"
                        )
                    }
                )
            }

            if (cargando) {

                item {

                    Row(
                        verticalAlignment =
                            Alignment.CenterVertically
                    ) {

                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = AlkiraPrimaryBlue
                        )

                        Spacer(
                            modifier = Modifier.width(10.dp)
                        )

                        Text(
                            text = "ALKIRA está consultando...",
                            fontSize = 12.sp,
                            color = AlkiraTextSecondary
                        )
                    }
                }
            }
        }

        // ======================================
        // MENSAJES DE ERROR
        // ======================================

        mensajeError?.let { error ->

            Surface(
                color = MaterialTheme.colorScheme.errorContainer,
                shape = RoundedCornerShape(8.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
            ) {

                Text(
                    text = error,
                    color = MaterialTheme.colorScheme
                        .onErrorContainer,
                    fontSize = 12.sp,
                    modifier = Modifier.padding(12.dp)
                )
            }
        }

        if (!conversacionActiva) {

            Text(
                text = "Esta conversación ha finalizado. " +
                    "Inicia un nuevo chat para continuar.",
                fontSize = 12.sp,
                color = AlkiraTextSecondary,
                modifier = Modifier.padding(16.dp)
            )
        }

        // ======================================
        // SUGERENCIAS RÁPIDAS
        // ======================================

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState())
                .padding(
                    horizontal = 16.dp,
                    vertical = 6.dp
                ),
            horizontalArrangement =
                Arrangement.spacedBy(8.dp)
        ) {

            val sugerencias = listOf(
                "¿Cuánto debo?",
                "Estado de mis reportes",
                "Horario de alberca",
                "¿Cómo generar un pase QR?"
            )

            sugerencias.forEach { pregunta ->

                AssistChip(
                    enabled = !cargando && conversacionActiva,
                    onClick = {
                        enviarMensaje(pregunta)
                    },
                    label = {

                        Text(
                            text = pregunta,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium,
                            color = AlkiraDarkNavy
                        )
                    },
                    shape = RoundedCornerShape(16.dp),
                    colors =
                        AssistChipDefaults.assistChipColors(
                            containerColor = AlkiraSurfaceWhite
                        )
                )
            }
        }

        // ======================================
        // BARRA DE ENTRADA
        // ======================================

        Surface(
            color = AlkiraSurfaceWhite,
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle)
        ) {

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .imePadding()
                    .padding(
                        horizontal = 12.dp,
                        vertical = 10.dp
                    ),
                verticalAlignment =
                    Alignment.CenterVertically
            ) {

                OutlinedTextField(
                    value = textoEntrada,
                    onValueChange = {
                        textoEntrada = it
                    },
                    enabled =
                        !cargando && conversacionActiva,
                    placeholder = {

                        Text(
                            text = "Escribe o dicta tu consulta...",
                            fontSize = 12.sp,
                            color = AlkiraTextMuted
                        )
                    },
                    modifier = Modifier.weight(1f),
                    maxLines = 4,
                    shape = RoundedCornerShape(24.dp),
                    colors =
                        OutlinedTextFieldDefaults.colors(
                            focusedContainerColor =
                                AlkiraCanvasBg,
                            unfocusedContainerColor =
                                AlkiraCanvasBg,
                            focusedBorderColor =
                                AlkiraPrimaryBlue,
                            unfocusedBorderColor =
                                AlkiraBorderSubtle
                        )
                )

                // Botón de micrófono.

                IconButton(
                    enabled = !cargando && conversacionActiva,
                    onClick = {
                        iniciarReconocimientoVoz()
                    }
                ) {

                    Icon(
                        imageVector = Icons.Default.Mic,
                        contentDescription = "Dictar consulta",
                        tint = AlkiraPrimaryBlue
                    )
                }

                // Botón de envío.

                Button(
                    enabled =
                        !cargando &&
                        conversacionActiva &&
                        textoEntrada.isNotBlank(),
                    onClick = {
                        enviarMensaje(textoEntrada)
                    },
                    modifier = Modifier.size(42.dp),
                    shape = CircleShape,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = AlkiraPrimaryBlue
                    ),
                    contentPadding = PaddingValues(10.dp)
                ) {

                    Icon(
                        imageVector = Icons.Default.Send,
                        contentDescription = "Enviar mensaje",
                        tint = Color.White,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
        }
    }

    // ==========================================
    // MODAL DEL HISTORIAL
    // ==========================================

    if (mostrarHistorial) {

        AlertDialog(
            onDismissRequest = {
                mostrarHistorial = false
            },
            containerColor = AlkiraSurfaceWhite,
            title = {

                Text(
                    text = "Historial de conversaciones",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
            },
            text = {

                if (conversaciones.isEmpty()) {

                    Text(
                        text = "Todavía no tienes conversaciones.",
                        color = AlkiraTextSecondary,
                        fontSize = 13.sp
                    )

                } else {

                    LazyColumn(
                        modifier = Modifier.heightIn(
                            max = 360.dp
                        ),
                        verticalArrangement =
                            Arrangement.spacedBy(8.dp)
                    ) {

                        items(conversaciones) { conversacion ->

                            OutlinedCard(
                                onClick = {

                                    abrirConversacion(
                                        conversacion
                                    )
                                },
                                modifier = Modifier.fillMaxWidth(),
                                colors = CardDefaults
                                    .outlinedCardColors(
                                        containerColor =
                                            AlkiraCanvasBg
                                    )
                            ) {

                                Column(
                                    modifier =
                                        Modifier.padding(12.dp)
                                ) {

                                    Text(
                                        text =
                                            "Conversación #" +
                                            conversacion.id,
                                        fontSize = 13.sp,
                                        fontWeight =
                                            FontWeight.Bold,
                                        color = AlkiraTextPrimary
                                    )

                                    Text(
                                        text = conversacion.fecha,
                                        fontSize = 11.sp,
                                        color = AlkiraTextSecondary
                                    )

                                    Text(
                                        text =
                                            "${conversacion.totalMensajes} mensajes",
                                        fontSize = 11.sp,
                                        color = AlkiraTextSecondary
                                    )

                                    Text(
                                        text = conversacion.estado,
                                        fontSize = 11.sp,
                                        color = AlkiraPrimaryBlue
                                    )
                                }
                            }
                        }
                    }
                }
            },
            confirmButton = {

                TextButton(
                    onClick = {
                        mostrarHistorial = false
                    }
                ) {

                    Text(
                        text = "Cerrar",
                        color = AlkiraPrimaryBlue
                    )
                }
            }
        )
    }
}

// ==============================================
// COMPONENTE DE BURBUJAS DEL CHAT
// ==============================================

@Composable
private fun BurbujaMensajeChat(
    mensaje: MensajeChat,
    vozDisponible: Boolean,
    alEscuchar: (String) -> Unit
) {

    if (mensaje.esUsuario) {

        // Mensaje del residente.

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.End
        ) {

            Surface(
                color = AlkiraPrimaryBlue,
                shape = RoundedCornerShape(
                    16.dp,
                    16.dp,
                    2.dp,
                    16.dp
                ),
                modifier = Modifier.widthIn(max = 280.dp)
            ) {

                Column(
                    modifier = Modifier.padding(12.dp)
                ) {

                    Text(
                        text = mensaje.contenido,
                        color = Color.White,
                        fontSize = 13.sp
                    )

                    if (mensaje.fecha.isNotBlank()) {

                        Text(
                            text = mensaje.fecha,
                            color = Color.White.copy(
                                alpha = 0.7f
                            ),
                            fontSize = 9.sp,
                            modifier = Modifier
                                .align(Alignment.End)
                                .padding(top = 4.dp)
                        )
                    }
                }
            }
        }

    } else {

        // Mensaje del asistente ALKIRA.

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.Start
        ) {

            Box(
                modifier = Modifier
                    .size(32.dp)
                    .background(
                        AlkiraDarkNavy,
                        CircleShape
                    ),
                contentAlignment = Alignment.Center
            ) {

                Text(
                    text = "AI",
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp
                )
            }

            Spacer(
                modifier = Modifier.width(8.dp)
            )

            Surface(
                color = AlkiraSurfaceWhite,
                shape = RoundedCornerShape(
                    16.dp,
                    16.dp,
                    16.dp,
                    2.dp
                ),
                border = BorderStroke(
                    1.dp,
                    AlkiraBorderSubtle
                ),
                modifier = Modifier.widthIn(max = 280.dp)
            ) {

                Column(
                    modifier = Modifier.padding(12.dp)
                ) {

                    Text(
                        text = mensaje.contenido,
                        color = AlkiraTextPrimary,
                        fontSize = 13.sp
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement =
                            Arrangement.SpaceBetween,
                        verticalAlignment =
                            Alignment.CenterVertically
                    ) {

                        if (vozDisponible) {

                            IconButton(
                                modifier = Modifier.size(30.dp),
                                onClick = {

                                    alEscuchar(
                                        mensaje.contenido
                                    )
                                }
                            ) {

                                Icon(
                                    imageVector =
                                        Icons.Default.VolumeUp,
                                    contentDescription =
                                        "Escuchar respuesta",
                                    tint = AlkiraPrimaryBlue,
                                    modifier =
                                        Modifier.size(16.dp)
                                )
                            }

                        } else {

                            Spacer(
                                modifier = Modifier.width(1.dp)
                            )
                        }

                        Text(
                            text = mensaje.fecha,
                            color = AlkiraTextSecondary,
                            fontSize = 9.sp
                        )
                    }
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
fun ChatScreenPreview() {

    PantallasMovilTheme {

        ChatScreen()
    }
}