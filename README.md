# Müşteri Takip

Hosting, alan adı, bakım gibi yıllık hizmetleri müşteri bazında takip eden, borçları otomatik oluşturan ve ödemeleri eşleştiren iç yönetim uygulaması.

Ayrıntılar: [`docs/PRD.md`](docs/PRD.md), [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md), [`docs/ROADMAP.md`](docs/ROADMAP.md), [`docs/DECISIONS.md`](docs/DECISIONS.md), [`docs/UI.md`](docs/UI.md).

## Yerel kurulum

Ortam: Windows + Docker Desktop. PHP, Composer ve Node Windows'a kurulmaz; tüm komutlar `app` konteynerinde çalışır.

### Ön koşullar

- Docker Desktop (çalışır durumda)
- Git for Windows; satır sonları için: `git config --global core.autocrlf false`

### İlk kurulum

Git Bash'te, proje klasöründe:

```bash
cp .env.example .env                           # ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD doldurun
docker compose build
docker compose run --rm app composer install   # vendor birimi boşken uygulama başlayamaz
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan app:create-admin   # .env'deki ADMIN_* bilgileriyle ilk kullanıcı
```

### Adresler

| Servis | Adres |
| --- | --- |
| Uygulama | http://localhost:8000 |
| Yönetim paneli | http://localhost:8000/panel |
| Mailpit (yerel e-postalar) | http://localhost:8025 |
| MySQL (dışarıdan bağlanmak için) | `127.0.0.1:3307`, kullanıcı `musteri_takip`, şifre `secret` |

Uygulama veritabanı `musteri_takip`, test veritabanı `musteri_takip_testing`; ikisi de MySQL konteynerinin ilk açılışında oluşturulur.

### Günlük komutlar

```bash
docker compose up -d                          # başlat
docker compose down                           # durdur
docker compose exec app php artisan ...       # Artisan
docker compose exec app composer ...          # Composer
docker compose exec app php artisan test      # testler
```

### Notlar

- `vendor` ve `node_modules` Docker birimlerinde durur; Windows klasöründe boş görünmeleri normaldir.
- Satır sonları her dosyada LF'dir (`.gitattributes`).
- Birimleri sıfırlamak için: `docker compose down -v` (yerel veritabanı da silinir).

### Claude Code ve Laravel Boost

- Claude Code'u başlatmadan önce `docker compose up -d` çalıştırılmalı, yoksa Boost MCP bağlanamaz (`.mcp.json` sunucuyu `app` konteynerinde çalıştırır).
- Boost yönergelerini ve skill'lerini güncellemek için: `docker compose exec app php artisan boost:update`. `AGENTS.md` ve `.claude/skills` Boost tarafından yönetilir; elle düzenlenmez.
- `boost:install` yeniden çalıştırılırsa `.mcp.json` Windows PHP'sini çağıracak şekilde yeniden yazılabilir; komutun `docker compose exec -T app php artisan boost:mcp` kaldığını kontrol edin.

## Yayına alma

Hostingin ilk kurulumu adım adım: [`docs/KURULUM.md`](docs/KURULUM.md).

Hosting, deponun `public_html`'e klonlanmış bir kopyasını (cPanel Git Sürüm Kontrolü) `main` dalında tutar. Sunucuda Composer olmadığı için canlı paket (`vendor` dev paketleri olmadan, derlenmiş tema, Filament dosyaları ve `RELEASE`) depoda durur ve yerelde hazırlanır.

Yayın üç adımdır:

1. Git Bash'te, Docker çalışırken: `./yayin.sh`. Betik önce testleri, Pint'i ve Larastan'ı çalıştırır; biri düşerse durur. Sonra `vendor`'u `--no-dev` ile geçici bir konteynerde proje klasörüne kurar (`app` konteynerinin dev paketli `vendor` birimine dokunmaz), temayı derler, `RELEASE`'e kaynak commit kimliğini yazar, yalnızca paket dosyalarını "Yayın paketi: …" commit'i olarak ekler ve `main`'e gönderir.
2. cPanel → Git Sürüm Kontrolü → **Yönet** → **Çek veya Dağıt** → **Uzaktan Güncelle**.
3. Tarayıcıda `https://ALAN_ADI/deploy?key=DEPLOY_KEY`: migration'lar çalışır, önbellekler yenilenir (`DeployRelease`). Aynı sürüm için yeniden açmak bir şey yapmaz.

Notlar:

- GitHub Actions (`.github/workflows/ci.yml`) her gönderimde yalnızca testleri çalıştırır; depoya yazmaz, secret gerekmez.
- Çekmeden sonraki ilk istek, eski sürümün `bootstrap/cache` dosyalarını Laravel açılmadan önce siler (`ClearStaleBootstrapCache`); yeni kod eski önbellekle açılmaz.
- Depo public olmalı: hosting kimlik bilgisi olmadan çeker.
- Hostingte doğan dosyalar (`.env`, `storage` içeriği, önbellekler, `public_html/.htaccess`) depoya girmez ve çekme onlara dokunmaz.
- Hostingteki PHP sürümü 8.3 olmalı: paket `composer.lock` ve `composer.json`'daki platform ayarına (8.3) göre kurulur, `vendor/composer/platform_check.php` farklı sürümde uygulamayı açmaz.
- Cron yalnızca uygulamanın zamanlanmış işleri içindir (`schedule:run`); yayınla ilgisi yoktur.
