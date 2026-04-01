# Animal Management Module — Design & Implementation Plan

> **Version:** 1.2  
> **Date:** 2026-03-31  
> **Status:** Draft  
> **Audience:** Developer / Implementation Agent

---

## 1. Introduction

This document describes the database design and step-by-step implementation plan for adding animal/livestock management to the Farm Management System. The design mirrors the existing **crop workflow** (`crops → crop_varieties → plantings`) with an animal equivalent, and fully reuses the polymorphic tables already in the system (`treatments`, `productions`, `ledger_transactions`, `tasks`).

### 1.1 Design Principles

| Principle | How it applies |
|-----------|----------------|
| **Non-tech-savvy user** | Farmer thinks in groups ("my chicken flock", "dairy herd") not database tables. Individual animal tracking is opt-in only for high-value animals. |
| **Mirror crop workflow** | Crop Type → Crop Variety → Planting maps to Animal Type → Animal Breed → Animal Group. Same mental model, same UI patterns. |
| **Reuse, don't duplicate** | Treatments, productions, ledger transactions, tasks already support polymorphic relationships — animal groups simply become a new morph target. No `AnimalExpense` or `AnimalTreatment` tables. |
| **Laravel best practices** | UUID exposure, integer PKs, soft deletes, Form Requests, API Resources, Service classes, DTOs. |

### 1.2 How Groups, Individuals & Standalone Animals Work

| Farmer scenario | Animal Type | Tracking Mode | How it looks |
|----------------|------------|---------------|-------------|
| Dairy farmer with 5 named cows | Cattle | **Grouped + Individual** | Animal Group "Dairy Herd" (count=5) → 5 `Animal` records with tag_id, name, gender |
| Poultry farmer with 500 layers | Poultry | **Group only** | Animal Group "Layer House A" (count=500). No individual records. |
| Beekeeper with 3 hives | Bees | **Group only** | Animal Group "Apiary 1" (count=3). Each "unit" is a hive. |
| Goat farmer with 20 goats, 2 are breeding bucks | Goats | **Mixed** | Animal Group "Main Herd" (count=20) + 2 individual `Animal` records for the bucks |
| Farmer buys 1 dairy cow | Cattle | **Standalone** | `Animal` record "Bessie" directly under the farm. No group needed. |
| Farmer has a guard donkey | Donkeys | **Standalone** | `Animal` record "Jack" directly under the farm. No group needed. |
| Farmer has 3 unrelated animals | Mixed | **Standalone** | 3 separate `Animal` records under the farm — a dog, a cat, a donkey. |

#### Three paths for the farmer

1. **"Add a herd / flock"** → creates an `AnimalGroup` (with a count). Best for batch animals (poultry, bees, fish) or herds (cattle, goats).
2. **"Add an animal to a herd"** → creates an `Animal` linked to an existing `AnimalGroup`. Best for individual tracking within a group (the star cow in the herd).
3. **"Add an animal"** → creates a standalone `Animal` directly under the farm with no group. Best for single high-value animals, working animals, or miscellaneous animals that don't fit any flock.

The frontend shows two sections on the farm's livestock page:
- **My Herds & Flocks** — lists `animal_groups` with their counts
- **Individual Animals** — lists standalone `animals` (those with `animal_group_id = null`)

---

## 2. Database Design

### 2.1 New Tables Overview

```
                    SETTINGS (Reference Data)
                    ┌──────────────────┐
                    │   animal_types   │  e.g. Cattle, Poultry, Bees
                    └────────┬─────────┘
                             │ 1:N
                    ┌────────▼─────────┐
                    │  animal_breeds   │  e.g. Holstein, Kienyeji
                    └──────────────────┘

                    OPERATIONAL (Farm-specific)

      ┌─────┐       ┌──────────────────┐
      │     │──1:N──▶│  animal_groups   │  A herd / flock / colony on a farm
      │     │       └────────┬─────────┘
      │farms│                │ 1:N (optional)
      │     │       ┌────────▼─────────┐
      │     │──1:N──▶│     animals      │  ◄── can belong to a group OR directly to a farm
      └─────┘       └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
       ┌────────────┐ ┌───────────┐ ┌────────────┐
       │ treatments │ │productions│ │   tasks     │   ← existing polymorphic tables
       └────────────┘ └───────────┘ └────────────┘
                             ▲
                    ┌────────┘
              ┌─────┴──────────┐
              │animal_events   │  Lifecycle events (birth, death, sale…)
              └────────────────┘

       ┌─────────────────────┐
       │ ledger_transactions │  ← existing, morph to animal_group OR animal
       └─────────────────────┘

  ┌───────────────────────────────────────────────────────────────────────┐
  │  animals.animal_group_id is NULLABLE                                 │
  │                                                                       │
  │  • animal_group_id SET   → animal belongs to a herd/flock            │
  │  • animal_group_id NULL  → standalone animal, farm_id is required    │
  │                                                                       │
  │  farm_id on animals resolves to: group.farm_id (if grouped)          │
  │                              or: animals.farm_id (if standalone)      │
  └───────────────────────────────────────────────────────────────────────┘
```

