# MotoGears — Automobile Parts & Accessories E-Commerce

> **`production` branch — deployed to https://themotogears.in (API https://api.themotogears.in/v1).**
> Defaults here are the live values: no demo data, debug off, demo payment methods off, phpMyAdmin off.
> Server setup: `cp .env.example .env`, fill in `ADMIN_PASSWORD`, database passwords, mail and Razorpay
> live keys, then `docker compose up -d --build` — see section 8. Develop on `main` and merge into
> `production` to release. For running it on your own computer, use the `main` branch.

A full-stack e-commerce platform for car and motorcycle parts with **exact vehicle fitment**
(make → model → variant → year), a complete customer storefront and a role-based admin console.

| Layer    | Stack |
|----------|-------|
| Frontend | Angular 22 (standalone components, signals, zoneless, OnPush, reactive forms, functional guards & interceptors, lazy routes, Angular CDK) |
| Backend  | Laravel 13 (PHP 8.3+), Sanctum bearer tokens, Form Requests, API Resources, Policies, Services, Events/Listeners, Notifications, Storage |
| Database | MySQL 8 via Eloquent (SQLite also works for tests) |
| API      | REST on its own host — `https://api.themotogears.in/v1`, `{ success, message, data, meta }` envelope, OpenAPI 3 docs |

```text
automotive-shop/
├── docker-compose.yml     frontend + backend + mysql + phpmyadmin
├── frontend/              Angular 22 app (storefront at /, admin at /admin)
└── backend/               Laravel REST API (/v1) + OpenAPI docs (/docs)
```

Angular never talks to MySQL. Laravel is the single source of truth for prices, stock,
coupons, orders, payments, permissions, compatibility and customer data.

---

## 1. Quick start with Docker (recommended)

Requirements: Docker Desktop (or Docker Engine + Compose v2).

```bash
cd MotoGears
cp .env.example .env        # local settings (also enables phpMyAdmin)
docker compose up -d --build
```

First boot takes a few minutes (image builds, `composer install`, `npm install`, migrations and
seeding). The backend waits for MySQL, runs migrations, seeds demo data **only when the database is
empty**, links storage and starts the scheduler.

| What | URL |
|------|-----|
| Storefront | http://localhost:4200 |
| Admin console | http://localhost:4200/admin |
| REST API | http://localhost:8000/v1 |
| API docs (Swagger UI) | http://localhost:8000/docs |
| phpMyAdmin | http://localhost:8090 (only with `COMPOSE_PROFILES=tools`, set in `.env.example`) |
| MySQL from the host | not published by default — use phpMyAdmin, or uncomment the `ports` lines under `mysql` in `docker-compose.yml` (db `automotive_shop`, user `motogears` / `motogears`) |

In Docker the Angular production build is served by nginx, which proxies `/api` to Laravel, so the
browser talks to a single origin.

Reset everything (drops the database and uploaded images):

```bash
docker compose down -v && docker compose up --build
```

---

## 2. Manual setup (without Docker)

Requirements: **Node.js 22.12+**, **Angular CLI 22** (`npm i -g @angular/cli`), **PHP 8.3+** with
`pdo_mysql, mbstring, intl, gd, zip, bcmath, fileinfo`, **Composer 2**, **MySQL 8**.

### Backend

```bash
cd backend
composer install
cp .env.example .env            # then set DB_* for your MySQL
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve               # http://localhost:8000
php artisan queue:work          # second terminal — runs product imports in the background
```

Create the database first, e.g. `CREATE DATABASE automotive_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

Optional: `php artisan schedule:work` in another terminal auto-cancels unpaid online orders after the
configured timeout (Settings → Checkout) and releases their reserved stock.

### Frontend

```bash
cd frontend
npm install
ng serve                        # http://localhost:4200  (or: npm start)
```

`src/environments/environment.ts` points at `http://localhost:8000/v1` for development.
The production build (`ng build`) calls `https://api.themotogears.in/v1` (`environment.prod.ts`); the Docker
build can override it with the `API_URL` build argument.

---

