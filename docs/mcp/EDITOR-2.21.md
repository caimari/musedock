# (MCP) Conexiones IA — editor 2.21.0

El usuario solicitó ampliar el editor y añadir aprobación opcional desde MCP. El CMS sigue autónomo de MuseDock Panel. Los interruptores globales y las conexiones existentes no se activan ni reciben permisos nuevos automáticamente.

## Elegir conexión

- **Dominio principal:** solo contenido con tenant NULL/0.
- **Cross Publisher:** recomendado para el grupo editorial propio; intersección de sitios propios del recurso, sitios del cliente y grupos actuales. Además copia artículos a destinos autorizados como borradores, sin sincronización automática.
- **News Aggregator:** mismo aislamiento editorial; consulta y convierte noticias existentes en borradores. No ejecuta capturas ni reescritura de fuentes desde MCP.

Las pestañas de plugins solo aparecen si el plugin está activo. Sus URLs de gestión tampoco permiten operar cuando está inactivo. Los iconos de información abren diálogos con el alcance y funcionamiento de cada conexión.

## Permisos y aprobación

Los ámbitos anteriores continúan limitados a lectura y borradores básicos. Los nuevos ámbitos se conceden expresamente al cliente y en el consentimiento OAuth:

| Ámbito | Herramientas |
| --- | --- |
| `musedock-cms:posts:edit` | `create_post`, `update_post`: todas las opciones del editor de borradores |
| `musedock-cms:posts:manage` | `publish_post`, `schedule_post`, y permiso adicional obligatorio para editar artículos o traducciones publicados |
| `musedock-cms:taxonomy:manage` | crear/editar categorías y etiquetas |
| `musedock-cms:comments:manage` | consultar, aprobar, devolver a pendiente, marcar spam o borrar comentarios |
| `musedock-cms:translations:manage` | consultar y guardar traducciones vinculadas por post e idioma |
| `musedock-cms:media:manage` | consultar biblioteca, subir imágenes y generar imágenes con el proveedor del CMS |
| `musedock-cms:requests:approve` | consultar, aprobar o rechazar solicitudes del propio cliente y consentimiento |

`get_post_details` amplía la lectura del artículo con sus opciones y taxonomías, sin devolver contraseñas ni hashes. `list_comments` no devuelve emails, IPs o agentes de usuario.

Los cambios siguen el flujo plan → solicitud (`apply=true`) → aprobación. El checkbox **Permitir aprobar solicitudes desde MCP** está desactivado por defecto. Además de marcarlo, el cliente necesita el ámbito de aprobación en su consentimiento. `approve_request` puede ejecutar únicamente solicitudes de ese cliente, recurso y consentimiento. No es aprobación automática implícita: el asistente realiza una llamada explícita con el ID de solicitud. Esta opción permite escritura desde el chat sin volver al panel para cada cambio.

Publicar requiere el checkbox independiente **Permitir publicar, programar y editar artículos publicados**, el ámbito de publicación y permisos vigentes del usuario. Conceder aprobación remota no concede publicación. Es posible usar el editor completo para borradores sin conceder publicación. Cambiar ajustes exige renovar consentimientos, como en 2.20.

## Campos y contenido

El editor completo admite título, contenido, extracto, slug, estado, fecha, visibilidad, contraseña, idioma base, imagen destacada por ID propio o URL, ocultación de imagen, artículo/brief/docs, destacado, comentarios, plantilla, cabecera, título H1, SEO completo y campos de X. Imágenes por ID se comprueban contra el sitio autorizado. Categorías y etiquetas también, incluyendo padres de categoría y prevención de ciclos. No se ofrece borrado de artículos.

Las traducciones se guardan en `blog_post_translations`; el asistente proporciona el texto traducido. Admiten título, contenido, extracto y campos SEO/sociales. Deben usar un idioma activo diferente del idioma base. Editar una traducción de un post publicado exige publicación además del permiso de traducciones.

Las contraseñas nuevas se guardan con `password_hash`, nunca se devuelven al asistente y no aparecen en los planes. El frontend comprueba privacidad y contraseña antes de renderizar el contenido. El desbloqueo tiene CSRF y límite de intentos; cambiar la contraseña invalida el desbloqueo anterior. Se soportan contraseñas anteriores en texto para compatibilidad. Los listados públicos excluyen artículos privados o protegidos; las respuestas privadas no se guardan en la caché HTML. Los cambios editoriales invalidan la caché del sitio afectado.

La migración `2026_10_11_150000` añade los campos de cabecera que faltaban y los campos SEO/sociales de traducciones. Es idempotente y conserva datos al volver de versión. La migración `2026_10_11_160000` actualiza el nombre del menú sin cambiar entradas personalizadas con otros nombres.

## Imágenes

`generate_image` utiliza el proveedor configurado en **IA → AI Image** del sitio, sus claves y cuotas. No utiliza las claves del Panel ni la generación integrada de la cuenta ChatGPT. El plan informa del proveedor/modelo y no genera ni cobra. La llamada externa se hace solo al aprobar y no se reintenta automáticamente.

`upload_image` acepta una URL HTTPS pública o base64 (hasta 2 MiB decodificados). Las descargas se limitan a 10 MiB, con IP pública fijada y revisión de redirecciones mediante UrlGuard. Se validan MIME, formato real y límite de 40 megapíxeles; no SVG/HTML. Los datos base64 no se incluyen en el plan público. La biblioteca y las cuotas se procesan en el contexto temporal del sitio de destino, restaurando el contexto del panel después. La biblioteca MCP muestra imágenes con enlace público gestionado (token); las imágenes antiguas sin ese enlace pueden asignarse por su URL desde el editor. Devuelve un ID, URL y HTML que pueden usarse en el artículo. Los proveedores también utilizan la descarga segura del CMS.

## Programación

`schedule_post` requiere una fecha ISO 8601 con zona horaria al menos un minuto en el futuro. El artículo continúa como borrador. El worker `cli/cron-mcp-publications.php` revisa las fechas cada minuto y publica solo si recurso, plugin, cliente, consentimiento, usuario, grupo, sitio y contenido siguen autorizados y sin cambios. Cambiar el artículo o sus permisos cancela la publicación. No se permiten fechas futuras en un artículo marcado publicado directamente: hay que usar la programación.

Las publicaciones programadas se muestran en la pantalla y se pueden cancelar allí. Revocar un cliente o desactivar la conexión impide la publicación. El worker no opera cuando los interruptores globales están desactivados. Se almacena en el SQLite privado `schedules`, sin mezclar servicios externos.

## Validación

Suite con 139 comprobaciones en fixtures SQLite, MariaDB y PostgreSQL; 38 comprobaciones HTTP OAuth/MCP con aprobación remota, protección de transporte y acceso a artículos privados/protegidos; concurrencia de códigos OAuth; render Blade y escapes. Prueba adicional en una copia íntegra del backup PostgreSQL de producción, restaurada en una instancia temporal sin TCP: editor completo, publicación, taxonomías, comentarios, traducciones, programación y migración idempotente. No se escriben datos reales.

La subida prueba validación, ámbito, aprobación y contexto contra un adaptador de persistencia de medios de prueba. No se llama a proveedores reales de imágenes, no se certifica la salida de cada modelo, ni se verifica una cuenta real de ChatGPT/Claude. La configuración de cada proveedor y el callback real se comprueban al conectar.