### 2.2 Table: `animal_types` (Settings)

Equivalent of `crops`. Global reference data shared across all farmers.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | — | auto | PK |
| `uuid` | uuid | — | — | unique, API identifier |
| `name` | string(255) | — | — | "Cattle", "Poultry", "Bees" |
| `category` | enum | — | `livestock` | `livestock`, `poultry`, `apiculture`, `aquaculture` |
| `tracking_mode` | enum | — | `both` | `group_only`, `individual_only`, `both` — see explanation below |
| `count_label` | string(50) | — | `animals` | What one "unit" is called: `animals`, `birds`, `hives`, `ponds`, `heads` — used in UI ("12 birds", "3 hives") |
| `description` | text | ✓ | null | |
| `status` | tinyint unsigned | — | 1 | 0=inactive, 1=active |
| `deleted_at` | timestamp | ✓ | null | soft delete |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

#### `tracking_mode` explained

| Mode | Allows Group? | Allows Individual? | Use case |
|------|:------------:|:-----------------:|----------|
| `group_only` | ✓ | ✗ | Bees, fish, poultry — you manage by colony/pond/flock, never by single animal |
| `individual_only` | ✗ | ✓ | Horses, camels — always tracked one-by-one, no "herd" concept needed |
| `both` | ✓ | ✓ | Cattle, goats, sheep — can have a herd AND track named individuals within it or standalone |

**Enforcement rules (validated in backend):**
- If `tracking_mode = group_only` → the system **rejects** any attempt to create an individual `Animal` record for this type (grouped or standalone). The farmer only works with `AnimalGroup` counts.
- If `tracking_mode = individual_only` → the system **rejects** any attempt to create an `AnimalGroup` for this type. The farmer adds animals directly to the farm as standalone `Animal` records.
- If `tracking_mode = both` → no restrictions. Groups and individuals are both available.

### 2.3 Table: `animal_breeds` (Settings)

Equivalent of `crop_varieties`.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | — | auto | PK |
| `uuid` | uuid | — | — | unique |
| `animal_type_id` | bigint unsigned | — | — | FK → `animal_types.id` |
| `name` | string(255) | — | — | "Holstein", "Kienyeji", "Apis mellifera" |
| `purpose` | enum | — | `dual` | `meat`, `dairy`, `eggs`, `honey`, `wool`, `breeding`, `dual`, `other` |
| `average_lifespan_months` | int unsigned | ✓ | null | For reference |
| `gestation_days` | int unsigned | ✓ | null | For breeding tracking |
| `description` | text | ✓ | null | |
| `status` | tinyint unsigned | — | 0 | |
| `deleted_at` | timestamp | ✓ | null | soft delete |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

### 2.4 Table: `animal_groups` (Operational — Primary Entity)

Equivalent of `plantings`. This is what the farmer creates and interacts with daily.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | — | auto | PK |
| `uuid` | uuid | — | — | unique |
| `farm_id` | bigint unsigned | — | — | FK → `farms.id` |
| `field_id` | bigint unsigned | ✓ | null | FK → `fields.id` (pen, paddock, coop) |
| `animal_type_id` | bigint unsigned | — | — | FK → `animal_types.id` |
| `animal_breed_id` | bigint unsigned | ✓ | null | FK → `animal_breeds.id` |
| `name` | string(255) | — | — | Farmer-friendly name: "Dairy Herd A" |
| `initial_count` | int unsigned | — | 1 | How many animals when group created |
| `current_count` | int unsigned | — | 1 | Updated by events (births, deaths, sales) |
| `acquired_date` | date | — | — | When animals were acquired |
| `acquisition_type` | enum | — | `purchased` | `born`, `purchased`, `donated`, `transferred` |
| `purpose` | enum | — | `commercial` | `commercial`, `subsistence`, `mixed` |
| `description` | text | ✓ | null | |
| `user_id` | bigint unsigned | — | — | Creator |
| `status` | tinyint unsigned | — | 1 | 0=inactive, 1=active, 2=sold_all, 3=archived |
| `deleted_at` | timestamp | ✓ | null | soft delete |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

**Polymorphic morph map alias:** `animal_group`

### 2.5 Table: `animals` (Individual Tracking — Grouped or Standalone)

