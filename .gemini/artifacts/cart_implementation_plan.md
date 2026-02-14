# Shopping Cart (Корзина) — Implementation Plan

## Architecture Overview
- **Storage**: localStorage (`tiktak_cart`) for cart items
- **Server**: Laravel Session for checkout validation + booking creation
- **Price calculation**: Client-side from tariff data embedded as data-attributes; server validates at checkout

## Data Structure (localStorage)
```json
{
  "items": [
    {
      "modelId": 12345,
      "name": "Автокресло-коляска Simple Parenting Doona",
      "picUrl": "/path/to/image.jpg",
      "l3Url": "/ru/razdel/subrazdel/category/model",
      "dateFrom": "2026-02-14",
      "days": 14,
      "tariffs": [[7, 3.93], [14, 3.50], [21, 3.20], [28, 2.80]],
      "addedAt": 1739541600000
    }
  ]
}
```
`tariffs` = array of [daysThreshold, dailyRate] sorted ascending.

## Files Created
1. ✅ `app/Http/Controllers/CartController.php` — Cart page + checkout endpoint + tariff/availability APIs
2. ✅ `resources/views/cart/index.blade.php` — Cart page (mobile cards + desktop table)

## Files Modified
1. ✅ `routes/web.php` — Added `GET /cart`, `POST /cart/checkout`, `POST /cart/tariffs`, `POST /cart/check-availability`
2. ✅ `resources/views/includes/l2_model_block.blade.php` — Added "В корзину" button + tariff data-attrs
3. ✅ `resources/views/includes/l3_tovar_info_block.blade.php` — Added "В корзину" button + cartWarningModal
4. ✅ `resources/views/includes/header.blade.php` — Added cart badge (desktop + mobile)
5. ✅ `resources/views/layouts/app.blade.php` — Added TiktakCart JS module (IIFE, globally exposed)
6. ✅ `resources/sass/objects/l2-card.scss` — Cart button + toast styles
7. ✅ `AGENTS.md` — Added CartController entry
8. ✅ `app/Http/Middleware/CheckRedirects.php` — Added try-catch for missing redirects table

## Implementation Status: ✅ COMPLETE

### What Works:
- Add to cart from L2 (product listing) — default 14 days
- Add to cart from L3 (product detail) — uses selected dates/days
- Cart badge in header (desktop + mobile)
- Toast notifications (success, warning)
- Cart page with item list, date/day adjustment, price calculation
- Remove items, clear cart
- Promo/gift certificate fields (visual stubs)
- Checkout form with validation
- Server-side checkout with booking creation via bron::createBronStrong()
- Waitlist fallback via Zvonok::addLitZvonok()
- Cart warning modal on L3 when items already in cart
- Max 10 items enforcement

## Constraints
- Max 10 items in cart
- Default: today + 14 days when adding from L2
- Promo/certificate fields: visual only (stub)
- Mobile-first design (card layout on mobile, table on desktop)
- No closures in web.php
