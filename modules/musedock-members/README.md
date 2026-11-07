# MuseDock Members 1.0.0

Módulo propio de socios para MuseDock CMS. Fuente ejecutable única: `modules/musedock-members/`. Depende de `musedock-forms`. El descriptor de `plugins/` es un puntero al módulo, no otro runtime.

## Capacidades

- Importación de cuentas y definiciones de Ultimate Member, sin arrancar WordPress ni modificarlo.
- Inicio de sesión de socios separado del administrador CMS. Un administrador WordPress importado NO recibe privilegios de administrador MuseDock.
- Conservación del estado aprobado/pendiente original. Los pendientes no acceden hasta aprobación.
- Registro de socios internacionales sujeto a aprobación; los formularios de artista y simpatizante conservan su naturaleza de solicitudes separadas, sin crear cuentas automáticamente.
- Gestión privada en `/md-members/manage`: fichas, aprobación/rechazo y auditoría de cambios. Permiso CMS `custom_forms.manage` y tenant autenticado correcto.
- Cuenta privada: datos personales y perfil. Directorio con autorización expresa; solo nombre y tipo. El resto de datos no se publica, aunque el original ofreciera más campos.
- Los ajustes históricos de perfil público/privado se conservan como datos de origen. La publicación nueva se inicia desactivada por prudencia; el socio puede autorizarla.
- Compatibilidad con hashes portables WordPress `$P$`/`$H$`, bcrypt nativo y `$wp` moderno. Al acceder correctamente se actualiza el hash, sin conocer la contraseña.
- Recuperación con token aleatorio, hash en BD, caducidad de una hora, uso único y revocación de sesiones. El envío usa el Mailer y SMTP del tenant. No se enviaron correos de prueba durante esta entrega; verificar SMTP antes de anunciar recuperación por correo.
- Fotografías originales preservadas en almacenamiento privado; la foto de perfil solo se sirve al propio socio autenticado, con control de MIME.

## Instalación y publicación

Ver `../musedock-forms/README.md`. Ambos módulos se distribuyen como paquetes de módulo CMS, no como plugins ChatGPT ni WordPress. Activar solamente en tenants elegidos.

Shortcode de página:

```text
[musedock-member view="register"]
[musedock-member view="login"]
[musedock-member view="account"]
[musedock-member view="reset"]
[musedock-member view="directory"]
```

El formulario de registro configurable se selecciona con el ajuste del tenant `musedock_members_registration_form`; por defecto `international-associate`. Puede crearse otro formulario en Forms con claves `user_email`, `first_name` y `last_name` y los demás campos necesarios.

## Importación (CLI, PostgreSQL)

```bash
php modules/musedock-members/cli/import-wordpress.php \
  --tenant=ID \
  --source-config=/ruta/privada/wordpress/wp-config.php
```

Simula y muestra recuentos. Para aplicar, añadir `--apply --backup-dir=/ruta/privada/nueva` fuera del webroot público. Se requiere acceso autorizado de lectura a configuración y BD WordPress. El código usa una transacción MySQL READ ONLY para el origen; nunca crea archivos en WordPress ni activa plugins allí.

El importador guarda una copia privada con manifiesto SHA-256, conserva las definiciones originales, cuentas, estados, perfiles y respuestas de Form Maker, copia y verifica los archivos de Ultimate Member en privado y verifica los hashes originales y cada respuesta antes de confirmar. Los datos originales completos se conservan en `legacy_data` y en las copias privadas. Tokens, sesiones y privilegios CMS del WordPress no se reutilizan para iniciar sesiones nuevas.

Repetible sin duplicados por tenant/origen/ID. No sobrescribe perfiles modificados tras migrar. No borrar ni restaurar sobre datos nuevos automáticamente: para revertir, desactivar ambos módulos, restaurar solo las páginas editadas desde su copia y revisar primero si hay registros nuevos.

## Pruebas

`php modules/musedock-members/tests/run.php`: casos sintéticos en transacción revertida, contraseñas, validación, aislamiento, aprobación, revocación y perfil público. No envía correo.

`python3 modules/musedock-members/tests/http_check.py`: prueba HTTP específica de ACA, crea un formulario sintético y lo elimina en finally; valida CSRF, envío y protección de paneles. No contiene datos reales. Revisar mapeo de tenant antes de usar en otro dominio.

## Alcance de esta versión

Reconstruye las funciones que ACA utilizaba, con código propio. No implementa todos los complementos comerciales, mensajería social, cobros, subida de fotos nuevas ni correos personalizados de Ultimate Member. Las configuraciones y datos originales se archivan para futuras ampliaciones. La admisión sigue siendo decisión de la Junta. La migración no acredita por sí sola el cumplimiento de privacidad.
