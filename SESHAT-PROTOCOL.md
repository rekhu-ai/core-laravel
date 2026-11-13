# Seshat protocol, version 1

What a client (the file check widget `@rekhu/seshat-widget`, or an app's own screens) and a **Rekhu server package** in the app's back end say to each other. The server packages implement it: `@rekhu/core` for Node.js and `rekhu/core` for Laravel.

The server package runs inside the app's back end and calls Seshat with the app key. The browser never talks to Rekhu and never holds a Rekhu key.

```
browser (widget or own screens)  ──protocol 1──▶  server package in the app  ──▶  Rekhu platform  ──▶  Seshat
```

## The flow

1. **Check:** the client sends a file and its specs. The answer is the findings, each with Seshat's decision.
2. **Read:** the client shows the findings and a proof of each page with the trim, bleed and safe zone.
3. **Fix:** the client asks for the fixes Seshat decided on. The answer is a new file.
4. **Verify:** the fixed file is checked again in the same call. The answer carries that check.
5. **Done:** the client downloads the fixed file.

## Endpoint

One base URL, chosen by the app (for example `/rekhu/seshat`):

| Method and path | Does |
|---|---|
| `POST {base}/check` | checks a file against its specs |
| `POST {base}/fix` | fixes a checked file and checks the result again |
| `GET {base}/preview?file={file_id}&page={n}` | one page as a PNG with its trim, bleed and safe zone |
| `GET {base}/files/{file_id}` | the file itself, to download |

## Who is checking

The server package uses the app's own login: a session cookie (with the CSRF token on a same-domain page), an API token (`Authorization: Bearer …`), or whatever else the app uses. The protocol does not define it; the client passes it on.

- No signed-in user: `401` (or `419` for an expired CSRF token).
- Every request may carry `X-Rekhu-Protocol: 1`. A server package that does not speak that version answers `400`. On `POST` the header is required (a form on another site cannot send it); without it the answer is `403`.
- Every check and fix is charged to the app's Rekhu account and kept in its usage ledger for the signed-in user and their tenant.

## Specs

What the file is checked against. The server package keeps only these fields and drops anything else. The app's own defaults (see the server package's configuration) fill what the client does not send.

| Field | |
|---|---|
| `width_mm`, `height_mm` | the trimmed size; required |
| `bleed_mm` | the bleed around the trim (default from the configuration, usually 3) |
| `pages` | the number of pages expected |
| `colour` | `cmyk` or `any` |
| `min_ppi` | the lowest resolution a picture may have as placed |
| `max_ink` | the highest total ink, in percent |
| `safe_mm` | the distance text and logos keep from the trim |
| `profile` | the print profile the file is checked for |
| `spot_colours_allowed` | `true` when spot colours may stay |

## POST {base}/check

Request, `multipart/form-data`:

| Field | |
|---|---|
| `file` | the print file: PDF, EPS, ZIP or a picture; at most the server's limit (`413` above it) |
| `specs` | the specs as JSON |
| `locale` | optional: the language the person reads (BCP 47, for example `nl-NL`): of the findings' sentences and the explanation. Absent: the browser's `Accept-Language`, else English |
| `explain` | optional: `0` for no explanation; on by default |
| `recipe` | optional: the name of a recipe kept in the Rekhu account (its specs and fixes, set once per product); the specs sent here go on top of it. Only when the server package allows recipes |

Answer `200`, JSON:

```json
{
  "protocol": 1,
  "file_id": "f_77a04278ad5f45c7a6a49516",
  "name": "flyer.pdf",
  "pages": 1,
  "specs": { "width_mm": 148, "height_mm": 210, "bleed_mm": 3 },
  "status": "warning",
  "findings": [
    { "code": "bleed_missing", "decision": "fix", "about": "The page has no bleed", "fix": "add_bleed", "pages": [1] },
    { "code": "resolution_low", "decision": "ask", "about": "Pictures are a little small for this size and may print soft", "pages": [1] }
  ],
  "explanation": {
    "summary": "Your flyer is nearly ready …",
    "findings": [{ "code": "resolution_low", "text": "…", "question": "Can you send the photo in a higher resolution?" }]
  }
}
```

