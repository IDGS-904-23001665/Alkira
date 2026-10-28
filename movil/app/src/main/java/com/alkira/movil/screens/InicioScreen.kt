package com.alkira.movil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.ui.theme.*

@Composable
fun InicioScreen(
    modifier: Modifier = Modifier,
    onNavigate: (String) -> Unit = {}
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .padding(horizontal = 20.dp, vertical = 16.dp)
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Banner de Bienvenida y Estado del Condominio
        Surface(
            color = AlkiraDarkNavy,
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(modifier = Modifier.padding(20.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Condominio Las Torres",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Text(
                            text = "Torre B • Depto 402",
                            fontSize = 12.sp,
                            color = AlkiraTextMuted
                        )
                    }
                    Surface(
                        color = AlkiraSuccessGreen,
                        shape = RoundedCornerShape(20.dp)
                    ) {
                        Text(
                            text = "Al Corriente",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Surface(
                        color = AlkiraDarkNavyLight,
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier
                            .weight(1f)
                            .clickable { onNavigate("PAGOS") }
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Próximo Pago", fontSize = 11.sp, color = AlkiraTextMuted)
                            Text("$3,500 MXN", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color.White)
                            Text("Vence 15 Oct", fontSize = 10.sp, color = AlkiraWarningAmber)
                        }
                    }

                    Surface(
                        color = AlkiraDarkNavyLight,
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier
                            .weight(1f)
                            .clickable { onNavigate("PASES_QR") }
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Pase de Acceso", fontSize = 11.sp, color = AlkiraTextMuted)
                            Text("1 Activo", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color.White)
                            Text("Mariana L. (Hoy)", fontSize = 10.sp, color = AlkiraPrimaryBlue)
                        }
                    }
                }
            }
        }

        // Accesos Directos
        Text(
            text = "Acciones Frecuentes",
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = AlkiraTextPrimary
        )

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Surface(
                color = AlkiraSurfaceWhite,
                shape = RoundedCornerShape(12.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderSubtle),
                modifier = Modifier
                    .weight(1f)
                    .clickable { onNavigate("PASES_QR") }
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .background(AlkiraBlueSoft, CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.QrCodeScanner,
                            contentDescription = "Pase QR",
                            tint = AlkiraPrimaryBlue,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Generar QR",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraTextPrimary
                    )
                }
            }

            Surface(
                color = AlkiraSurfaceWhite,
                shape = RoundedCornerShape(12.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderSubtle),
                modifier = Modifier
                    .weight(1f)
                    .clickable { onNavigate("SUBIR_PAGO") }
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .background(AlkiraGreenSoft, CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.UploadFile,
                            contentDescription = "Subir Pago",
                            tint = AlkiraSuccessGreen,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Subir Pago",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraTextPrimary
                    )
                }
            }

            Surface(
                color = AlkiraSurfaceWhite,
                shape = RoundedCornerShape(12.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderSubtle),
                modifier = Modifier
                    .weight(1f)
                    .clickable { onNavigate("REPORTAR_FALLA") }
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .background(AlkiraAmberSoft, CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.ReportProblem,
                            contentDescription = "Reportar Avería",
                            tint = AlkiraWarningAmber,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Avería",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraTextPrimary
                    )
                }
            }
        }

        // Aviso Comunitario Destacado
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp))
                .clickable { onNavigate("COMUNIDAD") }
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Surface(
                        color = AlkiraBlueSoft,
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = "Votación Abierta",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = AlkiraBlueBadgeText,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                        )
                    }
                    Text("Cierra en 48h", fontSize = 11.sp, color = AlkiraTextSecondary)
                }
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Instalación de Luminarias Solares LED",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraTextPrimary
                )
                Text(
                    text = "Participa en la asamblea virtual para autorizar el presupuesto.",
                    fontSize = 12.sp,
                    color = AlkiraTextSecondary,
                    modifier = Modifier.padding(top = 2.dp)
                )
            }
        }

        // Estado de Mantenimiento Activo
        Card(
            colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .border(1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp))
                .clickable { onNavigate("MANTENIMIENTO") }
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Ticket #TK-2026-084 • En Proceso",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraWarningAmber
                    )
                    Text(
                        text = "Fuga en tubería principal de baño",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = AlkiraTextPrimary
                    )
                    Text(
                        text = "Téc. Roberto M. • Cotización: $1,450 MXN",
                        fontSize = 11.sp,
                        color = AlkiraTextSecondary
                    )
                }
                Surface(
                    color = AlkiraCanvasBg,
                    shape = RoundedCornerShape(6.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, AlkiraBorderSubtle)
                ) {
                    Text(
                        text = "Ver →",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AlkiraPrimaryBlue,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
fun InicioScreenPreview() {
    PantallasMovilTheme {
        InicioScreen()
    }
}
