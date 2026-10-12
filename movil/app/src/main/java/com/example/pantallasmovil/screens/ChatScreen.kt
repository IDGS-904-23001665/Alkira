package com.example.pantallasmovil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.example.pantallasmovil.ui.theme.*

data class MessageBubble(
    val id: Int,
    val text: String,
    val isUser: Boolean,
    val timestamp: String
)

@Composable
fun ChatScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var inputText by remember { mutableStateOf("") }
    var showQuickSummaryModal by remember { mutableStateOf(false) }

    val messages = remember {
        mutableStateListOf(
            MessageBubble(
                id = 1,
                text = "¡Hola! Soy el asistente virtual de ALKIRA Property OS. ¿En qué puedo orientarte hoy?",
                isUser = false,
                timestamp = "10:00 AM"
            ),
            MessageBubble(
                id = 2,
                text = "¿Cuál es mi saldo actual y cuándo es mi próxima fecha de corte?",
                isUser = true,
                timestamp = "10:01 AM"
            ),
            MessageBubble(
                id = 3,
                text = "Tu unidad (Torre B - Depto 402) tiene un saldo de $3,500.00 MXN correspondiente a la cuota de mantenimiento de Octubre. La fecha límite de pago es el 15 de Octubre.",
                isUser = false,
                timestamp = "10:01 AM"
            )
        )
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
    ) {
        // Encabezado
        Surface(
            color = AlkiraSurfaceWhite,
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp, vertical = 14.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
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

                // Status Pill con dot verde
                Surface(
                    color = AlkiraGreenSoft,
                    shape = RoundedCornerShape(20.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraSuccessGreen.copy(alpha = 0.3f))
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(modifier = Modifier.size(6.dp).background(AlkiraSuccessGreen, CircleShape))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "IA Online",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraGreenText
                        )
                    }
                }
            }
        }

        // Lista de Mensajes del Chat
        LazyColumn(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 10.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(messages) { msg ->
                if (msg.isUser) {
                    // Burbuja de Usuario (Alineada a la derecha, Azul)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End
                    ) {
                        Surface(
                            color = AlkiraPrimaryBlue,
                            shape = RoundedCornerShape(16.dp, 16.dp, 2.dp, 16.dp),
                            modifier = Modifier.widthIn(max = 280.dp)
                        ) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Text(
                                    text = msg.text,
                                    color = Color.White,
                                    fontSize = 13.sp
                                )
                                Text(
                                    text = msg.timestamp,
                                    color = Color.White.copy(alpha = 0.7f),
                                    fontSize = 9.sp,
                                    modifier = Modifier
                                        .align(Alignment.End)
                                        .padding(top = 4.dp)
                                )
                            }
                        }
                    }
                } else {
                    // Burbuja de ALKIRA Bot (Alineada a la izquierda, Blanca con avatar)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.Start
                    ) {
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .background(AlkiraDarkNavy, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("AI", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        Surface(
                            color = AlkiraSurfaceWhite,
                            shape = RoundedCornerShape(16.dp, 16.dp, 16.dp, 2.dp),
                            border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderSubtle),
                            modifier = Modifier
                                .widthIn(max = 280.dp)
                                .clickable {
                                    if (msg.id == 3) showQuickSummaryModal = true
                                }
                        ) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Text(
                                    text = msg.text,
                                    color = AlkiraTextPrimary,
                                    fontSize = 13.sp
                                )
                                Text(
                                    text = msg.timestamp,
                                    color = AlkiraTextSecondary,
                                    fontSize = 9.sp,
                                    modifier = Modifier
                                        .align(Alignment.End)
                                        .padding(top = 4.dp)
                                )
                            }
                        }
                    }
                }
            }
        }

        // Sugerencias Rápidas (Chips interactivos)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            listOf("¿Cuánto debo?", "Horario de alberca", "Ver pase QR").forEach { chipText ->
                Surface(
                    color = AlkiraSurfaceWhite,
                    shape = RoundedCornerShape(16.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderStrong),
                    modifier = Modifier.clickable {
                        messages.add(
                            MessageBubble(
                                id = messages.size + 1,
                                text = chipText,
                                isUser = true,
                                timestamp = "10:05 AM"
                            )
                        )
                        // Respuesta automática
                        val reply = when (chipText) {
                            "¿Cuánto debo?" -> "Tu saldo actual es de $3,500.00 MXN sin recargos."
                            "Horario de alberca" -> "El área de alberca está abierta de 08:00 a 22:00 hrs de martes a domingo."
                            else -> "Puedes generar un pase dinámico para visitas desde el módulo de Pases QR."
                        }
                        messages.add(
                            MessageBubble(
                                id = messages.size + 2,
                                text = reply,
                                isUser = false,
                                timestamp = "10:05 AM"
                            )
                        )
                    }
                ) {
                    Text(
                        text = chipText,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Medium,
                        color = AlkiraDarkNavy,
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
                    )
                }
            }
        }

        // Barra Inferior de Entrada de Mensaje
        Surface(
            color = AlkiraSurfaceWhite,
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedTextField(
                    value = inputText,
                    onValueChange = { inputText = it },
                    placeholder = { Text("Escribe o dicta tu consulta...", fontSize = 12.sp, color = AlkiraTextMuted) },
                    modifier = Modifier
                        .weight(1f)
                        .padding(end = 8.dp),
                    shape = RoundedCornerShape(24.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = AlkiraCanvasBg,
                        unfocusedContainerColor = AlkiraCanvasBg,
                        focusedBorderColor = AlkiraPrimaryBlue,
                        unfocusedBorderColor = AlkiraBorderSubtle
                    )
                )

                Button(
                    onClick = {
                        if (inputText.isNotBlank()) {
                            messages.add(
                                MessageBubble(
                                    id = messages.size + 1,
                                    text = inputText,
                                    isUser = true,
                                    timestamp = "10:06 AM"
                                )
                            )
                            val userQ = inputText
                            inputText = ""
                            messages.add(
                                MessageBubble(
                                    id = messages.size + 2,
                                    text = "He recibido tu consulta sobre \"$userQ\". Estoy procesando la información autorizada para darte respuesta oportuna.",
                                    isUser = false,
                                    timestamp = "10:06 AM"
                                )
                            )
                        }
                    },
                    shape = CircleShape,
                    colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue),
                    contentPadding = PaddingValues(10.dp)
                ) {
                    Text("➤", color = Color.White, fontSize = 14.sp)
                }
            }
        }
    }

    // ==========================================
    // SUBVENTANA: Modal de Resumen Rápido
    // ==========================================
    if (showQuickSummaryModal) {
        Dialog(onDismissRequest = { showQuickSummaryModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier.fillMaxWidth().padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Resumen de Cuenta del Inquilino",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Información consultada en tiempo real",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(bottom = 12.dp)
                    )

                    Surface(
                        color = AlkiraCanvasBg,
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Adeudo Actual: $3,500.00 MXN", fontWeight = FontWeight.Bold, color = AlkiraPrimaryBlue)
                            Text("Concepto: Mantenimiento Octubre", fontSize = 12.sp, color = AlkiraTextPrimary)
                            Text("Vence: 15 de Octubre de 2026", fontSize = 12.sp, color = AlkiraTextSecondary)
                        }
                    }

                    Spacer(modifier = Modifier.height(14.dp))
                    Button(
                        onClick = { showQuickSummaryModal = false },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraDarkNavy)
                    ) {
                        Text("Cerrar")
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
