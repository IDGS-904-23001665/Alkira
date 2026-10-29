# HU05 y HU10: servicio, web y móvil

Fecha: 28 de octubre de 2026. Rama: `feature/HU10-pases-acceso-qr`, integración de main + HU05 + HU10.

## Estado actual

Paso 2 completado: HU05 se integra dentro de HU10, sobre la base de `origin/main`.
Las pruebas de esta integración quedan pendientes para el paso 3; no se ejecutan
en este paso. `main` local y `origin/main` permanecen en `c276308`.
No se ha publicado esta integración ni se ha llevado HU10 a main.
Los resultados siguientes corresponden a la verificación anterior en la rama
local `codex`, conservada como referencia.

## Resultados previos de referencia

- Comprobación conjunta final correcta: 76 marcas del ejecutor (71 comprobaciones previas HTTP/MySQL, fase Android, tres movimientos Android→caseta y fase web).
- 4 tests Android correctos en el Samsung: contexto de la app de prueba, login/navegación real entre ambas historias, HU05 con archivo/formulario y HU10 con QR compartible/formulario/revocación.
- Las dos vistas móviles recuperan el diseño original y se verifican juntas: HU05 con tarjeta gris, adjunto azul y botón terracota; HU10 con tarjetas blancas, visor QR, azul/marino y vista ampliada.
- 4 pruebas Cypress correctas en navegador Electron: incidencia con archivo y categorización; pase QR con entrada/salida/rechazo; login de guardia y navegación restringida; lectura QR desde video simulado.
- `npm run lint`, `npm run build` y sintaxis PHP correctos.
- Regresiones verificadas por HTTP: login de administrador, inmuebles, autenticación Bearer existente, sesión móvil, adeudos, historial de pagos y contratos. Se conservan sus servicios.
- Las pruebas crearon y eliminaron bases `alkira_pruebas_*` y copias temporales del backend. No importaron SQL ni cambiaron usuarios o registros en la base de trabajo.
- Base remota consultada: `origin/main` en `c276308`. La integración actual está en HU10; main permanece sin cambios y no se hizo push.

## Implementado

HU05:

- Catálogo limitado a inquilinos/propietarios activos y relaciones de unidad vigentes; selección de unidad diferenciada cuando un residente tiene varias.
- Validación de reportante y relación residente/unidad/inmueble en el backend.
- Creación y categorización transaccionales, conservando procedimientos, respuestas y enlace al módulo 6. Reasignar no duplica el ticket.
- Carga multipart de JPG, PNG, MP4 y PDF, hasta 20 MB; inspección MIME del contenido, nombres aleatorios y almacenamiento fuera de `public/`.
- Descarga autenticada y botón «Ver evidencia». Se mantienen las peticiones JSON y referencias URL existentes.
- Limpieza del archivo recibido si falla la creación del reporte; rechazo probado de PHP disfrazado y carga excesiva.

HU10:

- Sesión web para guardia mediante métodos de autenticación específicos para web. Los métodos anteriores de administrador y móvil conservan sus roles.
- Guardia: consulta pases/bitácora y valida movimientos. Solo administrador emite/revoca desde la web; guardia no accede a incidencias ni inmuebles.
- QR generado localmente, visible y descargable. Lectura de cámara con `jsqr`, sin servicios externos; el token manual sigue disponible.
- La cámara se detiene tras una lectura; el usuario confirma la validación. El segundo movimiento requiere otra acción explícita.
- Fechas estrictas y vigencia de 1 a 168 horas; reloj de inicio tomado de MySQL; tokens aleatorios con `random_bytes`.
- Validación y revocación serializadas por pase con transacción y `FOR UPDATE`.
- Listado general calcula vencimiento sin modificar filas. Una visita abierta puede salir después del vencimiento; no se revoca mientras permanezca en el interior.

Integración:

- `VistaContenido.jsx` registra Incidencias y Seguridad; el router PHP conserva ambos módulos y las rutas anteriores.
- El menú del guardia muestra solo Seguridad. Los permisos se comprueban también en PHP.
- Configuración ESLint de Cypress corregida; dependencias QR y lockfile actualizados; capturas y archivos privados excluidos de Git.

## Repetir las pruebas

Requiere PHP con PDO MySQL, curl, fileinfo y mbstring; MySQL local accesible; Node y dependencias AWP instaladas. Las credenciales locales deben poder crear una base efímera. El ejecutor no expone credenciales ni usa las contraseñas de usuarios reales.

