# Bonus for distributors whose customer file has no bonus — design

Date: 2026-11-19 · Status: approved and built (2026-11-19)

Built as: `StockReportParser`, `Bonus\BonusAllocator`, `Bonus\ImportBonus` (one decision for review and
confirm), `AttachStockReport`. Report products are matched to the matrix's product names with spaces and
punctuation ignored ("GYNOFOL TAB" = "GYNOF OL TAB"), then through linked names or item codes. Checks on
the report live in `stock_report_checks` and the review page, not in `checks`, so they never change the status.

## Why

Some distributors (Hamdan; likely Monal and Saaj, same SoftTouch matrix) can only send the
**CUSTOMER WISE SALES REPORT** (matrix): net units per customer per product, no bonus at all.
Their **SALES & STOCK REPORT** (SSR) does print bonus, but per product only: sale qty/bonus,
return qty/bonus, net qty/bonus. They cannot send the line-item Sale Report that Umar Drug sends.

Today such a distributor gets either zero bonus (`BonusSource::File`) or the product scheme
(`BonusSource::Scheme`). Neither matches what the distributor actually gave: the scheme misses
special deals and returns that cancel out within the month.

Hamdan, September 2026, both files reconcile on net units per product (327 in total):

| Product | Matrix net qty | SSR net qty | SSR net bonus |
|---|---|---|---|
| GYNOFOL TAB | 164 | 164 | 50 |
| GYNOFOL TAB (N) | 30 | 30 | 10 |
| PROLIF-D TAB | −6 | −6 | 0 |
| PROLIF-D TAB (N) | 129 | 129 | 40 |
| PROVIEM MILK | 10 | 10 | 2 |
| **Total** | **327** | **327** | **102** |

## Goals

- For these distributors, the bonus posted for a month **equals the SSR's net bonus per product**.
- Each customer gets a fair, explainable share of that bonus, clearly marked as **estimated**.
- The user uploads both PDFs for the same import and sees the reconciliation before confirming.

## Non-goals

- Exact per-customer bonus. The files do not contain it, so the shares are estimates.
- Using the SSR for distributors whose file already prints bonus (the Umar hidden-return check).
  Possible later; not in this build.
- Stock reconciliation from the SSR's opening and closing stock. Possible later.
- Purchase returns (SSR "P.R S.Out"). They are not customer sales.

## Anti-goals

- The month's bonus total must never differ from the SSR without a visible warning.
- A matrix upload must never be blocked just because the SSR is missing or does not match. The user said **warn, don't block**.

## Decisions made by the user

1. Approach: import the matrix together with the SSR and spread each product's SSR bonus across customers (option 2).
2. When the matrix and the SSR disagree: **warn, don't block**. The bonus is still spread.
3. The user uploads both files.

## Design

### 1. Distributor setting

Add a case to `App\Modules\Distribution\Enums\BonusSource`:

```php
case Allocated = 'allocated';
// label: 'Spread the bonus from the Sales & Stock report across customers'
```

Hamdan (and other matrix distributors as needed) is switched to it on the Distributors page.
*My call:* this is a per-distributor setting, like the existing two. It is not switched on
automatically by the layout.

### 2. Upload flow

- Upload the matrix exactly as today (`ReadImport`). Nothing changes there.
- On the review page, for a distributor with `bonus_source = allocated`, a second card appears,
  **"Sales & Stock report"**, with its own drop zone. It also has a replace action, and an
  "Open the PDF" link once a file is attached.
- Attaching the SSR posts to a new route `imports.stock-report` (`POST /imports/{import}/stock-report`),
  under the `imports.upload` permission. The same guard applies: user can see the distributor, import not confirmed.
- A new matrix upload replaces the import (existing behaviour), so its SSR goes with it and must be uploaded again.
- *My call:* the SSR attaches after the matrix rather than on the first form. Only after reading
  the matrix do we know the import and its distributor setting, and the review page is where the comparison is shown.

### 3. Storage (migration)

On `imports`:

| Column | Type | Meaning |
|---|---|---|
| `stock_report_name` | string, null | original filename |
| `stock_report_path` | string, null | stored file |
| `stock_report` | json, null | parsed SSR products (below) |
| `stock_report_checks` | json, null | same `{key, ok, message}` shape as `checks` |

`stock_report` items: `code`, `name`, `trade_price`, `sale_units`, `sale_bonus`, `return_units`,
`return_bonus`, `net_units`, `net_bonus`, `closing_units`, `page`, `source`.

On `sale_lines`: add `bonus_target` (decimal, null), the product's SSR net bonus that this line's share
came from, kept for tracing. `bonus_rule` gains the value `allocated`.

Discard and revert delete the SSR file along with the matrix file.

### 4. Parser: `StockReportParser`

- New class `App\Modules\Imports\Parsers\StockReportParser`. It is **not** a `LayoutParser`: it does not
  produce customer lines and is not registered in `PdfImportReader`.
- Matches text containing `SALES & STOCK REPORT` and the `Net Sale` header. Other files are rejected with
  "This is not a Sales & Stock report."
- One product per row: `code name T.P. tax opening(qty,value) purchase(qty,bon,value) B.C sale(qty,bon)
  return(qty,bon) net(qty,bon,value) P.R transfer(out,in) closing(qty,value)`. Product names contain
  spaces and digits ("BELFIX 400MG CAP", "PROLIF-D TAB (N)"). Read the trailing numbers from the right
  and take the name as everything between the code and the T.P.
- Check against the printed totals: the sum of rows must equal the `Total Units` sale, return and net
  quantity and bonus on the Grand Total line.
