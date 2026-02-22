# Architecture
## High-Level Architecture
```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND (Nuxt.js)                       │
│                      http://localhost:3000                       │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ HTTP/HTTPS (JSON API)
                                │ Cookie-based Auth (Sanctum)
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     BACKEND (Laravel 12)                        │
│                     http://localhost:8000                        │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                      API Routes                           │  │
│  │  /api/v1/farms, /api/v1/settings/crops, etc.             │  │
│  └───────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                     Middleware                            │  │
│  │  • EnsureFrontendRequestsAreStateful (Sanctum)           │  │
│  │  • Throttle (Rate Limiting)                               │  │
│  │  • SubstituteBindings                                     │  │
│  └───────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                     Controllers                           │  │
│  │  • Auth Controllers (Login, Register, etc.)              │  │
│  │  • API v1 Controllers (Farms, Crops, etc.)               │  │
│  └───────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                  Models & Repositories                    │  │
│  │  • Eloquent Models with Scopes                           │  │
│  │  • Repository Classes for Complex Queries                │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        DATABASE (MySQL)                         │
│  users, farmers, farmer_users, farms, crops, crop_varieties    │
└─────────────────────────────────────────────────────────────────┘
```
## Directory Structure
```
farm-app-backend/
├── app/
│   ├── Console/Commands/          # Artisan commands
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/              # Authentication controllers
│   │   │   ├── Api/v1/            # Versioned API controllers
│   │   │   │   ├── Farms/         # Farm management
│   │   │   │   └── Settings/      # Settings (Crops, etc.)
│   │   ├── Middleware/            # Custom middleware
│   │   └── Requests/              # Form request validation
│   ├── Models/
│   │   ├── User.php               # User model
│   │   └── Core/                  # Domain models
│   │       ├── Farmer.php
│   │       ├── FarmerUser.php
│   │       ├── Farm.php
│   │       ├── Crop.php
│   │       └── CropVariety.php
│   ├── Providers/                 # Service providers
│   ├── Repositories/              # Data access layer
│   └── Traits/
│       └── ApiResponse.php        # Standardized API responses
├── bootstrap/
│   └── app.php                    # Application bootstrap & middleware
├── config/                        # Configuration files
├── database/
│   ├── migrations/                # Database migrations
│   ├── factories/                 # Model factories for testing
│   └── seeders/                   # Database seeders
├── docs/                          # Documentation (you are here)
├── routes/
│   ├── api.php                    # Main API routes
│   ├── auth.php                   # Authentication routes
│   ├── web.php                    # Web routes
│   └── v1/                        # Versioned route files
│       └── settings/
│           └── crops/
│               ├── crops.route.php
│               └── varieties.route.php
├── storage/                       # Logs, cache, uploads
└── tests/                         # Pest PHP tests
```
## Request Lifecycle
```
1. Request arrives at server
        │
        ▼
2. public/index.php (Laravel entry point)
        │
        ▼
3. Bootstrap application (bootstrap/app.php)
        │
        ▼
4. HTTP Kernel loads middleware stack
   • EnsureFrontendRequestsAreStateful
   • Throttle rate limiting
   • SubstituteBindings
        │
        ▼
5. Router matches route
        │
        ▼
6. Controller method executes
        │
        ▼
7. Response sent to client
```
## Data Flow: Multi-Tenancy
All data access is scoped through the authenticated user's associated farmers:
```php
// User → Farmers relationship
$user->farmers();  // Returns all farmers the user belongs to
// Farm scope (in Farm model)
#[Scope]
protected function farmerOwned(Builder $query, $userId): void {
    $query->whereIn('farmer_id', function ($query) use ($userId) {
        $query->select('farmer_id')
            ->from('farmer_users')
            ->where('user_id', $userId);
    });
}
// Usage in controller
Farm::farmerOwned(Auth::id())->get();
```
## API Response Format
All API responses follow a consistent structure using the `ApiResponse` trait:
### Success Response
```json
{
    "success": true,
    "message": "Farm created successfully",
    "data": {
        "uuid": "a10695dd-be01-42a8-a6b4-e3b175881ff1",
        "name": "Green Valley Farm",
        "location": "Nairobi, Kenya",
        "size": 50.00,
        "size_unit": "acres"
    }
}
```
### Error Response
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "name": ["The name field is required."]
    }
}
```
## Key Design Patterns
### 1. Repository Pattern
Complex queries are encapsulated in repository classes:
- `SearchRepo` - Handles search, filtering, pagination
- `ModelSaverRepository` - Generic model save operations
### 2. Trait-based Composition
- `ApiResponse` - Standardized JSON responses
- Scopes defined using PHP 8 Attributes
### 3. UUID Strategy
- Primary keys use auto-increment integers (performance)
- UUIDs stored in separate column (API exposure)
- All API endpoints accept/return UUIDs, never internal IDs
### 4. Route Organization
Routes are organized by version and domain:
```
routes/
├── api.php              # Main entry, includes v1 routes
├── auth.php             # Auth routes (login, register)
└── v1/
    └── settings/
        └── crops/
            ├── crops.route.php
            └── varieties.route.php
```
## Security Architecture
### Authentication Flow (Sanctum SPA)
```
1. Frontend calls GET /sanctum/csrf-cookie
   ← Sets XSRF-TOKEN cookie
        │
        ▼
2. Frontend calls POST /login with credentials
   → Sends X-XSRF-TOKEN header
   ← Session cookie set
        │
        ▼
3. Subsequent API calls include session cookie
   → Laravel authenticates via session
   ← Protected resources returned
```
### CORS Configuration
```php
// config/cors.php
'paths' => ['*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'supports_credentials' => true,
```
### Rate Limiting
```php
// bootstrap/app.php
$middleware->group('api', [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:60,1',  // 60 requests per minute
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
]);
```
