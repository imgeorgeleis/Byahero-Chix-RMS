# Changelog

## 0.7.0
- Added ledger-based ingredient inventory engine.
- Added stock-on-hand derived from immutable inventory movements.
- Added stock receiving with supplier/reference/date and purchase-cost capture.
- Added manual stock-in, stock-out, and wastage/spoilage adjustments.
- Added automatic recipe ingredient consumption when a POS order is completed.
- Added automatic inventory reversal when a completed order is voided.
- Added low-stock status using ingredient reorder levels.
- Added Stock Movements audit ledger.
- Added inventory capabilities for managers/administrators.
- Held orders do not affect inventory until checkout is completed.


## 0.6.2
- Fixed Void Order: v0.6.1 displayed the form and had the service method, but the admin save router did not handle `void_order`.
- Fixed duplicate `BC-YYYYMMDD-NNNN` order numbers after hold/resume workflows.
- Daily order numbering now advances from the highest existing completed-order sequence and checks uniqueness before insert.
- No database schema changes.


## 0.6.1
- Added Hold Order and Held Orders queue.
- Added resume workflow for held orders.
- Added printable order receipt/detail layout.
- Added modifier and item-note visibility in order details.
- Added completed-order voiding with required reason and audit note.
- Added order status to the Orders list.
- No database schema changes.


## 0.6.0
- Added first functional touchscreen POS order screen.
- Added menu search, category filtering, cart, variants, and modifiers.
- Added dine-in/takeout, discounts, payment selection, cash tender and change.
- Added server-side pricing and modifier validation.
- Added immutable order price/cost snapshots and Orders history.


## 0.5.1
- Fixed Product / Menu packaging assignment form routing.
- Fixed Product / Menu modifier-group assignment form routing.
- Added visible assigned modifier groups.
- Added Remove controls for product packaging and modifier-group assignments.
- No database schema changes.


## 0.5.0
- Added Packaging and per-product packaging costing.
- Added Product Variants with price/cost adjustments.
- Added Modifier Groups and Modifiers/Add-ons.
- Added product-to-modifier-group assignments.
- Expanded product direct-cost and margin calculations.
- Added dependency protection for new relationships.
- Preserved v0.4.0 data through automatic schema upgrades.


## 0.4.0
- Added Menu Categories.
- Added sellable Products / Menu master data.
- Added optional Recipe-to-Product mapping.
- Added SKU, selling price, POS availability, active status, and sort order.
- Added live product food-cost percentage, gross profit, and gross-margin calculations.
- Added suggested selling price based on the configured target food-cost percentage.
- Added dependency protection when deleting recipes or menu categories.
- Added `bc_manage_products` capability.
- Preserved all v0.3.0 data through the automatic database migration.


## 0.3.0
- Added GPL-2.0-or-later project licensing and repository documentation.
- Added WordPress.org-oriented plugin headers and `readme.txt`.
- Added `recipes` and `recipe_items` database tables.
- Added Recipe Builder admin screen.
- Added live ingredient, waste, total-recipe, and per-serving costing.
- Added automatic database upgrade check.
- Added `bc_manage_recipes` capability.

## 0.2.0
- Added safe permanent deletion for master-data records.
- Added dependency checks before deletion.
- Updated author to George L. and author URI.

## 0.1.0
- Initial working master-data foundation.
- Added ingredients, categories, units, suppliers, supplier pricing, and settings.
