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
