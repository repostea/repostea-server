#!/bin/bash
set -e

# Wait for MySQL to be ready using PHP
echo "Waiting for MySQL..."
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    echo "MySQL not ready, waiting..."
    sleep 3
done
echo "MySQL is ready!"

# Update .env with Docker environment variables
echo "Updating .env with Docker config..."
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=${CACHE_STORE:-redis}|" .env
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${SESSION_DRIVER:-redis}|" .env
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}|" .env
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST:-redis}|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-mysql}|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-repostea}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-repostea}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-repostea123}|" .env

# Generate key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Remove schema dump to avoid SSL issues with mysql command
rm -f database/schema/*.sql

# Create media database if it doesn't exist
echo "Creating media database..."
php -r "
\$pdo = new PDO('mysql:host=' . getenv('DB_HOST'), 'root', 'root');
\$pdo->exec('CREATE DATABASE IF NOT EXISTS repostea_media');
\$pdo->exec(\"GRANT ALL PRIVILEGES ON repostea_media.* TO 'repostea'@'%'\");
"

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Create storage link if not exists
if [ ! -L "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Clear and cache config
php artisan config:clear
php artisan cache:clear

echo "Server ready!"

exec "$@"
