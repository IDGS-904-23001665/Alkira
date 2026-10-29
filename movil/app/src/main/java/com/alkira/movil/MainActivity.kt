package com.alkira.movil

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ReceiptLong
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.AutenticacionApi
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.SesionMovil
import com.alkira.movil.red.UsuarioMovil
import com.alkira.movil.screens.*
import com.alkira.movil.ui.theme.*
import kotlinx.coroutines.launch
import java.io.IOException

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            PantallasMovilTheme {
                var usuarioAutenticado by remember { mutableStateOf<UsuarioMovil?>(null) }
                var cargando by remember { mutableStateOf(false) }
                var mensajeError by remember { mutableStateOf<String?>(null) }
                val alcance = rememberCoroutineScope()

                if (usuarioAutenticado == null) {
                    LoginScreen(
                        cargando = cargando,
                        mensajeError = mensajeError,
                        onIniciarSesion = { correo, contrasena ->
                            alcance.launch {
                                cargando = true
                                mensajeError = null
                                try {
                                    val respuesta = AutenticacionApi.iniciarSesion(correo, contrasena)
                                    val usuario = AutenticacionApi.verificarSesion(respuesta.token)
                                    SesionMovil.iniciar(respuesta.token)
                                    usuarioAutenticado = usuario
                                } catch (error: ErrorApi) {
                                    mensajeError = when (error.codigoHttp) {
                                        401 -> "Correo o contraseña incorrectos."
                                        else -> error.message ?: "No fue posible iniciar sesión."
                                    }
                                } catch (_: IOException) {
                                    mensajeError = "No se pudo conectar con ALKIRA. Revisa el servidor y la red Wi-Fi."
                                } catch (_: Exception) {
                                    mensajeError = "Ocurrió un error inesperado al iniciar sesión."
                                } finally {
                                    cargando = false
                                }
                            }
                        }
                    )
                } else {
                    AplicacionAutenticada(
                        usuario = usuarioAutenticado!!,
                        onCerrarSesion = {
                            SesionMovil.cerrar()
                            usuarioAutenticado = null
                            mensajeError = null
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun AplicacionAutenticada(
    usuario: UsuarioMovil,
    onCerrarSesion: () -> Unit
) {
    val drawerState = rememberDrawerState(initialValue = DrawerValue.Closed)
    val scope = rememberCoroutineScope()
    val esTecnico = usuario.tipoUsuario == "tecnico"
    var currentScreen by remember(usuario.idUsuario) {
        mutableStateOf(if (esTecnico) "MANTENIMIENTO" else "INICIO")
    }

    ModalNavigationDrawer(
        drawerState = drawerState,
        drawerContent = {
            ModalDrawerSheet(
                drawerContainerColor = AlkiraDarkNavy,
                drawerContentColor = Color.White,
                modifier = Modifier.width(300.dp)
            ) {
                Column(modifier = Modifier.padding(24.dp)) {
                    Text(
                        text = "ALKIRA",
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Text(
                        text = "Property OS • Móvil",
                        fontSize = 12.sp,
                        color = AlkiraTextMuted
                    )

                    Spacer(modifier = Modifier.height(18.dp))

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .background(AlkiraPrimaryBlue, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(usuario.nombres.take(2).uppercase(), color = Color.White, fontWeight = FontWeight.Bold)
                        }
                        Spacer(modifier = Modifier.width(12.dp))
                        Column {
                            Text("${usuario.nombres} ${usuario.apellidos}".trim(), fontWeight = FontWeight.Bold, fontSize = 14.sp, color = Color.White)
                            Text(usuario.tipoUsuario.replaceFirstChar { it.uppercase() }, fontSize = 11.sp, color = AlkiraTextMuted)
                        }
                    }
                }

                HorizontalDivider(color = AlkiraDarkNavyLight)

                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    if (!esTecnico) {
                    DrawerItem("Inicio", Icons.Outlined.Home, currentScreen == "INICIO") {
                        currentScreen = "INICIO"
                        scope.launch { drawerState.close() }
                    }
                    DrawerItem("Estado de Cuenta", Icons.AutoMirrored.Outlined.ReceiptLong, currentScreen == "PAGOS") {
                        currentScreen = "PAGOS"
                        scope.launch { drawerState.close() }
                    }
                    DrawerItem("Carga de Comprobante", Icons.Outlined.UploadFile, currentScreen == "SUBIR_PAGO") {
                        currentScreen = "SUBIR_PAGO"
                        scope.launch { drawerState.close() }
                    }
                    }
                    DrawerItem("Tickets y Servicios", Icons.Outlined.Build, currentScreen == "MANTENIMIENTO") {
                        currentScreen = "MANTENIMIENTO"
                        scope.launch { drawerState.close() }
                    }
                    if (!esTecnico) {
                    DrawerItem("Reportar Incidencia", Icons.Outlined.ReportProblem, currentScreen == "REPORTAR_FALLA") {
                        currentScreen = "REPORTAR_FALLA"
                        scope.launch { drawerState.close() }
                    }
                    DrawerItem("Comunidad y Votaciones", Icons.Outlined.HowToVote, currentScreen == "COMUNIDAD") {
                        currentScreen = "COMUNIDAD"
                        scope.launch { drawerState.close() }
                    }
                    DrawerItem("Pases de Acceso QR", Icons.Outlined.QrCodeScanner, currentScreen == "PASES_QR") {
                        currentScreen = "PASES_QR"
                        scope.launch { drawerState.close() }
                    }
                    DrawerItem("Analítica & Métricas", Icons.Outlined.Analytics, currentScreen == "METRICAS") {
                        currentScreen = "METRICAS"
                        scope.launch { drawerState.close() }
                    }
                    DrawerItem("Propiedades e Inmuebles", Icons.Outlined.Apartment, currentScreen == "PROPIEDADES") {
                        currentScreen = "PROPIEDADES"
                        scope.launch { drawerState.close() }
                    }
                    }
                    HorizontalDivider(color = AlkiraDarkNavyLight)
                    DrawerItem("Cerrar sesión", Icons.Outlined.Logout, false) {
                        scope.launch { drawerState.close() }
                        onCerrarSesion()
                    }
                }
            }
        }
    ) {
        Scaffold(
            modifier = Modifier.fillMaxSize(),
            topBar = {
                Surface(
                    color = AlkiraDarkNavy,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .statusBarsPadding()
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            IconButton(
                                onClick = { scope.launch { drawerState.open() } },
                                modifier = Modifier
                                    .size(36.dp)
                                    .background(AlkiraDarkNavyLight, RoundedCornerShape(8.dp))
                            ) {
                                Icon(
                                    imageVector = Icons.Outlined.Menu,
                                    contentDescription = "Menú",
                                    tint = Color.White,
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                            Spacer(modifier = Modifier.width(12.dp))
                            Text(
                                text = "ALKIRA",
                                fontWeight = FontWeight.Bold,
                                fontSize = 18.sp,
                                color = Color.White
                            )
                        }

                        if (!esTecnico) {
                        Surface(
                            color = if (currentScreen == "CHAT") AlkiraPrimaryBlue else AlkiraDarkNavyLight,
                            shape = RoundedCornerShape(20.dp),
                            modifier = Modifier.clickable { currentScreen = "CHAT" }
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = Icons.Outlined.AutoAwesome,
                                    contentDescription = "Asistente IA",
                                    tint = Color.White,
                                    modifier = Modifier.size(15.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(
                                    text = "Asistente IA",
                                    color = Color.White,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }
                        }
                    }
                }
            },
            bottomBar = {
                if (!esTecnico) {
                Surface(
                    color = AlkiraSurfaceWhite,
                    shadowElevation = 8.dp,
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 8.dp),
                        horizontalArrangement = Arrangement.SpaceAround,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        BottomNavItem("Inicio", Icons.Outlined.Home, currentScreen == "INICIO") { currentScreen = "INICIO" }
                        BottomNavItem("Pagos", Icons.Outlined.CreditCard, currentScreen == "PAGOS") { currentScreen = "PAGOS" }
                        BottomNavItem("Servicios", Icons.Outlined.Build, currentScreen == "MANTENIMIENTO") { currentScreen = "MANTENIMIENTO" }
                        BottomNavItem("Comunidad", Icons.Outlined.HowToVote, currentScreen == "COMUNIDAD") { currentScreen = "COMUNIDAD" }
                        BottomNavItem("Pases QR", Icons.Outlined.QrCodeScanner, currentScreen == "PASES_QR") { currentScreen = "PASES_QR" }
                    }
                }
                }
            }
        ) { innerPadding ->
            Box(modifier = Modifier.padding(innerPadding)) {
                when (if (esTecnico) "MANTENIMIENTO" else currentScreen) {
                    "INICIO" -> InicioScreen(onNavigate = { currentScreen = it })
                    "PAGOS" -> EstadoCuentaScreen()
                    "SUBIR_PAGO" -> HU03_Screen()
                    "VALIDAR_PAGOS" -> HU04_Screen()
                    "REPORTAR_FALLA" -> IncidenciasScreen()
                    "PROPIEDADES" -> HU01_Screen()
                    "MANTENIMIENTO" -> TicketsScreen(onNavigateBack = { currentScreen = "INICIO" })
                    "COMUNIDAD" -> VotacionesScreen(onNavigateBack = { currentScreen = "INICIO" })
                    "METRICAS" -> MetricasScreen(onNavigateBack = { currentScreen = "INICIO" })
                    "CHAT" -> ChatScreen(onNavigateBack = { currentScreen = "INICIO" })
                    "PASES_QR" -> PasesQrScreen(onNavigateBack = { currentScreen = "INICIO" })
                    else -> InicioScreen(onNavigate = { currentScreen = it })
                }
            }
        }
    }
}

@Composable
fun DrawerItem(title: String, icon: ImageVector, isSelected: Boolean, onClick: () -> Unit) {
    Surface(
        color = if (isSelected) AlkiraPrimaryBlue else Color.Transparent,
        shape = RoundedCornerShape(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = title,
                tint = if (isSelected) Color.White else AlkiraTextMuted,
                modifier = Modifier.size(20.dp)
            )
            Spacer(modifier = Modifier.width(12.dp))
            Text(
                text = title,
                fontSize = 13.sp,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                color = Color.White
            )
        }
    }
}

@Composable
fun BottomNavItem(label: String, icon: ImageVector, isSelected: Boolean, onClick: () -> Unit) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clickable { onClick() }
            .padding(horizontal = 8.dp, vertical = 4.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = label,
            tint = if (isSelected) AlkiraPrimaryBlue else AlkiraTextSecondary,
            modifier = Modifier.size(22.dp)
        )
        Spacer(modifier = Modifier.height(2.dp))
        Text(
            text = label,
            fontSize = 11.sp,
            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
            color = if (isSelected) AlkiraPrimaryBlue else AlkiraTextSecondary
        )
    }
}
