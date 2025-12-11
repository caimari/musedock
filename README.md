# MuseDock CMS

CMS modular con framework MVC propio basado en Blade One. Arquitectura multi-tenant experimental para alojar múltiples dominios. Sistema de módulos base (compartidos) y plugins independientes por tenant. Almacenamiento seguro en storage/ (no public/) con soporte S3/R2. Multi-idioma integrado.

![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)

> **IMPORTANTE:** El hosting debe apuntar el document root a la carpeta `public/`

## Instalación

### Git (VPS/Dedicado)

```bash
# Instalar
git clone https://github.com/caimari/musedock.git .
composer update

Después de instalar, visita `http://tu-dominio.com/install/` para el asistente de configuración (crea base de datos, usuario superadmin y contraseña).

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

Después de instalar, visita `http://tu-dominio.com/install/` para el asistente de configuración (crea base de datos, usuario superadmin y contraseña).

### Instalación sin asistente web

Si prefieres no usar el instalador web:

```bash
# 1. Copiar configuración
cp .env.example .env

# 2. Editar .env con credenciales de BD

# 3. Ejecutar migraciones con seeders
php migrate --seed

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

## Características

- **Multi-Tenant** (experimental) - Múltiples dominios en una instalación
- **Módulos base** - Compartidos entre todos los tenants
- **Plugins por tenant** - Funcionalidades independientes por dominio
- **Almacenamiento seguro** - Archivos en storage/, compatible S3/R2
- **URLs SEO-friendly** - Media con slugs indexables por Google
- **Multi-idioma** - i18n integrado
- **Blade One** - Motor de plantillas
- **Seguridad** - CSRF, 2FA, rate limiting, WAF

## Estructura

```
musedock/
├── config/          # Configuración
├── core/            # Framework MVC
├── database/        # Migraciones y seeders
├── modules/         # Módulos base
├── public/          # Document root
├── routes/          # Rutas
├── storage/         # Archivos, logs, caché
└── themes/          # Temas frontend
```

## Panel de Administración

- **Superadmin**: `/musedock/` - Panel principal del CMS
- **Tenant Admin**: `/admin/` - Requiere un dominio configurado en el CMS apuntando al servidor

### Activar Multi-Tenant

Editar `.env`:

```env
MULTI_TENANT_ENABLED=true
MAIN_DOMAIN=tu-dominio.com
DEFAULT_THEME=default
DEFAULT_LANG=es
```

Los dominios de tenants se configuran desde el panel Superadmin.

## Comandos

```bash
php migrate              # Ejecutar migraciones
php migrate --seed       # Con seeders
php migrate fresh --seed # Reinstalar
```

## Licencia

MIT - [LICENSE](LICENSE)

## Autor

**Antoni Caimari Caldes** - [screenart.es](https://screenart.es) - [@caimari](https://github.com/caimari)

## Soporte

- [Documentación](https://musedock.org)
- [Issues](https://github.com/caimari/musedock/issues)
