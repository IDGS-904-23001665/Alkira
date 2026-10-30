package com.alkira.movil.screens

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.ui.theme.AlkiraBlueSoft
import com.alkira.movil.ui.theme.AlkiraBorderStrong
import com.alkira.movil.ui.theme.AlkiraBorderSubtle
import com.alkira.movil.ui.theme.AlkiraCanvasBg
import com.alkira.movil.ui.theme.AlkiraDarkNavy
import com.alkira.movil.ui.theme.AlkiraDangerRed
import com.alkira.movil.ui.theme.AlkiraPrimaryBlue
import com.alkira.movil.ui.theme.AlkiraRedSoft
import com.alkira.movil.ui.theme.AlkiraTextMuted
import com.alkira.movil.ui.theme.AlkiraTextSecondary

@Composable
fun LoginScreen(
    cargando: Boolean,
    mensajeError: String?,
    onIniciarSesion: (correo: String, contrasena: String) -> Unit
) {
    var correo by remember { mutableStateOf("") }
    var contrasena by remember { mutableStateOf("") }
    var mostrarContrasena by remember { mutableStateOf(false) }

    val coloresCampo = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AlkiraPrimaryBlue,
        unfocusedBorderColor = AlkiraBorderStrong,
        focusedLabelColor = AlkiraPrimaryBlue,
        unfocusedLabelColor = AlkiraTextSecondary,
        cursorColor = AlkiraPrimaryBlue,
        focusedContainerColor = Color.White,
        unfocusedContainerColor = Color.White,
        disabledContainerColor = Color.White
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .imePadding()
            .navigationBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 22.dp, vertical = 30.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "ALKIRA",
            fontSize = 30.sp,
            fontWeight = FontWeight.Bold,
            color = AlkiraDarkNavy
        )
        Text(
            text = "Property OS · Móvil",
            fontSize = 13.sp,
            color = AlkiraTextSecondary
        )

        Spacer(modifier = Modifier.height(26.dp))

        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .widthIn(max = 440.dp),
            shape = RoundedCornerShape(24.dp),
            color = Color.White,
            border = BorderStroke(1.dp, AlkiraBorderSubtle),
            shadowElevation = 6.dp
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Espacio provisional para colocar el logotipo definitivo de ALKIRA.
                Surface(
                    shape = RoundedCornerShape(18.dp),
                    color = AlkiraBlueSoft,
                    border = BorderStroke(1.dp, AlkiraBorderSubtle),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(112.dp)
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center,
                        modifier = Modifier.padding(12.dp)
                    ) {
                        Text(
                            text = "Logo ALKIRA",
                            fontWeight = FontWeight.SemiBold,
                            color = AlkiraDarkNavy,
                            fontSize = 16.sp
                        )
                        Spacer(modifier = Modifier.height(5.dp))
                        Text(
                            text = "Coloca tu imagen en este espacio",
                            color = AlkiraTextSecondary,
                            fontSize = 12.sp,
                            textAlign = TextAlign.Center
                        )
                    }
                }

                Spacer(modifier = Modifier.height(26.dp))

                Text(
                    text = "Bienvenido de nuevo",
                    fontSize = 23.sp,
                    fontWeight = FontWeight.Bold,
                    color = AlkiraDarkNavy,
                    textAlign = TextAlign.Center
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Inicia sesión para acceder a tu comunidad.",
                    fontSize = 14.sp,
                    color = AlkiraTextSecondary,
                    textAlign = TextAlign.Center
                )

                Spacer(modifier = Modifier.height(24.dp))

                OutlinedTextField(
                    value = correo,
                    onValueChange = { correo = it },
                    label = { Text("Correo electrónico") },
                    placeholder = { Text("usuario@correo.com") },
                    keyboardOptions = KeyboardOptions(
                        keyboardType = KeyboardType.Email,
                        imeAction = ImeAction.Next
                    ),
                    singleLine = true,
                    enabled = !cargando,
                    colors = coloresCampo,
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(modifier = Modifier.height(14.dp))

                OutlinedTextField(
                    value = contrasena,
                    onValueChange = { contrasena = it },
                    label = { Text("Contraseña") },
                    placeholder = { Text("Ingresa tu contraseña") },
                    visualTransformation = if (mostrarContrasena) {
                        VisualTransformation.None
                    } else {
                        PasswordVisualTransformation()
                    },
                    keyboardOptions = KeyboardOptions(
                        keyboardType = KeyboardType.Password,
                        imeAction = ImeAction.Done
                    ),
                    trailingIcon = {
                        TextButton(
                            onClick = { mostrarContrasena = !mostrarContrasena },
                            enabled = !cargando
                        ) {
                            Text(
                                text = if (mostrarContrasena) "Ocultar" else "Mostrar",
                                fontSize = 12.sp,
                                color = AlkiraPrimaryBlue
                            )
                        }
                    },
                    singleLine = true,
                    enabled = !cargando,
                    colors = coloresCampo,
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth()
                )

                if (!mensajeError.isNullOrBlank()) {
                    Spacer(modifier = Modifier.height(16.dp))
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        color = AlkiraRedSoft,
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Text(
                            text = mensajeError,
                            color = AlkiraDangerRed,
                            fontSize = 13.sp,
                            modifier = Modifier.padding(12.dp)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(24.dp))

                Button(
                    onClick = { onIniciarSesion(correo.trim(), contrasena) },
                    enabled = !cargando && correo.isNotBlank() && contrasena.isNotBlank(),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = AlkiraPrimaryBlue,
                        contentColor = Color.White
                    ),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp)
                ) {
                    if (cargando) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = Color.White,
                                strokeWidth = 2.dp
                            )
                            Text("Ingresando...")
                        }
                    } else {
                        Text(
                            text = "Iniciar sesión",
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 16.sp
                        )
                    }
                }

                Spacer(modifier = Modifier.height(18.dp))
                Text(
                    text = "Acceso seguro a tu comunidad",
                    color = AlkiraTextMuted,
                    fontSize = 12.sp,
                    textAlign = TextAlign.Center
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))
        Text(
            text = "Administración inmobiliaria ALKIRA",
            color = AlkiraTextSecondary,
            fontSize = 12.sp,
            textAlign = TextAlign.Center
        )
    }
}
