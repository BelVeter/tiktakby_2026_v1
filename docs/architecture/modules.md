# TikTak.by — Modular Monolith Architecture

> Designed for Laravel 12 rewrite. Each module is a self-contained bounded context with its own Models, Services, Actions, DTOs, Events, and Policies. Modules communicate through well-defined interfaces (contracts) and Laravel Events — never through direct database queries across module boundaries.

---

## Module Dependency Map

```
                    ┌──────────┐
                    │   Core   │  (Offices, Announcements, Pages, Redirects)
                    └────┬─────┘
                         │
          ┌──────────────┼──────────────┐
          │              │              │
     ┌────▼────┐   ┌────▼────┐   ┌────▼────┐
     │   IAM   │   │ Catalog │   │  Audit  │
     └────┬────┘   └────┬────┘   └─────────┘
          │              │
     ┌────┼──────────────┼──────────────┐
     │    │              │              │
┌────▼────▼┐   ┌────────▼───┐   ┌─────▼──────┐
│ Inventory│   │  Pricing   │   │Communication│
└────┬─────┘   └────┬───────┘   └─────────────┘
     │              │
     └──────┬───────┘
            │
     ┌──────▼──────┐
     │     CRM     │
     └──────┬──────┘
            │
     ┌──────▼──────┐
     │   Rental    │ (Deals, Operations, Collateral, Delivery)
     └──────┬──────┘
            │
    ┌───────┼───────┐
    │       │       │
┌───▼──┐ ┌─▼──┐ ┌──▼───────┐
│Booking│ │Cart│ │ Carnival │
└──────┘ └────┘ └──────────┘
            │
     ┌──────▼──────┐
     │   Finance   │
     └─────────────┘
            │
     ┌──────▼──────┐
     │ Operations  │ (Shifts, Tasks, Payroll norms)
     └─────────────┘
```

---

## Folder Structure

