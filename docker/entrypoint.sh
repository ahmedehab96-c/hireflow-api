#!/bin/sh
set -e

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="${RENDER_EXTERNAL_URL}"
fi

case "${APP_KEY:-}" in
  base64:????????????????*)
    ;;
  *)
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    ;;
esac

# This Dockerfile/entrypoint only runs in the free-tier Render demo
# deployment (local dev uses `php artisan serve` directly, no Docker) — so
# it's safe to hardcode demo-appropriate defaults here rather than depend on
# dashboard-configured env vars (this Render CLI has no env-var subcommand).
: "${DB_CONNECTION:=sqlite}"
: "${SEED_ON_START:=true}"
: "${SEED_DEMO_DATA:=true}"
: "${APP_ENV:=production}"
: "${APP_DEBUG:=false}"
: "${SESSION_DRIVER:=file}"
: "${CACHE_STORE:=file}"
: "${QUEUE_CONNECTION:=sync}"
: "${MAIL_MAILER:=log}"
: "${CORS_ALLOWED_ORIGINS:=https://ahmedmyportofilo.netlify.app}"

set_env() {
  key="$1"
  value="$2"
  [ -z "$value" ] && return 0
  TMP_ENV="$(mktemp)"
  grep -v "^${key}=" .env > "$TMP_ENV" || true
  echo "${key}=${value}" >> "$TMP_ENV"
  mv "$TMP_ENV" .env
}

set_env APP_KEY "${APP_KEY}"
set_env APP_URL "${APP_URL:-}"
set_env APP_ENV "${APP_ENV}"
set_env APP_DEBUG "${APP_DEBUG}"
set_env DB_CONNECTION "${DB_CONNECTION}"
set_env SESSION_DRIVER "${SESSION_DRIVER}"
set_env CACHE_STORE "${CACHE_STORE}"
set_env QUEUE_CONNECTION "${QUEUE_CONNECTION}"
set_env MAIL_MAILER "${MAIL_MAILER}"
set_env CORS_ALLOWED_ORIGINS "${CORS_ALLOWED_ORIGINS}"
set_env SEED_DEMO_DATA "${SEED_DEMO_DATA}"

export APP_KEY

php artisan config:clear --no-interaction >/dev/null 2>&1 || true

case "${APP_URL:-}" in
  https://*)
    export SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-true}"
    set_env SESSION_SECURE_COOKIE "${SESSION_SECURE_COOKIE:-true}"
    ;;
esac

php artisan migrate --force --no-interaction

SHOULD_SEED="${SEED_ON_START:-false}"
if [ "$SHOULD_SEED" = "true" ]; then
  php artisan db:seed --force --no-interaction
elif [ -f database/database.sqlite ]; then
  COUNT="$(php -r "
    try {
      \$pdo = new PDO('sqlite:database/database.sqlite');
      \$n = (int) \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
      echo \$n;
    } catch (Throwable \$e) {
      echo '0';
    }
  ")"
  if [ "$COUNT" = "0" ]; then
    php artisan db:seed --force --no-interaction
  fi
else
  php artisan db:seed --force --no-interaction
fi

php artisan storage:link --force >/dev/null 2>&1 || true

PORT="${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
