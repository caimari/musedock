# Validación aislada — MuseDock CMS MCP 2.20.0

Fecha: 11 de octubre de 2026. Estado: preparada para revisión, sin despliegue público.

Checkpoint del CMS antes de modificar: `644ca5c`. Rama: `feature/autonomous-cms-mcp`.
Parche de plugins: rama `feature/cms-mcp-isolation`, basado en `40c0dc7`. No modifica el árbol activo de plugins ni sus cambios previos.

Pruebas reproducibles desde la raíz de la rama CMS:

```sh
php tests/mcp/run.php
python3 tests/mcp/mariadb_test.py
python3 tests/mcp/http_test.py
php tests/mcp/concurrency_test.php
php tests/mcp/views_test.php
```

La última prueba requiere BladeOne instalado en vendor, o su fichero como primer argumento. En este entorno se utilizó la biblioteca ya instalada del CMS, exclusivamente como dependencia de render. No se cargó su bootstrap ni sus credenciales.

| Suite | Resultado |
| --- | --- |
| Autorización, contenido, borradores, aislamiento REST y guard de cola | 78 comprobaciones correctas |
| Misma suite con MariaDB privado, incluida migración de origen API | 78 comprobaciones correctas |
| Router real y endpoints HTTP en loopback | 30 comprobaciones correctas |
| Intercambio concurrente de un mismo código OAuth | Un éxito, un rechazo |
| Vistas de superadmin y tenant | Ambas renderizan, escapan HTML, incluyen CSRF y ocultan el secreto |
| Sintaxis PHP y `git diff --check` | Correctos |

Las fixtures contienen un sitio principal, dos propios y un sitio de cliente; usuario superadmin y administrador de tenant. Se comprueban: callback exacto, S256, audiencia, código de un uso, permisos, rotación y reutilización de refresh, revocación, identidad del propietario, aislamiento por ID, selección de destinos, cambios de grupo, taxonomías extranjeras, planes sin efectos, solicitudes sin ejecución, aprobación única, caducidad, contenido cambiado, artículos publicados protegidos, bloqueo de IP falsificada y CIDR inválidos.

Las pruebas HTTP usan el router y los endpoints reales: descubrimiento, desafío 401, rebote SameSite sin cookie, consentimiento autenticado con CSRF, estado e issuer en callback, intercambio de código, inicialización MCP, catálogo de herramientas, solicitudes pendientes, rechazo de tokens revocados, método GET, JSON inválido, notificaciones y host no autorizado. No se conectan al SQL de producción ni se emiten credenciales reales.

Límites: no equivalen a una prueba visual del panel completo ni a una sesión real ChatGPT/Claude. El SQL de contenido se prueba con SQLite y MariaDB temporal, incluyendo la migración de origen API aplicada dos veces. Antes de activar debe verificarse contra una copia del esquema completo del despliegue; PostgreSQL no se ha ejecutado en estas pruebas. No se prueban publicación, IA, túnel, DNS ni firewall porque esta entrega no los implementa ni modifica.

## Despliegue autorizado — 11 de octubre de 2026

El usuario autorizó expresamente el despliegue después de recibir los resultados. Se integró el CMS en producción (`4889ed5`) y el parche de plugins privados (`18a2992`), conservando exactamente los cambios previos sin confirmar del repositorio de plugins. Se ejecutaron solo las dos migraciones nuevas, dentro de una transacción PostgreSQL, y se verificaron la entrada de menú y la columna de origen de la cola.

Se guardó una copia completa PostgreSQL en formato custom y la configuración fuera del directorio público, en `.codex/backups/mcp-deploy-20261011`; el índice se verificó con pg_restore 16. El almacenamiento OAuth privado se inicializó con permisos restrictivos y sin recursos ni clientes. No se publicaron commits en GitHub.

Se añadieron y pasaron las mismas 78 comprobaciones en un PostgreSQL 16 temporal sin escucha TCP (`python3 tests/mcp/postgres_test.py`). También pasaron nuevamente las 78 comprobaciones SQLite, 30 HTTP, concurrencia y vistas Blade. Se confirmó por HTTPS que la gestión requiere login y que los recursos públicos desactivados responden 404.

El valor anterior `MCP_ENABLED=false` se conserva; el nuevo interruptor también permanece desactivado por defecto. El código y la gestión están desplegados, pero la conexión externa requiere habilitación explícita y configurar clientes con callbacks reales. No se ha verificado aún una cuenta real de ChatGPT/Claude.
