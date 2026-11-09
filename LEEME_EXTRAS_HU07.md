# ALKIRA HU07 · Extras: publicación automática, correo Brevo y push Firebase

**Este ZIP es un complemento al HU07 que ya instalaste. NO es todo el proyecto.**
No sobrescribe tu corrección en `backend/controllers/Hu07Controlador.php`, ni toca MySQL, ni crea tareas de Windows al instalarlo.

## Requisitos
- Rama Git `feature/hu07-comunicados-votaciones`, con el HU07 anterior instalado.
- PHP 8.3 de XAMPP, MySQL, Windows PowerShell 5.1+ y Android Studio.
- Trabajador `backend/cron/procesarHu07.php` sin modificaciones respecto del paquete anterior. Si cambió, el instalador se detiene.

## 1) Instalación
Desde la raíz de ALKIRA, descomprime y ejecuta:

```powershell
Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\ALKIRA_HU07_extras_automatizacion_correo_push.zip" -DestinationPath 'C:\xampp\ALKIRA_HU07_EXTRAS' -Force
& 'C:\xampp\ALKIRA_HU07_EXTRAS\instalar_EXTRAS_HU07_seguro.ps1' -Proyecto 'C:\xampp\htdocs\Alkira'
```

El instalador protege archivos compartidos. Crea respaldo de `procesarHu07.php` FUERA de la carpeta del proyecto.

## 2) Primero probar en modo seguro (sin enviar nada)

```powershell
cd C:\xampp\htdocs\Alkira
& 'C:\xampp\php\php.exe' -l backend\cron\procesarHu07.php
& 'C:\xampp\php\php.exe' -l backend\services\Hu07CorreoBrevo.php
& 'C:\xampp\php\php.exe' -d date.timezone=America/Mexico_City backend\pruebas\diagnosticarHu07Entregas.php
```

*Sin un archivo `hu07.local.php` no se intenta enviar correo ni push.* El script de diagnóstico SOLO LEE; no publica ni envía.

## 3) Publicación automática cada minuto (Windows)

```powershell
cd C:\xampp\htdocs\Alkira
& '.\herramientas\instalarTareaHu07.ps1'
Get-ScheduledTaskInfo -TaskName 'ALKIRA-HU07-Publicaciones'
```

**Esto SÍ crea una tarea real de Windows** que ejecuta el trabajador cada minuto mientras el usuario tenga sesión abierta. Si la tarea necesita permisos, abre PowerShell como administrador. Si falla, no afecta al proyecto. Usará la hora de México aunque `php.ini` indique Berlín. Debe permanecer encendido **MySQL**; no hace falta ejecutar un servidor HTTP para procesar el trabajador.

Para apagarla:

```powershell
& '.\herramientas\quitarTareaHu07.ps1'
```

**No ejecutes el trabajador manualmente ni actives envíos externos si no has comprobado las cuentas destinatarias.**

## 4) Correos reales con Brevo (NO automática)

1. Crea una cuenta Brevo, registra y verifica remitente/dominio. Consulta la documentación oficial: https://developers.brevo.com/docs/send-a-transactional-email
2. Obtén una API key de Brevo y mantenla privada. Brevo API utiliza HTTPS, no necesitas configurar `mail()` de XAMPP.
3. Copia `backend/config/hu07.entrega.local.php.ejemplo` a `backend/config/hu07.local.php`. **Nunca subas este último a Git** (ya está ignorado por `.gitignore`).
4. Configura `correoDesde`, `brevoApiKey` y `habilitarCorreo => true` en ese archivo local.
5. Mantén `permitirEnvioMasivo => false` y configura `idUsuarioPrueba` con el ID numérico de un **usuario de prueba cuyo correo sea tuyo y válido**. Por ejemplo, en phpMyAdmin:

```sql
SELECT id_usuario,correo FROM usuarios WHERE correo='inquilino@alkira.test';
```

   Nota: `@alkira.test` es ficticio y **no puede recibir correos reales**. Cambia solo un usuario de prueba en una base de pruebas o crea uno con tu propio correo (no publiques ese correo aquí).
