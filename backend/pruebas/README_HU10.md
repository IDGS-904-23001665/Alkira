# HU10 independiente: servicio y web

Rama: `feature/HU10-pases-acceso-qr`.

Incluye pases con QR visible/descargable, lectura de cámara con confirmación,
entrada/salida, vigencia, revocación y protección de operaciones concurrentes.
El guardia solo accede a caseta; la administración emite/revoca pases.

El catálogo de residentes usa sus propias capas compartidas y mantiene el endpoint
`GET /api/incidencias/residentes` por compatibilidad. No requiere las clases,
pantalla, cargas de evidencia ni `modulo5.sql` de HU05.

Desde AWP: `npm ci`, `npm run lint` y `npm run build`.
Desde la raíz: `php backend/pruebas/integracionHu05Hu10.php --web`.
El ejecutor detecta HU10 y prueba únicamente esta historia y las regresiones compartidas.
Crea y elimina una base efímera; requiere permisos MySQL para crear bases de prueba.
El spec no debe ejecutarse directamente contra una base real.

Usar los objetos de `modulo10.sql` en la base destino; no reimportar `schema.sql`
sobre datos existentes. Falta comprobar la cámara física de caseta; el lector fue
probado con video simulado. Móvil queda para la siguiente fase.
