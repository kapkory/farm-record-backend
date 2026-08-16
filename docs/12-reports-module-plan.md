# Farmconsul Financial Reports Plan

> **Status (2026-08-09): R1 implemented** in `farm-app-backend` — `AccountBalanceQuery`, `ProfitAndLossService`, `GET /reports/profit-and-loss`, 7 passing Pest tests. Two deviations: scoping goes through **farm ids from `Farm::farmerOwned()`** rather than farmer ids (it applies farm pinning too, which the plan's farmer-id scoping would have missed), and there is **no `ProfitAndLossResource`** — the service already returns a JSON-ready array, so a pass-through resource would have been an empty abstraction.

**Goal:** A Reports section that answers three farmer questions from the existing double-entry ledger — *"Did I make money?"* (Profit & Loss), *"What is my farm worth?"* (Balance Sheet), and *"Where did my cash go?"* (Cash Flow Statement) — in plain language, for a chosen period, with no accounting jargon on screen.

Repos touched: `farm-app-backend/` (Laravel 12) and `frontend/` (Nuxt 4 offline-first PWA). Each is its own git repo — commit in the right one.

---

## 1. Current state assessment

### What already exists (build on it, don't replace it)

- **A real double-entry ledger.** `LedgerTransactionService` is the only writer: every transaction produces balanced debit/credit `LedgerEntry` rows. Corrections are reversals (`reverse()`), never deletes — so period totals are trustworthy.
- **A typed chart of accounts** (`LedgerAccountsSeeder`): every `LedgerAccount` has `type ∈ {asset, liability, equity, revenue, expense}` and a `slug`. Cash-like accounts are `1100 Cash`, `1150 Mobile Money`, `1200 Bank`. Loans (`2200`), Owner's Capital (`3100`) and Drawings (`3200`) exist for financing classification; Equipment/Livestock asset accounts exist for investing.
- **One report today:** `GET /profit-and-loss/plantings` (`ReportsController`) — per-planting revenue/expense sums. Good join pattern to reuse, but it is entity-scoped, not a period P&L.
- **Finance gating:** the `finances` middleware (`EnsureCanViewFinances`) blocks staff server-side; the frontend mirrors it with `can_view_finances` from `/api/user`.
- **Frontend machinery:** `/admin/**` SPA pages, `useReferenceData` TTL cache, finance screens (ledger accounts settings) already online-only by design.

### Gaps

| Gap | Effect on the farmer |
|---|---|
| No period P&L by account | "How much profit did I make in July?" has no answer — only per-planting figures |
| No balance sheet | No statement of assets vs debts vs owner's stake; loan applications need this |
| No cash flow statement | Profit ≠ cash (credit sales, loans, equipment purchases); farmer can't see why the wallet is empty despite "profit" |
| No year-end close / retained earnings | Any balance sheet must roll cumulative revenue − expenses into equity as "current earnings" or it won't balance |
| No report UI at all | Even the existing plantings P&L endpoint has no dedicated page |

### Data-model facts the report queries must respect

- **Account rows are shared:** system accounts have `farmer_id = null`, so balances must be scoped through `ledger_transactions.farm_id` / `farmer_id` — never through `ledger_accounts.farmer_id`.
- **Sign convention:** debit-normal types (asset, expense) balance = debits − credits; credit-normal (liability, equity, revenue) = credits − debits. Same rules as `LedgerPostingRuleResolver`.
- **Exclusions:** soft-deleted transactions (`ledger_transactions.deleted_at`) must be excluded; entries have no soft delete of their own.
- Accounts form a two-level tree (`parent_id`); reports group posting accounts under their parent headers (Assets → Cash, Bank, …).

---

## 2. Design decisions

1. **Server-computed, online-only.** Reports are read-only aggregates over the whole ledger — computing them client-side from IndexedDB would be wrong (partial sync) and heavy. They join the ledger-accounts settings screens in the "online-only by design" bucket. Offline, the page shows a friendly "connect to view reports" state.
2. **Money views — owner/manager only.** All three endpoints sit behind the `finances` middleware, same as `/transactions`.
3. **Direct-method cash flow.** No accruals engine exists and farmers think in cash moves anyway. Classify every entry that touches a cash account (`slug ∈ cash, mobile-money, bank`) by the *other* leg of its transaction (see §4). This is simpler and more explainable than the indirect method, and matches the data we have.
4. **Balance sheet balances via "current earnings".** With no closing process, equity = seeded equity accounts + (cumulative revenue − cumulative expenses to the as-of date). Shown as one plain line: *"Profit kept in the farm"*. (Same approach Stellium's balance sheet uses.)
5. **Plain words on screen, real statements underneath.** "Money In / Money Out / What's left" for P&L; "What you own / What you owe / Your stake" for the balance sheet; "Day-to-day farming / Buying & selling equipment / Loans & your own money" for cash flow sections. Account `note` text (already farmer-friendly) becomes the row tooltip.
6. **Farmer-wide by default, per-farm filter optional.** Follow the existing `ReportsController` pattern (all the user's farmers), with an optional `farm_uuid` query param — one farmer often runs several farms but banks/decisions look at the whole operation.

---

## 3. Backend implementation

### 3.1 Services — `app/Services/Ledger/Reports/`

Three small services plus one shared query builder (mirrors how `Ledger/Support` is organised):

- **`AccountBalanceQuery`** — the one place that knows how to compute per-account debit/credit sums for a farmer (+ optional farm), with `date <= X` or `date BETWEEN` variants, excluding soft-deleted transactions. Everything below composes it.
- **`ProfitAndLossService::generate($user, $from, $to, ?$farmUuid)`** — revenue accounts (credits − debits) and expense accounts (debits − credits) over the range, grouped under parent accounts, zero rows dropped, net profit line.
- **`BalanceSheetService::generate($user, $asOf, ?$farmUuid)`** — asset/liability/equity balances cumulative to `$asOf`, plus the current-earnings equity line; returns `is_balanced`/`difference` so tests (and a UI warning) can catch posting bugs.
- **`CashFlowService::generate($user, $from, $to, ?$farmUuid)`** — opening cash (cumulative to day before `$from`), inflow/outflow per classification section (§4), net movement, closing cash. Closing must equal the balance-sheet cash total for the same date — assert it in tests.

### 3.2 Endpoints — extend `routes/v1/farms/farm/reports/reports.route.php`

```php
Route::middleware('finances')->group(function () use ($controller) {
    Route::get('/profit-and-loss/plantings', [$controller, 'profitAndLossByPlantings']); // existing
    Route::get('/profit-and-loss', [$controller, 'profitAndLoss']);   // ?date_from&date_to&farm_uuid
    Route::get('/balance-sheet', [$controller, 'balanceSheet']);      // ?as_of&farm_uuid
    Route::get('/cash-flow', [$controller, 'cashFlow']);              // ?date_from&date_to&farm_uuid
});
```

Defaults: `date_from` = Jan 1 of the current year, `date_to`/`as_of` = today. Validate `date_to >= date_from`. Responses via `ApiResponse::successResponse` with dedicated Resources under `app/Http/Resources/Farms/Farm/Reports/`.

### 3.3 Tests (Pest, `farm-app-backend/tests/`)

Seed the chart, post a known script of transactions through `LedgerTransactionService` (cash sale, credit sale, AR settlement via `transfer()`, expense, loan received, equipment purchase, drawing, plus one `reverse()`), then assert:

- P&L: revenue/expense totals and net profit match hand-computed figures; reversed sale nets to zero.
- Balance sheet: `total_assets == total_liabilities + total_equity` (with current earnings); per-section figures correct.
- Cash flow: opening + net movement == closing == balance-sheet cash; each script line lands in the right section.
- Authorization: staff login (no finances) gets 403; farmer B never sees farmer A's figures; `farm_uuid` filter narrows correctly.

---

## 4. Cash flow classification (direct method)

For each entry on a cash account (debit = inflow, credit = outflow), classify by the other leg's account:

| Other leg | Section | Farmer label |
|---|---|---|
| Any `revenue` account, `Accounts Receivable` (1250) | Operating — in | Money from sales & buyers paying up |
| Any `expense` account, `Suppliers to Pay` (2100), `Inventory` (1300) | Operating — out | Paying for inputs, labour & suppliers |
| `Equipment & Tools` (1500), `Livestock` (1400) | Investing | Buying / selling equipment & animals kept |
| `Loans` (2200) | Financing | Loans received / repaid |
| `Owner's Capital` (3100), `Drawings` (3200) | Financing | Your own money in / money taken out |
| Another cash account (cash ↔ bank ↔ M-Pesa transfer) | **Excluded** | Internal move, not a flow |
| Anything unmapped | Operating (fallback) | — |

Drive the mapping off account `type` + `slug` in one `match` inside `CashFlowService` — no new columns needed. If custom accounts later break the slug assumptions, that's the trigger to add an explicit `cash_flow_section` column, not before.

---

## 5. Frontend (Nuxt)

- **Pages:** `app/pages/admin/reports/index.vue` (hub with the three report cards + the existing plantings P&L), `profit-and-loss.vue`, `balance-sheet.vue`, `cash-flow.vue`. All gated by `can_view_finances` exactly like existing money screens, hidden from staff nav.
- **Composable:** one `useFinancialReports.ts` — thin, API-first, **no offline registry entry** (read-only, online-only). Holds date-range state shared across the three pages so switching reports keeps the period.
- **Shared components:** `reports/ReportPeriodPicker.vue` (presets: This month / Last month / This year / Custom; single date for balance sheet), `reports/StatementTable.vue` (indented account rows, section subtotals, bold total lines, KES formatting), an offline empty-state.
- **Print/export:** browser print stylesheet on the statement pages first (farmers share PDFs via print-to-PDF on phones); a server-rendered PDF can come later if requested.
- **Entry points:** "Reports" item in the admin nav (money section); dashboard Money In/Out cards link to the P&L for the same period.

---

## 6. Phases

| Phase | Deliverable | Done when |
|---|---|---|
| **R1 — Balance query + P&L** | `AccountBalanceQuery`, `ProfitAndLossService`, endpoint + resource + tests | P&L for any range matches hand-computed script figures |
| **R2 — Balance sheet** | `BalanceSheetService`, endpoint + tests | Sheet balances (A = L + E incl. current earnings) on seeded + scripted data |
| **R3 — Cash flow** | `CashFlowService` + classification map, endpoint + tests | Opening/closing tie to balance-sheet cash; sections correct for the full script |
| **R4 — Reports UI** | Hub + three pages, period picker, statement table, nav + finance gating | Owner sees all three on phone; staff sees nothing; offline shows friendly notice |
| **R5 — Polish** | Print stylesheet, per-farm filter UI, prior-period comparison column on P&L | Optional; ship R1–R4 first |

R1–R3 are pure backend and can land independently of the frontend work.

---

## 7. Open questions (defaults chosen, flag if wrong)

1. **Fiscal year:** assumed calendar year for the "This year" preset and current-earnings roll-up. If farmers need a July–June option later, it's a settings field, not a schema change.
2. **Comparatives:** prior-period column deferred to R5 — is that acceptable for the first release?
3. **Plantings P&L placement:** plan keeps it and links it from the reports hub as a drill-down; not merging it into the new period P&L for now.
