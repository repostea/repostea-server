#!/bin/bash

# 🚀 Renegados Server Deployment Script - STAGING
# Deploys the Laravel server to staging environment
#
# Usage:
#   ./deploy-staging.sh              # Deploy with confirmation
#   ./deploy-staging.sh --yes        # Deploy without confirmation
#   ./deploy-staging.sh --migrations # Run migrations after deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Parse arguments
SKIP_CONFIRMATION=false
RUN_MIGRATIONS=true
for arg in "$@"; do
    if [ "$arg" = "--yes" ] || [ "$arg" = "-y" ]; then
        SKIP_CONFIRMATION=true
    elif [ "$arg" = "--migrations" ] || [ "$arg" = "-m" ]; then
        RUN_MIGRATIONS=true
    fi
done

print_status "🚀 Renegados Server Deployment - STAGING"
echo ""

# Safety check: only run in staging directory
if [[ ! "$PWD" =~ "repostea-staging" ]]; then
    print_error "❌ This script must be run from /var/www/repostea-staging/server"
    print_error "   Current directory: $PWD"
    exit 1
fi

# Check if running as correct user
CURRENT_USER=$(whoami)
print_status "Running as user: $CURRENT_USER"

# Confirmation
if [ "$SKIP_CONFIRMATION" = false ]; then
    print_warning "This will deploy the latest changes to STAGING."
    read -p "Continue? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment cancelled."
        exit 0
    fi
fi

echo ""
print_status "========================================="
print_status "📥 PULLING LATEST CHANGES (SHALLOW)"
print_status "========================================="
echo ""

# Pull latest changes using shallow clone
CURRENT_BRANCH=$(git branch --show-current)
print_status "Current branch: $CURRENT_BRANCH"

if git fetch --depth=1 origin "$CURRENT_BRANCH"; then
    print_success "✅ Latest changes fetched successfully"
else
    print_error "Failed to fetch changes"
    exit 1
fi

if git reset --hard FETCH_HEAD; then
    print_success "✅ Repository updated to latest commit"
else
    print_error "Failed to update repository"
    exit 1
fi

# Clean up old history
git reflog expire --expire=all --all 2>/dev/null || true
git gc --prune=all 2>/dev/null || true

echo ""
print_status "========================================="
print_status "📦 INSTALLING DEPENDENCIES"
print_status "========================================="
echo ""

# Install Composer dependencies (no dev dependencies)
if composer install --no-dev --optimize-autoloader --no-interaction; then
    print_success "✅ Composer dependencies installed successfully"
else
    print_error "Failed to install Composer dependencies"
    exit 1
fi

echo ""
print_status "========================================="
print_status "🔨 BUILDING FRONTEND ASSETS"
print_status "========================================="
echo ""

# Install npm dependencies
if npm ci --silent; then
    print_success "✅ NPM dependencies installed successfully"
else
    print_warning "⚠️  Failed to install NPM dependencies, trying npm install..."
    if npm install --silent; then
        print_success "✅ NPM dependencies installed successfully"
    else
        print_error "Failed to install NPM dependencies"
        exit 1
    fi
fi

# Build assets
if npm run build; then
    print_success "✅ Frontend assets built successfully"
else
    print_error "Failed to build frontend assets"
    exit 1
fi

echo ""
print_status "========================================="
print_status "🗄️  MEDIA DATABASE SETUP"
print_status "========================================="
echo ""

# Check if media database configuration exists in .env
print_status "Checking media database configuration..."

# Get database credentials from .env
DB_HOST=$(grep '^DB_HOST=' .env | cut -d '=' -f2)
DB_PORT=$(grep '^DB_PORT=' .env | cut -d '=' -f2)
DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d '=' -f2)
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2 | tr -d '"')
DB_MEDIA_DATABASE=$(grep '^DB_MEDIA_DATABASE=' .env | cut -d '=' -f2)

# If DB_MEDIA_DATABASE is not set, add it with staging value
if [ -z "$DB_MEDIA_DATABASE" ]; then
    print_warning "DB_MEDIA_DATABASE not found in .env, adding staging value..."
    echo "" >> .env
    echo "# Media database (for images)" >> .env
    echo "DB_MEDIA_DATABASE=repostea_media_staging" >> .env
    DB_MEDIA_DATABASE="repostea_media_staging"
    print_success "✅ DB_MEDIA_DATABASE added to .env: repostea_media_staging"
fi

# Validate that we have all required connection details
if [ -z "$DB_HOST" ] || [ -z "$DB_USERNAME" ]; then
    print_error "Missing database connection details in .env"
    print_error "Please ensure DB_HOST, DB_USERNAME are set"
    exit 1
fi

