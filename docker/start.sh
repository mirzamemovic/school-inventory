#!/bin/sh
# Runs each time the demo container starts. See VIDEO-DEMO.md.
set -e

export PORT="${PORT:-8080}"

# Every sticker the site prints takes its address from APP_URL, and the host
# knows that address, so nobody has to type it in.
if [ -z "$APP_URL" ]; then
    if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
        export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
    elif [ -n "$RENDER_EXTERNAL_URL" ]; then
        export APP_URL="$RENDER_EXTERNAL_URL"
    fi
fi

# A new key on a restart only signs people out, which is fine for a demo.
if [ -z "$APP_KEY" ]; then
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
fi

php artisan db:seed --class=VideoDemoSeeder --force
chown -R www-data:www-data database storage bootstrap/cache

exec apache2-foreground
