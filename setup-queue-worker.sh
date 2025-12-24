#!/bin/bash

# Setup Queue Worker with Supervisor
# This script configures supervisor to run Laravel queue workers automatically

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}Setting up Laravel Queue Worker...${NC}"
echo ""

# Check if supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${YELLOW}Supervisor is not installed. Installing...${NC}"
    sudo apt-get update
    sudo apt-get install -y supervisor
fi

# Copy supervisor configuration
echo -e "${BLUE}Copying supervisor configuration...${NC}"
sudo cp supervisor-repostea-worker.conf /etc/supervisor/conf.d/

# Reload supervisor to read new config
echo -e "${BLUE}Reloading supervisor configuration...${NC}"
sudo supervisorctl reread
sudo supervisorctl update

# Start the worker
echo -e "${BLUE}Starting queue worker...${NC}"
sudo supervisorctl start repostea-worker:*

# Check status
echo ""
echo -e "${GREEN}Queue worker setup complete!${NC}"
echo ""
echo -e "${BLUE}Status:${NC}"
sudo supervisorctl status repostea-worker:*

echo ""
echo -e "${BLUE}Useful commands:${NC}"
echo "  Check status:  sudo supervisorctl status repostea-worker:*"
echo "  Restart:       sudo supervisorctl restart repostea-worker:*"
echo "  Stop:          sudo supervisorctl stop repostea-worker:*"
echo "  Start:         sudo supervisorctl start repostea-worker:*"
echo "  View logs:     tail -f storage/logs/worker.log"
echo ""
