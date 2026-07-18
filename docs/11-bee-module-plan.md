# Bee Management Module — Design & Implementation Plan

> **Version:** 1.2
> **Date:** 2026-07-18
> **Status:** Implemented — backend B1–B4 (migrations, models, naming/harvest services, controllers, routes, reminder command, 28 Pest tests) and frontend B5 core (registry entries, `useHives`/`useBeeHarvests`, `/admin/bees` page). Remaining: B6 polish items.
> **Audience:** Developer / Implementation Agent
> **Related:** `09-animal-module-plan.md` (apiary-as-group precedent), root `plan.md` (roles: workers may record harvests)

---

## 1. Introduction

Beekeeping support: a farmer registers hives, the system names them automatically (`A…Z`, then `1A…1Z`, `2A…2Z`, or a custom letter prefix), harvests of honey **and by-products (beeswax, propolis, bee venom, royal jelly, pollen, comb honey)** are recorded against a *selection* of hives, and the system tells the farmer when each hive can be harvested again. Every hive belongs directly to a **farm** (`hives.farm_id`), so production rolls up per farm as well as per apiary and per hive.

### 1.1 Design principles

| Principle | How it applies |
|-----------|----------------|
| **Reuse, don't duplicate** | The apiary **is an `AnimalGroup`** (animal type "Bees") — exactly as `09-animal-module-plan.md` §1.2 anticipated. Harvests are `Production` rows, hive treatments are `Treatment` rows, harvest reminders are `Task` rows — all via the existing polymorphic tables. |
| **Hives are not animals** | A hive needs `code`, `hive_type`, occupancy, `last_harvested_at`, `next_harvest_due` — and none of `Animal`'s gender/DOB/breeding/gestation semantics. Forcing hives into `Animal` would pollute breeding and event logic. A small dedicated `hives` table (new morph target `hive`) is the one new domain table. |
| **Non-tech-savvy user** | Farmer never types a hive code — the system assigns it. Harvest flow is "tick the hives you harvested, enter the honey". Readiness is shown as "Ready" / "Ready in 23 days", not dates math. |
| **Existing conventions** | uuid + auto-increment id, `Str::orderedUuid()` set explicitly, soft deletes, `farmerOwned` scoping (404 on miss), `ApiResponse` envelope, Form Requests + Resources, business logic in `app/Services/`, route auto-loading under `routes/v1/farms/farm/bees/`. |

---

## 2. Data model

### 2.1 Reused as-is

- **Apiary** = `AnimalGroup` (`animal_type` "Bees", `field_id` for location, `current_count` = occupied hives). Created through the existing animal-group endpoints/UI.
- **Harvest record** = `Production` (`productionable` = hive, `name` = "Honey", `unit` = kg, `grade`, `trace_number` = harvest-session id shared by all hives ticked in one harvest).
- **Hive treatment / inspection notes** = `Treatment`; **harvest reminder** = `Task` (`taskable` = hive).

### 2.2 New table: `hives`

```php
Schema::create('hives', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('farm_id')->constrained();           // direct farm link — per-farm production reports
    $table->foreignId('farmer_id')->constrained();
    $table->foreignId('animal_group_id')->constrained();   // the apiary
    $table->unsignedInteger('sequence');                   // naming counter position, 1-based
    $table->string('code', 20);                            // generated: "A", "1F", "KB-3C"
    $table->string('name')->nullable();                    // optional friendly label, editable
    $table->string('hive_type', 40)->nullable();           // langstroth | kenya_top_bar | log | box
    $table->string('occupancy', 20)->default('occupied');  // occupied | empty | absconded | dead
    $table->date('installed_date')->nullable();
    $table->date('last_inspected_at')->nullable();
    $table->date('last_harvested_at')->nullable();
    $table->date('next_harvest_due')->nullable();          // stored, recomputed on each harvest
    $table->unsignedSmallInteger('harvest_interval_days')->nullable(); // per-hive override
    $table->foreignId('user_id')->constrained();           // creator
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // Unique per apiary, not per farm: two apiaries on one farm may both use
    // the default A…Z scheme, so farm-wide uniqueness would break the second "A".
    $table->unique(['animal_group_id', 'code']);
    $table->unique(['animal_group_id', 'sequence']);
    $table->index(['farm_id', 'next_harvest_due']);
});
```

