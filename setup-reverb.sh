#!/bin/bash

# 🔌 Setup Reverb WebSocket Server
# Creates supervisor config for Laravel Reverb
#
# Usage:
#   ./setup-reverb.sh              # Production
#   ./setup-reverb.sh --staging    # Staging

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Parse arguments
IS_STAGING=false
if [ "$1" = "--staging" ]; then
    IS_STAGING=true
fi

# Set variables based on environment
if [ "$IS_STAGING" = true ]; then
    ENV_NAME="staging"
    APP_PATH="/var/www/repostea-staging/server"
    SUPERVISOR_NAME="repostea-staging-reverb"
    REVERB_PORT="8081"
    CONFIG_FILE="/etc/supervisor/conf.d/supervisor-repostea-staging-reverb.conf"
else
    ENV_NAME="production"
    APP_PATH="/var/www/repostea/server"
    SUPERVISOR_NAME="repostea-reverb"
    REVERB_PORT="8080"
    CONFIG_FILE="/etc/supervisor/conf.d/supervisor-repostea-reverb.conf"
fi

print_status "🔌 Setting up Reverb for ${ENV_NAME^^}"
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    print_error "Please run with sudo: sudo ./setup-reverb.sh"
    exit 1
fi

# Check if supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    print_status "Installing supervisor..."
    apt-get update && apt-get install -y supervisor
    systemctl enable supervisor
    systemctl start supervisor
fi

print_status "Creating supervisor config for Reverb..."

# Create supervisor config
cat > "$CONFIG_FILE" << EOF
[program:${SUPERVISOR_NAME}]
process_name=%(program_name)s
command=php ${APP_PATH}/artisan reverb:start --host=127.0.0.1 --port=${REVERB_PORT}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=${APP_PATH}/storage/logs/reverb.log
stopwaitsecs=10
EOF

print_success "✅ Supervisor config created: ${CONFIG_FILE}"

# Reload supervisor
print_status "Reloading supervisor..."
supervisorctl reread
supervisorctl update

# Start Reverb
print_status "Starting Reverb..."
supervisorctl start ${SUPERVISOR_NAME} || supervisorctl restart ${SUPERVISOR_NAME}

# Check status
sleep 2
if supervisorctl status ${SUPERVISOR_NAME} | grep -q "RUNNING"; then
    print_success "✅ Reverb is running on port ${REVERB_PORT}"
else
    print_error "❌ Reverb failed to start. Check logs:"
    echo "  tail -f ${APP_PATH}/storage/logs/reverb.log"
    exit 1
fi

echo ""
print_status "========================================="
print_status "📋 REVERB SETUP COMPLETE"
print_status "========================================="
echo ""
print_success "Environment: ${ENV_NAME^^}"
print_success "Port: ${REVERB_PORT}"
print_success "Log: ${APP_PATH}/storage/logs/reverb.log"
echo ""
print_status "💡 Useful commands:"
echo "  Status:   sudo supervisorctl status ${SUPERVISOR_NAME}"
echo "  Restart:  sudo supervisorctl restart ${SUPERVISOR_NAME}"
echo "  Logs:     tail -f ${APP_PATH}/storage/logs/reverb.log"
echo ""
print_warning "⚠️  Don't forget to configure Nginx proxy for WebSocket!"
echo ""
