# School Inventory System — Build Specification

## 0. How to work with me on this project

Read this whole file before writing any code.

**Rules of engagement:**

1. **Ask before assuming.** If something in this spec is ambiguous or missing, stop and ask. Do not invent a feature, a field, a screen, or a library choice that isn't written here.
2. **Build in the phase order in section 10.** Do not jump ahead. Do not scaffold Phase 4 while building Phase 1.
3. **Do not add features that aren't in this spec.** If you think something is missing, say so and wait. The scope is deliberately small. Scope creep is the main risk to this project.
4. **Explain in plain language when you report progress.** The non-technical stakeholder is a school director.
5. **Write a test for every write-path** (anything that creates, updates or logs). Use Pest.
6. **Commit in small, working increments** with clear messages.
7. If you find yourself building a permissions matrix, a notification centre, an analytics dashboard, or a role hierarchy — stop. Re-read section 11.

---

## 1. What this is

An inventory and asset management system for a small private gymnasium (high school) in Kosovo. It replaces nothing — the school currently tracks assets on paper or not at all.

**Scale (design against these numbers, don't over-engineer):**

| Thing | Count |
|---|---|
| Students | ~100 |
| Classes | ~10 |
| Staff | ~20 |
| Admin users | 2 |
| Labelled assets | 80–120 |
| Unlabelled furniture items | ~500, counted per room |
| Book titles | ~30 |
| Book copies | ~800 |
| Book loans per year | ~800 |
| Problem reports | 5–15 per month |

This is a small system. Do not build for scale that will never come. No queues beyond Laravel's defaults, no caching layer, no search engine, no API versioning.

**The three things it must do well:**

1. Someone points a phone at a QR sticker and reports that something is broken — in under 30 seconds, with no login.
2. The director sees what's broken and closes the loop.
3. Books get issued to a whole class in September and chased in June.

Everything else is secondary.

---

## 2. Stack

- **PHP 8.4** (minimum 8.3 — set `"php": "^8.3"` in composer.json)
- **Laravel 13**

Version rationale: Laravel 11 reached end of life on 12 March 2026 and must not be used. Laravel 12 loses support in February 2027, which is inside this project's expected lifetime. Laravel 13 (released March 2026) is supported through March 2028 and shipped with zero breaking changes from 12, so the package ecosystem is already compatible. PHP 8.4 rather than 8.5 because shared cPanel hosts lag on new PHP releases.

**Verify before starting:** check which PHP versions the school's cPanel offers (*Select PHP Version* / *MultiPHP Manager*). If it caps at 8.2, Laravel 13 cannot run and this becomes a hosting decision — raise it with me rather than downgrading the framework.

- **Blade + HTMX** for interactivity. No SPA. No React, Vue, Livewire, Inertia.
- **Tailwind CSS**
- **MySQL**
- **Pest** for tests
- **QR generation:** use a maintained PHP QR library (`endroid/qr-code` or `simplesoftwareio/simple-qrcode`). Check the current version before installing and tell me which you chose.
- **PDF label sheets:** `barryvdh/laravel-dompdf`
- Deployment target is **shared cPanel hosting**. Assume SSH and Composer may not be available on the server. Keep the build simple: no Node-only runtime dependencies in production, compile assets locally and commit the build output if needed. Flag anything that would break on shared hosting.

---

## 3. Locked decisions (do not revisit these)

| Decision | Choice |
|---|---|
| Reporting authentication | **None.** Public page, identity self-declared, remembered on the device via a long-lived cookie. |
| Who can report | **Anyone** — staff and students. Student reports are visually tagged. One admin setting can disable student reporting. |
| Notifications | **In-app only for now.** But fire a `ReportCreated` event so an email listener can be added later without refactoring. |
| Textbook tracking | **Per title, not per copy.** Books get no QR labels at all. |
| Furniture | **Counted per room, no labels.** |
| Supplies (paper, toner) | Simple quantity + minimum level. Optional public "cupboard" QR page. No purchase orders, no suppliers, no cost tracking. |
| Roles | **Two: Admin and Reporter.** Reporter has no login. That's the whole permissions model. |
| Interface language | **English**, but every user-facing string goes in Laravel translation files from day one so the three public screens can be translated to Albanian later. Never hardcode a user-facing string in a Blade template. |
| Asset numbering | We generate our own tags (`INV-PC-0142`). An `official_number` column exists but stays empty for now. |

### 3.1 Access model

There are two access levels and only one involves a password.

**Level 1 — anyone, no login.** Reached only by scanning a QR or following a direct link.

- `/a/{tag}` — asset page and report form
- `/c/{room_id}` — supplies cupboard page
- `/r/{reference}` — check the status of a report

**These routes carry no auth middleware at all.** If a teacher scans a sticker and gets redirected to a login screen, the system has failed at its main job.

Identity here is a name chosen from a list on first use, stored in a one-year cookie. **That cookie is a convenience, not a credential.** It must never unlock anything, grant any capability, or be checked as a permission. The public routes read it for a name and nothing else, ever. Someone who copies another person's cookie gains only the ability to file a report under a different name — which they could already do by picking a different name from the dropdown.

**Level 2 — admin, real login.** Standard Laravel auth, email and password, two accounts (director + a backup such as the secretary). Everything under `/admin/*`.

- **Delete the registration route.** Starter kits ship with public sign-up; on this app it's a hole. Accounts are created by seeder or artisan command only.
- **No password reset UI.** Reset needs email, and email isn't configured. Build `php artisan user:password {email}` instead. With two users, that's the correct amount of machinery.
- Logged-out users hitting an admin URL are redirected to login, then forwarded to their original destination.

**There is no teacher login, deliberately.** Twenty accounts for people who touch the system four times a year means twenty forgotten passwords and you become the reset service. That is the failure mode that kills systems like this.

**The one exception — class book debts in June.** Class teachers need to see who still owes books. Do not solve this with accounts. Use a **signed, read-only, per-class link**: `/class/{signed-token}`, generated from the Returns screen, sent over WhatsApp, printable, expiring at the end of the academic year, regenerable if leaked. Use Laravel's built-in signed URLs.

| Who | How they get in | What they can do |
|---|---|---|
| Teacher, student, anyone | Scan a QR | View an item, report a problem, check a report, log a supply withdrawal or an inspection |
| Class teacher (June only) | A signed link | View and print their class's book debts |
| Director + backup | Email and password | Everything |

---

## 4. The four asset classes — they behave differently

Do not merge these into one generic table.

**Fixed assets** (PCs, laptops, projectors, printers) — one QR per physical unit. Scanning answers "what is this and what's its story?"

**Compliance items** (fire extinguishers, first aid kits) — these are an *inspection schedule*, not inventory. The QR is a "I checked this and it's fine" button. They live in the `assets` table with `needs_inspection = true`; the difference is behavioural, not structural.

**Supplies** (paper, toner, chalk) — no QR on the item. Quantity only. Optionally one QR on the storage cupboard door.

**Books** — a loan system. Title-level records plus loan records. No labels.

**The rule for deciding which bucket something goes in:** does anyone care about *this specific one*, or only *how many are left*? Individual → asset. Count only → supply or room contents.

Label individually only if the item meets at least one of: costs more than ~€100, moves between rooms, has a warranty or service history, or someone would scan it to report a fault. A box of spare mice fails all four — it's a supply.

---

## 5. Data model

**Schema principle:** keep it minimal, but **domain correctness takes priority over table count.** Never store a real business fact in a JSON column or an audit-log row just to avoid adding a table. Before creating a new table, justify it in one sentence — and then create it.

### `rooms`
`id`, `name`, `floor`, `type` (classroom / lab / office / storage / corridor / offsite), `last_counted_at`, timestamps.

Seed one room of type `offsite` named **"Off-site / with staff"** for laptops that genuinely live in someone's bag. Without it, people invent inconsistent answers for the location of mobile equipment.

### `room_contents`
Unlabelled furniture. `id`, `room_id`, `item_name`, `quantity`, `condition`, timestamps.

### `categories`
`id`, `name`, `needs_inspection_default` (bool), `default_inspection_interval_months` (nullable).

Seed with: Computer, Laptop, Monitor, Projector, Printer, Network, Interactive board, Lab equipment, Fire safety, First aid, Audio, Other.

Keep this as a table, not an enum. Schools always add a category nobody predicted.

### `assets`

**Identity:** `id`, `asset_tag` (unique, indexed, printed on the label), `official_number` (nullable), `category_id`, `name`, `make`, `model`, `serial_number`, `photo_path`

**Purchase:** `purchase_date`, `purchase_price`, `supplier`, `invoice_ref`, `funding_source`, `warranty_end`

`funding_source` matters in Kosovo — donated and EU-project equipment carries different reporting and disposal obligations than budget purchases. Enum: budget / donation / project / unknown.

**Location:** `room_id` (**required**), `assigned_person_id` (nullable)

These are two independent facts and must not be mutually exclusive.

- `room_id` is **where the item normally lives**. Every physical object is somewhere, and during bulk intake you are standing in the room — you always know this. Required, always.
- `assigned_person_id` is **who is answerable for it**. Optional. Most items have no custodian.

A projector is Room 12, no custodian. A laptop is Room 8 (home base) with custodian Krasniqi. Both are complete records. Equipment that genuinely lives in a staff member's bag goes in the `offsite` room with a custodian set.

This matters beyond tidiness: the printable room inventory sheet for the annual physical count lists what is in a room. If assigning a custodian removed an item from its room, someone doing a manual count would find a laptop the system claims isn't there.

**State:** `status` (in_use / in_storage / under_repair / retired / lost / unassigned), `condition` (good / needs_attention / broken)

These are two separate fields and people constantly merge them. A working laptop in a cupboard is `in_storage` + `good`.

`unassigned` is the status of a pre-printed blank label that hasn't been attached to anything yet. This is what makes the bulk labelling day possible.

**Lifecycle:** `expected_life_years`, `disposal_date`, `disposal_method`

**Compliance:** `needs_inspection` (bool), `inspection_interval_months`, `last_inspected_at`

`last_inspected_at` is a **denormalised cache** of the newest row in `inspections`, kept so the overdue list doesn't need a join. It is derived — never edit it directly, only recalculate it when an inspection is recorded. Next-due is calculated from it, not stored.

**Meta:** `notes`, timestamps.

### `asset_events` — the audit log

This is the single audit log for the whole system. Do not build a second one.

`id`, `subject_type`, `subject_id` (polymorphic — assets, reports, supplies, loans, people), `action`, `actor_type` (admin / staff / student / public / system), `actor_id` (nullable), `actor_name` (the self-declared name for public actions), `device_token` (nullable), `meta` (JSON), `created_at`.

**Actions:**
`asset.created` · `asset.moved` · `asset.assigned` · `asset.status_changed` · `asset.edited` · `asset.retired` · `asset.inspected` · `report.created` · `report.viewed` · `report.acknowledged` · `report.resolved` · `report.dismissed` · `book.issued` · `book.returned` · `supply.taken` · `supply.recounted` · `person.added` · `person.deactivated`

**Rules:**
- **Append only. Never update. Never delete.** No `updated_at` column. If something was logged wrong, log a correction on top of it. The moment rows can be edited, the log stops being evidence.
- `meta` stores before/after values. `{"field":"room","from":"Lab 2","to":"Room 14"}` is useful. `"asset edited"` alone is not.
- Every write path in the application writes here. No exceptions.

**Important honesty constraint:** because reporting has no password, the actor on a public action is *self-declared*, not verified. `actor_type` distinguishes authenticated admin actions from self-declared public ones. The UI must reflect this — admin actions get a solid badge, public actions a lighter "self-reported" style. Never present the log as implying more certainty than it has.

### `inspections`
`id`, `asset_id`, `inspected_at`, `inspector_name`, `result` (pass / fail), `notes`, `next_due_at`, `created_at`.

This is a **domain table, not a second audit log.** An inspection is a business fact with an inspector, a result, and legal weight for fire safety — not merely a record that something was logged. Storing it only as an `asset_events` row would bury the result in a JSON column where nothing can react to it.

**A failed inspection must be actionable:** `result = fail` sets the asset's condition to `needs_attention` and automatically raises a report so it enters the director's queue. This is the main reason inspections need to be a first-class record.

Also write an `asset.inspected` event alongside every inspection, and recalculate `assets.last_inspected_at`.

### `licenses`
`id`, `name`, `vendor`, `type` (software / domain / hosting / antivirus / other), `licence_model` (subscription / perpetual / volume), `seats_total`, `seats_used`, `purchase_date`, `expires_at` (**nullable**), `renewal_cost`, `auto_renew` (bool), `account_reference`, `asset_id` (nullable), `notes`, timestamps.

**A license is not an asset.** It has no room, no condition, no QR code, and cannot be reported broken. It must never go in the `assets` table.

Field notes:

- `expires_at` is **nullable** because perpetual licenses never expire. The expiry list must treat null as "never expires," not as overdue. This is the most likely bug in this feature — test it.
- `account_reference` records which email or account owns the subscription. Schools routinely lose track of this and discover it the day the person who set it up leaves.
- `auto_renew` changes what the warning means. Manual renewal expiring means *the service stops*. Auto-renew expiring means *money leaves the account*. Same countdown, different wording in the warning.
- `seats_total` and `seats_used` are plain numbers. **Do not build a seat-assignment table.** At 20 staff it would be maintained for two weeks and then be permanently wrong.
- `asset_id` is optional and covers OEM licenses tied to one machine (a Windows license on a specific PC). `nullOnDelete`. It is a single nullable foreign key, not a many-to-many.

**Renewals:** when a license is renewed, update `expires_at` and write a `licence.renewed` event with the old and new dates in `meta`. Do not create a renewals table.

Add `licence.created`, `licence.edited`, `licence.renewed` and `licence.deleted` to the action list in `asset_events`. The table is polymorphic and already supports this — do not rename it.

### `reports`
`id`, `asset_id`, `reporter_name`, `reporter_type` (staff / student / other), `device_token`, `description`, `photo_path`, `status` (open / in_progress / resolved / dismissed), `asset_status_before`, `asset_condition_before`, `created_at`, `first_viewed_at`, `first_viewed_by`, `acknowledged_at`, `resolved_at`, `resolved_by`, `resolution_note`.

`asset_status_before` and `asset_condition_before` are snapshotted when the report is created, so the asset can be returned to where it actually was on resolution. See section 7B.

Four timestamps, and the middle two matter:

- `created_at` — someone reported it
- `first_viewed_at` — an admin **opened** the report. Stamped automatically on first page load by an admin. **Never overwritten** — only the first view counts.
- `acknowledged_at` — the director actively tapped "Working on it"
- `resolved_at` — closed

*Viewed* means the director knows. *Acknowledged* means someone is acting. The gap between them is where things rot, which is why both exist.

Derive and display two figures on the report page: **response time** (created → first viewed) and **resolution time** (created → resolved). Show them in plain words: "Reported 4 days ago · Seen after 6 hours · Still open."

### `people`
`id`, `name`, `type` (staff / student), `class` (nullable, for students), `email` (nullable), `active` (bool), timestamps.

Staff and students in one table. At 120 rows, splitting them buys nothing. **Deactivate, never delete** — old book loans point at these records.

### `book_titles`
`id`, `title`, `subject`, `grade`, `isbn` (nullable), `total_copies`, timestamps.

There is no separate "grade book set" table. The Grade 10 set *is* every title where `grade = 10`. One less screen to build and maintain.

### `book_loans`
`id`, `person_id`, `book_title_id`, `issued_at`, `due_at`, `returned_at`, `condition_on_return` (good / damaged / lost), `notes`, `academic_year`.

### `supplies`
`id`, `name`, `unit` (packs / boxes / pieces / reams), `quantity`, `minimum_level`, `storage_location`, `last_counted_at`, timestamps.

### `users`
Laravel default. Two rows. Do not add roles, teams, or permissions.

### `settings`
Key/value. Keys: `school_name`, `student_reporting_enabled`, `academic_year`, `notification_email` (present but unused).

---

## 6. The QR contract

The QR encodes `https://{host}/a/{asset_tag}` — the **printed tag**, not the database ID.

Two reasons this matters:
1. You can print a stack of blank labels *before* the asset records have real data. Each blank label gets an `assets` row with only a tag and `status = unassigned`.
2. If the QR is scratched or the sticker is damaged, a human can read the tag printed beside it and type it into a search box.

Always print the human-readable tag next to the QR. Always provide a "search by tag" input.

The cupboard QR for supplies uses a different route: `/c/{room_id}`.

### 6.1 The QR image is never stored

Do not save QR images to disk. Do not create a `qr_path` column. Do not queue a generation job.

The QR is **drawn on demand** from the asset tag, wherever it's needed — the asset detail page, the label PDF, anywhere. It is a pure function of `asset_tag`.

This means there is no file that can go missing, no job that can fail, no asset that can end up without a QR, and nothing that can fall out of sync. As long as the asset row exists, its QR exists.

### 6.2 Two write-once rules

**A tag is assigned once and never changes.** If a projector is later recategorised from Projector to Audio, its tag stays `INV-PRJ-0031`. The sticker is physical and cannot be updated. A tag that changes is a tag that lies about the object it is stuck to. Enforce this: `asset_tag` is write-once at the model level, and the edit form must not expose it.

**Reprinting produces the identical tag.** Stickers peel off. When one is replaced, the reprint carries the same code — never a newly generated one. A new tag would sever the item from its entire history.

Tag format: `INV-{CATEGORY_PREFIX}-{4-digit sequence}`, e.g. `INV-PC-0142`. The prefix comes from the category at creation time and is frozen from then on. The sequence is global, not per-category, so no two assets can ever collide.

### 6.3 Labels: the actual printing process

There are two ways an asset enters the system, and both must work.

**Path A — data first** (a new laptop arrives with an invoice)

1. Assets → **Add asset** → fill the form → **Save**
2. The tag is assigned automatically on save. The QR appears immediately on the asset detail page.
3. Two buttons sit beside it: **Add to label queue** and **Print this one now**

**Path B — label first** (the bulk labelling day)

1. Assets → Print labels → **Generate blank labels** → enter a number, e.g. 100
2. The system creates 100 `assets` rows with only a tag and `status = unassigned`, then produces the PDF sheet
3. Print, stick them on everything
4. Later, scan each one → it opens Bulk intake → fill four fields → **Save and scan next**

#### The label queue

Printing one label onto a 24-up sticker sheet wastes 23 stickers. So the default action after adding an asset is **Add to label queue**, not print.

- A counter is visible in the Assets section header: *"7 labels waiting"*
- Clicking it opens the queue: a list of pending labels with the option to remove any of them
- **Print sheet** generates the PDF and clears the queue
- **Print this one now** remains available for when a label is genuinely needed immediately

Store the queue in the session or a small `label_queue` table keyed to the user — either is fine, but say which you chose.

#### Choosing the start position

When printing a sheet, the user picks **which cell to start at** (1–24). This lets a partly-used sticker sheet be fed back through the printer instead of thrown away.

The print dialog shows a simple visual grid of the 24 cells. Cells before the chosen start are greyed out and left blank in the PDF. Default is position 1.

This is the single most practically useful feature in the printing flow. Do not skip it.

#### Label contents

Each label carries exactly three things:

1. The QR code
2. The tag in **large, high-contrast, monospaced** type — this is the fallback when the QR is scratched, so it must be readable at arm's length
3. The school name, small

Nothing else. No item name (it can change, and the label is physical), no date, no logo.

#### PDF output

A4, 24-up (3 columns × 8 rows), standard 70×37mm label sheet dimensions. Include crop guides. Test the alignment by printing one page onto plain paper and holding it against a real sticker sheet before doing a full run — printer margins vary and being 2mm off ruins a whole sheet.

---

## 7. The three core workflows

### A. Someone finds something broken (90% of all usage)

1. Person points their **phone's native camera** at the sticker. **No app to install, ever.** If any part of the design requires installing an app, the design is wrong.
2. `/a/{tag}` loads. No login, no redirect, no interstitial.
3. Page shows: photo, item name and room in large type, a status badge, and one large red button: **Report a problem**. **Nothing else.** No warranty date, no repair history, no serial number, no price. See section 13 for why — this page is enumerable by anyone who scans one sticker.
4. **If an admin is logged in, `/a/{tag}` redirects to the full admin asset page.** Same sticker, same URL, more depth by session. The director scanning in a corridor gets everything; an anonymous scan gets almost nothing. This is what lets the public page stay minimal without losing anything.
4. If an open report already exists, a yellow banner sits **above** the button: *"Reported 2 days ago — being looked at."* The button changes to **Add more information**. This single element eliminates most duplicate reports.
5. Tap → "Who are you?" (first visit only — searchable list of people, or free-text name; then stored in a 1-year cookie) → three tap-chips (*Doesn't turn on* / *Physically damaged* / *Missing*) → one free-text box → optional photo → **Send**.
6. Confirmation in plain language: "Reported. The director has been notified. You'll hear when it's fixed." Plus a short reference number.
7. The asset's current status and condition are snapshotted onto the report, then status flips to `under_repair` so the next scanner sees the banner.

The tap-chips are not decoration. Typing a paragraph on a phone is exactly where a 55-year-old teacher abandons the process.

### B. The director deals with it

Report queue → three tabs (Open / In progress / Closed), opens on **Open**, sorted **oldest first**. Rows open more than 7 days turn red.

Report detail shows the item, the reporter, the description, the photo, and — importantly — **that item's recent history on the same page**. When the director sees "this projector was also repaired in March and November," they stop treating it as a one-off and start thinking about replacement. That element is what makes the system feel like it knows something they don't.

Three buttons: **Working on it** / **Fixed** / **Not a problem**. Plus a note box. Not a status dropdown with eight options.

**On *Fixed* or *Not a problem*, restore the snapshot automatically — do not prompt.** The asset returns to the `asset_status_before` and `asset_condition_before` values captured when the report was created. A projector that was `in_storage` when it broke goes back to `in_storage`, not into service.

Do **not** hardcode `in_use` + `good`, and do **not** ask the director to pick a resulting state. Making them choose every time is a status dropdown wearing a different hat, and it defeats the purpose of the three-button design.

Two refinements:

- A small optional link — *"Set a different state"* — for partial repairs where the condition should stay `needs_attention`. Default silent and correct; override available for the rare case.
- **Only restore if the asset's status is still `under_repair`.** If something else changed it during the repair, leave it alone rather than clobbering newer information with a stale snapshot.

The repair is written permanently into asset history, and (once notifications exist) the reporter is told.

### C. Books

**September:** Books → Issue → pick a class → students appear with that grade's titles pre-ticked → confirm the whole class at once. Adjust individuals afterwards.

**June:** Books → Returns → pick a class → see every student and what they still hold → tick off returns. **Printable** — the class teacher will want it on paper.

**Mid-year:** one student's page, tick or untick a title.

---

## 8. Screens

Navigation is five items: **Home · Assets · Reports · Books · Manage**

Reports carries a red count badge visible on every screen. If a sixth top-level item seems necessary, stop and ask.

### Home
One job: answer "does anything need me today?" in three seconds.
- Red open-report count at the top, tappable.
- **Needs attention** list, max 5 rows, pulled from several sources into one list: reports open >7 days, overdue inspections, warranties expiring within 60 days, **licenses expiring within 60 days**, assets stuck in `under_repair` >30 days, supplies below minimum, and outstanding book debts (May–September only).
- Four plain numbers: total assets, in use, under repair, retired.
- No charts. No graphs.
- Empty state reads **"Nothing needs your attention."** That is the goal state, not a placeholder.

### Assets
- **List** — search box at top, autofocused. Filter chips: Room, Category, Status. Row: thumbnail, name + model, tag, room, status badge. Sorted by room then name. Actions: Add asset, Bulk intake, Print labels.
- **Detail** — facts panel on top, full timeline underneath (newest first, forever). QR displayed on screen. Actions: Edit, Move to room, Assign to person, Change status, Retire, Print label, and *Log inspection* (only shown when `needs_inspection`).
- **Add / edit** — four collapsible groups: Identity, Purchase, Location, Condition. **Purchase collapsed by default** — during bulk entry nobody has the invoice in hand, and eight visible financial fields make people abandon the form.
- **Bulk intake** — scan a blank label → 4 fields → **Save and scan next** → camera reopens immediately. This loop must never dump the user back to a list. Breaking it turns a 4-hour job into a 2-day job.
- **Print labels** — three entry points: generate N blank labels, print the pending queue, or reprint for selected assets. Sheet-position picker on every print. Full process in section 6.3.

### Reports
- **Queue** and **Detail** as described in workflow B.

### Books
- **Titles** — title, subject, grade, copies owned / out / available.
- **Issue** — class-level bulk issue.
- **Returns & debts** — by class, printable.
- **Student record** — current holdings, due dates, previous-year history.

### Manage
- **Rooms** — list with asset count, furniture count, last counted date. Detail shows assets, furniture lines, a *Mark as counted* button, and a **printable room inventory sheet** (schools do annual physical counts on paper; someone will ask for this).
- **People** — staff and students, CSV import for September intake.
- **Supplies** — list with inline +/− quantity adjustment, low-stock rows highlighted. Add/edit is four fields. Optional public cupboard page at `/c/{room_id}` with a "took some" tap and a **Recount** action that overwrites the number (reconciliation is more reliable than catching every withdrawal).
- **Licenses** — list sorted by expiry date, soonest first. Perpetual licenses (null `expires_at`) sort last and show "Never expires," never a red badge. Rows expiring within 60 days highlighted amber, expired rows red. Columns: name, vendor, seats used/total, expiry, auto-renew indicator. Detail page shows the record plus its renewal history from `asset_events`, with a **Renew** action that asks only for the new expiry date and optionally an updated cost. Add/edit form is one screen — do not split it into steps.
- **Categories** — short editable list.
- **Activity** — the audit log. Read-only, newest first, infinite scroll. Each row reads as a **sentence**: *"Arben Krasniqi reported a problem with Projector INV-PRJ-004 (Room 12) — 4 days ago."* Not a table of IDs. Filters: date range, action type, person, item. Search box. CSV export.
- **Settings** and **Users**.

---

## 9. UI direction

**The audience is a teacher in their fifties using a cracked Android phone on school wifi.** Every design decision serves that person. This is institutional utility software, not a startup product. Restraint is the aesthetic.

**Design thesis: the only colour in the application is status colour.** Everything else is greyscale plus one dark ink navy for primary actions. Green means working, amber means attention, red means broken or overdue, grey means retired or inactive. Because colour is never decorative, colour always means something — which is what lets someone read a list of 40 assets at a glance.

**Type:** system font stack throughout (`-apple-system, Segoe UI, Roboto, sans-serif`). No web fonts — a self-hosted display face costs a render-blocking request on a connection that may be a single bar of 3G in a basement storeroom. Get personality from an aggressive weight and size scale instead: page titles large and heavy, everything else quiet. Use **tabular numerals** for all quantities, counts and dates so columns align.

**Non-negotiable rules:**

1. One primary action per screen, visually dominant. Everything else is a text link.
2. Never more than six input fields visible at once on a phone.
3. No modals on mobile — use a full page.
4. Status is always a coloured badge **containing a word**, never colour alone. Some of your users are colourblind and all of them are looking at a phone in a sunlit corridor.
5. Minimum tap target 44×44px. The public pages will be used one-handed while holding something else.
6. Every list has a search box at the top, autofocused on page load.
7. Loading states on every HTMX action. A button that appears to do nothing gets tapped four times.

**Copy rules:**

- Name things by what the person controls, never by how the system is built. "Report a problem," not "Create ticket."
- Active voice. A button says exactly what happens: "Save changes," not "Submit."
- An action keeps the same name through the whole flow. The button that says "Report a problem" produces a confirmation that says "Reported."
- Confirmations say what happened in plain language: "Reported. The director has been notified." Never "Success."
- Errors explain what went wrong and how to fix it. They don't apologise and they're never vague.
- Empty states are an invitation to act, not a shrug.

**Quality floor, built in without announcing it:** responsive down to 320px, visible keyboard focus rings, `prefers-reduced-motion` respected, all images have alt text, forms are properly labelled.

---

## 10. Build order

Do not jump ahead.

**Phase 1 — the spine.** Rooms, categories, assets, asset detail with timeline, QR generation, the label queue, the label PDF with sheet-position picker, blank-label generation, public scan page. Outcome: point a phone at a sticker, see what the thing is. Demo-able.

**Phase 1.5 — bulk intake.** Required *before* the physical labelling day, and the labelling day has to happen before the system means anything. Do not defer this to the end.

**Phase 2 — the value.** Report form, report queue, report detail, the three-button resolution flow, `ReportCreated` event, audit log writing throughout, Home's needs-attention list. **Phases 1 + 2 alone are a genuinely useful system.** Treat this as the minimum shippable outcome.

**Phase 3 — books.** Titles, class-level issue, returns and debts, student record. Self-contained.

**Phase 4 — supplies, inspections and licenses.** Smallest modules. Inspection logging happens on the public scan page; the only new screen is the overdue list. Licenses sit next to inspections because both are "a dated thing that goes overdue."

**Phase 5 — Activity screen, CSV export, room inventory sheet, polish.**

---

## 14. Global search

One search box in the **admin header**, visible on every admin page, autofocused on `/` keypress.

Searches, with results grouped by type: assets (tag, name, make, model, serial), people (name), rooms (name), licenses (name, vendor), book titles (title), report reference numbers.

**Constraints:**
- Route lives under `/admin/*` with auth middleware. **Never reachable from a public page.** §13 spends real effort ensuring the public side leaks nothing; an unauthenticated search route would undo all of it at once.
- Plain SQL `LIKE`. No Scout, no search index, no full-text engine. At ~120 assets anything more is theatre.
- No new nav item, no new columns, no new packages.

---

## 15. Duplicate detection

When a serial number is entered that already exists on another asset, show an **inline warning** naming the existing asset and linking to it.

**It warns, it never blocks.** Blank serials and vendor-reused serials are both common. A hard block would make the system wrong in a way the user cannot override.

**It must not interrupt bulk intake.** No modal, no confirmation step, no interruption to the "save and scan next" loop — that loop is what turns a two-day labelling job into a four-hour one. The warning appears inline and the save proceeds.

Zero schema change.

---

## 16. Export

CSV export on: assets, book debts, licenses, activity log. Admin-only, read-only, streamed rather than buffered. No new packages — use Laravel's streamed response.

**Exports containing personal data are themselves a processing event.** Any export including student or staff names:

1. Writes a `data.exported` event recording who exported, which dataset, and the row count. Never the contents.
2. Carries a footer line in the file: the export date, who generated it, and a statement that it contains personal data and is subject to the retention rules in section 17.

CSV rather than Excel — it opens in Excel anyway and adds no dependency.

---

## 17. Data protection

The system stores names and book-borrowing history of approximately 100 students, most of them minors. Kosovo's Law No. 06/L-082 on Protection of Personal Data transposes the GDPR and applies to private bodies, so a private gymnasium is in scope.

### Retention rules

Two rules, each justified by the purpose the record serves:

1. **A returned loan is deleted at the end of the academic year following the one it was issued in.** Maximum life ~2 years. A loan record exists to manage an active loan and settle disputes about it; a dispute about a book returned two school years ago does not arise.
2. **When a person is marked as left, their record and all their loans are deleted one year after the leaving date.** One year covers final-year disputes.

**Open loans are never auto-deleted** — they are still active.

Enforcement is an artisan command, `php artisan data:enforce-retention`. It prints exactly what it will delete and requires confirmation. **It is never scheduled.** Unattended silent deletion of school records is not acceptable behaviour in this system.

Document the rules and their justification in `RETENTION.md`.

**Open item:** confirm whether Kosovo education regulations impose a minimum retention period on school records. If they do, that overrides the above.

### Erasure

`php artisan person:erase {id}` **pseudonymises — it does not delete log rows.**

- `people`: name replaced with `Removed person #{id}`, contact fields nulled
- `book_loans`: retained, now pointing at the pseudonymised person
- `asset_events`: `actor_name` replaced, `actor_id` nulled, **and `meta` scrubbed of any name** — a book-issue event storing `{"student":"Ana B"}` leaves the name in JSON if only `actor_name` is replaced. This is the easy thing to miss.
- `reports`: `reporter_name` replaced
- Finally, log a `person.erased` event **without naming the person**

**Never delete an `asset_events` row.** Hard deletion would remove history about *equipment* — a fault reported and repaired is a fact about the projector, not only about the person who reported it — and it would break the log's central promise. Gaps in an append-only log are indistinguishable from tampering. Pseudonymisation resolves the apparent conflict between the append-only log and the right to erasure: once data can no longer be attributed to an identifiable person, it is no longer personal data.

### Public privacy note

A short plain-language note on the public report page: what is collected, why, and how long it is kept. Two or three sentences, linked rather than inline so it doesn't clutter the page.

---

## 18. Handover

**Built as part of the project:**
- A seeder producing realistic but clearly fictional demo data — rooms, assets, people, loans, reports — in a separate seeder that never runs in production.
- `DEPLOYMENT.md` covering the shared-hosting deployment procedure, `composer install --no-dev`, environment variables, and the backup routine.

**Written separately:** the printed one-page admin guide for the director, in English. That is written by hand, after usability testing, because the testing reveals which steps people actually get stuck on. Anyone documenting a system they built documents what they believe they built.

---

## 19. Deliberately NOT in scope

Do not build any of these. If you think one is needed, stop and ask first.

- Analytics or reporting dashboards with charts
- A notification centre
- A separate audit log screen per module (history lives on the item it belongs to)
- User roles, permission matrices, or departments — there are two roles and one has no login
- Purchase orders, supplier management, or procurement workflow
- Per-copy book tracking or book barcodes
- Individual furniture labels
- Depreciation schedules or financial reporting
- Per-seat license assignment or a seat/user junction table
- License cost forecasting, budget reports, or spend analysis
- A many-to-many link between licenses and assets
- A mobile app of any kind
- Offline sync
- Multi-school / multi-tenant support
- An API

---

## 12. Open questions to raise with me

Ask about these before they block you; don't guess:

1. Does the shared cPanel host provide SSH and Composer? This affects the whole deployment approach.
2. **Which PHP versions does the cPanel host offer?** Laravel 13 needs 8.3 minimum. If the host caps at 8.2, stop and raise it — do not silently downgrade the framework to an unsupported version.
3. Does the school have an existing official asset register with government-assigned inventory numbers? If so, `official_number` needs to be populated and displayed prominently.
4. What is the school's academic year start/end date, for book loan due dates?
5. Photo storage: local disk on shared hosting has size limits. Confirm the limit and set an upload cap accordingly.

---

## 13. Security requirements

### The actual threat model

This app has an unusual shape: a public, unauthenticated write endpoint. The realistic threats, ranked:

1. **A bored 16-year-old** submitting 200 joke reports or setting paper stock to 9999. By far the most likely.
2. **Enumeration** — walking `/a/INV-PC-0001` through `0200` to download the school's asset inventory. The most serious.
3. **The public photo upload** — unauthenticated file upload is the classic hole.
4. **Bot noise** on public URLs.
5. **Admin account compromise** — two accounts, no 2FA, no email reset.

Nation-state actors are not the concern. Teenagers and crawlers are.

### Rate limiting

Use Laravel's `RateLimiter`. **Critical detail: the entire school shares one public IP.** Limiting reports by IP is actively wrong — the tenth person to report something on a busy Monday would be blocked. Limit by **device token first**, IP as a loose backstop.

| Route | Per device | Per IP |
|---|---|---|
| Submit report | 3 / hour | 60 / hour |
| View asset page | — | 120 / minute |
| Supply withdrawal | 10 / hour | 100 / hour |
| Report status check | 20 / hour | — |
| Admin login | — | 5 / minute per email |

When someone is limited, show a plain page, not a 429 JSON error: *"You've already sent several reports. If something else is broken, please tell the director directly."* The person hitting this limit is far more likely an enthusiastic teacher than an attacker.

### Enumeration defence

Tags are sequential, so anyone who scans one sticker can guess the rest. If the public page exposed purchase price and serial numbers, enumeration would produce a categorised shopping list of every valuable item in the building, with room numbers. Three measures, implement all three:

1. **The public asset page shows only:** photo, item name, room, status badge, report button. **Never** `purchase_price`, `supplier`, `invoice_ref`, `serial_number`, `funding_source`, `warranty_end`, or repair history. Warranty and repair history are excluded deliberately — "under warranty until 2027" signals *new and worth stealing*, and "repaired three times" signals *troublesome*. Neither helps a teacher reporting a fault. Enforce this with a dedicated public view model or DTO — not by remembering not to print the field. Admins scanning the same QR are redirected to the full admin page (section 7A), so nothing is lost. Enumeration then yields "a PC in Room 12, working," which is near-worthless.
2. **`noindex` meta tag on every public route, plus robots.txt.** Otherwise Google indexes the school's entire inventory.
3. **Rate limit the scan route** as above.

Keep tags sequential despite this. Random tags would break the human-readable fallback — someone reading a damaged sticker in a corridor needs to be able to type it. Reducing what the page exposes is the better trade.

### Photo uploads

- Validate **actual file content**, not the extension. `image/jpeg`, `image/png`, `image/webp` only.
- Max 5MB, and **re-encode server-side after upload**. This strips EXIF (including the school's GPS coordinates) and destroys anything embedded in the file.
- Random generated filenames. Never trust a user-supplied name.
- Store **outside the webroot**, serve through a controller. An uploads directory inside `public/` that can execute PHP is game over on shared hosting.
- Watch disk quota. Consider purging photos attached to reports resolved more than 12 months ago.

### Admin accounts

- Registration route deleted (see 3.1).
- Force HTTPS. cPanel provides free Let's Encrypt.
- Enforce a strong password at creation. With two accounts, the director's password is the entire admin security perimeter.
- **No 2FA.** Two users and no working email — the machinery would cost more than it protects.

### Laravel defaults and three gotchas

Keep Eloquent's parameterised queries, Blade's auto-escaping, and CSRF protection. Don't fight any of it. But:

- **CSRF with HTMX:** the token must be configured as a header on the body element. This is the most common HTMX-in-Laravel mistake and it fails silently in ways that look like a routing bug.
- **Never use `{!! !!}` on report descriptions.** That text comes from an anonymous stranger and renders on the director's screen. Stored XSS lives exactly there.
- **Mass assignment on the public report form is the single most likely real vulnerability in this codebase.** `$fillable` on every model, and the report creation path must build the model explicitly, field by field. It must never accept `status`, `resolved_at`, `resolved_by`, `first_viewed_at`, or `acknowledged_at` from request input.

### Shared hosting

- `APP_DEBUG=false` in production. A stack trace leaks database credentials.
- **Confirm the document root points at `/public`.** If it points at the project root, `.env` is publicly downloadable. One-line hosting setting, worst possible outcome.

### The audit log is the real security control

Public actions are unverified, so the log isn't a gate — it's a deterrent and a forensic tool. Store `device_token` and IP address on every public action. Add a small **device block list** (one table, one column) so a spamming device can be identified from the log and blocked. That is the correct response to the most likely attack.

### Backups — set up before launch

The realistic way this system dies is not an attacker. It's a database wiped by a bad migration in month four. Weekly `mysqldump` plus cPanel's own backup is sufficient. Do this before the school starts entering real data, not after.