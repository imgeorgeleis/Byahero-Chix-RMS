# Contributing to Byahero Chix RMS

Thanks for helping improve the project.

## Development principles

1. Preserve backward compatibility with existing RMS data whenever practical.
2. Use WordPress APIs instead of hard-coded WordPress paths or direct assumptions about installation layout.
3. Capability-check and nonce-protect every state-changing admin action.
4. Sanitize input before storage and escape output for its rendering context.
5. Keep business logic in services rather than duplicating it in admin views.
6. Write comments that explain intent, constraints, or non-obvious decisions rather than narrating obvious syntax.
7. Do not introduce non-GPL-compatible dependencies or assets.

## Pull requests

- Keep a PR focused on one feature or fix.
- Describe schema changes and upgrade implications.
- Test activation and upgrade from the previous stable release.
- Test create, edit, and delete/deactivate flows affected by the change.
- Update CHANGELOG.md when behavior changes.

## Coding style

Follow WordPress PHP coding conventions where practical. Use descriptive class/method/variable names and PHPDoc for public APIs and non-obvious services.
