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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.example.pantallasmovil.ui.theme.*

@Composable
fun TicketsScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var selectedTab by remember { mutableStateOf("En Atención") }
    var showQuoteModal by remember { mutableStateOf(false) }
    var showSignatureModal by remember { mutableStateOf(false) }

    // Estados de formulario
    var quoteAmount by remember { mutableStateOf("1,450.00") }
    var materialsCost by remember { mutableStateOf("650.00") }
    var laborCost by remember { mutableStateOf("800.00") }
    var isVerifiedSite by remember { mutableStateOf(true) }
    var isPartsOriginal by remember { mutableStateOf(true) }
    var isAreaClean by remember { mutableStateOf(false) }
    var rating by remember { mutableStateOf(5) }
    var ticketStatus by remember { mutableStateOf("En Proceso") }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .padding(horizontal = 20.dp, vertical = 16.dp)
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Encabezado con navegación de retorno y estatus
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Gestión de Mantenimiento",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Ticket #TK-2026-084",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary
                )
            }

            // Status Pill con punto indicador (estilo web)
            Surface(
                color = if (ticketStatus == "Concluido") AlkiraGreenSoft else AlkiraAmberSoft,
                shape = RoundedCornerShape(20.dp),
                border = androidx.compose.foundation.BorderStroke(
                    1.dp,
                    if (ticketStatus == "Concluido") AlkiraSuccessGreen.copy(alpha = 0.3f) else AlkiraWarningAmber.copy(alpha = 0.3f)
                )
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(7.dp)
                            .background(
                                if (ticketStatus == "Concluido") AlkiraSuccessGreen else AlkiraWarningAmber,
                                CircleShape
                            )
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = ticketStatus,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (ticketStatus == "Concluido") AlkiraGreenText else AlkiraAmberText
                    )
                }
            }
        }

        // Tabs de filtro de estado
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(AlkiraSurfaceWhite, RoundedCornerShape(10.dp))
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(10.dp))
                .padding(4.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            listOf("Pendiente", "En Atención", "Concluido").forEach { tab ->
                val isSelected = selectedTab == tab
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .background(
                            if (isSelected) AlkiraPrimaryBlue else Color.Transparent,
                            RoundedCornerShape(8.dp)
                        )
                        .clickable { selectedTab = tab }
                        .padding(vertical = 8.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = tab,
                        fontSize = 12.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                        color = if (isSelected) Color.White else AlkiraTextSecondary
                    )
                }
            }
        }

        // Card 1: Detalle del Problema
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
                    Text(
                        text = "Avería Reportada",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraTextSecondary
                    )
                    Surface(
                        color = AlkiraRedSoft,
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = "Prioridad Alta",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraDangerRed,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                        )
                    }
                }
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Fuga de agua en tubería de baño principal",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Ubicación: Torre B • Departamento 402",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(top = 2.dp)
                )

                HorizontalDivider(
                    color = AlkiraBorderSubtle,
                    modifier = Modifier.padding(vertical = 12.dp)
                )

                // Proveedor asignado
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .background(AlkiraBlueSoft, CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "PL",
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            color = AlkiraPrimaryBlue
                        )
                    }
                    Spacer(modifier = Modifier.width(10.dp))
                    Column {
                        Text(
                            text = "Plomería Express del Bajío",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraTextPrimary
                        )
                        Text(
                            text = "Téc. Roberto Mendoza • Visita: Hoy 16:30 hrs",
                            fontSize = 11.sp,
                            color = AlkiraTextSecondary
                        )
                    }
                }
            }
        }

        // Card 2: Presupuesto y Cotización
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
                    Column {
                        Text(
                            text = "Presupuesto Autorizado",
                            fontSize = 12.sp,
                            color = AlkiraTextSecondary
                        )
                        Text(
                            text = "$$quoteAmount MXN",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraTextPrimary
                        )
                    }
                    OutlinedButton(
                        onClick = { showQuoteModal = true },
                        shape = RoundedCornerShape(8.dp),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Text("Ver Desglose", fontSize = 12.sp, color = AlkiraPrimaryBlue)
                    }
                }
            }
        }

        // Card 3: Checklist de Verificación y Conformidad
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp))
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = "Checklist de Verificación en Sitio",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AlkiraTextPrimary,
                    modifier = Modifier.padding(bottom = 8.dp)
                )

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { isVerifiedSite = !isVerifiedSite }
                        .padding(vertical = 4.dp)
                ) {
                    Checkbox(
                        checked = isVerifiedSite,
                        onCheckedChange = { isVerifiedSite = it },
                        colors = CheckboxDefaults.colors(checkedColor = AlkiraPrimaryBlue)
                    )
                    Text(
                        text = "Fuga sellada y presión de agua verificada",
                        fontSize = 12.sp,
                        color = AlkiraTextPrimary
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { isPartsOriginal = !isPartsOriginal }
                        .padding(vertical = 4.dp)
                ) {
                    Checkbox(
                        checked = isPartsOriginal,
                        onCheckedChange = { isPartsOriginal = it },
                        colors = CheckboxDefaults.colors(checkedColor = AlkiraPrimaryBlue)
                    )
                    Text(
                        text = "Refacciones nuevas colocadas con garantía",
                        fontSize = 12.sp,
                        color = AlkiraTextPrimary
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { isAreaClean = !isAreaClean }
                        .padding(vertical = 4.dp)
                ) {
                    Checkbox(
                        checked = isAreaClean,
                        onCheckedChange = { isAreaClean = it },
                        colors = CheckboxDefaults.colors(checkedColor = AlkiraPrimaryBlue)
                    )
                    Text(
                        text = "Área de trabajo limpia y sin escombros",
                        fontSize = 12.sp,
                        color = AlkiraTextPrimary
                    )
                }
            }
        }

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 4.dp, bottom = 12.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            OutlinedButton(
                onClick = { ticketStatus = "Rechazado / En Revisión" },
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(8.dp),
                colors = ButtonDefaults.outlinedButtonColors(
                    containerColor = AlkiraSurfaceWhite,
                    contentColor = AlkiraDangerRed
                ),
                border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderSubtle)
            ) {
                Text(
                    text = "Rechazar",
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 13.sp
                )
            }

            Button(
                onClick = { showSignatureModal = true },
                modifier = Modifier.weight(1.4f),
                shape = RoundedCornerShape(8.dp),
                colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
            ) {
                Text(
                    text = "Finalizar y Firmar",
                    fontWeight = FontWeight.Bold,
                    fontSize = 13.sp,
                    color = Color.White
                )
            }
        }
    }

    // ==========================================
    // SUBVENTANA 1: Modal de Desglose de Cotización
    // ==========================================
    if (showQuoteModal) {
        Dialog(onDismissRequest = { showQuoteModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Detalle de Cotización",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Valores aprobados por administración",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(bottom = 16.dp)
                    )

                    OutlinedTextField(
                        value = materialsCost,
                        onValueChange = { materialsCost = it },
                        label = { Text("Materiales y Refacciones (MXN)") },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 10.dp)
                    )

                    OutlinedTextField(
                        value = laborCost,
                        onValueChange = { laborCost = it },
                        label = { Text("Mano de Obra Certificada (MXN)") },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 16.dp)
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End
                    ) {
                        TextButton(onClick = { showQuoteModal = false }) {
                            Text("Cancelar", color = AlkiraTextSecondary)
                        }
                        Button(
                            onClick = {
                                val mat = materialsCost.toDoubleOrNull() ?: 0.0
                                val lab = laborCost.toDoubleOrNull() ?: 0.0
                                quoteAmount = String.format("%.2f", mat + lab)
                                showQuoteModal = false
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
                        ) {
                            Text("Guardar")
                        }
                    }
                }
            }
        }
    }

    // ==========================================
    // SUBVENTANA 2: Modal de Firma de Conformidad
    // ==========================================
    if (showSignatureModal) {
        Dialog(onDismissRequest = { showSignatureModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Firma de Conformidad",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "El inquilino valida la resolución satisfactoria.",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(bottom = 14.dp)
                    )

                    // Simulación de lienzo de firma
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(110.dp)
                            .background(AlkiraCanvasBg, RoundedCornerShape(8.dp))
                            .border(1.dp, AlkiraBorderStrong, RoundedCornerShape(8.dp)),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = "✍️ [Firma Digital Capturada]",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AlkiraPrimaryBlue
                            )
                            Text(
                                text = "Inquilino: Depto 402 • Token: #SIG-8942",
                                fontSize = 10.sp,
                                color = AlkiraTextSecondary
                            )
                        }
                    }

                    // Calificación
                    Spacer(modifier = Modifier.height(12.dp))
                    Text(
                        text = "Calificación del Servicio:",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraTextPrimary
                    )
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 8.dp),
                        horizontalArrangement = Arrangement.Center
                    ) {
                        (1..5).forEach { star ->
                            Text(
                                text = if (star <= rating) "★" else "☆",
                                fontSize = 28.sp,
                                color = if (star <= rating) AlkiraWarningAmber else AlkiraBorderStrong,
                                modifier = Modifier
                                    .padding(horizontal = 4.dp)
                                    .clickable { rating = star }
                            )
                        }
                    }

                    Button(
                        onClick = {
                            ticketStatus = "Concluido"
                            showSignatureModal = false
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraSuccessGreen)
                    ) {
                        Text("Confirmar y Cerrar Ticket", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
fun TicketsScreenPreview() {
    PantallasMovilTheme {
        TicketsScreen()
    }
}
