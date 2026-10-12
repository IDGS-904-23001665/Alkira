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
fun HU02_Screen(modifier: Modifier = Modifier) {
    val bgMain = Color(0xFFF8FAFC)
    val bgCard = Color(0xFFEDF2F7)
    val borderCard = Color(0xFFCBD5E1)
    val textPrimary = Color(0xFF0F172A)
    val textSecondary = Color(0xFF475569)
    val accentBlue = Color(0xFF2563EB)
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
                    .padding(bottom = 20.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Mi Arrendamiento",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )
                Surface(
                    color = accentBlue,
                    shape = RoundedCornerShape(6.dp)
                ) {
                    Text(
                        text = "Contrato Activo",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            // Card 1: Balance de Saldo
            Card(
                colors = CardDefaults.cardColors(containerColor = bgCard),
                shape = RoundedCornerShape(20.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .border(1.dp, borderCard, RoundedCornerShape(20.dp))
                    .padding(bottom = 16.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Saldo Total Pendiente",
                        fontSize = 12.sp,
                        color = textSecondary
                    )
                    Row(
                        verticalAlignment = Alignment.Bottom,
                        modifier = Modifier.padding(vertical = 6.dp)
                    ) {
                        Text(
                            text = "$3,500.00 ",
                            fontSize = 28.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )
                        Text(
                            text = "MXN",
                            fontSize = 14.sp,
                            color = textSecondary,
                            modifier = Modifier.padding(bottom = 4.dp)
                        )
                    }
                    Surface(
                        color = Color(0xFFFEF3C7),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Text(
                            text = "Vence: 10/Oct/2026 (Mantenimiento)",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = Color(0xFFD97706),
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        )
                    }
                }
            }

            // Card 2: Detalle del Contrato
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
                        text = "Detalles del Contrato",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = textSecondary,
                        modifier = Modifier.padding(bottom = 4.dp)
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Inmueble:", fontSize = 13.sp, color = textSecondary)
                        Text("Torre A - Depto 402", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                    }
                    HorizontalDivider(color = borderCard, thickness = 1.dp)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Vigencia:", fontSize = 13.sp, color = textSecondary)
                        Text("01/Ene/26 - 31/Dic/26", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                    }
                    HorizontalDivider(color = borderCard, thickness = 1.dp)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Renta Mensual:", fontSize = 13.sp, color = textSecondary)
                        Text("$12,500.00 MXN", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                    }
                }
            }
        }

        // Botón CTA Principal
        Button(
            onClick = {},
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = ctaTerracotta),
            shape = RoundedCornerShape(12.dp)
        ) {
            Text(
                text = "Descargar PDF del Contrato",
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Preview(showBackground = true, showSystemUi = true)
@Composable
fun HU02_Preview() {
    PantallasMovilTheme {
        HU02_Screen()
    }
}