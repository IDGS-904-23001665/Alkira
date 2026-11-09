package com.alkira.movil.red

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.alkira.movil.BuildConfig
import com.alkira.movil.MainActivity
import com.google.android.gms.tasks.Tasks
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/** Configuración opcional: las cuatro propiedades se guardan en ~/.gradle/gradle.properties. */
object PushAlkira {
    const val CANAL="avisos_alkira"
    fun configurado():Boolean = BuildConfig.FIREBASE_APP_ID.isNotBlank() &&
        BuildConfig.FIREBASE_API_KEY.isNotBlank() && BuildConfig.FIREBASE_SENDER_ID.isNotBlank() &&
        BuildConfig.FIREBASE_PROJECT_ID.isNotBlank()

    fun inicializar(context:Context):Boolean {
        if(!configurado())return false
        if(FirebaseApp.getApps(context).isEmpty()) {
            val opciones=FirebaseOptions.Builder()
                .setApplicationId(BuildConfig.FIREBASE_APP_ID)
                .setApiKey(BuildConfig.FIREBASE_API_KEY)
                .setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
                .setProjectId(BuildConfig.FIREBASE_PROJECT_ID)
                .build()
            FirebaseApp.initializeApp(context,opciones)
        }
        val gestor=context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        gestor.createNotificationChannel(NotificationChannel(CANAL,"Avisos de ALKIRA",NotificationManager.IMPORTANCE_DEFAULT))
        return true
    }
    suspend fun registrar(context:Context) {
        if(!inicializar(context)) return
        withContext(Dispatchers.IO) {
            try {
                val token=Tasks.await(FirebaseMessaging.getInstance().token)
                ComunidadApi.registrarToken(token)
            }catch(_:Exception) { /* No bloquear inicio de sesión: revisar conexión/FCM. */ }
        }
    }
    suspend fun salir(context:Context) {
        if(!inicializar(context))return
        withContext(Dispatchers.IO) {
            try {
                val token=Tasks.await(FirebaseMessaging.getInstance().token)
                ComunidadApi.eliminarToken(token)
                Tasks.await(FirebaseMessaging.getInstance().deleteToken())
            }catch(_:Exception) { /* La sesión aún debe cerrarse incluso sin conexión. */ }
        }
    }
}

/** Receptor de FCM para avisos cuando llega una notificación en primer plano. */
class AlkiraMessagingService : FirebaseMessagingService() {
    override fun onNewToken(token:String) {
        super.onNewToken(token)
        // El token se asocia al iniciar sesión; no tenemos identidad al arrancar el servicio.
    }
    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        if(Build.VERSION.SDK_INT>=33 && ContextCompat.checkSelfPermission(this,Manifest.permission.POST_NOTIFICATIONS)
            !=PackageManager.PERMISSION_GRANTED)return
        val titulo=message.notification?.title ?: "Nuevo aviso de ALKIRA"
        val texto=message.notification?.body ?: "Consulta tu centro de avisos."
        val intent=Intent(this,MainActivity::class.java).apply{flags=Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP}
        val pendiente=PendingIntent.getActivity(this,0,intent,PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val aviso=NotificationCompat.Builder(this,PushAlkira.CANAL)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(titulo).setContentText(texto).setAutoCancel(true).setContentIntent(pendiente).build()
        NotificationManagerCompat.from(this).notify((System.currentTimeMillis()%Int.MAX_VALUE).toInt(),aviso)
    }
}
