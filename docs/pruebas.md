# Registro de pruebas

Ejecución comprobada con `C:\xampp\php\php.exe` (PHP 8.2.12), `pdo_mysql` y MariaDB 10.4.32 en la instancia aislada de esta biblioteca `127.0.0.1:3308`.

Comandos:

```powershell
C:\xampp\php\php.exe tests/integration.php
C:\xampp\php\php.exe tests/http-smoke.php
```

Resultado real: 27 comprobaciones de integración y 13 comprobaciones HTTP aprobadas. La salida detallada se conserva en la terminal de la sesión de desarrollo.

También se ejecutó `php -l` con PHP 8.2.12 para todos los archivos PHP del proyecto y todos reportaron sintaxis correcta. La ruta HTTP local devolvió estado 200. El router local rechaza solicitudes directas a `runtime/`, `.git`, `tests/`, clases y archivos de configuración.

| Área | Resultado |
|---|---|
| Conexión PDO y tablas | Aprobado |
| CRUD de libros y usuarios | Aprobado |
| Reglas de validación | Aprobado |
| Préstamo, bloqueo de stock y operación sin stock | Aprobado |
| Devolución, reposición de stock y duplicado | Aprobado |
| Integridad de historial y borrado de registros independientes | Aprobado |
| Sintaxis PHP (XAMPP PHP 8.2.12) | Aprobado |
| Respuesta HTTP | Aprobado (200) |
| Métricas de devoluciones del dashboard | Aprobado |
| Formularios y patrón Post/Redirect/Get | Aprobado (13 comprobaciones HTTP) |
| Token CSRF válido e inválido | Aprobado |
| Escape XSS de datos ingresados | Aprobado |
| Revisión visual en Edge (escritorio) | Aprobado |
| Capturas reales de inicio, libros, edición, usuarios, préstamo y devolución | Aprobado; archivos en `docs/capturas/` |
| Revisión visual en un viewport móvil | No comprobada manualmente; el CSS incluye reglas responsive |