| Field | |
|---|---|
| `file_id` | the checked file, kept by Rekhu for 24 hours; send it to `fix`, `preview` and `files` |
| `status` | `ok`, `warning` or `error`: Seshat's verdict on the whole file |
| `findings[]` | each problem: `code`, `decision`, `about` (the sentence for the person, in their language: English, Dutch, German, French or Arabic, else English), `fix` (the fix Seshat will make, for `decision: fix`), `pages`, and `file` for a file inside a ZIP |
| `explanation` | for the person who sent the file, in their language: a `summary`, and per finding a `text` and, for `ask`, a `question`. `null` when it is off or could not be written; the findings are complete either way |

The decisions:

| `decision` | Meaning |
|---|---|
| `fix` | Seshat fixes it with `fix` |
| `ask` | the person who sent the file must choose (send a better file, or keep it) |
| `refuse` | the file cannot be printed as it is |
| `note` | for information |

## POST {base}/fix

Request, JSON:

```json
{ "file_id": "f_…", "specs": { … }, "fixes": ["add_bleed", "convert_cmyk"], "recipe": null, "locale": "nl-NL" }
```

| Field | |
|---|---|
| `file_id` | the checked file |
| `specs` | the same specs as the check |
| `fixes` | optional: the fixes to make, by name, from the check's `fix` fields (the person may have chosen some). Absent: every fix Seshat decided on. The server package keeps only the fixes its configuration allows; an empty list makes nothing |
| `recipe` | optional: the same recipe as the check |
| `locale` | optional: the language of the check of the fixed file's sentences, as for the check |

The fixes are made, and the fixed file is then checked again against the same specs.

Answer `200`, JSON:

```json
{
  "protocol": 1,
  "file_id": "f_8a99d3e70938489b9846fe4b",
  "name": "flyer-print.pdf",
  "fixed_from": "f_77a04278ad5f45c7a6a49516",
  "applied": [
    { "fix": "add_bleed", "about": "adds bleed by mirroring the page edges, and sets the trim box" },
    { "fix": "convert_cmyk", "about": "converts RGB, Lab and spot colours to CMYK …" }
  ],
  "check": { "status": "warning", "pages": 1, "findings": [ … ] }
}
```

| Field | |
|---|---|
| `file_id` | the fixed file, kept 24 hours |
| `applied[]` | each fix that changed the file, with what it does |
| `check` | the check of the fixed file: the verification. The findings left are the ones a fix cannot solve (`ask`, `refuse`, `note`) |

Nothing to fix, or no allowed fix left: `200` with `applied: []`, `check: null`, and `file_id` is the checked file.

## GET {base}/preview?file={file_id}&page={n}

Answer `200`, `image/png`: page `n` (from 1) with the trim (green), bleed (blue) and safe zone (orange, dashed) drawn on it. It can be used straight as an `<img src>`. The preview is part of the check and is not charged.

## GET {base}/files/{file_id}

Answer `200`: the file, with its own `Content-Type` and `Content-Disposition` (its name). A file past its 24 hours: `404`.

## Errors

Every error is JSON, `{ "error": { "type": "…", "message": "…" } }`. Show `message` to the user as it is.

| Status | When |
|---|---|
| `400` | the specs are not valid JSON, the size is missing (and no recipe), or the protocol version is not spoken |
| `401`, `419` | not signed in, or the CSRF token expired |
| `403` | no `X-Rekhu-Protocol` header on a `POST` |
| `404` | no such file, or it is past its 24 hours |
| `413` | the file is larger than the server's limit |
| `422` | the file is refused: not a print file, or not safe (`type: refused_unsafe`) |
| `429` | the app's own limit a day for this user or tenant |
| `503` | the check is not available right now. Problems with the app's Rekhu account (key, credit, modules) are this, with no detail for the user; the server package logs the cause |

## Changes

- **1** (November 2026): check, fix with verification (all or chosen fixes), recipes, preview, download. Since widget 0.15.0 and core 0.15.0: the findings' sentences in the person's language, and `locale` on `fix`.
