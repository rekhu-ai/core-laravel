# Changelog

All notable changes to `rekhu/core`. Versions follow [semantic versioning](https://semver.org).

## 0.1.1 · 6 November 2026

- Installs next to Guzzle 8 as well as 7, as Laravel 13 allows.

## 0.1.0 · 5 November 2026

- **Rekhu Core for Laravel.** One package to install Rekhu's knowers: `php artisan rekhu:core --install` connects the app (key, single or multi-tenant, languages, cloud or private), `php artisan rekhu:core --install=machines` adds a knower.
- **Seshat**, the print-file knower: checks a file against its order (size, bleed, resolution, colour, fonts, ink, pages, small text, overprint), decides fix / ask / refuse for each finding, explains it in the user's language, fixes it (bleed, size, trim box, CMYK, fonts, pictures to PDF), draws a proof and hands the fixed file back.
- **Rekhu Machines**: identify a machine from a description, fill in its profile, check a machine for values that cannot be right, choose the machine for a job with why, answer questions about it, and learn from feedback.
- **Your tenants, limits and prices.** `Rekhu::tenantUsing()`, `localeUsing()`, `limitsUsing()` (per tenant and user a day), `priceUsing()` (your own price per call).
- **Usage ledger.** Every call in `rekhu_usage` with Rekhu's cost and your price, `KnowerUsed` after every call, `php artisan rekhu:core --usage` per tenant and month, with CSV.
