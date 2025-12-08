# MuseDock CMS

**Español**

Sistema de Gestión de Contenidos moderno, modular y multi-tenant construido con PHP 8+ y un framework MVC personalizado.

![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)

## Características

- **Arquitectura Multi-Tenant** - Aloja múltiples sitios web desde una sola instalación
- **Sistema Modular** - Habilita/deshabilita funcionalidades según necesites (Blog, Galerías, Formularios, etc.)
- **Integración con IA** - Soporte integrado para generación de contenido con IA (OpenAI, Anthropic)
- **Panel de Administración Moderno** - Dashboard limpio y responsive con Bootstrap 5
- **Sistema de Temas** - Motor de plantillas Blade con temas personalizables
- **Gestor de Medios** - Gestión avanzada de archivos con soporte para almacenamiento en la nube (S3, R2)
- **Control de Acceso Basado en Roles** - Sistema de permisos granular
- **Optimizado para SEO** - Meta tags, slugs, sitemaps
- **Multi-Idioma** - Soporte completo de internacionalización
- **Seguridad Primero** - Protección CSRF, limitación de peticiones, 2FA, WAF

## Requisitos

- PHP 8.0 o superior
- MySQL 5.7+ / MariaDB 10.3+ o PostgreSQL 12+
- Composer
- Extensiones: pdo, pdo_mysql, json, mbstring, openssl, curl, fileinfo, gd

## Instalación

### Opción 1: Instalación por FTP (Hosting Compartido - Sin SSH)

**Perfecto para hosting compartido sin acceso SSH.**

