I'm in **Plan mode** — I'll present the complete implementation plan incorporating all your confirmed choices. No files will be changed until you toggle to Act mode.

---

## Implementation Plan: Alabiq Multi-Restaurant Food Delivery Marketplace

### Confirmed Decisions

| Decision | Value |
|---|---|
| **Frontend Stack** | React + TypeScript + Inertia.js |
| **CSS Framework** | Bootstrap 5 (Courier theme adapted) — NOT Tailwind |
| **Logo** | `main-logo.png` from ALABIQ root |
| **Base Branch** | `main` → create feature branch from it |
| **Database** | MySQL 8.4 (shared hosting compatible), migrations driver-aware |
| **Testing** | Unit testing integrated into every phase (PHPUnit 11) |

---

### 1. Branch Strategy

Per the git rule, I will **not** work directly on `main`:

```bash
git checkout main
git pull origin main
git checkout -b feat/alabiq-marketplace
```

All work happens on `feat/alabiq-marketplace`. When complete, you'll open a PR to merge into `main`.

---

### 2. Frontend Architecture (Inertia.js + React + TypeScript + Bootstrap 5)

The existing ALABIQ uses Blade + Tailwind. We're adding Inertia.js + React + TypeScript on top, with Bootstrap 5 for the Courier theme styling.

**New dependencies to add:**
- `@inertiajs/react`, `@inertiajs/laravel`
- `react`, `react-dom`, `typescript`
- `bootstrap@5`, `@popperjs/core`
- `@types/react`, `@types/react-dom`, `@types/bootstrap`
- `vite-plugin-php` (or standard Laravel Vite setup)

**Directory structure:**

```
resources/
├── css/
│   └── app.css              (Bootstrap 5 + Courier theme overrides + ALABIQ brand tokens)
├── js/
│   ├── app.ts               (Inertia + React bootstrap)
│   ├── inertia.ts           (Inertia client config)
│   ├── components/
│   │   ├── ui/              (shadcn-style reusable components built on Bootstrap 5)
│   │   ├── layout/
│   │   │   ├── GuestLayout.tsx
│   │   │   ├── CustomerLayout.tsx
│   │   │   ├── RestaurantLayout.tsx
│   │   │   ├── RiderLayout.tsx
│   │   │   └── SuperAdminLayout.tsx
│   │   └── common/
│   ├── pages/
│   │   ├── marketplace/     (Public marketplace)
│   │   │   ├── Home.tsx
│   │   │   ├── RestaurantShow.tsx
│   │   │   └── Search.tsx
│   │   ├── customer/        (Customer dashboard)
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Orders.tsx
│   │   │   ├── Cart.tsx
│   │   │   ├── Addresses.tsx
│   │   │   └── Profile.tsx
│   │   ├── restaurant/      (Restaurant dashboard)
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Orders.tsx
│   │   │   ├── Menu.tsx
│   │   │   └── Profile.tsx
│   │   ├── rider/           (Rider dashboard)
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Deliveries.tsx
│   │   │   └── Tracking.tsx
│   │   ├── admin/           (Super Admin dashboard)
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Restaurants.tsx
│   │   │   ├── Riders.tsx
│   │   │   └── Reports.tsx
│   │   └── shared/
│   │       ├── NotFound.tsx
│   │       └── Error.tsx
│   ├── lib/
│   │   ├── axios.ts         (API client with Sanctum token)
│   │   ├── utils.ts
│   │   └── validation.ts    (Zod schemas)
│   └── hooks/
│       ├── useAuth.ts
│       ├── useCart.ts
│       └── useMapbox.ts
routes/
├── web.php                  (Inertia SSR entry points)
└── api.php                  (Existing API, extended)
```

---

### 3. Backend Architecture (Existing ALABIQ + Extensions)

The existing Laravel 11 API at `routes/api.php` already has controllers, models, services, and migrations. We extend it:

**New Controllers:**
- `MarketplaceController` — public restaurant listing, search, featured
- `RestaurantStorefrontController` — `/restaurants/{slug}` public page
- `CustomerController` — customer profile, addresses, orders
- `RestaurantDashboardController` — restaurant order management, menu management
- `RiderController` — rider profile, availability, deliveries, location updates
- `AdminController` — super admin dashboard, reports, user management
- `PaymentController` — Tap Payments abstraction, webhooks
- `MapboxController` — geocoding, directions, coverage checks
- `NotificationController` — in-app notifications

**New Services:**
- `MarketplaceService` — restaurant discovery, search, filtering
- `CartService` — one-restaurant-per-cart enforcement, snapshot pricing
- `OrderService` — order state machine, status transitions, transactions
- `DispatchService` — rider assignment (manual initially)
- `PaymentService` — gateway abstraction (Tap Payments first)
- `LedgerService` — double-entry accounting
- `CoverageService` — delivery zone checking
- `LocationService` — Mapbox geocoding, directions
- `NotificationService` — multi-channel (in-app, email, SMS)

**New Policies:**
- `RestaurantPolicy`, `OrderPolicy`, `MenuItemPolicy`, `RiderPolicy`, `UserPolicy`