```
app/
├── Modules/
│   │
│   ├── Core/
│   │   ├── Models/
│   │   │   ├── Office.php
│   │   │   ├── Announcement.php
│   │   │   ├── Page.php
│   │   │   ├── Redirect.php
│   │   │   ├── Subscription.php
│   │   │   └── LegalEntity.php
│   │   ├── Services/
│   │   │   └── OfficeScheduleService.php
│   │   ├── Actions/
│   │   ├── DTOs/
│   │   ├── Events/
│   │   ├── Policies/
│   │   ├── Contracts/           ← Public API for other modules
│   │   │   └── OfficeResolverInterface.php
│   │   └── Providers/
│   │       └── CoreServiceProvider.php
│   │
│   ├── IAM/
│   │   ├── Models/
│   │   │   ├── StaffUser.php
│   │   │   ├── Permission.php
│   │   │   ├── StaffSession.php
│   │   │   ├── IpAllowlist.php
│   │   │   ├── EmailSignature.php
│   │   │   └── User.php          ← Laravel auth (public-facing future)
│   │   ├── Services/
│   │   │   ├── AuthenticationService.php
│   │   │   └── AuthorizationService.php
│   │   ├── Actions/
│   │   │   ├── LoginStaffAction.php
│   │   │   └── CheckIpRestrictionAction.php
│   │   ├── Guards/
│   │   │   └── StaffGuard.php
│   │   ├── Middleware/
│   │   │   ├── StaffAuthenticated.php
│   │   │   └── CheckIpRestriction.php
│   │   ├── Contracts/
│   │   │   └── StaffResolverInterface.php
│   │   └── Providers/
│   │       └── IAMServiceProvider.php
│   │
│   ├── Catalog/
│   │   ├── Models/
│   │   │   ├── Section.php
│   │   │   ├── Subsection.php
│   │   │   ├── Category.php
│   │   │   ├── ProductModel.php
│   │   │   ├── ProductWebProfile.php
│   │   │   ├── ProductPhoto.php
│   │   │   ├── ProductCrossListing.php
│   │   │   ├── AttributeDefinition.php      ← EAV: attribute types
│   │   │   ├── AttributeEnumOption.php       ← EAV: enum choices
│   │   │   ├── ProductAttributeValue.php     ← EAV: model-level values
│   │   │   ├── VideoLink.php
│   │   │   └── Favorite.php
│   │   ├── Services/
│   │   │   ├── CatalogTreeService.php        ← Builds section→subsection→category tree
│   │   │   ├── ProductSearchService.php
│   │   │   ├── AttributeFilterService.php    ← Builds filter sidebar from EAV definitions
│   │   │   └── AvailabilityService.php       ← Checks item availability per model
│   │   ├── Actions/
│   │   │   ├── ResolveCatalogBreadcrumbsAction.php
│   │   │   └── SyncProductAttributesAction.php ← Saves EAV values for a product
│   │   ├── Enums/
│   │   │   ├── CategoryType.php              ← regular, carnival
│   │   │   └── AttributeDataType.php         ← integer, decimal, string, boolean, enum
│   │   ├── Contracts/
│   │   │   ├── ProductModelResolverInterface.php
│   │   │   └── CategoryResolverInterface.php
│   │   └── Providers/
│   │       └── CatalogServiceProvider.php
│   │
│   ├── Inventory/
│   │   ├── Models/
│   │   │   ├── InventoryItem.php
│   │   │   ├── InventoryDisposal.php
│   │   │   └── LastRentListing.php
│   │   ├── Services/
│   │   │   ├── InventoryService.php         ← Status transitions, availability
│   │   │   ├── InventoryTransferService.php ← Inter-office transfers
│   │   │   └── InventoryReportService.php   ← Age, count, utilization
│   │   ├── Actions/
│   │   │   ├── ReserveItemAction.php
│   │   │   ├── ReleaseItemAction.php
│   │   │   ├── TransferItemAction.php
│   │   │   └── DisposeItemAction.php
│   │   ├── Enums/
│   │   │   ├── ItemStatus.php               ← available, rented, reserved, in_delivery, unavailable, in_repair
│   │   │   ├── ItemCondition.php            ← normal, last_rent
│   │   │   └── DisposalReason.php           ← sold, no_return, written_off
│   │   ├── Events/
│   │   │   ├── ItemReserved.php
│   │   │   ├── ItemReleased.php
│   │   │   └── ItemTransferred.php
│   │   ├── Contracts/
│   │   │   └── InventoryResolverInterface.php
│   │   └── Providers/
│   │       └── InventoryServiceProvider.php
│   │
│   ├── Pricing/
│   │   ├── Models/
│   │   │   └── Tariff.php
│   │   ├── Services/
│   │   │   └── TariffCalculatorService.php  ← Core pricing engine
│   │   ├── DTOs/
│   │   │   ├── PriceQuote.php               ← Result: total, daily rate, tier info
│   │   │   └── TariffTier.php
│   │   ├── Contracts/
│   │   │   └── PricingEngineInterface.php
│   │   └── Providers/
│   │       └── PricingServiceProvider.php
│   │
│   ├── CRM/
│   │   ├── Models/
│   │   │   ├── Client.php
│   │   │   └── ClientHistory.php
│   │   ├── Services/
│   │   │   ├── ClientService.php
│   │   │   └── ClientMatchingService.php    ← Match phone/name to existing client
│   │   ├── Actions/
│   │   │   ├── CreateClientAction.php
│   │   │   └── UpdateClientAction.php       ← Auto-snapshots to history
│   │   ├── Contracts/
│   │   │   └── ClientResolverInterface.php
│   │   └── Providers/
│   │       └── CRMServiceProvider.php
│   │
│   ├── Rental/
│   │   ├── Models/
│   │   │   ├── RentalDeal.php
│   │   │   ├── RentalOperation.php
│   │   │   ├── Collateral.php
│   │   │   └── Delivery.php
│   │   ├── Services/
│   │   │   ├── RentalDealService.php        ← Create, extend, close deals
│   │   │   ├── RentalPaymentService.php     ← Record payments, recalculate
│   │   │   ├── OverdueManagementService.php ← Assign overdue deals to staff
│   │   │   └── DeliveryService.php
│   │   ├── Actions/
│   │   │   ├── CreateDealAction.php
│   │   │   ├── ExtendDealAction.php
│   │   │   ├── CloseDealAction.php
│   │   │   ├── RecordPaymentAction.php
│   │   │   └── ArchiveDealAction.php
│   │   ├── Enums/
│   │   │   ├── DealStatus.php               ← active, closed, closed_loss, closed_problem
│   │   │   ├── OperationType.php            ← first_rent, extension, return, close, payment, etc.
│   │   │   ├── OperationStatus.php          ← pending, for_courier, delivered, completed
│   │   │   ├── PaymentMethod.php            ← bank, card, cash_receipt, cash_no_receipt
│   │   │   └── DeliveryStatus.php           ← new, in_progress, done, failed
│   │   ├── Events/
│   │   │   ├── DealCreated.php
│   │   │   ├── DealExtended.php
│   │   │   ├── DealClosed.php
│   │   │   └── PaymentRecorded.php
│   │   ├── Contracts/
│   │   │   └── RentalServiceInterface.php
│   │   └── Providers/
│   │       └── RentalServiceProvider.php
│   │
│   ├── Booking/
│   │   ├── Models/
│   │   │   ├── Booking.php
│   │   │   └── BookingDateChange.php
│   │   ├── Services/
│   │   │   ├── BookingService.php           ← Create, approve, cancel bookings
│   │   │   └── WaitlistService.php          ← Manage zayavka / waitlist entries
│   │   ├── Actions/
│   │   │   ├── CreateBookingAction.php
│   │   │   ├── ApproveBookingAction.php
│   │   │   └── ConvertBookingToDealAction.php
│   │   ├── Enums/
│   │   │   ├── BookingType.php              ← confirmed, waitlist
│   │   │   ├── BookingPurpose.php           ← rental, delivery, repair, disposal, cleaning
│   │   │   └── BookingStatus.php            ← new, approved, completed, cancelled
│   │   ├── Events/
│   │   │   ├── BookingCreated.php
│   │   │   └── BookingApproved.php
│   │   └── Providers/
│   │       └── BookingServiceProvider.php
│   │
│   ├── Cart/
│   │   ├── Services/
│   │   │   ├── CartValidationService.php    ← Server-side price/availability validation
│   │   │   └── CartCheckoutService.php      ← Orchestrates checkout → bookings
│   │   ├── Actions/
│   │   │   └── ProcessCheckoutAction.php
│   │   └── Providers/
│   │       └── CartServiceProvider.php
│   │
│   ├── Carnival/
│   │   ├── Models/
│   │   │   ├── CarnivalBooking.php
│   │   │   ├── CarnivalOrder.php
│   │   │   ├── CarnivalBookingRequest.php
│   │   │   └── CarnivalPageSettings.php
│   │   ├── Services/
│   │   │   ├── CarnivalBookingService.php   ← Costume booking lifecycle
│   │   │   └── CarnivalScheduleService.php  ← Free period calculation
│   │   ├── Enums/
│   │   │   └── CarnivalBookingStatus.php    ← new, in_process, ok, cancelled
│   │   └── Providers/
│   │       └── CarnivalServiceProvider.php
│   │
│   ├── Communication/
│   │   ├── Models/
│   │   │   ├── CallbackRequest.php
│   │   │   └── FittingRequest.php
│   │   ├── Services/
│   │   │   ├── CallbackService.php
│   │   │   └── NotificationService.php      ← Email notifications (future: SMS, Telegram)
│   │   ├── Actions/
│   │   │   └── CreateCallbackAction.php
│   │   └── Providers/
│   │       └── CommunicationServiceProvider.php
│   │
│   ├── Finance/
│   │   ├── Models/
│   │   │   ├── IncomeCategory.php
│   │   │   ├── ExpenseCategory.php
│   │   │   ├── FinancialTransaction.php
│   │   │   └── CashRegisterDay.php
│   │   ├── Services/
│   │   │   ├── FinancialReportService.php   ← Sales by kassa, by category, by period
│   │   │   ├── CashRegisterService.php
│   │   │   └── RevenueService.php
│   │   ├── Contracts/
│   │   │   └── FinancialServiceInterface.php
│   │   └── Providers/
│   │       └── FinanceServiceProvider.php
│   │
│   ├── Operations/
│   │   ├── Models/
│   │   │   ├── WorkShift.php
│   │   │   ├── WorkHourNorm.php
│   │   │   └── Task.php
│   │   ├── Services/
│   │   │   ├── SchedulingService.php
│   │   │   └── TaskService.php
│   │   └── Providers/
│   │       └── OperationsServiceProvider.php
│   │
│   └── Audit/
│       ├── Models/
│       │   └── AuditLog.php
│       ├── Services/
│       │   └── AuditService.php
│       ├── Listeners/
│       │   └── LogAuditableEventListener.php
│       └── Providers/
│           └── AuditServiceProvider.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Web/                  ← Public website controllers
│   │   │   ├── HomeController.php
│   │   │   ├── CatalogController.php
│   │   │   ├── ProductController.php
│   │   │   ├── CartController.php
│   │   │   ├── CarnivalController.php
│   │   │   ├── PageController.php
│   │   │   ├── SearchController.php
│   │   │   └── CallbackController.php
│   │   └── Admin/                ← Admin panel controllers (API-driven SPA)
│   │       ├── DealController.php
│   │       ├── BookingController.php
│   │       ├── InventoryController.php
│   │       ├── ClientController.php
│   │       ├── TariffController.php
│   │       ├── CarnivalController.php
│   │       ├── FinanceController.php
│   │       ├── StaffController.php
│   │       ├── ReportController.php
│   │       └── SettingsController.php
│   │
│   └── Middleware/
│       ├── CheckRedirects.php
│       ├── StaffAuthenticated.php
│       └── ResolveLocale.php
│
└── Providers/
    └── ModuleServiceProvider.php  ← Registers all module providers
```