print_success "✅ Database configuration validated"
print_status "  Host: $DB_HOST"
print_status "  Port: ${DB_PORT:-3306}"
print_status "  Username: $DB_USERNAME"
print_status "  Media Database: $DB_MEDIA_DATABASE"

# Create media database if it doesn't exist
print_status "Creating media database (if it doesn't exist)..."
if php artisan db:create-media --force; then
    print_success "✅ Media database ready"
else
    print_error "Failed to create media database"
    exit 1
fi

echo ""
print_status "========================================="
print_status "🔨 OPTIMIZING APPLICATION"
print_status "========================================="
echo ""

# Clear and cache config
if php artisan config:clear; then
    print_success "✅ Config cache cleared"
else
    print_warning "Failed to clear config cache"
fi

if php artisan config:cache; then
    print_success "✅ Config cached"
else
    print_warning "Failed to cache config"
fi

# Clear and cache routes
if php artisan route:clear; then
    print_success "✅ Route cache cleared"
else
    print_warning "Failed to clear route cache"
fi

if php artisan route:cache; then
    print_success "✅ Routes cached"
else
    print_warning "Failed to cache routes"
fi

# Clear and cache views
if php artisan view:clear; then
    print_success "✅ View cache cleared"
else
    print_warning "Failed to clear view cache"
fi

if php artisan view:cache; then
    print_success "✅ Views cached"
else
    print_warning "Failed to cache views"
fi

# Optimize autoloader
if php artisan optimize; then
    print_success "✅ Application optimized"
else
    print_warning "Failed to optimize application"
fi

# Run migrations if requested
if [ "$RUN_MIGRATIONS" = true ]; then
    # Check if there are pending migrations
    PENDING_MIGRATIONS=$(php artisan migrate:status --pending 2>/dev/null | grep -c "Pending" || echo "0")

    if [ "$PENDING_MIGRATIONS" -gt 0 ]; then
        echo ""
        print_status "========================================="
        print_status "💾 DATABASE BACKUP"
        print_status "========================================="
        echo ""
        print_status "Detected $PENDING_MIGRATIONS pending migration(s)"

        # Create backups directory if it doesn't exist
        mkdir -p storage/backups

        # Backup filename with timestamp
        BACKUP_FILE="storage/backups/backup_staging_$(date +%Y%m%d_%H%M%S).sql"

        # Get database credentials from .env
        DB_HOST=$(grep '^DB_HOST=' .env | cut -d '=' -f2)
        DB_PORT=$(grep '^DB_PORT=' .env | cut -d '=' -f2)
        DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d '=' -f2)
        DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d '=' -f2)
        DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2 | tr -d '"')
        DB_MEDIA_DATABASE=$(grep '^DB_MEDIA_DATABASE=' .env | cut -d '=' -f2)

        # Create backup of main database
        if command -v mysqldump &> /dev/null; then
            if mysqldump -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" > "${BACKUP_FILE}" 2>/dev/null; then
                # Compress backup
                gzip "${BACKUP_FILE}"
                BACKUP_FILE="${BACKUP_FILE}.gz"

                print_success "✅ Main database backup created: ${BACKUP_FILE}"
            else
                print_warning "⚠️  Could not create main database backup"
            fi

            # Create backup of media database if it exists
            if [ -n "$DB_MEDIA_DATABASE" ]; then
                MEDIA_BACKUP_FILE="storage/backups/backup_staging_media_$(date +%Y%m%d_%H%M%S).sql"
                if mysqldump -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_MEDIA_DATABASE}" > "${MEDIA_BACKUP_FILE}" 2>/dev/null; then
                    gzip "${MEDIA_BACKUP_FILE}"
                    MEDIA_BACKUP_FILE="${MEDIA_BACKUP_FILE}.gz"
                    print_success "✅ Media database backup created: ${MEDIA_BACKUP_FILE}"
                else
                    print_warning "⚠️  Could not create media database backup (may not exist yet)"
                fi
            fi

            # Clean old backups (keep last 10 of each type)
            ls -t storage/backups/backup_staging_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm
            ls -t storage/backups/backup_staging_media_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm
            print_success "✅ Old backups cleaned (last 10 of each type kept)"
        else
            print_warning "⚠️  mysqldump not available, skipping backup"
        fi
    else
        echo ""
        print_status "========================================="
        print_status "💾 DATABASE BACKUP"
        print_status "========================================="
        echo ""
        print_status "✅ No pending migrations - skipping backup"
    fi

    echo ""
    print_status "========================================="
    print_status "🗄️  RUNNING MIGRATIONS"
    print_status "========================================="
    echo ""

    if php artisan migrate --force; then
        print_success "✅ Migrations completed successfully"
    else
        print_error "Failed to run migrations"
        exit 1
    fi
