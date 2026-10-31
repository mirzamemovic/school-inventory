# Security model

How this system is kept safe, why, and what is deliberately accepted. `SPECIFICATION.md`
§13 is the specification; this is the reasoning built on top of it. Read it
before adding anything a stranger can reach.

---

## What is being protected, in order

1. **The warnings.** The system's job is to tell the director what is wrong.
   If anyone can make a warning go quiet, it is worse than paper. Fire
   extinguisher inspections also carry legal weight.
2. **Children's data.** About a hundred minors' names and borrowing history,
   under Kosovo's Law No. 06/L-082 (the GDPR, transposed).
3. **The 30-second report.** A teacher scanning a sticker must never meet a
   broken page.
4. **The admin accounts.** No second factor and no email reset, so the
   password is the whole perimeter. Every account is an admin; there are no
   roles.

The inventory itself is barely secret: the public scan page shows almost
nothing, by design. Nobody moves money through this system.

## The rule for anything public

> **Anonymous input may raise a warning. It must never silence one.**

Public actions cannot be verified — the whole point is no login. So instead of
trying to verify them, each one is shaped so that the worst a stranger can do
is add noise the director can see, never remove a signal the director needs.

| Public action | What it can do | Why that is safe |
|---|---|---|
| Report a problem | Opens a report, marks the item under repair | Raises a warning. The snapshot puts the item back on *Not a problem* |
| Add more information | Appends to the open report | Capped at 60,000 bytes, so it cannot fill the column and break later reports |
| Took some | Lowers stock | Can only push towards *running low* |
| Recount | Confirms or lowers stock | **Cannot raise it.** 9999 would silence *running low* for good; deliveries go through the office |
| Inspection: fine | Recorded | **Only counts from an enrolled phone or a signed-in admin.** Otherwise it cannot reset the date and silence *overdue* |
| Inspection: problem | Raises a report | Raises a warning |
| Choose a password (once-only link) | Sets the password of the one account the link was made for | **Only with a link an admin made, in the last hour, not yet used.** The link is the trusted step; it touches no warning |

**Before adding a public write, put it in this table.** If the answer to "can
this make a warning disappear?" is yes, it needs a trusted step — an admin, or a
device the director enrolled in person — before it counts.

## Controls, by threat

**A bored 16-year-old** (the likeliest attacker)
- Rate limits by device token first, IP address as a loose backstop — the whole
  school shares one address.
- The device block list, run from the Activity screen. Blocking and unblocking
  are themselves logged.
- The rule above, so the worst outcome is visible noise.

**Enumeration** (walking `/a/INV-PC-0001` upwards)
- The public page is a DTO (`PublicAssetView`): price, serial, supplier,
  warranty and repair history are not on the object, so they cannot leak.
- `noindex` on every public page, and `robots.txt` disallows everything.
- Rate limits exist but do not stop this — 120 assets fit in one minute. The DTO
  is the real defence.

**The public photo upload**
- Content checked, not the extension; 5 MB; re-encoded through GD, which strips
  EXIF and GPS; random name; stored outside the webroot; served by a controller
  with a strict path pattern.
- **The declared dimensions are checked before decoding.** An 80-byte PNG can
  claim 20,000 × 20,000 pixels and exhaust memory the moment GD allocates it
  (`DecodablePhoto`).
- Report photos need a login; only asset photos are public.

**Stored XSS** (strangers' text on the director's screen)
- Blade escapes everything; report text is never output raw.
- The Content-Security-Policy runs scripts only from the site's own files. There
  are no inline scripts or event-handler attributes, and a test fails if one
  appears. htmx runs with `eval` off.
- CSV exports prefix any cell starting with `= + - @` so a typed "name" cannot
  run as a formula in Excel.

**Admin account compromise**
- 16-character minimum, checked against known breaches (the lookup sends five
  characters of a hash, never the password), and refused if it contains the
  school's name or the email address. No composition rules: they let
  `Gjimnazi2026` through.
- Lockout counted per email *and* address, so a stranger cannot lock the
  director out from everywhere; plus 100 an hour per email from anywhere.
- "Keep me signed in" lasts 14 days, not a year.
- Secure, HTTP-only session cookies; HTTPS forced in production, with HSTS.
- `X-Frame-Options: DENY`, so no button can be clicked through a frame.

**Account management** (Manage → Users, approved 2026-10-01)
- **Nobody can see or learn another person's password.** None is stored in a
  form that can be read back, and none is ever shown, logged or sent to a page.
  A new or reset account gets a random password nobody knows plus a
  **once-only link** (Laravel's reset token: stored hashed, one hour, single
  use, replaced by any newer link) with which the person chooses their own.
  The link is shown once, with a QR, and cannot be shown again.
- **The password typed again** within the last 10 minutes before any Users
  page or action — checked by the server on every method, not by the page.
  Wrong re-entries are limited like sign-in.
- **Guardrails** (`UserPolicy`, checked again under a row lock in
  `AdminAccounts`): at most 3 active accounts; nobody resets or deactivates
  themselves; whoever acts must still be able to sign in. Together these mean
  the last admin can never be switched off, even by two admins at once — on a
  host without a command line that would be unrecoverable.
- **Deactivate, never delete.** Deactivating or resetting signs the account out
  everywhere at once; `EnsureAccountCanSignIn` turns it away on its next
  request whatever the session driver. A deactivated account is told so only
  after typing the right password, so the message reveals nothing to a stranger.
- Every change is in the audit log (`user.*`) naming the account, never a
  password, hash or link. `password` is not mass-assignable.
- Sign-ins are not logged (they would bury everything else); the Users page
  shows each account's last sign-in instead.

**Leaks of children's data**
- The class book-debt link carries a version inside its signature. *Make a new
  link* withdraws every copy of the old one at once.
- Exports record who took them and how many rows, and say inside the file that
  they hold personal data (§16).
- Backups are encrypted and copied off the server (`DEPLOYMENT.md`).

## What is accepted, not fought

- **Device limits and the block list are deterrents.** Clearing cookies or a
  private window gets a new device token. The real ceiling is the per-address
  limit. A CAPTCHA would stop a teacher in their fifties before it stopped a
  teenager.
- **The student-reporting switch is advisory.** A student can pick *Someone
  else*. Only the label changes; nothing is unlocked.
- **The tags stay sequential.** Random tags would break reading a scratched
  sticker aloud. Exposing almost nothing is the better trade.
- **Enrolled inspector phones are trusted by cookie.** Someone who copied that
  phone's cookie could record counted checks. Withdraw the phone if it is lost,
  and set it up again.

## When something goes wrong

| What happened | Do this |
|---|---|
| Someone may know your password | Manage → Change my password — every other device is signed out |
| Someone may know another admin's password | Manage → Users → the person → *Reset password*, and give them the new link in person |
| An admin leaves the school | Manage → Users → the person → *Deactivate* |
| Nobody can sign in at all | `php artisan user:password email` (or `user:create`) on the server — the emergency route |
| A class link reached the wrong person | Books → Returns → the class → *Make a new link*, and send the new one |
| An inspector's phone is lost or replaced | Manage → Inspectors' phones → *Withdraw this phone*, then set up the new one |
| Joke reports are arriving | Manage → Activity → find the rows → *Block this device* |
| Someone asks to be forgotten | `php artisan person:erase {id}` (see `RETENTION.md`) |

## Deployment

The settings that decide whether any of the above holds — document root, debug
off, two database users, encrypted off-server backups, no development packages —
are in `DEPLOYMENT.md`, with a checklist to run after every deploy.