---

## Module Responsibilities Summary

| Module | Legacy Source | Responsibility |
|--------|-------------|----------------|
| **Core** | offices, announcement, pages, redirects, subscriptions, legalentity | Shared infrastructure: offices, CMS pages, announcements, redirects, legal entities, settings, tenants, exchange rates |
| **IAM** | logpass, permissions, user_permissions, logpass_track, logpass_wrong, allowed_ip, signature, users | Staff authentication, authorization, session tracking, IP restrictions |
| **Catalog** | razdel, sub_razdel, razdel_subrazdel, subrazdel_category, tovar_rent_cat, tovar_rent, rent_model_web, dop_photos, multi_web, video_links, tovar_properties, favorite_tovars | Product hierarchy, web presentation, EAV attributes, search, cross-listings. All translatable. |
| **Inventory** | tovar_rent_items, tovar_rent_items_arch, last_rent | Physical item management, status tracking, inter-office transfers, disposals |
| **Pricing** | rent_tarif_act, rent_tarif_prev | Tariff tiers, degressive pricing engine, price calculation |
| **CRM** | clients, clients_arch | Client database, contact info, change history |
| **Rental** | rent_deals_act, rent_deals_arch, rent_sub_deals_act, rent_sub_deals_arch, collateral, delivery | Full rental deal lifecycle: create, extend, pay, close, archive |
| **Booking** | rent_orders, rent_orders_arch, kb_change | Reservation/booking management, waitlists |
| **Cart** | CartController (new) | Web cart checkout orchestration |
| **Carnival** | karn_brons, karn_brons_arch, karnaval_zakaz, kb_zayavki, l3_karn_dop_fields | Costume rental with its own booking lifecycle |
| **Communication** | zvonki, primerki | Callback requests, fitting appointments, notifications |
| **Finance** | doh_items, rash_items, doh_rash, kassas, kassa_1, kassa_2 | Accounting, income/expense tracking, cash register management |
| **Operations** | work_shifts, info_work_hours_months, tasks | Staff scheduling, task management, payroll norms |
| **Audit** | users_log | Cross-module audit trail |

