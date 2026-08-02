# Atelier

Atelier presents design work to clients behind a shareable link and collects
anchored feedback, without asking those clients to create an account. Creators
sign in; clients follow a link, optionally clear a password gate, and comment.

- **Design language & specs:** `docs/atelier.specs.md`
- **Architecture decisions:** `docs/adr/`
- **Contributor / agent context:** `CONTEXT.md`, `CLAUDE.md`

This document is for **standing the app up** — locally and in production. It
covers the non-obvious pieces (the sandbox vhost, web push, mail, trusted
proxies, the queue worker and the scheduler) that otherwise live only in
scattered config comments.

---

## Requirements

- PHP **8.4**
- Composer 2
- Node **22** and npm
- A database (SQLite works out of the box; MySQL/Postgres in production)

---

## Local development

```bash
composer setup      # install, copy .env, generate APP_KEY, migrate, npm install + build
composer run dev    # serve the app, Vite, and a queue worker together
```

`composer setup` creates `.env` from `.env.example`, generates an `APP_KEY`,
runs migrations, and builds the front end. `composer run dev` is the day-to-day
loop. If a front-end change doesn't show up, run `npm run dev` (or `npm run
build`) — see `CLAUDE.md`.

Seed some realistic feedback to click around:

```bash
php artisan db:seed --class=PrototypeCommentsSeeder
```

### The quality gate

```bash
composer ci:check   # Pint (style) + PHPStan (level 7) + Pest
```

CI runs this on every push to `main` and every pull request
(`.github/workflows/tests.yml`). Keep it green.

---

## Production deployment

### 1. Environment

Set at minimum, in the production `.env` (or real environment variables):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # php artisan key:generate --force
APP_URL=https://your-app.example.com
```

Then the usual build and cache step:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize            # config + route + view cache
```

Security headers (Content-Security-Policy, `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`, and HSTS over TLS) are added to every
app-origin response automatically by `App\Http\Middleware\SecurityHeaders` — no
web-server configuration required. The CSP already allows the HTML-artifact
iframe to reach the sandbox origin below.

### 2. The sandbox vhost (required for HTML "pages")

HTML artifacts are uploaded as zip bundles, unpacked to disk, and served from a
**second, PHP-less document root** — a different origin from the app. This
origin isolation is deliberate (ADR-0002 / specs §6): it keeps client-authored
HTML from ever executing on the app's own origin.

Configure the two halves — they **must point at the same directory**:

| Config | `.env` | Meaning |
| --- | --- | --- |
| `atelier.sandbox.path` | `ATELIER_SANDBOX_PATH` | Where the app writes unpacked bundles |
| `atelier.sandbox.url`  | `ATELIER_SANDBOX_URL`  | The public origin the sandbox vhost serves them from |

Then add a second vhost whose document root is `ATELIER_SANDBOX_PATH`, on the
host in `ATELIER_SANDBOX_URL`, with:

- **PHP execution disabled** (static files only), and
- **directory indexing off**.

Example nginx server block:

```nginx
server {
    server_name sandbox.example.com;                 # == ATELIER_SANDBOX_URL host
    root /var/www/atelier-sandbox;                    # == ATELIER_SANDBOX_PATH
    autoindex off;                                    # no directory listings

    location / {
        try_files $uri $uri/ =404;                    # static only; never pass to PHP
    }
}
```

Give it its own TLS certificate. Do **not** map any PHP handler into this root.

### 3. Web push (VAPID)

Browser notifications need a VAPID key pair. Generate one and set the values:

```bash
php artisan webpush:vapid        # writes VAPID_* into .env
```

```dotenv
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT="mailto:you@example.com"   # required by Safari/iOS
```

Never commit real keys. Rotating the keypair invalidates existing browser
subscriptions (they simply re-subscribe on next visit).

### 4. Mail

A real transport is required — password reset, email verification, and the
Creator's comment notifications all send mail. The default `MAIL_MAILER=log`
only writes to the log and must not be used in production.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="you@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 5. Trusted proxies

Behind a CDN or load balancer, `request()->ip()` collapses to the proxy's
address unless the forwarded headers are trusted — which silently breaks every
per-IP rate limit (the comment abuse ceilings and the project-unlock throttle)
and turns the per-IP comment ceiling into a single global budget.

Set `TRUSTED_PROXIES` (read by `config/trustedproxy.php`):

```dotenv
TRUSTED_PROXIES=*          # a trusted-network LB that terminates the connection
# TRUSTED_PROXIES=10.0.0.0/8,192.168.1.5   # or an explicit proxy list / CIDRs
```

Unset trusts nothing — correct for direct serving, wrong behind a proxy.

### 6. Queue worker

`QUEUE_CONNECTION=database` and the comment/reply notifications are queued, so a
slow or failing push endpoint never blocks a client's comment. **A worker must
be running** or notifications (including the in-app bell) never send.

Run one under a process supervisor (systemd, Supervisor, …):

```bash
php artisan queue:work --queue=default --tries=3 --max-time=3600
```

Restart it on deploy: `php artisan queue:restart`.

### 7. Scheduler

Register Laravel's scheduler with a single system cron entry:

```cron
* * * * * cd /path/to/atelier && php artisan schedule:run >> /dev/null 2>&1
```

It drives `atelier:prune-client-data` (below). Without this cron, scheduled
retention never runs.

---

## Data protection & retention

Atelier captures client name + email at comment time and a persistent
`atelier_commenter` cookie (ADR-0003, specs §13). Two erasure paths exist:

- **On request (always available):**
  ```bash
  php artisan atelier:forget-client "client@example.com"
  ```
  Anonymises the client and redacts their comment bodies, preserving thread
  structure.

- **Time-based, on a schedule (opt-in):** off by default — erasure is
  on-request-only, matching the retention statement in `config/atelier.php`. Set
  a window to also prune automatically:
  ```dotenv
  ATELIER_PRIVACY_RETENTION_DAYS=365
  ```
  With the scheduler running, `atelier:prune-client-data` then erases a client
  once **every** project they commented on has gone inactive (archived or past
  its share-link expiry) **and** their most recent comment is older than the
  window. A client still active on any live project is never touched. Preview
  with `php artisan atelier:prune-client-data --dry-run`.

The data-subject contact address and the plain-language retention statement are
configurable under `atelier.privacy` (`ATELIER_PRIVACY_CONTACT`,
`ATELIER_PRIVACY_RETENTION`).
