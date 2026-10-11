# MuseDock CMS MCP — versión 2.20.0

Estado: implementación preparada en rama aislada. No desplegada ni activada en producción.

## Independencia

El CMS posee su código, SQLite privado, clientes OAuth, consentimientos, secretos y herramientas. No incluye ni ejecuta código de `/opt/musedock-panel`, no utiliza sus servicios, DNS, túnel, clientes o tokens. El Panel sirvió únicamente como referencia del diseño de aprobación.

Código: `core/Services/Mcp/Direct/`. Almacenamiento por defecto: un directorio `.musedock-cms` junto a `httpdocs`, fuera del árbol público, con directorio 0700 y fichero 0600. Puede configurarse `MCP_CMS_PRIVATE_DIRECTORY` con una ruta privada absoluta fuera de `httpdocs`. SQLite debe estar en disco local y requiere PDO SQLite. Incluir este fichero privado en las copias cifradas del operador; nunca en Git ni en archivos públicos.

## Recursos y límites

| Recurso | URL | Alcance |
| --- | --- | --- |
| `main` | `https://{APP_URL-host}/mcp` | Contenido con `tenant_id IS NULL` o `tenant_id = 0` |
| `tenant:{id}` | `https://{dominio-canónico}/mcp` | Únicamente ese tenant |
| `plugin:cross-publisher` | `https://{APP_URL-host}/mcp/cross-publisher` | Intersección de sitios propios del recurso, sitios del cliente y grupos actuales |
| `plugin:news-aggregator` | `https://{APP_URL-host}/mcp/news-aggregator` | La misma intersección, para noticias y borradores |

El sitio principal se identifica como `main`, no como una clave superadmin sin restricciones. El selector de sitio no puede ampliar su alcance. Los plugins editoriales solo se sirven desde el dominio principal y estando instalados y activos. No se deduce la propiedad de un sitio por pertenecer a un grupo: un superadmin marca explícitamente los sitios propios en la configuración del recurso, y cada cliente selecciona un subconjunto y grupos. Cambiar un sitio de grupo elimina su acceso en ejecución. Añadir un tenant nuevo a un grupo no lo autoriza automáticamente.

Los nuevos tokens `cms_access_…` no sirven para REST ni para otro recurso. Las claves REST `mdk_…` y las conexiones antiguas no se convierten ni amplían automáticamente.

## OAuth y herramientas

Registro manual de clientes, callback HTTPS exacto sin comodines, `client_secret_basic`, Authorization Code con PKCE S256, recurso exacto obligatorio, códigos consumidos mediante transacción, access tokens de una hora y refresh de 30 días con rotación y revocación de familia ante reutilización. Se guardan hashes de secretos, códigos y tokens. Consentimiento con sesión del propio CMS y CSRF. El acceso y la aprobación vuelven a comprobar el usuario y sus permisos vigentes.

Issuer por recurso: `https://{dominio}/mcp-auth/{cms|cross-publisher|news-aggregator}`. Metadatos de autorización: `/.well-known/oauth-authorization-server/mcp-auth/{nombre}`. Metadatos del recurso: `/.well-known/oauth-protected-resource/mcp` o la misma ruta seguida del slug del plugin. MCP responde 401 con `resource_metadata`. El primer salto de autorización hace un rebote para cookies SameSite=Strict. El login del CMS devuelve al consentimiento mediante una ruta interna validada.

Herramientas explícitas, sin descubrimiento automático de capacidades nuevas:

- Lectura: `list_posts`, `get_post`, `list_categories`, `list_tags`.
- Borradores: `create_draft`, `update_draft`.
- Plugins: `list_sites`; Cross Publisher añade `copy_draft`; News Aggregator añade `list_news_items`, `import_news_draft`.

Ámbitos: `musedock-cms:read`, `musedock-cms:drafts:create`, `musedock-cms:drafts:update`, `musedock-cross-publisher:drafts:copy` y `musedock-news-aggregator:drafts:import`. No hay permisos de DNS, hosting, publicación o borrado en este catálogo.

`apply=false` muestra el plan. `apply=true` deja una solicitud de diez minutos (máximo 20 por cliente), sin escribir artículos. Un administrador con sesión y CSRF aprueba o rechaza en el CMS. La aprobación recalcula el plan, revalida recurso, cliente, consentimiento, usuario, grupos, sitios y taxonomías; cambios en el plan exigen una solicitud nueva. La solicitud se consume antes de escribir. Fallos no se reintentan automáticamente. Los cambios de contenido son transaccionales y siempre generan borradores, con slugs y taxonomías del sitio autorizado.

Cross Publisher copia texto sin IA y registra la relación con sincronización desactivada, para que un cron posterior no modifique contenido fuera de este consentimiento. Evita una segunda copia vinculada al mismo destino. News Aggregator importa el texto disponible (reescrito, si ya existía; original en otro caso), sin ejecutar IA, y registra el artículo creado. Su item adopta el estado interno `published`, como hace el plugin al convertir una noticia en un post aunque el post sea un borrador; esto lo retira del pipeline de conversión automática. El post sigue en `draft`.

