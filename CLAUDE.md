# CLAUDE.md

Hosting, alan adı, bakım gibi yıllık hizmetleri müşteri bazında takip eden, borçları otomatik oluşturan ve ödemeleri eşleştiren bir iç yönetim uygulaması. Tek kullanıcı (işletme sahibi) kullanır. En önemli ilke: **sistem Excel'den basit olmalı.**

## Kaynaklar (önce bunları oku)

- `docs/PRD.md` — İş kurallarının tek doğru kaynağı.
- `docs/DATA_MODEL.md` — Tablolar, alanlar, kısıtlar.
- `docs/ROADMAP.md` — Görevler, bağımlılıklar ve kabul kriterleri.
- `docs/DECISIONS.md` — Verilmiş kararlar ve gerekçeleri.

Bu dosyalar arasında çelişki görürsen kendin çözme; dur ve kullanıcıya sor.

## Sürümler (sabit)

- Laravel 13, PHP 8.3 (`composer.json` platform 8.3; 8.4'e özgü özellik kullanma)
- Filament 5
- MySQL 8.0
- Pest, Pint, Larastan

Laravel ve Filament API'leri için hafızana güvenme: Laravel Boost'un dokümantasyon araçlarıyla kurulu sürümün dokümantasyonuna bak. Laravel 13 ve Filament 5 görece yeni; eski sürüm kalıplarını (ör. Filament 3/4 sözdizimi) kullanma.

## Ortam ve komutlar

Windows + Docker Desktop. Proje Windows klasöründe durur; WSL/Ubuntu ve Sail kullanılmaz. Ortam projedeki `compose.yaml` ile kurulur: `app` (PHP 8.3), `mysql` (MySQL 8.0, uygulama ve test veritabanı), `mailpit`.

**PHP, Composer, Artisan ve test komutlarını asla Windows'ta doğrudan çalıştırma; hepsi `app` konteynerinde çalışır.** Windows'ta kurulu PHP/Composer farklı sürümde olabilir.

- Başlat / durdur: `docker compose up -d` / `docker compose down`
- Artisan: `docker compose exec app php artisan ...`
- Composer: `docker compose exec app composer ...`
- Test: `docker compose exec app composer test` (tek dosya: `docker compose exec app php artisan test tests/Feature/...`)
- Kod stili: `docker compose exec app composer lint` (düzeltme: `docker compose exec app vendor/bin/pint`)
- Statik analiz: `docker compose exec app composer analyse`
- Örnek veri: `docker compose exec app php artisan migrate:fresh --seed` (sadece yerel)
- E-postalar: Mailpit arayüzü `http://localhost:8025`

Windows'a özgü kurallar:

- `vendor` ve `node_modules` Windows klasörüne değil, Docker birimlerine (named volume) bağlanır; bu yapıyı bozma.
- Satır sonları her dosyada LF; `.gitattributes` bunu sabitler. CRLF'li dosya oluşturma.
- Komutlar Git Bash'te çalışır; yol ayırıcı olarak `/` kullan.

## Çalışma kuralları

1. **Her oturumda tek görev.** Kullanıcının verdiği görev kodunu (örn. G-2.3) `docs/ROADMAP.md`'de bul; bağımlılıkları tamamlanmamışsa başlamadan söyle.
2. **Önce plan.** Değiştireceğin dosyaları ve yazacağın testleri listele, onay bekle.
3. **Kapsamda kal.** Görevde olmayan özellik, iyileştirme veya yeniden düzenleme ekleme; gerekli gördüğün şeyi sadece öner.
4. **Kararları değiştirme.** `docs/DECISIONS.md` ve `docs/PRD.md`'deki bir kararla çelişen bir yol gerekiyorsa dur ve sor.
5. **Kabul kriterleri test olur.** Her kriter için Pest testi yaz. Tarihe bağlı kurallarda zaman yolculuğu kullan (`$this->travelTo(...)`).
6. **Bitti = hepsi yeşil.** Test, lint ve analiz temiz olmadan görevi bitmiş sayma.
7. **Bitirince:** Görevin kutusunu `docs/ROADMAP.md`'de işaretle, kod içi bir karar verdiysen `docs/DECISIONS.md`'ye ekle, commit et (mesaj Türkçe ve görev koduyla başlar: `G-2.3: Müşteri ve ilgili kişiler`).
8. **K görevleri kullanıcınındır.** Hesap açma, şifre, secret girme gibi işleri yapma; gerektiğinde hatırlat.
9. **Karar yetkisi.** Klasör adı, sınıf adı gibi sadece kodun içini ilgilendiren kararları sormadan ver ve DECISIONS'a gerekçesiyle yaz. Görünüm, iş kuralı, paket ve altyapı kararlarını sor.

## Kod kuralları

- **İş mantığı `app/Actions` altında.** Her kural tek bir Action sınıfında; Filament ekranları, komutlar ve zamanlanmış görevler sadece Action çağırır. Arayüzde iş mantığı yok.
- **Para:** Tutarlar kuruş cinsinden `int`. Float, `round()` ile para hesabı, `number_format` ile ayrıştırma yasak; para yardımcısını kullan. Farklı para birimlerini asla toplama veya karşılaştırma.
- **Türetilen değerler saklanmaz:** Kalan tutar, kısmi ödenmiş, gecikmiş, alacak bakiyesi, teklifin süresinin dolması hesaplanır.
- **Geçmiş korunur:** Dağıtımı olan borcun tutarı/para birimi değişmez; geçmiş dönem borcu otomatik iptal edilmez; borcu/ödemesi olan kayıt silinmez, arşivlenir.
- **Enum'lar** `app/Enums` altında, PHP backed enum; modelde enum cast.
- **Modeller** PHP Attribute stiliyle; tüm projede tek stil.
- **Adlandırma:** Kod, değişken ve yorumlar İngilizce; kullanıcıya görünen her metin Türkçe ve `lang/tr` üzerinden. Terimler PRD'deki sözlüğe uygun (örn. müşteri hizmeti = `CustomerService`, borç = `Charge`).
- **Tarihler** `date` alanları; saat dilimi Europe/Istanbul; görünüm gg.aa.yyyy.
- **Zamanlanmış işler idempotent:** "Tam şu dakikada çalış" değil, "henüz yapılmadıysa yap". Aynı işin iki kez çalışması hiçbir zaman ikinci kayıt üretmemeli; atlanan çalışmalar sonraki çalışmada tamamlanmalı.

## Hosting kısıtları (reseller cPanel)

- SSH yok, sunucuda Composer yok: bağımlılıklar GitHub Actions'ta paketlenir.
- Cron en sık `*/15`. Kuyruk işçisi yok: kuyruk kullanma, e-postalar senkron gönderilir.
- Redis yok: önbellek ve oturum database/dosya.
- Sunucuda tarayıcı yok: PDF için dompdf (Türkçe için DejaVu font).
- Symlink ve dosya izinleri kısıtlı olabilir; yayın adresi bunları kontrol eder.

## Yasaklar

- Kullanıcıya sormadan yeni Composer/npm paketi ekleme.
- `.env`, anahtar, şifre veya secret commit etme; bunları koda yazma.
- Canlı veritabanında `migrate:fresh`, `db:wipe` veya seeder çalıştıracak kod yazma; örnek veri canlıda çalışmamalı.
- Testleri geçirmek için testi zayıflatma veya atlama; test yanlışsa bunu söyle.
- Gerçek e-posta adreslerine e-posta gönderen yerel ayar kullanma (yerelde Mailpit).
- Windows'taki PHP veya Composer'ı kullanma.

## Laravel Boost

- Boost MCP sunucusu `app` konteynerinde çalışır (`.mcp.json`: `docker compose exec -T app php artisan boost:mcp`); Claude Code açılmadan önce `docker compose up -d` çalışmış olmalı.
- AGENTS.md Laravel Boost tarafından yönetilir; elle düzenleme veya silme, `boost:update` ile güncellenir.
- Boost yönergeleriyle (AGENTS.md, `.claude/skills`) bu dosya çelişirse bu dosya geçerlidir. Özellikle: yönergelerdeki `php artisan`, `vendor/bin/pint`, `vendor/bin/pest` gibi komutlar her zaman `docker compose exec app` ile çalışır; görev bitince tam test paketini, lint'i ve analizi ben çalıştırırım; ROADMAP ve DECISIONS güncellemeleri istenmiş doküman sayılır.

@AGENTS.md