---

### 4. Database Extensions (New Migrations)

All driver-aware (PostgreSQL + MySQL/MariaDB):

| Migration | Table | Purpose |
|---|---|---|
| `create_restaurant_branches` | `restaurant_branches` | Multi-branch per restaurant |
| `create_restaurant_delivery_zones` | `restaurant_delivery_zones` | Coverage polygons, fees |
| `create_menu_item_option_values` | `menu_item_option_values` | Add-ons, sizes |
| `create_menu_item_availability` | `menu_item_availability` | Per-branch availability |
| `create_customer_addresses` | `customer_addresses` | Saved delivery addresses |
| `create_order_status_histories` | `order_status_histories` | State machine audit |
| `create_delivery_assignments` | `delivery_assignments` | Rider-order assignments |
| `create_payment_transactions` | `payment_transactions` | Gateway transactions |
| `create_payment_webhooks` | `payment_webhooks` | Webhook audit |
| `create_refunds` | `refunds` | Refund records |
| `create_platform_commissions` | `platform_commissions` | Commission config |
| `create_restaurant_settlements` | `restaurant_settlements` | Payout records |
| `create_ledger_entries` | `ledger_entries` | Double-entry accounting |
| `create_rider_locations` | `rider_locations` | Live GPS (TTL-based) |
| `create_rider_availability` | `rider_availability` | Online/offline/busy |
| `create_delivery_tracking_events` | `delivery_tracking_events` | Tracking milestones |
| `create_coupons` | `coupons` | Discount codes |
| `create_promotions` | `promotions` | Featured promotions |
| `create_reviews` | `reviews` | Ratings |
| `create_notifications` | `notifications` | In-app notifications |
| `create_support_tickets` | `support_tickets` | Support |
| `create_audit_logs` | `audit_logs` | Sensitive ops audit |
| `create_system_settings` | `system_settings` | Platform config |

---

### 5. Unit Testing Plan (Integrated Per Phase)

Each phase delivers code **plus** unit tests. Testing stack: PHPUnit 11 + Mockery + Faker (already in `composer.json`).

```
tests/Unit/
├── Services/
│   ├── VatServiceTest.php
│   ├── FoodMoneyServiceTest.php
│   ├── CartServiceTest.php
│   ├── OrderServiceTest.php
│   ├── PaymentServiceTest.php
│   ├── DispatchServiceTest.php
│   ├── CoverageServiceTest.php
│   └── LedgerServiceTest.php
├── Policies/
│   ├── RestaurantPolicyTest.php
│   ├── OrderPolicyTest.php
│   ├── MenuItemPolicyTest.php
│   └── RiderPolicyTest.php
├── Validation/
│   ├── OrderStatusTransitionTest.php
│   └── CartValidationTest.php
├── Jobs/
│   ├── OrderNotificationJobTest.php
│   └── DispatchSweepTest.php
└── Helpers/
    └── MoneyHelperTest.php
```

**Coverage targets:** Services 90%+, Policies 100%, Models 80%+, Overall 80%+

---

### 6. Phase-by-Phase Implementation Plan

#### Phase 1 — Foundation (Weeks 1–2)
- Create feature branch from `main`
- Install Inertia.js + React + TypeScript + Bootstrap 5
- Configure Vite with React plugin and TypeScript
- Set up shared layout components (Guest, Customer, Restaurant, Rider, SuperAdmin)
- Integrate `main-logo.png` as the platform logo across all layouts
- Adapt Courier theme Bootstrap 5 styles into Tailwind-compatible CSS (or pure Bootstrap 5)
- Database migrations: `restaurant_branches`, `restaurant_delivery_zones`, `menu_item_option_values`, `menu_item_availability`, `customer_addresses`
- Models: `RestaurantBranch`, `RestaurantDeliveryZone`, `MenuItemOptionValue`, `MenuItemAvailability`, `CustomerAddress`
- Auth middleware for Inertia (Sanctum + role-based guards)
- Unit tests: `VatServiceTest`, `FoodMoneyServiceTest`, `RestaurantPolicyTest`, `OrderPolicyTest`
- **Gate:** `php artisan test --testsuite=Unit` green, `php artisan serve` loads Inertia root page

#### Phase 2 — Public Marketplace (Weeks 3–4)
- `MarketplaceController` + `RestaurantStorefrontController`
- Public pages: Home (hero, search, featured restaurants, categories, popular items, testimonials), `/restaurants/{slug}` storefront
- Restaurant search & filtering (keyword, cuisine, delivery fee, rating)
- Menu browsing with categories, items, add-ons, customization
- Customer address management (save, geocode fallback, manual pin)
- Delivery coverage check before checkout
- Unit tests: `MarketplaceServiceTest`, `CartServiceTest`, `CoverageServiceTest`, `MenuItemPolicyTest`
- **Gate:** Restaurant storefront loads dynamically from DB, cart enforces one restaurant

