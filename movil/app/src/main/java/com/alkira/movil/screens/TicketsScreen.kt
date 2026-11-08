
package com.alkira.movil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalInspectionMode
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.ErrorApi
import com.alkira.movil.red.MovimientoTicket
import com.alkira.movil.red.TicketMantenimiento
import com.alkira.movil.red.TicketsApi
import com.alkira.movil.ui.theme.*
import kotlinx.coroutines.launch
import java.io.IOException

private fun nombreEstado(estado: String): String = when (estado) {
    "abierto" -> "Abierto"
    "asignado" -> "Asignado"
    "en_proceso" -> "En proceso"
    "pendiente_revision" -> "En evaluación"
    "cerrado" -> "Cerrado"
    "cancelado" -> "Cancelado"
    else -> estado.replace('_', ' ').replaceFirstChar { it.uppercase() }
}

private fun fechaLegible(fecha: String?): String =
    fecha?.replace('T', ' ')?.take(16)?.takeIf { it.isNotBlank() } ?: "Sin programar"

private fun colorEstado(estado: String): Color = when (estado) {
    "cerrado" -> AlkiraSuccessGreen
    "cancelado" -> AlkiraDangerRed
    "abierto" -> AlkiraWarningAmber
    else -> AlkiraPrimaryBlue
}