### 2.3 New table: `apiary_profiles` (naming convention + harvest defaults)

One row per apiary; keeps bee-specific settings **off** the shared `animal_groups` table.

```php
Schema::create('apiary_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('animal_group_id')->unique()->constrained();
    $table->string('naming_prefix', 10)->nullable();       // custom letters, e.g. "KB"
    $table->string('naming_scheme', 20)->default('alpha'); // alpha (A..Z,1A..) | numeric (1,2,3..)
    $table->unsignedInteger('next_sequence')->default(1);  // allocation counter
    $table->unsignedSmallInteger('default_harvest_interval_days')->default(90);
    $table->timestamps();
});
```

### 2.4 morphMap addition (`AppServiceProvider`)

```php
'hive' => \App\Models\Core\Hive::class,
```

---

## 3. Automatic hive naming

### 3.1 The `alpha` scheme (default)

Sequence → code, where `cycle = intdiv(seq - 1, 26)` and `letter = chr(65 + (seq - 1) % 26)`:

| sequence | 1 | 2 | … | 26 | 27 | 28 | … | 52 | 53 |
|---|---|---|---|---|---|---|---|---|---|
| code | A | B | … | Z | 1A | 1B | … | 1Z | 2A |

With a custom prefix (`naming_prefix = "KB"`): `KB-A … KB-Z, KB-1A … KB-1Z, KB-2A …`
`numeric` scheme: `KB-1, KB-2, …` (or bare `1, 2, …`).

The user's "custom letters to start with" = `naming_prefix`. Optionally accept a *start letter* at apiary setup (e.g. start at `H`) by seeding `next_sequence = ord('H') - 64 = 8`; existing sequence math handles the rest.

### 3.2 `HiveNamingService` (`app/Services/Bees/`)

- `allocate(AnimalGroup $apiary): array{sequence, code}` — inside a DB transaction, `lockForUpdate()` the `apiary_profiles` row, read `next_sequence`, build the code, increment, return. Row lock prevents two concurrent creates producing the same code.
- `codeFor(int $sequence, ApiaryProfile $profile): string` — pure function, unit-testable.
- Changing the convention later applies to **new** hives only (existing codes are painted on physical hives — never rename them automatically). `code` is immutable after creation; `name` is the editable label.

### 3.3 Offline-first caveat (frontend)

Codes are **server-assigned** — an offline-created hive can't know the next sequence without conflicting. The frontend registry entry for `hives` works normally (client uuid, queued create), but the UI shows a "code pending" badge until the sync replay returns the assigned code. Document this in the composable; it's the one place the local-first illusion is deliberately broken.

---

## 4. Harvesting & "when can I harvest again?"

### 4.1 Bee products vocabulary

A harvest session records one or more **products**. Constant `BeeProduct::PRODUCTS` (config-style constant in `app/Services/Bees/`), each with a default unit; stored in `Production.name`/`unit` — no schema change:

| key | label | default unit |
|---|---|---|
| `honey` | Honey | kg |
| `comb_honey` | Comb honey | kg |
| `beeswax` | Beeswax | kg |
| `propolis` | Propolis | g |
| `royal_jelly` | Royal jelly | g |
| `pollen` | Pollen | g |
| `bee_venom` | Bee venom | g |

**Readiness rule:** only sessions containing **honey or comb honey** update `last_harvested_at` / `next_harvest_due`. A propolis-scrape or venom-collection session is recorded as production but does not reset the honey-harvest clock.

### 4.2 Recording a harvest (`HiveHarvestService`, `app/Services/Bees/`)

`POST /api/v1/farms/farm/bees/harvests`

```json
{
  "uuid": "client-generated-session-uuid",
  "date": "2026-07-18",
  "grade": "A",
  "notes": "...",
  "products": [
    {
      "product": "honey",
      "unit": "kg",
      "hives": [
        { "hive_uuid": "…", "quantity": 4.5 },
        { "hive_uuid": "…", "quantity": 3.0 }
      ]
    },
    { "product": "beeswax", "unit": "kg", "total_quantity": 1.2, "split": "even", "hive_uuids": ["…", "…"] }
  ]
}
```