Used for high-value animals the farmer needs per-animal records for. An animal can either belong to a group (herd member) **or** exist standalone directly under a farm (single cow, guard donkey, etc.).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | — | auto | PK |
| `uuid` | uuid | — | — | unique |
| `farm_id` | bigint unsigned | — | — | FK → `farms.id`. **Always set** — denormalized for standalone animals, derived from group for grouped animals |
| `animal_group_id` | bigint unsigned | **✓** | null | FK → `animal_groups.id`. **Null = standalone animal** |
| `animal_type_id` | bigint unsigned | — | — | FK → `animal_types.id`. Required for standalone; for grouped animals, inherited from group but stored for query convenience |
| `animal_breed_id` | bigint unsigned | ✓ | null | FK → `animal_breeds.id` |
| `tag_id` | string(100) | ✓ | null | Physical ear tag / microchip number |
| `name` | string(255) | ✓ | null | "Bessie", "Bull #12" |
| `gender` | enum | — | `unknown` | `male`, `female`, `unknown` |
| `date_of_birth` | date | ✓ | null | |
| `acquisition_date` | date | ✓ | null | Date animal was added to herd / farm |
| `acquisition_type` | enum | — | `born` | `born`, `purchased`, `donated`, `transferred` |
| `weight` | decimal(8,2) | ✓ | null | Last recorded weight |
| `weight_unit` | string(10) | ✓ | `kg` | kg, lbs |
| `status` | enum | — | `active` | `active`, `sold`, `deceased`, `transferred` |
| `notes` | text | ✓ | null | |
| `user_id` | bigint unsigned | — | — | Creator |
| `deleted_at` | timestamp | ✓ | null | soft delete |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

**Unique constraint:** `(animal_group_id, tag_id)` where `animal_group_id IS NOT NULL` — no duplicate tags within a group.  
**Additional unique:** `(farm_id, tag_id)` where `animal_group_id IS NULL` — no duplicate tags among standalone animals on the same farm.

**Key design rule:**  
- `animal_group_id` **set** → animal is a member of a herd/flock. `farm_id` mirrors the group's `farm_id`.  
- `animal_group_id` **null** → standalone animal. `farm_id` points directly to the farm. `animal_type_id` is required.

### 2.6 Table: `animal_events` (Lifecycle Tracking)

Records significant events. Uses polymorphic relationship so events can target an `AnimalGroup` (batch event) or an individual `Animal`.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | — | auto | PK |
| `uuid` | uuid | — | — | unique |
| `eventable_type` | string | — | — | Morph type (AnimalGroup or Animal) |
| `eventable_id` | bigint unsigned | — | — | Morph id |
| `event_type` | enum | — | — | `birth`, `death`, `sale`, `purchase`, `weight_check`, `movement`, `other` |
| `date` | date | — | — | When event occurred |
| `quantity` | int unsigned | ✓ | null | Number of animals affected (births: 3 calves, deaths: 2 chickens) |
| `description` | text | ✓ | null | Free-text notes |
| `metadata` | json | ✓ | null | Flexible data: `{"buyer": "John", "price_per_unit": 25000}` |
| `user_id` | bigint unsigned | — | — | Who recorded it |
| `deleted_at` | timestamp | ✓ | null | soft delete |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

**Index:** `(eventable_type, eventable_id)`

### 2.7 Existing Tables — How They Integrate

No changes to the table structure. Only the application code needs updating to accept new morph types.

| Table | Morph Column | Current Values | New Values Added |
|-------|-------------|----------------|-----------------|
| `treatments` | `treatmentable_type/id` | `App\Models\Core\Planting` | `App\Models\Core\AnimalGroup`, `App\Models\Core\Animal` |
| `productions` | `productionable_type/id` | `App\Models\Core\Planting` | `App\Models\Core\AnimalGroup` (milk, eggs, honey, wool) |
| `ledger_transactions` | `transactionable_type/id` | `App\Models\Core\Planting` | `App\Models\Core\AnimalGroup`, `App\Models\Core\Animal` (standalone) |
| `tasks` | `taskable_type/id` | `Planting`, `Farm`, `Treatment` | `App\Models\Core\AnimalGroup`, `App\Models\Core\Animal` |

### 2.8 Ledger Accounts — Already Seeded

The following accounts from `LedgerAccountsSeeder` already support animal operations:

| Code | Name | Use for animals |
|------|------|----------------|
| 1400 | Livestock | Asset value of animal herds |
| 4200 | Livestock Sales | Revenue from selling animals / animal products |
| 5500 | Veterinary | Vet bills, vaccinations |
| 5600 | Feed | Animal feed costs |

**New accounts to seed (optional):**

| Code | Name | Type | Notes |
|------|------|------|-------|
| 4250 | Milk Sales | revenue | If farmer wants granular revenue tracking |
| 4260 | Egg Sales | revenue | |
| 4270 | Honey Sales | revenue | |
| 5650 | Breeding Costs | expense | AI, stud fees |

---

## 3. Model Design

### 3.1 `AnimalType` (Models/Core/AnimalType.php)

