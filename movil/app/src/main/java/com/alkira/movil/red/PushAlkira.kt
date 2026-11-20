package com.alkira.movil.red

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
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

object PushAlkira {

    const val CANAL = "avisos_alkira"

    fun configurado(): Boolean =
        BuildConfig.FIREBASE_APP_ID.isNotBlank() &&
        BuildConfig.FIREBASE_API_KEY.isNotBlank() &&
        BuildConfig.FIREBASE_SENDER_ID.isNotBlank() &&
        BuildConfig.FIREBASE_PROJECT_ID.isNotBlank()

    fun inicializar(contexto: Context): Boolean {
        if (!configurado()) return false

        if (FirebaseApp.getApps(contexto).isEmpty()) {
            val opciones = FirebaseOptions.Builder()
                .setApplicationId(BuildConfig.FIREBASE_APP_ID)
                .setApiKey(BuildConfig.FIREBASE_API_KEY)
                .setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
                .setProjectId(BuildConfig.FIREBASE_PROJECT_ID)
                .build()

            FirebaseApp.initializeApp(contexto, opciones)
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val gestor = contexto.getSystemService(
                Context.NOTIFICATION_SERVICE
            ) as NotificationManager

            gestor.createNotificationChannel(
                NotificationChannel(
                    CANAL,
                    "Avisos de ALKIRA",
                    NotificationManager.IMPORTANCE_DEFAULT
                )
            )
        }

        return true
    }

    suspend fun registrar(contexto: Context, tipoUsuario: String) {
        if (!inicializar(contexto)) return

        withContext(Dispatchers.IO) {
            try {
                val token = Tasks.await(
                    FirebaseMessaging.getInstance().token
                )

                if (tipoUsuario == "tecnico") {
                    DispositivosTecnicosApi.registrarToken(token)
                } else {
                    ComunidadApi.registrarToken(token)
                }

                Log.i(
                    "ALKIRA_FCM",
                    "Dispositivo registrado correctamente para $tipoUsuario"
                )

            } catch (error: Exception) {
                Log.w(
                    "ALKIRA_FCM",
                    "Falló el registro FCM: ${error.javaClass.simpleName}"
                )
            }
        }
    }

    suspend fun salir(contexto: Context, tipoUsuario: String) {
        if (!inicializar(contexto)) return

        withContext(Dispatchers.IO) {
            try {
                val token = Tasks.await(
                    FirebaseMessaging.getInstance().token
                )

                if (tipoUsuario == "tecnico") {
                    DispositivosTecnicosApi.eliminarToken(token)
                } else {
                    ComunidadApi.eliminarToken(token)
                }

                Tasks.await(
                    FirebaseMessaging.getInstance().deleteToken()
                )

            } catch (error: Exception) {
                Log.w(
                    "ALKIRA_FCM",
                    "Falló la desvinculación FCM: ${error.javaClass.simpleName}"
                )
            }
        }
    }
}

class AlkiraMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        // El token se asocia cuando el usuario inicia sesión.
    }

    override fun onMessageReceived(mensaje: RemoteMessage) {
        super.onMessageReceived(mensaje)

        if (
            Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.POST_NOTIFICATIONS
            ) != PackageManager.PERMISSION_GRANTED
        ) return

        val titulo = mensaje.notification?.title
            ?: "Nuevo aviso de ALKIRA"

        val texto = mensaje.notification?.body
            ?: "Consulta tu centro de avisos."

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or
                Intent.FLAG_ACTIVITY_SINGLE_TOP
        }

        val pendiente = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or
                PendingIntent.FLAG_IMMUTABLE
        )

        val aviso = NotificationCompat.Builder(this, PushAlkira.CANAL)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(titulo)
            .setContentText(texto)
            .setAutoCancel(true)
            .setContentIntent(pendiente)
            .build()

        NotificationManagerCompat.from(this).notify(
            (System.currentTimeMillis() % Int.MAX_VALUE).toInt(),
            aviso
        )
    }
}
