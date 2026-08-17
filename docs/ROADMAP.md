# E-Commerce Build Roadmap

**Project:** Computer, electronics accessories & watch store — **personal, single shop**
**Stack:** Laravel 12.66 · PHP 8.4 · Inertia 2 · Vue 3 · Tailwind + Flowbite · Vite · MySQL
**Status:** Phases 1–3 complete · Phase 4 underway · 41 tables · 30 models · **126 tests passing**

> ### 🔀 Architecture: split frontend
>
> **The admin panel is Inertia + Vue. The storefront is a separate API client.**
> `routes/web.php` serves the admin panel only — hitting `/` redirects to the admin login.
> Customers are served by a versioned JSON API under `routes/api.php` (`/api/v1/*`), built on
> `spatie/laravel-query-builder` with API Resources for payload shaping.
>
> This is a change from the original plan, which put the storefront in the same Inertia app.
> Phases 4–9 below are written against the API-driven design. Phase 8 (order admin) stays
> Inertia, since it lives in the admin panel.

> ### 📐 Scale: personal single shop
>
> Phases 3–10 are scoped for **one owner running one shop**, not a team or a marketplace. Each
> phase has a **Core** list and an explicit **Skip for now** table with the reasoning.
>
> **The schema stays as designed.** Unused tables cost nothing — no maintenance, no runtime cost.
> What costs real time is building admin screens for features you don't need, so that is what
> gets cut. If the shop grows, the tables are already there.
>
> Roughly **40% less work** to a shippable shop than the original scope.

---

## Table of contents

**Build phases**