```
- fillable: uuid, name, category, tracking_mode, count_label, description, status
- uses: SoftDeletes
- constants:
    TRACKING_GROUP_ONLY      = 'group_only'
    TRACKING_INDIVIDUAL_ONLY = 'individual_only'
    TRACKING_BOTH            = 'both'
- relationships:
    - hasMany → AnimalBreed
- helper methods:
    - allowsGroups(): bool    → tracking_mode !== 'individual_only'
    - allowsIndividuals(): bool → tracking_mode !== 'group_only'
    - isGroupOnly(): bool     → tracking_mode === 'group_only'
```

### 3.2 `AnimalBreed` (Models/Core/AnimalBreed.php)

```
- fillable: uuid, animal_type_id, name, purpose, average_lifespan_months,
            gestation_days, description, status
- uses: SoftDeletes
- relationships:
    - belongsTo → AnimalType
```

### 3.3 `AnimalGroup` (Models/Core/AnimalGroup.php)

```
- fillable: uuid, farm_id, field_id, animal_type_id, animal_breed_id, name,
            initial_count, current_count, acquired_date, acquisition_type,
            purpose, description, user_id, status
- uses: SoftDeletes
- casts: acquired_date → date, current_count → integer
- relationships:
    - belongsTo → Farm, AnimalType, AnimalBreed, Field
    - hasMany → Animal
    - morphMany → Treatment (treatmentable)
    - morphMany → Production (productionable)
    - morphMany → LedgerTransaction (transactionable)
    - morphMany → Task (taskable)
    - morphMany → AnimalEvent (eventable)
```

### 3.4 `Animal` (Models/Core/Animal.php)

```
- fillable: uuid, farm_id, animal_group_id, animal_type_id, animal_breed_id,
            tag_id, name, gender, date_of_birth, acquisition_date,
            acquisition_type, weight, weight_unit, status, notes, user_id
- uses: SoftDeletes
- casts: date_of_birth → date, acquisition_date → date, weight → decimal:2
- relationships:
    - belongsTo → Farm (always set)
    - belongsTo → AnimalGroup (nullable — null for standalone animals)
    - belongsTo → AnimalType
    - belongsTo → AnimalBreed (nullable)
    - morphMany → Treatment (treatmentable)
    - morphMany → Task (taskable)
    - morphMany → AnimalEvent (eventable)
    - morphMany → LedgerTransaction (transactionable) — for standalone animals
    - morphMany → Production (productionable) — for standalone animals (e.g. single dairy cow's milk)
- scopes:
    - scopeStandalone($query) → where('animal_group_id', null) — ungrouped animals
    - scopeGrouped($query) → whereNotNull('animal_group_id')
    - scopeForFarm($query, $farmId) → where('farm_id', $farmId)
- accessor:
    - getIsStandaloneAttribute() → return $this->animal_group_id === null
```

### 3.5 `AnimalEvent` (Models/Core/AnimalEvent.php)

```
- fillable: uuid, eventable_type, eventable_id, event_type, date, quantity,
            description, metadata, user_id
- uses: SoftDeletes
- casts: date → date, metadata → array
- relationships:
    - morphTo → eventable (AnimalGroup or Animal)
```

### 3.6 `Farm` Model Update

Add relationships:

```php
public function animalGroups()
{
    return $this->hasMany(AnimalGroup::class);
}

// All animals on the farm (grouped + standalone)
public function animals()
{
    return $this->hasMany(Animal::class);
}

// Only standalone animals (not in any group)
public function standaloneAnimals()
{
    return $this->hasMany(Animal::class)->whereNull('animal_group_id');
}
```

---

## 4. Implementation Steps

### Step 1: Settings — Animal Types & Breeds

**Files to create:**

| Type | Path |
|------|------|
| Migration | `database/migrations/2026_03_31_000001_create_animal_types_table.php` |
| Migration | `database/migrations/2026_03_31_000002_create_animal_breeds_table.php` |
| Model | `app/Models/Core/AnimalType.php` |
| Model | `app/Models/Core/AnimalBreed.php` |
| Controller | `app/Http/Controllers/Api/v1/Settings/Animals/AnimalTypesController.php` |
| Controller | `app/Http/Controllers/Api/v1/Settings/Animals/AnimalBreedsController.php` |
| Routes | `routes/v1/settings/animals/animal-types.route.php` |
| Routes | `routes/v1/settings/animals/animal-breeds.route.php` |
| Seeder | `database/seeders/AnimalTypesSeeder.php` |

**Controller methods** (mirror `CropsController` / `VarietiesController`):
- `create($request, $uuid = null)` — store or update
- `listAnimalTypes()` — list all
- `delete($uuid)` — soft delete

**Default seed data:**

