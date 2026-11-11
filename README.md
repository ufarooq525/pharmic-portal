# Pharmic LH distribution portal

Laravel 13 + Blade portal that replaces the month-end Excel sheet. Each distributor's customer-wise
PDF is uploaded, read, checked against its own grand total, reviewed, and confirmed. Confirming posts
sales (with that month's prices, sales tax and withholding tax), stock movements and the customer register.

## What is built (phase 1)

| Page | What it does |
| --- | --- |
| Dashboard | Month-end close progress, things that need attention, sales/profit/units with change vs last month, tax to account for (sales tax + WHT), MR incentive, bonus cost, 6-month trend, sales by distributor, rep and product, stock cover (months left), top customers |
| Imports | Upload a PDF per distributor and month; layout recognised automatically; checks for totals, month and distributor name; review every customer and line with bonus, sales tax and WHT; link unknown product spellings once; confirm (Accounts) or undo (Owner); download the original PDF |
| Customers | Register built from the files: bought this month, new, needs a rep, no purchase (1–2 months), inactive (3+ months); assign one rep, or two at 50/50 |
| Inventory | Opening, purchases, sold + bonus, returns, closing, value at cost and Ex-FP, each product's tax rates, months of cover |
| Reports | Sales, customer-wise, bonus, percentage incentive, sales tax & WHT; filter by distributor, product, rep; CSV export that opens in Excel |
| Products & taxes | Prices, sales tax %, withholding tax %, incentive % and bonus scheme per product, saved with an effective date |
| Users & roles | Owner adds users, sets role and active status; table of what each role can do |

Phase 2: invoices, distributor ledger and payments, MR payroll and payslips, WhatsApp sharing, MR self-service.

## Structure

```
app/Modules/<Module>/
    web.php                 Blade routes (loaded with the "web" middleware)
    routes.php              JSON API under /api/v1 (kept for a future mobile app)
    Http/Controllers/Web    page controllers
    Resources/views         Blade views, namespaced: view('imports::review')
    Services                business logic (ReadImport, ConfirmImport, ImportPreview, DashboardData, ReportBuilder)
    Database/Migrations     module tables
    Console                 artisan commands
resources/views/components  shared Blade components (layout, page header, KPI, chip, bar list)
```

Modules: Access (login, users, roles), Catalog (products, dated rates and taxes), Distribution
(distributors, customers, reps, product spellings), Imports (PDF parsers, review, confirm), Sales
(sale lines, stock movements, dashboard, inventory, reports).

## Setup (Windows with Laragon/XAMPP, or a Linux VPS)

With Laragon: clone into `C:\laragon\www\pharmic-portal`, start Laragon (Apache + MySQL), put
poppler's `pdftotext.exe` in `tools\poppler\` (ignored by git) or point `PDFTOTEXT_PATH` at it,
run `npm install && npm run build`, then double-click `setup-windows.bat`. The site opens at
http://pharmic-portal.test.


Requirements: PHP 8.3+, Composer, Node 20+, MySQL 8 (or PostgreSQL), and poppler's `pdftotext`.

- Linux: `sudo apt install poppler-utils`
- Windows: download poppler for Windows, unzip to `C:\poppler`, and set
  `PDFTOTEXT_PATH=C:/poppler/Library/bin/pdftotext.exe` in `.env`

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_DATABASE / DB_USERNAME / DB_PASSWORD and SEED_OWNER_PASSWORD in .env
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Sign in with `owner@example.com` and the `SEED_OWNER_PASSWORD` you set (it falls back to
`change-me-now`; change it on the Users page).

## Distributor formats

Each distributor's software prints a different report. The portal reads three families and keeps a
profile per distributor on the **Distributors** page:

| Distributor | Report | Customer codes | Item codes | Bonus in file | Values |
|---|---|---|---|---|---|
| Hamdan, Monal, Saaj | SoftTouch product grid, each with its own column order and spellings | No | No | No | From the grand-total row |
| Irfan, Umar Drug | SoftTouch "Sale Report" (Item / Customer / Area lines) | Yes | Yes | Yes (returns negative) | TP and net amount per line |
| Friends | Customer annual sales, Jan–Dec columns | Yes | Yes | No | T.P and amount per line |

Every upload is checked for: totals against the file's grand total, the month (from and to dates
must fall in the chosen month, so a Jan–Dec export can't double count), the name at the top of the
file, and the report format against the distributor's saved one. The format and header are saved
from the first confirmed file if not set.

Product names are linked once per distributor. Where the file prints item codes, the code is kept
with the link, so a re-spelled name with a known code is still recognised. Codes are per
distributor (676 is Gynofol at Friends but Equate Flora at Umar Drug).

Bonus per distributor: the bonus printed in the file (the product scheme where none is printed,
the default), always the scheme, or only what the file prints.

## Load the October 2026 files

From the project folder, pointing at the "Pharmic LH" folder:

```bash
php artisan imports:read Hamdan  "C:/Users/umerf/OneDrive/Desktop/Pharmic LH/Hamdan ent.Mardan/CUSTOMER WISE (2).PDF" --confirm
php artisan imports:read Monal   "C:/Users/umerf/OneDrive/Desktop/Pharmic LH/Monal ent. Batkhela/COSTOMER.PDF" --confirm
php artisan imports:read Irfan   "C:/Users/umerf/OneDrive/Desktop/Pharmic LH/Irfan ent. Temergara/LH COSTUMER.PDF" --confirm
php artisan imports:read Friends "C:/Users/umerf/OneDrive/Desktop/Pharmic LH/Friends ent.Bunir/CUSTANUAL2.pdf" --confirm
php artisan imports:read Umar    "C:/Users/umerf/OneDrive/Desktop/Pharmic LH/Umer ent.Pesshawar/21-CWS.PDF"
php artisan imports:read Saaj    "C:/Users/umerf/OneDrive/Desktop/Pharmic LH/Saaj ent. Swabi/CUSTOMER (4).PDF" --period=2026-10
```

Umar Drug and Friends stay in review on purpose: "PROVIEM SUPPLEMENT", "EQUAT FLORA" and "Equate Flora"
are not linked to a product until someone confirms which product they are (link them on the review
page, then confirm). Saaj's file is for December 2025, so it is blocked for October. You can also
upload the same files on the Imports page.

## Tests

```bash
php artisan test
PHARMIC_SAMPLES="C:/Users/umerf/OneDrive/Desktop/Pharmic LH" php artisan test --filter=DistributorPdfTest
php tools/verify-parsers.php "path/to/file.pdf" ...   # parser check without the framework
```

## Values to confirm with the client

Sales tax %, withholding tax %, incentive % and bonus schemes in `DatabaseSeeder` are samples. Ex-FP,
cost and opening units come from the client's Excel sheet; trade prices from the distributor files.
Change any of them on Products & taxes with the date they start.

## Search engines

The portal is private: every response sends `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet,
noai, noimageai`, every page has `<meta name="robots" content="noindex">`, and `robots.txt` disallows
everything. SEO, GEO (AI search) and AEO (answer engines) belong on a public website for Pharmic LH,
not on this portal.

## Production

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize        # caches config, routes, views and events
```

A 2 vCPU / 4 GB VPS is plenty. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, and nightly
database + `storage/app/private/imports` backups (the original PDFs).
