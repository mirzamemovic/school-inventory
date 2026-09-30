# School Inventory — technical context

A complete briefing on this codebase. Tables, routes, invariants and the traps already found.

The authoritative specification is `SPECIFICATION.md` (§0–§13). Where this document and
that one disagree, `SPECIFICATION.md` wins. Retention rules live in `RETENTION.md`,
hosting in `DEPLOYMENT.md`.

---

## 1. What it is

Inventory and asset management for a small private gymnasium (high school) in
Kosovo. It replaces paper. Built for these numbers and no larger:

| | |
|---|---|
| Students | ~100 |
| Staff | ~20 |
| Admin accounts | 2 |
| Labelled assets | 80–120 |
| Unlabelled furniture | ~500, counted per room |
| Book titles / copies | ~30 / ~800 |
| Book loans per year | ~800 |
| Problem reports | 5–15 per month |

**No queues beyond Laravel's defaults, no caching layer, no search engine, no
API.** Scale is not a design consideration. Several deliberate choices (filtering
in PHP rather than SQL, six LIKE queries for search) are correct *because* of
these numbers and would be wrong at 100×.

**The three things it must do well**

1. Someone points a phone at a QR sticker and reports a fault in under 30
   seconds, with no login.
2. The director sees what is broken and closes the loop.
3. Books get issued to a whole class in September and chased in June.

**The audience is a teacher in their fifties on a cracked Android phone.** Every
UI decision serves that person.

---

## 2. Stack

- PHP 8.4 target, `^8.3` minimum in `composer.json` (dev machine runs 8.3.33)
- Laravel 13.34
- Blade + **HTMX 2.0.10**. No SPA, no React/Vue/Livewire/Inertia
- Tailwind CSS 4 (via `@tailwindcss/vite`), compiled by Vite
- MySQL in production, **SQLite in development and tests**
- Pest 4.7 for tests
- `endroid/qr-code` ^6.0 — QR drawn on demand, never stored
- `barryvdh/laravel-dompdf` ^3.1 — label sheets

Deployment target is **shared cPanel hosting** with no guaranteed SSH, Composer
or Node. Assets are built locally and uploaded.

---

## 3. Access model

Two levels. Only one has a password.

**Level 1 — anyone, no login.** Reached by scanning a QR or following a link.
These routes carry **no auth middleware at all**:

- `/a/{tag}` — asset page and report entry point
- `/a/{tag}/report` — the report form
- `/a/{tag}/inspect` — the "I checked this" button for compliance items
- `/c/{room}` — supplies cupboard page
- `/r/{reference}` — check the status of your own report
- `/privacy` — what is collected and kept
- `/photo/{path}` — photo serving (asset photos only; report photos need a login)
- `/inspector/{phone}` — the signed, once-only, 30-minute code that sets up an
  inspector's phone (opening it changes nothing; only its button does)

Identity is a name typed once and kept in a one-year cookie (`si_reporter`,
JSON: `{token, name, type}`). **That cookie is a convenience, not a credential.**
It never unlocks anything and is never checked as a permission — with one
narrow exception: a device token enrolled in `inspector_phones` makes that
phone's inspections count towards the schedule. See `SECURITY.md`.

**Level 2 — admin, real login.** Everything under `/admin/*`. Two accounts.

- **No registration route exists** (asserted by a test)
- **No password reset UI.** `php artisan user:password {email}` instead, which
  also signs the account out everywhere
- Login throttled 5/minute per email **and address**, plus 100/hour per email
  from anywhere; "keep me signed in" lasts 14 days

**There is no teacher login, deliberately.** Twenty accounts for people who touch
the system four times a year means twenty forgotten passwords.

**The one exception:** class teachers chasing book debts get a **signed,
temporary, per-class URL** — `/class/{class}?v=…&expires=…&signature=…` —
generated from the Returns screen, expiring at the end of the academic year.
`v` is a per-class version kept in `settings` (`class_link_version:{class}`);
*Make a new link* moves it on and every older copy stops working. Without it the
same class and expiry always signed to the same URL, so a leaked link could not
be withdrawn. Built only by `App\Services\ClassDebtLinks`.

