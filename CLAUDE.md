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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.
</laravel-boost-guidelines>
