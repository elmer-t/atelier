#!/bin/bash
#
# SessionStart hook for Claude Code on the web.
#
# Bootstraps the project so tests, Pint, and Larastan run in a fresh remote
# container. It works around two egress-proxy limitations of that environment:
#
#   1. GitHub *dist* zipballs (api.github.com / codeload) are blocked for repos
#      outside the session's scope, so Composer packages that ship dist-only 403.
#      `git clone` of github.com works, so we install from source and handle the
#      one dist-only package (phpstan/phpstan) via a temporary path repository.
#   2. fonts.bunny.net is blocked, so the Vite build skips remote font resolution
#      via ATELIER_SKIP_REMOTE_FONTS (guarded in vite.config.js).
#
# Idempotent and non-interactive: safe to re-run; heavy steps are skipped when
# their outputs already exist.

set -euo pipefail

# Only bootstrap in the remote (Claude Code on the web) environment; a local
# machine or CI installs dependencies its own way.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

export COMPOSER_ALLOW_SUPERUSER=1

log() { echo "[session-start] $*"; }

# --- 1. Environment file -----------------------------------------------------
if [ ! -f .env ]; then
    log "Creating .env from .env.example"
    cp .env.example .env
fi

# --- 2. Composer dependencies ------------------------------------------------
install_composer_from_source() {
    # phpstan/phpstan is dist-only in the lock; its zipball is blocked by the
    # proxy. Clone it at the locked version and feed it through a path repo so
    # `composer update` can install everything else from git source too.
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

    # Confirm the clone matches the locked commit; warn but proceed if git's
    # shallow tag omitted the ref (version tag is the authoritative match).
    if [ -n "$ref" ] && [ "$(git -C "$pkgdir" rev-parse HEAD)" != "$ref" ]; then
        log "WARNING: cloned phpstan HEAD does not match locked reference ${ref}"
    fi

    cp composer.json .composer.json.hookbak
    cp composer.lock .composer.lock.hookbak
    # Restore the pristine manifest/lock no matter how this function exits, so
    # the committed files never carry the temporary path repository.
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
    log "Installing Composer dependencies"
    if ! composer install --no-interaction --no-progress 2>/dev/null; then
        log "Direct install failed (proxy-blocked dist zipballs); installing from source"
        install_composer_from_source
    fi
    # Build Laravel's package manifest (skipped when --no-scripts was used).
    php artisan package:discover --ansi || true
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
fi

if [ ! -f public/build/manifest.json ]; then
    log "Building front-end assets (remote fonts skipped)"
    ATELIER_SKIP_REMOTE_FONTS=1 npm run build
fi

log "Bootstrap complete"
