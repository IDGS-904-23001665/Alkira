package com.alkira.movil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import com.alkira.movil.ui.theme.*

@Composable
fun VotacionesScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var selectedTower by remember { mutableStateOf("Todas las Torres") }
    var selectedOptionIndex by remember { mutableStateOf(0) }
    var hasVoted by remember { mutableStateOf(false) }
    var showConfirmModal by remember { mutableStateOf(false) }
    var showNewNoticeModal by remember { mutableStateOf(false) }

    // Estados de votos en tiempo real
    var votesOptionA by remember { mutableStateOf(34) }
    var votesOptionB by remember { mutableStateOf(16) }
    val totalVotes = votesOptionA + votesOptionB
    val percentA = if (totalVotes > 0) (votesOptionA.toFloat() / totalVotes) else 0f
    val percentB = if (totalVotes > 0) (votesOptionB.toFloat() / totalVotes) else 0f

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .padding(horizontal = 20.dp, vertical = 16.dp)
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Encabezado
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Avisos y Votaciones",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Asamblea Virtual en Vivo",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary
                )
            }

            // Pill tiempo restante
            Surface(
                color = AlkiraBlueSoft,
                shape = RoundedCornerShape(20.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraPrimaryBlue.copy(alpha = 0.3f))
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(modifier = Modifier.size(6.dp).background(AlkiraPrimaryBlue, CircleShape))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = "Activa: 48h",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraBlueBadgeText
                    )
                }
            }
        }

        // Segmentador por Torre / Bloque
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(AlkiraSurfaceWhite, RoundedCornerShape(10.dp))
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(10.dp))
                .padding(4.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            listOf("Todas las Torres", "Torre A", "Torre B").forEach { tower ->
                val isSelected = selectedTower == tower
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .background(
                            if (isSelected) AlkiraDarkNavy else Color.Transparent,
                            RoundedCornerShape(8.dp)
                        )
                        .clickable { selectedTower = tower }
                        .padding(vertical = 7.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = tower,
                        fontSize = 11.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                        color = if (isSelected) Color.White else AlkiraTextSecondary
                    )
                }
            }
        }

        // Card 1: Comunicado Destacado
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp))
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Surface(
                        color = AlkiraGreenSoft,
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = "Aviso Oficial • Administración",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraGreenText,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                        )
                    }
                    Text(
                        text = "Hoy, 10:30 AM",
                        fontSize = 11.sp,
                        color = AlkiraTextSecondary
                    )
                }

                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Mantenimiento General e Instalación de Luminarias LED",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Estimados condóminos de $selectedTower: Se somete a consulta comunitaria la sustitución total de iluminación exterior para optimizar costos de consumo eléctrico en áreas comunes.",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(top = 6.dp)
                )
            }
        }

        // Card 2: Votación Comunitaria en Vivo
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp))
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = "Votación en Curso",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AlkiraPrimaryBlue
                )
                Text(
                    text = "¿Aprueba la inversión de $45,000 MXN para luminarias solares LED?",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary,
                    modifier = Modifier.padding(vertical = 4.dp)
                )

                Spacer(modifier = Modifier.height(10.dp))

                // Opción A
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(if (selectedOptionIndex == 0) AlkiraBlueSoft else AlkiraCanvasBg, RoundedCornerShape(8.dp))
                        .border(
                            1.dp,
                            if (selectedOptionIndex == 0) AlkiraPrimaryBlue else AlkiraBorderSubtle,
                            RoundedCornerShape(8.dp)
                        )
                        .clickable(enabled = !hasVoted) { selectedOptionIndex = 0 }
                        .padding(12.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Opción A: Sí, aprobar proyecto",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraTextPrimary
                        )
                        Text(
                            text = "${(percentA * 100).toInt()}% ($votesOptionA votos)",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraPrimaryBlue
                        )
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    LinearProgressIndicator(
                        progress = { percentA },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(6.dp),
                        color = AlkiraPrimaryBlue,
                        trackColor = AlkiraBorderSubtle
                    )
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Opción B
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(if (selectedOptionIndex == 1) AlkiraBlueSoft else AlkiraCanvasBg, RoundedCornerShape(8.dp))
                        .border(
                            1.dp,
                            if (selectedOptionIndex == 1) AlkiraPrimaryBlue else AlkiraBorderSubtle,
                            RoundedCornerShape(8.dp)
                        )
                        .clickable(enabled = !hasVoted) { selectedOptionIndex = 1 }
                        .padding(12.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Opción B: Solicitar más cotizaciones",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraTextPrimary
                        )
                        Text(
                            text = "${(percentB * 100).toInt()}% ($votesOptionB votos)",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraTextSecondary
                        )
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    LinearProgressIndicator(
                        progress = { percentB },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(6.dp),
                        color = AlkiraTextSecondary,
                        trackColor = AlkiraBorderSubtle
                    )
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Botón de emitir voto
                if (!hasVoted) {
                    Button(
                        onClick = { showConfirmModal = true },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
                    ) {
                        Text("Confirmar Voto", fontWeight = FontWeight.Bold, color = Color.White)
                    }
                } else {
                    Surface(
                        color = AlkiraGreenSoft,
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = "✓ Tu voto ha sido registrado y encriptado en el quórum.",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraGreenText,
                            modifier = Modifier.padding(10.dp)
                        )
                    }
                }
            }
        }

        // Acciones administrativas de apoyo
        OutlinedButton(
            onClick = { showNewNoticeModal = true },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(10.dp),
            border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderStrong)
        ) {
            Text("+ Redactar Nuevo Comunicado Push", color = AlkiraDarkNavy, fontWeight = FontWeight.SemiBold)
        }
    }

    // ==========================================
    // SUBVENTANA 1: Modal de Confirmación de Voto
    // ==========================================
    if (showConfirmModal) {
        Dialog(onDismissRequest = { showConfirmModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier.fillMaxWidth().padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Confirmar Emisión de Voto",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Has seleccionado: ${if (selectedOptionIndex == 0) "Opción A (Aprobar proyecto)" else "Opción B (Solicitar cotizaciones)"}.",
                        fontSize = 13.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(vertical = 12.dp)
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End
                    ) {
                        TextButton(onClick = { showConfirmModal = false }) {
                            Text("Modificar", color = AlkiraTextSecondary)
                        }
                        Button(
                            onClick = {
                                if (selectedOptionIndex == 0) votesOptionA++ else votesOptionB++
                                hasVoted = true
                                showConfirmModal = false
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
                        ) {
                            Text("Confirmar")
                        }
                    }
                }
            }
        }
    }

    // ==========================================
    // SUBVENTANA 2: Modal de Nuevo Comunicado
    // ==========================================
    if (showNewNoticeModal) {
        var noticeTitle by remember { mutableStateOf("") }
        var noticeBody by remember { mutableStateOf("") }

        Dialog(onDismissRequest = { showNewNoticeModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier.fillMaxWidth().padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Nuevo Comunicado Push",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Segmentación para $selectedTower",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(bottom = 12.dp)
                    )

                    OutlinedTextField(
                        value = noticeTitle,
                        onValueChange = { noticeTitle = it },
                        label = { Text("Título del aviso") },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp)
                    )

                    OutlinedTextField(
                        value = noticeBody,
                        onValueChange = { noticeBody = it },
                        label = { Text("Cuerpo del comunicado") },
                        maxLines = 3,
                        modifier = Modifier.fillMaxWidth().padding(bottom = 14.dp)
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End
                    ) {
                        TextButton(onClick = { showNewNoticeModal = false }) {
                            Text("Descartar", color = AlkiraTextSecondary)
                        }
                        Button(
                            onClick = { showNewNoticeModal = false },
                            colors = ButtonDefaults.buttonColors(containerColor = AlkiraDarkNavy)
                        ) {
                            Text("Enviar Notificación")
                        }
                    }
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
fun VotacionesScreenPreview() {
    PantallasMovilTheme {
        VotacionesScreen()
    }
}
