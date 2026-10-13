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
fun HU01_Screen(modifier: Modifier = Modifier) {
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
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 20.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "ALKIRA | Propiedades",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )
                Surface(
                    color = accentBlue,
                    shape = RoundedCornerShape(6.dp)
                ) {
                    Text(
                        text = "HU01 - PWA",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            // Card 1: Estructura del Condominio
            Card(
                colors = CardDefaults.cardColors(containerColor = bgCard),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .border(1.dp, borderCard, RoundedCornerShape(16.dp))
                    .padding(bottom = 16.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Estructura del Condominio",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = textSecondary,
                        modifier = Modifier.padding(bottom = 12.dp)
                    )
                    OutlinedTextField(
                        value = "Condominio Las Torres",
                        onValueChange = {},
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 10.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedContainerColor = bgInput,
                            unfocusedContainerColor = bgInput,
                            focusedBorderColor = borderCard,
                            unfocusedBorderColor = borderCard,
                            focusedTextColor = textPrimary,
                            unfocusedTextColor = textPrimary
                        )
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = "Torre A",
                            onValueChange = {},
                            modifier = Modifier.weight(1f),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedContainerColor = bgInput,
                                unfocusedContainerColor = bgInput,
                                focusedBorderColor = borderCard,
                                unfocusedBorderColor = borderCard,
                                focusedTextColor = textPrimary,
                                unfocusedTextColor = textPrimary
                            )
                        )
                        OutlinedTextField(
                            value = "24 Unidades",
                            onValueChange = {},
                            modifier = Modifier.weight(1f),
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

            // Card 2: Parámetros del Contrato Base
            Card(
                colors = CardDefaults.cardColors(containerColor = bgCard),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .border(1.dp, borderCard, RoundedCornerShape(16.dp))
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Parámetros de Contrato Base",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = textSecondary,
                        modifier = Modifier.padding(bottom = 12.dp)
                    )
                    OutlinedTextField(
                        value = "$12,500 MXN / mes",
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
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Día de corte: 05", fontSize = 12.sp, color = textSecondary)
                        Text("Penalización: 5%", fontSize = 12.sp, color = textSecondary)
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
            Text("+ Guardar Inmueble y Configuración", color = Color.White, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Preview(showBackground = true, showSystemUi = true)
@Composable
fun HU01_Preview() {
    PantallasMovilTheme {
        HU01_Screen()
    }
}