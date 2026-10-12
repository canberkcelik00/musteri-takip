#!/usr/bin/env bash
#
# Builds the production package and pushes it to main; the hosting pulls main from cPanel.
#
# The package is vendor without dev packages, the compiled theme, Filament's public assets
# and RELEASE (the source commit id, read by the deploy address). The app container's
# vendor is a Docker volume with dev packages, so the production vendor is installed in a
# throwaway container straight into the project folder, which the volume never touches.
#
# Usage (Git Bash, project folder, Docker running): ./yayin.sh

set -euo pipefail
cd "$(dirname "$0")"

fail() {
    echo "HATA: $1" >&2
    exit 1
}

# Paths the package consists of; nothing else may change while it is built.
package_paths=(vendor public/build public/js/filament public/css/filament public/fonts/filament RELEASE)

[ "$(git branch --show-current)" = "main" ] || fail "Yalnızca main dalında çalışır."
[ -z "$(git status --porcelain)" ] || fail "Commit edilmemiş değişiklikler var; önce commit edin."

container="$(docker compose ps -q app)"
[ -n "$container" ] || fail "Docker ortamı çalışmıyor; önce: docker compose up -d"

echo "== Testler, kod stili ve statik analiz"
docker compose exec -T app composer test || fail "Testler başarısız; yayın yapılmadı."
docker compose exec -T app composer lint || fail "Kod stili hatalı; yayın yapılmadı."
docker compose exec -T app composer analyse || fail "Statik analiz hatalı; yayın yapılmadı."

source_commit="$(git rev-parse HEAD)"
project_dir="$(pwd -W 2>/dev/null || pwd)"
image="$(docker inspect -f '{{.Image}}' "$container")"

echo "== Canlı bağımlılıkları (vendor, dev paketleri olmadan)"
# Composer scripts stay on: filament:upgrade writes Filament's public assets.
MSYS_NO_PATHCONV=1 docker run --rm -v "$project_dir:/var/www/html" -w /var/www/html "$image" \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress \
    || fail "composer install başarısız."

# The throwaway install rewrote the package list without dev packages; the app container needs them back.
docker compose exec -T app php artisan package:discover > /dev/null

echo "== Tema"
docker compose exec -T app npm run build || fail "Tema derlenemedi."

printf '%s' "$source_commit" > RELEASE

# Forced: a vendor package's own .gitignore must not drop its files from the package.
git add --all --force -- "${package_paths[@]}"

unexpected="$(git status --porcelain | grep -Ev '^.. (vendor/|public/build/|public/(js|css|fonts)/filament/|RELEASE$)' || true)"
[ -z "$unexpected" ] || fail "Paket dışında değişen dosyalar var, commit yapılmadı:
$unexpected"

git commit -q -m "Yayın paketi: ${source_commit:0:8}"
git push origin main

echo
echo "Gönderildi. Şimdi: cPanel → Git → Update from Remote, ardından yayın adresini açın."