## 3. Demo credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@example.com | password |
| Customer | customer@example.com | password |
| Admin | manager@example.com | password |
| Catalog manager | catalog@example.com | password |
| Order manager | orders@example.com | password |
| Inventory manager | inventory@example.com | password |
| Content manager | content@example.com | password |

Coupons: `WELCOME10` (10% off ≥ ₹999, max ₹1,000), `SAVE500` (₹500 off ≥ ₹4,999), `FREESHIP`, plus
`BRAKES15`, `RIDE20` and an expired `MONSOON300` for testing error messages.

Demo payments (no real gateway is contacted):

| Method | Succeeds | Fails |
|--------|----------|-------|
| Cash on Delivery | always (up to the COD limit) | order above the COD limit |
| Demo card | any Luhn-valid number, e.g. `4111 1111 1111 1111`, future expiry, any CVV | a card ending in `0002` |
| UPI (simulator) — QR code | click **Simulate payment** under the QR | **Simulate failure** |
| UPI (simulator) — UPI ID | any UPI ID, e.g. `aarav@okaxis` (approved after ~4 s) | an ID starting with `fail`, e.g. `fail@ybl`; `invalid@…` is "not found" |

A failed payment leaves the order *pending / payment failed* with stock still reserved; the customer
can retry from the order page until the unpaid-order timeout cancels it.

### Razorpay (real payment gateway)

Razorpay Standard Checkout is built in (UPI, cards, net banking, wallets). It appears at checkout as
soon as keys are configured; without keys it stays hidden and the demo methods above are used.

1. In the [Razorpay Dashboard](https://dashboard.razorpay.com), switch to **Test Mode** →
   *Account & Settings → API Keys* → generate a key pair.
2. Copy `.env.example` (next to `docker-compose.yml`) to `.env` and fill in:
   ```
   RAZORPAY_KEY_ID=rzp_test_…
   RAZORPAY_KEY_SECRET=…
   ```
   (Manual setup: put the same variables in `backend/.env`.)
3. `docker compose up -d --build` — the checkout now shows **Razorpay**.
4. Pay with Razorpay's **Indian** test card `5267 3181 8797 5449` (any future expiry, any CVV; on the OTP
   screen any OTP works in test mode). `4111 1111 1111 1111` is an *international* card — accounts that
   accept domestic cards only decline it ("accepts domestic (Indian) card payments only"), which is a
   handy way to see the failure + retry flow. Use a real-looking mobile number (e.g. `9447012345`);
   Razorpay rejects obviously fake ones like `9876543210`.

How it works (all amounts and checks are server-side):

- `POST /orders` creates the order, reserves stock and creates a Razorpay Order for the **server-computed**
  total (in paise). The response carries only public checkout options (`key`, `order_id`, `amount`) —
  the key secret never leaves Laravel.
- Angular lazily loads `checkout.razorpay.com/v1/checkout.js`. On success it posts
  `razorpay_order_id / razorpay_payment_id / razorpay_signature` to `POST /orders/{id}/razorpay/verify`,
  which checks the HMAC-SHA256 signature, fetches the payment from Razorpay, checks order id + amount,
  captures it if only authorized, and confirms the order. Calls are idempotent.
- Closing the window or a declined payment leaves the order *pending*; the customer can retry from the
  order page (Razorpay or another method). Duplicate payments on the same order are refunded automatically.
- Cancelling a paid order (customer or admin) refunds it through the Razorpay Refunds API; a failed refund
  is logged in the order timeline for manual handling.
- **Webhook (recommended in production):** Dashboard → *Webhooks* → URL
  `https://api.themotogears.in/v1/webhooks/razorpay`, events `payment.captured`, `payment.authorized`,
  `payment.failed`, `order.paid`, and a secret you put in `RAZORPAY_WEBHOOK_SECRET`. It confirms orders even
  if the customer closes the browser before verification. Locally it needs a public tunnel (e.g. ngrok).
- **Closed window, missing webhook:** opening the order (success page, My orders, guest order page) asks
  Razorpay about pending attempts and confirms the order if it was paid; the unpaid-order job does the
  same before it cancels anything, so a paid order is never auto-cancelled.