fi

# Clear general cache
if php artisan cache:clear; then
    print_success "✅ Application cache cleared"
else
    print_warning "Failed to clear application cache"
fi

echo ""
print_status "========================================="
print_status "🔄 RESTARTING SERVICES"
print_status "========================================="
echo ""

# Restart queue workers and Reverb if supervisor is installed
if command -v supervisorctl &> /dev/null; then
    # Queue workers
    if supervisorctl status repostea-staging-worker:* &> /dev/null; then
        if supervisorctl restart repostea-staging-worker:*; then
            print_success "✅ Queue workers restarted"
        else
            print_warning "⚠️  Failed to restart queue workers"
        fi
    else
        print_warning "⚠️  Queue workers not configured in supervisor for staging"
    fi

    # Reverb WebSocket server
    if supervisorctl status repostea-staging-reverb &> /dev/null; then
        if supervisorctl restart repostea-staging-reverb; then
            print_success "✅ Reverb WebSocket server restarted"
        else
            print_warning "⚠️  Failed to restart Reverb"
        fi
    else
        print_warning "⚠️  Reverb not configured. Run: sudo ./setup-reverb.sh --staging"
    fi
else
    print_warning "⚠️  Supervisor not installed (optional for staging)"
fi

echo ""
print_status "========================================="
print_status "🔌 SYNCING PLUGINS"
print_status "========================================="
echo ""

# Sync plugins from local plugins directory if it exists
if [ -d "plugins" ]; then
    print_status "Found plugins directory, fixing permissions..."

    # Fix permissions: directories 755, files 644
    find plugins -type d -exec chmod 755 {} \;
    find plugins -type f -exec chmod 644 {} \;

    # Make PHP files executable for opcache
    find plugins -type f -name "*.php" -exec chmod 644 {} \;

    # Count plugins
    PLUGIN_COUNT=$(find plugins -maxdepth 1 -mindepth 1 -type d | wc -l)

    if [ "$PLUGIN_COUNT" -gt 0 ]; then
        print_success "✅ Plugins permissions fixed ($PLUGIN_COUNT plugin(s) found)"

        # List plugins
        for plugin_dir in plugins/*/; do
            if [ -d "$plugin_dir" ]; then
                plugin_name=$(basename "$plugin_dir")
                print_status "  - $plugin_name"
            fi
        done
    else
        print_status "No plugins installed"
    fi
else
    print_status "No plugins directory found (plugins are optional)"
fi

echo ""
print_status "========================================="
print_status "🔒 CHECKING PERMISSIONS"
print_status "========================================="
echo ""

# Check storage permissions
PERMISSION_ISSUES=false
if [ -d "storage" ]; then
    if ! [ -w "storage" ] || ! [ -w "storage/logs" ] 2>/dev/null; then
        print_warning "⚠️  Storage permissions may need fixing"
        PERMISSION_ISSUES=true
    else
        print_success "✅ Storage permissions OK"
    fi
fi

# Check bootstrap/cache permissions
if [ -d "bootstrap/cache" ]; then
    if ! [ -w "bootstrap/cache" ]; then
        print_warning "⚠️  Bootstrap cache permissions may need fixing"
        PERMISSION_ISSUES=true
    else
        print_success "✅ Bootstrap cache permissions OK"
    fi
fi

# Show fix commands if issues detected
if [ "$PERMISSION_ISSUES" = true ]; then
    echo ""
    print_warning "To fix permissions, run these commands:"
    echo "  sudo chown -R www-data:www-data storage bootstrap/cache"
    echo "  sudo chmod -R 775 storage bootstrap/cache"
    echo "  sudo chmod g+s storage bootstrap/cache"
fi

echo ""
print_status "========================================="
print_status "📋 DEPLOYMENT SUMMARY"
print_status "========================================="
echo ""

print_success "✨ Staging server deployment completed successfully!"
echo ""
echo "🌐 API URL: https://pre-api.renegados.es"
echo "🌐 Blade URL: https://pre-repostea.renegados.es"
echo ""

print_status "💡 Useful commands:"
echo "  Clear cache:         php artisan cache:clear"
echo "  Clear config:        php artisan config:clear"
echo "  Run migrations:      php artisan migrate --force"
echo "  View logs:           tail -f storage/logs/laravel.log"
echo "  Apache restart:      sudo systemctl restart apache2"
echo "  Sync from prod:      php artisan staging:sync-from-production"
echo ""

if [ "$RUN_MIGRATIONS" = false ]; then
    print_warning "Migrations were not run. Use --migrations flag to run them."
else
    print_success "✅ Migrations were executed successfully"
fi

exit 0
