# Müşteri Takip

Hosting, alan adı, bakım gibi yıllık hizmetleri müşteri bazında takip eden, borçları otomatik oluşturan ve ödemeleri eşleştiren iç yönetim uygulaması.

Ayrıntılar: [`docs/PRD.md`](docs/PRD.md), [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md), [`docs/ROADMAP.md`](docs/ROADMAP.md), [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Yerel kurulum

Ortam: Windows + Docker Desktop. PHP, Composer ve Node Windows'a kurulmaz; tüm komutlar `app` konteynerinde çalışır.

### Ön koşullar

- Docker Desktop (çalışır durumda)
- Git for Windows; satır sonları için: `git config --global core.autocrlf false`

### İlk kurulum

Git Bash'te, proje klasöründe:

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install   # vendor birimi boşken uygulama başlayamaz
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### Adresler

| Servis | Adres |
| --- | --- |
| Uygulama | http://localhost:8000 |
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
