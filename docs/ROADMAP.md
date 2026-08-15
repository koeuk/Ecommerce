# E-Commerce Build Roadmap

**Project:** Computer, electronics accessories & watch store
**Stack:** Laravel 12.66 · PHP 8.4 · Inertia 2 · Vue 3 · Tailwind + Flowbite · Vite · MySQL
**Status:** Phases 1–2 complete · 41 tables · 39 tests passing · next up **Phase 3 — Catalog Admin**

> **Carried debt:** ~24 catalog models and all catalog seeders (§1.6 / §1.7) are still unwritten.
> Phase 3 is blocked on them.

---

## Table of contents

**Build phases**

| | Phase | Title | Outcome |
|---|---|---|---|
| ✅ | [1](#-phase-1--cleanup--foundation) | Cleanup & Foundation | Clean codebase, correct schema — *models/seeders outstanding* |
| ✅ | [2](#-phase-2--roles-permissions--admin-auth) | Roles, Permissions & Admin Auth | Admin can log in; `isAdmin` retired |
| ⬜ | [3](#-phase-3--catalog-admin) | Catalog Admin | Products with variants, specs & images manageable |
| ⬜ | [4](#-phase-4--storefront-catalog) | Storefront Catalog | Customers can browse, filter and view products |
| ⬜ | [5](#-phase-5--cart) | Cart | Guest + user carts that survive login |
| ⬜ | [6](#-phase-6--checkout--orders-cod) | Checkout & Orders (COD) | **First real order can be placed** |
| ⬜ | [7](#-phase-7--payments) | Payments | Online payment gateways live |
| ⬜ | [8](#-phase-8--order-management-admin) | Order Management Admin | Staff can fulfil orders end to end |
| ⬜ | [9](#-phase-9--customer-account--engagement) | Customer Account & Engagement | Accounts, wishlist, reviews |
| ⬜ | [10](#-phase-10--growth--polish) | Growth & Polish | Coupons, reports, compare, SEO |

**Legend:** ✅ done · 🔄 partial · ⬜ not started · `[~]` skipped deliberately

**Reference**

- [Appendix A — Database schema](#appendix-a--database-schema)
- [Appendix B — Category taxonomy](#appendix-b--category-taxonomy)
- [Appendix C — Packages](#appendix-c--packages)
- [Appendix D — Cross-cutting concerns](#appendix-d--cross-cutting-concerns)
- [Appendix E — Decisions (settled)](#appendix-e--decisions-settled)

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
- **`HandleInertiaRequests`** — extend it later to share cart count, auth user, store settings
- Vite / Tailwind / PostCSS config
- `Welcome.vue` and `Dashboard.vue` — become the storefront home and account dashboard

> **Already fine, no action:** `.env` is correctly gitignored and untracked ✓ ·
> `Kernel.php:5` properly imports the `redirectAdmin` middleware ✓

### 🔄 1.6 Build the model layer

- [x] `GeneratesSlug` trait — unique slug from the English value on create
- [x] **Brand**, **Category** (self-referencing tree), **Tag** — `HasTranslations`, relations, scopes
- [x] **User** — `HasRoles`, soft deletes, relations, `isAdmin()`
- [x] **CartItem**, **Product** — relations fixed *(still need full `$fillable` / `$casts`)*
- [ ] **~24 remaining models** — ProductVariant, Attribute, AttributeValue, ProductImage,
      ProductSpecification, Order, OrderItem, OrderStatusHistory, Payment, Refund, RefundItem,
      UserAddress, Review, ReviewImage, Wishlist, Coupon, CouponUsage, CouponTarget, Currency,
      TaxRate, Setting, ShippingZone, ShippingMethod, InventoryMovement
- [ ] Scopes: `published()`, `inStock()`, `featured()`, `filter()`
- [ ] Accessors: `final_price`, `discount_percent`, `primary_image_url`, `is_low_stock`

### 🔄 1.7 Seeders & factories

- [x] **RoleSeeder** — 4 roles, 56 permissions
- [x] **DatabaseSeeder** — 3 sample accounts (admin / manager / customer, password `password`)
- [x] **UserFactory** — with `unverified()` and `inactive()` states
- [ ] **Catalog seeders** — brands, the category tree, attributes, ~50 products with variants,
      specs and images. Everything in Phase 3 and 4 needs data to look at
- [ ] Factories for Product, ProductVariant, Order

**✅ Done when:** `migrate:fresh --seed` produces a full catalog, and every model relationship
resolves in `tinker`.

---

# ✅ Phase 2 — Roles, Permissions & Admin Auth

> **Goal:** admin can actually log in. Right now the route points at a missing method.

- [x] Install `spatie/laravel-permission`
- [x] Roles: `super-admin`, `manager`, `staff`, `customer`
- [x] **Drop `users.isAdmin`** — replaced by roles
- [~] ~~Merge `AdminAuthController` + `AdminController`~~ — **skipped deliberately.** The route→method
      mismatch that motivated this was fixed in Phase 1; merging auth with dashboard rendering
      would put two unrelated concerns in one class. Route bindings ✅
- [x] Update `AdminMiddleware` to check role, not the boolean; fix the `route('home')` redirect
- [x] `users` table: add `phone`, `avatar`, `last_login_at`, `is_active`
- [ ] Policies scaffolded: `ProductPolicy`, `OrderPolicy`, `ReviewPolicy`, `CouponPolicy` — **deferred to Phase 3**, they need the catalog models
- [x] Rate-limit the admin login route (5/min per email + IP)
- [x] Install `spatie/laravel-activitylog` for the admin audit trail

**✅ Done —** verified by 14 tests in `tests/Feature/Admin/`. Beyond the original scope, the login
flow now also rejects deactivated accounts and tears down the session for authenticated
non-admins rather than leaving them logged in.

> **Note:** `User` is soft-deleted so past orders keep their customer attribution. Trade-off — a
> deleted email still occupies the unique index, so that person cannot re-register until the row
> is restored or purged.

---

# ⬜ Phase 3 — Catalog Admin

> **Goal:** you can manage the full product catalog. The biggest phase — budget accordingly.

Wire the existing Flowbite sidebar links to real routes as you go.

- [ ] **Brands** — CRUD, logo upload, active toggle, sort order
- [ ] **Categories** — nested tree CRUD with drag-reorder ([Appendix B](#appendix-b--category-taxonomy))
- [ ] **Attributes & values** — RAM, Storage, Colour, Case Size, Strap Material
- [ ] **Tags** — CRUD
- [ ] **Products** — the centrepiece:
  - [ ] List: search, filter by category/brand/status, bulk actions
  - [ ] Rich text description (TipTap)
  - [ ] **Variant matrix generator** — pick attributes, auto-generate SKU rows with price + stock
  - [ ] Drag-drop multi-image upload with reorder + primary flag
  - [ ] Spec-sheet builder (grouped key/value rows)
  - [ ] SEO fields, warranty months, condition, release year
  - [ ] Duplicate product
- [ ] **Media** — `spatie/laravel-medialibrary` + `intervention/image`, thumbnails, WebP
- [ ] **Inventory** — stock levels, low-stock view, bulk adjust, `inventory_movements` history

**✅ Done when:** you can create a gaming laptop with 4 RAM/storage variants, 8 images, a
20-row spec sheet, and see correct per-variant stock.

---

# ⬜ Phase 4 — Storefront Catalog

> **Goal:** customers can browse and evaluate products. No cart yet.

- [ ] Storefront layout — header, nav from category tree, footer, mobile menu
- [ ] **Home** — hero/banner slider, featured categories, deals, new arrivals, best sellers, brand strip
- [ ] **Category / listing** — **faceted filters are essential here**: brand, price range, CPU,
      RAM, storage, screen size, case size, movement type, in-stock, rating. Plus sort,
      pagination, grid/list toggle
- [ ] **Product detail** — image gallery with zoom, variant picker (disable unavailable combos),
      price + stock, **spec table**, warranty info, related products
- [ ] **Search** — MySQL FULLTEXT to start, autocomplete dropdown, results page, no-results suggestions
- [ ] Recently viewed
- [ ] **Static pages** — about, contact, FAQ, warranty, returns, shipping, privacy, terms

**✅ Done when:** a customer can filter to "Gaming Laptops, Asus, 16GB RAM, under $1500" and
open a product with a working variant picker.

---

# ⬜ Phase 5 — Cart

> **Goal:** guest and logged-in carts that behave correctly across login.

- [ ] `cart_items`: add `product_variant_id`, `session_id` (guest), `price_at_add`; `user_id` nullable
- [ ] **`CartService`** — add / update / remove, and **merge guest cart into user cart on login**
      (the part that's easy to get wrong)
- [ ] Cart page — quantity update, totals summary, stock re-validation
- [ ] Mini-cart dropdown in the header
- [ ] Cart count shared via `HandleInertiaRequests`

**✅ Done when:** add to cart as a guest, log in, and the items are still there — with no duplicates.

---

# ⬜ Phase 6 — Checkout & Orders (COD)

> **Goal:** **the first real order can be placed.** Cash on Delivery only — no gateway
> integration needed, which unblocks the entire flow.

- [ ] `user_addresses`: add `receiver_name`, `phone`, `is_default_shipping`, `is_default_billing`
- [ ] `shipping_methods` + `shipping_zones` — Phnom Penh vs provinces, flat or weight-based
- [ ] `tax_rates`
- [ ] **Checkout flow** — guest + logged-in, address form, shipping method, payment method
      (COD), order review
- [ ] **`CheckoutService`** — validate stock → reserve → create order → confirm
- [ ] **`PricingService`** — variant price, tax, shipping, totals
- [ ] **`InventoryService`** — decrement on order, restock on cancel
- [ ] `OrderNumberGenerator` — `ORD-20260816-0001`
- [ ] **Snapshot** shipping/billing address as JSON on the order, and product name/SKU/price on
      order items — see [Appendix A](#a4-cart--checkout) for why
- [ ] `order_status_histories`
- [ ] Order confirmation page + order tracking by number (guests included)

**✅ Done when:** a guest completes checkout, stock decrements, and the order appears with a
correct total.

---

# ⬜ Phase 7 — Payments

> **Goal:** take money online.

- [ ] Abstract a `PaymentGateway` interface so new gateways never touch checkout code
- [ ] **Cambodia:** ABA PayWay, Bakong KHQR, Wing
- [ ] **International:** Stripe / PayPal (only if selling abroad — see [Appendix E](#appendix-e--open-decisions))
- [ ] `payments`: add `transaction_id`, `gateway`, `gateway_payload` JSON, `paid_at`, `refunded_at`
- [ ] Webhook handling + signature verification
- [ ] Payment status transitions, failed-payment retry
- [ ] `refunds` / RMA table — expect returns with electronics

**✅ Done when:** a real (sandbox) payment completes and the webhook flips the order to paid.

---

# ⬜ Phase 8 — Order Management Admin

> **Goal:** staff can fulfil orders end to end.

- [ ] **Orders admin** — list with status/date/payment filters; detail page (items, customer,
      addresses, timeline, payment); status transitions; refund
- [ ] **Invoices + packing slips** — `barryvdh/laravel-dompdf`
- [ ] **Customers admin** — list, detail with order history and lifetime value
- [ ] **Emails** — order confirmation, shipped, delivered, cancelled, low-stock admin alert
- [ ] Events → listeners → jobs: `OrderPlaced`, `OrderStatusChanged`, `PaymentSucceeded`,
      `LowStockDetected`
- [ ] ⚠️ **Switch `QUEUE_CONNECTION` off `sync`** — right now every email blocks the request
- [ ] Configure real SMTP (`MAIL_HOST=mailpit` is dev-only)
- [ ] **Dashboard overview** — replace the placeholder boxes: revenue today/week/month, orders by
      status, low-stock alerts, top products, recent orders, sales chart

**✅ Done when:** an order can go placed → paid → shipped → delivered, with the customer emailed
at each step and a printable invoice.

---

# ⬜ Phase 9 — Customer Account & Engagement

- [ ] **Account** — dashboard, order list + detail, addresses CRUD, profile/password
- [ ] **Wishlist**
- [ ] **Reviews** — submit (verified-purchase flag from order history), review images
- [ ] **Review moderation admin** — approve/reject queue, admin reply
- [ ] Rating aggregation back onto `products.rating_avg` / `rating_count`

**✅ Done when:** a customer who received an order can review it, and the rating shows on the
product page after approval.

---

# ⬜ Phase 10 — Growth & Polish

- [ ] **Coupons** — CRUD, usage limits, per-user limits, scoped to category/brand/product
- [ ] **Reports** — sales by period / category / brand, best sellers, low performers, CSV export
- [ ] **Compare** — side-by-side spec comparison. High value for computers; a real differentiator
- [ ] **Search upgrade** — `laravel/scout` + Meilisearch once the catalog outgrows FULLTEXT
- [ ] **SEO** — slug routes, `sitemap.xml`, meta tags, Open Graph, JSON-LD Product schema
      (gets rich results with price/rating)
- [ ] **Performance** — eager-load to kill N+1, cache category tree + settings,
      `CACHE_DRIVER=redis`, index `slug`/`sku`/`status`/FK columns
- [ ] **Settings admin** — store info, shipping, tax, gateways, currency, email templates
- [ ] `laravel/horizon` for queue monitoring
- [ ] Flash sales / campaign pricing
- [ ] Feature tests: cart, checkout, order creation, stock decrement
- [ ] `APP_DEBUG=false` in production

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

```bash
# Phase 1–3
composer require \
  spatie/laravel-permission \    # roles & permissions        (Phase 2)
  spatie/laravel-activitylog \   # admin audit trail          (Phase 2)
  spatie/laravel-sluggable \     # slugs                      (Phase 1)
  spatie/laravel-medialibrary \  # product images             (Phase 3)
  intervention/image             # thumbnails / WebP          (Phase 3)

# Phase 8+
composer require \
  barryvdh/laravel-dompdf \      # invoices & packing slips   (Phase 8)
  maatwebsite/excel              # import / report export     (Phase 10)

# Phase 10
composer require laravel/scout laravel/horizon
```

**Frontend:** TipTap (rich text editor), a chart library for the dashboard,
`vuedraggable` (image + category reordering).

---

# Appendix D — Cross-cutting concerns

| Concern | Recommendation | Phase |
|---|---|---|
| **Images** | `spatie/laravel-medialibrary` + `intervention/image` — thumbnails, WebP. Product photos are your heaviest asset | 3 |
| **Search** | MySQL FULLTEXT first; `laravel/scout` + Meilisearch as the catalog grows | 4 → 10 |
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

- **Form Requests** — one per create/update action. Currently 2 exist; roughly **25** needed
- **Policies** — `ProductPolicy`, `OrderPolicy`, `ReviewPolicy`, `CouponPolicy`
- **API Resources** — for shaping Inertia payloads now, and a mobile app later
- **Service classes** — keep controllers thin:

| Class | Responsibility | Phase |
|---|---|---|
| `CartService` | Add/update/remove, **merge guest cart into user cart on login** | 5 |
| `CheckoutService` | Validate stock → reserve → create order → payment | 6 |
| `PricingService` | Variant price, coupon, tax, shipping | 6 |
| `InventoryService` | Decrement on order, restock on cancel | 6 |
| `OrderNumberGenerator` | Sequential human-readable order numbers | 6 |

---

# Appendix E — Decisions (settled)

| # | Decision | Answer | Consequence |
|---|---|---|---|
| 1 | **Market** | 🇰🇭 **Cambodia only** | USD + KHR dual pricing (fixed rate, not full FX). Gateways: ABA PayWay, Bakong KHQR, Wing, COD. Shipping zones = Phnom Penh vs provinces. **No** international tax/shipping matrix |
| 2 | **Language** | **English + Khmer** | Product/category/brand text columns are **JSON translatable** (`spatie/laravel-translatable`). Admin gets per-locale input tabs. `config/app.php` locale `en`, fallback `en` |
| 3 | **Variants** | **Yes** | `product_variants` + `attributes` + `attribute_values` as designed. Price and stock live on the variant, not the product |
| 4 | **Skeleton** | **Migrated** ✅ | Laravel 12 structure — `bootstrap/app.php` + `bootstrap/providers.php`. Done in Phase 1 |

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

*Last updated: 2026-08-16*
