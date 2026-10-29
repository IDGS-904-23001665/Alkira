# HU05 independiente: servicio y web

Rama: `feature/HU05-reporte-incidencias`.

Incluye recepción/categorización, evidencia por archivo o URL, almacenamiento privado,
validación de residente/unidad y generación de ticket sin duplicados.
El catálogo de residentes es compartido con HU10; esta rama no requiere sus clases,
pantallas, dependencias QR ni `modulo10.sql`.

Verificación desde AWP: `npm run lint` y `npm run build`.
Desde la raíz: `php backend/pruebas/integracionHu05Hu10.php --web`.
El ejecutor detecta HU05 y prueba únicamente esta historia y las regresiones compartidas.
Crea una base efímera y la elimina; requiere permisos MySQL para crear bases de prueba.
El spec no debe ejecutarse directamente contra una base real.

Servidor local con carga de hasta 20 MB:

```powershell
php -d upload_max_filesize=20M -d post_max_size=22M -S 127.0.0.1:8000 -t backend/public backend/public/router.php
```

Usar los objetos de `modulo5.sql` en la base destino; no reimportar `schema.sql`
sobre datos existentes.

## Móvil implementado y probado

`IncidenciasScreen.kt` reemplaza el prototipo. Permite escribir el reporte, elegir
una unidad propia o área común, categoría/urgencia, seleccionar JPG/PNG/MP4/PDF
y enviar el archivo; consulta únicamente los reportes del residente autenticado.
Los endpoints aceptan Bearer, fijan la identidad desde el JWT y bloquean acceso,
evidencias, suplantación y cambios de estado no autorizados. La web conserva cookie.

Verificado en Samsung SM-A035M, Android 13: transporte multipart real, consulta,
rechazo de acceso ajeno y envío desde formulario Compose. Compilación y pruebas JVM correctas.

Para repetir con un teléfono autorizado, definir `JAVA_HOME`, `ANDROID_HOME` y
`ANDROID_SERIAL`; ejecutar desde la raíz:

```powershell
php backend/pruebas/integracionHu05Hu10.php --android
```

`--android --solo-android` repite solo instrumentación. Usa una app separada
`com.alkira.movil.pruebas`, conexión USB, una base efímera y elimina app/datos de
prueba al terminar. No reemplaza la instalación normal del usuario.
La URL sigue centralizada en `ConfiguracionApi`; se configura al compilar mediante
`-PalkiraApiUrl=http://IP_DE_LA_PC:8000`. El valor anterior del equipo se conserva
como predeterminado; para una red distinta se debe proporcionar el valor local.
