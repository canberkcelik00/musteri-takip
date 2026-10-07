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

`main` dalına her push'ta GitHub Actions (`.github/workflows/ci.yml`) şunları yapar; testler, Pint veya Larastan başarısızsa yükleme yapılmaz:

1. `composer install --no-dev` (Filament'in `public/` dosyaları bu sırada üretilir) ve `npm run build` ile paket hazırlanır. Pakette `.env`, `storage` içeriği ve `bootstrap/cache` önbellekleri yoktur.
2. Dosyalar FTPS ile yüklenir; yalnız değişen dosyalar gider. Sunucuda doğan dosyalara (`.env`, `storage` içeriği) dokunulmaz.
3. Hostingteki eski `bootstrap/cache` önbellekleri silinir, en son `RELEASE` dosyası yüklenir.
4. Yayın adresi (`POST /deploy`) çağrılır: migration ve önbellek yenileme. Adres meşgulse (409) birkaç kez yeniden denenir; çağrı başarısız olsa bile cron yayını sonraki çalışmada tamamlar.

GitHub'da **Settings → Secrets and variables → Actions** altına girilecek secret'lar:

| Secret | Ne | Örnek |
| --- | --- | --- |
| `FTP_SERVER` | FTP sunucusunun adı; FTPS sertifikasındaki adla aynı olmalı | `ftp.alan.adi` |
| `FTP_USERNAME` | cPanel FTP kullanıcısı | `deploy@alan.adi` |
| `FTP_PASSWORD` | O FTP kullanıcısının şifresi | |
| `FTP_SERVER_DIR` | Projenin hostingteki klasörü (kök dizinin `public`'i gösterdiği klasör); `/` ile biter | `/musteri-takip/` |
| `DEPLOY_URL` | Yayın adresi | `https://alan.adi/deploy` |
| `DEPLOY_KEY` | Yayın anahtarı; `openssl rand -hex 32` ile üretilir, aynı değer hostingteki `.env`'de `DEPLOY_KEY` olarak durur | |

Notlar:

- Bağlantı açık FTPS'tir (explicit, port 21) ve sertifika doğrulanır.
- Secret'ların hiçbiri girilmemişse yayın işi uyarıyla atlanır; bir kısmı eksikse eksikleri sayarak başarısız olur.
- Hostingteki PHP sürümü 8.3 olmalı: paket `composer.lock` ve `composer.json`'daki platform ayarına (8.3) göre kurulur, `vendor/composer/platform_check.php` farklı sürümde uygulamayı açmaz.
- İlk yükleme (~14,5 bin dosya) hostingin hızına göre uzun sürebilir; sonraki yayınlar yalnız değişen dosyaları yükler. Yükleme aracı hangi dosyaların yüklendiğini en sonda kaydettiği için kesilen bir yükleme yeniden çalıştırıldığında her şey baştan yüklenir; bu yüzden yayın işinin sınırı geniş tutuldu (en çok 240 dk).
- Yükleme aracı sunucuda proje klasörüne `.ftp-deploy-sync-state.json` dosyasını yazar; silinirse sonraki yayında tüm dosyalar yeniden yüklenir.
