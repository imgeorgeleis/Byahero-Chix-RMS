=== Byahero Chix RMS ===
Contributors: georgeleis
Tags: restaurant, pos, inventory, recipe, costing
Requires at least: 6.4
Stable tag: 0.13.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restaurant management foundation for WordPress with ingredients, suppliers, recipe building, and live recipe costing.

== Description ==

Byahero Chix RMS is an open-source restaurant management system being built for WordPress. Version 0.3.0 includes master-data management, supplier pricing, a Recipe Builder, and live recipe costing.

Current functionality includes ingredients, ingredient categories, suppliers, supplier pricing, units, recipes, recipe ingredients, yield, servings, waste percentage, and cost per serving.

== Installation ==

1. Back up your WordPress site and database.
2. Upload the plugin ZIP from Plugins > Add Plugin > Upload Plugin.
3. Activate Byahero Chix RMS.
4. Open Byahero Chix RMS in WordPress Admin.
5. Configure ingredients, suppliers, and supplier pricing before creating recipes.

== Frequently Asked Questions ==

= Is this already a complete POS? =

No. The project is being released incrementally. POS, payments, inventory transactions, and kitchen features are planned for later versions.

= How is recipe cost calculated? =

The current build uses active preferred supplier pricing, unit conversion factors, recipe quantities, and the recipe waste percentage. Packaging, utilities, labor, and weighted-average inventory costing are planned for later milestones.

= Will upgrading remove my existing v0.1.0 or v0.2.0 data? =

No. Version 0.3.0 upgrades the schema and preserves the existing master-data tables.

== Changelog ==

= 0.3.0 =
* Added GPL-2.0-or-later licensing and developer documentation.
* Added WordPress.org-style plugin metadata/readme.
* Added Recipe Builder.
* Added recipe ingredient lines, yield, servings, and waste percentage.
* Added live recipe total and per-serving costing.
* Added database version upgrade handling.

For older releases, see CHANGELOG.md.


= 0.4.0 =
* Added Menu Categories and Products / Menu.
* Added Recipe-to-Product mapping.
* Added selling price, SKU, POS availability, and menu sorting.
* Added food-cost percentage, gross profit, gross margin, and target-price guidance.
* Added safe dependency checks for product-related records.


= 0.5.0 =
* Added packaging and product packaging costing.
* Added product variants.
* Added modifier groups and add-ons.
* Expanded product direct-cost calculations.


= 0.5.1 =
* Fixed packaging assignment to products.
* Fixed modifier-group assignment to products.
* Added assignment removal controls.


= 0.6.0 =
* Added touchscreen POS interface and cart.
* Added variants and modifiers during ordering.
* Added order storage and order history.
* Added cash, GCash, card and other payment methods.


= 0.6.1 =
* Added hold/resume order workflow.
* Added printable receipt view.
* Added order voiding with audit reason.
* Added order status display.


= 0.6.2 =
* Fixed completed-order voiding.
* Fixed duplicate order numbers after multiple hold/resume transactions.
* Improved order-number uniqueness handling.


= 0.7.0 =
* Added ingredient inventory ledger and stock-on-hand.
* Added receiving, adjustments, and wastage.
* Added automatic recipe consumption on completed POS sales.
* Added automatic inventory reversal for voided orders.
* Added low-stock status and stock movement audit history.


= 0.7.1 =
* Added recipe-based POS stock guards.
* Added maximum sellable quantity.
* Added server-side cart inventory validation.
* Prevented POS sales from creating negative ingredient stock.


= 0.8.0 =
* Added packaging inventory ledger.
* Added packaging stock receiving and adjustments.
* Added automatic packaging deduction on POS sales and restoration on void.
* POS availability now considers assigned packaging stock.


= 0.8.1 =
* Fixed missing packaging inventory columns on upgraded installations.
* Added explicit packaging schema migration and compatibility fallbacks.


= 0.8.2 =
* Refreshes all POS menu availability immediately after checkout.
* Recalculates ingredient and packaging constraints without a page reload.
* Automatically updates OUT OF STOCK states after completed sales.


= 0.9.0 =
* Added supplier purchase orders.
* Added PO status lifecycle and partial receiving.
* PO receipts automatically update ingredient inventory and ledger history.


= 0.10.0 =
* Added multi-line supplier purchase orders.
* Added live PO totals and dynamic ingredient lines.
* Added per-line partial receiving and remaining-quantity tracking.


= 0.10.1 =
* Added mixed ingredient and packaging purchase orders.
* Packaging received against a PO now updates packaging stock.
* Preserved compatibility with existing ingredient-only purchase orders.


= 0.10.2 =
* Fixed Supplier Add/Edit undefined-array warnings.
* Added Address and Notes to the supplier form.
* Fixed post-save redirect warnings caused by PHP output before headers.


= 0.11.0 =
* Added supplier catalogs for ingredients and packaging.
* Added packaging pack-to-piece costing.
* Added preferred suppliers and price history.
* Purchase Orders now filter items and pricing by selected supplier.


= 0.11.1 =
* Sync actual PO receipt prices to ingredient recipe costing.
* Sync packaging PO receipt cost to packaging unit cost.
* Record PO-sourced supplier price history.


= 0.12.0 =
* Added front-end RMS shell and POS 2.0.
* Added Product menu images using the WordPress Media Library.
* Added larger payment totals, live change, and quick-cash controls.
* Inventory remains committed only on successful paid checkout.


= 0.12.1 =
* Prevent duplicate Ingredients, Recipes, and Products/Menu records.
* Prevent duplicate Ingredients within the same Recipe.
* Add searchable Ingredient and Recipe selectors.

= 0.12.2 =
* Search and pagination for major RMS admin data tables.