---

## 4. Data model

25 tables; 9 are Laravel's own (cache, jobs, sessions, migrations, users, …).

### `rooms`
`id, name, floor, type, last_counted_at, timestamps`
`type` ∈ classroom/lab/office/storage/corridor/offsite. One `offsite` room named
"Off-site / with staff" is seeded — it is where genuinely mobile equipment lives.

### `room_contents`
`id, room_id, item_name, quantity, condition, timestamps`
Unlabelled furniture, counted per room, never individually labelled.

### `categories`
`id, name, prefix, needs_inspection_default, default_inspection_interval_months, timestamps`
`prefix` is **not in the original spec** — added because the asset tag prefix must
come from the category and stay stable when a category is renamed. Frozen once
any asset uses it. Seeded with 12 categories.

### `assets`
```
id, asset_tag, tag_sequence, official_number, category_id, name, make, model,
serial_number, photo_path, purchase_date, purchase_price, supplier, invoice_ref,
funding_source, warranty_end, room_id, assigned_person_id, status, condition,
expected_life_years, disposal_date, disposal_method, needs_inspection,
inspection_interval_months, last_inspected_at, notes, timestamps
```
- `tag_sequence` (int, unique) backs the printed tag so the next number never has
  to be parsed out of a string. **Not in the spec** — added for this reason.
- `room_id` is nullable **at the database level only**, because a pre-printed
  blank label has no room yet. Validation requires it for every non-blank asset.
- `category_id` and `name` nullable for the same reason.
- `room_id` (where it lives) and `assigned_person_id` (who is answerable) are
  **independent**. Assigning a custodian must not remove an item from its room,
  or the printed room inventory sheet lies.
- `status` and `condition` are **two separate fields**. A working laptop in a
  cupboard is `in_storage` + `good`.
- `last_inspected_at` is a **denormalised cache** of the newest **counted**
  `inspections` row. Derived only; never edited by hand.

### `asset_events` — the single audit log
`id, subject_type, subject_id, action, actor_type, actor_id, actor_name, device_token, ip_address, meta (JSON), created_at`

- **Append only. No `updated_at`.** `AssetEvent::booted()` throws
  `AuditLogIsAppendOnly` on any update or delete.
- Polymorphic via an enforced morph map: `asset, report, person, supply,
  inspection, licence, inspector_phone, user`. `user` is the subject of what an
  admin does to the system rather than one record (exporting, blocking a
  device, replacing a class link). `Relation::enforceMorphMap()` means an
  unmapped model throws rather than writing a class name.
- `ip_address` is **not in the spec** — added per §13's requirement to store IP
  on public actions.
- 31 actions: the 18 from §5, plus `licence.created/edited/renewed/deleted`,
  `person.erased`, `data.retention_enforced`, `data.exported` (§16),
  `device.blocked/unblocked`, `book.class_link_replaced` and
  `inspector_phone.invited/claimed/withdrawn`. All in `App\Support\EventAction`.
- `meta` stores before/after pairs: `{"room":{"from":"Lab 2","to":"Room 14"}}`.

**Honesty constraint:** public actors are self-declared, never verified.
`actor_type` distinguishes them and the UI styles them as "Self-reported".

### `inspections`
`id, asset_id, inspected_at, inspector_name, result, counted, notes, next_due_at, device_token, created_at`
A **domain table, not a second log** — an inspection has legal weight for fire
safety. `result = fail` sets the asset's condition to `needs_attention` **and
automatically raises a report**. `inspector_name` is free text with no FK.
`counted` (**added later**) is true only for a check from an enrolled inspector
phone or a signed-in admin; only counted checks set `next_due_at` and move
`assets.last_inspected_at`. Rows from before the column existed are counted.

