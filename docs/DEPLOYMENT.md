# Deploying to shared cPanel hosting

This is an operations note, not a user guide. It covers getting the application
onto the school's host and keeping it safe there.

Two assumptions from the specification shape everything below: **SSH and
Composer may not exist on the server**, and **the whole thing runs on shared
hosting**. So the build happens on your machine and the result is uploaded.

---

## Before you start — two things to confirm with the host

Both are blockers. Find out before touching anything else.

**1. Which PHP versions does cPanel offer?** *Select PHP Version* or *MultiPHP
Manager*. Laravel 13 needs **PHP 8.3 minimum**; 8.4 is what this was built
against. If the host caps at 8.2 the framework cannot run and this becomes a
hosting decision, not a code one. Do not downgrade Laravel to fit.

**2. Is there SSH and a `composer` binary?** If yes, everything below gets
easier and you can run `composer install` and `php artisan migrate` on the
server. If no, follow the upload route.

---

## Building the release

Run all of this **locally**, then upload the result.

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

`--no-dev` is not optional. It keeps development tooling out of the release.
See the checks at the end.

`npm run build` compiles CSS and JavaScript into `public/build`. **Node never
runs on the server.** Upload the compiled output; the host needs no Node at all.

Note that `public/build` is in `.gitignore`. If you deploy by pulling from git
rather than uploading, either remove that line so the built assets are committed,
or build on the server — and the server has no Node, so in practice that means
committing them.

### What to upload

Everything **except**:

```
node_modules/          never needed on the server
tests/                 not used in production
.git/
storage/framework/cache/data/*
storage/logs/*
```

`vendor/` **does** get uploaded — that is the point of building locally.

---

## Document root

**Point the domain's document root at `/public`, not at the project folder.**

This is one setting in cPanel and it is the single worst thing to get wrong. If
the document root is the project root, then `https://school.example/.env` is a
plain file that anybody can download, and it contains the database password and
the application key.

In cPanel: *Domains* → the domain → *Document Root* → append `/public`.

Verify it afterwards from a browser that is not logged in:

```
https://school.example/.env          → must be 404 or 403, never a download
https://school.example/storage/      → must not list files
https://school.example/              → the login screen
```

If `.env` downloads, stop and fix the document root before putting any real data
in.

The project folder carries its own `.htaccess` that denies everything, and
`public/.htaccess` grants access back to that one folder. It is insurance: if the
document root is ever set to the project folder by mistake, nothing outside
`public/` — `.env`, `storage/` — can be fetched. Upload both files and keep them
together: without the grant in `public/.htaccess`, Apache applies the parent's
denial to the whole site.

---

## Environment

Copy `.env.example` to `.env` on the server and set these.

| Variable | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Also what stops `DemoSeeder` from running |
| `APP_DEBUG` | `false` | A stack trace prints your database credentials on screen |
| `APP_KEY` | generate once | See below |
| `APP_URL` | `https://school.example` | Goes on every QR sticker. See below |
| `DB_CONNECTION` | `mysql` | |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from cPanel | |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | No Redis on shared hosting |
| `QUEUE_CONNECTION` | `database` | |
| `MAIL_MAILER` | `log` | Email is not configured; nothing sends |
| `SESSION_SECURE_COOKIE` | leave unset | Secure by default when `APP_ENV=production` |
| `AUTH_REMEMBER_MINUTES` | leave unset | "Keep me signed in" lasts 14 days (20160) |
| `SCHOOL_FORCE_HTTPS` | leave unset | Plain HTTP is redirected to HTTPS. Set `false` only if the host already redirects and the two loop |

Generate the key once and never change it afterwards — rotating `APP_KEY`
invalidates every encrypted cookie, which signs everybody out and breaks the
one-year reporter cookie on every phone in the school:

```bash
php artisan key:generate
```

If there is no SSH, run it locally against a throwaway `.env`, then copy the
`APP_KEY=base64:…` line onto the server by hand.

**`APP_URL` matters more than it looks.** Every QR sticker and cupboard address
is built from it, and nothing else: the address the site happens to be opened
from makes no difference. A sticker cannot be updated, so set this to the final
school-owned address, on HTTPS, before printing a single label. If it still says
`localhost` or a temporary address, that is what goes on the stickers.

