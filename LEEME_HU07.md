# ALKIRA · Módulo 07 (HU07.1–HU07.4)

**Base:** `Alkira-main (3).zip`, última versión proporcionada por el equipo. Es una entrega de código para integrar, **no un PR ni una prueba de producción**. No sustituir toda la carpeta del repositorio sin verificar primero la base.

## Incluye

- **HU07.1 Android:** centro de avisos vinculado al usuario y unidad, lectura/no lectura e historial (hasta 150). El push utiliza Firebase Cloud Messaging (FCM) una vez configurado.
- **HU07.2 Android:** encuestas elegibles por unidad y periodo, confirmación antes de votar, consulta de resultados cada 10 s, `UNIQUE (id_votacion,id_unidad)` en MySQL.
- **HU07.3 PWA web:** boletines con editor enriquecido (negritas, cursiva, subrayado y listas), filtro por condominio/torre, publicación inmediata o programada, historial de destinatarios y envíos.
- **HU07.4 PWA web:** apertura y cierre, 2–10 opciones, gráficas porcentuales, resultados periódicos, auditoría por unidad (sin publicar votos individuales a otros residentes).

## Instalación local segura

1. Guardar o completar trabajo pendiente. `git status -sb` debe estar limpio. **Actualizar primero `main` con Git** y crear rama exclusiva: `git fetch origin` y `git switch -c feature/hu07-comunicados-votaciones origin/main`. No introducir esta HU en la rama HU03.1 ni tocar directamente `main`.
2. Descomprimir este ZIP y ejecutar `instalar_HU07_seguro.ps1`. El instalador comprueba el contenido de los archivos compartidos contra el ZIP base usado para desarrollar. Si alguien ha modificado uno desde esa versión, **se detiene sin copiar**: integrar esos archivos manualmente. Hace copia de respaldo fuera del proyecto.
3. Respaldar la base MySQL de pruebas. Abrir `backend/database/modulo7.sql` desde la pestaña SQL de phpMyAdmin, **en la base `alkira`**. Ejecutar **una sola vez**. Esta migración crea tablas nuevas; no borra datos. No importar de nuevo `schema.sql`.
4. Arrancar MySQL de XAMPP y el backend PHP como de costumbre (`C:\xampp\php\php.exe -S 0.0.0.0:8000 -t backend/public backend/public/router.php`). Usar un Wi-Fi privado para el Samsung; nunca exponer PHP dev server a Internet.
5. En PowerShell, desde la raíz del proyecto: `& 'C:\xampp\php\php.exe' backend\pruebas\probarModulo7.php`. Debe indicar que todas las tablas y el índice de voto único existen.
6. En `AWP`: `npm install`, `npm run build` y `npm run dev`. Entrar como administrador: menú **Comunicados** y **Votaciones**.
7. En Android Studio abrir `movil`, sincronizar Gradle y ejecutar en el Samsung. Ingresar como inquilino. En **Comunidad** hay pestañas **Centro de avisos** y **Votaciones**. El login móvil existente se reutiliza.
8. Crear un comunicado de prueba para el inmueble de la unidad del inquilino. Aparecerá en la app cuando se actualice; marcarlo como leído. Crear encuesta con periodo que cubra la hora **local del servidor**, votar desde la unidad de prueba y corroborar porcentaje/auditoría web. Intentar otro voto desde esa unidad debe producir `409`.

## Publicaciones programadas y salida externa

Los comunicados **inmediatos** aparecen en el centro de avisos inmediatamente y se encolan para correo/push. Los **programados** se publican cuando ejecuta el trabajador en o después de la fecha elegida. El envío efectivo a correo y FCM **NO funciona sin configuración externa**:

- Copiar `backend/config/hu07.local.php.ejemplo` como `backend/config/hu07.local.php` (ignorando Git). Habilitar `correoDesde`/`habilitarCorreo` *solo después* de configurar SMTP/sendmail de `mail()` en PHP. XAMPP Windows normalmente no lo trae listo. Sin SMTP el centro de avisos funciona pero no hay correos reales.
- Para FCM: crear proyecto y aplicación Android **`com.alkira.movil`** en Firebase, activar Cloud Messaging API (v1). En `%USERPROFILE%\.gradle\gradle.properties` agregar las propiedades `alkiraFirebaseProjectId`, `alkiraFirebaseApplicationId`, `alkiraFirebaseApiKey` y `alkiraFirebaseSenderId` de ese proyecto (evitar publicarlas en Git). En `hu07.local.php`, establecer `proyectoFcm` y `cuentaServicioFcm` como ruta ABSOLUTA de JSON de cuenta de servicio guardado **fuera de htdocs**. No subir nunca este JSON. Reinstalar Android para registrar el token y permitir notificaciones en Android 13+.
- Ejecutar manualmente el trabajador de pruebas: `& 'C:\xampp\php\php.exe' backend\cron\procesarHu07.php`. Para programar, crear una tarea en el Programador de tareas Windows que ejecute ese comando **cada minuto**. No se configura ninguna tarea automáticamente.
- Revisar las tablas `hu07_envios` (`pendiente`, `enviado`, `fallido`) y `hu07_destinatarios`. El trabajador evita duplicación por clave única y marca fallidos para revisión manual; **no reintenta correos fallidos automáticamente** para no duplicar mensajes posiblemente aceptados.
- En producción usar **HTTPS**, FCM configurado, SMTP autenticado/servicio de correo transaccional, protección de rate limit y autenticación sólida. HTTP local es solo para demostración.

## Notas de integración

- El proyecto ya incluye `comunicados`, `votaciones`, `votos_registrados` y `notificaciones_push_log` en `schema.sql`. HU07 añade `hu07_*` para destinatarios, opciones y votos con restricción; **no migra ni borra votos antiguos**. En listas HU07 se muestran las encuestas creadas con estas opciones.
- El estado de boletín `publicado` significa **visible para el destinatario**, no que el correo o el push se hayan entregado. Los canales externos son cola aparte.
- Los avisos se envían según las relaciones activas `usuarios_propiedades` y contratos vigentes, incluyendo los inmuebles hijos de la torre/condominio elegido. Los destinatarios se fijan cuando se publica el comunicado.
- El voto se atribuye a una **unidad**, no simplemente a la persona, y se valida otra vez en el servidor dentro de una transacción. El cambio de hora y el filtro por unidad se revalidan en backend, no solo por Android.
- El editor web no admite imágenes, enlaces ni HTML libre; el backend sanea todas las etiquetas y atributos, de modo que solo se conservan párrafos, listas y formato básico.
- El último `git status` antes del commit debe mostrar exclusivamente los archivos esperados del HU07. Repetir pruebas de módulos previos (HU03.1, HU03.2, HU05 y QR).
- No se verificó contra un MySQL real ni compiló Android en este entorno; ejecuta las pruebas aquí documentadas antes de crear PR. FCM y correo **requieren cuentas externas**, por lo que no es correcto presentarlos como probados.