### `inspector_phones` (**added later**)
`id, label, device_token, invite_expires_at, invited_by, claimed_at, withdrawn_at, last_used_at, timestamps`
The phones whose inspections count. Invited by an admin (a once-only signed
code, 30 minutes), claimed by scanning it on the phone, withdrawn by an admin
— never deleted. `device_token` is indexed, not unique: a withdrawn phone keeps
its token for the record and may be set up again.

### `licenses`
`id, name, vendor, type, licence_model, seats_total, seats_used, purchase_date, expires_at, renewal_cost, auto_renew, account_reference, asset_id, notes, timestamps`

- **A licence is not an asset.** No room, no condition, no status, no QR.
- `expires_at` **nullable** = perpetual = *never expires*, never "overdue". This
  is called out in the spec as the most likely bug; `LicenseExpiryTest` covers it
  from ten angles.
- `seats_total`/`seats_used` are plain integers. **No seat-assignment table.**
- `asset_id` is one nullable FK with `nullOnDelete`. **Not a many-to-many.**
- Column is `licence_model` (UK) while the table is `licenses` (US), and the four
  audit actions are `licence.*`. That mixed spelling is what the spec dictates
  and is implemented verbatim.

### `reports`
```
id, reference, asset_id, reporter_name, reporter_type, device_token, ip_address,
description, photo_path, status, asset_status_before, asset_condition_before,
created_at, updated_at, first_viewed_at, first_viewed_by, acknowledged_at,
resolved_at, resolved_by, resolution_note
```
- `reference` (**not in spec**, required by `/r/{reference}`) — 6 chars, ambiguous
  characters removed.
- `asset_status_before` / `asset_condition_before` are snapshotted at creation so
  resolution restores the real prior state.
- Four timestamps. `first_viewed_at` is stamped on first admin page load and
  **never overwritten**. The gap between *viewed* and *acknowledged* is where
  things rot, which is why both exist.

### `people`
`id, name, type, class, email, active, left_at, timestamps`
Staff and students in one table. **Deactivate, never delete** — old loans point
here. `left_at` (**added later**) is maintained automatically by `PersonObserver`
and drives retention.

### `book_titles` / `book_loans`
`book_titles: id, title, subject, grade, isbn, total_copies`
`book_loans: id, person_id, book_title_id, issued_at, due_at, returned_at, condition_on_return, notes, academic_year`
There is **no "grade book set" table** — the Grade 10 set *is* every title where
`grade = 10`.

### `supplies`
`id, name, unit, quantity, minimum_level, room_id, storage_location, last_counted_at, timestamps`
`room_id` is **not in the spec** — added because the public cupboard page is
addressed by room, and matching free text to a room name would break on rename.

### `settings`
Key/value. Keys: `school_name`, `student_reporting_enabled`, `academic_year`,
`notification_email` (present, unused).

### `label_queue_items`, `blocked_devices`
Per-user pending label sheet (a table, not the session, so labels queued on a
phone are still there at the desktop). Block list is one token per row.

---

## 5. Enums

All PHP backed enums with `label()` returning a translation key; status-bearing
ones also carry `badgeClasses()`.

`RoomType` · `AssetStatus` (in_use, in_storage, under_repair, retired, lost,
**unassigned**) · `AssetCondition` (good, needs_attention, broken) ·
`FundingSource` (budget, donation, project, unknown — matters in Kosovo for
disposal obligations) · `ActorType` · `ReportStatus` · `ReporterType` ·
`PersonType` · `LoanCondition` · `SupplyUnit` · `InspectionResult` ·
`LicenseType` · `LicenseModel`

`AssetStatus::assignable()` excludes `unassigned` — that status is system-set for
blank labels and an admin must not choose it.

---

## 6. Domain invariants

Break any of these and the system becomes wrong rather than merely buggy.

1. **A tag is write-once.** `Asset::booted()` throws `TagIsWriteOnce` if
   `asset_tag` or `tag_sequence` changes. Absent from `$fillable`; absent from
   the edit form. A recategorised projector keeps `INV-PRJ-0031`.
2. **A reprint carries the identical tag.** A new one would sever the item from
   its history.