Per product, quantities are either per-hive or `total_quantity` + `split: "even"` for farmers who only weighed the bucket — the service divides across the selected hives.

Inside one DB transaction the service:
1. Resolves hives by uuid **and** verifies each belongs to a `farmerOwned` farm (404 on any miss — matches existing convention).
2. Uses the request `uuid` as the harvest-session id: stored in `Production.trace_number` on every row, and checked first for **idempotent replay** (same pattern as `TasksController`'s client-uuid replay — critical because the offline queue may resend).
3. Creates one `Production` per hive **per product** (`productionable_type = 'hive'`, `name` = product key, `unit` = product unit).
4. If the session contains honey/comb honey: updates each of those hives with `last_harvested_at = date`, `next_harvest_due = date + (hive.harvest_interval_days ?? profile.default_harvest_interval_days)`.
5. Returns a session summary (totals per product, per-hive rows, each hive's new `next_harvest_due`).

Nothing selected = validation error; empty/absconded/dead hives are selectable but flagged with a warning in the response (farmer may have reused the box — don't block, inform).

### 4.3 Readiness

- `GET /api/v1/farms/farm/bees/hives/list?apiary={uuid}` returns per hive: `code`, `name`, `occupancy`, `last_harvested_at`, `next_harvest_due`, plus computed `harvest_status`: `ready` (due date ≤ today, or never harvested and installed > interval days ago), `waiting` (with `days_remaining`), `unknown` (no history, recently installed).
- **Reminder tasks:** a scheduled command `bees:flag-due-harvests` (daily, runs in the existing `farm-app-scheduler` container) creates a `Task` ("Harvest hive KB-3A", `taskable` = hive, due = `next_harvest_due`) when a hive comes due and no open task exists for it. Reuses the entire task/calendar UI and (post-roles) worker task assignment for free.
- Interval defaults to **90 days**, editable per apiary (`default_harvest_interval_days`) and per hive (override) — flow seasons differ by region; don't hardcode. A future refinement can suggest an interval from the hive's own harvest history (average gap); out of scope for v1.

---

## 5. API surface (route auto-loading)

New files under `routes/v1/farms/farm/bees/` → controllers in `app/Http/Controllers/Api/v1/Farms/Farm/Bees/`:

| Route file | Endpoints |
|---|---|
| `hives.route.php` | `POST /` create (server assigns code) · `GET /list` (filter by apiary, includes readiness) · `GET /{uuid}` detail (harvest history via productions, treatments, tasks) · `PUT /{uuid}` (name, hive_type, occupancy, interval override, notes — **not** code) · `DELETE /{uuid}` (soft) |
| `harvests.route.php` | `POST /` batch harvest (§4.2) · `GET /list` harvest sessions (group productions by `trace_number`) |
| `apiaries.route.php` | `POST /{uuid}/profile` create/update naming convention + interval · `GET /{uuid}/profile` |
| `reports.route.php` | `GET /production` — totals per product for a period, grouped by `farm` \| `apiary` \| `hive` (joins `productions` → `hives` on the morph, aggregates via `hives.farm_id` / `hives.animal_group_id`). Feeds the "production per farm" dashboard card. |

Apiary CRUD itself stays on the existing animal-group endpoints. Once root `plan.md` M3 (roles) lands: workers can record harvests and complete tasks; profile/naming changes are owner/manager.

---

## 6. Frontend plan

1. **Registry entries** (`app/utils/offline/registry.ts`): `hives` (parent-scoped by apiary uuid) and `bee_harvests`. The batch-harvest create replays safely thanks to the session-uuid idempotency (§4.1.2).
2. **Composable** `useHives.ts` / `useBeeHarvests.ts` mirroring `useAnimalBreedings.ts` — form state + labels only, persistence via `useOfflineEntity`.
3. **Pages** under `app/pages/admin/bees/`: apiary list → apiary detail (hive grid) → hive detail. Hive grid is the centerpiece: one card per hive showing the big code letter, occupancy icon, and a colored readiness chip (green "Ready", amber "Ready in 23 days", grey "Empty").
4. **Harvest flow** (non-tech-savvy critical path): "Record Harvest" button → tap hive cards to select (ready ones pre-highlighted) → one screen for quantities with **honey pre-selected**; by-products (wax, propolis, venom, …) added via an "Add another product" row so the common case stays one field (date defaults to today) → confirmation showing each hive's next harvest date. ≤3 taps before the quantity screen.
5. **Reference data:** apiary + hive lists via `useReferenceData` so the harvest form works offline.
6. Settings screen for the naming convention lives with the apiary (online-only is fine — matches the existing settings-screens convention).

---

## 7. Improvements to the existing model (required / recommended)

| # | Item | Why | Kind |
|---|---|---|---|
| 1 | **`productions.quantity` integer → `decimal(10,2)`** (migration + cast `'decimal:2'` in `Production`) | Honey is weighed in fractional kg (4.5 kg is unrecordable today). Also fixes milk in litres for the animal module. **Blocker for this module.** | Required |
| 2 | Add `'hive'` to the `Relation::morphMap` | New morph target; forgetting it breaks payload aliases silently. | Required |
| 3 | Index `productions.trace_number` | It becomes the harvest-session grouping key queried by `harvests/list`. | Required |
| 4 | Maintain `AnimalGroup.current_count` for apiaries via a `HiveObserver` (count of `occupancy = occupied`) | Keeps the existing group-count UI truthful without bee-specific hacks; follow the `saveQuietly()` pattern from existing observers. | Recommended |
| 5 | Consider denormalized `farm_id` on `productions` | Farm-level production reports currently need per-morph joins; hives make a third morph family. For v1 the bee report (§5) aggregates through `hives.farm_id`, which is why the direct hive→farm link is required; a later cross-module farm report would justify this denormalization. | Optional |
| 6 | `Production` validation should accept the shared `unit` vocabulary (kg default for honey) | Consistency across crop/animal/bee harvests. | Recommended |

---

## 8. Implementation phases

| Phase | Contents |
|---|---|
| **B1 — Schema & models** | Migrations (`hives`, `apiary_profiles`, production decimal + trace index), `Hive` + `ApiaryProfile` models, morphMap, relationships (`Hive` morphMany productions/treatments/tasks; `AnimalGroup::hives()`). |
| **B2 — Naming + hive CRUD** | `HiveNamingService`, hives controller + Form Requests + `HiveResource`, `HiveObserver` (count sync). Pest: sequence→code table test (A, Z, 1A, 2A boundaries; prefix; numeric), concurrent-allocation lock test, ownership 404 test. |
| **B3 — Harvesting & reports** | `HiveHarvestService` + `BeeProduct` vocabulary, harvests controller, readiness computation in `HiveResource`, production report endpoint (per farm/apiary/hive/product). Pest: batch creates N productions per product + updates due dates in one transaction; by-product-only session does **not** move `next_harvest_due`; replay idempotency; even split rounding; interval override precedence (hive > profile > 90); farm report totals. |
| **B4 — Reminders** | `bees:flag-due-harvests` command + scheduler registration, no-duplicate-open-task guard. |
| **B5 — Frontend** | Registry entries, composables, apiary/hive pages, harvest flow, readiness chips, "code pending" offline state. |
| **B6 — Polish** | Harvest-history-based interval suggestion, hive movement between apiaries (codes are unique per apiary, so a re-parent that would clash must be rejected or re-coded), Swahili strings once i18n (root `plan.md` M5) exists. |

Each backend phase lands with its Pest tests (`uses(RefreshDatabase::class)` per file, `actingAs($user, 'sanctum')`, ownership chain built User → FarmerUser → Farmer → Farm → AnimalGroup → Hive).

---

## 9. Resolved decisions

1. **Naming convention is per apiary** (default design confirmed) — Apiary 1 can use `A…`, Apiary 2 `KB-…`.
2. **Sequences/codes are never reused** after a hive is deleted or absconds (confirmed) — physical hive boxes keep their painted codes; reuse would invite field mix-ups.
3. **By-products ship in v1**: beeswax, propolis, bee venom, royal jelly, pollen, comb honey alongside honey (§4.1). Only honey/comb-honey sessions reset the harvest-readiness clock.
4. **Hives link directly to the farm** (`hives.farm_id`, §2.2), and the production report (§5) aggregates per farm as well as per apiary/hive.