| Name | Category | Tracking Mode | Count Label |
|------|----------|---------------|-------------|
| Cattle | livestock | `both` | heads |
| Goats | livestock | `both` | animals |
| Sheep | livestock | `both` | animals |
| Pigs | livestock | `both` | animals |
| Poultry | poultry | `group_only` | birds |
| Bees | apiculture | `group_only` | hives |
| Rabbits | livestock | `group_only` | animals |
| Fish | aquaculture | `group_only` | ponds |
| Donkeys | livestock | `both` | animals |
| Horses | livestock | `individual_only` | animals |
| Camels | livestock | `individual_only` | animals |
| Dogs | livestock | `individual_only` | animals |
| Cats | livestock | `individual_only` | animals |

> **Note:** Farmers can override `tracking_mode` per-type from the settings page if they want more/less granularity. For example, a large poultry integrator might switch Poultry to `both` to tag and track breeding roosters.

### Step 2: Animal Groups — Core Operational Table

**Files to create:**

| Type | Path |
|------|------|
| Migration | `database/migrations/2026_03_31_000003_create_animal_groups_table.php` |
| Model | `app/Models/Core/AnimalGroup.php` |
| Controller | `app/Http/Controllers/Api/v1/Farms/Farm/AnimalGroupsController.php` |
| Resource | `app/Http/Resources/Farms/Farm/AnimalGroupResource.php` |
| Form Request | `app/Http/Requests/Farms/StoreAnimalGroupRequest.php` |
| Routes | `routes/v1/farms/farm/animal-groups.route.php` |

**Controller methods:**
- `storeAnimalGroup(StoreAnimalGroupRequest $request)` — create group
- `listAnimalGroups($farm_uuid)` — list groups for a farm
- `show($group_uuid)` — single group with counts & recent events
- `update(StoreAnimalGroupRequest $request, $group_uuid)` — update
- `destroy($group_uuid)` — soft delete

**Routes:**

```
POST   /v1/farms/farm/animal-groups/                  → storeAnimalGroup
GET    /v1/farms/farm/animal-groups/list/{farm_uuid?}  → listAnimalGroups
GET    /v1/farms/farm/animal-groups/{uuid}             → show
PUT    /v1/farms/farm/animal-groups/{uuid}             → update
DELETE /v1/farms/farm/animal-groups/{uuid}             → destroy
```

**Update `Farm` model:** Add `hasMany(AnimalGroup::class)` relationship.

**Update `FarmResource`:** Replace `'total_livestocks' => 0` with:

```php
'total_livestocks' => $this->whenLoaded('animalGroups',
    fn () => $this->animalGroups->sum('current_count'),
    $this->animal_groups_sum_current_count ?? 0
) + $this->whenLoaded('standaloneAnimals',
    fn () => $this->standaloneAnimals->where('status', 'active')->count(),
    0
),
```

**`tracking_mode` enforcement in `StoreAnimalGroupRequest`:**

```php
// In StoreAnimalGroupRequest::withValidator()
$validator->after(function ($validator) {
    $animalType = AnimalType::find($this->input('animal_type_id'));
    if ($animalType && !$animalType->allowsGroups()) {
        $validator->errors()->add('animal_type_id',
            "{$animalType->name} is set to individual-only tracking. Add animals directly instead of creating a group.");
    }
});
```

### Step 3: Individual Animals (Grouped or Standalone)

**Files to create:**

| Type | Path |
|------|------|
| Migration | `database/migrations/2026_03_31_000004_create_animals_table.php` |
| Model | `app/Models/Core/Animal.php` |
| Controller | `app/Http/Controllers/Api/v1/Farms/Farm/AnimalsController.php` |
| Resource | `app/Http/Resources/Farms/Farm/AnimalResource.php` |
| Form Request | `app/Http/Requests/Farms/StoreAnimalRequest.php` |
| Routes | `routes/v1/farms/farm/animals.route.php` |

**Controller methods:**
- `store(StoreAnimalRequest $request)` — add animal to a group **or** as standalone under a farm
- `listByGroup($group_uuid)` — list animals in a specific group
- `listStandalone($farm_uuid)` — list standalone (ungrouped) animals for a farm
- `show($animal_uuid)` — single animal with treatment/event history
- `update($animal_uuid)` — update weight, status, notes
- `destroy($animal_uuid)` — soft delete (or mark as sold/deceased)

**Routes:**

```
POST   /v1/farms/farm/animals/                           → store
GET    /v1/farms/farm/animals/group/{group_uuid}          → listByGroup
GET    /v1/farms/farm/animals/standalone/{farm_uuid}      → listStandalone
GET    /v1/farms/farm/animals/{uuid}                      → show
PUT    /v1/farms/farm/animals/{uuid}                      → update
DELETE /v1/farms/farm/animals/{uuid}                      → destroy
```

