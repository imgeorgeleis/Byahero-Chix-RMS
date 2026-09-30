# Includes

Application code that is not specific to a rendered WordPress admin page belongs here.

- `class-bc-rms-db.php` — low-level database helpers.
- `class-bc-rms-installer.php` — schema installation/migrations and capabilities.
- `services/` — reusable business logic such as recipe and product costing.

As the project grows, repository classes, REST controllers, and dedicated migration
classes should be added here rather than expanding the admin UI class with business logic.