6. **Antes de activar envíos**, consulta `SELECT MAX(id_comunicado)+1 AS siguiente FROM comunicados;` y coloca ese número en `idComunicadoDesde`. Así no reenvías boletines antiguos ya en cola.
7. Publica un comunicado a ese usuario de prueba y deja correr la tarea. Revisa `hu07_envios` para ver si el proveedor aceptó el envío. **Aceptado por Brevo no equivale a entregado a la bandeja**, revisa registros de entrega de Brevo.

Con `permitirEnvioMasivo => false`, el trabajador **no enviará a otros usuarios**. Cambiarlo a `true` habilita envíos externos masivos; hazlo solo cuando tu equipo lo autorice.

## 5) Notificaciones push reales con Firebase

Ya existe código Android (`PushAlkira.kt`) y envío FCM v1 en `procesarHu07.php`. Este ZIP NO debe incluir secretos de Firebase.

1. En Firebase Console (https://console.firebase.google.com/) crea proyecto y registra app Android con package `com.alkira.movil` (o `com.alkira.movil.pruebas` si activaste `alkiraPruebas=true`). Habilita FCM HTTP v1.
2. En `%USERPROFILE%\.gradle\gradle.properties`, configura estas propiedades públicas según tu proyecto (sin comillas ni enlaces):

```properties
alkiraFirebaseProjectId=ID_DEL_PROYECTO
alkiraFirebaseApplicationId=1:NUMERO:android:IDENTIFICADOR
alkiraFirebaseApiKey=CLAVE_DE_LA_APP_ANDROID
alkiraFirebaseSenderId=NUMERO_DEL_REMITENTE
```

3. Descarga el JSON **de cuenta de servicio** en Firebase > Configuración del proyecto > Cuentas de servicio. Guárdalo fuera de `htdocs`, por ejemplo `C:\xampp\alkira-privado\firebase-service-account.json`. No envíes ni subas ese JSON a GitHub.
4. En `backend/config/hu07.local.php`, llena `proyectoFcm` y `cuentaServicioFcm` con la ruta ABSOLUTA del archivo JSON.
5. Abre Android Studio, reconstruye e instala la aplicación; concede permiso de notificaciones Android 13+, inicia sesión con el usuario de prueba; el módulo debe registrar su token en la tabla `hu07_dispositivos`.
6. Configura también `idComunicadoDesde` con el siguiente ID (véase sección Correo). Crea un nuevo comunicado solo para el usuario de prueba y ejecuta la tarea. Prueba la llegada con la app en segundo plano. Verifica `hu07_envios` (canal `push`).

**No puede probarse push real sin un proyecto Firebase propio y una cuenta de servicio válida.**
Documentación FCM: https://firebase.google.com/docs/cloud-messaging/send/v1-api

## 6) Comprobar sin exponer secretos

```powershell
& 'C:\xampp\php\php.exe' -d date.timezone=America/Mexico_City backend\pruebas\diagnosticarHu07Entregas.php
```

No compartas contenido de `hu07.local.php`, credenciales de Firebase, contraseñas, cookies ni tokens.

## 7) Antes de commit

```powershell
git status -sb
git diff --check
```

No subir configuraciones privadas ni comprobantes reales. Las **dos integraciones externas son opcionales hasta configurar cuentas** y no están validadas con tus credenciales. Programación automática debe comprobarse mediante una nueva publicación programada y observando que se publica SIN ejecutar el trabajador manualmente.

## Posibles riesgos/limitaciones
- Configurar proveedor no implica garantía de llegada: puede haber bloqueos, límites y fallos de entrega.
- Las entregas `enviado` se interpretan como aceptadas por proveedor, no como recibidas por usuario.
- Las tareas fallidas quedan auditadas, sin reintentos automáticos de correo por riesgo de duplicación.
- Enviar FCM puede requerir que Windows tenga certificado TLS, conexión saliente HTTPS y permisos adecuados.
- Se requiere probar en entorno de prueba antes de despliegue. La tarea debe configurarse para un servidor persistente al salir del PC de desarrollo.