**Validation logic in `StoreAnimalRequest`:**
- If `animal_group_uuid` is provided → animal belongs to the group; `farm_id` is auto-set from the group's `farm_id`.
- If `animal_group_uuid` is null → standalone animal; `farm_uuid` is **required** and `animal_type_id` is **required**.
- At least one of `animal_group_uuid` or `farm_uuid` must be provided.

**`tracking_mode` enforcement in `StoreAnimalRequest`:**

```php
// In StoreAnimalRequest::withValidator()
$validator->after(function ($validator) {
    // Resolve the animal type — from the group (if grouped) or from the request (if standalone)
    $animalType = null;
    if ($this->filled('animal_group_uuid')) {
        $group = AnimalGroup::where('uuid', $this->input('animal_group_uuid'))->first();
        $animalType = $group?->animalType;
    } else {
        $animalType = AnimalType::find($this->input('animal_type_id'));
    }

    if ($animalType && !$animalType->allowsIndividuals()) {
        $validator->errors()->add('animal_type_id',
            "{$animalType->name} is set to group-only tracking. You cannot add individual animal records — manage the herd count instead.");
    }
});
```

This means:
- **Bees** (`group_only`): Farmer can create "Apiary 1" group with count=3 hives. **Cannot** add individual bee/hive records. Treatments, productions, expenses all target the group.
- **Horses** (`individual_only`): Farmer **cannot** create a "Horse Herd" group. Must add each horse as a standalone `Animal` under the farm.
- **Cattle** (`both`): Farmer can create "Dairy Herd A" group AND add individual cow records inside it, OR add a standalone cow directly under the farm.

### Step 4: Animal Events

**Files to create:**

| Type | Path |
|------|------|
| Migration | `database/migrations/2026_03_31_000005_create_animal_events_table.php` |
| Model | `app/Models/Core/AnimalEvent.php` |
| Controller | `app/Http/Controllers/Api/v1/Farms/Farm/AnimalEventsController.php` |
| Resource | `app/Http/Resources/Farms/Farm/AnimalEventResource.php` |
| Form Request | `app/Http/Requests/Farms/StoreAnimalEventRequest.php` |
| Routes | `routes/v1/farms/farm/animal-events.route.php` |

**Controller methods:**
- `store(StoreAnimalEventRequest $request)` — record an event
- `listEvents($group_uuid_or_animal_uuid)` — list events, filtered by morph type
- `destroy($event_uuid)` — soft delete

**Auto-update `current_count`:** When storing a `birth` or `purchase` event, increment `animal_groups.current_count` by `quantity`. When storing a `death` or `sale` event, decrement. Use a Laravel Observer (`AnimalEventObserver`) or handle inline in the controller within a DB transaction.

**Sample event JSON from frontend:**

```json
{
  "eventable_type": "animal_group",
  "eventable_uuid": "uuid-of-herd",
  "event_type": "birth",
  "date": "2026-03-30",
  "quantity": 3,
  "description": "3 healthy calves born",
  "metadata": {
    "mother_tag": "COW-042",
    "health_status": "healthy"
  }
}
```

### Step 5: Wire Into Existing Polymorphic Systems