| | Phase | Title | Outcome |
|---|---|---|---|
| ✅ | [1](#-phase-1--cleanup--foundation) | Cleanup & Foundation | Clean codebase, correct schema, full model layer |
| ✅ | [2](#-phase-2--roles-permissions--admin-auth) | Roles, Permissions & Admin Auth | Admin can log in; `isAdmin` retired |
| ✅ | [3](#-phase-3--catalog-admin) | Catalog Admin | Products with variants, specs & images manageable — *tests outstanding* |
| 🔄 | [4](#-phase-4--storefront-catalog-api) | Storefront Catalog API | Customers can browse, filter and view products |
| ⬜ | [5](#-phase-5--cart-api) | Cart API | Guest + user carts that survive login |
| ⬜ | [6](#-phase-6--checkout--orders-cod) | Checkout & Orders (COD) | **First real order can be placed** |
| ⬜ | [7](#-phase-7--payments) | Payments *(optional)* | One gateway live — COD-only is viable |
| ⬜ | [8](#-phase-8--order-management-admin) | Order Management Admin | You can fulfil orders end to end |
| ⬜ | [9](#-phase-9--customer-account-api) | Customer Account API | Order history, addresses, wishlist |
| ⬜ | [10](#-phase-10--polish) | Polish | SEO, performance, security, backups |

**Legend:** ✅ done · 🔄 partial · ⬜ not started · `[~]` skipped deliberately

Each unbuilt phase lists **Core** (build it) and **Skip for now** (with reasons).

**Reference**

- [Appendix A — Database schema](#appendix-a--database-schema)
- [Appendix B — Category taxonomy](#appendix-b--category-taxonomy)
- [Appendix C — Packages](#appendix-c--packages)
- [Appendix D — Cross-cutting concerns](#appendix-d--cross-cutting-concerns)
- [Appendix E — Decisions (settled)](#appendix-e--decisions-settled)
- [**Where things actually stand**](#where-things-actually-stand) — the carried-forward gaps, ranked

---

## The core problem

The original `products` table was **flat** — one price, one `quantity`. Selling laptops and watches
means:

- **Variants** — 16GB/512GB vs 32GB/1TB, 41mm vs 45mm case, strap colour
- **Heavy spec data** — CPU, GPU, screen, battery, movement type, water resistance

Everything below assumes variants and specs get modelled properly in Phase 1.

> ✅ **Resolved in Phase 1.** Variants, specs and translatable columns are all in the schema.
> See [Appendix E](#appendix-e--decisions-settled) for the decisions as applied.

---

# ✅ Phase 1 — Cleanup & Foundation

### ✔️ DONE

> **Goal:** a clean codebase with a correct schema and working models.
> Nothing user-facing ships in this phase.

### ✅ 1.1 Checkpoint

```bash
git add -A && git commit -m "checkpoint before cleanup"
```

### ✅ 1.2 Fix the blockers

| Issue | File |
|---|---|
| Admin login/logout point to wrong controller → 500 | `routes/web.php:44-45` |
| `route('home')` doesn't exist → exception on access denial | `app/Http/Middleware/AdminMiddleware.php:20` |
| `cart_items.product_id` commented out | `2024_10_26_152334_create_cart_items_table.php:20` |
| `order_items.product_id` commented out | `2024_10_26_152419_create_order_items_table.php:19` |
| `Product::cartItem()` uses key `cartItem_id` (should be `product_id`) | `app/Models/Product.php:14` |
| `SoftDeletes` trait missing despite `softDeletes()` column | `app/Models/Product.php` |
| `products` mixes `created_by` with `update_by` / `delete_by` | products migration |
| `->nullable()` chained after `->on()` — no effect | `user_addresses` migration:26 |

### ✅ 1.3 Delete

**Verdict: clean now.** There are **8 stub controllers × 7 empty methods = 56 methods with zero
logic**. You are deleting empty shells, not working code. This gets expensive later; right now it
costs nothing.

| What | Why |
|---|---|
| 8 stub controllers — `Brand`, `CartItem`, `Category`, `Order`, `OrderItem`, `Payment`, `Product`, `ProductImage`, `UserAddress` | All empty, and all in the **flat namespace**. You need `Admin\*` and `Shop\*` — keeping these leaves wrong-namespace duplicates to work around. Regenerate later with `php artisan make:controller Admin/ProductController --resource --model=Product` |
| `resources/views/welcome.blade.php` | Dead. Inertia serves `app.blade.php`, and `/` renders the Inertia `Welcome` page |
| `.erd.json` | Empty ERD — `tableIds: []`, zero tables |
| `resources/js/Pages/Admin/Components/Footer.vue` | 0 bytes |

### ✅ 1.4 Rewrite

| What | Change |
|---|---|
| The 9 domain migrations | Rewrite **in place** with [Appendix A](#appendix-a--database-schema) — pre-launch, no data to preserve. Cleaner than stacking `ALTER` migrations |
| `app/Http/Middleware/redirectAdmin.php` | Rename to `RedirectIfAdmin`. It *works*, but a lowercase class name breaks PSR-1 and trips static analysis |
| `app/Models/Product.php`, `CartItem.php` | Fix broken relations; add `$fillable` / `$casts` |

### ✅ 1.5 Keep as-is

- **All Breeze auth** — controllers, form requests, `Pages/Auth/*.vue`, `Layouts/`, `Components/`
- **`Sidebar.vue` + `Navbar.vue`** — 824 lines of working Flowbite markup. Keep it; just swap
  `href="#"` for Ziggy `route()` calls in Phase 3
- **`HandleInertiaRequests`** — shares the authenticated admin and flash messages.
  ~~cart count~~ 🔀 the cart is API-side now, so nothing storefront-facing goes through here
- Vite / Tailwind / PostCSS config
- ~~`Welcome.vue` and `Dashboard.vue` become the storefront home and account dashboard~~ —
  🔀 obsolete. `/` redirects to the admin login; the storefront home lives in the frontend project

> **Already fine, no action:** `.env` is correctly gitignored and untracked ✓ ·
> `Kernel.php:5` properly imports the `redirectAdmin` middleware ✓

### ✅ 1.6 Build the model layer

- [x] `GeneratesSlug` trait — unique slug from the English value on create
- [x] **Brand**, **Category** (self-referencing tree), **Tag** — `HasTranslations`, relations, scopes
- [x] **User** — `HasRoles`, soft deletes, relations, `isAdmin()`
- [x] **CartItem**, **Product** — relations fixed, `$fillable` / `$casts` complete
- [x] **All 30 models** — ProductVariant, Attribute, AttributeValue, ProductImage,
      ProductSpecification, Order, OrderItem, OrderStatusHistory, Payment, Refund, RefundItem,
      UserAddress, Review, ReviewImage, Wishlist, Coupon, CouponUsage, CouponTarget, Currency,
      TaxRate, Setting, ShippingZone, ShippingMethod, InventoryMovement
- [x] Scopes: `published()`, `inStock()`, `featured()`, `search()`, `active()`
- [x] Accessors: `final_price`, `discount_percent`, `primary_image_url`, `is_low_stock`
- [x] **Enums** — `ProductStatus`, `OrderStatus`, `PaymentStatus`, `FulfillmentStatus`, `Role`.
      `OrderStatus` encodes its own legal transitions and stock-release rule

### ✅ 1.7 Seeders & factories

- [x] **RoleSeeder** — 4 roles, 56 permissions
- [x] **DatabaseSeeder** — 3 sample accounts (admin / manager / customer, password `password`)
- [x] **UserFactory** — with `unverified()` and `inactive()` states
- [x] **ReferenceDataSeeder** — currencies, settings, tax rates, shipping zones & methods
- [x] **CatalogStructureSeeder** — brands, the category tree, attributes & values, tags
- [x] **ProductSeeder** — products with variants, specs and images
- [x] **Factories** — `Product`, `ProductVariant`, `Order`, plus `Brand`, `Category`,
      `Attribute` and `AttributeValue` as their dependencies. Seeders cover *looking at* data;
      factories are what the tests generate it with. States included: `draft()` / `featured()` /
      `onSale()` / `outOfStock()` on products, `isDefault()` / `lowStock()` on variants, and
      `guest()` / `paid()` / `shipped()` / `delivered()` / `withTotals()` on orders

**✅ Done —** `migrate:fresh --seed` produces a full catalog, every model relationship resolves
in `tinker`, and the factories back 126 passing tests.

---

# ✅ Phase 2 — Roles, Permissions & Admin Auth

### ✔️ DONE

> **Goal:** admin can actually log in. Right now the route points at a missing method.

- [x] Install `spatie/laravel-permission`
- [x] Roles: `super-admin`, `manager`, `staff`, `customer`
- [x] **Drop `users.isAdmin`** — replaced by roles
- [~] ~~Merge `AdminAuthController` + `AdminController`~~ — **skipped deliberately.** The route→method
      mismatch that motivated this was fixed in Phase 1; merging auth with dashboard rendering
      would put two unrelated concerns in one class. Route bindings ✅
- [x] Update `AdminMiddleware` to check role, not the boolean; fix the `route('home')` redirect
- [x] `users` table: add `phone`, `avatar`, `last_login_at`, `is_active`
- [x] Policies: `ProductPolicy`, `OrderPolicy`, `ReviewPolicy`, `CouponPolicy` — ✅ **written**,
      auto-discovered, covered by 11 tests. They add the per-record rules route middleware
      cannot express: a product referenced by an order line cannot be force-deleted, a paid
      order cannot be deleted at all, a customer sees only their own orders, a review author
      may edit only while it is pending, and a redeemed coupon is deactivated rather than deleted
- [x] Rate-limit the admin login route (5/min per email + IP)
- [x] Install `spatie/laravel-activitylog` for the admin audit trail

**✅ Done —** verified by 14 tests in `tests/Feature/Admin/`. Beyond the original scope, the login
flow now also rejects deactivated accounts and tears down the session for authenticated
non-admins rather than leaving them logged in.

> **Note:** `User` is soft-deleted so past orders keep their customer attribution. Trade-off — a
> deleted email still occupies the unique index, so that person cannot re-register until the row
> is restored or purged.

---

# ✅ Phase 3 — Catalog Admin

### ✔️ DONE — *built, tested (53 tests) · media optimisation deferred to Phase 10*

> **Goal:** you can manage the products you actually sell. The biggest phase — budget accordingly.

Wire the existing Flowbite sidebar links to real routes as you go.

### Core — build this

- [x] **Products** — the centrepiece:
  - [x] List: search, filter by category/brand/status
  - [x] Create/edit: title, description, price, stock, warranty, condition, release year
  - [x] **Variant matrix generator** — pick attributes, auto-generate SKU rows with price + stock
  - [x] Drag-drop multi-image upload with reorder + primary flag
  - [x] Spec-sheet builder (grouped key/value rows)
  - [x] Duplicate product — the fastest way to add your 40th similar item
- [x] **Categories** — nested tree CRUD ([Appendix B](#appendix-b--category-taxonomy))
- [x] **Brands** — CRUD, logo upload
- [~] **Media** — ~~`spatie/laravel-medialibrary` + `intervention/image`~~ — **not installed.**
      Images are stored through Laravel's own `Storage` disk, with upload, reorder, primary-flag
      and delete handled in `ProductService`. What that costs: **no thumbnails and no WebP**, so
      the storefront serves full-size originals. Revisit as a Phase 10 performance item —
      swapping it in later means rewriting a working image layer, which is not worth doing now
- [x] **Inventory** — stock levels, low-stock view, adjustments with `inventory_movements` history

> **Built as:** `Admin\{Product,Category,Brand,Inventory}Controller` → `routes/admin.php`, every
> route gated on a spatie permission. Vue pages in `resources/js/Pages/Admin/`, with
> `VariantMatrix.vue` and `SpecBuilder.vue` as the two non-trivial components. Product writes go
> through `ProductService` rather than the controller.

### ✅ Tests — 53 covering this phase

Phase 3 originally shipped with none. Now covered:

- [x] Product CRUD, with the variant matrix and spec builder round-tripping in both locales
- [x] Image upload, reorder, primary-flag and delete, including primary promotion on delete
- [x] Product duplication — the deep-copy of variants, specs, images and tags
- [x] Category tree CRUD, including the cycle guards, and brand CRUD with logo replacement
- [x] Inventory adjustment writing a correct `inventory_movements` row
- [x] Permission gating per role

> **Two real bugs surfaced by writing these:**
>
> 1. **`InventoryController` 500'd whenever no reason was submitted** — `reason` is `nullable`,
>    so it is simply absent from the validated array, and the controller read `$data['reason']`
>    directly. Fixed.
> 2. **Search was case-sensitive** — see [Phase 4](#-phase-4--storefront-catalog-api).
>
> A third finding needed no fix: `ProductService` guards against a blank specification key, but
> `ProductRequest` rejects that row first, so the guard is unreachable over HTTP.

### Skip for now

| Skipped | Why | Fallback |
|---|---|---|
| Attributes & values admin UI | 7 attributes, 35 values, already seeded. You'll change them maybe twice a year | Edit the seeder, re-run it |
| Tags admin UI | Same — low churn | Seeder |
| Bulk actions | Useful at 500 products, not at 50 | Edit individually |
| Drag-reorder on the category tree | A `sort_order` number field does the same job | Number input |
| `inventory_movements` history UI | The rows still get written; you just don't browse them | Query the table if you ever need it |

**✅ Done —** you can create a gaming laptop with 4 RAM/storage variants, 8 images and a
20-row spec sheet, and see correct per-variant stock. Media optimisation and the test suite
are the two knowingly-deferred pieces above.

---

# 🔄 Phase 4 — Storefront Catalog API

### 🔨 IN PROGRESS — *catalog + auth endpoints live · 5 items left*

> **Goal:** everything a customer needs to browse and evaluate products, exposed as JSON.
> No cart yet.

> 🔀 **Rewritten.** This phase originally described Inertia pages inside this app. The storefront
> is now a separate API client, so Phase 4 delivers **endpoints, not screens** — the UI work
> (layout, home page, gallery, variant picker) belongs to the frontend project and is out of
> scope here.

### ✅ Already built

`routes/api.php`, under `/api/v1` with the `api.locale` middleware:

| Endpoint | Notes |
|---|---|
| `GET products` | `spatie/laravel-query-builder` — filters `search`, `brand`, `category`, `price_min`, `price_max`, `in_stock`, `rating_min`, `condition`, `release_year`, `is_featured` |
| `GET products/{slug}` | Eager-loads brand, category, images, specs, active variants + their attribute values; increments `views_count` |
| `GET categories` · `GET categories/{slug}` | |
| `GET brands` · `GET brands/{slug}` | |
| `POST register` · `POST login` | ✅ Sanctum tokens, throttled 10/min on email + IP |
| `GET me` · `POST logout` · `POST logout-all` | ✅ `auth:sanctum` |

Sorts: `price`, `created_at`, `rating_avg`, `views_count`, `name`. Includes: `brand`, `category`,
`images`, `variants`, `specifications`. Pagination is `per_page`, capped at 100, default 24.

**Localisation:** `SetApiLocale` resolves the response language from `?lang=km` or
`Accept-Language`, so translatable columns serialise in the caller's locale. Payloads are shaped
by six API Resources (`Product`, `ProductVariant`, `ProductImage`, `ProductSpecification`,
`Brand`, `Category`).

**Customer auth (settled):** Sanctum personal access tokens, not sessions — the storefront is a
separate origin. Registration assigns the `customer` role. Admins are **refused** here and must
use the session-based admin panel, so the two auth models never mix. Deactivated accounts are
rejected, and a bad password and an unknown email return the same error so the endpoint cannot
be used to enumerate accounts. 13 tests.

**Search (fixed):** `products.search_text` is a STORED generated column flattening `title.en`,
`title.km` and `sku`, carrying a FULLTEXT index. `Product::search()` runs boolean-mode
`MATCH … AGAINST` with each token required and a trailing wildcard, stripping boolean operators
first — otherwise the hyphen in `LAP-001` reads as "exclude 001". Terms below MySQL's minimum
token length fall back to a `CAST(title AS CHAR) LIKE` scan. 10 tests.

### Core — still to build

- [ ] **Category tree endpoint** — the flat `categories` list can't drive nested navigation.
      Return the tree in one call, cached (it changes maybe twice a year)
- [ ] **Filter metadata for listing pages** — the brands, price range and attribute values
      actually present in a given category, so the frontend can render filters without
      hardcoding them
- [ ] **Home-page feed** — featured products, new arrivals, best sellers. One endpoint beats
      three round-trips on first paint
- [ ] **Related products** on the detail response
- [ ] **`GET settings`** — shop name, currency + KHR rate, contact details, static-page copy.
      The `settings` table exists and is seeded; nothing reads it yet
- [ ] **API tests for the catalog endpoints** — filtering, sorting, pagination, locale
      negotiation, and 404s for unpublished or missing slugs. *(Search and auth are covered;
      the product/category/brand endpoints themselves are not yet.)*

### Skip for now

| Skipped | Why |
|---|---|
| Deep spec faceting (CPU, RAM, screen size as filters) | Needs specs promoted to indexed columns. Brand + price covers most of the value at your catalog size |
| Search autocomplete | The results page is enough under a few hundred products |
| Recently viewed | Pure nice-to-have |
| Rate limiting per-endpoint | The default `api` throttle is fine until there's real traffic |
| API docs (OpenAPI/Scramble) | One consumer, written by you. The route file is the doc |

**✅ Done when:** `GET /api/v1/products?filter[category]=laptops&filter[brand]=asus&filter[price_max]=1500`
returns the right page, and the detail response carries everything a variant picker needs.

---

# ⬜ Phase 5 — Cart API

### ⬜ NOT STARTED — *schema and model accessors already in place*

> **Goal:** guest and logged-in carts that behave correctly across login.

- [ ] **`CartService`** — add / update / remove, and **merge guest cart into user cart on login**
      (the part that's easy to get wrong)
- [ ] **Endpoints** — `GET/POST/PATCH/DELETE /api/v1/cart`, returning the full cart with line
      subtotals and a grand total on every mutation, so the client never recomputes money
- [ ] Stock re-validation on read *and* on write — a cart can go stale between the two
- [ ] Surface `price_changed` per line — `CartItem` already exposes the accessor
- [ ] **Guest cart identity** — the API has no session cookie to lean on. Issue a cart token the
      client stores and sends back; `cart_items.session_id` is the column it maps to. **Settle
      this before writing `CartService`** — it decides the whole endpoint contract

> Schema is already done — `cart_items` has `product_variant_id`, `session_id` and `price_at_add`,
> and the model exposes `current_price`, `subtotal` and `price_changed` accessors plus
> `forUser()` / `forSession()` scopes.

> ~~Mini-cart dropdown, cart count via `HandleInertiaRequests`~~ — frontend concerns, out of
> scope for the API. The count is derivable from the cart response.

**✅ Done when:** add to cart as a guest, log in, and the items are still there — with no duplicates.

---

# ⬜ Phase 6 — Checkout & Orders (COD)

### ⬜ NOT STARTED — *the milestone phase · shipping helpers already written*

> **Goal:** **the first real order can be placed.** Cash on Delivery only — no gateway
> integration needed, which unblocks the entire flow.

### Core — build this

- [ ] **Checkout endpoint** — guest + logged-in, taking address, shipping method and COD in one
      `POST /api/v1/checkout`, wrapped in a transaction
- [ ] **`CheckoutService`** — validate stock → create order → decrement stock
- [ ] **`PricingService`** — variant price, shipping, totals. **The server is the only authority
      on money** — never accept a client-sent total
- [ ] **`InventoryService`** — decrement on order, restock on cancel
- [ ] `OrderNumberGenerator` — `ORD-20260816-0001`
- [ ] **Quote endpoint** — totals for a cart + address *before* committing, so the client can
      show shipping and grand total on the checkout screen
- [ ] Order confirmation response + **order tracking by number** (guests included — needs an
      unauthenticated lookup, so pair the number with the order email to prevent enumeration)
- [ ] Customer address book — `/api/v1/addresses` CRUD with default shipping/billing

> Already built: `ShippingZone::forProvince()` resolves Phnom Penh vs provinces,
> `ShippingMethod::calculate()` handles flat/weight/free-threshold, `UserAddress::toSnapshot()`
> flattens for JSON storage.

### Decide, don't assume

- [ ] **Tax** — do you actually charge VAT? If not, set `tax_total` to 0 and skip `PricingService`
      tax logic entirely. The `tax_rates` table can sit unused.

**✅ Done when:** a guest completes checkout, stock decrements, and the order appears with a
correct total.

---

# ⬜ Phase 7 — Payments

### ⬜ NOT STARTED — *optional · COD-only is a viable launch*

> **Goal:** take money online. **Optional** — plenty of Cambodian shops run COD-only indefinitely.

- [ ] Abstract a `PaymentGateway` interface so new gateways never touch checkout code
- [ ] **Start with one gateway.** ABA PayWay or Bakong KHQR — whichever your bank already supports
- [ ] Webhook handling + signature verification
- [ ] Payment status transitions

### Skip for now

| Skipped | Why |
|---|---|
| Multiple gateways at once | Integrate one, prove it, then add another |
| Stripe / PayPal | Cambodia-only ([Appendix E](#appendix-e--decisions-settled)) |
| Automated refund API calls | Refund manually in the bank portal, mark the order refunded in admin |

---

# ⬜ Phase 8 — Order Management Admin

### 🔄 PARTLY DONE — *dashboard ✅ built early · orders, invoices, emails outstanding*

> **Goal:** you can fulfil orders end to end.

### Core — build this

> This phase stays **Inertia + Vue** — it lives in the admin panel, not the storefront.

- [ ] **Orders admin** — list with status/date filters; detail page (items, customer, address,
      timeline); status transitions
- [ ] **Invoices + delivery notes** — `barryvdh/laravel-dompdf`
- [ ] **Emails** — order confirmation, shipped, delivered
- [ ] ⚠️ **Switch `QUEUE_CONNECTION` off `sync`** — every email currently blocks the request.
      Tables already exist; it's a one-line `.env` change *(still `sync` as of today)*
- [ ] Configure real SMTP (`MAIL_HOST=mailpit` is dev-only — *still mailpit*)
- [x] **Dashboard** — ✅ **built ahead of schedule.** `AdminController` already serves today's and
      this month's revenue, a zero-filled 14-day sales chart, orders by status, low-stock alerts,
      recent orders and top products by views. Note this contradicts the "sales charts" skip
      below — the chart got built anyway

> `OrderStatus` already encodes its own legal transitions and stock-release rule, so the admin
> just calls `canTransitionTo()`.

### Skip for now

| Skipped | Why |
|---|---|
| Customers admin (list, lifetime value) | You can see who ordered from the order itself |
| Reports module + CSV export | A few SQL queries when you're curious beats a reporting UI |
| ~~Sales charts~~ | **Built anyway** — the dashboard carries a 14-day revenue chart |
| Activity log UI | Rows still get written; browse the table if needed |

**✅ Done when:** an order can go placed → confirmed → shipped → delivered, with the customer
emailed at each step and a printable invoice.

---

# ⬜ Phase 9 — Customer Account API

### ⬜ NOT STARTED — *blocked on the Phase 4 customer auth decision*

> **Goal:** returning customers can see their history.

> Sanctum-authenticated endpoints, not Inertia pages. Depends on the customer auth model
> settled in [Phase 4](#-phase-4--storefront-catalog-api).

- [ ] **Account** — `GET /orders`, `GET /orders/{number}`, addresses CRUD, profile + password
- [ ] **Wishlist** — cheap to add, genuinely used. `wishlists` table and model already exist

### Skip for now

| Skipped | Why |
|---|---|
| Reviews + moderation queue | Real work (submission, verified-purchase check, moderation UI, rating aggregation) for something a new shop has almost none of. The tables are there when you want it |
| Review images | Same |

---

# ⬜ Phase 10 — Polish

### ⬜ NOT STARTED — *absorbs the deferred image and search work*

> **Goal:** make it fast and findable. Do these before launch, not after.

- [ ] **SEO** — 🔀 mostly the **frontend's** job now (meta tags, Open Graph, JSON-LD, rendering
      strategy). This repo owns the inputs: `meta_title` / `meta_description` in API responses,
      stable slugs, and a `sitemap.xml` feed the frontend can consume
- [ ] **Images** — the deferred Phase 3 item: resizing, thumbnails and WebP. Serving full-size
      originals is the single biggest storefront payload win available
- [ ] **Search** — the deferred Phase 4 item: FULLTEXT index, drop the unindexed `LIKE`
- [ ] **Performance** — eager-load to kill N+1, cache the category tree and settings,
      `CACHE_DRIVER=redis`, paginate everywhere
- [ ] **Security** — `APP_DEBUG=false` *(currently `true`)*, rate-limit checkout, validate all
      uploads, and **write the policies** deferred since Phase 2
- [ ] **CORS** — the storefront is a different origin. `config/cors.php` needs a real allowlist
      before launch, not `*`
- [ ] Feature tests: cart, checkout, order creation, stock decrement — plus the Phase 3 catalog
      admin tests still outstanding
- [ ] Backups — a nightly `mysqldump` is enough

### Skip for now

| Skipped | Why |
|---|---|
| Coupons admin UI | `Coupon::discountFor()` is written. Add codes via tinker until you run real promotions |
| Product compare | High value at scale, low at 50 products |
| Meilisearch / Scout | MySQL FULLTEXT handles thousands of products — add the index first |
| Laravel Horizon | Only worth it once queues are busy |
| Flash sales | `compare_at_price` already gives you strike-through pricing |

---
---
---

# Appendix A — Database schema

## A1. Catalog

**`categories`** — add `parent_id` (self FK), `description`, `image`, `icon`, `is_active`, `sort_order`

**`brands`** — add `logo`, `description`, `is_active`, `sort_order`

**`products`** — add `sku`, `short_description`, `compare_at_price` (strike-through), `cost_price`,
**`warranty_months`**, `release_year`, `weight`, `length`/`width`/`height`, `is_featured`,
`condition` (new/refurbished/used), `meta_title`, `meta_description`, `views_count`,
`rating_avg`, `rating_count`, `status` enum

**`product_variants`** — 🆕 **the critical one**
`product_id`, `sku`, `price`, `compare_at_price`, `stock_quantity`, `image_id`, `is_default`,
`low_stock_threshold`, `allow_backorder`

**`attributes`** + **`attribute_values`** — 🆕 RAM, Storage, Colour, Case Size, Strap Material

**`product_variant_attribute_values`** — 🆕 pivot linking a variant to its option combination

**`product_specifications`** — 🆕 `product_id`, `group` (Processor/Display/Battery), `key`,
`value`, `sort_order`. This is your spec sheet.

**`product_images`** — add `sort_order`, `is_primary`, `alt_text`, `variant_id`

**`product_tags`** + pivot — 🆕

**`reviews`** — 🆕 `user_id`, `product_id`, `order_id`, `rating`, `title`, `body`,
`is_verified_purchase`, `status`, `admin_reply`

**`review_images`** — 🆕 *(optional)* · **`wishlists`** — 🆕 · **`product_views`** — 🆕 *(optional)*

## A2. Inventory

**`inventory_movements`** — 🆕 `in`/`out`/`adjustment`/`return`, quantity, reason, reference

**`product_serials`** — 🆕 *(optional)* serial / IMEI per unit — useful for warranty claims

## A3. Promotions & pricing

**`coupons`** — 🆕 `code`, `type` (fixed/percent), `value`, `min_order`, `usage_limit`,
`per_user_limit`, `starts_at`, `expires_at`, `applies_to`

**`coupon_usages`** — 🆕 · **`flash_sales`** — 🆕 *(optional)* · **`tax_rates`** — 🆕

**`currencies`** + exchange rates — 🆕 *(if selling in USD **and** KHR)*

## A4. Cart & checkout

**`cart_items`** — add `product_variant_id`, `session_id` (guest carts), `price_at_add`;
`user_id` nullable

**`orders`** — add `order_number`, `subtotal`, `discount_total`, `tax_total`, `shipping_fee`,
`grand_total`, `currency`, `payment_status`, `fulfillment_status`, `coupon_id`,
**`shipping_address` as JSON snapshot**, `billing_address` JSON, `customer_note`, `admin_note`,
`placed_at`, `shipped_at`, `delivered_at`, `cancelled_at`

> ⚠️ **Snapshot the address as JSON.** If you only FK to `user_addresses` and the customer edits
> that address later, your order history silently rewrites itself.

**`order_items`** — add `product_variant_id`, plus **snapshots**: `product_name`, `sku`,
`variant_label`, `unit_price`, `quantity`, `subtotal`. Same reasoning.

**`order_status_histories`** — 🆕 who changed status, when, note

**`shipping_methods`** + **`shipping_zones`** — 🆕

**`payments`** — add `transaction_id`, `gateway`, `gateway_payload` JSON, `paid_at`, `refunded_at`

**`refunds`** / RMA — 🆕

## A5. Users & access

- Replace `users.isAdmin` with **roles & permissions** (`spatie/laravel-permission`)
- **`user_addresses`** — add `receiver_name`, `phone`, `is_default_shipping`,
  `is_default_billing`; fix the broken `->nullable()` chain
- **`users`** — add `phone`, `avatar`, `last_login_at`, `is_active`
- **`activity_log`** (spatie) — 🆕 · **`settings`** — 🆕

---

# Appendix B — Category taxonomy

## Four concepts not to confuse

Four different things, four different tables. Mixing them up is the most common modelling mistake:

| Concept | Question it answers | Example | Creates a new SKU? |
|---|---|---|---|
| **Category** | What *is* it? (navigation tree) | Laptops → Gaming Laptops | No |
| **Brand** | Who made it? | Asus, Casio, Apple | No |
| **Attribute** | Which *version* of it? | RAM 16GB, Case 41mm, Colour Black | **Yes** — makes variants |
| **Specification** | What are its details? | CPU i7-13700H, Release Year 2024, Water Resist 50m | No |

> **Release year is a specification, not a category.** Add `release_year` as a column on
> `products` if you want to filter/sort by it (common for computers — "2024 model"), otherwise
> store it as a `product_specifications` row.
>
> It can't be a category because a product belongs to **one** place in the tree — a 2024 gaming
> laptop would have to live in both "Gaming Laptops" and "2024" at once.

## Proposed tree

Nested via `categories.parent_id`:

```
Computers
├── Laptops ──── Gaming · Business · Ultrabook · 2-in-1
├── Desktops ─── Gaming PC · All-in-One · Mini PC · Workstation
└── Tablets

Computer Components
├── Processors (CPU)
├── Graphics Cards (GPU)
├── Memory (RAM)
├── Storage ──── SSD · HDD · NVMe
├── Motherboards
├── Power Supplies
├── Cases
└── Cooling

Accessories
├── Keyboards
├── Mice
├── Monitors
├── Headsets
├── Webcams
├── Docking Stations
├── Cables & Adapters
└── Laptop Bags

Watches
├── Smartwatches
├── Analog
├── Digital
├── Luxury
└── Watch Accessories ─── Straps · Chargers

Audio
├── Headphones
├── Earbuds
└── Speakers

Networking ─── Routers · Switches · WiFi Adapters
Phones & Accessories
Printers & Scanners
Gaming ─── Consoles · Controllers
Software & Licenses
```

Keep the tree **2–3 levels deep at most**. Deeper trees hurt navigation and slow filtering queries.

## Attributes per category

Attributes generate variants, so they differ by category:

| Category | Attributes (make SKUs) | Specifications (descriptive only) |
|---|---|---|
| Laptops | RAM, Storage, Colour | CPU, GPU, Screen size, Resolution, Battery, Ports, Weight, OS, Release year |
| Desktops | RAM, Storage, GPU tier | CPU, Motherboard, PSU wattage, Case type |
| Monitors | Size, Panel | Resolution, Refresh rate, Response time, Ports |
| Keyboards | Switch type, Layout, Colour | Connection, Backlight, Key count |
| Watches | Case size, Strap material, Colour | Movement, Water resistance, Crystal, Battery life, Release year |
| Smartwatches | Case size, Band, Colour | OS compat, Sensors, Battery, GPS, Display type |
| Storage | Capacity, Interface | Read/write speed, Endurance, Form factor |

---

# Appendix C — Packages

### Installed

| Package | Purpose | Phase |
|---|---|---|
| `spatie/laravel-permission` | roles & permissions | 2 |
| `spatie/laravel-activitylog` | admin audit trail | 2 |
| `spatie/laravel-translatable` | JSON translatable columns (EN/KM) | 1 |
| `spatie/laravel-query-builder` | API filtering, sorting, includes | 4 |
| `inertiajs/inertia-laravel` | admin panel | 1 |
| `laravel/sanctum` | customer API auth | 4 |
| `tightenco/ziggy` | route names in Vue | 1 |

### Planned but not installed

| Package | Status |
|---|---|
| `spatie/laravel-sluggable` | **Not used.** Replaced by the hand-rolled `GeneratesSlug` trait in `app/Models/Concerns/` — fewer moving parts for one behaviour |
| `spatie/laravel-medialibrary` + `intervention/image` | **Not installed.** Images go through plain `Storage`; no thumbnails, no WebP. See [Phase 3](#-phase-3--catalog-admin) |
| `barryvdh/laravel-dompdf` | Phase 8 — invoices & delivery notes |

```bash
# Not needed at personal-shop scale — revisit only if you outgrow them
#   maatwebsite/excel     report export      → a SQL query covers it
#   laravel/scout         + Meilisearch      → add a FULLTEXT index first (Phase 4)
#   laravel/horizon       queue monitoring   → only once queues are busy
```

**Admin frontend:** `package.json` carries only Vue 3, Inertia, Tailwind, Flowbite, Vite and
axios. The three packages originally planned here — **TipTap** (rich text), a **chart library**,
and **`vuedraggable`** (reordering) — were never installed; the dashboard chart and the image
drag-reorder are hand-rolled instead. Add them only if the hand-rolled versions start costing
more than the dependency would.

**Storefront frontend:** a separate project consuming `/api/v1`. Not covered by this roadmap.

---

# Appendix D — Cross-cutting concerns

| Concern | Recommendation | Phase |
|---|---|---|
| **Images** | ⚠️ Plain `Storage`, no resizing — full-size originals are served to the storefront. `medialibrary` + `intervention/image` deferred | 3 → 10 |
| **Search** | ✅ MySQL FULLTEXT over a generated column, with a LIKE fallback for short terms. `laravel/scout` + Meilisearch only if the catalog grows | 4 |
| **Payments** | Cambodia: ABA PayWay, Bakong KHQR, Wing, plus COD. Stripe/PayPal if selling abroad. Abstract behind a `PaymentGateway` interface | 6–7 |
| **Currency** | USD + KHR if selling locally — **decide early**, it touches every price column | 1 |
| **Language** | EN + KM if local — **decide early**, it changes column design (JSON translatable vs separate tables) | 1 |
| **Email** | Order confirmation, shipped, delivered, cancelled, password reset, low-stock alert. Configure real SMTP | 8 |
| **Queues** | Switch `QUEUE_CONNECTION` off `sync` — every email currently blocks the request | 8 |
| **SEO** | Slug routes, `sitemap.xml`, meta tags, Open Graph, JSON-LD Product schema | 10 |
| **Security** | Rate-limit login & checkout, `APP_DEBUG=false` in prod, validate all uploads, authorization on every admin route | 2, 10 |
| **Performance** | Eager-load to kill N+1, cache category tree + settings, `CACHE_DRIVER=redis`, index `slug`/`sku`/`status`/FKs, paginate everywhere | 10 |
| **Testing** | Feature tests for cart, checkout, order creation, stock decrement | 5–6, 10 |

## Application layer conventions

- **Form Requests** — one per create/update action. **7 exist**
  (`Admin\{Brand,Category,Product}Request`, `Api\{Login,Register}Request`, `Auth\LoginRequest`,
  `ProfileUpdateRequest`); roughly **25** needed by Phase 6
- **Policies** — ✅ **all four written** and auto-discovered: `ProductPolicy`, `OrderPolicy`,
  `ReviewPolicy`, `CouponPolicy`. Route-level `can:` middleware still handles the coarse admin
  gating; the policies carry the per-record rules on top of it. Super admin short-circuits
  every one of them via `Gate::before`
- **API Resources** — 6 exist, all catalog-facing. Extend for cart, order and account payloads
- **Service classes** — keep controllers thin:

| Class | Responsibility | Phase |
|---|---|---|
| `ProductService` ✅ | Create/update with variants, specs, images; duplicate | 3 |
| `CartService` | Add/update/remove, **merge guest cart into user cart on login** | 5 |
| `CheckoutService` | Validate stock → reserve → create order → payment | 6 |
| `PricingService` | Variant price, coupon, tax, shipping | 6 |
| `InventoryService` | Decrement on order, restock on cancel | 6 |
| `OrderNumberGenerator` | Sequential human-readable order numbers | 6 |

## API conventions

The storefront API is the contract with a separate frontend, so keep it predictable:

| Concern | Convention |
|---|---|
| **Versioning** | `/api/v1/*`. Breaking changes get a `v2`, they don't mutate `v1` |
| **Filtering** | `spatie/laravel-query-builder` — `filter[brand]=asus&sort=-price&include=brand,images` |
| **Payloads** | Always an API Resource, never a raw model — resources are what stop an internal column leaking into a public response |
| **Locale** | `?lang=km` or `Accept-Language`, resolved by `SetApiLocale` against `config('app.supported_locales')` |
| **Pagination** | `per_page`, capped server-side (100 on products) |
| **Money** | Computed server-side, always. A client-supplied total is never trusted |
| **Auth** | Sanctum tokens. Admin stays session-based Inertia — the two never mix |

---

# Appendix E — Decisions (settled)

| # | Decision | Answer | Consequence |
|---|---|---|---|
| 1 | **Market** | 🇰🇭 **Cambodia only** | USD + KHR dual pricing (fixed rate, not full FX). Gateways: ABA PayWay, Bakong KHQR, Wing, COD. Shipping zones = Phnom Penh vs provinces. **No** international tax/shipping matrix |
| 2 | **Language** | **English + Khmer** | Product/category/brand text columns are **JSON translatable** (`spatie/laravel-translatable`). Admin gets per-locale input tabs. `config/app.php` locale `en`, fallback `en` |
| 3 | **Variants** | **Yes** | `product_variants` + `attributes` + `attribute_values` as designed. Price and stock live on the variant, not the product |
| 4 | **Skeleton** | **Migrated** ✅ | Laravel 12 structure — `bootstrap/app.php` + `bootstrap/providers.php`. Done in Phase 1 |
| 5 | **Storefront delivery** | **Separate API client** 🔀 | *Changed after Phase 3.* The storefront is no longer Inertia pages in this app. This app is the **admin panel + JSON API**; the customer UI is a separate frontend against `/api/v1`. Consequences: Phases 4, 5 and 9 ship endpoints rather than screens · payload shaping moves to API Resources · guest carts need a client-held token instead of a session cookie · SEO (Phase 10) becomes the frontend's problem, not this repo's |
| 6 | **Slugs** | **Hand-rolled** | `GeneratesSlug` trait instead of `spatie/laravel-sluggable` |

### Translatable columns

These use `json` instead of `string` / `text`:

| Table | Columns |
|---|---|
| `brands` | `name`, `description` |
| `categories` | `name`, `description` |
| `products` | `title`, `short_description`, `description`, `meta_title`, `meta_description` |
| `attributes` | `name` |
| `attribute_values` | `label` |
| `product_specifications` | `group`, `key`, `value` |
| `tags` | `name` |
| `shipping_methods` | `name`, `description` |
| `settings` | `value` (where the setting is user-facing text) |

> **Not translatable:** `slug` (single canonical URL), `sku`, and anything numeric or enum.

### Currency approach

Prices are stored **in USD** as the base. KHR is a display conversion driven by a rate in the
`currencies` table. Orders snapshot both `currency` and `exchange_rate` at placement, so a later
rate change never rewrites historical order totals.

---

---

## Where things actually stand

### ✅ Closed

| Item | From | How |
|---|---|---|
| Product / Variant / Order factories | 1.7 | 7 factories with states; they back all 126 tests |
| No catalog-admin tests | 3 | 53 tests — products, images, categories, brands, inventory |
| No policies | 2 → 3 | 4 policies, auto-discovered, 11 tests |
| Customer auth endpoints | 4 | Sanctum register/login/logout, admins refused, 13 tests |
| Search was an unindexed, case-sensitive `LIKE` | 4 | FULLTEXT over a generated column, 10 tests |

### ⬜ Still open

| # | Item | From | Why it matters |
|---|---|---|---|
| 1 | **Guest cart identity** | 5 | The API has no session cookie to lean on. Settle the cart-token scheme *before* writing `CartService` — it decides the whole endpoint contract |
| 2 | **Catalog endpoint tests** | 4 | Search and auth are covered; products/categories/brands are not |
| 3 | **Category tree · filter metadata · home feed · settings** | 4 | The remaining Phase 4 endpoints |
| 4 | **No image resizing** | 3 → 10 | Full-size originals are served; the biggest payload win available |
| 5 | **`QUEUE_CONNECTION=sync`, mailpit, `APP_DEBUG=true`, CORS** | 8, 10 | Config, not code — all four block launch, and none should be flipped before deploy |

*Last updated: 2026-08-17 — reconciled against the codebase, then updated as each gap closed.*
