# E-Commerce Build Roadmap

**Project:** Computer, electronics accessories & watch store — **personal, single shop**
**Stack:** Laravel 12.66 · PHP 8.4 · Inertia 2 · Vue 3 · Tailwind + Flowbite · Vite · MySQL
**Status:** Phases 1–2 complete · 41 tables · 26 models · 39 tests passing · next up **Phase 3**

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
| ✅ | [1](#-phase-1--cleanup--foundation) | Cleanup & Foundation | Clean codebase, correct schema — *models/seeders outstanding* |
| ✅ | [2](#-phase-2--roles-permissions--admin-auth) | Roles, Permissions & Admin Auth | Admin can log in; `isAdmin` retired |
| ⬜ | [3](#-phase-3--catalog-admin) | Catalog Admin | Products with variants, specs & images manageable |
| ⬜ | [4](#-phase-4--storefront-catalog) | Storefront Catalog | Customers can browse, filter and view products |
| ⬜ | [5](#-phase-5--cart) | Cart | Guest + user carts that survive login |
| ⬜ | [6](#-phase-6--checkout--orders-cod) | Checkout & Orders (COD) | **First real order can be placed** |
| ⬜ | [7](#-phase-7--payments) | Payments *(optional)* | One gateway live — COD-only is viable |
| ⬜ | [8](#-phase-8--order-management-admin) | Order Management Admin | You can fulfil orders end to end |
| ⬜ | [9](#-phase-9--customer-account) | Customer Account | Order history, addresses, wishlist |
| ⬜ | [10](#-phase-10--polish) | Polish | SEO, performance, security, backups |

**Legend:** ✅ done · 🔄 partial · ⬜ not started · `[~]` skipped deliberately

Each unbuilt phase lists **Core** (build it) and **Skip for now** (with reasons).

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

> **Goal:** you can manage the products you actually sell. The biggest phase — budget accordingly.

Wire the existing Flowbite sidebar links to real routes as you go.

### Core — build this

- [ ] **Products** — the centrepiece:
  - [ ] List: search, filter by category/brand/status
  - [ ] Create/edit: title, description, price, stock, warranty, condition, release year
  - [ ] **Variant matrix generator** — pick attributes, auto-generate SKU rows with price + stock
  - [ ] Drag-drop multi-image upload with reorder + primary flag
  - [ ] Spec-sheet builder (grouped key/value rows)
  - [ ] Duplicate product — the fastest way to add your 40th similar item
- [ ] **Categories** — nested tree CRUD ([Appendix B](#appendix-b--category-taxonomy))
- [ ] **Brands** — CRUD, logo upload
- [ ] **Media** — `spatie/laravel-medialibrary` + `intervention/image`, thumbnails, WebP
- [ ] **Inventory** — stock levels + low-stock view

### Skip for now

| Skipped | Why | Fallback |
|---|---|---|
| Attributes & values admin UI | 7 attributes, 35 values, already seeded. You'll change them maybe twice a year | Edit the seeder, re-run it |
| Tags admin UI | Same — low churn | Seeder |
| Bulk actions | Useful at 500 products, not at 50 | Edit individually |
| Drag-reorder on the category tree | A `sort_order` number field does the same job | Number input |
| `inventory_movements` history UI | The rows still get written; you just don't browse them | Query the table if you ever need it |

**✅ Done when:** you can create a gaming laptop with 4 RAM/storage variants, 8 images and a
20-row spec sheet, and see correct per-variant stock.

---

# ⬜ Phase 4 — Storefront Catalog

> **Goal:** customers can browse and evaluate products. No cart yet.

### Core — build this

- [ ] Storefront layout — header, nav from category tree, footer, mobile menu
- [ ] **Home** — hero banner, featured categories, new arrivals, best sellers
- [ ] **Category / listing** — filters that matter for electronics: **brand, price range, in-stock**.
      Plus sort and pagination
- [ ] **Product detail** — image gallery, variant picker (disable unavailable combos),
      price + stock, **spec table**, warranty info, related products
- [ ] **Search** — MySQL FULLTEXT, results page
- [ ] **Static pages** — about, contact, warranty, returns, shipping policy

### Skip for now

| Skipped | Why |
|---|---|
| Deep spec faceting (CPU, RAM, screen size as filters) | Needs specs promoted to indexed columns. Brand + price covers most of the value at your catalog size |
| Search autocomplete | The results page is enough under a few hundred products |
| Recently viewed | Pure nice-to-have |
| Grid/list toggle | Pick one layout |
| Banner slider | One static hero image converts about as well and is far less code |

**✅ Done when:** a customer can filter to "Laptops, Asus, under $1500" and open a product with a
working variant picker.

---

# ⬜ Phase 5 — Cart

> **Goal:** guest and logged-in carts that behave correctly across login.

- [ ] **`CartService`** — add / update / remove, and **merge guest cart into user cart on login**
      (the part that's easy to get wrong)
- [ ] Cart page — quantity update, totals, stock re-validation
- [ ] Mini-cart dropdown in the header
- [ ] Cart count shared via `HandleInertiaRequests`

> Schema is already done — `cart_items` has `product_variant_id`, `session_id` and `price_at_add`.

**✅ Done when:** add to cart as a guest, log in, and the items are still there — with no duplicates.

---

# ⬜ Phase 6 — Checkout & Orders (COD)

> **Goal:** **the first real order can be placed.** Cash on Delivery only — no gateway
> integration needed, which unblocks the entire flow.

### Core — build this

- [ ] **Checkout flow** — guest + logged-in, address form, shipping method, COD, order review
- [ ] **`CheckoutService`** — validate stock → create order → decrement stock
- [ ] **`PricingService`** — variant price, shipping, totals
- [ ] **`InventoryService`** — decrement on order, restock on cancel
- [ ] `OrderNumberGenerator` — `ORD-20260816-0001`
- [ ] Order confirmation page + order tracking by number (guests included)
- [ ] Customer address book (create/edit/default)

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

> **Goal:** you can fulfil orders end to end.

### Core — build this

- [ ] **Orders admin** — list with status/date filters; detail page (items, customer, address,
      timeline); status transitions
- [ ] **Invoices + delivery notes** — `barryvdh/laravel-dompdf`
- [ ] **Emails** — order confirmation, shipped, delivered
- [ ] ⚠️ **Switch `QUEUE_CONNECTION` off `sync`** — every email currently blocks the request.
      Tables already exist; it's a one-line `.env` change
- [ ] Configure real SMTP (`MAIL_HOST=mailpit` is dev-only)
- [ ] **Dashboard** — replace the placeholder boxes: today's revenue, orders by status,
      low-stock alerts, recent orders

> `OrderStatus` already encodes its own legal transitions and stock-release rule, so the admin
> just calls `canTransitionTo()`.

### Skip for now

| Skipped | Why |
|---|---|
| Customers admin (list, lifetime value) | You can see who ordered from the order itself |
| Reports module + CSV export | A few SQL queries when you're curious beats a reporting UI |
| Sales charts | Same |
| Activity log UI | Rows still get written; browse the table if needed |

**✅ Done when:** an order can go placed → confirmed → shipped → delivered, with the customer
emailed at each step and a printable invoice.

---

# ⬜ Phase 9 — Customer Account

> **Goal:** returning customers can see their history.

- [ ] **Account** — order list + detail, addresses, profile/password
- [ ] **Wishlist** — cheap to add, genuinely used

### Skip for now

| Skipped | Why |
|---|---|
| Reviews + moderation queue | Real work (submission, verified-purchase check, moderation UI, rating aggregation) for something a new shop has almost none of. The tables are there when you want it |
| Review images | Same |

---

# ⬜ Phase 10 — Polish

> **Goal:** make it fast and findable. Do these before launch, not after.

- [ ] **SEO** — slug routes, `sitemap.xml`, meta tags, Open Graph, JSON-LD Product schema
      (gets rich results with price and rating in Google)
- [ ] **Performance** — eager-load to kill N+1, cache the category tree and settings,
      `CACHE_DRIVER=redis`, paginate everywhere
- [ ] **Security** — `APP_DEBUG=false`, rate-limit checkout, validate all uploads
- [ ] Feature tests: cart, checkout, order creation, stock decrement
- [ ] Backups — a nightly `mysqldump` is enough

### Skip for now

| Skipped | Why |
|---|---|
| Coupons admin UI | `Coupon::discountFor()` is written. Add codes via tinker until you run real promotions |
| Product compare | High value at scale, low at 50 products |
| Meilisearch / Scout | MySQL FULLTEXT handles thousands of products |
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

```bash
# Phase 1–3
composer require \
  spatie/laravel-permission \    # roles & permissions        (Phase 2)
  spatie/laravel-activitylog \   # admin audit trail          (Phase 2)
  spatie/laravel-sluggable \     # slugs                      (Phase 1)
  spatie/laravel-medialibrary \  # product images             (Phase 3)
  intervention/image             # thumbnails / WebP          (Phase 3)

# Phase 8
composer require barryvdh/laravel-dompdf   # invoices & delivery notes

# Not needed at personal-shop scale — revisit only if you outgrow them
#   maatwebsite/excel     report export      → a SQL query covers it
#   laravel/scout         + Meilisearch      → MySQL FULLTEXT handles thousands of products
#   laravel/horizon       queue monitoring   → only once queues are busy
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