Signed links work the other way round. Class book-debt links and the once-only
setup links carry the address they were made on and are checked against it, so
make the ones you hand out from the final address. A link made on a temporary
address stops working when that address goes away; make a new one.

### HTTPS

cPanel provides free Let's Encrypt certificates — turn one on. The application
forces HTTPS by itself once `APP_ENV=production`: plain-HTTP requests are
redirected, and every HTTPS response tells the browser to stay on HTTPS for a
year (HSTS). So a certificate has to exist and renew, or nothing will load.

If pages loop between HTTP and HTTPS, the host is ending HTTPS at a proxy and
not saying so. The application already honours `X-Forwarded-Proto`; if the loop
persists, set `SCHOOL_FORCE_HTTPS=false` and turn on the host's own redirect.

### Two database users, not one

The realistic way this system dies is a database wiped by mistake, so the
account the website runs as should not be able to wipe it. In cPanel →
*MySQL Databases*, make two users for the same database:

| User | Privileges | Used by |
|---|---|---|
| the app user | `SELECT`, `INSERT`, `UPDATE`, `DELETE` only | `DB_USERNAME` in `.env`, always |
| a migration user | `ALL PRIVILEGES` | only while running `php artisan migrate` |

Nothing the website does needs to create, alter or drop a table. To migrate,
put the migration user's name and password in `.env`, run the migration, run
`php artisan config:clear`, and put the app user back.

---

## First run on the server

```bash
php artisan migrate --force
php artisan db:seed --force          # categories, the off-site room, settings
php artisan user:create "Director Name" director@school.example
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`--force` is required because these are destructive-capable commands and the
environment is production.

`db:seed` runs `DatabaseSeeder`, which creates the twelve categories, the
*Off-site / with staff* room and the default settings. It does **not** create
demo data — see below.

**There is no sign-up page and no "forgot password" link,** by design. The
**first** account is made with `user:create`. If you cannot run artisan on the
server, create it locally before uploading and it comes across in the database.

After that, accounts are managed in the browser: **Manage → Users** (add a
user, reset a password, deactivate someone who has left) and **Manage → Change
my password**. Nobody ever sees anyone else's password: adding or resetting a
user gives a once-only link, valid for an hour, with which that person chooses
their own. Give it to them in person or privately — never in a group chat. At
most three accounts can be active at once.

Passwords must be at least 16 characters, must not appear in a known data
breach, and must not contain the school's name or the email address. A phrase of
four or more ordinary words is the easiest way to meet that.

`user:password` is now the emergency route only, for when nobody can sign in. It
also signs the account out everywhere.

### Inspectors' phones

A fire-extinguisher check only counts when it is recorded on a phone set up
under *Manage → Inspectors' phones*, or by an admin who is signed in. Anyone else
can still record what they found, but it will not reset the inspection date.
Set up the caretaker's phone (or whoever does the rounds) before the first round
of inspections: *Make a set-up code*, then scan it with that phone straight away.
The code works once and runs out after half an hour.

If you cache config, remember: **`php artisan config:clear` after every `.env`
change**, or the old values keep being used.

### Writable directories

`storage/` and `bootstrap/cache/` must be writable by the web server. Photos are
stored under `storage/app/private` — outside the webroot on purpose, served
through a controller. If uploads fail silently, check these first.

---

## Demo data must not go near this

`DemoSeeder` invents 100 students, 20 staff, 63 assets and a handful of reports.
Every person it creates is marked `(demo)`, but the safest position is that it
never runs on the live site at all.

Two things keep it out:

1. It is **not** registered in `DatabaseSeeder`, so `db:seed` and
   `migrate --seed` never reach it. It only runs when named explicitly.
2. It **throws** if `APP_ENV=production`.

```bash
php artisan db:seed --class=DemoSeeder    # local only; refuses in production
```

If you want to show the system to somebody, do it from a local copy or a staging
subdomain with `APP_ENV=staging`, never from the school's live database.

---

## Backups — set this up before the school types anything real

The realistic way this system dies is not an attacker. It is a database wiped by
a bad migration in month four.

**Two layers, both before launch:**

**1. cPanel's own backup.** *Backup Wizard* → schedule a full account backup, and
confirm it includes the database, not just files. Check the retention setting —
some hosts keep only one.

**2. A weekly `mysqldump`,** encrypted, with a copy kept somewhere that is not
the same server.

Keep the database password out of the command. A password written after `-p`
shows up in the process list and in cron's own logs. Put it in a file only you
can read instead — in cPanel's *File Manager* or *Terminal*, create
`~/.my.cnf`:

```ini
[client]
user=THE_APP_DB_USER
password=THE_APP_DB_PASSWORD
```

and set its permissions to `600` (owner read/write only). `mysqldump` reads it
by itself.

The dump holds the names and borrowing history of children, so it is encrypted
before it is written. Create `~/.backup-pass` with one long random line in it,
also `600`, and keep a copy of that line somewhere safe **off** the server — a
backup nobody can decrypt is not a backup. Then the cron job in cPanel:

```bash
mysqldump --single-transaction --no-tablespaces DATABASE | gzip | openssl enc -aes-256-cbc -pbkdf2 -salt -pass file:$HOME/.backup-pass > ~/backups/inventory-$(date +\%F).sql.gz.enc
```

cPanel cron escapes `%` as `\%` — that is not a typo. To restore:

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -pass file:$HOME/.backup-pass -in inventory-2026-10-05.sql.gz.enc | gunzip | mysql DATABASE
```

