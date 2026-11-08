# Changelog

All notable changes to `rekhu/core`. Versions follow [semantic versioning](https://semver.org).

## 0.2.0 · 6 November 2026

- **Seshat in modules.** `Rekhu::seshat()->modules()` says which modules this application may use: `preflight` (check, fix, preview) and `ai_enhance`. Rekhu switches them on per application; a call to a module that is off is refused with `module_not_enabled`.
- **ai_enhance:** `enhance($picture, $modes, $options)` improves one picture without a check:
  - the modes are upscale to a print size, extend, denoise, sharpen, restore, remove_background, replace_background, remove_object, vectorize and colour_correct;
  - it answers `file_id`, format, size, ppi, confidence and warnings;
  - a large picture runs as a job: `poll($jobId)`, or a webhook checked with `Rekhu::verifyWebhook()`;
  - `enhancePreview($fileId)` gives a before / after picture, and `upload()` keeps a mask or background picture.
- The usage of every call says what it cost per module and mode (`meta.usage`).

## 0.1.1 · 6 November 2026

- Installs next to Guzzle 8 as well as 7, as Laravel 13 allows.

## 0.1.0 · 5 November 2026

- **Rekhu Core for Laravel.** One package to install Rekhu's knowers: `php artisan rekhu:core --install` connects the app (key, single or multi-tenant, languages, cloud or private), `php artisan rekhu:core --install=machines` adds a knower.
- **Seshat**, the print-file knower: checks a file against its order (size, bleed, resolution, colour, fonts, ink, pages, small text, overprint), decides fix / ask / refuse for each finding, explains it in the user's language, fixes it (bleed, size, trim box, CMYK, fonts, pictures to PDF), draws a proof and hands the fixed file back.
- **Rekhu Machines**: identify a machine from a description, fill in its profile, check a machine for values that cannot be right, choose the machine for a job with why, answer questions about it, and learn from feedback.
- **Your tenants, limits and prices.** `Rekhu::tenantUsing()`, `localeUsing()`, `limitsUsing()` (per tenant and user a day), `priceUsing()` (your own price per call).
- **Usage ledger.** Every call in `rekhu_usage` with Rekhu's cost and your price, `KnowerUsed` after every call, `php artisan rekhu:core --usage` per tenant and month, with CSV.
