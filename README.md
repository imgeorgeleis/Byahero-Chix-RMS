# Byahero Chix RMS

Byahero Chix RMS is an open-source restaurant management system for WordPress. The project is being built incrementally from master data and recipe costing toward inventory, purchasing, POS, kitchen, payments, and reporting.

## Current release: 0.3.0

Version 0.3.0 adds a Recipe Builder and live recipe costing while preserving the master-data foundation from 0.1.x/0.2.x.

### Features

- Ingredient and ingredient-category management
- Supplier master data and supplier pricing
- Units and metric conversion factors
- Preferred supplier cost per ingredient base unit
- Recipe master records
- Recipe ingredient lines
- Recipe yield, servings, and waste percentage
- Live ingredient cost, waste cost, total recipe cost, and cost per serving
- Dependency-aware deletion for master data
- Upgrade-safe schema migration through `dbDelta()` and a database version option

## Requirements

- WordPress 6.4 or later
- PHP 7.4 or later
- MySQL/MariaDB supported by the installed WordPress version

The minimum versions are project targets and should be expanded only after compatibility testing.

## Installation

1. Back up the WordPress site and database.
2. Upload the release ZIP through **Plugins > Add Plugin > Upload Plugin**.
3. If upgrading, choose **Replace current with uploaded** when WordPress prompts you.
4. Activate **Byahero Chix RMS**.
5. Open **Byahero Chix RMS** in WordPress Admin.

## Costing model in 0.3.0

Ingredient cost uses the active preferred supplier price (or the newest active supplier-price row when no row is marked preferred). Purchase quantity is converted through the selected unit's factor-to-base. Recipe lines are then converted to the ingredient's base-unit family before cost is calculated.

The recipe total is:

`ingredient subtotal + configured recipe waste = total recipe cost`

`total recipe cost / servings = cost per serving`

Packaging, utilities, labor, weighted-average inventory valuation, and product selling-price analysis are intentionally reserved for later milestones.

## Development

The code follows WordPress APIs for capabilities, nonces, sanitization, escaping, database prefixes, and plugin paths. Keep business logic out of view rendering when adding substantial new features; new costing/inventory logic belongs in `includes/services/`.

### Planned structure

- `admin/` — WordPress Admin integration and presentation
- `includes/` — core PHP classes
- `includes/services/` — business logic
- `includes/database/migrations/` — migration organization as schema complexity grows
- `includes/repositories/` — data-access abstractions when required
- `assets/` — plugin CSS/JS/images
- `languages/` — translation files
- `tests/` — automated tests as the project matures

## Security

All state-changing admin requests must use WordPress nonces and capability checks. Inputs must be sanitized before storage and output escaped for its rendering context. Do not add telemetry or external data transmission without an explicit privacy design and user consent.

## Roadmap

- 0.1.0 — Master Data foundation
- 0.2.0 — Safe delete and dependency protection
- 0.3.0 — Developer documentation, GPL licensing, Recipe Builder, costing foundation
- 0.4.0 — Products/Menu and recipe-to-product mapping
- 0.5.0 — Inventory and purchasing foundation
- Later — POS, payments, kitchen display, expenses, reports, PWA/offline support

## License

Copyright (c) 2026 George L.

Licensed under the GNU General Public License v2.0 or later (`GPL-2.0-or-later`). See `LICENSE`.


## Menu Products (v0.4.0)

Version 0.4.0 introduces the first sellable-menu layer. A product can be assigned to a
menu category, linked to a recipe, given a selling price and SKU, and marked as
available for the future POS.

When a recipe is linked, the RMS calculates recipe cost per serving, food-cost
percentage, gross profit, gross margin, and a suggested selling price using the
configured target food-cost percentage.

This release intentionally keeps one recipe as the primary cost basis for one product.
Modifiers, add-ons, sizes, bundles, packaging costs, taxes, and POS transactions are
planned as later modules.


## Product Configuration & Costing (v0.5.0)

Products now support packaging costs, variants, and modifier/add-on groups. Packaging contributes to base direct cost. Variants and modifiers carry independent selling-price and cost adjustments for use by the future POS order engine.


### v0.5.1 Patch

