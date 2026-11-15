
package com.alkira.movil.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.MiViviendaApi
import com.alkira.movil.red.ViviendaHu01
import com.alkira.movil.red.NotificacionHu01
import com.alkira.movil.red.RespuestaViviendaHu01

private val Azul = Color(0xFF2563EB)
private val AzulOscuro = Color(0xFF0F172A)
private val Gris = Color(0xFF64748B)
private val Fondo = Color(0xFFF8FAFC)
private val AzulSuave = Color(0xFFEFF6FF)
private val Borde = Color(0xFFE2E8F0)

@Composable
fun HU01_Screen(modifier: Modifier = Modifier) {

    var respuesta by remember {
        mutableStateOf<RespuestaViviendaHu01?>(null)
    }

    var cargando by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var recarga by remember { mutableIntStateOf(0) }

    LaunchedEffect(recarga) {
        cargando = true
        error = null

        try {
            respuesta = MiViviendaApi.consultar()
        } catch (e: Exception) {
            respuesta = null
            error = e.message ?: "No se pudo cargar tu vivienda."
        } finally {
            cargando = false
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Fondo)
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(18.dp)
    ) {

        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column {
                Text(
                    "ALKIRA · MI HOGAR",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = Azul
                )

                Spacer(Modifier.height(5.dp))

                Text(
                    "Mi vivienda",
                    fontSize = 26.sp,
                    fontWeight = FontWeight.Bold,
                    color = AzulOscuro
                )

                Text(
                    "Todo sobre el lugar donde vives",
                    fontSize = 13.sp,
                    color = Gris
                )
            }

            TextButton(
                onClick = { recarga++ },
                enabled = !cargando
            ) {
                Text("Actualizar", color = Azul)
            }
        }

        if (cargando) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(36.dp),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator(color = Azul)
            }
        }

        error?.let { mensaje ->
            TarjetaSimple("No pudimos cargar tu vivienda") {
                Text(mensaje, color = Gris)
                Spacer(Modifier.height(8.dp))
                Button(onClick = { recarga++ }) {
                    Text("Reintentar")
                }
            }
        }

        respuesta?.let { datos ->

            if (datos.viviendas.isEmpty()) {
                TarjetaSimple("Tu vivienda") {
                    Text(
                        "Todavía no tienes una vivienda asignada.",
                        color = Gris
                    )
                    Text(
                        "Si esto no es correcto, contacta " +
                            "a la administración.",
                        fontSize = 13.sp,
                        color = Gris
                    )
                }
            } else {

                datos.viviendas.forEach { vivienda ->

                    TarjetaVivienda(vivienda)

                    Text(
                        "Información de mi hogar",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = AzulOscuro
                    )

                    if (vivienda.descripcion.isNotBlank()) {
                        SeccionDesplegable(
                            simbolo = "⌂",
                            titulo = "Características y equipamiento",
                            descripcion = "Lo que incluye tu vivienda"
                        ) {
                            Text(
                                vivienda.descripcion,
                                color = AzulOscuro,
                                fontSize = 14.sp,
                                lineHeight = 21.sp
                            )
                        }
                    }

                    SeccionDesplegable(
                        simbolo = "✦",
                        titulo = "Servicios incluidos",
                        descripcion = "Servicios de tu propiedad"
                    ) {
                        Text(
                            vivienda.serviciosIncluidos.ifBlank {
                                "No hay servicios adicionales registrados."
                            },
                            color = AzulOscuro,
                            fontSize = 14.sp,
                            lineHeight = 21.sp
                        )
                    }

                    SeccionDesplegable(
                        simbolo = "⌖",
                        titulo = "Ubicación de mi vivienda",
                        descripcion = "Dirección y ubicación"
                    ) {
                        TextoDetalle(
                            "Dirección",
                            vivienda.direccion
                        )
                        TextoDetalle(
                            "Ciudad",
                            vivienda.ciudad
                        )
                        TextoDetalle(
                            "Tipo de vivienda",
                            vivienda.tipoUnidad
                        )
                    }
                }
            }

            SeccionDesplegable(
                simbolo = "●",
                titulo = "Mis avisos",
                descripcion = if (datos.notificaciones.isEmpty()) {
                    "No tienes avisos nuevos"
                } else {
                    "${datos.notificaciones.size} avisos disponibles"
                }
            ) {
                ListaAvisos(datos.notificaciones)
            }
        }

        Spacer(Modifier.height(12.dp))
    }
}