**5a. Register morph map** in `AppServiceProvider::boot()`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'planting'     => \App\Models\Core\Planting::class,
    'animal_group' => \App\Models\Core\AnimalGroup::class,
    'animal'       => \App\Models\Core\Animal::class,
    'farm'         => \App\Models\Core\Farm::class,
    'treatment'    => \App\Models\Core\Treatment::class,
]);
```

**5b. Update `TransactionableResolver`** (`app/Services/Ledger/Resolvers/TransactionableResolver.php`):

Add `'animal_group' => AnimalGroup::where('uuid', $uuid)->firstOrFail()` to the `match` statement.

**5c. Update `ProductionsController::resolveProductionable()`**:

Add `'animal_group' => AnimalGroup::where('uuid', $uuid)->firstOrFail()`.

**5d. Update `TasksController::resolveTaskable()`**:

Add `'animal_group'` and `'animal'` to the `match` statement.

**5e. Update `StoreTaskRequest` validation:**

Add `'animal_group','animal'` to the `taskable_type` in rule.

**5f. Update `StoreProductionRequest` validation:**

Add `'animal_group'` to the `productionable_type` in rule.

**5g. Update `TreatmentsController::storeTreatment()`:**

Support `model => 'animal_group'` in addition to `'planting'`. Resolve the morph target accordingly. Reuse the same `TreatmentExpenseRecorder` pattern — create `AnimalTreatmentExpenseRecorder` or generalize the existing recorder to accept any morphable with a `farm` relationship.

### Step 6: Seeders & Ledger Accounts

**Create `AnimalTypesSeeder`** with default types + common breeds.

**Update `LedgerAccountsSeeder`** to add optional granular revenue accounts (4250 Milk Sales, 4260 Egg Sales, etc.) under the Revenue parent.

**Register seeders in `DatabaseSeeder`.**

---

## 5. API Response Samples

### 5.1 Animal Group List Response

```json
{
  "status": "success",
  "message": "Animal groups retrieved successfully",
  "data": [
    {
      "uuid": "a1b2c3d4-...",
      "name": "Dairy Herd A",
      "animal_type": "Cattle",
      "animal_breed": "Holstein",
      "current_count": 12,
      "initial_count": 10,
      "acquired_date": "2026-01-15",
      "acquired_date_human": "2 months ago",
      "acquisition_type": "purchased",
      "purpose": "commercial",
      "field": "Paddock 1",
      "status": 1,
      "total_animals_tracked": 5,
      "recent_events_count": 3
    }
  ]
}
```

### 5.2 Individual Animal Response

**Grouped animal (belongs to a herd):**

```json
{
  "uuid": "e5f6g7h8-...",
  "tag_id": "COW-042",
  "name": "Bessie",
  "gender": "female",
  "date_of_birth": "2024-06-15",
  "age_human": "1 year ago",
  "weight": 450.00,
  "weight_unit": "kg",
  "status": "active",
  "is_standalone": false,
  "animal_type": "Cattle",
  "animal_breed": "Holstein",
  "animal_group": {
    "uuid": "a1b2c3d4-...",
    "name": "Dairy Herd A"
  },
  "farm": {
    "uuid": "f1a2r3m4-...",
    "name": "Green Valley Farm"
  }
}
```

**Standalone animal (no group):**

```json
{
  "uuid": "j3k4l5m6-...",
  "tag_id": "DONKEY-001",
  "name": "Jack",
  "gender": "male",
  "date_of_birth": "2022-01-10",
  "age_human": "4 years ago",
  "weight": 180.00,
  "weight_unit": "kg",
  "status": "active",
  "is_standalone": true,
  "animal_type": "Donkeys",
  "animal_breed": null,
  "animal_group": null,
  "farm": {
    "uuid": "f1a2r3m4-...",
    "name": "Green Valley Farm"
  }
}
```

### 5.3 Animal Event Response

```json
{
  "uuid": "i9j0k1l2-...",
  "event_type": "birth",
  "date": "2026-03-30",
  "date_human": "1 day ago",
  "quantity": 3,
  "description": "3 healthy calves born",
  "metadata": {
    "mother_tag": "COW-042",
    "health_status": "healthy"
  }
}
```

### 5.4 Store Animal Group — Frontend JSON

```json
{
  "farm_uuid": "uuid-of-farm",
  "field_uuid": null,
  "animal_type_id": 1,
  "animal_breed_id": 3,
  "name": "Dairy Herd A",
  "initial_count": 10,
  "acquired_date": "2026-01-15",
  "acquisition_type": "purchased",
  "purpose": "commercial",
  "description": "10 Holstein dairy cows from Naivasha"
}
```

### 5.5 Store Individual Animal — Frontend JSON

**Adding an animal to a group:**

```json
{
  "animal_group_uuid": "uuid-of-group",
  "tag_id": "COW-042",
  "name": "Bessie",
  "gender": "female",
  "date_of_birth": "2024-06-15",
  "acquisition_type": "purchased",
  "weight": 450,
  "weight_unit": "kg",
  "notes": "Pregnant, due April 2026"
}
```

**Adding a standalone animal (no group):**

```json
{
  "farm_uuid": "uuid-of-farm",
  "animal_type_id": 1,
  "animal_breed_id": 3,
  "tag_id": "DONKEY-001",
  "name": "Jack",
  "gender": "male",
  "date_of_birth": "2022-01-10",
  "acquisition_type": "purchased",
  "acquisition_date": "2025-06-01",
  "weight": 180,
  "weight_unit": "kg",
  "notes": "Guard donkey for the farm"
}
```

---

## 6. File Checklist

### Migrations (5 files)

- [ ] `2026_03_31_000001_create_animal_types_table.php`
- [ ] `2026_03_31_000002_create_animal_breeds_table.php`
- [ ] `2026_03_31_000003_create_animal_groups_table.php`
- [ ] `2026_03_31_000004_create_animals_table.php`
- [ ] `2026_03_31_000005_create_animal_events_table.php`

### Models (5 files)

- [ ] `app/Models/Core/AnimalType.php`
- [ ] `app/Models/Core/AnimalBreed.php`
- [ ] `app/Models/Core/AnimalGroup.php`
- [ ] `app/Models/Core/Animal.php`
- [ ] `app/Models/Core/AnimalEvent.php`

### Controllers (5 files)

- [ ] `app/Http/Controllers/Api/v1/Settings/Animals/AnimalTypesController.php`
- [ ] `app/Http/Controllers/Api/v1/Settings/Animals/AnimalBreedsController.php`
- [ ] `app/Http/Controllers/Api/v1/Farms/Farm/AnimalGroupsController.php`
- [ ] `app/Http/Controllers/Api/v1/Farms/Farm/AnimalsController.php`
- [ ] `app/Http/Controllers/Api/v1/Farms/Farm/AnimalEventsController.php`

### API Resources (5 files)

- [ ] `app/Http/Resources/Farms/Farm/AnimalGroupResource.php`
- [ ] `app/Http/Resources/Farms/Farm/AnimalResource.php`
- [ ] `app/Http/Resources/Farms/Farm/AnimalEventResource.php`
- [ ] `app/Http/Resources/Settings/AnimalTypeResource.php` (optional)
- [ ] `app/Http/Resources/Settings/AnimalBreedResource.php` (optional)

### Form Requests (4 files)

- [ ] `app/Http/Requests/Farms/StoreAnimalGroupRequest.php`
- [ ] `app/Http/Requests/Farms/StoreAnimalRequest.php`
- [ ] `app/Http/Requests/Farms/StoreAnimalEventRequest.php`
- [ ] `app/Http/Requests/Settings/StoreAnimalTypeRequest.php` (optional)

### Routes (5 files)

- [ ] `routes/v1/settings/animals/animal-types.route.php`
- [ ] `routes/v1/settings/animals/animal-breeds.route.php`
- [ ] `routes/v1/farms/farm/animal-groups.route.php`
- [ ] `routes/v1/farms/farm/animals.route.php`
- [ ] `routes/v1/farms/farm/animal-events.route.php`

### Seeders (1 file)

- [ ] `database/seeders/AnimalTypesSeeder.php`

### Existing Files to Update

- [ ] `app/Models/Core/Farm.php` — add `animalGroups()` relationship
- [ ] `app/Http/Resources/Farms/Farm/FarmResource.php` — replace `total_livestocks => 0`
- [ ] `app/Services/Ledger/Resolvers/TransactionableResolver.php` — add `animal_group`
- [ ] `app/Http/Controllers/Api/v1/Farms/Farm/ProductionsController.php` — add `animal_group` to morph resolver
- [ ] `app/Http/Controllers/Api/v1/Tasks/TasksController.php` — add `animal_group`, `animal` to morph resolver
- [ ] `app/Http/Requests/Tasks/StoreTaskRequest.php` — add to `taskable_type` validation
- [ ] `app/Http/Requests/Farms/StoreProductionRequest.php` — add to `productionable_type` validation
- [ ] `app/Http/Controllers/Api/v1/Farms/Farm/Crops/TreatmentsController.php` — generalize or add animal group support
- [ ] `app/Providers/AppServiceProvider.php` — register morph map
- [ ] `database/seeders/LedgerAccountsSeeder.php` — optional new revenue sub-accounts

---

## 7. Implementation Order

```
Phase 1 (Day 1-2): Settings
  → Migrations + Models + Controllers + Routes for animal_types & animal_breeds
  → Seeder for default types and breeds
  → Test via API (Postman / frontend settings tab)

