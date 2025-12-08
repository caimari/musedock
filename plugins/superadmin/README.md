# Plugins de Superadmin

Este directorio contiene plugins exclusivos para el **dominio base** de MuseDock (panel superadmin).

## Características

- ✅ **Aislados de tenants**: Los plugins aquí son completamente independientes de los plugins de cada tenant
- ✅ **Solo dominio base**: Únicamente se cargan cuando se accede al panel superadmin (`/musedock`)
- ✅ **Gestión desde panel**: Se administran desde `/musedock/plugins`

## Estructura

Cada plugin debe estar en su propio directorio:

```
superadmin/
├── mi-plugin/
│   ├── plugin.json      # Metadatos (recomendado)
│   ├── mi-plugin.php    # Archivo principal
│   └── ...
└── otro-plugin/
    └── ...
```

## Crear un Plugin

1. Crea un directorio con el slug de tu plugin
2. Agrega un archivo `plugin.json` con los metadatos
3. Crea el archivo PHP principal con tu código
4. Ve a `/musedock/plugins` y haz clic en "Instalar"

## Ejemplo Mínimo

**plugin.json**
```json
{
    "slug": "mi-plugin",
    "name": "Mi Plugin",
    "version": "1.0.0",
    "main_file": "mi-plugin.php"
}
```

**mi-plugin.php**
```php
<?php
/**
 * Plugin Name: Mi Plugin
 * Version: 1.0.0
 */

// Tu código aquí
error_log("Mi Plugin cargado!");
```

## Documentación Completa

Consulta la documentación completa en:
`docs/SISTEMA_PLUGINS_SUPERADMIN.md`

---

**Nota:** Este directorio está gitignoreado. Los plugins se instalan manualmente o mediante el panel.