@Composable
private fun TarjetaVivienda(vivienda: ViviendaHu01) {

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = AzulSuave
        )
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Surface(
                    color = Color.White,
                    shape = RoundedCornerShape(20.dp)
                ) {
                    Text(
                        "MI RESIDENCIA",
                        modifier = Modifier.padding(
                            horizontal = 12.dp,
                            vertical = 7.dp
                        ),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = Azul
                    )
                }

                Text(
                    "⌂",
                    fontSize = 27.sp,
                    color = Azul
                )
            }

            Text(
                vivienda.condominio.ifBlank {
                    vivienda.inmueble.ifBlank { "Mi hogar" }
                },
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = AzulOscuro
            )

            Text(
                listOf(
                    "Unidad ${vivienda.numeroUnidad}",
                    vivienda.inmueble.takeIf {
                        it.isNotBlank() &&
                            it != vivienda.condominio
                    }
                ).filterNotNull().joinToString(" · "),
                fontSize = 14.sp,
                color = Gris,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            HorizontalDivider(color = Borde)

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                DatoRapido(
                    titulo = "Superficie",
                    valor = vivienda.superficie
                        .takeIf { it.isNotBlank() }
                        ?.let { "$it m²" } ?: "—",
                    modifier = Modifier.weight(1f)
                )

                DatoRapido(
                    titulo = "Cajones",
                    valor = vivienda.estacionamientos.toString(),
                    modifier = Modifier.weight(1f)
                )

                DatoRapido(
                    titulo = "Bodega",
                    valor = if (vivienda.tieneBodega) {
                        "Sí"
                    } else {
                        "No"
                    },
                    modifier = Modifier.weight(1f)
                )
            }

            val ubicacion = listOf(
                vivienda.direccion,
                vivienda.ciudad
            ).filter { it.isNotBlank() }
                .joinToString(", ")

            if (ubicacion.isNotBlank()) {
                Text(
                    "⌖  $ubicacion",
                    fontSize = 12.sp,
                    color = Gris,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}

@Composable
private fun DatoRapido(
    titulo: String,
    valor: String,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier,
        color = Color.White,
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier.padding(
                horizontal = 6.dp,
                vertical = 12.dp
            ),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                valor,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = AzulOscuro,
                maxLines = 1
            )

            Spacer(Modifier.height(3.dp))

            Text(
                titulo,
                fontSize = 10.sp,
                color = Gris,
                maxLines = 1
            )
        }
    }
}

@Composable
private fun SeccionDesplegable(
    simbolo: String,
    titulo: String,
    descripcion: String,
    contenido: @Composable ColumnScope.() -> Unit
) {
    var expandida by remember { mutableStateOf(false) }

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White
        ),
        elevation = CardDefaults.cardElevation(
            defaultElevation = 1.dp
        )
    ) {
        Column {

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { expandida = !expandida }
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {

                Surface(
                    color = AzulSuave,
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Box(
                        modifier = Modifier.size(46.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            simbolo,
                            fontSize = 22.sp,
                            color = Azul
                        )
                    }
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        titulo,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AzulOscuro
                    )

                    Spacer(Modifier.height(3.dp))

                    Text(
                        descripcion,
                        fontSize = 12.sp,
                        color = Gris
                    )
                }

                Text(
                    if (expandida) "⌃" else "⌄",
                    fontSize = 22.sp,
                    color = Gris
                )
            }

            if (expandida) {
                HorizontalDivider(
                    modifier = Modifier.padding(
                        horizontal = 14.dp
                    ),
                    color = Borde
                )

                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement =
                        Arrangement.spacedBy(10.dp)
                ) {
                    contenido()
                }
            }
        }
    }
}

@Composable
private fun TextoDetalle(
    etiqueta: String,
    valor: String
) {
    Column {
        Text(
            etiqueta,
            fontSize = 12.sp,
            color = Gris
        )

        Spacer(Modifier.height(3.dp))

        Text(
            valor.ifBlank { "No especificado" },
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            color = AzulOscuro
        )
    }
}

@Composable
private fun ListaAvisos(
    avisos: List<NotificacionHu01>
) {
    if (avisos.isEmpty()) {
        Text(
            "No tienes avisos registrados.",
            fontSize = 14.sp,
            color = Gris
        )
    } else {
        avisos.forEachIndexed { indice, aviso ->

            Column(
                verticalArrangement =
                    Arrangement.spacedBy(5.dp)
            ) {
                Text(
                    aviso.titulo,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AzulOscuro
                )

                Text(
                    aviso.mensaje,
                    fontSize = 13.sp,
                    color = Gris
                )

                if (aviso.fecha.isNotBlank()) {
                    Text(
                        aviso.fecha,
                        fontSize = 11.sp,
                        color = Azul
                    )
                }
            }

            if (indice < avisos.lastIndex) {
                HorizontalDivider(color = Borde)
            }
        }
    }
}

@Composable
private fun TarjetaSimple(
    titulo: String,
    contenido: @Composable ColumnScope.() -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White
        )
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement =
                Arrangement.spacedBy(10.dp)
        ) {
            Text(
                titulo,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = AzulOscuro
            )

            contenido()
        }
    }
}
