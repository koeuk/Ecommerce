# E-Commerce Build Roadmap

**Project:** Computer, electronics accessories & watch store
**Stack:** Laravel 10 · Inertia · Vue 3 · Tailwind + Flowbite · Vite · MySQL
**Status:** Early scaffold — schema drafted, admin shell partially built, no business logic yet

---

## Table of contents

1. [Blockers to fix first](#0-blockers-to-fix-before-anything-new)
2. [Database changes](#1-database--tables-to-add-or-change)
3. [Backend application layer](#2-backend--application-layer)
4. [Admin dashboard pages](#3-admin-dashboard--pages-to-build)
5. [Storefront pages](#4-storefront--pages-to-build)
6. [Cross-cutting concerns](#5-cross-cutting-concerns)
7. [Packages](#6-packages-worth-adding)
8. [Build order](#7-suggested-build-order)
9. [Open decisions](#8-open-decisions)

---

## The core problem

The current `products` table is **flat** — one price, one `quantity`. Selling laptops and watches
means:

- **Variants** — 16GB/512GB vs 32GB/1TB, 41mm vs 45mm case, strap colour
- **Heavy spec data** — CPU, GPU, screen, battery, movement type, water resistance

Everything below assumes variants and specs get modelled properly first.

---

## 0. Blockers to fix before anything new

| Issue | File |
|---|---|
| Admin login/logout point to wrong controller → 500 | `routes/web.php:44-45` |
| `route('home')` doesn't exist → exception on access denial | `app/Http/Middleware/AdminMiddleware.php:20` |
| `cart_items.product_id` commented out | `database/migrations/2024_10_26_152334_create_cart_items_table.php:20` |
| `order_items.product_id` commented out | `database/migrations/2024_10_26_152419_create_order_items_table.php:19` |
| `Product::cartItem()` uses key `cartItem_id` (should be `product_id`) | `app/Models/Product.php:14` |
| `SoftDeletes` trait missing despite `softDeletes()` column | `app/Models/Product.php` |
| `products` table mixes `created_by` with `update_by` / `delete_by` | products migration |
| `->nullable()` chained after `->on()` — no effect | `user_addresses` migration:26 |

> **Recommendation:** since the project is pre-launch, **rewrite the 2024 migrations in place**
> rather than stacking `ALTER` migrations. Cleaner than carrying the mistakes forward.

---

## 1. Database — tables to add or change

### 1.1 Category taxonomy — and four concepts not to confuse

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

#### Proposed category tree

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

Keep the tree **2–3 levels deep at most**. Deeper trees hurt navigation and make filtering
queries slower.

#### Attributes per category

Attributes are what generate variants, so they differ by category:

| Category | Attributes (make SKUs) | Specifications (descriptive only) |
|---|---|---|
| Laptops | RAM, Storage, Colour | CPU, GPU, Screen size, Resolution, Battery, Ports, Weight, OS, Release year |
| Desktops | RAM, Storage, GPU tier | CPU, Motherboard, PSU wattage, Case type |
| Monitors | Size, Panel | Resolution, Refresh rate, Response time, Ports |
| Keyboards | Switch type, Layout, Colour | Connection, Backlight, Key count |
| Watches | Case size, Strap material, Colour | Movement, Water resistance, Crystal, Battery life, Release year |
| Smartwatches | Case size, Band, Colour | OS compat, Sensors, Battery, GPS, Display type |
| Storage | Capacity, Interface | Read/write speed, Endurance, Form factor |

### 1.2 Catalog

**`categories`** — add
`parent_id` (self FK, for Computers → Laptops → Gaming Laptops), `description`, `image`, `icon`,
`is_active`, `sort_order`

**`brands`** — add
`logo`, `description`, `is_active`, `sort_order`
*(Asus, Dell, Apple, Lenovo, Casio, Seiko, Citizen…)*

**`products`** — add
`sku`, `short_description`, `compare_at_price` (strike-through), `cost_price`,
**`warranty_months`** ← important for electronics, `weight`, `length` / `width` / `height`
(shipping calc), `is_featured`, `condition` (new / refurbished / used), `meta_title`,
`meta_description`, `views_count`, `rating_avg`, `rating_count`, `status` enum

**`product_variants`** — 🆕 **the critical one**
`product_id`, `sku`, `price`, `compare_at_price`, `stock_quantity`, `image_id`, `is_default`,
`low_stock_threshold`, `allow_backorder`

**`attributes`** + **`attribute_values`** — 🆕
RAM, Storage, Colour, Case Size, Strap Material, Screen Size

**`product_variant_attribute_values`** — 🆕
Pivot linking a variant to its option combination

**`product_specifications`** — 🆕
`product_id`, `group` (Processor / Display / Battery), `key`, `value`, `sort_order`
This is your spec sheet.

**`product_images`** — add
`sort_order`, `is_primary`, `alt_text`, `variant_id`

**`product_tags`** + pivot — 🆕

**`reviews`** — 🆕
`user_id`, `product_id`, `order_id`, `rating`, `title`, `body`, `is_verified_purchase`,
`status` (moderation), `admin_reply`

**`review_images`** — 🆕 *(optional)*

**`wishlists`** — 🆕

**`product_views`** / recently-viewed — 🆕 *(optional)*

### 1.3 Inventory

**`inventory_movements`** — 🆕
Audit trail: `in` / `out` / `adjustment` / `return`, quantity, reason, reference

**`product_serials`** — 🆕 *(optional)*
Serial / IMEI per unit — genuinely useful for warranty claims on computers

### 1.4 Promotions & pricing

**`coupons`** — 🆕
`code`, `type` (fixed/percent), `value`, `min_order`, `usage_limit`, `per_user_limit`,
`starts_at`, `expires_at`, `applies_to` (all/category/brand/product)

**`coupon_usages`** — 🆕

**`flash_sales`** / campaign pricing — 🆕 *(optional)*

**`tax_rates`** — 🆕

**`currencies`** + exchange rates — 🆕 *(if selling in USD **and** KHR)*

### 1.5 Cart & checkout

**`cart_items`** — add
`product_variant_id`, `session_id` (guest carts), `price_at_add`; keep `user_id` nullable

**`orders`** — add
`order_number` (human-readable, e.g. `ORD-20260816-0001`), `subtotal`, `discount_total`,
`tax_total`, `shipping_fee`, `grand_total`, `currency`, `payment_status`, `fulfillment_status`,
`coupon_id`, **`shipping_address` as JSON snapshot**, `billing_address` JSON, `customer_note`,
`admin_note`, `placed_at`, `shipped_at`, `delivered_at`, `cancelled_at`

> ⚠️ **Snapshot the address as JSON.** If you only FK to `user_addresses` and the customer edits
> that address later, your order history silently rewrites itself.

**`order_items`** — add
`product_variant_id`, plus **snapshots**: `product_name`, `sku`, `variant_label`, `unit_price`,
`quantity`, `subtotal`. Same reasoning as above.

**`order_status_histories`** — 🆕
Who changed status, when, note

**`shipping_methods`** + **`shipping_zones`** — 🆕
Phnom Penh vs provinces, flat vs weight-based

**`payments`** — add
`transaction_id`, `gateway`, `gateway_payload` JSON, `paid_at`, `refunded_at`

**`refunds`** / returns (RMA) — 🆕
Expect these with electronics

### 1.6 Users & access

- Replace `users.isAdmin` boolean with **roles & permissions**
  (`spatie/laravel-permission`): super-admin, manager, staff, customer
- **`user_addresses`** — add `receiver_name`, `phone`, `is_default_shipping`,
  `is_default_billing`; fix the broken `->nullable()` chain
- **`users`** — add `phone`, `avatar`, `last_login_at`, `is_active`
- **`activity_log`** (spatie) — 🆕 admin audit trail
- **`settings`** — 🆕 store name, logo, contact, social, currency, tax mode

---

## 2. Backend — application layer

### Models

All 10 models are currently empty. Each needs `$fillable`, `$casts`, and relationships. Plus:

- Slug generation (sluggable package or a `boot()` hook)
- Scopes: `published()`, `inStock()`, `featured()`, `filter()`
- Accessors: `final_price`, `discount_percent`, `primary_image_url`, `is_low_stock`

### Controllers

All 10 are empty stubs and **none are routed**. You need two separate sets:

- `App\Http\Controllers\Admin\*` — full CRUD
- `App\Http\Controllers\Shop\*` — storefront read + cart/checkout

### Form Requests

One per create/update action. Currently 2 exist; roughly **25** needed.

### Policies

`ProductPolicy`, `OrderPolicy`, `ReviewPolicy`, `CouponPolicy`, etc.

### Service / Action classes

Keep controllers thin:

| Class | Responsibility |
|---|---|
| `CartService` | Add/update/remove, **merge guest cart into user cart on login** |
| `CheckoutService` | Validate stock → reserve → create order → payment |
| `PricingService` | Variant price, coupon, tax, shipping |
| `InventoryService` | Decrement on order, restock on cancel |
| `OrderNumberGenerator` | Sequential human-readable order numbers |

### Events / Listeners / Jobs

`OrderPlaced`, `OrderStatusChanged`, `PaymentSucceeded`, `LowStockDetected`
→ send mail, decrement stock, notify admin

> ⚠️ **Switch `QUEUE_CONNECTION` off `sync`** in `.env`. Right now every email blocks the request.

### API Resources

Needed now for shaping Inertia payloads, and later for a mobile app.

---

## 3. Admin dashboard — pages to build

The current `Admin/Dashboard.vue` is placeholder dashed boxes, and every sidebar link is `href="#"`.

- [ ] **Overview** — revenue today/week/month, orders by status, low-stock alerts, top products, recent orders, sales chart
- [ ] **Products** — list (search / filter / bulk actions); create/edit with rich text description, **variant matrix generator**, drag-drop multi-image upload with reorder, spec-sheet builder, SEO fields, duplicate product
- [ ] **Categories** — nested tree with drag-reorder
- [ ] **Brands**
- [ ] **Attributes & values**
- [ ] **Tags**
- [ ] **Inventory** — stock levels, low-stock view, bulk adjust, movement history
- [ ] **Orders** — list with status/date/payment filters; detail page (items, customer, addresses, timeline, payment); status transitions; **printable invoice + packing slip**; refund
- [ ] **Customers** — list, detail with order history and lifetime value
- [ ] **Coupons** — CRUD + usage stats
- [ ] **Reviews** — moderation queue, approve/reject, reply
- [ ] **Reports** — sales by period / category / brand, best sellers, low performers, CSV export
- [ ] **Settings** — store info, shipping methods & zones, tax, payment gateways, currency, email templates
- [ ] **Staff & roles** — user management, role assignment
- [ ] **Activity log**

---

## 4. Storefront — pages to build

Currently **zero storefront** exists.

- [ ] **Home** — hero/banner slider, featured categories, deals, new arrivals, best sellers, brand strip
- [ ] **Category / listing** — **faceted filters are essential here**: brand, price range, CPU, RAM, storage, screen size, case size, movement type, in-stock, rating. Plus sort, pagination, grid/list toggle
- [ ] **Search** — autocomplete dropdown, results page, "no results" suggestions
- [ ] **Product detail** — image gallery with zoom, variant picker (disable unavailable combos), price + stock, **spec table**, warranty info, add to cart / buy now, reviews, related & "frequently bought with"
- [ ] **Compare** — side-by-side spec comparison. High value for computers; a real differentiator in this category
- [ ] **Cart** — quantity update, coupon field, totals summary, stock re-validation
- [ ] **Checkout** — guest + logged-in, address form, shipping method, payment method, order review
- [ ] **Order confirmation** + **order tracking** (by number, guests included)
- [ ] **Account** — dashboard, orders + detail, addresses, wishlist, my reviews, profile/password
- [ ] **Static pages** — about, contact, FAQ, warranty policy, return policy, shipping policy, privacy, terms

---

## 5. Cross-cutting concerns

| Concern | Recommendation |
|---|---|
| **Images** | `spatie/laravel-medialibrary` + `intervention/image` — thumbnails, WebP conversion. Product photos are your heaviest asset |
| **Search** | Start with MySQL FULLTEXT; move to `laravel/scout` + Meilisearch as the catalog grows |
| **Payments** | Cambodia: **ABA PayWay**, **Bakong KHQR**, Wing, plus **Cash on Delivery**. Stripe/PayPal if selling abroad. Abstract behind a `PaymentGateway` interface so new gateways don't touch checkout |
| **Currency** | USD + KHR if selling locally — **decide early**, it touches every price column |
| **Language** | EN + KM if local — **decide early**, it changes column design (JSON translatable vs separate tables) |
| **Email** | Order confirmation, shipped, delivered, cancelled, password reset, low-stock admin alert. `MAIL_HOST=mailpit` is dev-only — configure real SMTP |
| **SEO** | Slug routes, `sitemap.xml`, meta tags, Open Graph, JSON-LD Product schema (gets rich results with price/rating) |
| **Security** | Rate-limit login & checkout, `APP_DEBUG=false` in prod, validate all uploads, authorization on every admin route |
| **Performance** | Eager-load to kill N+1, cache category tree and settings, `CACHE_DRIVER=redis`, index `slug`/`sku`/`status`/FK columns, paginate everywhere |
| **Testing** | Feature tests at minimum for cart, checkout, order creation, stock decrement |

---

## 6. Packages worth adding

```bash
composer require \
  spatie/laravel-permission \    # roles & permissions
  spatie/laravel-medialibrary \  # product images
  spatie/laravel-activitylog \   # admin audit trail
  spatie/laravel-sluggable \     # slugs
  intervention/image \           # thumbnails / WebP
  barryvdh/laravel-dompdf \      # invoices & packing slips
  maatwebsite/excel              # product import / report export

# later
composer require laravel/scout laravel/horizon
```

**Frontend:** a rich text editor (TipTap), a chart library for the dashboard,
and `vuedraggable` for image/category reordering.

---

## 7. Suggested build order

| # | Phase | Notes |
|---|---|---|
| 1 | **Fix blockers** + rewrite migrations with the full schema | Foundation |
| 2 | **Roles & permissions** (replace `isAdmin`), working admin login | |
| 3 | **Catalog admin** — brands → categories → attributes → products with variants + images | Biggest chunk |
| 4 | **Storefront catalog** — home, listing with filters, product detail | |
| 5 | **Cart** — guest + user, with merge on login | |
| 6 | **Checkout + orders** — COD first | Needs no gateway; unblocks the whole flow |
| 7 | **Payment gateway** integration | |
| 8 | **Order management admin** + emails + invoices | |
| 9 | **Customer account**, reviews, wishlist | |
| 10 | **Reports, coupons, compare, search polish, SEO** | |

---

## 8. Open decisions

Three questions that change the schema — worth settling before step 1:

1. **Market** — Cambodia only, or international?
   *Drives currency, payment gateways, shipping model.*

2. **Language** — English only, or EN + KM?
   *Retrofitting translations later is painful.*

3. **Variants** — confirmed needed?
   *If every product is a single fixed SKU you can skip `product_variants` entirely and save
   significant complexity — but for laptops and watches, assume you need them.*

---

*Last updated: 2026-08-16*
