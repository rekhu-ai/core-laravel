# Changelog

All notable changes to `rekhu/core`. Versions follow [semantic versioning](https://semver.org).






## 0.6.0 · 9 November 2026

- **Pages:** `merge($fileIds)`, `split($fileId, ['1-2', '3-end'])` and `render($fileId, 'jpg', 150)` (pages as pictures), each kept as files to download.
- **New Seshat fixes**, all through `fix()`: `convert_profile` (FOGRA39, FOGRA51, FOGRA52, GRACoL 2013, SWOP 2013, newspaper; sets the output intent), `reduce_ink`, `pure_black_text`, `white_knockout`, `rename_spot`, `make_pdfx` (PDF/X-4 or X-1a, or what stops it), `pages` (reorder, remove, rotate, blank pages) and `add_marks` (crop and registration marks). Fix fields such as `limit`, `profile`, `order` and `rotate` pass through as given.
- The order takes `profile`. White set to overprint is now fixed on its own (`white_knockout`); too much ink offers `reduce_ink`. Code list 1.5.
- Docs by task: rekhu.com/docs/seshat.
## 0.5.0 · 9 November 2026

- **Text and fonts in a print file.** Two Seshat fixes, each several times per call, in order, as one charge:
  - `['fix' => 'replace_text', 'find' => 'Summer sale', 'replace' => 'Winter sale', 'to' => 'Lato-Regular']` changes words at the same place and size: in the file's own font when it has the letters, else the same font from the library, else `to`. Otherwise the text stays and is reported with the missing letters. The new text does not re-wrap.
  - `['fix' => 'replace_font', 'from' => 'ArialMT', 'to' => 'Montserrat-Bold']` sets all text in `from` in a library font. `applied` reports per font: `texts` changed, `not_set`, `missing_letters`, and `not_found` or `not_possible` (Type 3).
  - A `to` that is not in the library is refused with `InvalidRequest` naming the closest fonts; Seshat never picks a font itself.
- **`fontFile($name)` and `fontFileTo($name, $path)`** (free): a library font as TrueType with its licence, for an app that sets text with its own tool.
## 0.4.0 · 9 November 2026

- **Seshat fonts:** `Rekhu::seshat()->fonts()` (free) lists the font library: families, styles, PostScript names and licences, for an editor that lets customers change text.
- `embed_fonts` puts in the same font from the library; the option `lookalike` puts in a named look-alike for the others. `applied` says per font: `same`, `lookalike` (with `as`), `not_available` or `not_possible` (Type 3). A font is never swapped without a word, also not by `convert_cmyk`.
- `fonts_not_embedded` names `in_library` and `not_in_library`; it is decided `fix` only when the library has every font.
- `upscale_ai` in `applied` gives `images`, `from_ppi` and `to_ppi`.
## 0.3.0 · 9 November 2026

- **Typed exceptions.** Every refusal has its own class under `RekhuException` (so existing catches keep working): `InvalidKey`, `ModuleNotEnabled`, `RegionUnavailable`, `CreditExhausted`, `AppPaused`, `FileNotFound`, `InvalidRequest`, `RateLimited`, `Unavailable`.
- **Retries without double charges.** A dropped connection, 429, 502 or 503 is tried again up to three times. Charged calls carry an `Idempotency-Key`; Rekhu answers a repeated key with the first result (`meta.replayed`) and charges nothing.
- **Large files streamed.** Uploads go from their path instead of being read into memory; `downloadTo($fileId, $path)` writes a result straight to disk.
- **File names in any language** arrive whole (percent-encoded in the header and named in the input).
- **Seshat:** `codes()` lists every finding code, decision and fix with its options, versioned; every `ask` finding carries structured `options`; an unknown fix or option is refused with an `InvalidRequest` and not charged; `applied` names side effects too, and `resolved` lists the codes the file no longer has; `remove($fileId)` deletes a kept file at once.
- **`php artisan rekhu:core --check`** calls Rekhu with the key: the platform, its version, the account and the agent.
- **No signed-in user in queued work:** the client no longer guesses the user from `auth()` in console and queue; name it with `for()` or `forTenant()`. `download()` and `feedback()` send the tenant and user too.
- **`REKHU_KNOWERS` is optional:** empty means every knower the key allows.
- A warning in the log when limits are set while the ledger is off (limits are counted in the ledger).
- The timeout defaults to 180 seconds everywhere; Guzzle `^7.2` only; no account offer in error messages.
## 0.2.1 · 9 November 2026

- **The ledger's migration only when the ledger is on.** With `REKHU_LEDGER=false` the package no longer adds the `rekhu_usage` table to the app's migrations.
- **Seshat's explanation on its own line.** A check with `explain: true` now shows two lines in `meta.usage`: the check (`seshat`) and the explanation (`seshat_explain`), so an app can show and charge them separately. Nothing changes in the calls.
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
