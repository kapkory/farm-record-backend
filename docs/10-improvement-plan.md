# Improvement Plan

_Last reviewed: 2026-06-26 — based on a walkthrough of the actual codebase (not `schema.txt`, which is a stale early draft)._

A prioritized plan covering what exists, what is broken, how to improve the architecture, and what to build next.

---

## 1. What we actually have

A Laravel + Sanctum API, versioned under `/api/v1`, with a more mature domain than the older docs suggest:

- **Farms / Farmers / Personnel** — multi-tenant via the `farmers` ↔ `farmer_users` pivot
- **Crops module** — crops, varieties, treatment types, treatments, schedules + schedule activities
- **Plantings & Productions** — planting cycles and harvest/yield tracking
- **Animals module** — types, breeds, groups, individual animals, events, breedings (with gestation logic)
- **Double-entry ledger** — `ledger_accounts` / `ledger_transactions` / `ledger_entries`, powering P&L reporting
- A **Services layer** (`Ledger`, `Production`, `Treatment`), some **FormRequests**, **API Resources**, and **Pest** tests

The foundations are solid. The main problems are **consistency and security**, not missing building blocks.

---

## 2. 🔴 Broken / not working (fix first)

### 2.1 Broken object-level authorization (IDOR) — **critical**
The newer **Animals** controllers correctly gate every action with `Farm::farmerOwned(auth()->id())`. Several older controllers do **not**:

- `FarmController@show` → `Farm::where('uuid', $uuid)->firstOrFail()` with **no ownership check**
- `FieldsController` (`listFields`, `show`, `delete`, `toggleStatus`) → fetches by uuid, **no ownership check**
- `PlantingsController@storePlanting` → resolves farm by uuid, **no ownership check**

**Impact:** any authenticated user who knows or guesses a farm UUID can read, modify, or delete another farmer's data. This is the single most important issue to fix.

### 2.2 `Farm::user()` relationship is wrong
It declares `belongsTo(User::class)`, but the table's foreign key is `farmer_id` and farms belong to *farmers*, not users directly. Any code relying on `$farm->user` is silently broken.

### 2.3 Unguarded `->first()->id` chains
Patterns like `Farm::where('uuid', $x)->first()->id` and `Field::where('uuid', $x)->first()->id` throw a fatal **500** (instead of a clean 404) when the UUID does not exist. Several call sites also lack an `exists` validation rule.

### 2.4 Reflection-based route loader in `routes/api.php`
It scans the filesystem on every request using `Jenssegers\Agent` and dynamic `require` inside closures. Because of the closures + dynamic requires, **`php artisan route:cache` will not work** — a real production performance hit — and the Windows path-separator branching is fragile.

### 2.5 Documentation drift
`schema.txt` and `docs/06-database-schema.md` (empty) describe polymorphic `harvests` / `sales` / `consumptions` tables that the migrations replaced with the ledger + productions design. New contributors will be misled.

### 2.6 Thin test coverage
Mostly example tests plus a handful of feature tests; **no authorization tests** — exactly the area that is currently broken.

---

## 3. 🟡 How to improve (architecture & quality)

- **Centralize authorization.** Add a `FarmPolicy` + an `EnsureFarmAccess` middleware (or scoped route-model-binding) that resolves `{farm_uuid}` to the *owned* farm once and injects it. Replace every ad-hoc `Farm::where('uuid')` lookup. This eliminates the IDOR bug class permanently instead of patching it per controller.
- **Make routing cacheable.** Replace the auto-loader with explicit `require __DIR__.'/v1/...php'` includes so `route:cache` works in production.
- **Standardize the controller contract:** a FormRequest for every write, an API Resource for every read, a Service for business logic. The pieces already exist — apply them uniformly. The **Animals module is the reference template**; bring Fields and Plantings up to it.
- **Database hygiene:** unique indexes on every `uuid` column; soft deletes (`deleted_at`) on farms / plantings / animals; an activity/audit log (small-farm records get edited and history matters).
- **CI:** Pest + Larastan (static analysis) + Pint on every push.

---

## 4. 🟢 What to add (features, prioritized for small farmers)

| Priority | Feature | Why |
|----------|---------|-----|
| High | **Inputs / Inventory** (seeds, fertilizer, feed, medicine) with stock + cost | In the roadmap, not built; closes the cost side of P&L |
| High | **Dashboard / summary endpoints** (per farm: revenue, expenses, active plantings, livestock counts) | The first thing a farmer opens the app to see |
| High | **Tasks + reminders / notifications** (spraying, vaccination, harvest windows) | Drives daily engagement; schedule activities already exist to build on |
| Medium | **Harvest quality grading & sales records** tied to the ledger | Completes the productions → revenue loop |
| Medium | **Report export** (PDF / Excel) + more P&L cuts (by field, by animal group) | Only P&L-by-planting exists today |
| Medium | **SMS / USSD or offline-first sync** | Small farmers often have low connectivity / no smartphone |
| Lower | Budgets & variance, weather integration, cooperative / marketplace features | Roadmap phases 4–6 |

---

## 5. Suggested sequencing

1. **Week 1 — Security hardening:** `FarmPolicy` + `EnsureFarmAccess`, fix `Farm::user()`, guard the `->first()` chains, add authorization feature tests. _(Non-negotiable before more features.)_
2. **Week 1–2 — Routing + CI:** explicit route includes (enable `route:cache`), add Larastan / Pint / Pest CI, refresh `docs/06-database-schema.md` from the real migrations.
3. **Weeks 2–4 — Inputs / Inventory module** (mirroring the Animals module structure).
4. **Weeks 4–6 — Dashboard + Tasks / notifications.**
5. **Ongoing — Reports / export, sales, offline considerations.**

---

## 6. Immediate next step

Start with **§2.1 (the authorization fix)** — it is a live security hole. Concretely:

1. Create `FarmPolicy` and register it.
2. Add an `EnsureFarmAccess` middleware that resolves `{farm_uuid}` to an owned `Farm` and binds it to the request.
3. Retrofit `FarmController`, `FieldsController`, and `PlantingsController` to use it.
4. Add feature tests asserting a non-owner receives `403`/`404` on each farm-scoped endpoint.