**Off the server.** A backup on the same cPanel account dies with the account: a
hosting failure, an unpaid invoice or a compromised password takes both. Once a
month, download the newest `.enc` file from *File Manager* to the school's own
computer (it is already encrypted), or point cPanel's *Backup Wizard* at a
remote destination if the host offers one.

Add a second weekly job to delete anything older than about eight weeks, or the
disk quota fills up quietly:

```bash
find ~/backups -name 'inventory-*.sql.gz.enc' -mtime +56 -delete
```

**Test the restore once.** A backup nobody has restored is a hypothesis. Load a
dump into a local database and check the asset count matches.

Two things worth knowing about what backups hold:

- Photos live in `storage/app/private` and are **not** in a `mysqldump`. The
  cPanel file backup covers them; a database-only backup does not.
- A person erased with `person:erase` today is still named in last week's dump.
  See `RETENTION.md`.

---

## Confirming development packages are excluded

Everything under `require-dev` in `composer.json` stays on your machine, and
`composer install --no-dev` is what leaves it out. Verify on the server after
deploying:

```bash
ls vendor/pestphp                  # must not exist
```

If it exists, `--no-dev` was missed. Rebuild and re-upload `vendor/`.

---

## After deploying — the checks worth doing

Run through these once, in a browser that is not signed in:

- [ ] `https://school.example/.env` returns 404 or 403
- [ ] `http://school.example/` (plain HTTP) redirects to `https://`
- [ ] `https://school.example/` shows the login screen over HTTPS
- [ ] The response headers of any page include `Content-Security-Policy` and
      `X-Frame-Options: DENY` (browser developer tools → Network → the page)
- [ ] `https://school.example/a/INV-PC-0001` shows a scan page with **no** price,
      serial number or supplier on it — and does not redirect to login
- [ ] `https://school.example/robots.txt` disallows everything
- [ ] Signing in works, and the Home screen loads
- [ ] Print one label sheet onto plain paper and hold it against a real sticker
      sheet before doing a full run — printer margins vary and being 2mm out
      ruins the sheet
- [ ] Scan a QR on that plain-paper sheet with a phone. It must open the final
      `https://` address. If it opens anything else, fix `APP_URL` before
      printing any stickers

Then, and only then, start entering real data.

---

## Updating later

```bash
# locally
composer install --no-dev --optimize-autoloader
npm run build

# upload changed files, then on the server
php artisan migrate --force          # as the migration user, then switch back
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Take a database backup before running any migration.** That is the failure mode
that kills this system, and it takes thirty seconds to avoid.