1. **Descargar la versión de producción**:
   - Ve a [GitHub Releases](https://github.com/caimari/musedock/releases)
   - Descarga `musedock-vX.X.X-complete.zip` (incluye todas las dependencias)

2. **Extraer y subir**:
   - Extrae el ZIP en tu ordenador
   - Sube todos los archivos vía FTP a tu hosting

3. **Configurar document root**:
   - ⚠️ **IMPORTANTE:** Apunta tu servidor web a la carpeta `public/`
   - Ejemplo: `public_html/musedock/public`

4. **Abrir navegador**:
   ```
   http://tu-dominio.com/install/
   ```

5. **Seguir el asistente** - Base de datos, cuenta admin, ¡listo!

📖 [Guía Detallada de Instalación FTP](INSTALL_FTP.md)

### Opción 2: Instalador Web (VPS/Dedicado - Con SSH)

1. **Clonar e instalar**:
   ```bash
   git clone https://github.com/caimari/musedock.git
   cd musedock
   composer install --no-dev --optimize-autoloader
   ```

2. **Abrir navegador**:
   ```
   http://tu-dominio.com/install/
   ```

3. **Seguir el asistente** para configurar base de datos y cuenta de administrador.

### Opción 3: Composer Create-Project (Recomendado para Desarrolladores)

La forma más rápida de crear un nuevo proyecto:

```bash
composer create-project caimari/musedock mi-proyecto
cd mi-proyecto
```

Luego abre `http://tu-dominio.com/install/` en tu navegador.

**¿Qué hace automáticamente?**
- ✅ Descarga MuseDock CMS y todas las dependencias
- ✅ Crea el archivo `.env` desde `.env.example`
- ✅ Configura el autoloader optimizado
- ✅ Te muestra las instrucciones para el siguiente paso

### Opción 4: Instalación Manual CLI

1. Clonar e instalar dependencias:
   ```bash
   git clone https://github.com/caimari/musedock.git
   cd musedock
   composer install --no-dev
   ```

2. Copiar archivo de entorno:
   ```bash
   cp .env.example .env
   ```

3. Editar `.env` con tus credenciales de base de datos:
   ```env
   DB_HOST=localhost
   DB_NAME=tu_base_datos
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   ```

4. Ejecutar migraciones y seeders:
   ```bash
   php migrate --seed
   ```

5. Crear tu usuario administrador vía base de datos o usar el seeder.

## Configuración

### Variables de Entorno

Opciones clave de configuración en `.env`:

| Variable | Descripción | Por Defecto |
|----------|-------------|-------------|
| `APP_ENV` | Entorno (production/development) | production |
| `APP_DEBUG` | Habilitar modo debug | false |
| `APP_URL` | URL de tu sitio | http://localhost |
| `DB_DRIVER` | Driver de base de datos (mysql/pgsql) | mysql |
| `MULTI_TENANT_ENABLED` | Habilitar multi-tenancy | false |
| `REDIS_ENABLED` | Habilitar caché Redis | false |

### Estructura de Directorios

```
musedock/
├── config/              # Archivos de configuración
├── core/                # Clases del framework core
│   ├── Controllers/     # Controladores base
│   ├── Middlewares/     # Middlewares de peticiones
│   ├── Models/          # Modelos base
│   └── Views/           # Vistas del panel admin
├── database/
│   ├── migrations/      # Migraciones de base de datos
│   └── seeders/         # Seeders de datos
├── install/             # Instalador web
├── modules/             # Módulos instalables
├── public/              # Raíz web (APUNTA AQUÍ)
│   └── assets/          # CSS, JS, imágenes
├── routes/              # Definiciones de rutas
├── storage/             # Logs, caché, uploads
└── themes/              # Temas del frontend
```

## Módulos Disponibles

| Módulo | Descripción |
|--------|-------------|
| `blog` | Blog completo con categorías y etiquetas |
| `media-manager` | Gestión de archivos e imágenes |
| `image-gallery` | Galerías de fotos |
| `custom-forms` | Constructor de formularios |
| `react-sliders` | Sliders de imágenes con React |

## Comandos CLI

```bash
# Migraciones
php migrate                     # Ejecutar migraciones pendientes
php migrate status              # Verificar estado de migraciones
php migrate --seed              # Ejecutar migraciones con seeders
php migrate rollback            # Revertir último lote
php migrate fresh --seed        # Instalación limpia con seeders

# Generar migraciones desde base de datos existente
php generate-migrations         # Generar todas las migraciones faltantes
php generate-migrations --list  # Listar tablas sin migraciones
```

## Panel de Administración

Accede al panel de administración en:
- **Superadmin**: `https://tu-dominio.com/musedock/`
- **Admin del Tenant**: `https://tu-dominio.com/admin/`

## Seguridad

MuseDock incluye características de seguridad integradas:

- Protección CSRF en todos los formularios
- Limitación de peticiones para intentos de login
- Autenticación de dos factores (TOTP)
- Hash de contraseñas con bcrypt
- Prevención de inyección SQL (PDO prepared statements)
- Headers de protección XSS
- Content Security Policy
- Lista negra de IPs

## Instalación vía Composer (Packagist)


```bash
composer create-project caimari/musedock mi-sitio
```


## Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## Autor

**Antoni Caimari Caldes**
- Sitio web: [screenart.es](https://screenart.es)
- GitHub: [@caimari](https://github.com/caimari)

## Soporte

- Documentación: [musedock.org](https://musedock.org)
- Issues: [GitHub Issues](https://github.com/caimari/musedock/issues)

## Preguntas Frecuentes (FAQ)

### ¿Cómo instalo con Composer?

```bash
composer create-project caimari/musedock nombre-proyecto
cd nombre-proyecto
# Visita http://tu-dominio.com/install/
```

### ¿Necesito acceso SSH para instalar?

No. Puedes usar la **Opción 1 (FTP)** descargando el release completo desde GitHub que ya incluye todas las dependencias.

### ¿Cómo actualizo MuseDock?

Con Composer:
```bash
composer update caimari/musedock
```

O descarga el nuevo release y reemplaza los archivos (respetando `.env` y `storage/`).

### ¿Puedo usar MuseDock en hosting compartido?

Sí, usa la instalación por FTP. Solo necesitas:
- PHP 8.0+
- MySQL/MariaDB
- Configurar el document root a la carpeta `public/`

### ¿Dónde está la documentación completa?

Visita [musedock.org/docs](https://musedock.org/docs) (próximamente).

---

**¡Construye algo increíble con MuseDock CMS! 🚀**
