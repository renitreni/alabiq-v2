#!/usr/bin/env bash
# Shared-hosting deploy for alabiq.yaramay.online
#
# This server has no Docker and no Node, so:
#   - frontend assets are built locally (`npm run build`) and synced via rsync
#   - PHP deps + migrations + framework caches run on the server
#
# Usage: ./deploy.sh [ssh_host]   # ssh_host defaults to u805024096@178.16.135.22 -p 65002
set -euo pipefail

SSH_HOST="${1:-u805024096@178.16.135.22}"
SSH_PORT="65002"
WEB_ROOT="domains/alabiq.yaramay.online/public_html"

echo "==> Building frontend assets locally"
npm run build --silent

echo "==> Syncing application files to shared hosting (rsync; no --delete, to keep server-only files)"
rsync -avz -e "ssh -p $SSH_PORT" \
  --exclude='.git' \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='.DS_Store' \
  --exclude='storage' \
  --exclude='bootstrap/cache/*.php' \
  --exclude='public/storage' \
  --exclude='public/hot' \
  --exclude='.htaccess' \
  --exclude='public/.htaccess' \
  --exclude='*.log' \
  --exclude='composer.phar' \
  --exclude='.phpunit.result.cache' \
  . "$SSH_HOST:$WEB_ROOT/"

echo "==> Running deploy steps on server"
ssh -p "$SSH_PORT" "$SSH_HOST" <<'EOF'
set -euo pipefail
WEB_ROOT="domains/alabiq.yaramay.online/public_html"
cd "$WEB_ROOT"

# Restore production customizations rsync must not overwrite.
# Backups live one level above public_html, under the domain dir.
B="$(ls -td "$HOME/domains/alabiq.yaramay.online/deploy/backups/custom_"* 2>/dev/null | head -1 || true)"
if [ -z "$B" ]; then
  echo "  ERROR: no customization backup found; aborting" >&2
  exit 1
fi
cp "$B/AppServiceProvider.php" app/Providers/AppServiceProvider.php
cp "$B/site.blade.php" resources/views/layouts/site.blade.php
cp "$B/.htaccess" .htaccess
rm -f .htaccess.swp
echo "  restored production customizations from $B"

# PHP dependencies (production, optimized)
# NOTE: server runs PHP 8.3 while local composer.lock may require PHP 8.4+,
# so use `composer update --no-dev` (not install) to regenerate a compatible lock.
composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Migration reconciliation: mark migrations whose tables already exist (from a
# previous/older schema on this shared host) so migrate only creates new tables.
php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
\$ran = DB::table('migrations')->pluck('migration')->toArray();
foreach (glob('database/migrations/*.php') as \$f) {
    \$base = basename(\$f, '.php');
    if (in_array(\$base, \$ran)) continue;
    \$content = file_get_contents(\$f);
    if (preg_match(\"/Schema::create\(\s*'([^']+)'/\", \$content, \$m)) {
        if (Schema::hasTable(\$m[1])) {
            DB::table('migrations')->insert(['migration' => \$base, 'batch' => 99]);
            echo \"marked \$base\n\";
        }
    }
}
echo \"migration reconciliation complete\n\";
"

# Migrations
php artisan migrate --force

# Framework caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage symlink (artisan storage:link uses exec() which is disabled on
# shared hosting, so fall back to a manual symlink)
if [ ! -L public/storage ]; then
  ln -s ../storage/app/public public/storage 2>/dev/null || true
fi

# Permissions (shared hosting: web user needs read + storage write)
chmod -R u+rwX,go+rX storage bootstrap/cache 2>/dev/null || true
chmod -R 755 storage bootstrap/cache public 2>/dev/null || true

echo "==> Deploy complete on $(hostname)"

EOF