3. **The QR is never stored.** Pure function of the tag, drawn on demand. No
   `qr_path` column, no generation job.
4. **The audit log is append-only.** Never updated, never deleted — including by
   erasure, which rewrites columns instead.
5. **The public asset page is a DTO, not a model.** `PublicAssetView` carries
   only tag, name, room, status, photo, open-report flag. Price, serial,
   supplier, warranty and repair history *cannot* leak because they are not on
   the object.
6. **Report resolution restores the snapshot,** silently, and only if the asset
   is still `under_repair`.
7. **A null `expires_at` means never expires,** never overdue.
8. **Status ≠ condition.** Two fields, always.
9. **An open book loan is never deleted,** at any age.
10. **Every write path writes to `asset_events`.**
11. **Anonymous input may raise a warning, never silence one.** A public
    recount can only confirm or lower stock; a public inspection only counts
    from an enrolled phone. Everything else a stranger can do only adds to what
    the director sees. See `SECURITY.md` before adding any public write.
12. **A signed link that shows personal data must be withdrawable.** A signed
    URL alone cannot be recalled; the class link carries a version for that.

---

## 7. Routes

100 routes. Public ones are listed in §3. Admin structure:

```
/admin                        dashboard (needs-attention + four numbers)
/admin/search                 global search (auth — reads serials + references)
/admin/assets                 index / create / show / edit
/admin/assets/serial-check    HTMX duplicate-serial warning
/admin/assets/{a}/move|assign|status|retire
/admin/assets/{a}/label|label-queue
/admin/intake                 bulk intake loop
/admin/labels                 queue, blank generation, reprint
/admin/reports                queue (3 tabs) / detail / acknowledge|resolve|dismiss
/admin/books                  titles / issue / returns / student record
/admin/manage                 landing for the below
/admin/rooms                  index / show / sheet / counted / contents
/admin/people                 index / create / edit / import / (de)activate
/admin/supplies               index / edit / adjust (HTMX)
/admin/licenses               index / create / show / edit / renew / destroy
/admin/inspections/overdue
/admin/categories /admin/activity /admin/settings /admin/users
```

**Route ordering matters twice:** `assets/create` and `assets/serial-check` must
precede `assets/{asset}`, because `Asset::getRouteKeyName()` is `asset_tag` and
would otherwise swallow them. Same for `rooms/create` before `rooms/{room}`.

Navigation is **exactly five items** — Home · Assets · Reports · Books · Manage —
with a red count badge on Reports. Tests assert the count stays at five.

---

## 8. Application structure

**Services** (`app/Services`) — where the real logic lives:

| Class | Responsibility |
|---|---|
| `AssetTagGenerator` | `INV-{PREFIX}-{0000}`, global sequence, retries on collision |
| `AuditLogger` | The only writer to `asset_events`; `changes()` builds before/after meta |
| `QrImage` | SVG for screen, PNG data URI for PDF |
| `LabelSheet` | A4 24-up pagination with start-position offset |
| `PhotoStorage` | GD re-encode, EXIF stripped, random name, outside webroot |
| `DeviceIdentity` | The one-year cookie; token memoised per request |
| `NeedsAttention` | Home's list, max 5 rows, several sources |
| `BookService` | Class-level issue and return |
| `InspectionRecorder` | Records, recalculates cache, raises a report on failure |
| `GlobalSearch` | Six LIKE queries with an explicit ESCAPE clause |
| `SerialNumberCheck` | Duplicate serial lookup — warns, never blocks |
| `RetentionPolicy` | Answers what has expired; deletes nothing itself |
| `PersonEraser` | Pseudonymisation including meta-JSON scrub |

**Support** (`app/Support`): `EventAction` (action constants), `Actor` (who did
it), `EventSentence` (renders a log row as a sentence), `AcademicYear`,
`PublicAssetView` (the enumeration-defence DTO).

**Observers**: `PersonObserver` maintains `left_at`.

**Events**: `ReportCreated` fires on every new report. Nothing listens yet —
notifications are in-app only — but an email listener can be added without
refactoring.

