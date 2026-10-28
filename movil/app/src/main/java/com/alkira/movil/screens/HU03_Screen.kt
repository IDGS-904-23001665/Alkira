package com.alkira.movil.screens

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
import com.alkira.movil.ui.theme.PantallasMovilTheme

@Composable
fun HU03_Screen(modifier: Modifier = Modifier) {
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
                text = "Registrar Pago",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = textPrimary
            )
            Text(
                text = "Sube la foto o PDF de tu transferencia",
                fontSize = 12.sp,
                color = textSecondary,
                modifier = Modifier.padding(bottom = 20.dp)
            )

            // Card 1: Carga de Archivo
            Card(
                colors = CardDefaults.cardColors(containerColor = bgCard),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .border(2.dp, accentBlue, RoundedCornerShape(16.dp))
                    .padding(bottom = 20.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(28.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text("", fontSize = 32.sp)
                    Text(
                        text = "Arrastra o selecciona el archivo",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = textPrimary,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                    Text(
                        text = "Formatos permitidos: JPG, PNG, PDF (Máx 5MB)",
                        fontSize = 11.sp,
                        color = textSecondary,
                        modifier = Modifier.padding(top = 4.dp)
                    )
                }
            }

            // Card 2: Datos del Registro
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
                            text = "CONCEPTO",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textSecondary,
                            modifier = Modifier.padding(bottom = 4.dp)
                        )
                        OutlinedTextField(
                            value = "Mantenimiento Octubre ($3,500)",
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
                            text = "MONTO DEPOSITADO",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textSecondary,
                            modifier = Modifier.padding(bottom = 4.dp)
                        )
                        OutlinedTextField(
                            value = "$3,500.00 MXN",
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
                text = "Enviar Comprobante",
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Preview(showBackground = true, showSystemUi = true)
@Composable
fun HU03_Preview() {
    PantallasMovilTheme {
        HU03_Screen()
    }
}