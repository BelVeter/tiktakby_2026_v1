# Schema.org Rental Fix — Design Spec

**Date:** 2026-07-01  
**Branch:** feature/listing-products-endpoint (current)

## Goal

Fix JSON-LD structured data on product and listing pages to correctly describe rental (not sale). Improve `availability` accuracy, add return date to schema and L3 page UI.

## Scope

### Schema changes (all Offer objects, L3 and L2)

| Field | Change |
|-------|--------|
| `businessFunction` | Add `"http://purl.org/goodrelations/v1#LeaseOut"` to every Offer |
| `availability` | Replace binary InStock/OutOfStock with: `InStock` (has free items) or `BackOrder` (all rented out). Never `OutOfStock` — all shown items exist physically. |
| `availabilityStarts` | Add ISO date when `BackOrder` and `getEarliestReturnDateForModelId()` returns a date |
| `priceValidUntil` | Add `"2027-12-31"` to every Offer (removes GSC warning) |

`eligibleQuantity` — skipped. Not required by Google Rich Results, no ranking impact.

### UI change — L3 product page only

When `!$p->model->hasFreeItems()`, show plain-text block (Variant B, matching L2 card style) between the tariff block and the "ОСТАВИТЬ ЗАЯВКУ" button:

```
Товар находится в прокате
Ожидается возврат 15 июля        ← only if date available
Оставьте заявку — мы сообщим о наличии!
```

No background, Nunito font, matches L2 card `.meta-row` style. Full-width on mobile and desktop.

## Files to change

1. `bb/classes/TariffModel.php` — `getSchemaOffers()` + `getSchemaMinOffer()`: add `businessFunction`, `priceValidUntil`
2. `app/MyClasses/L3Page.php` — `getSchemaJsonLd()`: availability → InStock/BackOrder + `availabilityStarts`
3. `app/MyClasses/MainPage.php` — `getSchemaJsonLdForCategoryPageJsonLd()`: same availability logic for L2 JSON-LD
4. `resources/views/includes/l3_tovar_info_block.blade.php` — add return-date text block
5. `resources/sass/pages/l3.scss` — add `.l3-backorder-notice` styles

## Consistency check

The L2 blade template (`l2_model_block.blade.php`) already shows return date via `tovar::getEarliestReturnDateForModelId()` — no changes needed there. We only update its JSON-LD in `MainPage.php`.

`availabilityStarts` in L2 JSON-LD: add only when the offer is `BackOrder` and date is non-null. One extra DB query per out-of-stock model on listing pages (already done for the blade display, acceptable).
