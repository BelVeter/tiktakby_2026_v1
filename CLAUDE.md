# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**TikTak.by** - Children's goods rental service (Minsk, Belarus)
- **Framework**: Laravel 8.83.29 with hybrid legacy PHP architecture
- **Database**: MySQL (73+ tables)
- **Frontend**: Laravel Mix (Sass + JS), Bootstrap
- **Hosting**: cPanel (Hoster.by)
- **Local**: Laragon on Windows at `http://localhost` (Apache required)

**CRITICAL**: This project has a dual architecture:
1. **Laravel app** (`app/`, `routes/`, `resources/`) - Public website
2. **Legacy admin panel** (`bb/`) - Standalone PHP admin interface

## Essential Commands

### Development
```bash
# Asset compilation (REQUIRED after CSS/JS changes)
npm run dev          # Development build
npm run watch        # Watch mode
npm run prod         # Production build (commit output!)

# Dependencies
composer install     # PHP packages
npm install          # Node packages

# Database
php artisan migrate           # Run migrations
php artisan db:seed          # Seed database

# Cache management
php artisan optimize:clear   # Clear all caches
php artisan config:cache     # Cache config
php artisan route:cache      # Cache routes
php artisan view:cache       # Cache views
```

### Testing
```bash
php artisan test                    # Run all tests
./vendor/bin/phpunit               # PHPUnit directly
./vendor/bin/phpunit tests/Unit    # Unit tests only
./vendor/bin/phpunit tests/Feature # Feature tests only
```

### Git Workflow
```bash
# Standard workflow
git checkout -b feature/my-feature
# ... make changes ...
git add .
git commit -m "description"
git push -u origin feature/my-feature
# Create PR to main via GitHub

# Deploy trigger (production)
# Visit: https://tiktak.by/Deploy.php?key=SECRET_KEY
```

## Critical Architecture Rules

### 1. NO Route Closures
**FORBIDDEN**: Closures in `routes/web.php` break `route:cache` on production.
```php
// ❌ WRONG
Route::get('/path', function() { ... });

// ✅ CORRECT
Route::get('/path', 'App\Http\Controllers\MyController@method');
```

### 2. NO `php artisan serve`
The project root must serve as document root (not `/public`). Use Laragon's Apache.
- **Correct URL**: `http://localhost`
- **Wrong URL**: `http://localhost:8000`

### 3. Frontend Build Process
Production server has NO npm. Build locally and commit output:
```bash
npm run prod
git add public/css/ public/js/ public/mix-manifest.json
git commit -m "Build frontend assets"
```

### 4. Database Access Patterns

**In Laravel code** (`app/`, `routes/`, `resources/`):
```php
use Illuminate\Support\Facades\DB;
use App\Models\User;

// Eloquent ORM
$user = User::find(1);

// Query Builder
$results = DB::table('clients')->where('id', $id)->first();
```

**In legacy admin code** (`bb/`):
```php
$mysqli = \bb\Db::getInstance()->getConnection();
$result = $mysqli->query("SELECT * FROM clients WHERE id = {$id}");
```

**NEVER** mix these approaches in the same file.

### 5. Authentication Check

Two separate session systems exist. To check admin status in Laravel views:

```php
// ✅ CORRECT (works in Laravel)
@if(isset($_COOKIE['tt_is_logged_in']))
    {{-- Admin content --}}
@endif

// ❌ WRONG (only works in bb/)
@if(\bb\models\User::isLoggedIn())
    {{-- Won't work in Laravel context --}}
@endif
```

## Project Structure

### Controllers (`app/Http/Controllers/`)
- `MainController` - Homepage
- `CatController` - Catalog pages (sections → subsections → categories)
- `L3Controller` - Individual product pages
- `CartController` - Shopping cart and checkout
- `ZvonokController` - Callbacks, bookings, subscriptions
- `SearchController` - Search and filters
- `RedirectController` - URL redirects (enables `route:cache`)
- `AboutController` - Static pages
- `FavoritesController` - Favorites functionality

### Business Logic (`app/MyClasses/`)
- `MainPage` - Homepage generation
- `CatMainPage` - Catalog page logic
- `L3Page` - Product page display
- `L2ModelWeb` - Product model web representation
- `KBForm`, `KBronLine` - Booking forms

### Admin Panel (`bb/`)
Standalone PHP application (not Laravel-based):
- `bb/index.php` - Admin dashboard
- `bb/Base.php` - Core admin functionality
- `bb/Db.php` - Database singleton wrapper
- `bb/classes/` - Business logic classes (Deal, Client, Category, etc.)
- `bb/models/` - Data models (User, Office, Kassa, etc.)

### Routes (`routes/web.php`)
URL structure: `/{lang}/{razdel}/{subrazdel}/{category}/{model}`
- Only `/ru/` is active; `/en/` and `/lt/` redirect to `/ru/`
- All routes use controllers (required for route caching)

### Database Tables (Key Groups)
- **Catalog**: `razdel`, `sub_razdel`, `tovar_cats`, `tovar_list`, `tovar_properties`
- **Rental**: `rent_deals_act`, `rent_orders`, `rent_model_web`, `rent_tarif_act`
- **Clients**: `clients`, `clients_arch`, `logpass`
- **Redirects**: `redirects` (SEO redirect management)
- **Content**: `pages`, `video_links`, `dop_photos`

## Deployment Process

`Deploy.php` automates production deployment:
1. `git fetch` + `git reset --hard origin/main`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. Cache clearing + rebuilding (config, routes, views)

**NOTE**: `git clean` is DISABLED to preserve user-uploaded images in `/bb/`.

## Common Pitfalls

1. **Modifying `bb/` files**: Use `\bb\Db` for database access, not Eloquent
2. **Adding routes**: Always use controller methods, never closures
3. **Frontend changes**: Run `npm run prod` and commit output before deploying
4. **Testing locally**: Use Laragon's Apache, not `artisan serve`
5. **Cache issues**: Run `php artisan optimize:clear` after config/route changes
6. **Session confusion**: Laravel sessions ≠ legacy PHP sessions in `bb/`

## Additional Documentation

For deeper architectural details, see `AGENTS.md`:
- Detailed controller responsibilities
- Middleware explanation
- Complete database schema
- Admin panel file structure
- Security and authentication details
- Project history and specifics