Phase 2 (Day 2-3): Animal Groups
  → Migration + Model + Controller + Resource + Request + Routes
  → Update Farm model & FarmResource
  → Register morph map in AppServiceProvider
  → Test: create group, list groups for a farm

Phase 3 (Day 3-4): Individual Animals
  → Migration + Model + Controller + Resource + Routes
  → Test: add animals to a group, list, update weight/status

Phase 4 (Day 4-5): Animal Events + Auto Count
  → Migration + Model + Controller + Resource + Routes
  → Implement AnimalEventObserver for current_count sync
  → Test: record birth (count goes up), record death (count goes down)

Phase 5 (Day 5-6): Polymorphic Integration
  → Update TransactionableResolver, ProductionsController, TasksController
  → Update all validation rules to accept animal morph types
  → Update TreatmentsController to handle animal_group treatments
  → Test: create treatment for a herd, record milk production, create
    expense ledger entry for feed purchase against an animal group

Phase 6 (Day 6-7): Polish & Test
  → End-to-end testing of full animal lifecycle
  → Verify ledger P&L includes animal transactions
  → Verify tasks can be assigned to animal groups
  → Update documentation
```

---

## 8. Future Extensions (Out of Scope for Now)

| Feature | Notes |
|---------|-------|
| Breeding records | Track mating pairs, pregnancy status, expected birth dates. Could be a `breeding_records` table or an event type with metadata. |
| Feed schedule | Recurring feed plans with cost auto-calculation. Ties into tasks + ledger. |
| Milk / egg production charts | Aggregate `productions` by week/month for trend graphs. Frontend concern. |
| Animal movement | Track transfers between groups, farms, or fields. Use `movement` event type with metadata. |
| Health records timeline | Frontend view aggregating treatments + events for an individual animal. Data already exists. |
| QR/barcode tag scanning | Frontend feature to quickly look up an animal by scanning the tag_id. |