---

## 9. Core workflows

### A. Reporting a fault (90% of usage)

1. Phone camera → `/a/{tag}`. No login, no redirect, no interstitial.
2. Page shows photo, name, room, status badge, one large red button. **Nothing
   else.**
3. **If an admin is signed in, the same URL redirects to the full admin page** —
   and to bulk intake if the label is still blank.
4. If a report is already open: yellow banner *above* the button, and the button
   becomes **Add more information**. This kills most duplicates.
5. Form: "Who are you?" (first visit only) → three tap-chips → free text →
   optional photo → Send.
6. On submit: snapshot status+condition onto the report, flip the asset to
   `under_repair`, fire `ReportCreated`, write two audit events, redirect to
   `/r/{reference}` with a plain-language confirmation.
7. A second person reporting the same fault **appends to the open report**
   rather than creating a duplicate.

### B. The director closes the loop

Queue → three tabs (Open / In progress / Closed), opens on Open, oldest first,
rows over 7 days flagged red. Opening a report stamps `first_viewed_at` once.
The detail page shows **that item's previous reports**, which is what turns a
one-off into a replacement decision. Three buttons: Working on it / Fixed / Not a
problem, plus a note box. Fixed and Not-a-problem **restore the snapshot
silently**; a small "Set a different state" link covers partial repairs.

### C. Books

September: pick a class → students appear with that grade's titles pre-ticked →
confirm the whole class. June: pick a class → tick off returns, printable. A
signed link shares the debt list with the class teacher. Mid-year: one student's
page, tick or untick.

### D. Labels and intake

**Path A (data first):** add asset → tag assigned on save → *Add to label queue*
(default) or *Print this one now*.
**Path B (label first):** generate N blank labels → `assets` rows with only a tag
and `status = unassigned` → print → stick → scan each → **bulk intake**: four
fields, *Save and scan next*, straight back to the scan box. **This loop must
never dump the user into a list.**

Sheet printing always offers a **start-position picker** (1–24) so a part-used
sticker sheet can be reused. Labels carry exactly three things: QR, tag in large
mono, school name.

### E. Inspections

Compliance items (`needs_inspection = true`) get an extra button on the public
scan page. Pass records and recalculates. **Fail sets condition to
`needs_attention` and raises a report automatically.** Overdue is computed in
PHP, not SQL.

