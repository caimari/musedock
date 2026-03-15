# MuseDock CMS

Version 2.2.0 — CMS multi-tenant estable con panel Superadmin, panel Tenant Admin, gestor de dominios con Caddy y sistema de plugins.

CMS modular con framework MVC propio basado en Blade One. Arquitectura multi-tenant para alojar múltiples dominios desde una sola instalación. Sistema de módulos base (compartidos) y plugins independientes por tenant. Almacenamiento seguro en storage/ (no public/) con soporte S3/R2. Multi-idioma integrado.

![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Version](https://img.shields.io/badge/version-2.2.0-orange.svg)

> **IMPORTANTE:** El hosting debe apuntar el document root a la carpeta `public/`

## Instalación

### Git (VPS/Dedicado)

```bash
# Instalar
git clone https://github.com/caimari/musedock.git .
composer install --no-dev --no-progress
```

Después de instalar, visita `http://tu-dominio.com/install/` para el asistente de configuración (crea base de datos, usuario superadmin y contraseña).

```bash
# Actualizar
git pull origin main
```

### Composer

```bash
# Instalar
composer create-project caimari/musedock .

# Actualizar
composer update
```

Después de instalar, visita `http://tu-dominio.com/install/` para el asistente de configuración.

### Instalación sin asistente web

```bash
# 1. Copiar configuración
cp .env.example .env

# 2. Editar .env con credenciales de BD

# 3. Ejecutar migraciones con seeders
php cli/migrate.php --seed

# 4. Crear usuario superadmin manualmente en BD
# Tabla: superadmin_users (password con password_hash())
```

### FTP (Hosting compartido)

1. Descarga `musedock-vX.X.X-complete.zip` desde [Releases](https://github.com/caimari/musedock/releases)
2. Sube los archivos vía FTP
3. Apunta el document root a `public/`
4. Visita `http://tu-dominio.com/install/`

## Requisitos

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+ / PostgreSQL 12+
- Extensiones: pdo, pdo_mysql, json, mbstring, openssl, curl, fileinfo, gd

## Características principales

- **Multi-Tenant** — Múltiples dominios/sitios en una sola instalación
- **Panel Superadmin** (`/musedock/`) — Gestión global del CMS, dominios, tenants y módulos
- **Panel Tenant Admin** (`/admin/`) — Panel independiente por cada dominio/tenant
- **Gestor de dominios Caddy** — Alta, baja y configuración automática de dominios con Caddy Server + Cloudflare DNS
- **Blog completo** — Posts, categorías, tags, feeds RSS/Atom, SEO, autor
- **Media Manager** — Gestión de archivos con almacenamiento seguro en storage/, compatible S3/R2
- **IA integrada** — AI Writer para generar contenido, soporte multi-proveedor (OpenAI, Anthropic, etc.)
- **AI Image** — Generación de imágenes con IA
- **Temas** — Sistema de temas con múltiples layouts (default, musedock, play-bootstrap, react-modern, HighTechIT)
- **URLs SEO-friendly** — Slugs limpios indexables por Google, sitemaps, robots.txt
- **Multi-idioma** — i18n integrado (ES, EN)
- **Seguridad** — CSRF, 2FA, rate limiting, WAF, sanitización HTML
- **Web Analytics** — Analíticas integradas sin dependencias externas
- **Módulos y plugins** — Arquitectura extensible con módulos base y plugins por tenant

## Módulos incluidos

| Módulo | Descripción |
|--------|-------------|
| `blog` | Blog completo con posts, categorías, tags, feeds RSS/Atom y SEO |
| `media-manager` | Gestor de archivos multimedia con soporte S3/R2 |
| `ai-writer` | Escritor IA integrado en TinyMCE |
| `ai-image` | Generación de imágenes con IA |
| `custom-forms` | Formularios personalizados |
| `image-gallery` | Galerías de imágenes |
| `react-sliders` | Sliders con React |

## Plugins Superadmin

| Plugin | Descripción |
|--------|-------------|
| `caddy-domain-manager` | Gestión automática de dominios con Caddy Server y Cloudflare DNS |

## Estructura

```
musedock/
├── config/          # Configuración
├── core/            # Framework MVC
├── database/        # Migraciones y seeders
├── lang/            # Traducciones (superadmin y tenant)
├── modules/         # Módulos base (compartidos)
├── plugins/         # Plugins (superadmin y tenant)
├── public/          # Document root
├── routes/          # Rutas (web, admin, superadmin, API)
├── storage/         # Archivos, logs, caché, datos tenant
└── themes/          # Temas frontend
```

## Configuración Multi-Tenant

Editar `.env`:

```env
MULTI_TENANT_ENABLED=true
MAIN_DOMAIN=tu-dominio.com
DEFAULT_THEME=default
DEFAULT_LANG=es
```

Los dominios de tenants se configuran desde el panel Superadmin con el plugin Caddy Domain Manager.

## CLI - Migraciones

```bash
# Ver estado de migraciones
php cli/migrate.php --status

# Ejecutar todas las pendientes
php cli/migrate.php

# Ejecutar una migración específica
php cli/migrate.php --run=000240_create_tenant_default

# Revertir una migración
php cli/migrate.php --rollback=000240_create_tenant_default

# Fresh: revertir todas y ejecutar de nuevo
php cli/migrate.php --fresh
```

## CLI - Seeders

```bash
# Ejecutar pendientes
php cli/migrate.php seed

# Ver estado
php cli/migrate.php seed --status

# Ejecutar uno específico
php cli/migrate.php seed --run=NOMBRE

# Re-ejecutar (forzar)
php cli/migrate.php seed --rerun=NOMBRE

# Marcar como no ejecutado
php cli/migrate.php seed --rollback=NOMBRE
```

## Licencia

MIT - [LICENSE](LICENSE)

## Autor

**Antoni Caimari Caldes** - [screenart.es](https://screenart.es) - [@caimari](https://github.com/caimari)

## Soporte

- [Documentación](https://musedock.org)
- [Issues](https://github.com/caimari/musedock/issues)
