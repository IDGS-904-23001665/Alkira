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
fun HU05_Screen(modifier: Modifier = Modifier) {
    val bgMain = Color(0xFFF8FAFC)
    val bgCard = Color(0xFFEDF2F7)
    val bgInput = Color(0xFFFFFFFF)
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
            Text(
                text = "Reportar Incidencia",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = textPrimary
            )
            Text(
                text = "Notifica fallas o problemas en áreas comunes o tu propiedad",
                fontSize = 12.sp,
                color = textSecondary,
                modifier = Modifier.padding(bottom = 20.dp)
            )

            // Card Formulario
            Card(
                colors = CardDefaults.cardColors(containerColor = bgCard),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .border(1.dp, borderCard, RoundedCornerShape(16.dp))
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Column {
                        Text(
                            text = "TÍTULO DE LA INCIDENCIA",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textSecondary,
                            modifier = Modifier.padding(bottom = 4.dp)
                        )
                        OutlinedTextField(
                            value = "Fuga de agua en estacionamiento",
                            onValueChange = {},
                            modifier = Modifier.fillMaxWidth(),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedContainerColor = bgInput,
                                unfocusedContainerColor = bgInput,
                                focusedBorderColor = borderCard,
                                unfocusedBorderColor = borderCard,
                                focusedTextColor = textPrimary,
                                unfocusedTextColor = textPrimary
                            )
                        )
                    }

                    Column {
                        Text(
                            text = "DESCRIPCIÓN DETALLADA",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textSecondary,
                            modifier = Modifier.padding(bottom = 4.dp)
                        )
                        OutlinedTextField(
                            value = "Se detectó un goteo constante en la tubería principal del cajón B-12.",
                            onValueChange = {},
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(100.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedContainerColor = bgInput,
                                unfocusedContainerColor = bgInput,
                                focusedBorderColor = borderCard,
                                unfocusedBorderColor = borderCard,
                                focusedTextColor = textPrimary,
                                unfocusedTextColor = textPrimary
                            )
                        )
                    }

                    // Adjuntar Evidencia Fotográfica
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(100.dp)
                            .background(bgInput, RoundedCornerShape(12.dp))
                            .border(1.dp, borderCard, RoundedCornerShape(12.dp)),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("", fontSize = 24.sp)
                            Text(
                                text = "Adjuntar Evidencia Fotográfica",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = accentBlue,
                                modifier = Modifier.padding(top = 4.dp)
                            )
                        }
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
                text = "Enviar Reporte",
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Preview(showBackground = true, showSystemUi = true)
@Composable
fun HU05_Preview() {
    PantallasMovilTheme {
        HU05_Screen()
    }
}