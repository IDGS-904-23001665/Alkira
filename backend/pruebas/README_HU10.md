# HU10 y preparación de integración con HU05

Rama: `feature/HU10-pases-acceso-qr`.

Incluye pases con QR visible/descargable, lectura de cámara con confirmación,
entrada/salida, vigencia, revocación y protección de operaciones concurrentes.
El guardia solo accede a caseta; la administración emite/revoca pases.

El catálogo de residentes usa sus propias capas compartidas y mantiene el endpoint
`GET /api/incidencias/residentes` por compatibilidad. Esta rama recibe ahora HU05
para probar ambas historias juntas; incluye su pantalla, evidencia y `modulo5.sql`.
HU05 conserva su rama independiente. La integración en main queda para después.

Desde AWP: `npm ci`, `npm run lint` y `npm run build`.
Desde la raíz: `php backend/pruebas/integracionHu05Hu10.php --web`.
El ejecutor detecta ambas historias. La integración se verificó comparando todo
el código y las pruebas con `a563ae1`: son idénticos a la versión que pasó
76 comprobaciones y los escenarios Android/web. Ver `README_HU05_HU10.md`.
Crea y elimina una base efímera; requiere permisos MySQL para crear bases de prueba.
El spec no debe ejecutarse directamente contra una base real.

Usar los objetos de `modulo10.sql` en la base destino; no reimportar `schema.sql`
sobre datos existentes. Falta comprobar la cámara física de caseta; el lector fue
probado con video simulado.

## Móvil implementado y probado

`PasesQrScreen` permite generar pases temporales, consultar solo los propios,
mostrar un QR real, preparar su imagen PNG para compartir y revocar con confirmación.
El backend acepta JWT Bearer, toma el anfitrión de la sesión y bloquea la suplantación,
la revocación ajena y el acceso del residente a validación/bitácora de caseta.

Verificado en Samsung SM-A035M (Android 13): creación/listado/revocación,
decodificación de la imagen compartible y formulario Compose con QR y revocación.
El pase creado por Android se validó después en caseta: ENTRADA, SALIDA, RECHAZADO.
No se envió ninguna imagen ni mensaje a terceros durante las pruebas.

Con `JAVA_HOME`, `ANDROID_HOME` y `ANDROID_SERIAL` configurados:

```powershell
php backend/pruebas/integracionHu05Hu10.php --android
```

Usa una app separada `com.alkira.movil.pruebas`, conexión USB y base efímera;
retira las apps/datos de prueba al terminar. No reemplaza la instalación normal.
`--android --solo-android` repite solo instrumentación y el enlace con caseta.
La URL permanece centralizada en `ConfiguracionApi`; se configura al compilar con
`-PalkiraApiUrl=http://IP_DE_LA_PC:8000`. El predeterminado anterior del equipo
se conserva: proporcionar una URL local cuando se use otra red.