Desde `AWP/`:

```powershell
npm ci
npm run lint
npm run build
```

Desde la raíz:

```powershell
php backend/pruebas/integracionHu05Hu10.php
php backend/pruebas/integracionHu05Hu10.php --web
```

La segunda orden detecta las historias presentes en la rama, incluye sus casos HTTP y lanza los specs `hu05.cy.js` y/o `hu10.cy.js` contra el servidor y la BD efímeros. No ejecutar el spec aislado contra una base real; el spec exige la URL y contraseña efímeras del ejecutor. Las capturas quedan en `AWP/cypress/screenshots/`, excluidas de Git.

Para revisión manual local, ejecutar PHP con límites compatibles con los 20 MB anunciados:

```powershell
php -d upload_max_filesize=20M -d post_max_size=22M -S 127.0.0.1:8000 -t backend/public backend/public/router.php
```

Y `npm run dev` desde AWP. Deben existir los objetos SQL de `modulo5.sql` y `modulo10.sql` en la base destino. Revisar los scripts antes de aplicarlos; no reimportar `schema.sql` sobre datos existentes.

## Móvil

- HU05: formulario editable, categoría/urgencia, unidades propias y área común,
  selección de archivo y envío multipart real, y consulta de los reportes propios.
- HU10: generación, listado y revocación propios; QR real y PNG compartible con
  FileProvider. El residente no puede consultar bitácora ni validar entradas.
- Ambos usan `SesionMovil`, JWT Bearer y `ConfiguracionApi`; PHP fija la identidad
  y aplica permisos por objeto. La sesión web por cookie conserva sus funciones.
- Probados individualmente en Samsung SM-A035M, Android 13: formularios Compose,
  API real, carga de imagen, decodificación del QR compartible y revocación.
  Un pase generado en Android completó ENTRADA, SALIDA y RECHAZADO en caseta.
- La comprobación conjunta pasó también el login y la navegación real entre ambas historias.
- La prueba usa una app separada `com.alkira.movil.pruebas` y una BD efímera;
  desinstala únicamente las apps de prueba y retira la conexión USB al terminar.
  No reemplaza la app normal ni toca los registros de la base de trabajo.

Para repetir en un teléfono autorizado, configurar `JAVA_HOME`, `ANDROID_HOME`
y `ANDROID_SERIAL` y ejecutar:

```powershell
php backend/pruebas/integracionHu05Hu10.php --android --web
```

`--android --solo-android` repite solo Android. La URL permanece centralizada;
para uso manual en otra red compilar con `-PalkiraApiUrl=http://IP_DE_LA_PC:8000`.
El predeterminado anterior del equipo se conserva y no corresponde a todas las redes.

## Límites y siguiente fase

- La lectura se probó con video de un QR real generado por la aplicación; falta validar permiso, enfoque y lectura con una cámara física del equipo de caseta. Requiere HTTPS o localhost.
- No se probaron todos los dispositivos/navegadores ni el despliegue. Asegurar permisos de escritura y respaldo del directorio privado, y límites de PHP compatibles con 20 MB.
- Las pruebas de regresión comprueban las consultas y autenticación indicadas; no equivalen a verificar todos los flujos de módulos de otros compañeros.


## Orden de ramas y commits

- `feature/HU05-reporte-incidencias`: `4ae61ce`, HU05 completa con diseño restaurado y prueba en Samsung; conserva sus commits de servicio/web y móvil.
- `feature/HU10-pases-acceso-qr`: conserva su implementación hasta `b4c24a2` y recibe HU05 para las siguientes pruebas conjuntas.
- `codex/cierre-hu05-hu10`: referencia previa solo local. El router y el mapa de pantallas de HU10 conservan ambas historias.
- El catálogo de residentes mantiene sus capas compartidas y endpoint; cada módulo conserva sus controladores, servicios y repositorios.
- HU05 móvil: `d49005a`; HU10 móvil: `864482e`. Se conservan los commits web anteriores; HU05 sigue independiente y HU10 contiene ahora ambas historias.
- `main` local y remoto permanecen en `c276308`. La rama `codex` es solo local. El intento anterior de main se conserva en un stash de respaldo; los stashes anteriores del usuario no se modifican.
