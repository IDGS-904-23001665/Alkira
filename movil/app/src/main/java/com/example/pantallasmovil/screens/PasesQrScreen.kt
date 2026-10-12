package com.example.pantallasmovil.screens

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
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.example.pantallasmovil.ui.theme.*

@Composable
fun PasesQrScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var visitorName by remember { mutableStateOf("Mariana López Ramírez") }
    var licensePlate by remember { mutableStateOf("GTO-782-B") }
    var selectedPassType by remember { mutableStateOf("Familiar / Amigos") }
    var showFullQrModal by remember { mutableStateOf(false) }
    var showShareModal by remember { mutableStateOf(false) }

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
                    text = "Control de Accesos QR",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Pases Temporales y Dinámicos",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary
                )
            }

            // Status Pill de Garita
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
                        text = "Garita Activa",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraGreenText
                    )
                }
            }
        }

        // Card 1: Visor del Pase QR Dinámico (Inspirado en visor de documentos de ALKIRA OS)
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(16.dp))
        ) {
            Column(
                modifier = Modifier.padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "Pase de Acceso Activo",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Torre B • Departamento 402",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(bottom = 14.dp)
                )

                // Simulación gráfica de Código QR con estilo corporativo
                Box(
                    modifier = Modifier
                        .size(170.dp)
                        .background(AlkiraCanvasBg, RoundedCornerShape(12.dp))
                        .border(2.dp, AlkiraDarkNavy, RoundedCornerShape(12.dp))
                        .clickable { showFullQrModal = true },
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = "☵ [ QR ] ☲",
                            fontSize = 28.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraDarkNavy
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "Toca para pantalla completa",
                            fontSize = 10.sp,
                            color = AlkiraPrimaryBlue
                        )
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Token y Vigencia
                Surface(
                    color = AlkiraBlueSoft,
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text(
                        text = "TOKEN: ALK-9482-VIS",
                        fontFamily = FontFamily.Monospace,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraBlueBadgeText,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }

                Text(
                    text = "Válido hoy hasta las 23:59 hrs • Un solo uso",
                    fontSize = 11.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(top = 8.dp)
                )

                // Acciones rápidas de compartir
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 14.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedButton(
                        onClick = { showFullQrModal = true },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(8.dp),
                        contentPadding = PaddingValues(vertical = 8.dp)
                    ) {
                        Text("Ver Ampliado", fontSize = 12.sp, color = AlkiraDarkNavy)
                    }
                    Button(
                        onClick = { showShareModal = true },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue),
                        contentPadding = PaddingValues(vertical = 8.dp)
                    ) {
                        Text("Compartir", fontSize = 12.sp)
                    }
                }
            }
        }

        // Card 2: Formulario para Generar Nuevo Pase
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(16.dp))
        ) {
            Column(modifier = Modifier.padding(18.dp)) {
                Text(
                    text = "Registrar Nueva Visita",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                OutlinedTextField(
                    value = visitorName,
                    onValueChange = { visitorName = it },
                    label = { Text("Nombre Completo del Visitante") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 10.dp)
                )

                OutlinedTextField(
                    value = licensePlate,
                    onValueChange = { licensePlate = it },
                    label = { Text("Placas del Vehículo (Opcional)") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 12.dp)
                )

                Text(
                    text = "Tipo de Acceso:",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AlkiraTextPrimary,
                    modifier = Modifier.padding(bottom = 6.dp)
                )

                // Selector de Pills
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    listOf("Familiar / Amigos", "Paquetería", "Servicios").forEach { type ->
                        val isSelected = selectedPassType == type
                        Surface(
                            shape = RoundedCornerShape(20.dp),
                            color = if (isSelected) AlkiraDarkNavy else AlkiraCanvasBg,
                            border = androidx.compose.foundation.BorderStroke(
                                1.dp,
                                if (isSelected) AlkiraDarkNavy else AlkiraBorderSubtle
                            ),
                            modifier = Modifier.clickable { selectedPassType = type }
                        ) {
                            Text(
                                text = type,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) Color.White else AlkiraTextSecondary,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                Button(
                    onClick = { showFullQrModal = true },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
                ) {
                    Text("Generar Pase de Acceso", fontWeight = FontWeight.Bold, color = Color.White)
                }
            }
        }
    }

    // ==========================================
    // SUBVENTANA 1: Modal de QR a Pantalla Completa (Modo Garita)
    // ==========================================
    if (showFullQrModal) {
        Dialog(onDismissRequest = { showFullQrModal = false }) {
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier.fillMaxWidth().padding(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Surface(
                        color = AlkiraGreenSoft,
                        shape = RoundedCornerShape(20.dp)
                    ) {
                        Text(
                            text = "● ACCESO VÁLIDO PARA GARITA",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraGreenText,
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
                        )
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // QR Grande
                    Box(
                        modifier = Modifier
                            .size(200.dp)
                            .background(Color.White)
                            .border(3.dp, AlkiraDarkNavy, RoundedCornerShape(12.dp)),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "█▀▀▀▀█ ▄▄ █▀▀▀▀█\n█ ██ █ █▄ █ ██ █\n█ ▀▀ █ ▄▄ █ ▀▀ █\n▀▀▀▀▀▀ ▀▀ ▀▀▀▀▀▀\n▀▄▄▀▄█ █▀ ▀█▄▀▄█\n█▀▀▀▀█ ▄▄ ▄▄▄▄▄▄\n█ ██ █ █▄ █▄█ █▄\n█ ▀▀ █ ▄▄ ▄ ▄▀▄█",
                            fontFamily = FontFamily.Monospace,
                            fontSize = 12.sp,
                            color = Color.Black
                        )
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    Text(
                        text = visitorName,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Vehículo: $licensePlate • Tipo: $selectedPassType",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary
                    )

                    Spacer(modifier = Modifier.height(20.dp))

                    Button(
                        onClick = { showFullQrModal = false },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraDarkNavy)
                    ) {
                        Text("Cerrar")
                    }
                }
            }
        }
    }

    // ==========================================
    // SUBVENTANA 2: Modal de Compartir Pase
    // ==========================================
    if (showShareModal) {
        Dialog(onDismissRequest = { showShareModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier.fillMaxWidth().padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Compartir Pase QR",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Envía el enlace de acceso directo al visitante",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(bottom = 16.dp)
                    )

                    Surface(
                        color = AlkiraCanvasBg,
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = "https://alkira.app/pass/ALK-9482-VIS",
                            fontSize = 12.sp,
                            fontFamily = FontFamily.Monospace,
                            color = AlkiraPrimaryBlue,
                            modifier = Modifier.padding(12.dp)
                        )
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        OutlinedButton(
                            onClick = { showShareModal = false },
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Copiar", color = AlkiraTextPrimary)
                        }
                        Button(
                            onClick = { showShareModal = false },
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.buttonColors(containerColor = AlkiraSuccessGreen)
                        ) {
                            Text("WhatsApp", color = Color.White)
                        }
                    }
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
fun PasesQrScreenPreview() {
    PantallasMovilTheme {
        PasesQrScreen()
    }
}