#### Phase 3 — Ordering (Weeks 5–6)
- Cart: add/update/remove items, one-restaurant enforcement, snapshot pricing at checkout
- Checkout flow: delivery address, payment method, order summary
- Order state machine: Pending Payment → Placed → Accepted → Preparing → Ready → Rider Assigned → Picked Up → On the Way → Delivered / Cancelled / Refunded
- All transitions validated on backend
- Restaurant order management: accept/reject, update prep status
- Order status history audit trail
- Unit tests: `OrderServiceTest`, `OrderStatusTransitionTest`, `CartValidationTest`, `RestaurantOrderManagementTest`
- **Gate:** Complete order flow works end-to-end, invalid transitions return 409

#### Phase 4 — Payments (Weeks 7–8)
- Payment gateway abstraction layer (`PaymentService`, `PaymentGateway` interface)
- Tap Payments sandbox integration (mada, Visa, Mastercard, Apple Pay)
- Cash on Delivery as configurable option
- Webhook handling with signature validation
- Idempotent transaction processing
- Refunds
- Commission calculation: platform share, restaurant share, delivery fee allocation
- Double-entry ledger (`ledger_entries`)
- Unit tests: `PaymentServiceTest`, `LedgerServiceTest`, `CommissionCalculationTest`, `RefundTest`
- **Gate:** Sandbox payment flow works, webhooks verified, ledger balances

#### Phase 5 — Maps & Delivery (Weeks 9–11)
- Mapbox GL JS integration (maps, geocoding, directions)
- Customer location selection: browser geolocation, address search, manual pin
- Restaurant location display, distance calculation, coverage zone check
- Rider dashboard: availability toggle, delivery assignments, accept/reject
- Live GPS tracking: rider sends location, broadcasts via Pusher, customer tracking page updates in real-time
- Rider location throttling (movement-based, TTL in Redis)
- Unit tests: `DispatchServiceTest`, `RiderLocationTest`, `DistanceCalculationTest`, `EtaServiceTest`
- **Gate:** Rider location broadcasts in real-time, customer map shows rider marker moving

#### Phase 6 — Administration & Reporting (Weeks 12–13)
- Super Admin dashboard: platform statistics, user management, restaurant approval, rider approval
- Commission management, delivery fee configuration
- Restaurant analytics: sales reports, earnings, settlements
- Rider reporting: earnings, delivery history
- Platform settings: VAT, timezone, currency, feature flags
- Audit logs
- Unit tests: `PlatformStatsTest`, `SalesReportTest`, `RiderEarningsTest`, `SettlementTest`, `SystemSettingsTest`
- **Gate:** Super admin can manage all restaurants/riders, reports are accurate and isolated

#### Phase 7 — Testing & Production (Weeks 14–15)
- Full test suite: `php artisan test` (Unit + Feature)
- Security review: authorization bypass, SQL injection, XSS, CSRF
- Performance: N+1 detection, query optimization, Redis caching
- Docker deploy configuration for shared hosting
- Environment variable documentation
- Coverage report generation (target 80%+)
- **Gate:** All tests green, coverage targets met, Docker build succeeds

---

### 7. Key File Changes

| File | Change |
|---|---|
| `composer.json` | Add Inertia.js Laravel package |
| `package.json` | Add React, TypeScript, Bootstrap 5, Inertia React |
| `vite.config.js` | Add React plugin, TypeScript support, Bootstrap 5 entry |
| `routes/web.php` | Add Inertia SSR routes for all dashboards |
| `resources/js/app.ts` | Inertia + React bootstrap |
| `resources/css/app.css` | Bootstrap 5 + Courier theme + ALABIQ brand colors |
| `config/alabiq.php` | Add payment, commission, Mapbox, notification config |
| `database/migrations/*` | New tables (21 migrations) |
| `app/Policies/*` | New policies (5 policies) |
| `app/Services/*` | New services (9 services) |
| `app/Http/Controllers/*` | New controllers (9 controllers) |
| `tests/Unit/*` | Unit tests for every phase |

---

### 8. Bootstrap 5 + Courier Theme Adaptation

The Courier theme is Bootstrap 5-based. We will:
1. Install Bootstrap 5 via npm (not CDN, for production reliability)
2. Extract Courier's SCSS variables (colors, spacing, typography) and map them to Bootstrap 5 Sass variables
3. Import Courier's component SCSS (buttons, cards, nav, testimonials, hero sections) into the Vite build
4. Use ALABIQ's brand colors from `main-logo.png` (red/orange brand) as the primary palette
5. Build React components that wrap Bootstrap 5 classes (e.g., `<Button variant="primary">`, `<Card>`, `<Navbar>`)
6. RTL support via Bootstrap 5 RTL or logical CSS properties for Arabic
7. All layouts are responsive (mobile-first for customer ordering)

---

### 9. Immediate Next Step

Once you toggle to **Act mode**, I will:

1. Verify we're on `main` and pull latest
2. Create feature branch `feat/alabiq-marketplace`
3. Install Inertia.js + React + TypeScript + Bootstrap 5 dependencies
4. Configure Vite and Laravel for Inertia SSR
5. Create the base layout components with `main-logo.png` integration
6. Run `php artisan test` to confirm the existing test suite still passes
7. Begin Phase 1 migrations and models

**Please toggle to Act mode to begin implementation.**