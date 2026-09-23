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

`--no-dev` is not optional. It is what keeps development tooling — including
**Laravel Boost**, which reads your application structure and queries the
database — out of the release. See the checks at the end.

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

---

## Environment

Copy `.env.example` to `.env` on the server and set these.

| Variable | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Also what stops `DemoSeeder` from running |
| `APP_DEBUG` | `false` | A stack trace prints your database credentials on screen |
| `APP_KEY` | generate once | See below |
| `APP_URL` | `https://school.example` | Signed class-debt links are checked against this |
| `DB_CONNECTION` | `mysql` | |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from cPanel | |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | No Redis on shared hosting |
| `QUEUE_CONNECTION` | `database` | |
| `MAIL_MAILER` | `log` | Email is not configured; nothing sends |

Generate the key once and never change it afterwards — rotating `APP_KEY`
invalidates every encrypted cookie, which signs everybody out and breaks the
one-year reporter cookie on every phone in the school:

```bash
php artisan key:generate
```

If there is no SSH, run it locally against a throwaway `.env`, then copy the
`APP_KEY=base64:…` line onto the server by hand.

**`APP_URL` matters more than it looks.** The class book-debt links are signed
against the full URL. If `APP_URL` is wrong, every link a class teacher receives
returns *Forbidden*.

### HTTPS

cPanel provides free Let's Encrypt certificates — turn one on. The application
forces HTTPS by itself once `APP_ENV=production`, so a certificate has to exist
or nothing will load.

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

**There is no sign-up page and no password reset link,** by design. Accounts are
made with `user:create` and passwords changed with `user:password`. If you cannot
run artisan on the server, create the two accounts locally before uploading and
they come across in the database.

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

**2. A weekly `mysqldump`,** kept somewhere that is not the same server. A cron
job in cPanel:

```bash
mysqldump -u USER -pPASSWORD DATABASE | gzip > ~/backups/inventory-$(date +\%F).sql.gz
```

cPanel cron escapes `%` as `\%` — that is not a typo.

Add a second weekly job to delete anything older than about eight weeks, or the
disk quota fills up quietly:

```bash
find ~/backups -name 'inventory-*.sql.gz' -mtime +56 -delete
```

**Test the restore once.** A backup nobody has restored is a hypothesis. Load a
dump into a local database and check the asset count matches.

Two things worth knowing about what backups hold:

- Photos live in `storage/app/private` and are **not** in a `mysqldump`. The
  cPanel file backup covers them; a database-only backup does not.
- A person erased with `person:erase` today is still named in last week's dump.
  See `RETENTION.md`.

---

## Confirming Laravel Boost is excluded

Boost is a development dependency that inspects your application and queries the
database. It must never be reachable in production.

It is listed under `require-dev` in `composer.json`, so
`composer install --no-dev` leaves it out. Verify on the server after deploying:

```bash
ls vendor/laravel/boost            # must not exist
php artisan list | grep boost      # must return nothing
```

If either shows something, `--no-dev` was missed. Rebuild and re-upload
`vendor/`.

Also delete or ignore `.mcp.json` and `boost.json` on the server. They are inert
without the package, but there is no reason for them to be there.

---

## After deploying — the checks worth doing

Run through these once, in a browser that is not signed in:

- [ ] `https://school.example/.env` returns 404 or 403
- [ ] `https://school.example/` shows the login screen over HTTPS
- [ ] `https://school.example/a/INV-PC-0001` shows a scan page with **no** price,
      serial number or supplier on it — and does not redirect to login
- [ ] `https://school.example/robots.txt` disallows everything
- [ ] Signing in works, and the Home screen loads
- [ ] Print one label sheet onto plain paper and hold it against a real sticker
      sheet before doing a full run — printer margins vary and being 2mm out
      ruins the sheet

Then, and only then, start entering real data.

---

## Updating later

```bash
# locally
composer install --no-dev --optimize-autoloader
npm run build

# upload changed files, then on the server
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Take a database backup before running any migration.** That is the failure mode
that kills this system, and it takes thirty seconds to avoid.
