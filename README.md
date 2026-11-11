# Rekhu Core for Laravel

Rekhu's **knowers** in your Laravel app. A knower is a specialist that knows one part of a trade the way its best expert does: it understands what is asked, decides, explains in your user's language and learns from what your users accept or correct. The first two know print: **Seshat** knows print files, **Rekhu Machines** knows printing and finishing machines.

You install Rekhu once and add the knowers your product needs. Your tenants, users, limits and prices stay yours: every call is kept in your own usage table, so you can charge your customers per call or per plan.

```bash
composer require rekhu/core
php artisan rekhu:core --install --tenancy=multi --locales=en,nl,ar
php artisan migrate
php artisan rekhu:core --install=seshat
php artisan rekhu:core --install=machines
```

No Rekhu account yet? [Create one](https://rekhu.com/register?ref=core-laravel): it starts with $10 credit, no card needed.

## Contents

1. [Connecting](#connecting)
2. [Tenants, languages, limits and your own prices](#tenants-languages-limits-and-your-own-prices)
3. [Seshat: print files](#seshat-print-files) · [Large files](#large-files) · [Seshat AI](#seshat-ai-one-picture-improved-ai_enhance)
4. [Rekhu Machines](#rekhu-machines)
5. [Usage per tenant](#usage-per-tenant)
6. [Where Rekhu runs](#where-rekhu-runs)
7. [Errors](#errors)

## Connecting

`php artisan rekhu:core --install` asks for the app key (Rekhu account → Projects → your app → Connection → Add key), checks it with Rekhu and writes the settings to `.env`:

| Setting | |
|---|---|
| `REKHU_APP_KEY` | the app key; an app with Rekhu Assistant can use its `REKHU_ASSISTANT_KEY` |
| `REKHU_TENANCY` | `single` (the app is one customer) or `multi` (every user belongs to one of your customers) |
| `REKHU_LOCALES` | the languages your users read |
| `REKHU_KNOWERS` | optional: limit the app to these knowers; empty means every knower the key allows |
| `REKHU_DEPLOYMENT`, `REKHU_PLATFORM` | where Rekhu runs, see below |

`php artisan rekhu:core --check` calls Rekhu with the key and shows which platform answered, its version, the account and the agent. A key works only on the platform it was made on: a local or test key gives a 401 on production that says so.

## Tenants, languages, limits and your own prices

In a service provider:

```php
use Rekhu\Core\Rekhu;

Rekhu::tenantUsing(fn ($user) => $user->team);                     // a model, ['id' => …, 'name' => …] or an id
Rekhu::localeUsing(fn ($user) => $user->language);                 // else the app's locale
Rekhu::limitsUsing(fn ($tenant, $knower) => $tenant['plan'] ?? null === 'basic' ? ['per_day_per_tenant' => 50] : []);
Rekhu::priceUsing(fn ($tenant, $knower, $action, $meta) => 0.02);  // what you charge your customer for a call
```

- **Limits** a day per tenant and per user, from `limits.<knower>` in `config/rekhu.php` with your resolver on top. A call over a limit is refused with `LimitReached` before anything is sent.
- **Your price** is kept with each call next to what Rekhu charged you, so your margin shows per customer.
- **`Rekhu\Core\Events\KnowerUsed`** fires after every call with the tenant, user, knower, action, Rekhu's cost and your price: charge a plan's credit there.

A queued job without a signed-in user names the tenant: `Rekhu::machines()->forTenant('shop-42', 'Print shop 42')->…`.

## Seshat: print files

Seshat checks a print file **against the order it is for**, not against fixed rules. Each finding comes with Seshat's decision: `fix` (Seshat repairs it), `ask` (the customer chooses), `refuse` (it cannot be printed as it is) or `note`.

```php
$order = ['width_mm' => 148, 'height_mm' => 210, 'bleed_mm' => 3, 'pages' => 2, 'colour' => 'cmyk'];

$check = Rekhu::seshat()->for($user)->check($request->file('artwork'), $order, explain: true);
// status ok | warning | error, file (pages, sizes, colours, fonts, images), findings, file_id
// explanation: a summary, a text per finding and a question for each "ask", in the user's language

$fixed = Rekhu::seshat()->fix($check['file_id'], 'auto', $order);          // every fix Seshat decided on
$fixed = Rekhu::seshat()->fix($check['file_id'], [['fix' => 'scale_to_size', 'option' => 'fit'], ['fix' => 'add_bleed']], $order);
Storage::put('print/'.$fixed['name'], Rekhu::seshat()->download($fixed['file_id']));

$proof = Rekhu::seshat()->preview($fixed['file_id']);                       // a PNG with trim, bleed and safe zone
```

| It checks | It fixes |
|---|---|
| Size against the order (also A4 artwork for A5, or a different shape), trim and bleed boxes | `set_trim_box`, `add_bleed` (mirrored edges), `scale_to_size` (scale, fit, fill: fill covers the order's bleed too; `from_mm` names the size it came from) |
| Resolution of every picture as placed, RGB and spot colours, total ink | `convert_cmyk` |
| Fonts not embedded | `embed_fonts` |
| Pages against the order, text too small, lines too thin, white set to overprint, transparency, text near the trim | (asked or noted) |
| Pictures (JPG, PNG, TIFF) at the ordered size, EPS, zips of files, locked files | `picture_to_pdf` |

The order's other values: `min_ppi` (250), `safe_mm` (3), `max_ink` (320), `min_text_pt` (6), `spot_colours_allowed`. An order key Seshat does not know is refused (`InvalidRequest`, with the nearest known key), so a misspelt one never passes unnoticed. Rekhu keeps a file for a day, so a fix or a preview can follow a check without sending it again. Findings are codes with values: show the explanation, or your own text per code.

**Every code, decision and fix in one list.** `Rekhu::seshat()->codes()` (free) answers the versioned list to map your own tool's names against: each code with its levels, decisions, fix and options, and each fix with the options it takes.

**A question without a model.** Every finding with the decision `ask` carries its `options`, so you can offer the choice without `explain`:

```php
// ['code' => 'size_differs', 'decision' => 'ask', 'options' => [
//     ['id' => 'fix:scale_to_size:fit', 'fix' => 'scale_to_size', 'option' => 'fit', 'about' => 'fit inside the ordered size'],
//     ['id' => 'fix:scale_to_size:fill', 'fix' => 'scale_to_size', 'option' => 'fill', 'about' => 'fill the ordered size, cropping the edges'],
//     ['id' => 'upload_new', 'about' => 'the customer sends a new file']]]
```

**Fixes only, your own check.** An app with its own preflight sends the file with `upload()` (free) and asks for the fixes it found: `fix($fileId, [['fix' => 'add_bleed'], ['fix' => 'convert_cmyk']], $order)`. An unknown fix or option is refused with an `InvalidRequest` listing the allowed ones, and nothing is charged. `applied` lists every change, also a side effect (fonts embedded by the CMYK conversion, with `side_effect_of`), and `resolved` the finding codes the file no longer has.

**Fonts.** A file that uses fonts without carrying them gets them from Seshat's font library (open-licence fonts by their PostScript name, such as `Montserrat-Bold`): `fix($id, [['fix' => 'embed_fonts']])` puts in the same font; `['fix' => 'embed_fonts', 'option' => 'lookalike']` also puts in a look-alike for the others. `applied` says per font `same`, `lookalike` (with `as`), `not_available` or `not_possible`: a font is never swapped without a word. `fonts()` (free) lists the library for your own text editor, and `fontFile('Montserrat-Bold')` (free) gives the font itself with its licence, for an app that sets text with its own tool.

**Text and fonts.** `replace_text` changes words at the same place and size, and `replace_font` sets all text of one font in a library font. Both can come several times in one call, applied in order:

```php
Rekhu::seshat()->fix($id, [
    ['fix' => 'replace_text', 'find' => 'Summer sale', 'replace' => 'Winter sale'],
    ['fix' => 'replace_font', 'from' => 'ArialMT', 'to' => 'Montserrat-Bold'],
], $order);
```

New text goes in the file's own font when it has the letters, else the same font from the library, else `to`; otherwise it stays and `applied` names the missing letters. Text does not re-wrap: for a new paragraph, change it before the PDF is made.

**Large files** are streamed from their path, and a file larger than 20 MB goes up in parts by itself (see [Large files](#large-files)); `downloadTo($fileId, $path)` writes the result straight to disk. `remove($fileId)` deletes a kept file at once. File names keep every character (Müller, مرحبا).

**Every file is checked for safety** before Seshat works on it: a virus scan, what the file really is, and anything in it that could act on its own. The answer of `upload`, `check` and the other calls that take a file carries the report as `safety`. An unsafe file is refused with an `UnsafeFile` exception (422, `refused_unsafe`) that says why.

**What it costs.** `meta.usage` has one line per charged item: the check (`seshat`) and, with `explain`, the explanation (`seshat_explain`, the model call). A refused call, or a fix with nothing to do, costs nothing.

**Everything else, by task:** colour through a print profile, ink, black text, white overprint, spot colours, PDF/X, pages, merge, split, crop marks and pages as pictures are all on [rekhu.com/docs/seshat](https://rekhu.com/docs/seshat), each with what to send, what comes back and one call.

## Large files

A file of any size (up to 10 GB) goes up in parts of 32 MB or more. You do not have to do anything for it: `upload`, `check`, `enhance`, `fillTemplate` and `textPositions` send a file larger than 20 MB in parts by themselves, then work on it by its `file_id`. A smaller file still goes in one request.

To send a file in parts yourself, for example to show how far it is:

1. Call `uploadInParts()` with an upload, a path, or the bytes with a name:

    ```php
    $file = Rekhu::seshat()->uploadInParts($path, 'catalogue.pdf', function (int $sent, int $total, string $uploadId) {
        cache()->put('upload:'.auth()->id(), ['id' => $uploadId, 'done' => $sent / $total]);
    });
    ```

2. The file is read from disk one part at a time, so it is never whole in memory. A part that fails is sent again, up to three times.
3. When all parts are in, Rekhu checks the file for safety. The method waits for that check (every 2 seconds, up to 30 minutes).
4. You get `['file_id' => …, 'name' => …, 'bytes' => …, 'safety' => […]]`. Use the `file_id` with `check`, `fix`, `fillTemplate` and the rest: `Rekhu::seshat()->check($file['file_id'], $order)`.
5. An unsafe file throws `UnsafeFile`; `$e->safety` has the report with the findings and why.

**When the process stopped halfway** (a deploy, a dropped connection), Rekhu keeps the parts it has for 24 hours:

1. Keep the `$uploadId` your progress function gets: it gets it once before the first part.
2. Call `Rekhu::seshat()->resumeUpload($uploadId, $path)` with the same file.
3. Only the parts Rekhu does not have yet are sent; then the file is checked and you get the same answer as from `uploadInParts()`.
4. To drop an upload instead, call `Rekhu::seshat()->abortUpload($uploadId)`.

The safety report: `verdict` (`clean`, `remove` or `refuse`), `virus` (`status`, and `signature` when one was found), `kind` (what the file really is) and `findings`, each with `code`, `decision` (`refuse`, `remove` or `note`) and `about`.

## Seshat AI: one picture improved (ai_enhance)

For a platform with its own preflight that only wants Seshat's image work, or next to the preflight. The module is switched on per application by Rekhu; `modules()` tells which ones this key may use.

| Mode | What it does | Options |
|---|---|---|
| `upscale` | More pixels, up to 4×: to a resolution at a print size | `ppi`, `width_mm`, `height_mm`, or `factor` |
| `extend` | Grows the picture beyond its edges (bleed); a little per side on CPU | `all_mm`, or `top_mm` `right_mm` `bottom_mm` `left_mm` (or `_px`) |
| `denoise`, `sharpen` | Cleans JPEG artefacts and noise; crisper edges | `strength`, `amount` |
| `restore` | An old or scanned photo: scratches filled, noise and faded colour corrected | |
| `remove_background` | Transparent PNG, or a solid colour | `background: '#ffffff'` |
| `replace_background` | A new background from a picture you send | `background_file_id` (from `upload`) |
| `remove_object` | Fills an object or blemish from its surroundings | `region: {x, y, w, h}`, or `mask_file_id` |
| `vectorize` | A logo or line drawing as SVG. A photo is refused (`not_vectorizable`) | |
| `colour_correct` | Exposure and white balance | |

Every answer says what it invented in `warnings` (an edge, a filled area, a reconstructed detail), with a `confidence` from 0 to 1. RGB stays RGB, and CMYK stays CMYK unless the mode needs otherwise. Files are kept a day and never used for training. A large picture runs as a job: poll it, or give a webhook URL. The webhook is signed `x-rekhu-signature: t=…,v1=…` with the `webhook_secret` the call answered. Each mode is priced on its own, and `meta.usage` says per item what it cost.

Limits:
- at most 40 megapixels in and 120 megapixels out;
- upscaling at most 4×;
- extending at most 12 % of a side on CPU;
- removing at most a quarter of the picture.

```php
// a key with only ai_enhance
Rekhu::seshat()->modules();          // ['modules' => ['preflight' => false, 'ai_enhance' => true], 'modes' => [...]]

$r = Rekhu::seshat()->forTenant($tenant->uuid, $tenant->name)
    ->enhance($request->file('photo'), ['denoise', 'upscale'], ['ppi' => 300, 'width_mm' => 100, 'height_mm' => 70], ['webhook_url' => route('rekhu.webhook')]);
// ['file_id' => 'f_…', 'format' => 'png', 'width_px' => 1182, 'height_px' => 788, 'ppi' => 300, 'confidence' => 0.8,
//  'warnings' => [], 'meta' => ['usage' => [['module' => 'ai_enhance', 'item' => 'seshat_upscale', 'units' => 1, 'cost_usd' => …]]]]
// or, for a large picture: ['status' => 'queued', 'job_id' => 'j_…', 'webhook_secret' => 'whsec_…']

$preview = Rekhu::seshat()->enhancePreview($r['file_id']);          // ['image' => 'data:image/png;base64,…'] before | after
Storage::put('print/photo.png', Rekhu::seshat()->download($r['file_id']));
Rekhu::seshat()->poll('j_…');                                       // queued | running | done with result | failed with error

// in your webhook controller
abort_unless(Rekhu::verifyWebhook($request->getContent(), $request->header('x-rekhu-signature'), $storedSecret), 401);
```

**Inside the full Seshat:** with `ai_enhance` on, a picture below the order's resolution gets the fix `upscale_ai` in its finding. `fix($fileId, [['fix' => 'upscale_ai']], $order)` upscales the pictures inside the PDF, in place.

## Rekhu Machines

Machines are sent as a **Rekhu machine profile**. Map your own machine records to it:

| Field | |
|---|---|
| `type` | printing, cutting, folding, binding, lamination, die_cutting |
| `method` | offset, digital_toner, digital_liquid, inkjet_sheet, large_format, guillotine |
| `maker`, `model` | as on the machine |
| `sheet_max_mm`, `sheet_min_mm` | `[width, height]` |
| `gsm` | `[min, max]` paper weights |
| `colour_units`, `perfecting` | units (offset), perfecting yes / no / option |
| `speed`, `speed_unit` | sheets/h (offset, inkjet), A4 pages/min (digital), m²/h (large format) |
| `makeready_minutes`, `makeready_waste_sheets`, `running_waste_percent` | |
| `gripper_mm`, `margins_mm` | `{top, right, bottom, left}` |

```php
$machines = Rekhu::machines()->for($user);

$found = $machines->identify('Exampla Offset 74-4 with coater, 2009');
// match: exampla-offset-74 (sure), details: 4 units, coater, 2009; questions when it could be another model

$profile = $machines->profile($found['match']['id'], ['colour_units' => 4]);
// every value with its source: given, library, typical (with the usual range)

$check = $machines->check($machineAsProfile, 'exampla-offset-74', explain: true);
// findings: speed_above_model, sheet_above_model, gsm_outside_model, waste_unusual, missing_for_price, …
// with explain: a summary and a text per finding in the user's language

$choice = $machines->choose(
    ['width_mm' => 85, 'height_mm' => 55, 'quantity' => 5000, 'colours_front' => 4, 'colours_back' => 4, 'gsm' => 350],
    [['ref' => 12, 'name' => 'Offset 74-4', 'method' => 'offset', 'sheet_max_mm' => [530, 740], 'speed' => 15000, 'hourly_rate' => 140], …],
);
// the machines that can do it, cheapest (with hourly rates) or fastest first, each with why; the others with why not

$machines->explain('Can I print 400 g/m² board on it, and what slows it down?', $machineAsProfile, 'exampla-offset-74');
$machines->models();   // the library's models, for a picker (not charged)
```

Every answer has a `ref`. Tell Rekhu what your user did with it, so the knower learns:

```php
$machines->feedback($check['ref'], 'corrected', ['speed' => 12000]);   // accepted, corrected or rejected
```

Findings are codes with values: show Rekhu's message, or your own text per code in your own language.

## Usage per tenant

```bash
php artisan rekhu:core --usage --month=2026-11
php artisan rekhu:core --usage --month=2026-11 --tenant=shop-42 --csv=storage/rekhu-2026-11.csv
```

Calls, model calls, what Rekhu charged and your own price, per tenant, knower and action. The rows are in `rekhu_usage` for your own reports.

## Where Rekhu runs

| `REKHU_DEPLOYMENT` | |
|---|---|
| `cloud` | Rekhu's platform (the default) |
| `private` | Rekhu inside your own network: `php artisan rekhu:core --install --deployment=private --platform=https://rekhu.intranet.example.com` |

The knowers, their answers and this package are the same in every deployment.

## Errors

Every refusal is a `Rekhu\Core\Exceptions\RekhuException` with `status` and `type`, and its own class, so you catch what you handle:

| Class | When |
|---|---|
| `InvalidKey` | 401: no key, a revoked key, or a key from another Rekhu platform |
| `ModuleNotEnabled` | 403: the knower's module is off for this application |
| `RegionUnavailable` | 403: the account is set to Europe only and this call has no engine in the EU |
| `CreditExhausted` | 402: the account's credit is used up |
| `AppPaused` | 402: the application is paused or at its monthly limit |
| `FileNotFound` | 404: a kept file is past its day or removed |
| `InvalidRequest` | 422: refused before anything ran, with what was wrong; not charged |
| `UnsafeFile` | 422 `refused_unsafe`: the file is not safe; `safety` has the report when Rekhu gave it |
| `RateLimited`, `Unavailable` | 429, or a 5xx: after the client tried again when the platform was unreachable |
| `LimitReached` | your own limits (`scope` tenant or user) |

When the platform is briefly unreachable (a dropped connection, or a 502, 503 or 504 without Rekhu's own error), a call is tried again up to 4 times in all, after 2, 4 and 8 seconds. A busy Rekhu (429, 529, or a 5xx with `overloaded_error` or `rate_limit_error`) is tried again up to three times. Any other 5xx that carries Rekhu's own error is not tried again. Every charged call carries an `Idempotency-Key`, so a retry is never charged twice: Rekhu answers the first result again, with `meta.replayed`. In a queued job there is no signed-in user: name it with `->for($user)` or `->forTenant($id, $name)`.
