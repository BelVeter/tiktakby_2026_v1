# TikTak.by — Extracted Business Rules

> This document captures every significant business rule found in the legacy codebase. It serves as the "Docs as Code" foundation for the Laravel 12 rewrite. Each rule is traced back to its source file(s).
>
> **v2 note**: Product characteristics (age, weight, size, etc.) are now implemented as EAV (Entity-Attribute-Value) rather than hardcoded columns. See Section 17 for the new attribute system rules.

---

## 1. Pricing & Tariff Engine

**Source**: `bb/classes/TariffModel.php`, `bb/classes/Tariff.php`, `app/Http/Controllers/CartController.php`

### 1.1 Tariff Structure
- Each **product model** has multiple tariff tiers defined in `rent_tarif_act`.
- A tariff tier is defined by three dimensions:
  - **Period type** (`step`): `day`, `week`, or `month`
  - **Period count** (`kol_vo`): number of those periods (e.g., 2 weeks, 3 months)
  - **Total amount** (`rent_amount`): the total price for that tier
- The **per-period rate** (`rent_per_step`) is stored but also computable: `rent_amount / kol_vo`.

### 1.2 Day Calculation
Period types map to days as follows:
| Period | Days per unit |
|--------|--------------|
| day    | 1            |
| week   | 7            |
| month  | 30           |

So "3 weeks" = 21 days, "2 months" = 60 days.

### 1.3 Degressive Pricing Algorithm
When computing the price for an arbitrary number of days:

1. Convert all tariff tiers to `[days_threshold, daily_rate]` pairs where `daily_rate = total_amount / days`.
2. Sort ascending by `days_threshold`.
3. Walk through the tiers: for the requested day count, find the highest tier where `requested_days >= days_threshold`.
4. Use that tier's `daily_rate`.
5. Compute: `amount = requested_days * daily_rate`.

**Ceiling Rule**: After computing the amount, check all higher tiers. If the computed amount exceeds the total price of any higher tier (`tier_days * tier_daily_rate`), cap the amount at that ceiling. This prevents situations where renting for (e.g.) 25 days costs more than renting for 30 days.

