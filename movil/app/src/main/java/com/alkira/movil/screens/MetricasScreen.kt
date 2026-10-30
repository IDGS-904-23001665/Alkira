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
fun MetricasScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var selectedPeriod by remember { mutableStateOf("Octubre 2026") }
    var showKddDetailModal by remember { mutableStateOf(false) }
    var selectedUnitDetail by remember { mutableStateOf("Depto 204") }
    var selectedUnitRisk by remember { mutableStateOf("Alto (Atraso en 3er mes consecutivo)") }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .padding(horizontal = 20.dp, vertical = 18.dp)
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
                    text = "Analítica y Métricas KDD",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Detección Temprana y Proyecciones",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(top = 2.dp)
                )
            }

            Surface(
                color = AlkiraGreenSoft,
                shape = RoundedCornerShape(20.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraSuccessGreen.copy(alpha = 0.25f))
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(modifier = Modifier.size(6.dp).background(AlkiraSuccessGreen, CircleShape))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = "KDD Online",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraGreenText
                    )
                }
            }
        }

        // Selector de Periodo tipo Segmented Control
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(AlkiraSurfaceWhite, RoundedCornerShape(10.dp))
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(10.dp))
                .padding(4.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            listOf("Septiembre", "Octubre 2026", "Año Completo").forEach { period ->
                val isSelected = selectedPeriod == period
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .background(
                            if (isSelected) AlkiraDarkNavy else Color.Transparent,
                            RoundedCornerShape(7.dp)
                        )
                        .clickable { selectedPeriod = period }
                        .padding(vertical = 8.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = period,
                        fontSize = 11.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                        color = if (isSelected) Color.White else AlkiraTextSecondary
                    )
                }
            }
        }

        // Grid 2x2 de KPIs Ejecutivos
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // KPI 1: Cobranza Efectiva
                Card(
                    colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier
                        .weight(1f)
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(12.dp))
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Cobranza Efectiva",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = AlkiraTextSecondary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "87.4%",
                            fontSize = 24.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraTextPrimary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Surface(
                            color = AlkiraGreenSoft,
                            shape = RoundedCornerShape(4.dp)
                        ) {
                            Text(
                                text = "+3.2% vs mes anterior",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AlkiraGreenText,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                            )
                        }
                    }
                }

                // KPI 2: Riesgo de Morosidad
                Card(
                    colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier
                        .weight(1f)
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(12.dp))
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Riesgo Morosidad",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = AlkiraTextSecondary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "12.6%",
                            fontSize = 24.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraWarningAmber
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Surface(
                            color = AlkiraAmberSoft,
                            shape = RoundedCornerShape(4.dp)
                        ) {
                            Text(
                                text = "3 unidades en alerta",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AlkiraAmberText,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                            )
                        }
                    }
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // KPI 3: Gastos / Presupuesto
                Card(
                    colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier
                        .weight(1f)
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(12.dp))
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Gastos Realizados",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = AlkiraTextSecondary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "$84.2k",
                            fontSize = 24.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraTextPrimary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "de $90k presupuestado",
                            fontSize = 10.sp,
                            color = AlkiraTextSecondary
                        )
                    }
                }

                // KPI 4: Resolución Tickets
                Card(
                    colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier
                        .weight(1f)
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(12.dp))
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Resolución Tickets",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = AlkiraTextSecondary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "92%",
                            fontSize = 24.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraTextPrimary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "En tiempo y forma",
                            fontSize = 10.sp,
                            color = AlkiraTextSecondary
                        )
                    }
                }
            }
        }

        // Panel de Detección Temprana KDD
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp))
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = "Patrones Detectados por KDD",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                // Item 1: Dpto 204
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(AlkiraCanvasBg, RoundedCornerShape(10.dp))
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(10.dp))
                        .clickable {
                            selectedUnitDetail = "Depto 204"
                            selectedUnitRisk = "Riesgo Alto • 3 pagos tardíos consecutivos con fecha > día 15."
                            showKddDetailModal = true
                        }
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier.size(34.dp).background(AlkiraAmberSoft, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("DW", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = AlkiraAmberText)
                        }
                        Spacer(modifier = Modifier.width(10.dp))
                        Column {
                            Text("Daniel Wong • Depto 204", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = AlkiraTextPrimary)
                            Text("Retraso recurrente estimado", fontSize = 11.sp, color = AlkiraTextSecondary)
                        }
                    }
                    Surface(
                        color = AlkiraAmberSoft,
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = "Revisión",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraAmberText,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Item 2: Dpto 310
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(AlkiraCanvasBg, RoundedCornerShape(10.dp))
                        .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(10.dp))
                        .clickable {
                            selectedUnitDetail = "Depto 310"
                            selectedUnitRisk = "Riesgo Moderado • Inconsistencia en importes conciliados de mantenimiento."
                            showKddDetailModal = true
                        }
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier.size(34.dp).background(AlkiraBlueSoft, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("SK", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = AlkiraPrimaryBlue)
                        }
                        Spacer(modifier = Modifier.width(10.dp))
                        Column {
                            Text("Sophia Kim • Depto 310", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = AlkiraTextPrimary)
                            Text("Desviación de cuota", fontSize = 11.sp, color = AlkiraTextSecondary)
                        }
                    }
                    Surface(
                        color = AlkiraBlueSoft,
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = "Analizado",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraBlueBadgeText,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )
                    }
                }
            }
        }

        // Botón CTA Principal
        Button(
            onClick = { /* Exportar reporte */ },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 12.dp),
            shape = RoundedCornerShape(8.dp),
            colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
        ) {
            Text(
                text = "Exportar Informe Analítico",
                fontWeight = FontWeight.Bold,
                fontSize = 13.sp,
                color = Color.White
            )
        }
    }

    // ==========================================
    // SUBVENTANA: Modal de Detalle KDD por Unidad
    // ==========================================
    if (showKddDetailModal) {
        Dialog(onDismissRequest = { showKddDetailModal = false }) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = AlkiraSurfaceWhite,
                modifier = Modifier.fillMaxWidth().padding(16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Diagnóstico KDD: $selectedUnitDetail",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Patrón predictivo identificado mediante minería:",
                        fontSize = 12.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(top = 4.dp, bottom = 12.dp)
                    )

                    Surface(
                        color = AlkiraCanvasBg,
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = selectedUnitRisk,
                            fontSize = 12.sp,
                            color = AlkiraTextPrimary,
                            modifier = Modifier.padding(12.dp)
                        )
                    }

                    Spacer(modifier = Modifier.height(12.dp))
                    Text(
                        text = "Sugerencia del Sistema:",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Enviar recordatorio automático vía WhatsApp 3 días antes de la fecha de corte.",
                        fontSize = 11.sp,
                        color = AlkiraTextSecondary,
                        modifier = Modifier.padding(top = 2.dp, bottom = 16.dp)
                    )

                    Button(
                        onClick = { showKddDetailModal = false },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
                    ) {
                        Text(
                            text = "Cerrar Diagnóstico",
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                    }
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
fun MetricasScreenPreview() {
    PantallasMovilTheme {
        MetricasScreen()
    }
}
