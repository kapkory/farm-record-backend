# Installation & Setup

## Requirements

- **PHP** >= 8.2
- **Composer** >= 2.x
- **MySQL** >= 8.0 or MariaDB >= 10.6
- **Node.js** >= 18.x
- **npm** >= 9.x

## Installation Steps

### 1. Clone & Install
```bash
git clone https://github.com/your-org/farm-app-backend.git
cd farm-app-backend
composer install
npm install
```

### 2. Configure Environment
```bash
cp .env.example .env
```

Edit `.env`:
```env
APP_NAME="Farm Management System"
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=farm_app
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
```

### 3. Setup Database
```bash
php artisan key:generate
php artisan migrate
php artisan db:seed  # Optional
```

### 4. Build & Run
```bash
npm run build
composer dev  # Starts server, queue, logs, vite
```

Or: `php artisan serve`

## Quick Setup
```bash
composer setup  # Runs all steps automatically
```

## Verify Installation
```bash
curl http://localhost:8000/up  # Should return 200 OK
```

## Common Issues

### CORS Errors
Ensure `.env` has:
```env
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
```

### CSRF Token Mismatch
1. Call `/sanctum/csrf-cookie` before login
2. Check `SESSION_DOMAIN` matches your domain
3. Verify `supports_credentials: true` in `config/cors.php`

## Development Commands

```bash
php artisan pail          # Real-time logs
php artisan migrate       # Run migrations
php artisan test          # Run tests
php artisan config:clear  # Clear caches
```