**Example** (Model #1):
- 1 day: 11.00 BYN (11.00/day)
- 1 week (7 days): 12.00 BYN (1.71/day)
- 2 weeks (14 days): 13.00 BYN (0.93/day)
- 1 month (30 days): 15.00 BYN (0.50/day)
- For 10 days: tier = 1 week (daily rate 1.71), amount = 10 * 1.71 = 17.10, ceiling check against 2-week total (13.00) → capped at 13.00.

### 1.4 Tariff Rounding
When auto-calculating tariffs, amounts are rounded to the nearest 0.50 BYN ("round half euro"):
- Cents < 0.25 → round down to .00
- Cents >= 0.25 and < 0.75 → round to .50
- Cents >= 0.75 → round up to next .00

**Source**: `Tariff::roundHalfEur()`

### 1.5 Cheapest Tariff Display
For catalog listing cards, the system shows the cheapest per-day rate. This is computed as: `MIN(rent_amount / (kol_vo * sort_num))` across all tiers for a model.

**Source**: `TariffModel::getChippestTarifByModelId()`

---

## 2. Inventory Item Status Machine

**Source**: `bb/classes/tovar.php`, `bb/classes/bron.php`

### 2.1 Item Statuses
| Status | Meaning | Display Text |
|--------|---------|-------------|
| `to_rent` | Available for rental | "Свободен" (Free) |
| `t_bron` | Temporary hold (7 min) | "Свободен" (treated as free if expired) |
| `bron` | Reserved/booked | "Бронь" (Reserved) |
| `rented_out` | Currently rented | "На руках" (Rented out) |
| `to_deliver` | Queued for courier delivery | "Доставка" (Delivery) |
| `not_to_rent` | Excluded from rental | "Не для сдачи" |
| `repair` | In repair | "В ремонте" |

### 2.2 Status Transitions
```
to_rent ──┬──→ bron ──────→ rented_out ──→ to_rent
           │                    │
           ├──→ to_deliver ─────┘
           │
           └──→ t_bron ──→ (expires after 7 min) ──→ to_rent
                  │
                  └──→ bron (if confirmed in time)

bron ──→ to_rent (if booking cancelled)
rented_out ──→ to_rent (on return)
any ──→ repair (manual)
any ──→ not_to_rent (manual exclusion)
```

### 2.3 Availability Check
An item is considered **available for rent** if:
- `status = 'to_rent'`, OR
- `status = 't_bron'` AND `br_time < current_time` (temporary hold expired; 7-minute window)

**Source**: `tovar::isForRent()`

### 2.4 Temporary Hold (t_bron)
When a consultant starts processing an item, a 7-minute temporary hold is placed:
- Sets `status = 't_bron'` and `br_time = current_time`
- After 7 minutes, the item is automatically treated as available again
- Validation: `(br_time + 7*60 - time()) < 0` → expired

### 2.5 Dirty/Cleaning Flag
Items can be marked as "dirty" (needs cleaning) via the `stirka` booking type in `rent_orders`. An item is dirty if there exists a `rent_orders` record with `inv_n = item.inv_n AND type2 = 'stirka'`.

**Source**: `tovar::isDirty()`, `bron::stirka()`

### 2.6 Item Condition States
The `state` field tracks item condition:
- `0` = Normal
- `3` = Last rental — item will be sold after this rental returns
- `-1` = Excluded from statistics

---

## 3. Booking (Order) System

**Source**: `bb/classes/bron.php`, `app/Http/Controllers/CartController.php`

### 3.1 Booking Types
| type | type2 | Meaning |
|------|-------|---------|
| `strong` | `bron` | Confirmed reservation (pickup) |
| `strong` | `deliv` | Confirmed reservation (delivery) |
| `strong` | `remont` | Repair booking |
| `strong` | `out` | Disposal booking |
| `zayavka` | `zayavka` | Waitlist request (item not available) |
| — | `stirka` | Cleaning request |
| — | `sell` | Sale reservation |

**Rule**: If `type2` is in `['bron', 'deliv', 'remont', 'out']`, the booking `type` is automatically set to `strong`. Otherwise, it becomes `zayavka`.

### 3.2 Booking Creation (Strong)
When creating a confirmed booking (`createBronStrong`):
1. Verify `inv_n > 0` (inventory number must be provided)
2. Load the item by `inv_n` and verify `isForRent()` returns true
3. Resolve `model_id` from item, `cat_id` from model
4. Set `validity = order_date + 2 days` (booking expires in 2 days if not processed)
5. Clean phone number (digits only)
6. If delivery requested: `type2 = 'deliv'`, store delivery address
7. If pickup: `type2 = 'bron'`
8. **Change item status to `bron`** (`setStatusAsBron()`)
9. Insert booking record into `rent_orders`

### 3.3 Table Locking
When creating bookings, the system uses `LOCK TABLES tovar_rent_items WRITE, rent_orders WRITE` to prevent race conditions on item status changes. This ensures two operators can't book the same item simultaneously.

### 3.4 Booking Validation Before Insert
Before setting an item as booked, the system checks:
- Item exists and is unique by `inv_n`
- Item status is NOT `rented_out`, `to_deliver`, or `bron`
- If any of these checks fail, the booking is rejected with an error message

### 3.5 Waitlist (Zayavka) Creation
When an item is not available:
1. Create a `zayavka`-type booking in `rent_orders` with `model_id` (no specific `inv_n`)
2. Also create a `zvonok` (callback request) so operators are notified
3. Set `validity` based on requested rental period

### 3.6 Booking to Rental Conversion (z_to_br)
When a waitlist request gets assigned to an available item:
1. Change `type2` from `zayavka` to `bron`, `type` to `strong`
2. Set approval timestamps
3. Lock tables, verify item availability
4. Set item status to `bron`
5. Update the booking record

### 3.7 Booking Removal
When a deal is created (item issued), all strong bookings for that `inv_n` are automatically archived:
1. Add note "Бронь удалена автоматически при выдаче товара"
2. Copy booking to `rent_orders_arch`
3. Delete from `rent_orders`

**Source**: `bron::removeStrongBrons()`

---

## 4. Rental Deal Lifecycle

**Source**: `bb/classes/Deal.php`, `bb/classes/SubDeal.php`, `bb/cur_page.php`, `bb/dogovor_new.php`, `bb/dogovor_new2.php`, `bb/dogovor_new4.php`

### 4.1 Deal Structure
A rental deal (`rent_deals_act`) is a container for one item rental. It contains:
- **Client reference** (client_id)
- **Item reference** (item_inv_n)
- **Date range** (start_date → return_date)
- **Financial summary** (r_to_pay, r_paid, delivery amounts, collateral)
- **Sub-deals** (rent_sub_deals_act) — individual operations within the deal

### 4.2 Sub-Deal (Operation) Types
| Type | Sort Order | Description |
|------|-----------|-------------|
| `takeaway_plan` | 5 | Planned pickup (reserved, not yet issued) |
| `first_rent` | 10 | Initial item issuance |
| `extention` | 20 | Rental period extension |
| `payment` | 30 | Payment recorded |
| `cl_payment` | — | Client-initiated payment |
| `cur_return` | — | Current-period return |
| `close` | 80 | Final return and deal closure |

### 4.3 Deal Status Values
| Status | Context | Meaning |
|--------|---------|---------|
| `''` (empty) | Active | Normal active deal |
| `bron` | Active | Reserved, not yet issued |
| `for_cur` | Active | Assigned to courier for delivery |
| `{staff_id}` (numeric) | Active | Overdue deal assigned to specific staff member |
| `closed` | Archived | Normally closed |
| `closed_loss` | Archived | Closed with loss (item not returned) |
| `closed_problem` | Archived | Closed with problems |
| `closed_loss_problem` | Archived | Both loss and problem |

### 4.4 Deal Creation Flow
1. Staff creates a booking → item status set to `bron`
2. When item is physically issued:
   - Create `rent_deals_act` record
   - Create `first_rent` sub-deal with tariff details
   - Set item `status = 'rented_out'`
   - Set item `active_deal_id = new_deal_id`
   - Remove all existing bookings for this `inv_n` (auto-archive)

### 4.5 Deal Extension
When a customer extends their rental:
1. Archive current sub-deal state
2. Create new `extention` sub-deal with new period and tariff
3. Update deal's `return_date` to the new end date
4. Recalculate `r_to_pay`

### 4.6 Deal Closure
When item is returned:
1. Create `close` sub-deal
2. Record final payment if any
3. Calculate if there's overpayment or underpayment
4. Update deal status
5. Archive deal: copy to `rent_deals_arch`, copy sub-deals to `rent_sub_deals_arch`
6. Delete from active tables
7. Set item `status = 'to_rent'`
8. Clear item `active_deal_id`

### 4.7 Financial Recalculation
Deal amounts are recalculated from sub-deals:
- `r_to_pay = SUM(r_to_pay)` from sub-deals of types: `first_rent`, `close`, `extention`, `cur_return`, `takeaway_plan`
- `r_paid = SUM(r_paid)` from sub-deals of types: `payment`, `cl_payment`

**Source**: `Deal::recalculateAmounts()`, `Deal::getAmountPaid()`, `Deal::getAmountToPay()`

### 4.8 Overdue Deal Management
Deals past their `return_date` are flagged:
- Deals with `deal_status = ''` and `return_date < today` are unassigned overdue deals
- Staff members can be assigned overdue deals: `deal_status` is set to their `logpass_id`
- The system counts overdue deals per assignee for workload management
- Deals with `deal_status NOT IN ('', 'bron', 'for_cur')` are treated as staff-assigned overdue

**Source**: `bb/kr_baza.php`, `bb/zpl.php`

### 4.9 Delivery Date Change
When changing a delivery date for an active deal:
1. Find the sub-deal with `delivery_yn = 1` and `type = 'first_rent'`
2. Calculate the interval between old and new start dates
3. Shift both start and end dates by this interval
4. Update both the deal and sub-deal dates within a transaction

**Source**: `Deal::changeDeliveryDate()`

---

## 5. Payment Processing

**Source**: `bb/classes/SubDeal.php`, `bb/classes/Deal.php`, `bb/cur_page.php`

### 5.1 Payment Methods
| Code | Description |
|------|-------------|
| `bank` | Bank transfer |
| `card` | Card payment (terminal) |
| `nal_cheque` | Cash with fiscal receipt |
| `nal_no_cheque` | Cash without receipt |

### 5.2 Payment Recording
When a payment is recorded:
1. Create a `payment` or `cl_payment` sub-deal
2. Set `r_paid` to the payment amount
3. Link to the operation being paid for via `link` field
4. Record `acc_date` (accounting date) and `place` (office)
5. Record fiscal receipt number if applicable (`ch_num`)
6. Update the parent deal's `r_paid` aggregate

### 5.3 Payment Deletion
When deleting a payment:
1. Get the deal_id from the sub-deal
2. Delete the payment sub-deal
3. Recalculate the parent deal's `r_paid` from remaining payment sub-deals

**Source**: `Deal::deletePaymentStatic()`

### 5.4 Sales Reporting
Sales reports aggregate `r_paid` from sub-deals across both active and archived tables, grouped by:
- Payment method (for cash register reconciliation)
- Category (for product performance analysis)
- Office/place (for location performance)
- Time period

**Source**: `Deal::getSalesByKassa()`, `Deal::getSalesCategorySplit()`, `Deal::getSalesRentDeliv()`

---

## 6. Cart & Web Checkout

**Source**: `app/Http/Controllers/CartController.php`

### 6.1 Cart Storage
The cart is stored entirely **client-side** (localStorage). There is no server-side cart state.

### 6.2 Tariff API
The cart page calls `getTariffs()` to get server-side pricing for up to 10 model IDs. Response format per model:
```json
{
  "tariffs": [[days_threshold, daily_rate], ...],
  "available": true/false
}
```

### 6.3 Checkout Flow
1. Client submits: items (modelId, days, dateFrom), customer info (fio, phone), delivery preference, optional promo code / gift certificate
2. **Server-side validation**:
   - Cart not empty
   - Name >= 3 characters
   - Phone >= 7 digits
   - Delivery method selected
   - If delivery: address >= 5 characters
3. For each item:
   - **Recalculate price server-side** (never trust client amounts)
   - Check availability (`getFreeItemsOfficeArrayForModelId`)
   - If available:
     - Find a specific free item (prefer same office if pickup, any if delivery)
     - Create a strong booking via `bron::createBronStrong()`
     - Result: `booked`
   - If unavailable:
     - Create a waitlist entry (`bron::createZayavka`)
     - Create a callback request (`Zvonok::addLitZvonok`)
     - Result: `waitlist`
   - On error: create callback as fallback, result: `error`

### 6.4 Availability Check
Single-model availability endpoint:
- Returns `available: true/false`
- If not available, returns `returnDate` — the earliest expected return date for any item of that model (formatted in Russian: "15 марта")

**Source**: `tovar::getEarliestReturnDateForModelId()`

---

## 7. Inter-Office Item Transfers

**Source**: `bb/classes/tovar.php`

### 7.1 Transfer Initiation
1. Operator selects an item and target office
2. System sets `to_move = target_office_number`
3. Item stays at its current `item_place`
4. The item appears in the "in transit" list for both offices
5. Bookings for the item: `client_id` is reset (unset call-to-customer flag)

### 7.2 Transfer Acceptance
When the receiving office accepts the item:
1. Verify `to_move == current_office_number` (can only accept at the correct destination)
2. Set `item_place = to_move`, clear `to_move`
3. If item has status `bron`, set the call-to-customer flag (notify customer that item arrived)

### 7.3 Transfer Cancellation
Clear the `to_move` field without changing `item_place`.

### 7.4 Transfer Rules
- Items with status `rented_out` should not be in "in move" state
- An item is "in transit" if `item_place > 1 AND to_move > 0 AND status != 'rented_out'`

---

## 8. Carnival Costume Rental

**Source**: `bb/KBron.php`, `app/MyClasses/KBForm.php`, `app/MyClasses/KBronLine.php`, `bb/classes/Category.php`

### 8.1 Carnival Detection
A category is identified as "carnival" by its `cat_type = 1` in `tovar_rent_cat`. Certain inventory number prefixes (702, 761) also indicate carnival items.

### 8.2 Carnival vs Regular Rental
| Aspect | Regular | Carnival |
|--------|---------|----------|
| Booking entity | `rent_orders` | `karn_brons` |
| Time granularity | Days | Hours |
| Booking display | Date range | Timeline with hourly slots |
| Scheduling | Date-based | Working hours aware |
| Multi-booking | One item per deal | Multiple sizes per event |
| Extra fields | — | Height (rost), event date, fitting |

### 8.3 Carnival Booking Timeline
The carnival booking form shows a visual timeline for each item:
- Shows 3 days centered on the event date
- Each day divided into hourly slots (based on office working hours)
- Green = free, Red = booked
- Hours per pixel: 6px per hour
- Timeline starts 1 day before target, ends 2 days after

**Source**: `KBronLine::getLine()`, `KBronLine::getFreePeriodsCssArray()`

### 8.4 Working Hours
Working hours vary by day of week:
- Weekdays: office-specific hours from `offices` table
- Weekends: separate weekend hours from `offices` table
- The carnival form shows only hours when the office is open

**Source**: `KBForm::getWorkingHoursArray()`, `bb/Schedule.php`

### 8.5 Carnival Booking Statuses
| Status | Meaning |
|--------|---------|
| `new` | New booking request |
| `in_process` | Being processed by operator |
| `ok` | Confirmed and completed |

---

## 9. Client Management

**Source**: `bb/classes/Client.php`, `clients` table

### 9.1 Client Data
Clients store full passport information (Belarusian ID document):
- Full name (family, name, patronymic)
- Passport number, personal number, issue date, issuing authority
- Current address + registered address
- Two phone numbers
- Free-text notes

### 9.2 Client History
Every edit to a client creates a full snapshot in `clients_arch`:
- Archives the complete previous state
- Records who made the change and when
- Original `client_id` is preserved via `main_cl_id`

### 9.3 Aggregated Stats
Client records maintain denormalized stats:
- `arch_n`: count of archived deals
- `arch_amount`: total amount from archived deals
- `arch_l_date`: timestamp of last deal

### 9.4 Client Source Tracking
The `source` field tracks how the client was acquired (e.g., "web" for website bookings).

---

## 10. Item Disposal

**Source**: `bb/classes/tovar.php`

### 10.1 Pre-Disposal Checks
Before removing an item from active inventory:
1. Item must exist (`inv_n` must be valid)
2. Status must NOT be `rented_out` — must return item first
3. Status must NOT be `to_deliver` — must complete delivery first
4. No active bookings (`type2 = 'bron'` or `type2 = 'deliv'`) may exist — delete bookings first
5. No active deals in `rent_deals_act` for this `inv_n` — close deals first

### 10.2 Disposal Process
1. Record disposal details: reason (sold, no_return, etc.), sale amount, payment method
2. Copy full item record to `tovar_rent_items_arch` with additional archive fields
3. Delete from `tovar_rent_items`

### 10.3 Disposal Reasons
| Code | Meaning |
|------|---------|
| `sold` | Item sold to customer |
| `no_return` | Customer did not return item |
| `bron_delete` | Removed during booking cleanup |

### 10.4 Item Sale
When selling a used item:
1. Verify item exists and is not rented out
2. Record financial transaction via `DohRash::sellTovarAmount()`
3. Dispose item with reason `sold`

**Source**: `Deal::sellTovar()`

---

## 11. Callback & Communication

**Source**: `bb/classes/Zvonok.php`

### 11.1 Callback Creation
When a web visitor submits a callback request:
1. Check for spam (blocked keywords: "go.", "snitssoke")
2. Check for duplicates: same name + same info within last 1 hour
3. If not spam/duplicate: insert into `zvonki`
4. Send email notification to operator (anna.kuyumdzhi@gmail.com, cc: dmitry.nayd@gmail.com)

### 11.2 Callback Statuses
| Status | Meaning |
|--------|---------|
| `new` | Awaiting operator response |
| `done` | Handled by operator |

### 11.3 Callback Types
| type1 | Meaning |
|-------|---------|
| `''` (empty) | General callback |
| `zayavka` | Product waitlist callback |
| `zayavka_done` | Waitlist callback completed |

---

## 12. Catalog & Web Display

**Source**: `app/MyClasses/L2ModelWeb.php`, `app/MyClasses/CatMainPage.php`, `app/MyClasses/L3Page.php`

### 12.1 URL Structure
```
/{lang}/{section_slug}/{subsection_slug}/{category_slug}/{model_page_addr}
```
- Only `/ru/` is active. `/en/` and `/lt/` redirect to `/ru/`
- Model page address comes from `rent_model_web.page_addr`

### 12.2 Availability Display
On product listing (L2) pages:
- Green indicator = at least one free item exists at any office
- Shows which offices have free items
- `l2_availability_show` flag can hide this per model

### 12.3 Product Search
Fulltext search uses MySQL `MATCH...AGAINST` on `rent_model_web` columns: `title`, `l2_name`, `item_name_main`.

### 12.4 Cross-Category Display
Products can appear in multiple categories via `multi_web`:
- Each cross-listing can have its own image override
- Links to a specific additional category
- The primary category is defined on `tovar_rent.tovar_rent_cat_id`

---

## 13. Financial Reporting

**Source**: `bb/classes/Deal.php`, `bb/doh_rash.php`

### 13.1 Revenue Calculation
Revenue is calculated from sub-deal `r_paid` fields, aggregated across both active (`rent_sub_deals_act`) and archived (`rent_sub_deals_arch`) tables:
- **By payment channel**: bank, card, cash_receipt, cash_no_receipt
- **By category**: JOIN through deal → item → model → category
- **By office/place**: using `place` field on sub-deals
- **By delivery status**: filter on `delivery_yn`

### 13.2 Rental Days Calculation
For utilization metrics, rental days are calculated as:
```
SUM((end_date - start_date) / 86400)
```
With clamping to the reporting period:
- `date1 = MAX(deal.start_date, period.from)`
- `date2 = MIN(deal.return_date, period.to)`

### 13.3 Inventory Age
Average inventory age is calculated as: `SUM(report_date - buy_date) / COUNT(items)`, considering both active and archived items (items archived after the report date are included).

---

## 14. Security & Access Control

**Source**: `bb/models/User.php`, `bb/Base.php`, `AGENTS.md`

### 14.1 Two Session Systems
- **Legacy admin** (`/bb/`): Native PHP sessions + `tt_is_logged_in` cookie (30-day TTL)
- **Laravel app**: Laravel session driver (file-based)
- These systems do NOT share sessions

### 14.2 Admin Detection in Laravel
To check if a user is a logged-in admin from Laravel context, check `$_COOKIE['tt_is_logged_in']`. Do NOT call `\bb\models\User::isLoggedIn()` (it only works within `bb/` context).

### 14.3 IP Restrictions
Staff accounts can have IP-based access restrictions:
- `ip_yn = 1`: enforce IP whitelist
- Up to 3 allowed IPs per user
- Global IP whitelist in `allowed_ip` table tied to office locations

### 14.4 Time Restrictions
Staff accounts can have time-based access windows:
- `time_yn = 1`: enforce time window
- `time_from` / `time_to`: allowed login hours

### 14.5 Staff Roles
| Role | Permissions |
|------|-------------|
| `owner` | Full access to all functionality |
| `consultant` | Deal management, bookings, client handling |
| `courier` | Delivery management, item transfers |
| `accountant` | Financial operations, reports |
| `coder` | System administration, configuration |

### 14.6 Failed Login Tracking
Failed login attempts are logged with: timestamp, attempted username, attempted password, IP address, reason for failure.

---

## 15. Data Archival Pattern

**Source**: Throughout all `bb/` files

### 15.1 Three-Tier Archive
The legacy system uses a three-tier data lifecycle:
1. **Active** (`*_act`): Current, actively used records
2. **Archive** (`*_arch`): Recently closed/completed records
3. **Deep Archive** (`*_arch_deep`): Old records moved from archive

### 15.2 Archive Operation
When archiving a record:
1. Copy full record to the corresponding `_arch` table (with added `arch_time` timestamp)
2. Delete from the `_act` table
3. Periodically, old `_arch` records are moved to `_arch_deep`

### 15.3 Tables Using This Pattern
| Active | Archive | Deep Archive |
|--------|---------|-------------|
| `rent_deals_act` | `rent_deals_arch` | `rent_deals_arch_deep` |
| `rent_sub_deals_act` | `rent_sub_deals_arch` | `rent_sub_deals_arch_deep` |
| `rent_orders` | `rent_orders_arch` | — |
| `tovar_rent_items` | `tovar_rent_items_arch` | — |
| `tovar_rent` | `tovar_rent_arch` | — |
| `tovar_rent_cat` | `tovar_rent_cat_arch` | — |
| `karn_brons` | `karn_brons_arch` | — |
| `clients` | `clients_arch` | — |

### 15.4 Modernization Note
The new schema replaces this pattern with:
- **Soft deletes** (`deleted_at`) for catalog entities
- **Status + archived_at** for transactional entities (deals, operations)
- If table partitioning is needed for performance, use MySQL native partitioning by date

---

## 16. Miscellaneous Rules

### 16.1 Inventory Number Format
- 6 digits, displayed as `XXX-YYY` (e.g., `101-001`)
- First 3 digits indicate category/section
- Carnival items typically start with `702` or `761`

### 16.2 Item Color Logic
- If model color is set and is NOT `multicolor`: use model color
- If model color is `multicolor`: use item-specific `item_color`
- If no color set: display nothing

**Source**: `tovar::getColor()`

### 16.3 Item Set/Accessories
- Each item can have its own `item_set` (what's included)
- Falls back to `model.set` if item-specific set is empty

**Source**: `tovar::getSet()`

### 16.4 Payback Period Calculation
For inventory analytics, the payback period (in months) for an item:
```
months = buy_price_in_BYN / monthly_rental_revenue
monthly_rental_revenue = tariff_for_30_days / 30 * 30.5
```

**Source**: `tovar::getMonthsToPayBack()`

### 16.5 Exchange Rate
Item purchase prices are stored in original currency. To convert to BYN:
```
price_BYN = buy_price / exchange_rate_to_usd * 3.2
```
**Legacy**: Uses a hardcoded 3.2 BYN/USD rate.
**New system**: Uses `exchange_rates` table with historical rates by date and currency pair.

### 16.6 Duplicate Callback Prevention
Callbacks are deduplicated by checking if an identical (same name + same info, non-empty) callback was created within the last hour.

### 16.7 Spam Filtering
Callback info and name fields are checked against a blocklist of spam keywords. Currently blocked: `"go."`, `"snitssoke"`.
**New system**: Spam keywords stored in `settings` table (group=`spam`, key=`blocked_keywords`) instead of hardcoded array.

---

## 17. EAV Product Attribute System (New)

> Replaces hardcoded columns (`age_from_months`, `weight_from_kg`, `sex`, etc.) from legacy `tovar_rent` table. Enables the system to support any rental domain without schema changes.

### 17.1 Attribute Definition
Each attribute is defined in `attribute_definitions` with:
- **`code`**: unique slug (e.g., `age_from`, `power_watts`, `chuck_size`)
- **`data_type`**: integer, decimal, string, boolean, or enum
- **`unit`**: measurement unit, translatable (e.g., "мес." / "months")
- **`is_filterable`**: whether it appears in catalog filter sidebar
- **`is_required`**: whether it must be filled when creating a product
- **`is_range`** + **`range_pair_code`**: for paired attributes like `age_from`/`age_to`

### 17.2 Category-Attribute Assignment
Attributes are assigned to categories via `category_attribute` pivot table:
- "Highchairs" category → age_from, age_to, weight_max
- "Power drills" category → power_watts, max_rpm, chuck_size_mm
- Admin can add/remove attribute assignments without code changes

### 17.3 Enum Attributes
For attributes with predefined choices (e.g., `sex`: m/f/u, `fuel_type`: petrol/diesel/electric):
- Choices stored in `attribute_enum_options` (value + sort_order)
- Display labels translatable via `attribute_enum_option_translations`
- Stored value in `product_attribute_values.value_text` matches `attribute_enum_options.value`

### 17.4 Two-Level EAV
Attributes exist at two levels:
1. **Model-level** (`product_attribute_values`): shared by all items of this model (e.g., age range, weight limit)
2. **Item-level** (`inventory_item_attribute_values`): specific to a physical unit (e.g., size=M, height_from=80cm)
- Item-level values override model-level for the same attribute
- This replaces the legacy pattern where `tovar_rent` had model attributes and `tovar_rent_items` had item-specific `item_size`, `item_rost1`, `item_rost2`

### 17.5 Filter Queries (EAV)
Filtering by attributes requires a JOIN per filtered attribute:
```sql
-- Example: find models with age_from <= 6 AND age_to >= 12
SELECT pm.id FROM product_models pm
JOIN product_attribute_values pav1
  ON pav1.product_model_id = pm.id AND pav1.attribute_definition_id = {age_from_id}
JOIN product_attribute_values pav2
  ON pav2.product_model_id = pm.id AND pav2.attribute_definition_id = {age_to_id}
WHERE pav1.value_numeric <= 6 AND pav2.value_numeric >= 12
```
Performance is maintained via composite indexes: `(attribute_definition_id, value_numeric)` and `(attribute_definition_id, value_text)`.

### 17.6 Attribute Translations
All user-facing attribute content is translatable:
- Attribute names: `attribute_definition_translations.name` ("Возраст от" / "Age from")
- Units: `attribute_definition_translations.unit_label` ("мес." / "months")
- Enum labels: `attribute_enum_option_translations.label` ("Мальчик" / "Boy")

---

## 18. Internationalization (i18n) Rules

### 18.1 Translation Strategy
- All user-facing text stored in separate `_translations` tables
- Non-translatable fields (slugs, images, numeric config) stay on the base table
- Locale is determined from the URL prefix: `/{locale}/...` (ru, en, lt)

### 18.2 Translated Entities
| Entity | Translated fields |
|--------|------------------|
| sections | name |
| subsections | name |
| categories | name, contract_name |
| product_models | name, included_set |
| product_web_profiles | title, meta_description, breadcrumb_name, listing_name, listing_alt_text, main_name, main_image_alt, main_image_title, description, keywords |
| pages | title, meta_description, h1, body_text, secondary_title, secondary_body |
| attribute_definitions | name, unit_label |
| attribute_enum_options | label |

### 18.3 Fallback
If a translation doesn't exist for the requested locale, fall back to the default locale (`ru`). This is handled by the translation package (astrotomic/laravel-translatable).

### 18.4 URL Structure
```
/{locale}/{section_slug}/{subsection_slug}/{category_slug}/{model_page_url}
```
- Slugs are NOT translated (shared across locales) — simplifies routing and SEO
- Only the display names/content change per locale
