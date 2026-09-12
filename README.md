# Atelier

Atelier presents design work to clients behind a shareable link and collects
anchored feedback, without asking those clients to create an account. Creators
sign in; clients follow a link, optionally clear a password gate, and comment.

- **Design language & specs:** `docs/atelier.specs.md`
- **Architecture decisions:** `docs/adr/`
- **Contributor / agent context:** `CONTEXT.md`, `CLAUDE.md`

This document is for **standing the app up** — locally and in production. It
covers the non-obvious pieces (the sandbox vhost and what it does *not* protect,
web push, mail, creating the first account, trusted proxies, the queue worker,
the scheduler and the agent's MCP token) that otherwise live only in scattered
config comments.

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
composer run dev    # app server, Vite, a queue listener and a log tail
```

`composer setup` creates `.env` from `.env.example`, generates an `APP_KEY`,
runs migrations, and builds the front end. `composer run dev` is the day-to-day
loop: it delegates to `php artisan dev`, which supervises four processes — the
PHP server, Vite, `queue:listen`, and a `pail` log tail. Run `php artisan
dev:list` to see them. If a front-end change doesn't show up, run `npm run dev`
(or `npm run build`) — see `CLAUDE.md`.

Seed some realistic feedback to click around:

```bash
php artisan db:seed --class=PrototypeCommentsSeeder
```

### The quality gate

```bash
composer ci:check   # Pint (style) + PHPStan (level 7) + Pest
```

`tests/Browser` drives a real Chromium through Playwright. `composer setup`
installs the npm package; the browser binary is a separate one-off download:

```bash
npx playwright install chromium
```

The same gate runs in CI (`.github/workflows/tests.yml`), but that workflow is
`workflow_dispatch` only — **nothing runs automatically** on a push or a pull
request. Run `composer ci:check` yourself before pushing, or start the workflow
by hand from the Actions tab. Keep it green.

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

#### The sandbox is protected by obscurity only

This is the one property of Atelier an operator must understand before putting
anything on it. The sandbox serves bundles **by path, with no authentication**,
and that is by design (specs §4.1).

A project's `private`/`public` flag and its password gate are enforced by the
**app**. Markdown and file artifacts are streamed through the app, so that check
runs on every request. HTML bundles never pass through the app at all — so
anyone who obtains a sandbox URL can view that mockup, whatever the project's
visibility says, and no matter whether its password has been entered.

What protects a bundle is only that its directory is named by an unguessable
per-project token (24 CSPRNG bytes, `atelier.sandbox_token_bytes`). A URL has to
leak for this to bite — but when it leaks, nothing else stands in the way.
Therefore:

- **Confidential material belongs in markdown or file artifacts** — those are
  app-gated on every request. Never put it in an HTML bundle.
- HTML mockups are accepted as "safe to be obscurity-protected". That was a
  deliberate decision, not an oversight (ADR-0002).
- If a project ever genuinely needs gated HTML, the escalation path is
  signed/expiring iframe URLs (specs §6.4). It is not built — so until it is,
  the rule above is the whole control.

### 3. Web push (VAPID)

Browser notifications need a VAPID key pair. Generate one and set the values:

```bash
php artisan webpush:vapid   # writes the two keys into .env
```

The command generates and writes `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY`
only. It does **not** write `VAPID_SUBJECT` — set that one by hand, as Safari
and iOS reject a subscription without it:

```dotenv
VAPID_SUBJECT="mailto:you@example.com"   # a mailto: or https: URL
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

### 5. The first Creator account

Self-registration is deliberately off — `Features::registration()` is commented
out in `config/fortify.php`, and there is no `register` route. A migrated
install therefore has **no account and no way to sign in** until you create the
first Creator by hand. Do this once, on the box, after `migrate` and after mail
(§4) works:

```bash
php artisan tinker --execute '
    $creator = App\Models\User::create([
        "name" => "Your Name",
        "email" => "you@example.com",
        "role" => App\Enums\UserRole::Creator,
        "password" => null,
    ]);
    $creator->forceFill(["email_verified_at" => now()])->save();
    Illuminate\Support\Facades\Password::sendResetLink(["email" => $creator->email]);
'
```

This is exactly what the Users panel's **Invite** action does: it leaves the
password null and mails a reset link, so no password is ever typed into a shell
or left in its history. Follow the emailed link to set one. Passkeys and
two-factor authentication are then available under **Settings → Security**.

Every Creator after the first is invited from **Admin → Users**, in the browser.
The shell is only needed to break the bootstrap circle.

### 6. Trusted proxies

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

### 7. Queue worker

`QUEUE_CONNECTION=database` and the comment/reply notifications are queued, so a
slow or failing push endpoint never blocks a client's comment. **A worker must
be running** or notifications (including the in-app bell) never send.

Run one under a process supervisor (systemd, Supervisor, …):

```bash
php artisan queue:work --queue=default --tries=3 --max-time=3600
```

Restart it on deploy: `php artisan queue:restart`.

### 8. Scheduler

Register Laravel's scheduler with a single system cron entry:

```cron
* * * * * cd /path/to/atelier && php artisan schedule:run >> /dev/null 2>&1
```

It drives `atelier:prune-client-data` (below). Without this cron, scheduled
retention never runs.

### 9. Agent access over MCP (optional)

Atelier hosts an MCP server at `APP_URL/mcp` (`routes/ai.php`) so an AI agent can
read and write artifact content on your behalf (ADR-0006). The route is public
but guarded by `auth:sanctum`, and it is **inert until you mint a token** — no
credential exists until you create one.

```bash
php artisan atelier:agent-token            # provision the Agent User, print a token once
php artisan atelier:agent-token --revoke   # revoke every agent token
```

The same two actions live in the browser under **Admin → Users → Agent access**;
both go through the same provisioner, so they cannot drift.

Before you mint one, know what it is:

- **Shown once.** The plaintext token is printed at mint time and never again.
  Put it in the agent's configuration, never in this repository.
- **Account-wide.** It reaches every project in the install. There are no
  per-project grants — that is deferred to a multi-tenant Atelier (ADR-0006).
- **Bounded by capability, not by scope.** The abilities granted are
  `artifact:read`, `artifact:write`, `comment:read` and `comment:reply`: the
  agent shapes content (create, update, rename, delete and reorder markdown
  artifacts; reply in threads) and cannot control exposure. Publishing,
  visibility, project lifecycle and resolving feedback are Creator-only and are
  not exposed as tools at all.
- **Revocation is the mitigation.** A leaked token can rewrite every project's
  markdown, and the answer to that is `--revoke`, which cuts the agent off
  immediately. Mint a fresh one to restore access; the Creator's own credentials
  are untouched either way.

If you never run this command, the MCP surface stays closed and nothing else in
this section applies.

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

---

## License

Atelier is open-sourced software licensed under the [MIT license](LICENSE) — use it however you like, with no warranty of any kind.
