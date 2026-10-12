package com.example.pantallasmovil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.pantallasmovil.ui.theme.PantallasMovilTheme

@Composable
fun HU04_Screen(modifier: Modifier = Modifier) {
    val bgMain = Color(0xFFF8FAFC)
    val bgCard = Color(0xFFEDF2F7)
    val borderCard = Color(0xFFCBD5E1)
    val textPrimary = Color(0xFF0F172A)
    val textSecondary = Color(0xFF475569)
    val ctaTerracotta = Color(0xFFC85233)

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(bgMain)
            .padding(20.dp),
        verticalArrangement = Arrangement.SpaceBetween
    ) {
        Column {
            // Encabezado
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Validar Comprobante",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )
                Surface(
                    color = Color(0xFFFEF3C7),
                    shape = RoundedCornerShape(6.dp)
                ) {
                    Text(
                        text = "Pendiente",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color(0xFFD97706),
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            // Visualizador de Comprobante / Evidencia
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(180.dp)
                    .background(bgCard, RoundedCornerShape(12.dp))
                    .border(1.dp, borderCard, RoundedCornerShape(12.dp)),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "[ Visualizador de Comprobante / SPEI ]",
                    fontSize = 13.sp,
                    color = textSecondary
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Card: Detalle del Pago a Conciliar
            Card(
                colors = CardDefaults.cardColors(containerColor = bgCard),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .border(1.dp, borderCard, RoundedCornerShape(16.dp))
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text(
                        text = "Detalles de la Transacción",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = textSecondary,
                        modifier = Modifier.padding(bottom = 4.dp)
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Inquilino:", fontSize = 13.sp, color = textSecondary)
                        Text("Carlos Daniel Chávez", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                    }
                    HorizontalDivider(color = borderCard, thickness = 1.dp)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Unidad:", fontSize = 13.sp, color = textSecondary)
                        Text("Torre A - Depto 402", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                    }
                    HorizontalDivider(color = borderCard, thickness = 1.dp)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Monto a Conciliar:", fontSize = 13.sp, color = textSecondary)
                        Text("$3,500.00 MXN", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    }
                    HorizontalDivider(color = borderCard, thickness = 1.dp)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Clave Rastro SPEI:", fontSize = 13.sp, color = textSecondary)
                        Text("8823194012", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                    }
                }
            }
        }

        // Botones de Acción (Rechazar / Validar con CTA Terracota)
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Button(
                onClick = {},
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444)),
                shape = RoundedCornerShape(12.dp)
            ) {
                Text("Rechazar", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            }
            Button(
                onClick = {},
                modifier = Modifier.weight(2f),
                colors = ButtonDefaults.buttonColors(containerColor = ctaTerracotta),
                shape = RoundedCornerShape(12.dp)
            ) {
                Text("Validar y Emitir Recibo", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Preview(showBackground = true, showSystemUi = true)
@Composable
fun HU04_Preview() {
    PantallasMovilTheme {
        HU04_Screen()
    }
}