- Also reads the From/To dates and the header name, reusing `PdfImportReader`'s period and name logic.
  Extract those into shared static helpers.

### 5. Product matching

SSR rows are matched to products through the distributor's existing aliases (`name_in_file`,
`code_in_file`), the same as import rows. If an SSR name is spelled differently from the matrix header
(e.g. "PROLIF- D TAB" vs "PROLIF-D TAB"), it appears in the existing "map this name" list on the review
page. Unmapped SSR rows block **Confirm**, exactly like unmapped import rows. That is a mapping gap, not a data mismatch.
SSR rows where net quantity and net bonus are both 0 are ignored: they don't need mapping.

### 6. Checks (warnings, never blocking)

Computed when the SSR is attached, and again when a product name is mapped. Shown as a card on the review page. None of them change `status`.

| Key | Warns when |
|---|---|
| `ssr_period` | SSR dates are not the import's month |
| `ssr_distributor` | SSR header name doesn't match the distributor |
| `ssr_totals` | SSR rows don't add up to its printed grand total |
| `ssr_units` | per product: matrix net qty ≠ SSR net qty (lists each product with both numbers) |
| `ssr_unassigned` | a product has SSR bonus but no matrix line with positive units, so that bonus can't be given to anyone |
| `ssr_missing` | distributor is `allocated` but no SSR is attached. Bonus falls back to the scheme. |

These checks need a non-blocking severity. Add `'warn' => true` to the check shape. `ReadImport`'s
blocked status keeps ignoring `warn` checks, and the review view renders them amber, not red.

### 7. Allocation: `BonusAllocator`

A pure class (no DB), used both by the review page (preview) and by `ConfirmImport` (posting), so both show the same numbers.

Input per product: the import rows for that product (units), the scheme bonus of each row from the
month's `ProductRate::schemeBonus()`, and the SSR `net_bonus` target T.

1. **No SSR row for the product** → each line gets its scheme bonus (`bonus_rule = scheme`).
2. **Target T ≥ 0:**
   - Eligible lines are those with units > 0. Lines with units ≤ 0 (returns, zero lines) get 0 bonus.
   - Weight = scheme bonus. If every eligible weight is 0, weight = units.
   - Share = T × weight / Σweight. Round down to whole units, then hand the leftover units, one each,
     to the largest fractional parts. Ties go to the larger units, then file order.
   - If there are no eligible lines, nothing is assigned and the `ssr_unassigned` warning shows.
3. **Target T < 0** (more bonus returned than given; rare): spread |T| as negative bonus over lines with
   units < 0, weighted by |units|, same rounding.

Result per row: `bonus`, `rule` (`allocated` | `scheme`), `target`. The sum per product equals T
whenever at least one line is eligible.

Example (Hamdan, September): PROVIEM MILK has one line (DR MEHWISH DARGAI, 10 units), so she gets the
whole SSR bonus of 2. GYNOFOL TAB: T = 50 over 14 customers, weighted by scheme bonus, so AKBAR M/C
(60 units) gets the largest share and the 1–2-unit customers usually get 0.

### 8. Confirm

In `ConfirmImport`, when the distributor is `allocated` and an SSR is attached, use the allocator's
result in place of `bonusFor()` / `bonusRule()`. Write `bonus_in_file = null`, `scheme_bonus`,
`bonus_rule = allocated`, `bonus_target`, `bonus_from_file = false`. Stock movements stay as they are
(units + bonus leave stock). The summary and the activity-log line gain "N allocated from the Sales & Stock report".

### 9. Review page

- **Stock report card** (allocated distributors only): drop zone, or file name + Open/Replace once attached.
- **Reconciliation table**, one row per product: matrix qty · SSR qty · SSR bonus · bonus allocated · ✓/⚠.
- Customer list and drawer: allocated bonus is shown with an **"estimated"** badge. Hovering it shows "Share of N bonus units on the Sales & Stock report".

### 10. Tests

Fixtures: the Hamdan September matrix PDF and the SSR PDF, in `tests/Fixtures`.

- `StockReportParser` reads all 17 products of the Hamdan SSR. Its totals match the printed Grand Total
  (sale 346/103, return 19/1, net 327/102).
- `BonusAllocator` unit tests: the sum equals the target, rounding with leftover units, all-zero scheme
  falls back to units, negative target, no eligible lines.
- Feature test: upload the matrix, attach the SSR, confirm. Posted bonus per product is 50/10/0/40/2 (102 total),
  every line has `bonus_rule = allocated`, and the units match.
- Mismatch feature test: an SSR with one product's qty changed shows the `ssr_units` warning, confirm still works,
  and the bonus still equals the SSR target.

## Approaches considered

1. **Ask for the line-item Sale Report.** Exact, no code. Ruled out: the distributor can't send it.
2. **Matrix + SSR, spread the bonus (chosen).** The totals always match the distributor's figures and the
   per-customer shares are estimates. One parser, one enum case, the allocator, a second upload slot.
3. **Scheme only (today).** No work, but the totals don't match the SSR. Hamdan's GYNOFOL is already off because of hidden returns.

## My calls (please check)

- A per-distributor `Allocated` setting rather than automatic by layout.
- The SSR attaches on the review page after the matrix, not on the first upload form.
- Weights are the scheme bonus, falling back to units. Returns get 0 bonus unless the target is negative.
- With no SSR attached, the bonus falls back to the scheme with a warning. Confirm is still allowed.
- Unmapped SSR product names block Confirm (it's a mapping step, same as today), but quantity
  mismatches only warn.

## Left to the builder

Exact column x-positions or regex for the SSR rows, view markup, the migration name, and how the
allocator receives rates (pass the `ProductRate` per product).