- **Refunds come out of your Razorpay balance.** If the balance is lower than the refund, Razorpay
  refuses it; the order then shows *Refund pending* in the admin with the reason and a **Retry refund**
  button (the customer just sees "your refund is being processed").
- Only methods Razorpay has switched on for the account are offered (checked via `GET /methods`, cached
  30 min): e.g. UPI stays hidden until Razorpay enables it after KYC.
- **Test mode on the live site:** `rzp_test_…` keys run Razorpay's test mode (no real money); the
  storefront shows no banner, so the site looks exactly like launch (e.g. for the bank's review). Use
  live `rzp_live_…` keys (and a live-mode webhook) when you launch, and keep `PAYMENT_DEMO_METHODS=false`.

### UPI — QR code and UPI ID on our own page

**UPI** is the first payment option at checkout. After *Place order & pay by UPI* the shopper lands on
the payment page (`/order-success/:number`) with two tabs:

1. **Scan QR code** — a QR code (a `upi://pay` link, rendered as SVG by Laravel — no third-party QR
   service) for the exact order total. Scan with Google Pay, PhonePe, Paytm, BHIM or any bank app. On
   phones an **Open UPI app to pay** button opens the UPI app directly. The QR is valid for 10 minutes
   (`UPI_QR_TTL_MINUTES`), with a countdown; an expired QR can be replaced with one click.
2. **Pay with UPI ID** — the shopper types their UPI ID (e.g. `98xxxxxx10@ybl`); it is checked first
   (and the account holder's name shown), then a payment request is sent. They approve it in their UPI
   app with their PIN within 5 minutes (`UPI_COLLECT_TTL_MINUTES`).

While waiting, the page asks Laravel for the status every 3 seconds (and immediately when the shopper
switches back from their UPI app). Laravel asks Razorpay — the browser never decides that an order is
paid. As soon as the money arrives the order becomes **Confirmed / Paid**, the confirmation shows and
the usual emails go out. If the tab was closed, the Razorpay webhook confirms it instead.

- **Declined / expired:** the order stays *pending* with stock reserved; the page offers "Try again" or
  the other tab. Unpaid orders are cancelled after the unpaid-order timeout (Settings).
- **Retry later:** *Complete payment* on the order page (customer or guest) opens the same UPI page.
- **Duplicates:** if both the QR and a request get paid, the extra payment is refunded automatically.
- **Refunds:** cancelling a paid UPI order refunds through Razorpay.
- **Admin:** each payment shows "Scanned QR code" or "Request to UPI ID", the payer's UPI ID and name.

Provider (`UPI_PROVIDER`): `auto` uses **Razorpay** when keys are set, otherwise the **simulator** (with
*Simulate payment / failure* buttons) while `PAYMENT_DEMO_METHODS=true`. With Razorpay it uses the S2S
UPI API (`/payments/create/upi` with `flow: intent` for the QR and `flow: collect` for UPI IDs, plus
`/payments/validate/vpa`). **Ask Razorpay support to enable "UPI S2S (intent + collect)" on your
account.** Until it is enabled, the UPI page shows "UPI is not available right now" with a
**Pay with Razorpay instead** button (Razorpay's own window also takes UPI), so no order is stuck —
or set `UPI_ENABLED=false` to hide the option. In Razorpay test mode use the UPI ID
`success@razorpay` / `failure@razorpay`.

API: `POST /orders/{id}/upi/qr`, `POST /orders/{id}/upi/collect` `{vpa}`, `GET /orders/{id}/upi/status`
(guests: `/guest/orders/{number}/upi/...` with `X-Order-Token`); `POST …/upi/simulate` works only
with the simulator.

Seed data (fresh install): 25 customers + 6 staff, 13 vehicle manufacturers, 40 models, 86 variants
with year ranges, 32 categories (parent + child), 28 brands, 172 products with generated images, 90
historical orders across every status and payment method, 135 reviews, plus banners, pages, FAQs, blog
posts and testimonials.

### Bulk product import (Excel)

**Admin → Products → Import from Excel** adds or updates many products at once.

1. **Download the template.** *Blank template* for new products, or *Export my products* (optionally one
   category/brand) to bulk-edit what you already have. The workbook has:
   - **Instructions** — how it works plus a guide to every column (hover over any header in Excel for help);
   - **Products** — one row per product; red headers are required for new products;
   - **Compatibility** — one row per vehicle a product fits (make, model or exact variant + optional years);
   - **Variants** and **FAQs** — optional, linked to products by SKU;
   - **Lists** — every category path, brand, vehicle and filter value from your store. Category, Brand,
     Vehicle, Vehicle type, Yes/No and GST cells are **dropdowns** fed from this sheet, so related data is
     picked, not typed. It is regenerated on every download, so it always matches the store.
2. **Upload** the file (.xlsx, .xls or .csv, up to 10 MB / 5,000 products) and choose *Add new and update
   existing* (match by SKU), *Add new only* or *Update existing only*.
3. **Check** — the queue worker validates every row and saves each product inside a transaction that is
   rolled back, so you see exactly what will be added, updated or skipped, and every problem by sheet, row
   and column (downloadable as Excel). Nothing is saved yet.
4. **Import** — click *Import N products*. It runs in the background with a live progress bar; you can leave
   the page, stop it, and you get a notification (bell icon) when it finishes. Each product is saved
   completely or not at all, so one bad row never blocks the rest. Tick *Start importing automatically…*
   to skip the review step when the check is clean.

Rules: SKU is the key (existing SKU → update, new SKU → create). When updating, empty cells keep the current
value and `CLEAR` erases an optional field. *Stock on hand* sets the stock count (logged in Inventory, and
never below stock reserved by open orders). If a SKU appears on the Compatibility, Variants or FAQs sheet,
that product's list is replaced. *Image URLs* (separated by `|`) are downloaded in the background — public
http(s) links only (private/internal addresses are blocked), JPG/PNG/WEBP/GIF up to 5 MB.

Background jobs: Docker starts a queue worker automatically (`QUEUE_CONNECTION=database`). Without Docker run
`php artisan queue:work`, or set `QUEUE_CONNECTION=sync` to process imports inside the upload request.
Uploaded files are deleted after 30 days (`IMPORT_KEEP_FILES_DAYS`); the history stays.

### Guest checkout

Shoppers can buy without creating an account (switch it off in **Admin → Settings → Checkout → Allow guest
checkout**).

- **Checkout** — the cart's *Checkout* button opens the same 4-step checkout; for guests step 1 asks for an
  email address and the delivery address instead of saved addresses. All payment methods work (COD,
  Razorpay, demo card/UPI). Logged-in customers keep the normal checkout.
- **Private order link** — each guest order gets a secret 64-character token. The confirmation email (and
  every status email) contains a private link `/order/<number>?token=…`; the order is also remembered in
  that browser. Without the token the order simply isn't found (404), so order numbers can't be guessed.
- **Order details page** (`/order/:number`) — timeline, items, totals, address, GST invoice download, retry
  payment for unpaid orders and cancel (before packing), exactly like *My orders*.
- **Track order** (`/track-order`, linked in the header, footer and mobile menu) — order number + email
  opens the order. Rate limited (6/min, 30/hour per IP). Orders that belong to an account ask the shopper
  to log in instead.
- **Save to an account** — the success and order pages offer *Create account* (just a password; the order
  and its address move into the new account and the shopper is logged in). A shopper who already has an
  account logs in and clicks *Add to My orders*.
- **Coupons** — "uses per customer" limits count by account **or** email, so guests can't reuse a coupon by
  checking out again with the same email.
- **Admin** — orders list has a *Guests / Registered* filter and a *Guest* badge; search matches the
  customer's name, email and phone; the order page shows the guest's email and phone.

The migration `2026_10_11_000100_add_guest_checkout` makes `orders.user_id` nullable and stores the
customer's name, email and phone on every order (existing orders are backfilled), so nothing is lost when
an account is later deleted.

---

### Logo & brand assets

The source artwork is `brand/logo-source.pdf` (Illustrator). The web versions are generated from it
as vector SVGs in `frontend/public/brand/`:

| File | Used for |
|------|----------|
| `logo.svg` | Light backgrounds — mobile menu, admin login, invoices (+ faint watermark) |
| `logo-light.svg` | Dark backgrounds — site header, footer, admin sidebar (white lettering) |
| `logo-mark.svg` | Helmet mark only — favicon and app icons |
| `favicon-16/32.png`, `apple-touch-icon.png`, `icon-192/512.png`, `icon-maskable-512.png` | Browser tab, phone home screen (`site.webmanifest`) |
| `og-image.png` | Link previews on WhatsApp / Facebook / X (1200×630) |
| `logo-email.png` | Header of order emails (PNG because Gmail/Outlook don't show SVG) |

The downloadable invoice embeds `backend/resources/brand/logo.svg`, so it works offline. The site red
`--brand` (#DA1E25) matches the logo.

## 4. Features

### Storefront (`/`)
- Home: hero banners, vehicle selector (Make → Model → Year → Variant from the API), featured
  categories, deals, best sellers, new arrivals, brands, offers, testimonials, blog — one aggregated
  `GET /homepage` call.
- Selected vehicle persists and personalises listings, search and the "fits your vehicle" badges.
- Listing (`/shop`, `/category/:slug`, `/brand/:slug`, `/search`): grid/list, URL-driven filters (price,
  brand, category, rating, availability, discount, attributes, vehicle), sorting, pagination, mobile
  filter drawer, skeleton loaders.
- Debounced search with suggestions (products, categories, brands, vehicles), recent and popular searches.
- Product page: gallery with zoom, full screen and video, compatibility checker, specs, warranty,
  installation, what's included, reviews with photos, FAQs, share, sticky mobile buy bar.
- Cart drawer + cart page (quantity, coupons, shipping choice, free-shipping meter), wishlist
  (move to cart), 4-step checkout (address → shipping → payment → review) for customers **and guests**, UPI by QR code
  or UPI ID with live status (see "UPI" in section 3), order success,
  retry payment, guest order page + Track order (see "Guest checkout" in section 3).
- Account: dashboard, orders + timeline + cancel, printable/downloadable GST invoice, profile, password,
  addresses, saved vehicles ("My garage"), wishlist, reviews (verified purchase), notifications.
- Content: about, CMS pages, FAQ, blog (categories, tags, search), contact form, 404.

### Admin (`/admin`)
- Dashboard: KPIs, revenue/orders chart (30 days / 12 months), orders by status, top categories,
  brands, products and payment methods, recent orders, low-stock alerts — one `GET /admin/dashboard` call.
- Orders: status tabs with counts, search (number, name, email, phone), filters (payment status/method,
  guest/registered, date range), sort, CSV export;
  detail with items, totals, timeline, payments, addresses, status transitions (validated server-side,
  optional customer email), tracking and internal notes, invoice.
- Products: filters, featured/active toggles, soft delete + restore; editor with pricing (margin, GST
  preview), opening stock, drag-and-drop image upload and reordering (CDK), primary image and alt text,
  vehicle compatibility builder with dependent dropdowns, specifications, attributes, variants, FAQs, SEO
  preview, unsaved-changes guard.
- Product import from Excel (Products → Import from Excel): template with live dropdowns, background
  check + import with progress, problem list, history — see "Bulk product import" in section 3.
- Inventory: on hand / reserved / available, low & out-of-stock tabs, adjustments with reason and
  reference, per-SKU history, global stock-movement log.
- Categories, brands, attributes, vehicles (manufacturers → models → variants), coupons (product and
  category restrictions), banners (desktop/mobile images, schedule), pages, blog + categories, FAQs,
  testimonials, contact enquiries, newsletter (CSV export).
- Reviews moderation (approve/reject in bulk, feature, delete), customers (profile, orders, addresses,
  garage, reviews, disable account), reports (sales/products/customers/inventory for today, yesterday,
  last 7/30 days, this/last month, this year or a custom range, CSV export), settings, staff users,
  role → permission matrix, audit log.
- Every admin endpoint is protected by Sanctum + `staff` + `permission:*` middleware (and policies);
  the Angular guards and hidden menu items are only a convenience.

---

## 5. Architecture notes

**Backend** (`backend/app`)
- `Http/Controllers/Api/V1` (+ `Admin`) are thin; business rules live in `Services`
  (`PricingService`, `CartService`, `CouponService`, `OrderService`, `InventoryService`,
  `ProductQueryService`, `ReportService`, `InvoiceService`, …).
- Payments go through `Services/Payments/PaymentManager` and a `PaymentGateway` interface
  (`RazorpayGateway`, `CodGateway`, demo gateways) — add Stripe or another provider by implementing one class.
- Prices are stored excluding GST. Tax = per line (subtotal − discount) × product GST rate, plus GST on
  shipping. Standard shipping ₹100 (free from ₹2,999), express ₹250 — all editable in Settings.
- Inventory: `quantity` (on hand), `reserved` (open orders) and `available = quantity − reserved`.
  Placing an order reserves stock in a DB transaction with row locks; shipping deducts it; cancelling or
  returning releases/restocks it. Every movement is written to `inventory_transactions`.
- Events → listeners → notifications for order placed / status changed / low stock (mail goes to the log
  driver by default). Audit logs record admin changes.
- Centralised JSON exception handling: 401/403/404/422/429/500 always return the envelope, no stack
  traces when `APP_DEBUG=false`.
- CORS origins come from `CORS_ALLOWED_ORIGINS` (never `*`); auth is bearer tokens, so no cookies/CSRF
  are involved. Rate limits: auth, checkout and public forms.

**Frontend** (`frontend/src/app`)
- `core/` — `ApiService` (envelope + pagination), feature services (Product, Cart, Checkout, Order, …),
  signal stores (`AuthStore`, `CartStore`, `VehicleStore`, `UiStore`), functional interceptors
  (bearer + guest-cart token, global error handling), guards (`authGuard`, `guestGuard`, `adminGuard`,
  `permissionGuard`).
- `shared/` — reusable UI (header, footer, search bar, vehicle selector, product card/grid, gallery,
  filter panel, pagination, modal, toast, stepper, address form, order timeline…).
- `features/storefront` and `features/admin` are lazy-loaded route trees. Many simpler admin modules
  share one config-driven CRUD page (`features/admin/crud`), so validation, uploads, toggles and deletes
  behave identically everywhere.
- Styling is a small hand-written design system in `src/styles.css` (CSS variables, utility classes).

---

## 6. API

- Base URL: `https://api.themotogears.in/v1` (local: `http://localhost:8000/v1`)
- Docs: `https://api.themotogears.in/docs` (local: `http://localhost:8000/docs`, Swagger UI) — spec at `backend/public/docs/openapi.yaml`
- Auth: `POST /auth/login` (customer) or `POST /admin/auth/login` (staff) returns a token; send
  `Authorization: Bearer <token>`. Guest carts use the `X-Cart-Token` header returned by the cart API
  and merge into the account on login. Guest orders (`/guest/...`) are opened with the `X-Order-Token`
  header (or `?token=`) returned when the order is placed.

```json
{ "success": true, "message": "Products retrieved successfully", "data": [], "meta": { "current_page": 1, "last_page": 9, "per_page": 20, "total": 172 } }
```

---

## 7. Tests

```bash
cd backend && php artisan test      # 130 feature tests: auth, catalog, cart, coupons, checkout, guest checkout, UPI,
                                    # orders, Razorpay, product import, inventory, reviews, admin CRUD, authorization
cd frontend && ng test              # 71 Vitest specs: services (incl. Razorpay, UPI, product import, guest orders), guards, interceptors, cart state,
                                    # components, admin chart/CRUD logic
```

Backend tests run on in-memory SQLite (see `phpunit.xml`), so no MySQL is needed for them.

---

## 8. Production (themotogears.in)

**Branches:** `main` is development (localhost defaults, demo data). `production` is what the server runs —
the same code with themotogears.in values as the defaults. Work happens on `main`; to release, merge
`main` into `production` and on the server run `git pull && docker compose up -d --build`.


The site and the API run on two hosts:

| Host | Serves | Points to |
|------|--------|-----------|
| `themotogears.in` (+ `www.`) | Angular storefront & admin (static files) | the `frontend` container (port 80 inside) |
| `api.themotogears.in` | Laravel API at `/v1`, docs at `/docs`, images at `/storage` | the `backend` container (port 8000 inside) |

1. **DNS** — A/AAAA records for `themotogears.in`, `www.themotogears.in` and `api.themotogears.in` to the server.
2. **Environment** — every setting in `docker-compose.yml` is read from the `.env` file next to it:
   ```bash
   cp .env.production.example .env
   nano .env                      # set ADMIN_PASSWORD, DB passwords, mail, Razorpay live keys
   docker compose up -d --build   # rebuild the frontend whenever API_URL changes
   ```
   It sets `API_URL=https://api.themotogears.in/v1` (baked into the shop), `APP_URL=https://api.themotogears.in`,
   `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS=https://themotogears.in,https://www.themotogears.in`, `APP_ENV=production`,
   `APP_DEBUG=false`, keeps phpMyAdmin off and binds the ports to `127.0.0.1` so only the proxy can reach them.
   With `SEED_DEMO_DATA=false` an empty database gets only roles, settings, categories, brands and vehicles plus
   the super admin from `ADMIN_EMAIL` / `ADMIN_PASSWORD` — **no demo products, orders, customers or demo logins**.
   > **Database passwords** are applied only when the MySQL volume is first created. On a server that already
   > started with the defaults, either keep `DB_PASSWORD=motogears` / `DB_ROOT_PASSWORD=root` for now and change
   > them in MySQL later, or reset the demo database with `docker compose down -v` (deletes all data) before
   > starting with the new values.
3. **HTTPS reverse proxy** on the server — **one `server` block per host**; the `api.` host must go to the
   backend (port 8000), not the shop. Example nginx:
   ```nginx
   server {
       server_name themotogears.in www.themotogears.in;
       location / { proxy_pass http://127.0.0.1:4200; proxy_set_header Host $host; }
   }
   server {
       server_name api.themotogears.in;
       client_max_body_size 20m;
       location / {
           proxy_pass http://127.0.0.1:8000;
           proxy_set_header Host $host;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
           proxy_read_timeout 120s;
       }
   }
   ```
   Then certificates: `certbot --nginx -d themotogears.in -d www.themotogears.in -d api.themotogears.in`.
   Check: `https://api.themotogears.in/v1/settings` must return JSON (`{"success":true,…}`), not the shop page.
**Admin accounts on the server** (run inside the backend container):
```bash
docker compose exec backend php artisan app:create-admin you@themotogears.in   # asks for a strong password
docker compose exec backend php artisan app:set-password someone@themotogears.in
docker compose exec backend php artisan app:remove-demo-staff                    # lock admin@example.com & co.
```
A server that was first started with demo data still has the demo logins (`admin@example.com` / `password`):
create your own admin and run `app:remove-demo-staff`, or reset to a clean store with `docker compose down -v`
(deletes all data) and start again with the production `.env`.

4. **Razorpay webhook** — `https://api.themotogears.in/v1/webhooks/razorpay`.

Old `/api/v1/...` and `/api/docs` addresses redirect to the new ones.

### Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_KEY`, HTTPS URLs in `APP_URL` / `FRONTEND_URL`.
- Use live Razorpay keys, register the webhook and set `PAYMENT_DEMO_METHODS=false`.
- Set `CORS_ALLOWED_ORIGINS` to the storefront domain(s) only; set `TRUSTED_PROXIES` to your load balancer.
- Use a real mail driver, a queue worker (`QUEUE_CONNECTION=database|redis` + `php artisan queue:work`),
  and cron for `php artisan schedule:run`.
- Serve Laravel with nginx + PHP-FPM (the bundled Docker image uses `php artisan serve`, which is meant
  for local/demo use), and `MEDIA_DISK=s3` for uploads if you run several servers.
- For SEO-critical deployments, add Angular SSR (`ng add @angular/ssr`); routes and meta tags are
  already structured for it.