---

## Tables NOT Migrated (Candidates for Removal)

| Legacy Table | Reason |
|-------------|--------|
| `t` | Sensor data (temperature/humidity) — not a business entity |
| `deals` | Pre-restructured legacy deal format, replaced by `rent_deals_act` |
| `user_role` | Empty table, roles managed via `staff_users.role` |
| `kassa_1`, `kassa_2` | Hardcoded per-register tables, merged into `cash_register_days` |
| `failed_jobs` | Laravel framework table, auto-created by framework |
| `migrations` | Laravel framework table, auto-created by framework |
| `password_resets` | Laravel framework table, auto-created by framework |
| `personal_access_tokens` | Laravel Sanctum, auto-created by package |
| All `*_arch_deep` tables | Deep archive pattern eliminated — use date-based partitioning or cold storage instead |

---

## Key Architecture Decisions

### 1. Elimination of Active/Archive Table Pattern
The legacy system duplicates every major table (e.g., `rent_deals_act` / `rent_deals_arch` / `rent_deals_arch_deep`). The new schema uses:
- **Soft deletes** (`deleted_at`) for catalog entities
- **Status fields** + `archived_at` timestamps for deals and operations
- Single table with composite indexes (e.g., `(status, expected_return_at)`) — MySQL handles 500K+ rows trivially

