# Changelog

All notable changes to `rekhu/core`. Versions follow [semantic versioning](https://semver.org).






## 0.10.0 · 12 November 2026

In parity with @rekhu/core 0.10.0 for Node.js. For Seshat 1.8.

- **Plates, versions and the customer report.** `plates($fileId, $page, $dpi, $profile)`, `compareFiles($fileId, $againstFileId)`, `report($fileId, $order, $recipe, $language, $texts)`, `saveRecipe($name, $order, $fixes)`, `recipes()` and `deleteRecipe($name)`.
- **Recipes per product.** `check(..., recipe: 'A5 flyer')` and `fix($fileId, recipe: 'A5 flyer')` take a recipe's name; `fix` without fixes and without a recipe still fixes `'auto'`. Saving, listing and removing recipes is free and not counted against your limits.

## 0.9.1 · 11 November 2026

In parity with @rekhu/core 0.9.1 for Node.js.

- **Calls are tried again when the platform is briefly unreachable.** A dropped connection, or a 502, 503 or 504 without the platform's JSON error (the request never reached it, as during a deploy), is tried again up to 4 times in all, after 2, 4 and 8 seconds, under the same idempotency key. A busy platform (429, 529, or a 5xx with `overloaded_error` or `rate_limit_error`) is tried again as before, up to 3 times as long as `retry-after` asks (up to 5 seconds). Any other 5xx that carries the platform's own error fails at once.
- **Examples use invented names.** The machine examples in the README, the class comment and the tests name an invented press.

## 0.9.0 · 11 November 2026

In parity with @rekhu/core 0.9.0 for Node.js.

- **Files of any size, in parts:** `uploadInParts($file, $name, $progress)` sends a file of up to 10 GB in parts, read from disk one part at a time. A part that fails is sent again, up to three times. The answer is `file_id`, `name`, `bytes` and `safety`.
- **Resume:** `resumeUpload($uploadId, $file)` finishes an upload that stopped halfway and sends only the parts that are missing. `abortUpload($uploadId)` drops one.
- **By itself:** `upload`, `check`, `enhance`, `fillTemplate` and `textPositions` send a file larger than 20 MB in parts, then use its `file_id`. A large file for `check` is no longer sent as the request body. Smaller files go in one request as before. `check` also takes a `file_id`.
- **Safety report:** every file is checked before Seshat works on it. Answers that take a file carry `safety`; an unsafe file throws the new `UnsafeFile` exception (`refused_unsafe`) with the report.

## 0.8.0 · 11 November 2026

- **Centred templates:** `fillTemplate($template, $rows, ['align' => 'center'])` keeps the middle of each filled text, so names on a centred certificate stay centred; `'right'` keeps the end. `align` also works per row item (`['find' => …, 'replace' => …, 'align' => 'center']`) and on the `replace_text` fix.

## 0.7.0 · 10 November 2026

- **Templates filled per row:** `fillTemplate($template, $rows, $options)` makes a copy of a PDF template per row (`['[name]' => 'Sara', '[grade]' => 'A']`, or lists of `find` / `replace`). The template is uploaded once and long lists go in steps of 1000 by themselves; the job gives a ZIP of one PDF per row, or a merged PDF, per 250 copies, and a report per row (replaced, not found, missing letters). `output`, `name_by` (name each file by a placeholder's value) and `webhook_url`. Charged per copy.
- `waitFor($jobId)` polls a job until it is done or failed.
- `textPositions($file, $find)`: page, box in points from the bottom left, font and size of every match.
- `replace_text` now finds a placeholder an editor split into several pieces, and an empty `replace` deletes the text.

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
