# Repostea

**Open-source content aggregation platform** - A modern alternative to Reddit/Hacker News that you can self-host.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net)

## What is Repostea?

Repostea is a complete platform for building community-driven content aggregation sites. Think Reddit, Hacker News, or Menéame - but open source and self-hosted.

**Live demo**: [Renegados](https://app.renegados.es) - A Spanish-language community running Repostea in production.

### Features

- **Content**: Posts, comments, polls, media uploads
- **Voting**: Karma system with reputation levels
- **Communities**: Multiple "subs" with independent moderation
- **Real-time**: WebSocket chat (Agora), live notifications
- **Federation**: ActivityPub support (connect with Mastodon, Lemmy, Mbin)
- **i18n**: 15+ languages supported
- **Auth**: Local accounts + OAuth (Google, Twitter, Telegram, Mastodon)
- **Moderation**: Reports, bans, spam detection, admin panel

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | [Nuxt 3](https://github.com/repostea/client), Vue 3, TypeScript |
| Database | MySQL 8.0+ / MariaDB 10.6+ |
| Cache/Queue | Redis 7+ |
| WebSocket | Laravel Reverb |
| Auth | Laravel Sanctum |

## Quick Start (Development)

### Requirements

- PHP 8.2+
- Composer 2.x
- Node.js 18+ and pnpm
- MySQL 8.0+ or MariaDB 10.6+
- Redis 7+

### 1. Clone repositories

```bash
# Backend (this repo)
git clone https://github.com/repostea/server.git
cd server

# Frontend (in a separate directory)
git clone https://github.com/repostea/client.git ../client
```

### 2. Backend setup

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env with your database credentials
nano .env

# Create database tables
php artisan migrate

# (Optional) Load sample data
php artisan db:seed

# Create storage symlink
php artisan storage:link
```

### 3. Frontend setup

```bash
cd ../client

# Install dependencies
pnpm install

# Configure environment
cp .env.example .env
```

### 4. Start development servers

```bash
# Option A: From server directory (starts everything)
cd server
composer dev

# Option B: Manually (two terminals)
# Terminal 1 - Backend
php artisan serve

# Terminal 2 - Frontend
cd client && pnpm dev
```

Visit:
- **Frontend**: http://localhost:3000
- **API**: http://localhost:8000

## Quick Start with Docker (Alternative)

> **Note**: Docker is provided for quickly testing the platform locally. For production, we recommend the manual installation described in [INSTALL.md](INSTALL.md).

```bash
# Clone both repositories
git clone https://github.com/repostea/server.git
git clone https://github.com/repostea/client.git

# Start everything
cd server
docker compose up -d

# Wait ~2 minutes for first build, then visit:
# http://localhost:3000
```

Default login: `admin` / `changeme123`

To stop: `docker compose down`

To reset everything: `docker compose down -v`

## Production Deployment

See **[INSTALL.md](INSTALL.md)** for complete production deployment guide including:

- Server requirements
- Nginx/Apache configuration
- SSL setup
- Process management (PM2/Supervisor)
- Performance optimization

## Configuration

### Essential Environment Variables

**Backend** (`.env`):
```env
APP_NAME=YourSiteName
APP_URL=https://api.yoursite.com
FRONTEND_URL=https://yoursite.com

DB_DATABASE=repostea
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

**Frontend** (`client/.env`):
```env
NUXT_PUBLIC_API_BASE=https://api.yoursite.com/api
NUXT_PUBLIC_SITE_URL=https://yoursite.com
NUXT_PUBLIC_APP_NAME=YourSiteName
```

See `.env.example` files for all available options.

## Project Structure

```
server/
├── app/
│   ├── Console/Commands/   # Artisan commands
│   ├── Http/Controllers/   # API controllers
│   ├── Models/             # Eloquent models
│   └── Services/           # Business logic
├── database/
│   ├── migrations/         # Database schema
│   └── seeders/            # Sample data
├── routes/
│   ├── api.php             # API routes
│   └── web.php             # Web routes
└── tests/                  # Pest tests
```

## Commands

```bash
# Development
composer dev              # Start all services
php artisan serve         # API server only
php artisan queue:listen  # Process background jobs
php artisan reverb:start  # WebSocket server

# Quality
composer quality          # Run Pint + PHPStan + Tests
composer quality-fix      # Auto-fix code style

# Database
php artisan migrate       # Run migrations
php artisan db:seed       # Load sample data
php artisan tinker        # Interactive shell
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=PostTest

# With coverage
php artisan test --coverage
```

## Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Run quality checks (`composer quality`)
4. Commit your changes
5. Open a Pull Request

## Using Repostea?

We'd love to hear from you! If you're running a Repostea instance, please [open an issue](https://github.com/repostea/server/issues/new?labels=showcase&title=Showcase:%20[Your%20Site%20Name]) to let us know.

## Related Repositories

- **Frontend**: [repostea/client](https://github.com/repostea/client) - Nuxt 3 application

## License

[GPL-3.0](LICENSE) - You can use, modify, and distribute this software. If you modify and deploy it publicly, you must share your changes.
