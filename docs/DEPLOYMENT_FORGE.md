# Laravel Forge Deployment Guide

This guide covers deploying the KasamaHR application to production using Laravel Forge.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Provisioning](#server-provisioning)
3. [Database Setup](#database-setup)
4. [Site Creation](#site-creation)
5. [Environment Configuration](#environment-configuration)
6. [Deployment Script](#deployment-script)
7. [Queue Workers](#queue-workers)
8. [Laravel Reverb (WebSockets)](#laravel-reverb-websockets)
9. [SSL Certificate](#ssl-certificate)
10. [Scheduler](#scheduler)
11. [Post-Deployment Checklist](#post-deployment-checklist)
12. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before deploying, ensure you have:

- A Laravel Forge account ([forge.laravel.com](https://forge.laravel.com))
- A server provider account (DigitalOcean, AWS, Linode, Vultr, or Hetzner)
- A domain name with DNS access
- Git repository access (GitHub, GitLab, or Bitbucket)

### Recommended Server Specifications

| Environment | CPU | RAM | Storage |
|-------------|-----|-----|---------|
| Staging | 1 vCPU | 2 GB | 50 GB SSD |
| Production | 2+ vCPU | 4+ GB | 100+ GB SSD |

---

## Server Provisioning

### 1. Create a New Server

1. Log in to Laravel Forge
2. Click **Create Server**
3. Select your server provider
4. Configure the server:
   - **Name**: `kasamahr-production` (or your preferred name)
   - **Region**: Choose closest to your users
   - **Server Size**: See recommendations above
   - **PHP Version**: **8.4** (required)
   - **Database**: **MySQL 8.0** or **PostgreSQL 16**
   - **Post-Deployment**: Install Composer and npm

### 2. Server Features to Enable

After provisioning, configure these in **Server Settings**:

- [ ] Enable **OPcache** for PHP performance
- [ ] Configure **PHP memory_limit** to at least `256M`
- [ ] Set **PHP upload_max_filesize** and **post_max_size** as needed
- [ ] Enable **Redis** if using Redis for cache/sessions (optional)

---

## Database Setup

### 1. Create the Database

1. Go to **Server → Database**
2. Click **Create Database**
3. Enter database name: `kasamahr`
4. Create a database user with a strong password

### 2. Note Your Credentials

Save these for the environment configuration:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasamahr
DB_USERNAME=kasamahr_user
DB_PASSWORD=your_secure_password
```

---

## Site Creation

### 1. Create a New Site

1. Go to **Server → Sites**
2. Click **New Site**
3. Configure:
   - **Root Domain**: `kasamahr.com` (your domain)
   - **Project Type**: PHP / Laravel
   - **Web Directory**: `/public`
   - **PHP Version**: 8.4
   - **Create Database**: Skip (already created)

### 2. Install Repository

1. Click on your new site
2. Go to **Git Repository**
3. Configure:
   - **Repository**: `your-org/kasamahr`
   - **Branch**: `main` (or your production branch)
   - **Install Composer Dependencies**: Yes

### 3. Configure Nginx

If you need custom Nginx configuration (e.g., for file uploads), go to **Site → Nginx Configuration** and adjust as needed:

```nginx
# Example: Increase client max body size for file uploads
client_max_body_size 50M;
```

---

## Environment Configuration

### 1. Edit Environment Variables

Go to **Site → Environment** and configure:

```env
APP_NAME="KasamaHR"
APP_ENV=production
APP_KEY=base64:your_generated_key
APP_DEBUG=false
APP_URL=https://kasamahr.com
APP_MAIN_DOMAIN=kasamahr.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasamahr
DB_USERNAME=kasamahr_user
DB_PASSWORD=your_secure_password

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.kasamahr.com

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@kasamahr.com"
MAIL_FROM_NAME="${APP_NAME}"

# Laravel Reverb WebSocket Configuration
REVERB_APP_ID=kasamahr
REVERB_APP_KEY=your_reverb_key
REVERB_APP_SECRET=your_reverb_secret
REVERB_HOST=kasamahr.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# MQTT Configuration (if using biometric attendance)
MQTT_HOST=your-mqtt-broker.com
MQTT_PORT=8883
MQTT_CLIENT_ID=kasamahr-production
MQTT_AUTH_USERNAME=mqtt_user
MQTT_AUTH_PASSWORD=your_mqtt_password
MQTT_ENABLE_LOGGING=false
MQTT_AUTO_RECONNECT_ENABLED=true
```

### 2. Generate App Key

If you don't have an `APP_KEY`, generate one:

```bash
php artisan key:generate --show
```

Copy the output and paste it into your environment file.

---

## Deployment Script

### 1. Configure the Deploy Script

Go to **Site → Deployment** and update the deployment script:

```bash
cd /home/forge/kasamahr.com

# Pull latest changes
git pull origin $FORGE_SITE_BRANCH

# Install PHP dependencies
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install Node dependencies and build assets
npm ci
npm run build

# Clear and optimize Laravel
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache
$FORGE_PHP artisan icons:cache

# Run database migrations
$FORGE_PHP artisan migrate --force

# Generate Wayfinder routes
$FORGE_PHP artisan wayfinder:generate

# Restart queue workers to pick up new code
$FORGE_PHP artisan queue:restart

# Restart Reverb if running
( flock -w 10 9 || exit 1
    echo 'Restarting Reverb...'
    $FORGE_PHP artisan reverb:restart
) 9>/tmp/reverb_restart.lock
```

### 2. Enable Quick Deploy (Optional)

Enable **Quick Deploy** to automatically deploy when you push to your repository branch.

---

## Queue Workers

The application uses database queues. Configure a queue worker in Forge:

### 1. Create Queue Worker

1. Go to **Server → Queue**
2. Click **New Worker**
3. Configure:
   - **Connection**: `database`
   - **Queue**: `default`
   - **Maximum Tries**: `3`
   - **Maximum Jobs**: `1000`
   - **Maximum Time**: `60`
   - **Timeout**: `60`
   - **Processes**: `2` (adjust based on server capacity)

### 2. Worker Configuration

```
Connection: database
Queue: default
Timeout: 60
Sleep: 3
Tries: 3
Processes: 2
```

---

## Laravel Reverb (WebSockets)

The application uses Laravel Reverb for real-time features.

### 1. Create a Daemon

1. Go to **Server → Daemons**
2. Click **New Daemon**
3. Configure:
   - **Command**: `php artisan reverb:start --host=127.0.0.1 --port=8080`
   - **Directory**: `/home/forge/kasamahr.com`
   - **User**: `forge`
   - **Processes**: `1`

### 2. Configure Nginx for WebSockets

Add this to your site's Nginx configuration to proxy WebSocket connections:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}
```

Save and let Forge restart Nginx.

---

## SSL Certificate

### 1. Install Let's Encrypt Certificate

1. Go to **Site → SSL**
2. Click **Let's Encrypt**
3. Add your domain(s):
   - `kasamahr.com`
   - `www.kasamahr.com` (if applicable)
4. Click **Obtain Certificate**

### 2. Enable Auto-Renewal

Let's Encrypt certificates auto-renew by default in Forge.

---

## Scheduler

Laravel's task scheduler needs to run via cron.

### 1. Enable Scheduler

1. Go to **Server → Scheduler**
2. Click **Enable Scheduler** for your site

This adds the following cron entry:

```
* * * * * cd /home/forge/kasamahr.com && php artisan schedule:run >> /dev/null 2>&1
```

---

## Post-Deployment Checklist

After deploying, verify everything works:

### Application Health

- [ ] Visit `https://kasamahr.com` and verify the homepage loads
- [ ] Test user authentication (login/logout)
- [ ] Check browser console for JavaScript errors
- [ ] Verify API endpoints respond correctly

### Background Services

- [ ] Queue worker is processing jobs: `php artisan queue:work --once`
- [ ] Scheduler is running: Check **Server → Scheduler** for recent runs
- [ ] Reverb is accepting connections: Check WebSocket functionality

### Monitoring

- [ ] Set up **Server Monitoring** in Forge
- [ ] Configure **Notification Channels** (Slack, Email, Discord)
- [ ] Set up **Heartbeat Monitoring** for critical endpoints

### Security

- [ ] `APP_DEBUG` is set to `false`
- [ ] `APP_ENV` is set to `production`
- [ ] SSL certificate is installed and working
- [ ] Sensitive files are not publicly accessible

---

## Troubleshooting

### Common Issues

#### 1. 500 Server Error After Deployment

```bash
# SSH into your server and check logs
tail -f /home/forge/kasamahr.com/storage/logs/laravel.log

# Check Nginx error logs
tail -f /var/log/nginx/kasamahr.com-error.log

# Ensure correct permissions
cd /home/forge/kasamahr.com
chmod -R 775 storage bootstrap/cache
```

#### 2. Assets Not Loading (Vite Manifest Error)

```bash
# Rebuild assets
cd /home/forge/kasamahr.com
npm run build

# Clear view cache
php artisan view:clear
```

#### 3. Queue Jobs Not Processing

```bash
# Check worker status
php artisan queue:work --once

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

#### 4. WebSocket Connection Failed

1. Verify Reverb daemon is running in **Server → Daemons**
2. Check Nginx WebSocket proxy configuration
3. Ensure SSL certificate covers the domain
4. Verify environment variables for Reverb

```bash
# Test Reverb manually
php artisan reverb:start --host=127.0.0.1 --port=8080
```

#### 5. Permission Denied Errors

```bash
# Fix storage permissions
cd /home/forge/kasamahr.com
sudo chown -R forge:forge storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

#### 6. Database Connection Refused

1. Verify database credentials in `.env`
2. Check if the database exists
3. Ensure the database user has proper permissions

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Useful Forge SSH Commands

```bash
# SSH into server
ssh forge@your-server-ip

# Navigate to project
cd /home/forge/kasamahr.com

# View deployment log
cat /home/forge/.forge/deployment.log

# Manually run deployment script
./deploy.sh

# Clear all Laravel caches
php artisan optimize:clear
```

---

## Additional Resources

- [Laravel Forge Documentation](https://forge.laravel.com/docs)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [Laravel Reverb Documentation](https://laravel.com/docs/reverb)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)

---

## Environment-Specific Configurations

### Staging Environment

For a staging server, modify these values:

```env
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://staging.kasamahr.com
SESSION_DOMAIN=.staging.kasamahr.com
LOG_LEVEL=debug
```

### Production with Redis

If using Redis for improved performance:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Install Redis on your Forge server via **Server → PHP → Install Redis**.
