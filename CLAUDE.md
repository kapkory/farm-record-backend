# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel 12 API backend for Farmconsul, a farm management system (farms, fields, plantings, livestock, breeding, treatments, productions, tasks, double-entry ledger). Serves a Nuxt 4 SPA via Sanctum cookie auth. This repo is also cloned inside the `farm-app` deployment umbrella repo, which owns the Docker/Caddy setup — see `../CLAUDE.md` and `../DEPLOYMENT.md` when working from there.

`docs/` contains maintained documentation: architecture, API reference, DB schema, module plans.

## Commands

```bash
composer dev       # serve + queue:listen + pail (logs) + vite, concurrently
composer test      # config:clear + php artisan test (Pest)
composer setup     # first-time: install, .env, key, migrate, npm build

# Single test file / single test
php artisan test tests/Feature/Farms/StoreProductionTest.php
php artisan test --filter="stores a production"

vendor/bin/pint    # code formatting (Laravel Pint)
```

Tests are **Pest** (`it(...)` style), run against in-memory SQLite (see `phpunit.xml`). `RefreshDatabase` is NOT applied globally — add `uses(RefreshDatabase::class);` per test file. Authenticate with `$this->actingAs($user, 'sanctum')`. Existing feature tests build the full ownership chain manually (User → FarmerUser → Farmer → Farm) since only `User` has a factory.

## Architecture

### Route auto-loading (the most important non-obvious thing)

`routes/api.php` scans every file in subdirectories of `routes/` and registers each `*.route.php` file as a route group, **deriving both the URL prefix and controller namespace from the file path**, all behind `auth:sanctum`:

```
routes/v1/farms/farm/animals/breedings.route.php
  → URL prefix  /api/v1/farms/farm/animals/breedings
  → controllers App\Http\Controllers\Api\v1\Farms\Farm\Animals\*
```

If the filename matches its directory name (e.g. `farm/farm.route.php`), no extra segment is appended. To add endpoints: create/edit a `*.route.php` file in the right subdirectory — never register feature routes directly in `api.php`. Route files just bind paths to a `$controller` variable; controllers live in a mirrored tree under `app/Http/Controllers/Api/v1/`.

Auth endpoints (`/login`, `/register`, password reset, email verification) are Breeze-style controllers in `routes/auth.php` at the root path (no `/api` prefix).

### IDs: `id` vs `uuid`

Every Core model has both an auto-increment `id` (used for internal FKs) and a `uuid` (the only identifier exposed through the API). Controllers resolve inbound uuids to models (`where('uuid', ...)->firstOrFail()`) and store the numeric ids. **Uuids are not auto-generated** — code sets them explicitly with `(string) Str::orderedUuid()` on create.

### Multi-tenancy / ownership

Chain: `User` → `FarmerUser` (pivot with role) → `Farmer` → `Farm` → everything else. Controllers guard access with the `Farm::farmerOwned($userId)` scope; a failed check returns 404 (not 403). There is no global scope — each controller enforces this itself, so don't forget it in new endpoints.

### Response envelope

Controllers use the `ApiResponse` trait (`app/Traits/ApiResponse.php`): `{status, message, data}` on success, `{status, message, errors}` on error. Handlers wrap logic in try/catch and return `errorResponse(..., 500, ['exception' => ...])` on Throwable. Validation via Form Requests (`app/Http/Requests/`), output via Resources (`app/Http/Resources/`).

### Polymorphism via morphMap

`AppServiceProvider` registers a `Relation::morphMap` with short aliases (`planting`, `animal`, `animal_group`, `farm`, `treatment`). API payloads use these aliases (e.g. `productionable_type: 'planting'`); `Production`, `Treatment`, `Task`, and `LedgerTransaction` attach polymorphically to those models. New morph targets must be added to the map.

### Double-entry ledger

`app/Services/Ledger/LedgerTransactionService` is the only writer of financial data: it resolves the transactionable (via `TransactionableResolver`), guards ownership, and inside one DB transaction creates a `LedgerTransaction` plus two balanced `LedgerEntry` rows (primary + contra account, debit/credit decided by `LedgerPostingRuleResolver`). `TreatmentExpenseRecorder` and `ProductionExpenseRecorder` services post treatment/production costs through it. Don't write `LedgerEntry` rows directly. Seed accounts come from `LedgerAccountsSeeder`.

### Observers (side effects on create)

Registered in `AppServiceProvider`: `PlantingObserver` sets `expected_harvest_date` from the crop variety's `maturity_days` and auto-generates `Task`s from the planting's `Schedule`; `AnimalEventObserver` handles animal event side effects. They use `saveQuietly()` to avoid recursion — keep that pattern when editing them.

### Layout notes

- Domain models live in `app/Models/Core/` (only `User` is in `app/Models/`).
- `app/Repositories/` contains older generic helpers (`ModelSaverRepository`, `SearchRepo`, `FormRepository`, M-Pesa, LDAP…) predating the Services approach; new business logic goes in `app/Services/`.
- Sanctum SPA auth: sessions + cookies, not tokens. Cross-domain settings (`SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`) are critical in production; password-reset links point at `FRONTEND_URL`.
- Sentry is installed for error reporting.