### 2. Unix Timestamps → Proper Datetime
All legacy `int` timestamp columns are replaced with `DATETIME` / `TIMESTAMP` with timezone support.

### 3. Internationalization (i18n)
- All user-facing text uses **separate `_translations` tables** (via astrotomic/laravel-translatable)
- Non-translatable fields (images, slugs, config) stay on the base table
- Supports 3+ locales (ru, en, lt initially)
- Affects: sections, subsections, categories, product_models, product_web_profiles, pages, attribute_definitions, attribute_enum_options

### 4. EAV Product Attributes (Service-Agnostic)
Product characteristics (age, weight, power, size, etc.) are NOT hardcoded columns. Instead:
- **`attribute_definitions`** — admin-configurable attribute types (code, data_type, unit, is_filterable)
- **`category_attribute`** — which attributes apply to which categories
- **`product_attribute_values`** — actual values per product model (typed: value_text, value_numeric, value_boolean)
- **`inventory_item_attribute_values`** — item-level overrides (e.g., specific size for a physical unit)
- **`attribute_enum_options`** — predefined choices for enum-type attributes (e.g., sex: m/f/u)
- All attribute names, units, and enum labels are translatable

This makes the system reusable across child equipment, construction tools, or any rental domain without schema changes.

### 5. Product Models vs Web Profiles (Kept Separate)
- **`product_models`** = domain entity (what you rent: manufacturer, category, procurement data)
- **`product_web_profiles`** = presentation layer (SEO titles, images, descriptions, display config)
- Both have their own `_translations` tables
- Rationale: different services may have completely different web presentation for the same domain concept

### 6. Multi-Tenant Ready (Single-Tenant Start)
- Optional nullable `tenant_id` column on all tenant-scoped tables
- For initial deployment: leave `tenant_id = NULL` (single-tenant mode)
- `tenants` table exists but can have a single row or none
- Future: scope queries with `->where('tenant_id', $tenantId)` via global Eloquent scope
- No middleware or routing changes needed initially

### 7. Module Communication
- Modules expose **Contracts** (PHP interfaces) for cross-module queries
- State changes emit **Laravel Events** that other modules can listen to
- **All events are synchronous** (shared hosting has no queue worker)
- No direct Eloquent queries across module boundaries

### 8. Inventory Number as Business Key
The `inventory_number` (inv_n) remains a unique business identifier. The database PK is a standard auto-increment `id`, but `inventory_number` is indexed and used in all business operations. It is also denormalized onto `rental_deals` for historical reference.

### 9. Public-Facing IDs
No UUID columns in the database. Use **Hashids** at the application layer to encode integer IDs into short, URL-safe strings. Zero database cost, reversible, configurable per model.

### 10. Admin Panel Strategy
The legacy `bb/` admin is a standalone PHP application. The new system replaces it with:
- Laravel controllers under `Http/Controllers/Admin/`
- **Livewire** recommended for shared hosting (server-rendered, no separate JS build step)
- Alternative: Inertia.js + Vue (requires local `npm run build` + commit, same as current setup)
- Staff authentication via the IAM module (separate from public Laravel auth)

### 11. Shared Hosting Constraints
- **No Redis/Memcached** → use `database` driver for cache and sessions (tables included in schema)
- **No queue workers** → all jobs run synchronously; use `sync` queue driver
- **Cron**: single entry `* * * * * php artisan schedule:run` in cPanel for scheduled tasks
- **No npm on production** → build frontend locally, commit compiled assets
- **Deploy**: same pattern as current `Deploy.php` (git pull → composer install → migrate → cache)
