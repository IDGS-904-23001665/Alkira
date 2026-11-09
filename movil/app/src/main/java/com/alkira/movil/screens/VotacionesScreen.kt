package com.alkira.movil.screens

import android.text.Html
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.HowToVote
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.alkira.movil.red.*
import com.alkira.movil.ui.theme.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.time.LocalDateTime

/** HU07.1 + HU07.2. Reemplaza la antigua pantalla con cifras simuladas. */
@Composable
fun VotacionesScreen(modifier: Modifier=Modifier, onNavigateBack: () -> Unit={}) {
    var pestana by remember { mutableIntStateOf(0) }
    var avisos by remember { mutableStateOf<List<AvisoComunidad>>(emptyList()) }
    var votaciones by remember { mutableStateOf<List<EncuestaComunidad>>(emptyList()) }
    var cargando by remember { mutableStateOf(true) }
    var guardando by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var mensaje by remember { mutableStateOf<String?>(null) }
    var confirmar by remember { mutableStateOf<Triple<Int,Int,Int>?>(null) }
    val scope=rememberCoroutineScope()

    suspend fun actualizar(silencioso:Boolean=false){
        if(!silencioso)cargando=true
        try {
            avisos=ComunidadApi.avisos();votaciones=ComunidadApi.votaciones();error=null
        }catch(e:CancellationException){throw e}
        catch(e:Exception){if(!silencioso)error=e.message?:"No se pudieron cargar los avisos."}
        finally{cargando=false}
    }
    LaunchedEffect(Unit) { actualizar() }
    // Los resultados se consultan periódicamente; el servidor es la autoridad.
    LaunchedEffect(pestana) { while (isActive) { delay(10_000); actualizar(true) } }

    Column(modifier.fillMaxSize().background(AlkiraCanvasBg).padding(18.dp)) {
        Text("Comunidad",fontSize=22.sp,fontWeight=FontWeight.Bold,color=AlkiraTextPrimary)
        Text("Avisos y votaciones de tu condominio",fontSize=12.sp,color=AlkiraTextSecondary)
        Spacer(Modifier.height(16.dp))
        TabRow(selectedTabIndex=pestana,containerColor=Color.White,contentColor=AlkiraPrimaryBlue){
            Tab(selected=pestana==0,onClick={pestana=0},text={Text("Centro de avisos")},icon={Icon(Icons.Outlined.Notifications,null)})
            Tab(selected=pestana==1,onClick={pestana=1},text={Text("Votaciones")},icon={Icon(Icons.Outlined.HowToVote,null)})
        }
        Spacer(Modifier.height(12.dp))
        Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween,verticalAlignment=Alignment.CenterVertically){
            val n=avisos.count{!it.leido}
            Text(if(pestana==0)"${n} sin leer" else "Un voto por unidad",fontSize=12.sp,color=AlkiraTextSecondary)
            TextButton(onClick={scope.launch{actualizar()}},enabled=!cargando){Text("Actualizar",color=AlkiraPrimaryBlue)}
        }
        if(cargando)LinearProgressIndicator(Modifier.fillMaxWidth(),color=AlkiraPrimaryBlue)
        error?.let { Text(it,color=AlkiraDangerRed,fontSize=13.sp) }
        mensaje?.let { Text(it,color=AlkiraSuccessGreen,fontSize=13.sp) }
        Spacer(Modifier.height(8.dp))
        if(pestana==0) {
            LazyColumn(verticalArrangement=Arrangement.spacedBy(12.dp)) {
                if(!cargando&&avisos.isEmpty())item { Text("Aún no hay boletines para tu unidad.",color=AlkiraTextSecondary) }
                items(avisos,key={it.id}) { aviso ->
                    Card(shape=RoundedCornerShape(14.dp),colors=CardDefaults.cardColors(containerColor=Color.White),
                        modifier=Modifier.fillMaxWidth()) {
                        Column(Modifier.padding(16.dp),verticalArrangement=Arrangement.spacedBy(8.dp)) {
                            Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween,verticalAlignment=Alignment.Top){
                                Text(aviso.titulo,Modifier.weight(1f),fontWeight=FontWeight.Bold,color=AlkiraTextPrimary)
                                if(!aviso.leido) Surface(color=AlkiraBlueSoft,shape=RoundedCornerShape(15.dp)) {
                                    Text("Nuevo",Modifier.padding(horizontal=9.dp,vertical=4.dp),fontSize=11.sp,color=AlkiraPrimaryBlue)
                                }
                            }
                            Text("${aviso.inmueble} · ${aviso.fecha}",fontSize=11.sp,color=AlkiraTextSecondary)
                            Text(Html.fromHtml(aviso.contenido,Html.FROM_HTML_MODE_COMPACT).toString().trim(),fontSize=13.sp,color=AlkiraTextPrimary)
                            if(!aviso.leido) TextButton(onClick={scope.launch {
                                try{ComunidadApi.marcarLeido(aviso.id);mensaje="Aviso marcado como leído.";actualizar(true)}
                                catch(e:Exception){error=e.message}
                            }},contentPadding=PaddingValues(0.dp)){Text("Marcar como leído",color=AlkiraPrimaryBlue)}
                            else Text("✓ Leído",fontSize=11.sp,color=AlkiraSuccessGreen)
                        }
                    }
                }
            }
        }else {
            LazyColumn(verticalArrangement=Arrangement.spacedBy(12.dp)) {
                if(!cargando&&votaciones.isEmpty())item { Text("No hay votaciones disponibles para tu unidad.",color=AlkiraTextSecondary) }
                items(votaciones,key={it.id}){ v ->
                    var unidad by remember(v.id,v.unidades) { mutableIntStateOf(v.unidades.firstOrNull()?.id?:0) }
                    var opcion by remember(v.id) { mutableIntStateOf(0) }
                    val ahora=LocalDateTime.now()
                    val abierta=try {v.estado=="activa"&&
                        !ahora.isBefore(LocalDateTime.parse(v.inicio.replace(' ','T')))&&
                        ahora.isBefore(LocalDateTime.parse(v.cierre.replace(' ','T')))}catch(_:Exception){false}
                    val votoExistente=v.misVotos[unidad]
                    Card(shape=RoundedCornerShape(14.dp),colors=CardDefaults.cardColors(containerColor=Color.White)) {
                        Column(Modifier.padding(16.dp),verticalArrangement=Arrangement.spacedBy(10.dp)) {
                            Text(v.titulo,fontWeight=FontWeight.Bold,fontSize=16.sp,color=AlkiraTextPrimary)
                            if(v.descripcion.isNotBlank())Text(v.descripcion,fontSize=13.sp,color=AlkiraTextSecondary)
                            Text("${v.inmueble} · ${if(abierta)"Abierta" else "Fuera del periodo"}",fontSize=12.sp,color=if(abierta)AlkiraSuccessGreen else AlkiraTextSecondary)
                            Text("Inicio: ${v.inicio} · Cierre: ${v.cierre}",fontSize=11.sp,color=AlkiraTextSecondary)
                            if(v.unidades.size>1){
                                var desplegado by remember { mutableStateOf(false) }
                                Box {
                                    OutlinedButton(onClick={desplegado=true}) {
                                        Text(v.unidades.find{it.id==unidad}?.etiqueta?:"Selecciona unidad")
                                    }
                                    DropdownMenu(expanded=desplegado,onDismissRequest={desplegado=false}){
                                        v.unidades.forEach { u->DropdownMenuItem(text={Text(u.etiqueta)},onClick={unidad=u.id;opcion=0;desplegado=false}) }
                                    }
                                }
                            }else Text("Unidad: ${v.unidades.firstOrNull()?.etiqueta.orEmpty()}",fontSize=12.sp,color=AlkiraTextSecondary)
                            if(votoExistente!=null)Text("✓ Tu unidad ya emitió su voto.",color=AlkiraSuccessGreen,fontWeight=FontWeight.Bold)
                            v.opciones.forEach { o ->
                                val seleccionado=opcion==o.id
                                Surface(color=if(seleccionado)AlkiraBlueSoft else AlkiraCanvasBg,
                                    shape=RoundedCornerShape(10.dp),modifier=Modifier.fillMaxWidth()
                                    .clickable(enabled=abierta&&votoExistente==null&&!guardando){opcion=o.id}) {
                                    Row(Modifier.padding(11.dp),verticalAlignment=Alignment.CenterVertically) {
                                        RadioButton(selected=seleccionado,onClick={opcion=o.id},enabled=abierta&&votoExistente==null&&!guardando)
                                        Text(o.texto,color=AlkiraTextPrimary,fontSize=13.sp)
                                    }
                                }
                            }
                            if(abierta&&votoExistente==null)Button(onClick={confirmar=Triple(v.id,unidad,opcion)},
                                enabled=opcion>0&&!guardando,colors=ButtonDefaults.buttonColors(containerColor=AlkiraPrimaryBlue)) {
                                Text("Confirmar mi voto")
                            }
                            Text("Resultados · ${v.total} unidades han participado",fontSize=12.sp,fontWeight=FontWeight.SemiBold,color=AlkiraTextSecondary)
                            v.opciones.forEach { o ->
                                Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween){
                                    Text(o.texto,fontSize=12.sp,color=AlkiraTextPrimary)
                                    Text("${o.porcentaje}% (${o.votos})",fontSize=12.sp,color=AlkiraTextSecondary)
                                }
                                LinearProgressIndicator(progress={ (o.porcentaje/100.0).toFloat().coerceIn(0f,1f) },
                                    modifier=Modifier.fillMaxWidth().height(7.dp),color=AlkiraPrimaryBlue,trackColor=AlkiraBorderSubtle)
                            }
                        }
                    }
                }
            }
        }
    }
    confirmar?.let { (id,unidad,opcion) ->
        AlertDialog(onDismissRequest={if(!guardando)confirmar=null},title={Text("Confirmar voto")},
            text={Text("Tu unidad solo puede votar una vez en esta encuesta. Esta decisión no se puede cambiar. ¿Deseas continuar?")},
            confirmButton={TextButton(enabled=!guardando,onClick={
                guardando=true;scope.launch {
                    try{ComunidadApi.votar(id,unidad,opcion);mensaje="¡Voto registrado correctamente!";error=null;actualizar(true)}
                    catch(e:Exception){error=e.message?:"No se pudo registrar el voto."}
                    finally{guardando=false;confirmar=null}
                }
            }){Text("Sí, emitir voto")}},
            dismissButton={TextButton(enabled=!guardando,onClick={confirmar=null}){Text("Cancelar")}})
    }
}
