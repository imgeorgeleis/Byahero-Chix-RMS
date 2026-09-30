=== Byahero Chix RMS ===
Contributors: georgeleis
Tags: restaurant, pos, inventory, recipe, costing
Requires at least: 6.4
Stable tag: 0.3.0
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
