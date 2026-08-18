#!/bin/bash
set -e

echo "🚀 Starting deployment..."

cd /var/www/kasamahr

# Pull latest changes
echo "📥 Pulling latest changes..."
git pull origin main

# Install/update PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install/update Node dependencies
echo "📦 Installing Node dependencies..."
npm ci

# Build frontend assets
echo "🔨 Building frontend assets..."
npm run build

# Run migrations
echo "🗃️ Running migrations..."
php artisan migrate --force

# Run tenant-schema migrations against all tenant databases
echo "🗃️ Running tenant migrations..."
php artisan tenant:migrate

# Clear and rebuild caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

echo "🔧 Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# Restart Reverb
echo "🔄 Restarting Reverb..."
sudo supervisorctl restart kasamahr-reverb

# Reload PHP-FPM
echo "🔄 Reloading PHP-FPM..."
sudo systemctl reload php8.4-fpm

echo "✅ Deployment completed successfully!"
