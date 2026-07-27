#!/bin/bash
#
# SessionStart hook for Claude Code on the web.
#
# Bootstraps the project so tests, Pint, and Larastan run in a fresh remote
# container. It works around two egress-proxy limitations of that environment:
#
#   1. GitHub *dist* zipballs (api.github.com / codeload) are blocked for repos
#      outside the session's scope, so Composer cannot use the fast dist path and
#      must install from git source. phpstan/phpstan is dist-only (no VCS source
#      in the lock), so it is installed from a git clone via a temporary path repo.
#   2. fonts.bunny.net is blocked, so the Vite build skips remote font resolution
#      via ATELIER_SKIP_REMOTE_FONTS (guarded in vite.config.js).
#
# Runs in ASYNC mode: the session starts immediately and this work proceeds in
# the background, because a cold-container Composer source install is slow and
# should not block startup. Progress is written to storage/logs/session-start.log,
# and completion is signalled by storage/framework/.session-ready. The steps are
# idempotent, so a warm container (deps already present) finishes near-instantly.

set -uo pipefail

# A local machine or CI installs dependencies its own way — no-op instantly, and
# do it before emitting the async directive so a local run stays fully synchronous.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

# Start the session now; keep installing in the background (timeout: 15 min).
echo '{"async": true, "asyncTimeout": 900000}'

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

export COMPOSER_ALLOW_SUPERUSER=1

# From here on everything is background work — send it to a log, not the session.
mkdir -p storage/logs storage/framework
LOG="storage/logs/session-start.log"
exec >>"$LOG" 2>&1

READY_MARKER="storage/framework/.session-ready"
rm -f "$READY_MARKER"

log() { echo "[session-start $(date -u '+%H:%M:%S')] $*"; }

log "Bootstrap started"

# --- 1. Environment file -----------------------------------------------------
if [ ! -f .env ]; then
    log "Creating .env from .env.example"
    cp .env.example .env
fi

# --- 2. Composer dependencies ------------------------------------------------
install_composer_from_source() {
    # phpstan/phpstan is dist-only in the lock; its zipball is blocked by the
    # proxy. Clone it at the locked version and feed it through a path repo so a
    # single `composer update` installs everything (from git source) in one pass.
    local version ref pkgdir
    version=$(php -r '$l=json_decode(file_get_contents("composer.lock"),true); foreach(array_merge($l["packages"]??[],$l["packages-dev"]??[]) as $p){ if(($p["name"]??"")==="phpstan/phpstan"){ echo $p["version"]; break; } }')
    ref=$(php -r '$l=json_decode(file_get_contents("composer.lock"),true); foreach(array_merge($l["packages"]??[],$l["packages-dev"]??[]) as $p){ if(($p["name"]??"")==="phpstan/phpstan"){ echo $p["dist"]["reference"] ?? ""; break; } }')

    if [ -z "$version" ]; then
        log "ERROR: phpstan/phpstan not found in composer.lock; cannot apply fallback"
        return 1
    fi

    pkgdir="${HOME:-/root}/.cache/atelier-hooks/phpstan-${version}"
    if [ ! -f "$pkgdir/composer.json" ]; then
        log "Cloning phpstan/phpstan ${version} from git source"
        rm -rf "$pkgdir"
        mkdir -p "$(dirname "$pkgdir")"
        git clone --quiet --depth 1 --branch "$version" https://github.com/phpstan/phpstan.git "$pkgdir"
    fi

    if [ -n "$ref" ] && [ "$(git -C "$pkgdir" rev-parse HEAD)" != "$ref" ]; then
        log "WARNING: cloned phpstan HEAD does not match locked reference ${ref}"
    fi

    cp composer.json .composer.json.hookbak
    cp composer.lock .composer.lock.hookbak
    # Restore the pristine manifest/lock however this function exits, so the
    # committed files never carry the temporary path repository.
    trap 'mv -f .composer.json.hookbak composer.json 2>/dev/null || true; mv -f .composer.lock.hookbak composer.lock 2>/dev/null || true' RETURN

    PHPSTAN_PATH="$pkgdir" php -r '
        $d = json_decode(file_get_contents("composer.json"), true);
        $d["repositories"] = $d["repositories"] ?? [];
        array_unshift($d["repositories"], ["type" => "path", "url" => getenv("PHPSTAN_PATH"), "options" => ["symlink" => false]]);
        file_put_contents("composer.json", json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    '

    composer update phpstan/phpstan --prefer-source --no-interaction --no-progress --no-scripts
}

if [ ! -f vendor/autoload.php ]; then
    log "Installing Composer dependencies (this is the slow step on a cold container)"
    if composer install --no-interaction --no-progress 2>/dev/null; then
        log "Composer install succeeded via the default path"
    else
        log "Default install failed (proxy-blocked dist zipballs); installing from source"
        install_composer_from_source
    fi
    # Build Laravel's package manifest (skipped when --no-scripts was used).
    php artisan package:discover --ansi || true
else
    log "Composer dependencies already present; skipping"
fi

# --- 3. Application key -------------------------------------------------------
if ! grep -qE '^APP_KEY=base64:' .env; then
    log "Generating application key"
    php artisan key:generate --force --ansi
fi

# --- 4. Node dependencies & front-end build ----------------------------------
if [ ! -d node_modules ]; then
    log "Installing npm dependencies"
    npm install --no-audit --no-fund
else
    log "npm dependencies already present; skipping"
fi

if [ ! -f public/build/manifest.json ]; then
    log "Building front-end assets (remote fonts skipped)"
    ATELIER_SKIP_REMOTE_FONTS=1 npm run build
else
    log "Front-end build already present; skipping"
fi

touch "$READY_MARKER"
log "Bootstrap complete"