Fixes the product relationship forms introduced in v0.5.0. Packaging and modifier groups can now be assigned from the Product / Menu edit screen, and existing assignments can be removed.


## POS Order Engine (v0.6.0)

The first cashier-facing POS uses active POS-enabled products, variants and modifier rules. Checkout is validated server-side and completed orders snapshot product names, prices and direct costs so later menu edits do not rewrite history.


## POS Workflow Polish (v0.6.1)

Cashiers can hold an in-progress order, browse held orders and resume one later. Completed orders have a printable receipt/detail view and can be marked void with a reason. Voiding preserves the transaction as an audit record rather than deleting it.


### v0.6.2 POS Reliability Patch

This patch fixes completed-order void routing and duplicate daily order numbers. The order sequence no longer depends on the number of rows created today, because held orders can be removed after resume. It instead advances from the highest existing `BC-YYYYMMDD-NNNN` number and verifies uniqueness before insert.


## Inventory Engine (v0.7.0)

Inventory is ledger-based. Ingredient stock-on-hand is the sum of inventory movement deltas rather than a mutable stock field.

Movement types include receipts, manual stock-in/out, wastage, automatic recipe consumption from completed POS orders, and automatic reversal when a completed order is voided. Held orders do not consume inventory.

Recipe consumption converts each recipe item into the ingredient's configured base unit and divides recipe usage by the recipe serving yield before multiplying by the sold quantity. This keeps inventory quantities aligned with the existing Units and Recipe Builder architecture.

The first v0.7.0 release tracks recipe ingredients. Packaging remains part of product costing but is not yet a stock-ledger item.


### v0.7.1 Stock Guard

POS availability is calculated from current physical stock and recipe requirements. Reorder level remains a warning threshold; a sale is blocked when there is not enough actual stock to produce the requested quantity. Checkout performs a fresh aggregate server-side validation so stale POS screens, resumed held orders, and multiple products sharing an ingredient cannot push inventory below zero.


## Packaging Inventory (v0.8.0)

Assigned product packaging is now a physical inventory constraint alongside recipe ingredients. Packaging stock is ledger-based and supports receiving, adjustments, damaged/waste deductions, automatic POS consumption, and automatic void reversal. The POS limiting quantity is the lowest quantity supported by either recipe ingredients or assigned packaging.


### v0.8.1 Packaging Migration Fix

Existing installations created the packaging table before inventory fields existed. This patch explicitly checks the live table and adds missing `track_inventory` and `reorder_level` columns, then keeps the Packaging Stock UI safe if a database host delays the schema alteration.


### v0.8.2 Live POS Availability Refresh

After a completed checkout, the POS requests a fresh catalog from the server and redraws all menu product availability. This recalculates limiting ingredient and packaging stock across the entire menu, so products that share resources update together without a manual browser refresh.


## Purchasing & Purchase Orders (v0.9.0)

The RMS now supports a purchasing lifecycle before inventory receiving. Purchase orders are linked to suppliers, tracked ingredients and purchase units, and move through draft, ordered, part-received, received or cancelled states.

Receiving against a PO uses the existing Inventory Engine, so accepted quantities create inventory receipts and immutable stock ledger movements. Partial receipts are supported and the PO keeps ordered, received and remaining quantities.

The first v0.9.0 implementation intentionally uses one ingredient line per PO to validate the purchasing workflow before expanding to multi-line purchase orders.


## Multi-line Purchasing (v0.10.0)

A supplier purchase order can now contain multiple ingredient lines. Each line tracks purchase unit, ordered quantity, received quantity, remaining quantity, unit price and line total. The PO form calculates estimated totals live.

Receiving is line-specific and supports mixed partial deliveries. A delivery can receive all of one ingredient, part of another, and none of the remaining lines. Inventory receipts and immutable stock movements are created only for quantities actually received.


### v0.10.1 Mixed Ingredient + Packaging POs

A single supplier PO may now contain both ingredient lines and packaging lines. Ingredient lines retain unit conversion and ingredient inventory receiving. Packaging lines are ordered and received in pieces and post to the packaging movement ledger. This supports suppliers that deliver food ingredients and disposable packaging on the same invoice or delivery.
