# Changelog

## 0.11.1
- Receiving a Purchase Order now synchronizes actual received pricing back to costing.
- Ingredient PO receiving updates the matching supplier catalog price, so live recipe costs use the latest received supplier price.
- Packaging PO receiving updates `packaging.unit_cost`, so product packaging cost updates immediately.
- PO receipts create supplier price-history snapshots with source `purchase_order`.
- Packaging pack prices are reconstructed from received per-piece cost and configured pack size.
- No database schema changes.


## 0.11.0
- Added Supplier Catalog support for both ingredients and packaging.
- Added packaging purchase pack size / units-per-purchase conversion.
- Packaging catalog now calculates per-piece cost automatically (e.g. ₱190 / 25 pcs = ₱7.60/pc).
- Added preferred supplier per ingredient or packaging item.
- Added supplier price history snapshots.
- Purchase Order item lists are now filtered by the selected supplier catalog.
- Preferred supplier items are visibly marked in PO selection.
- PO pricing auto-fills from supplier catalog; packaging is converted to per-piece PO cost while inventory remains piece-based.
- Existing ingredient Supplier Pricing records migrate as ingredient catalog entries.


## 0.10.2
- Fixed Supplier Add/Edit warnings caused by optional `address` and `notes` fields not being present in submitted forms.
- Added safe defaults for all optional supplier POST fields.
- Added Address and Notes fields to the Supplier Add/Edit form because both fields already exist in the supplier schema.
- Prevented PHP warnings from sending output before the WordPress redirect, fixing the resulting `Cannot modify header information` warning.
- Added supplier-name validation.
- No database schema changes.


## 0.10.1
- Purchase Orders can now mix tracked ingredients and tracked packaging from the same supplier.
- Added resource type and packaging linkage to PO lines while preserving existing ingredient PO lines.
- Packaging PO lines use piece quantities and do not require an ingredient unit.
- Receiving packaging from a PO posts directly to the packaging inventory ledger.
- Mixed partial receiving supports ingredient and packaging lines together.
- Existing v0.9.0/v0.10.0 purchase orders remain ingredient lines after migration.


## 0.10.0
- Upgraded Purchase Orders from one ingredient to multiple ingredient lines.
- Added dynamic Add Ingredient / Remove Line PO editor.
- Added live estimated PO line totals and grand total.
- Added per-line ordered, received, and remaining quantities.
- Added multi-line partial receiving: selected quantities can be received independently.
- PO becomes PART RECEIVED while any lines remain and RECEIVED only when all lines are complete.
- Each received line continues to create inventory receipt and stock-ledger entries through the existing Inventory Engine.
- Existing v0.9.0 purchase orders remain compatible.


## 0.9.0
- Added Purchase Orders linked to existing suppliers, ingredients, and units.
- Added draft, ordered, part-received, received, and cancelled PO lifecycle.
- Added partial receiving against purchase orders.
- PO receiving automatically creates normal inventory receipts and stock ledger movements.
- Added ordered/received/remaining quantity tracking and PO history.
- Added unique daily PO numbering.
- This initial purchasing build supports one ingredient line per PO; multi-line POs are planned for a later refinement.


## 0.8.2
- POS now refreshes the full menu catalog immediately after a successful checkout.
- Available counts are recalculated from current ingredient and packaging inventory without requiring a page reload.
- Products automatically switch to OUT OF STOCK when the completed sale consumes the last producible stock.
- Shared ingredients and packaging update availability across all affected menu products.
- No database schema changes.


## 0.8.1
- Fixed Packaging Stock warnings on upgraded installations where the existing packaging table was missing the new inventory columns.
- Added an explicit migration for `track_inventory` and `reorder_level` instead of relying only on dbDelta.
- Added backwards-safe Packaging Stock rendering while migration completes.
- Added a compatibility fallback for packaging POS availability.
- No data is deleted; existing packaging records are retained and default to inventory tracking enabled.


## 0.8.0
- Added physical packaging inventory ledger.
- Added packaging stock receiving, adjustments, waste/damage, and audit history.
- Product packaging assignments now participate in POS availability.
- Checkout automatically deducts assigned packaging quantities.
- Voiding an order automatically restores packaging stock.
- POS maximum sellable quantity considers both recipe ingredients and packaging.
- Added Packaging Stock admin screen.
- Existing packaging records default to inventory tracking enabled.


## 0.7.1
- Added recipe-based POS stock availability checks.
- Products that cannot produce one serving are disabled in POS.
- Added maximum sellable quantity based on the limiting recipe ingredient.
- Added authoritative server-side aggregate cart stock validation before checkout.
- Prevents negative ingredient inventory from POS transactions.
- Held orders do not reserve stock and are revalidated when completed.
- No database schema changes.


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
