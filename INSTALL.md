# Production Deployment Guide

Complete guide to deploy Repostea on a production server.

## Server Requirements

### Minimum Hardware
- **CPU**: 1 vCPU
- **RAM**: 2 GB
- **Disk**: 20 GB SSD

### Recommended (for 1000+ users)
- **CPU**: 2+ vCPU
- **RAM**: 4+ GB
- **Disk**: 50+ GB SSD

### Software Requirements

| Software | Version | Notes |
|----------|---------|-------|
| Ubuntu/Debian | 22.04+ / 12+ | Or any modern Linux |
| PHP | 8.2+ | With extensions below |
| Composer | 2.x | |
| Node.js | 18+ | LTS recommended |
| pnpm | 8+ | |
| MySQL | 8.0+ | Or MariaDB 10.6+ |
| Redis | 7+ | Optional but recommended |
| Nginx | 1.18+ | Or Apache 2.4+ |

### Required PHP Extensions

```bash
php -m | grep -E "bcmath|ctype|curl|dom|fileinfo|gd|intl|json|mbstring|openssl|pdo|pdo_mysql|redis|tokenizer|xml|zip"
```

Install on Ubuntu/Debian:
```bash
sudo apt install php8.2-{bcmath,cli,common,curl,fpm,gd,intl,mbstring,mysql,redis,xml,zip}
```

---

## Step 1: Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y git curl unzip nginx mysql-server redis-server

# Install PHP 8.2 (Ubuntu 22.04)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.2-fpm php8.2-{bcmath,cli,common,curl,gd,intl,mbstring,mysql,redis,xml,zip}

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
npm install -g pnpm

# Install PM2 (process manager)
npm install -g pm2
```

---

## Step 2: Database Setup

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE repostea CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'repostea'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON repostea.* TO 'repostea'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 3: Clone and Configure

### Create directory structure

```bash
sudo mkdir -p /var/www/repostea
sudo chown -R $USER:www-data /var/www/repostea
cd /var/www/repostea
```

### Clone repositories

```bash
# Backend
git clone https://github.com/repostea/server.git server
cd server

# Frontend
git clone https://github.com/repostea/client.git ../client
```

### Configure Backend

```bash
cd /var/www/repostea/server

# Install dependencies (production mode)
composer install --optimize-autoloader --no-dev

# Configure environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` with production values:

```env
APP_NAME="Your Site Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yoursite.com

FRONTEND_URL=https://yoursite.com
CLIENT_URL=https://yoursite.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=repostea
DB_USERNAME=repostea
DB_PASSWORD=your_secure_password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS="noreply@yoursite.com"
MAIL_FROM_NAME="${APP_NAME}"
```

```bash
# Run migrations
php artisan migrate --force

# Create storage symlink
php artisan storage:link

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Configure Frontend

```bash
cd /var/www/repostea/client

# Install dependencies
pnpm install

# Configure environment
cp .env.example .env
```

Edit `.env`:

```env
NODE_ENV=production

NUXT_PUBLIC_API_BASE=https://api.yoursite.com/api
NUXT_PUBLIC_SERVER_URL=https://api.yoursite.com
NUXT_PUBLIC_SITE_URL=https://yoursite.com
NUXT_PUBLIC_CLIENT_URL=https://yoursite.com

NUXT_PUBLIC_APP_NAME="Your Site Name"
NUXT_PUBLIC_COOKIE_DOMAIN=.yoursite.com
```

```bash
# Build for production
pnpm build
```

---

## Step 4: Nginx Configuration

### API (Backend)

Create `/etc/nginx/sites-available/api.yoursite.com`:

```nginx
server {
    listen 80;
    server_name api.yoursite.com;
    root /var/www/repostea/server/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase upload size limit
    client_max_body_size 100M;
}
```

### Frontend

Create `/etc/nginx/sites-available/yoursite.com`:

```nginx
server {
    listen 80;
    server_name yoursite.com www.yoursite.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

### Enable sites

```bash
sudo ln -s /etc/nginx/sites-available/api.yoursite.com /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/yoursite.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Step 5: SSL Certificates

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Get certificates
sudo certbot --nginx -d api.yoursite.com
sudo certbot --nginx -d yoursite.com -d www.yoursite.com

# Auto-renewal test
sudo certbot renew --dry-run
```

---

## Step 6: Process Management

### Start Frontend with PM2

```bash
cd /var/www/repostea/client
pm2 start ecosystem.config.cjs --env production

# Or manually
pm2 start npm --name "repostea-client" -- run start
```

### Start Queue Worker

```bash
cd /var/www/repostea/server
pm2 start php --name "repostea-queue" -- artisan queue:work --tries=3 --timeout=90
```

### Start WebSocket Server (optional)

```bash
pm2 start php --name "repostea-reverb" -- artisan reverb:start
```

### Save PM2 configuration

```bash
pm2 save
pm2 startup
```

---

## Step 7: Cron Jobs (Scheduled Tasks)

Add to crontab (`crontab -e`):

```cron
* * * * * cd /var/www/repostea/server && php artisan schedule:run >> /dev/null 2>&1
```

---

## Step 8: Final Verification

```bash
# Check services
sudo systemctl status nginx
sudo systemctl status mysql
sudo systemctl status redis
pm2 status

# Check logs
pm2 logs
tail -f /var/www/repostea/server/storage/logs/laravel.log

# Test endpoints
curl -I https://api.yoursite.com/api/v1/health
curl -I https://yoursite.com
```

---

## Updating

```bash
cd /var/www/repostea/server

# Pull latest changes
git pull origin main

# Update dependencies
composer install --optimize-autoloader --no-dev

# Run new migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue worker
pm2 restart repostea-queue
```

```bash
cd /var/www/repostea/client

# Pull latest changes
git pull origin main

# Update dependencies
pnpm install

# Rebuild
pnpm build

# Restart
pm2 restart repostea-client
```

---

## Troubleshooting

### Common Issues

**500 Error**: Check Laravel logs
```bash
tail -100 /var/www/repostea/server/storage/logs/laravel.log
```

**Permission denied**: Fix storage permissions
```bash
sudo chown -R www-data:www-data /var/www/repostea/server/storage
sudo chmod -R 775 /var/www/repostea/server/storage
```

**Connection refused**: Check if services are running
```bash
sudo systemctl status nginx php8.2-fpm mysql redis
pm2 status
```

**CORS errors**: Verify `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` in backend `.env`

---

## Security Recommendations

1. **Firewall**: Only allow ports 22, 80, 443
   ```bash
   sudo ufw allow 22/tcp
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   ```

2. **Fail2ban**: Protect against brute force
   ```bash
   sudo apt install fail2ban
   sudo systemctl enable fail2ban
   ```

3. **Regular updates**:
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

4. **Backup database** regularly:
   ```bash
   mysqldump -u repostea -p repostea > backup_$(date +%Y%m%d).sql
   ```

---

## Support

- **Issues**: [GitHub Issues](https://github.com/repostea/server/issues)
- **Discussions**: [GitHub Discussions](https://github.com/repostea/server/discussions)