Only a check from an enrolled inspector phone (*Manage → Inspectors' phones*)
or a signed-in admin is **counted**: it moves `last_inspected_at` and sets the
next due date. Anyone else's check is still recorded — and a failure still
raises a report — but the page says beforehand that it will not count, the
asset page marks it *Not counted*, and the overdue list shows it as an
unconfirmed check.

### F. Retention and erasure (both manual, never scheduled)

`data:enforce-retention` deletes returned loans at the end of the academic year
following issue, and people one year after `left_at`. Prints everything first,
requires confirmation, supports `--dry-run`. Someone who left but still holds a
book is **held back, not deleted**.

`person:erase {id}` pseudonymises to `Removed person #{id}` across people,
reports, inspections and `asset_events` — **including a recursive scrub of the
meta JSON, matched by value not by key**, so `{"name":"Ana B"}` goes while
`{"name":"Epson projector"}` stays. Nulls device tokens and IPs. Keeps every
audit row and every book loan. Appends `person.erased` naming nobody.

---

## 10. Security

The security model — what is protected, the rule every public write follows,
and what is deliberately accepted — is in `SECURITY.md`. This section is the
summary.

Threat model, in order: a bored 16-year-old; **enumeration**; the public photo
upload; bot noise; admin compromise. Not nation states.

**Headers on every response** (`App\Http\Middleware\SecureHeaders`): a
Content-Security-Policy that runs scripts only from the site's own files (no
inline scripts or handlers exist, and a test keeps it so; htmx runs with eval
off), `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `nosniff`, and in
production HSTS plus a 308 redirect from plain HTTP.

**Rate limits** (device token first — the whole school shares one public IP):

| Route | Per device | Per IP |
|---|---|---|
| Submit report | 3/hour | 60/hour |
| Scan page | — | 120/min |
| Supply withdrawal & inspection | 10/hour | 100/hour |
| Report status check | 20/hour | — |
| Login | — | 5/min per email |

Limited users get a **plain page**, not a 429 JSON error.

**Enumeration defence:** tags are sequential, so the public page is walkable. All
three measures are implemented — the `PublicAssetView` DTO, `noindex` on every
public route plus `robots.txt` disallowing everything, and the scan rate limit.

**Photos:** content validated (not extension), max 5MB, **re-encoded via GD**
(strips EXIF/GPS), random filename, stored in `storage/app/private`, served
through `PhotoController` with a strict path regex.

**Mass assignment** is the named top risk. `Report` has only four fillable
fields and the create path builds the model line by line. A test posts `status`,
`resolved_at`, `resolved_by`, `first_viewed_at`, `acknowledged_at` and
`reference` and asserts none stick.

**Blade escaping:** the only `{!! !!}` in the codebase is the QR SVG. Report
descriptions are never rendered raw.

**CSRF with HTMX:** the token is on the `<body>` as `hx-headers`.

---

## 11. Console commands

```
user:create {name} {email}      make an admin account (no sign-up page exists)
user:password {email}           the entire password-reset story
data:enforce-retention [--dry-run]
person:erase {id}
```

Retention and erasure are **never scheduled** — `schedule:list` is empty and a
test asserts it stays that way.

Seeders: `DatabaseSeeder` runs Category + Room + Setting (safe for production).
**`DemoSeeder` is deliberately not registered there** and throws if
`APP_ENV=production`. Run explicitly: `db:seed --class=DemoSeeder`. It produces
12 rooms, 63 assets, 120 people (all named `… (demo)`), 30 titles, 392 loans, 6
reports, 5 licences including one perpetual. It is idempotent.

---

## 12. Frontend conventions

**Design thesis: the only colour is status colour.** The Tailwind default palette
is *cleared* (`--color-*: initial`) so `bg-blue-500` does not exist. What remains:
greyscale, one ink navy `#1b2a4a` for primary actions, and four status pairs
(ok/attention/broken/inactive, each with a `-soft` background).

- System font stack only. **No web fonts** — 3G in a basement storeroom.
- `tabular-nums` on `body` so columns align.
- `--spacing-tap: 2.75rem` → `min-h-tap` enforces 44px targets.
- Status is always a **badge containing a word**, never colour alone.
- Every list has an autofocused search box; no modals on mobile; loading states
  on every HTMX action.
- Every user-facing string is in `lang/en/*.php` — 23 files. Nothing hardcoded,
  so the public screens can be translated to Albanian later.

Two layouts: `components/layouts/public.blade.php` (no nav, no header, `noindex`)
and `components/layouts/admin.blade.php` (five-item nav, report badge, search
box). HTMX is used for: supply +/− adjustment, activity infinite scroll, global
search dropdown, serial-number check.

---

## 13. Testing

**363 tests, 1,118 assertions, Pest, all passing.** 27 feature test files. Tests
run against SQLite `:memory:` with `RefreshDatabase`. `SecurityHardeningTest`
and `InspectorPhoneTest` hold the security work; the breached-password check is
faked so the suite never leaves the machine.

Every write path has a test (§0 rule 5). The suite is organised by behaviour
rather than by class — `PublicScanTest` asserts the enumeration defence,
`LicenseExpiryTest` asserts the null-expiry rule ten ways, `PersonErasureTest`
asserts the meta scrub in both directions.

---

## 14. Gotchas — mistakes already made here

Each of these was a real bug found in this codebase. Do not reintroduce them.

1. **SQLite ignores backslash as a LIKE escape; MySQL does not.** Wildcard
   escaping must use an explicit `ESCAPE '!'` clause or search behaves
   differently in dev and production. See `GlobalSearch::whereAnyLike()`.