## Interfaz

Dominio principal: Ajustes → Conexiones IA (MCP), `/musedock/settings/mcp`. Las pestañas separan CMS principal y ambos plugins. Cada plugin tiene un acceso desde su dashboard. Tenant: su pantalla existente enlaza a `/{admin_path}/mcp/direct`; tras adoptar la modalidad gestionada, el acceso habitual `/mcp` del panel redirige a ella.

La pantalla separa estado del servicio, clientes y consentimientos. Permite crear credenciales (secreto inicialmente oculto, copia y retirada del DOM), cambiar ámbitos con revocación de consentimientos, revocar cliente o consentimiento, revisar solicitudes y actividad. El secreto nuevo se retira de la sesión tras una visualización. Mostrar de nuevo un secreto anterior no está permitido.

Preparar credenciales de un tenant mientras el servicio nuevo está desactivado no interrumpe su MCP anterior. Al activar y adoptar la modalidad gestionada, sus clientes deben reconectarse; desde entonces desactivarla no vuelve silenciosamente al MCP antiguo.

## Red y transporte

Conexión directa HTTPS 443, Streamable HTTP sin stream SSE; POST JSON-RPC, GET autenticado 405, notificaciones 202. No modifica firewall, Caddy, DNS o certificados. Los metadatos y consentimiento son accesibles desde navegador. Se puede limitar cada cliente a IPs/CIDR de salida configurados por el operador, aplicados a MCP y a tokens. OAuth sigue siendo obligatorio. No se autorizan redes por User-Agent, cabeceras de proveedor o intentos observados.

La IP procede de `IPHelper`: cabeceras reenviadas solo desde proxies de confianza. El operador debe revisar `TRUSTED_PROXIES` y Cloudflare en su topología antes de activar. Esta entrega no descarga listas de proveedores ni instala su actualización automática; los CIDR manuales requieren mantenimiento explícito del operador.

No se implementa el túnel OpenAI: la interfaz muestra esa modalidad como no configurada, independiente del Panel. No se añaden publicación, traducción, readaptación, captura de fuentes ni sincronización automática a MCP. Estas operaciones conservan sus pantallas actuales del plugin y requerirán ámbitos y pruebas propios para una futura entrega.

Referencias de compatibilidad consultadas el 11 de octubre de 2026:
- https://developers.openai.com/plugins/build/auth
- https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp

Se debe usar el callback exacto mostrado por cada asistente. Esta entrega requiere clientes que admitan registro manual. No certifica el flujo de una cuenta real ChatGPT/Claude ni disponibilidad según suscripción.

## API anterior y cola Cross Publisher

Se cierran accesos por ID fuera del grupo en consulta, edición y borrado de posts REST. Cross Publisher valida origen y todos los destinos antes de encolar. Se corrige `cross_publisher_queue` a la tabla real `cross_publish_queue` y se guarda `origin_api_key_id`.

La migración `2026_10_11_130000` añade esa columna a instalaciones existentes. El cambio complementario del repositorio de plugins privados añade la columna a nuevas instalaciones y hace que `CrossPublishService` revalide la clave al ejecutar, incluyendo revocación, caducidad, grupos y permiso de publicación. Jobs de la interfaz privada anterior sin clave origen mantienen su comportamiento. Los nuevos jobs API fallan cerrados si falta la migración o el guard del CMS. Desplegar CMS y parche del plugin juntos.

## Validación y activación

Consultar `VALIDATION.md`. Los ensayos usan fixtures SQLite, una instancia temporal de MariaDB por socket privado y servidor PHP en loopback, sin conectar al SQL de producción ni ejecutar IA. Falta validación en una copia completa del esquema del despliegue, navegador autenticado y las cuentas reales de asistentes.

Hasta recibir autorización explícita después de presentar los resultados:
- no aplicar migraciones a producción;
- no integrar esta rama en el checkout servido;
- no crear credenciales reales;
- no activar `MCP_CMS_DIRECT_ENABLED`.

Tras autorización: respaldar DB y almacenamiento privado, desplegar los commits de CMS y plugin, ejecutar las migraciones, verificar APP_URL HTTPS, preparar los clientes y permisos, activar el interruptor global con `MCP_CMS_DIRECT_ENABLED=true`, y después habilitar únicamente los recursos aprobados. Comprobar TLS, descubrimiento y consentimiento con cada cuenta. Empezar con lectura y un borrador de prueba.

Rollback: desactivar el interruptor global y los recursos para impedir nuevos accesos, revocar clientes si procede y volver a los commits anteriores. No borrar a ciegas los artículos que ya se aprobaron ni retirar la columna origen mientras queden jobs API. Una restauración del SQLite debe mantener el acceso desactivado y revocar las concesiones restauradas antes de reabrir el servicio.
