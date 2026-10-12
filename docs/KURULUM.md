# Hosting Kurulum Rehberi

Bu rehber uygulamayı cPanel'li (reseller) hostinge kurar. SSH ve sunucuda Composer gerekmez: her şey cPanel ekranlarından, GitHub'dan ve kendi bilgisayarınızdan yapılır. Yayın düzeni: siz `main`'e gönderirsiniz; GitHub Actions testleri çalıştırır ve canlı paket (`vendor` ve derlenmiş dosyalar) değiştiyse onu `main`'e "Yayın paketi: …" commit'i olarak ekler; hosting `main`'i cPanel'in Git ekranından çeker, ardından yayın adresi tarayıcıda bir kez açılır ([README "Yayına alma"](../README.md#yayına-alma)).

Adımlar uygulama sırasıyla yazılmıştır; sırayı bozmayın. Menü adları Türkçe cPanel'e göredir, parantez içinde İngilizcesi verilir.

Rehberde şu yer tutucular geçer; kendi değerlerinizle değiştirin:

| Yer tutucu | Anlamı |
| --- | --- |
| `alan.adi` | Sahip olduğunuz alan adı |
| `panel.alan.adi` | Uygulamanın yayınlanacağı mevcut alt alan adı |
| `KULLANICI` | cPanel kullanıcı adınız (cPanel'in sağ panelinde "Geçerli kullanıcı" olarak görünür) |

## 0. Gerekenler

- cPanel girişi (Git Sürüm Kontrolü ekranı açık) ve bir alan adı
- GitHub deposu **public** olmalı: hosting depoyu kimlik bilgisi olmadan çeker. Depo private yapılırsa çekme durur.
- Kendi bilgisayarınızda çalışan Docker ortamı ([README "Yerel kurulum"](../README.md#yerel-kurulum)); uygulama anahtarını üretmek için

## 1. PHP 8.3 ve eklentiler

1. cPanel → **MultiPHP Yöneticisi** (MultiPHP Manager) → `panel.alan.adi` alan adını seçin → PHP sürümü **8.3** → **Uygula**.
2. cPanel → **MultiPHP INI Düzenleyicisi** ya da **PHP Eklentileri** (Select PHP Version / PHP Extensions) → şunların işaretli olduğundan emin olun: `intl`, `pdo_mysql`, `gd`, `zip` (yerel ortam ve CI ile aynı), ayrıca Laravel'in gerektirdiği `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`.

Sürüm 8.3 değilse uygulama açılmaz: paket PHP 8.3'e göre kurulur ve `vendor/composer/platform_check.php` farklı sürümde çalışmayı reddeder.

## 2. Depoyu `public_html`'e klonlama

Uygulama doğrudan `public_html` klasörüne, cPanel'in Git ekranıyla kurulur; ayrı klasör ya da ek alt alan adı gerekmez.

1. cPanel → **Dosya Yöneticisi** (File Manager) → `public_html` klasöründeki hostingin koyduğu varsayılan dosyaları (`index.html`, `cgi-bin` gibi) silin. Gizli dosyaları görmek için 9. adımdaki ayarı açın.
2. cPanel → **Git™ Sürüm Kontrolü** (Git™ Version Control) → **Oluştur** (Create):
   - **Bir Depo Klonla** (Clone a Repository) açık,
   - **Klon URL'si** (Clone URL): GitHub deposunun adresi, örn. `https://github.com/KULLANICI_ADI/musteri-takip.git`,
   - **Depo Yolu** (Repository Path): `public_html`,
   - **Depo Adı** (Repository Name): `musteri-takip` → **Oluştur**.
3. cPanel klasör boş değil diye klonlamayı reddederse `public_html`'deki `.htaccess` ve `error_log` dosyalarını geçici olarak ana dizine taşıyın, klonladıktan sonra geri taşıyın.

Yayın bu klasöre `app`, `config`, `vendor`, `public` gibi klasörleri koyar ve `.env` de burada durur. Belge kökü `public_html` olduğu sürece bunlar web'den indirilebilir; bunu bir sonraki adımdaki `.htaccess` kuralı engeller. **Kuralı ilk yayından önce ekleyin.**

## 3. Web erişimini `public` klasörüne kapatma (`.htaccess`)

Alt alan adı cPanel'de ana alan adı sayıldığı için belge kökü değiştirilemez; bunun yerine `public_html/.htaccess` dosyasındaki bir kural tüm istekleri `public/` klasörüne yönlendirir. Böylece `/.env` ya da `/vendor/...` istekleri `public/` altında aranır, bulunmaz ve 404 döner.

1. Dosya Yöneticisi'nde (gizli dosyalar açık, bkz. 9. adım) `public_html/.htaccess` dosyasına sağ tık → **Düzenle** (Edit). Dosya yoksa **+ Dosya** ile oluşturun.
2. Aşağıdaki satırları dosyanın **en üstüne** yapıştırın; cPanel'in yazdığı mevcut satırlara (`# php -- BEGIN cPanel-generated handler` bloğu: PHP sürümünü belirler) dokunmayın, **silmeyin**. → **Değişiklikleri Kaydet** (Save Changes).

```apache
# Müşteri Takip: kod ve .env web'den erişilemez olsun
<FilesMatch "^\.(?!well-known)">
    Require all denied
</FilesMatch>

RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
```

Kural bilerek `<IfModule>` içinde değildir: Apache bu kuralları anlayamazsa site 500 verir, `.env` açıkta kalmaz. Bu dosya depoda yoktur ve yayınlar ona dokunmaz.

Kontrol (ilk yayından sonra, 11. adım): `https://panel.alan.adi/.env` ve `https://panel.alan.adi/vendor/autoload.php` adresleri **404 ya da 403** vermeli. Biri dosya içeriği gösterirse bu kural çalışmıyordur; hemen `.env`'i silip bu adımı gözden geçirin.

## 4. SSL (HTTPS)

1. cPanel → **SSL/TLS Durumu** (SSL/TLS Status) → `panel.alan.adi` satırını seçin → **AutoSSL'yi Çalıştır** (Run AutoSSL). Sertifika birkaç dakikada gelir.
2. cPanel → **Alan Adları** → `panel.alan.adi` satırında **HTTPS Yönlendirmesini Zorla** (Force HTTPS Redirect) anahtarını açın.

## 5. Veritabanı

1. cPanel → **MySQL Veritabanı Sihirbazı** (MySQL Database Wizard).
2. Veritabanı adı: `takip` → cPanel tam adı `KULLANICI_takip` yapar; not edin.
3. Kullanıcı adı ve güçlü bir şifre belirleyin (cPanel adın başına yine `KULLANICI_` ekler); ikisini de not edin.
4. Yetkiler ekranında **TÜM YETKİLER** (All Privileges) → **İleri**.

`.env`'de `DB_HOST=localhost` yazılır (9. adım).

## 6. E-posta hesabı

1. cPanel → **E-posta Hesapları** (Email Accounts) → **Oluştur**: örn. `bildirim@alan.adi`, güçlü şifre.
2. Hesabın satırında **Cihazı Bağla** (Connect Devices) → "Giden sunucu" (Outgoing Server) adını ve **SSL/TLS** portunu (**465**) not edin.

Uygulama bu hesabla e-posta gönderir (9. adımdaki `MAIL_*` değerleri).

## 7. SPF ve DKIM

1. cPanel → **E-posta Teslim Edilebilirliği** (Email Deliverability) → `alan.adi` satırı.
2. Durum "Geçerli" (Valid) değilse **Onar** (Repair) deyin. Alan adının DNS'i hostingte değilse ekranın gösterdiği SPF ve DKIM kayıtlarını DNS'in tutulduğu yerde elle ekleyin.
3. Doğrulama (SSH gerekmez): cPanel → **Webmail** → `bildirim@alan.adi` ile kendi Gmail adresinize bir e-posta gönderin. Gmail'de iletiyi açın → ⋮ → **Orijinali göster** → "SPF" ve "DKIM" satırlarında `PASS` yazmalı. `FAIL` ya da `NONE` ise 2. adıma dönün; gelen kutusu yerine spam'e düşüyorsa neden büyük olasılıkla budur.

## 8. Uygulama anahtarını üretin (kendi bilgisayarınızda)

Git Bash'te, proje klasöründe:

```bash
docker compose exec app php artisan key:generate --show   # APP_KEY (base64:... ile başlar)
openssl rand -hex 32                                      # DEPLOY_KEY (64 karakter)
```

İkisini de güvenli bir yere (parola yöneticisi) kaydedin. **`APP_KEY` kaybolursa ya da değişirse şifreli lisans anahtarları okunamaz.** `--show` yalnız yazdırır; yerel `.env`'nizi değiştirmez. `DEPLOY_KEY` yayın adresini korur (11. adım); en az 32 karakter olmalı, sızarsa yenisini üretip `.env`'de değiştirin.

## 9. Hostingte `.env`

1. Dosya Yöneticisi → sağ üstte **Ayarlar** (Settings) → **Gizli dosyaları göster** (Show Hidden Files) → Kaydet.
2. `public_html` klasörüne girin → **+ Dosya** → adı `.env` → oluşturun → sağ tık → **Düzenle** (Edit).
3. Aşağıdakini yapıştırıp `<...>` yerlerini doldurun:

```dotenv
APP_NAME="Müşteri Takip"
APP_ENV=production
APP_KEY=<8. adımdaki APP_KEY>
APP_DEBUG=false
APP_URL=https://panel.alan.adi
APP_LOCALE=tr

DEPLOY_KEY=<8. adımdaki DEPLOY_KEY>

ADMIN_NAME="<adınız>"
ADMIN_EMAIL=<giriş e-postanız>
ADMIN_PASSWORD="<güçlü şifre>"

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=KULLANICI_takip
DB_USERNAME=<5. adımdaki kullanıcı>
DB_PASSWORD="<5. adımdaki şifre>"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=<6. adımdaki giden sunucu>
MAIL_PORT=465
MAIL_USERNAME=bildirim@alan.adi
MAIL_PASSWORD="<6. adımdaki şifre>"
MAIL_FROM_ADDRESS=bildirim@alan.adi
MAIL_FROM_NAME="${APP_NAME}"
```

Notlar:

- `#`, `$`, boşluk ya da tırnak içeren şifreleri çift tırnak içine alın.
- Port 465 doğrudan TLS demektir ve `MAIL_SCHEME=smtps` ile eşleşir. Hostingin yalnız 587 veriyorsa `MAIL_PORT=587` ve `MAIL_SCHEME=smtp` kullanın (bağlantı otomatik TLS'e yükselir).
- `.env` depoda yoktur ve yayınlar ona dokunmaz; dosya yalnız burada, elle yönetilir.
- `ADMIN_*` değerleri ilk yayında yöneticiyi oluşturur (11. adım). İlk girişten sonra şifrenin panelden değiştirilmesi ve `ADMIN_PASSWORD` satırının silinmesi canlıya geçiş kontrol listesindedir (G-5.8).
- `DEPLOY_KEY` boş ya da 32 karakterden kısaysa yayın adresi her isteği reddeder.

## 10. Cron (zamanlayıcı)

Uygulamanın kendi işleri (yenileme borçlarını oluşturma, dönem ilerletme, günlük özet) tek bir cron çağrısıyla çalışır. Bu cron yayınla ilgili değildir; bir kez kurulur.

1. cPanel → **Cron İşleri** (Cron Jobs) → "Ortak Ayarlar" (Common Settings) listesinden **Her 15 dakikada bir** (`*/15 * * * *`).
2. Komut (tek satır):

```text
cd /home/KULLANICI/public_html && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

PHP yolunda **8.3 yazmalıdır**: `/usr/local/bin/php` gibi kısa yollar başka bir sürümü çalıştırabilir. Hostingde EasyApache varsa yol yukarıdaki gibidir; CloudLinux ise `/opt/alt/php83/usr/bin/php` olur. Hangisinin var olduğunu Dosya Yöneticisi'nden o klasöre bakarak görebilirsiniz. Ana dizin yolu (`/home/KULLANICI`) cPanel'in sol/sağ panelinde yazar.

Çalıştığını SSH'siz doğrulamak için: aynı sayfada "Cron E-postası"na kendi adresinizi yazın, geçici bir cron ekleyin (komut: `/opt/cpanel/ea-php83/root/usr/bin/php -v`), çıktı e-postada `PHP 8.3.x` göstermeli; sonra geçici cron'u silin ve "Cron E-postası"nı boşaltın (aksi halde her çalışmada e-posta gelir).

Yeni bir sürüm çekilip yayın adresi henüz açılmadıysa cron, veritabanı eski yapıdayken çalışmasın diye işleri atlar; adres açılınca sonraki çalışmada kaldığı yerden devam eder.

## 11. İlk yayın

1. `main` dalına gönderin (ya da GitHub → **Actions** → `main`'deki son **CI** çalışması → **Re-run all jobs**). Testler geçince yayın işi canlı paketi hazırlar; paket değiştiyse `main`'e "Yayın paketi: …" commit'i ekler.
2. cPanel → **Git™ Sürüm Kontrolü** → depo satırında **Yönet** (Manage) → **Çek veya Dağıt** (Pull or Deploy) → **Uzaktan Güncelle** (Update from Remote).
   - Daha önce `production` dalını kullandıysanız önce **Temel Bilgiler** (Basic Information) → **Kullanıma Alınan Dal** (Checked-Out Branch): `main` → **Güncelle** (Update).
3. Tarayıcıda `https://panel.alan.adi/deploy?key=DEPLOY_KEY` adresini açın (`DEPLOY_KEY` yerine 8. adımdaki anahtar). Tablolar oluşturulur (migration), önbellekler yenilenir ve `ADMIN_*` değerleriyle yönetici kullanıcı oluşturulur. Sayfada "Yeni sürüm yayına alındı." yazmalı.
4. `https://panel.alan.adi/panel` adresini açın → `ADMIN_EMAIL` ve `ADMIN_PASSWORD` ile giriş yapın.
5. 3. adımdaki kontrolü yapın: `/.env` ve `/vendor/autoload.php` adresleri 404 ya da 403 vermeli.
6. Eski düzenlerden kalanlar artık gerekmez, silebilirsiniz: GitHub'daki secret'lar (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_SERVER_DIR`, `DEPLOY_URL`, `DEPLOY_KEY`), GitHub'daki `production` dalı ve cPanel'deki yayın FTP hesabı.

## 12. Sonraki yayınlar

1. `main`'e gönderin ve GitHub → **Actions**'ta CI'ın yeşil olmasını bekleyin. Testler, Pint ya da Larastan düşerse paket hazırlanmaz; o sürümü çekmeyin.
2. cPanel → **Git™ Sürüm Kontrolü** → **Yönet** → **Çek veya Dağıt** → **Uzaktan Güncelle**.
3. `https://panel.alan.adi/deploy?key=DEPLOY_KEY` adresini açın. Adresi tarayıcıda yer imi olarak saklayabilirsiniz.

Yayın işi yalnızca canlı paket değiştiğinde commit ekler: `composer.lock` değiştiğinde ("Composer bağımlılıkları (vendor) güncellendi"), tasarım değiştiğinde ("tema yeniden derlendi") ya da Filament sürümü değiştiğinde ("Filament dosyaları güncellendi"). Mesajın altında hangi commit için hazırlandığı yazar. Böyle bir commit eklendiyse kendi bilgisayarınızda bir sonraki gönderimden önce `git pull` yapın (VS Code'daki "Sync" bunu kendisi yapar).

## 13. Sorun giderme

| Belirti | Neden ve çözüm |
| --- | --- |
| "Uzaktan Güncelle" ya da dal değiştirme yerel değişiklik hatası veriyor | Klasörde depodaki dosyalardan farklılaşmış dosyalar var (örn. eski FTP yüklemesinden kalanlar). Cron İşleri'ne **Her dakika** çalışan geçici bir iş ekleyin: `cd /home/KULLANICI/public_html && /usr/local/cpanel/3rdparty/bin/git fetch origin main && /usr/local/cpanel/3rdparty/bin/git checkout -f -B main origin/main`. İki dakika bekleyip geçici işi silin. Bu komut yalnız depodaki dosyaları `main`'deki haline getirir; `.env`, `.htaccess` ve `storage` içeriğine dokunmaz. |
| Gönderim reddedildi ("fetch first" / "non-fast-forward") | Yayın işi `main`'e paket commit'i eklemiş; `git pull` yapıp yeniden gönderin. |
| Actions'ta "Commit package if it changed" adımı kırmızı | Adımın çıktısına bakın. "main bu çalışmadan sonra ilerledi" uyarısı hata değildir: paketi yeni çalışma değerlendirir. Yazma izni hatasında depo ayarlarında **Settings → Actions → General → Workflow permissions** "Read and write permissions" yapın. |
| Yayın adresi "Yetkisiz istek." diyor | Adresteki anahtar `.env`'deki `DEPLOY_KEY` ile aynı değil ya da `DEPLOY_KEY` 32 karakterden kısa. |
| Yayın adresi "Yayın sırasında hata oluştu" diyor | `public_html/storage/logs/laravel.log` son satırlarına bakın; sorunu giderip adresi yeniden açın. Başarısız yayın tekrar denenebilir. |
| İlk yayın migration'da yarıda kaldı, yeniden açınca günlükte "already exists" yazıyor | İlk kurulumda veritabanında henüz veri yoktur: cPanel → **phpMyAdmin** → soldan veritabanını seçin → **Tümünü işaretle** (Check all) → **Seçilenlerle: Kaldır** (Drop) → onaylayın. Sonra yayın adresini yeniden açın. **Uygulama kullanılmaya başladıktan sonra bunu yapmayın**; verilerin hepsi silinir. |
| 500 | Dosya Yöneticisi → `public_html/storage/logs/laravel.log` son satırlarına bakın. |
| `/panel` 404 ya da dizin listesi | `public_html/.htaccess` kuralı eksik ya da yanlış (3. adım). Dizin listesi görünüyorsa `.env` de açıkta olabilir: hemen düzeltin. |
| Boş sayfa ya da "PHP sürümü" hatası | Alan adının PHP sürümü 8.3 değil ya da eklenti eksik (1. adım). |
| Giriş yapılamıyor | `.env`'de `ADMIN_*` üçü de dolu mu? Kullanıcı yayın adresi açılınca oluşur (11. adım). |
| Yeni sürüm çekildi ama değişiklik görünmüyor | Yayın adresini açın (12. adım). |
| E-posta gitmiyor ya da spam'e düşüyor | `MAIL_*` değerlerini ve 7. adımdaki SPF/DKIM sonucunu kontrol edin. |