2. **A nested quote in a Blade *component* attribute silently drops the whole
   tag.** `hx-vals='@json([...])'` on `<x-input>` made the serial number field
   vanish from the form while every test still passed. Put values in the URL
   instead.
3. **`collect()` has no `->load()`.** Returning a plain collection where an
   Eloquent one is expected 500s the page — this took out `/admin/books/returns`
   in its empty state.
4. **The academic year rolls over on the day it *ends* (15 June), not when term
   starts.** Otherwise a book issued in August is born overdue. And a pinned
   `academic_year` setting that has already ended is ignored, or every due date
   and class link is generated already expired.
5. **`getOriginal()` after a save returns the new value.** Capture snapshot
   values *before* mutating, or a failed inspection records the condition it just
   set.
6. **Scrub audit meta by value, never by key.** Keying on `name` destroys
   `asset.created` and `licence.created` history.
7. **Date maths in SQL is not portable.** `last_inspected_at + N months` needs
   `DATE_ADD` on MySQL and `datetime()` on SQLite — filter in PHP instead.
8. **A device token generated per call gives every request its own rate-limit
   bucket.** Memoise it on the request.
9. **Label geometry has no slack.** 3 × 70mm is exactly A4 width and 8 × 37mm is
   296mm of 297mm. Cells are absolutely positioned; a single millimetre of
   border or padding in a flow layout pushes a row onto a second page. A test
   asserts the page count.
10. **Test the empty/landing state, not just the populated one.** Several bugs
    lived exclusively in "before anything is selected".
11. **A signed URL is deterministic.** The same route, parameters and expiry
    always sign to the same URL, so "regenerating" one hands back the link that
    leaked. Put a version inside the signed part if it must ever be withdrawn.
12. **File size says nothing about decoded size.** An 80-byte PNG can declare
    20,000 × 20,000 pixels and exhaust memory the moment GD allocates it. Check
    the declared dimensions before decoding (`DecodablePhoto`).
13. **Apache applies `.htaccess` files from parent folders too.** The deny-all
    in the project folder would block `public/` as well without the explicit
    grant in `public/.htaccess`. Keep them together.
14. **Tests send cookies encrypted.** Use `withCookie()` for the reporter
    cookie; `withUnencryptedCookie()` skips encryption, the app still decrypts,
    and the cookie silently reads as empty.

---

## 15. Known gaps and open questions

**Unanswered from `SPECIFICATION.md` §12** — assumptions currently stand in:

1. cPanel SSH/Composer availability — assumed absent
2. cPanel PHP version — must be ≥8.3, unverified
3. Whether a government asset register exists — `official_number` stays empty
4. Real academic year dates — assumed to end 15 June (`config/school.php`)
5. Photo storage quota — assumed 5MB per upload

**Not built:**

- Photo purging for old resolved reports (§13 suggests it)
- Report photos are untouched by erasure
- Both data commands act on the live database only; backups still hold old data
- `assigned_person_id` has no bulk-intake path (intake is four fields by design)

**Deliberately out of scope** (§11): charts/dashboards, notification centre, user
roles, purchase orders, per-copy book tracking, furniture labels, depreciation,
per-seat licence assignment, licence cost forecasting, licence↔asset
many-to-many, mobile app, offline sync, multi-tenant, an API.

**Beyond-spec additions, all flagged when made:** `categories.prefix`,
`assets.tag_sequence`, `reports.reference`, `asset_events.ip_address`,
`supplies.room_id`, `people.left_at`, and the `data.retention_enforced` action.
Each exists because the specified behaviour is impossible without it.

---

## 16. Working on this codebase

- `SPECIFICATION.md` §0 rules: ask before assuming, build in phase order, **do not add
  features that are not in the spec**, plain language in progress reports, a test
  for every write path, small commits.
- Run `php artisan test` before and after any change.
- `npm run build` after touching a Blade file that uses a new Tailwind class.
- `./vendor/bin/pint` before finishing.
- The project is **not** a git repository at time of writing.
