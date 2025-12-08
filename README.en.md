# MuseDock CMS

**English** | [Spanish](README.md)

A modern, modular, multi-tenant Content Management System built with PHP 8+ and a custom MVC framework.

![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)

## Features

- **Multi-Tenant Architecture** - Host multiple websites from a single installation
- **Modular System** - Enable/disable features as needed (Blog, Galleries, Forms, etc.)
- **AI Integration** - Built-in support for AI content generation (OpenAI, Anthropic)
- **Modern Admin Panel** - Clean, responsive dashboard with Bootstrap 5
- **Theme System** - Blade templating engine with customizable themes
- **Media Manager** - Advanced file management with cloud storage support (S3, R2)
- **Role-Based Access Control** - Granular permissions system
- **SEO Optimized** - Meta tags, slugs, sitemaps
- **Multi-Language** - Full i18n support
- **Security First** - CSRF protection, rate limiting, 2FA, WAF

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB 10.3+ or PostgreSQL 12+
- Composer
- Extensions: pdo, pdo_mysql, json, mbstring, openssl, curl, fileinfo, gd

## Installation

### Option 1: FTP Installation (Shared Hosting - No SSH Required)

**Perfect for shared hosting without SSH access.**

1. **Download production release**:
   - Go to [GitHub Releases](https://github.com/caimari/musedock/releases)
   - Download `musedock-vX.X.X-complete.zip` (includes all dependencies)

2. **Extract and upload**:
   - Extract ZIP on your computer
   - Upload all files via FTP to your hosting

3. **Configure document root**:
   - ⚠️ **IMPORTANT:** Point your web server to the `public/` folder
   - Example: `public_html/musedock/public`

4. **Open browser**:
   ```
   http://your-domain.com/install/
   ```

5. **Follow the wizard** - Database, admin account, done!

📖 [Detailed FTP Installation Guide](INSTALL_FTP.md)

### Option 2: Web Installer (VPS/Dedicated - With SSH)

1. **Clone and install**:
   ```bash
   git clone https://github.com/caimari/musedock.git
   cd musedock
   composer install --no-dev --optimize-autoloader
   ```

2. **Open browser**:
   ```
   http://your-domain.com/install/
   ```

3. **Follow the wizard** to configure database and admin account.

### Option 3: Composer Create-Project (Recommended for Developers)

The quickest way to create a new project:

```bash
composer create-project caimari/musedock my-project
cd my-project
```

Then open `http://your-domain.com/install/` in your browser.

**What does it do automatically?**
- ✅ Downloads MuseDock CMS and all dependencies
- ✅ Creates `.env` file from `.env.example`
- ✅ Sets up optimized autoloader
- ✅ Shows you the next steps

### Option 4: Manual CLI Installation

1. Clone and install dependencies:
   ```bash
   git clone https://github.com/caimari/musedock.git
   cd musedock
   composer install --no-dev
   ```

2. Copy environment file:
   ```bash
   cp .env.example .env
   ```

3. Edit `.env` with your database credentials:
   ```env
   DB_HOST=localhost
   DB_NAME=your_database
   DB_USER=your_username
   DB_PASS=your_password
   ```

4. Run migrations and seeders:
   ```bash
   php migrate --seed
   ```

5. Create your admin user via the database or use the seeder.

## Configuration

### Environment Variables

Key configuration options in `.env`:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Environment (production/development) | production |
| `APP_DEBUG` | Enable debug mode | false |
| `APP_URL` | Your site URL | http://localhost |
| `DB_DRIVER` | Database driver (mysql/pgsql) | mysql |
| `MULTI_TENANT_ENABLED` | Enable multi-tenancy | false |
| `REDIS_ENABLED` | Enable Redis caching | false |

### Directory Structure

```
musedock/
├── config/              # Configuration files
├── core/                # Core framework classes
│   ├── Controllers/     # Base controllers
│   ├── Middlewares/     # Request middlewares
│   ├── Models/          # Base models
│   └── Views/           # Admin panel views
├── database/
│   ├── migrations/      # Database migrations
│   └── seeders/         # Data seeders
├── install/             # Web installer
├── modules/             # Installable modules
├── public/              # Web root (POINT HERE)
│   └── assets/          # CSS, JS, images
├── routes/              # Route definitions
├── storage/             # Logs, cache, uploads
└── themes/              # Frontend themes
```

## Available Modules

| Module | Description |
|--------|-------------|
| `blog` | Full-featured blog with categories and tags |
| `media-manager` | File and image management |
| `image-gallery` | Photo galleries |
| `custom-forms` | Form builder |
| `react-sliders` | Image sliders with React |

## CLI Commands

```bash
# Migrations
php migrate                     # Run pending migrations
php migrate status              # Check migration status
php migrate --seed              # Run migrations with seeders
php migrate rollback            # Rollback last batch
php migrate fresh --seed        # Fresh install with seeders

# Generate migrations from existing database
php generate-migrations         # Generate all missing migrations
php generate-migrations --list  # List tables without migrations
```

## Admin Panel

Access the admin panel at:
- **Superadmin**: `https://your-domain.com/musedock/`
- **Tenant Admin**: `https://your-domain.com/admin/`

## Security

MuseDock includes built-in security features:

- CSRF protection on all forms
- Rate limiting for login attempts
- Two-factor authentication (TOTP)
- Password hashing with bcrypt
- SQL injection prevention (PDO prepared statements)
- XSS protection headers
- Content Security Policy
- IP blacklisting

## Installing via Composer (Packagist)

```bash
composer create-project caimari/musedock my-site
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

**Antoni Caimari Caldes**
- Website: [screenart.es](https://screenart.es)
- GitHub: [@caimari](https://github.com/caimari)

## Support

- Documentation: [musedock.org](https://musedock.org)
- Issues: [GitHub Issues](https://github.com/caimari/musedock/issues)

## Screenshots

_(You can add screenshots here later)_

## FAQ

### How do I install with Composer?

```bash
composer create-project caimari/musedock project-name
cd project-name
# Visit http://your-domain.com/install/
```

### Do I need SSH access to install?

No. You can use **Option 1 (FTP)** by downloading the complete release from GitHub which already includes all dependencies.

### How do I update MuseDock?

With Composer:
```bash
composer update caimari/musedock
```

Or download the new release and replace files (respecting `.env` and `storage/`).

### Can I use MuseDock on shared hosting?

Yes, use the FTP installation. You only need:
- PHP 8.0+
- MySQL/MariaDB
- Configure document root to the `public/` folder

### Where is the complete documentation?

Visit [musedock.org/docs](https://musedock.org/docs) (coming soon).

---

**Build something amazing with MuseDock CMS! 🚀**
