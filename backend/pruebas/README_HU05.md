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
sobre datos existentes. Móvil y sus permisos Bearer quedan para la siguiente fase.