@Composable
fun TicketsScreen(
    modifier: Modifier = Modifier,
    onNavigateBack: () -> Unit = {}
) {
    var tickets by remember { mutableStateOf<List<TicketMantenimiento>>(emptyList()) }
    var cargando by remember { mutableStateOf(true) }
    var errorCarga by remember { mutableStateOf<String?>(null) }
    var numeroConsulta by remember { mutableIntStateOf(0) }
    var filtroSeleccionado by remember { mutableStateOf("Todos") }
    var idSeleccionado by remember { mutableStateOf<Int?>(null) }
    var ticketParaFirmar by remember { mutableStateOf<TicketMantenimiento?>(null) }
    var mensajeConformidad by remember { mutableStateOf<String?>(null) }
    var mensajeInicio by remember { mutableStateOf<String?>(null) }
    var errorInicio by remember { mutableStateOf<String?>(null) }
    var idIniciando by remember { mutableStateOf<Int?>(null) }
    val alcance = rememberCoroutineScope()
    var movimientos by remember { mutableStateOf<List<MovimientoTicket>>(emptyList()) }
    var cargandoMovimientos by remember { mutableStateOf(false) }
    var errorMovimientos by remember { mutableStateOf<String?>(null) }
    val vistaPrevia = LocalInspectionMode.current

    LaunchedEffect(numeroConsulta) {
        if (vistaPrevia) {
            cargando = false
            return@LaunchedEffect
        }
        cargando = true
        errorCarga = null
        try {
            tickets = TicketsApi.listar()
        } catch (error: ErrorApi) {
            tickets = emptyList()
            errorCarga = when (error.codigoHttp) {
                401 -> "Tu sesión venció. Cierra sesión e inicia de nuevo."
                403 -> "Tu usuario no tiene permiso para consultar tickets."
                else -> error.message ?: "No se pudieron cargar los tickets."
            }
        } catch (_: IOException) {
            tickets = emptyList()
            errorCarga = "No se pudo conectar con el servidor PHP. Verifica la red."
        } catch (_: Exception) {
            tickets = emptyList()
            errorCarga = "No fue posible leer los tickets recibidos."
        } finally {
            cargando = false
        }
    }

    LaunchedEffect(idSeleccionado, numeroConsulta) {
        val idTicket = idSeleccionado
        movimientos = emptyList()
        errorMovimientos = null
        if (idTicket == null || vistaPrevia) return@LaunchedEffect
        cargandoMovimientos = true
        try {
            movimientos = TicketsApi.historial(idTicket)
        } catch (error: ErrorApi) {
            errorMovimientos = error.message ?: "No se pudo consultar la bitácora."
        } catch (_: IOException) {
            errorMovimientos = "No se pudo conectar para consultar la bitácora."
        } catch (_: Exception) {
            errorMovimientos = "No fue posible leer la bitácora."
        } finally {
            cargandoMovimientos = false
        }
    }

    val ticketsFiltrados = tickets.filter { ticket ->
        when (filtroSeleccionado) {
            "Pendientes" -> ticket.estado == "abierto"
            "En atención" -> ticket.estado in setOf("asignado", "en_proceso", "pendiente_revision")
            "Concluidos" -> ticket.estado == "cerrado"
            else -> true
        }
    }

    ticketParaFirmar?.let { ticket ->
        FirmaConformidadDialog(
            ticket = ticket,
            onCerrar = { ticketParaFirmar = null },
            onFirmaRegistrada = { mensaje ->
                ticketParaFirmar = null
                mensajeConformidad = mensaje
                numeroConsulta += 1
            }
        )
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AlkiraCanvasBg)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp, vertical = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text("Gestión de Mantenimiento", fontSize = 18.sp,
                    fontWeight = FontWeight.Bold, color = AlkiraTextPrimary)
                Text("Seguimiento de tus tickets y visitas técnicas",
                    fontSize = 12.sp, color = AlkiraTextSecondary)
            }
            TextButton(onClick = { numeroConsulta += 1 }, enabled = !cargando) {
                Text("Actualizar", color = AlkiraPrimaryBlue)
            }
        }

        if (mensajeConformidad != null) {
            TarjetaAviso(
                mensajeConformidad.orEmpty(),
                AlkiraBlueSoft,
                AlkiraPrimaryBlue
            )
        }

        if (mensajeInicio != null) {
            TarjetaAviso(
                mensajeInicio.orEmpty(),
                AlkiraBlueSoft,
                AlkiraPrimaryBlue
            )
        }

        if (errorInicio != null) {
            TarjetaAviso(
                errorInicio.orEmpty(),
                AlkiraRedSoft,
                AlkiraDangerRed
            )
        }

        Row(
            modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            listOf("Todos", "Pendientes", "En atención", "Concluidos").forEach { filtro ->
                val seleccionado = filtro == filtroSeleccionado
                FilterChip(
                    selected = seleccionado,
                    onClick = { filtroSeleccionado = filtro },
                    label = { Text(filtro, fontSize = 12.sp) },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = AlkiraBlueSoft,
                        selectedLabelColor = AlkiraPrimaryBlue
                    )
                )
            }
        }

        if (cargando) {
            Box(Modifier.fillMaxWidth().padding(28.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = AlkiraPrimaryBlue)
            }
        } else if (errorCarga != null) {
            TarjetaAviso(errorCarga ?: "Ocurrió un error.", AlkiraRedSoft, AlkiraDangerRed)
        } else if (ticketsFiltrados.isEmpty()) {
            TarjetaAviso(
                if (tickets.isEmpty()) "Aún no tienes tickets de mantenimiento asignados o reportados."
                else "No hay tickets en este filtro.",
                AlkiraSurfaceWhite, AlkiraTextSecondary
            )
        } else {
            Text(
                "${ticketsFiltrados.size} ticket(s)",
                fontSize = 12.sp,
                color = AlkiraTextSecondary
            )

            ticketsFiltrados.forEach { ticket ->
                Card(
                    modifier = Modifier.fillMaxWidth().border(
                        1.dp, AlkiraBorderSubtle, RoundedCornerShape(14.dp)
                    ),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = AlkiraSurfaceWhite)
                ) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(9.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Ticket #${ticket.idTicket}", fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold, color = AlkiraTextSecondary)
                            Surface(color = colorEstado(ticket.estado).copy(alpha = 0.10f),
                                shape = RoundedCornerShape(20.dp)) {
                                Text(nombreEstado(ticket.estado), fontSize = 11.sp,
                                    color = colorEstado(ticket.estado), fontWeight = FontWeight.Bold,
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                            }
                        }

                        Text(ticket.titulo, fontSize = 15.sp, fontWeight = FontWeight.Bold,
                            color = AlkiraTextPrimary)
                        Text(ticket.descripcion, fontSize = 12.sp, color = AlkiraTextSecondary)
                        Text("Prioridad: ${ticket.urgencia.replaceFirstChar { it.uppercase() }}",
                            fontSize = 12.sp, color = AlkiraTextSecondary)

                        val ubicacion = listOfNotNull(ticket.nombreInmueble, ticket.numeroUnidad)
                            .filter { it.isNotBlank() }.joinToString(" · ")
                        if (ubicacion.isNotBlank()) {
                            Text("Ubicación: $ubicacion", fontSize = 12.sp,
                                color = AlkiraTextSecondary)
                        }

                        HorizontalDivider(color = AlkiraBorderSubtle)
                        Text("Técnico: ${ticket.nombreTecnico ?: "Sin asignar"}",
                            fontSize = 13.sp, color = AlkiraTextPrimary)
                        Text("Visita programada: ${fechaLegible(ticket.fechaProgramada)}",
                            fontSize = 12.sp, color = AlkiraTextSecondary)
                        if (!ticket.descripcionTrabajo.isNullOrBlank()) {
                            Text("Trabajo: ${ticket.descripcionTrabajo}",
                                fontSize = 12.sp, color = AlkiraTextSecondary)
                        }

                        if (ticket.puedeIniciar) {
                            Button(
                                onClick = {
                                    if (idIniciando == null) {
                                        idIniciando = ticket.idTicket
                                        mensajeInicio = null
                                        errorInicio = null
                                        alcance.launch {
                                            try {
                                                mensajeInicio = TicketsApi.iniciarTrabajo(ticket.idTicket)
                                                numeroConsulta += 1
                                            } catch (error: ErrorApi) {
                                                errorInicio = when (error.codigoHttp) {
                                                    401 -> "Tu sesión venció. Vuelve a iniciar sesión."
                                                    403 -> "No tienes permiso para iniciar este trabajo."
                                                    404 -> "Este ticket no está asignado a tu usuario."
                                                    409 -> error.message ?: "Este ticket ya no puede iniciarse."
                                                    else -> error.message ?: "No fue posible iniciar el trabajo."
                                                }
                                            } catch (_: IOException) {
                                                errorInicio = "No se pudo conectar con el servidor PHP."
                                            } catch (_: Exception) {
                                                errorInicio = "Ocurrió un error al iniciar el trabajo."
                                            } finally {
                                                idIniciando = null
                                            }
                                        }
                                    }
                                },
                                modifier = Modifier.fillMaxWidth(),
                                enabled = idIniciando == null && !cargando,
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = AlkiraPrimaryBlue
                                )
                            ) {
                                if (idIniciando == ticket.idTicket) {
                                    CircularProgressIndicator(
                                        modifier = Modifier.size(18.dp),
                                        color = Color.White,
                                        strokeWidth = 2.dp
                                    )
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("Iniciando trabajo...")
                                } else {
                                    Text("Iniciar trabajo")
                                }
                            }
                        }

                        if (ticket.puedeFirmar) {
                            Button(
                                onClick = { ticketParaFirmar = ticket },
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(containerColor = AlkiraPrimaryBlue)
                            ) {
                                Text("Firmar conformidad")
                            }
                        } else if (ticket.tieneFirma) {
                            Text(
                                "La conformidad ya fue registrada. Pendiente de revisión administrativa.",
                                color = AlkiraTextSecondary,
                                fontSize = 12.sp
                            )
                        }

                        TextButton(onClick = {
                            idSeleccionado = if (idSeleccionado == ticket.idTicket) null else ticket.idTicket
                        }) {
                            Text(if (idSeleccionado == ticket.idTicket) "Ocultar bitácora"
                            else "Ver bitácora de estados", color = AlkiraPrimaryBlue)
                        }

                        if (idSeleccionado == ticket.idTicket) {
                            HorizontalDivider(color = AlkiraBorderSubtle)
                            Text("Historial del ticket", fontWeight = FontWeight.SemiBold,
                                color = AlkiraTextPrimary, fontSize = 13.sp)
                            if (cargandoMovimientos) {
                                CircularProgressIndicator(modifier = Modifier.size(24.dp),
                                    color = AlkiraPrimaryBlue, strokeWidth = 2.dp)
                            } else if (errorMovimientos != null) {
                                Text(errorMovimientos.orEmpty(), color = AlkiraDangerRed, fontSize = 12.sp)
                            } else if (movimientos.isEmpty()) {
                                Text("Todavía no hay movimientos registrados desde que se habilitó la bitácora.",
                                    fontSize = 12.sp, color = AlkiraTextSecondary)
                            } else {
                                movimientos.forEach { movimiento ->
                                    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                        Text(nombreEstado(movimiento.estadoNuevo),
                                            fontWeight = FontWeight.SemiBold, fontSize = 12.sp,
                                            color = colorEstado(movimiento.estadoNuevo))
                                        Text(
                                            "${fechaLegible(movimiento.fechaCambio)} · " +
                                                    (movimiento.estadoAnterior?.let {
                                                        "Antes: ${nombreEstado(it)}"
                                                    } ?: "Registro inicial"),
                                            fontSize = 11.sp, color = AlkiraTextSecondary
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        Text(
            "La firma envía el ticket a evaluación; el cierre definitivo corresponde a administración.",
            fontSize = 11.sp,
            color = AlkiraTextMuted
        )
    }
}

@Composable
private fun TarjetaAviso(mensaje: String, fondo: Color, colorTexto: Color) {
    Card(colors = CardDefaults.cardColors(containerColor = fondo),
        modifier = Modifier.fillMaxWidth()) {
        Text(mensaje, modifier = Modifier.padding(18.dp), color = colorTexto, fontSize = 13.sp)
    }
}
