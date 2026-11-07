# MuseDock Forms 1.0.0

Implementación propia para MuseDock CMS: formularios multi-tenant, editor visual y JSON, validación de servidor, almacenamiento de solicitudes, área privada de envíos y exportación CSV, shortcode y migración de Form Maker. No es un plugin de WordPress y no incluye su código.

## Fuente de verdad e instalación

La única fuente ejecutable es `modules/musedock-forms/`. El descriptor `plugins/musedock-forms/plugin.json` apunta aquí; no contiene una copia del código. Los archivos de `public/assets/modules/musedock-forms/` se generan a partir de `assets/` al instalar.

En el servidor de MuseDock, con ambos módulos disponibles:

```bash
php modules/musedock-forms/cli/install.php --tenant=ID
php modules/musedock-forms/cli/install.php --tenant=ID --apply
```

La primera orden simula. La segunda crea tablas, registra los módulos y activa Forms y Members únicamente para ese tenant. Nunca desinstala ni borra datos al desactivar. El soporte comprobado de instalación/importación es PostgreSQL. El runtime y esquema contemplan MySQL, sin validación de integración en MySQL en esta entrega.

Para instalar un ZIP, el paquete lleva `module.json`, `routes.php`, `install.php` y los demás archivos en la raíz. El script `install.php` instala el esquema y los recursos; la activación por tenant se realiza en el CMS o con el CLI anterior.

## Uso

- Gestión autenticada: `/md-forms/manage`. Requiere administrador del tenant y permiso `custom_forms.manage`, reutilizado para integrarse en la política de formularios existente.
- Publicación: `[musedock-form slug="mi-formulario"]`.
- Editor visual: añadir, quitar, subir/bajar campos; tipo, etiqueta, obligatoriedad y opciones. También se conserva el editor JSON.
- Tipos: text, email, tel, url, date, number, textarea, select, checkbox, checkboxes.
- Los envíos nuevos se guardan; esta versión no envía notificaciones de solicitudes automáticamente ni permite subir archivos nuevos.
- El formulario se sirve dentro de un iframe del mismo origen, sin caché y con CSRF de sesión. El padre ajusta la altura mediante mensajes que verifican origen y ventana.
- Los campos de comunicaciones son opcionales y no están marcados por defecto.

## Migración

El importador se distribuye con Members (`modules/musedock-members/cli/import-wordpress.php`) para trasladar en un proceso coherente Form Maker y Ultimate Member. Extrae las definiciones originales, campos, respuestas y fechas. Mantiene los identificadores de origen; una repetición no duplica ni sobrescribe respuestas.

Las respuestas históricas permanecen con su estructura original en `legacy_data`, además del mapa de campos del nuevo formulario. Las definiciones originales completas quedan en `legacy_definition`; no se evalúan scripts o HTML del plugin antiguo. Los editores o cambios futuros no borran estas referencias.

## Seguridad y límites

Todas las consultas de acceso incluyen el tenant. Las páginas de gestión y las solicitudes no son públicas. No se habilita por defecto en otros tenants. CSRF, honeypot, limitación de intentos y validación del servidor. No se publica DNI ni datos de solicitudes. Los registros previos no se consideran nuevas aceptaciones de privacidad.

No promete equivalencia con todos los tipos, pagos, integraciones o complementos de Form Maker: esta versión implementa el flujo y campos usados por ACA. Los tipos no soportados abortan la importación en vez de descartarlos silenciosamente.
