# Hosting Kurulum Rehberi

Bu rehber uygulamayı cPanel'li (reseller) hostinge kurar. SSH ve sunucuda Composer gerekmez: her şey cPanel ekranlarından, GitHub'dan ve kendi bilgisayarınızdan yapılır. Yayın sonrası güncellemeler kendiliğinden gelir ([README "Yayına alma"](../README.md#yayına-alma)).

Adımlar uygulama sırasıyla yazılmıştır; sırayı bozmayın. Menü adları Türkçe cPanel'e göredir, parantez içinde İngilizcesi verilir.

Rehberde şu yer tutucular geçer; kendi değerlerinizle değiştirin:

| Yer tutucu | Anlamı |
| --- | --- |
| `alan.adi` | Sahip olduğunuz alan adı |
| `panel.alan.adi` | Uygulamanın yayınlanacağı mevcut alt alan adı |
| `KULLANICI` | cPanel kullanıcı adınız (cPanel'in sağ panelinde "Geçerli kullanıcı" olarak görünür) |

## 0. Gerekenler

- cPanel girişi ve bir alan adı
- GitHub deposunda yönetici yetkisi (secret girmek için)
- Kendi bilgisayarınızda çalışan Docker ortamı ([README "Yerel kurulum"](../README.md#yerel-kurulum)); iki anahtar üretmek için

## 1. PHP 8.3 ve eklentiler

1. cPanel → **MultiPHP Yöneticisi** (MultiPHP Manager) → `panel.alan.adi` alan adını seçin → PHP sürümü **8.3** → **Uygula**. 2. cPanel → **MultiPHP INI Düzenleyicisi** ya da **PHP Eklentileri** (Select PHP Version / PHP Extensions) → şunların işaretli olduğundan emin olun: `intl`, `pdo_mysql`, `gd`, `zip` (yerel ortam ve CI ile aynı), ayrıca Laravel'in gerektirdiği `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`.

Sürüm 8.3 değilse uygulama açılmaz: paket PHP 8.3'e göre kurulur ve `vendor/composer/platform_check.php` farklı sürümde çalışmayı reddeder.

## 2. Proje klasörü

Uygulama doğrudan `public_html` klasörüne kurulur; ayrı klasör ya da ek alt alan adı gerekmez. cPanel → **Dosya Yöneticisi** (File Manager) → `public_html` klasörünü açın ve **boş olduğundan emin olun**: hosting'in koyduğu `index.html`, `cgi-bin` gibi varsayılan dosyaları silin. `.htaccess` ve `error_log` varsa dokunmayın (gizli dosyaları görmek için 10. adımdaki ayarı açın).

Yayın bu klasörün içine `app`, `config`, `vendor`, `public` gibi klasörleri ve `.env` dosyasını koyar. Bu yüzden belge kökü `public_html` olduğu sürece `.env` ve kod web'den indirilebilir; bunu bir sonraki adımdaki `.htaccess` kuralı engeller. **Kuralı ilk yayından önce ekleyin.**

## 3. Web erişimini `public` klasörüne kapatma (`.htaccess`)

Alt alan adı cPanel'de ana alan adı sayıldığı için belge kökü değiştirilemez; bunun yerine `public_html/.htaccess` dosyasındaki bir kural tüm istekleri `public/` klasörüne yönlendirir. Böylece `/.env` ya da `/vendor/...` istekleri `public/` altında aranır, bulunmaz ve 404 döner.

1. Dosya Yöneticisi'nde (gizli dosyalar açık, bkz. 10. adım) `public_html/.htaccess` dosyasına sağ tık → **Düzenle** (Edit). Dosya yoksa **+ Dosya** ile oluşturun.
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

Kural bilerek `<IfModule>` içinde değildir: Apache bu kuralları anlayamazsa site 500 verir, `.env` açıkta kalmaz. Yayın bu dosyaya dokunmaz.

Kontrol (ilk yayından sonra, 13. adım): `https://panel.alan.adi/.env` ve `https://panel.alan.adi/vendor/autoload.php` adresleri **404 ya da 403** vermeli. Biri dosya içeriği gösterirse bu kural çalışmıyordur; hemen `.env`'i silip bu adımı gözden geçirin.

`DEPLOY_URL` değeriniz (12. adım) bu alt alan adından gelir: `https://panel.alan.adi/deploy`.

## 4. SSL (HTTPS)

1. cPanel → **SSL/TLS Durumu** (SSL/TLS Status) → `panel.alan.adi` satırını seçin → **AutoSSL'yi Çalıştır** (Run AutoSSL). Sertifika birkaç dakikada gelir.
2. cPanel → **Alan Adları** → `panel.alan.adi` satırında **HTTPS Yönlendirmesini Zorla** (Force HTTPS Redirect) anahtarını açın.

## 5. Veritabanı

1. cPanel → **MySQL Veritabanı Sihirbazı** (MySQL Database Wizard).
2. Veritabanı adı: `takip` → cPanel tam adı `KULLANICI_takip` yapar; not edin.
3. Kullanıcı adı ve güçlü bir şifre belirleyin (cPanel adın başına yine `KULLANICI_` ekler); ikisini de not edin.
4. Yetkiler ekranında **TÜM YETKİLER** (All Privileges) → **İleri**.

`.env`'de `DB_HOST=localhost` yazılır (10. adım).

## 6. E-posta hesabı

1. cPanel → **E-posta Hesapları** (Email Accounts) → **Oluştur**: örn. `bildirim@alan.adi`, güçlü şifre.
2. Hesabın satırında **Cihazı Bağla** (Connect Devices) → "Giden sunucu" (Outgoing Server) adını ve **SSL/TLS** portunu (**465**) not edin.

Uygulama bu hesabla e-posta gönderir (10. adımdaki `MAIL_*` değerleri).

## 7. SPF ve DKIM

1. cPanel → **E-posta Teslim Edilebilirliği** (Email Deliverability) → `alan.adi` satırı.
2. Durum "Geçerli" (Valid) değilse **Onar** (Repair) deyin. Alan adının DNS'i hostingte değilse ekranın gösterdiği SPF ve DKIM kayıtlarını DNS'in tutulduğu yerde elle ekleyin.
3. Doğrulama (SSH gerekmez): cPanel → **Webmail** → `bildirim@alan.adi` ile kendi Gmail adresinize bir e-posta gönderin. Gmail'de iletiyi açın → ⋮ → **Orijinali göster** → "SPF" ve "DKIM" satırlarında `PASS` yazmalı. `FAIL` ya da `NONE` ise 2. adıma dönün; gelen kutusu yerine spam'e düşüyorsa neden büyük olasılıkla budur.

## 8. FTP hesabı

1. cPanel → **FTP Hesapları** (FTP Accounts) → yeni hesap: kullanıcı adı `deploy` (tam hali `deploy@alan.adi`), güçlü şifre.
2. **Dizin** (Directory) alanına `public_html` yazın; hesap yalnızca proje klasörünü görür. Bu yüzden yükleme klasörü `FTP_SERVER_DIR` için `/` olur.
3. Aynı sayfada hesabın satırında **FTP İstemcisini Yapılandır** (Configure FTP Client) → **FTP sunucusu** adını not edin (örn. `sunucu123.hosting.com`).

Yayın FTPS ile (açık, port 21) ve sertifika doğrulamasıyla yapılır. `FTP_SERVER` sertifikadaki adla aynı olmalıdır: çoğu reseller'da bu `ftp.alan.adi` değil, sunucunun kendi adıdır. Eşleşmezse yayın sertifika hatasıyla düşer.

## 9. İki anahtarı üretin (kendi bilgisayarınızda)

Git Bash'te, proje klasöründe:

```bash
docker compose exec app php artisan key:generate --show   # APP_KEY (base64:... ile başlar)
openssl rand -hex 32                                      # DEPLOY_KEY (64 karakter)
```

İkisini de güvenli bir yere (parola yöneticisi) kaydedin. **`APP_KEY` kaybolursa ya da değişirse şifreli lisans anahtarları okunamaz.** `--show` yalnız yazdırır; yerel `.env`'nizi değiştirmez.

## 10. Hostingte `.env`

1. Dosya Yöneticisi → sağ üstte **Ayarlar** (Settings) → **Gizli dosyaları göster** (Show Hidden Files) → Kaydet.
2. `public_html` klasörüne girin → **+ Dosya** → adı `.env` → oluşturun → sağ tık → **Düzenle** (Edit).
3. Aşağıdakini yapıştırıp `<...>` yerlerini doldurun:

```dotenv
APP_NAME="Müşteri Takip"
APP_ENV=production
APP_KEY=<9. adımdaki APP_KEY>
APP_DEBUG=false
APP_URL=https://panel.alan.adi
APP_LOCALE=tr

ADMIN_NAME="<adınız>"
ADMIN_EMAIL=<giriş e-postanız>
ADMIN_PASSWORD="<güçlü şifre>"

DEPLOY_KEY=<9. adımdaki DEPLOY_KEY>

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
- Yayın `.env`'e hiç dokunmaz; dosya yalnız burada, elle yönetilir.
- `ADMIN_*` değerleri ilk yayında yöneticiyi oluşturur (13. adım). İlk girişten sonra şifrenin panelden değiştirilmesi ve `ADMIN_PASSWORD` satırının silinmesi canlıya geçiş kontrol listesindedir (G-5.8).

## 11. Cron (zamanlayıcı)

Uygulama her işini tek bir cron çağrısından yürütür; `app:deploy` komutu da her çalışmada yayın adresinin yedeği olarak çalışır (`routes/console.php`). Bu yüzden cron'u ilk yayından **önce** kurun.

1. cPanel → **Cron İşleri** (Cron Jobs) → "Ortak Ayarlar" (Common Settings) listesinden **Her 15 dakikada bir** (`*/15 * * * *`).
2. Komut:

```text
/opt/cpanel/ea-php83/root/usr/bin/php /home/KULLANICI/public_html/artisan schedule:run >> /dev/null 2>&1
```

PHP yolunda **8.3 yazmalıdır**: `/usr/local/bin/php` gibi kısa yollar başka bir sürümü çalıştırabilir. Hostingde EasyApache varsa yol yukarıdaki gibidir; CloudLinux ise `/opt/alt/php83/usr/bin/php` olur. Hangisinin var olduğunu Dosya Yöneticisi'nden o klasöre bakarak görebilirsiniz. Ana dizin yolu (`/home/KULLANICI`) cPanel'in sol/sağ panelinde yazar.

Çalıştığını SSH'siz doğrulamak için: aynı sayfada "Cron E-postası"na kendi adresinizi yazın, geçici bir cron ekleyin (komut: `/opt/cpanel/ea-php83/root/usr/bin/php -v`), çıktı e-postada `PHP 8.3.x` göstermeli; sonra geçici cron'u silin ve "Cron E-postası"nı boşaltın (aksi halde her çalışmada e-posta gelir).

## 12. GitHub secret'ları

GitHub deposu → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**. Altı secret'ı ad ve değer olarak girin; adlar `.github/workflows/ci.yml` ile birebir aynıdır:

| Secret | Örnek değer | Nereden |
| --- | --- | --- |
| `FTP_SERVER` | `sunucu123.hosting.com` | 8. adım, "FTP İstemcisini Yapılandır" |
| `FTP_USERNAME` | `deploy@alan.adi` | 8. adım |
| `FTP_PASSWORD` | 8. adımda belirlediğiniz şifre | 8. adım |
| `FTP_SERVER_DIR` | `/` | 8. adım (hesabın ana dizini proje klasörü; `/` ile biter) |
| `DEPLOY_URL` | `https://panel.alan.adi/deploy` | 3. adım |
| `DEPLOY_KEY` | 64 karakterlik anahtar | 9. adım; hostingteki `.env`'dekiyle **aynı** |

Hiçbiri girilmemişse yayın işi uyarıyla atlanır; bir kısmı eksikse eksikleri sayarak başarısız olur.

## 13. İlk yayın ve kontrol

1. GitHub → **Actions** → `main` dalındaki son **CI** çalışması → **Re-run all jobs** (ya da `main`'e yeni bir commit gönderin).
2. İlk yükleme yaklaşık 14,5 bin dosyadır ve hostingin hızına göre uzun sürebilir; sonraki yayınlar yalnız değişen dosyaları yükler. İş en çok 240 dakika çalışır.
3. Yükleme bitince yayın adresi çağrılır: tablolar oluşturulur (migration), önbellekler yenilenir ve `ADMIN_*` değerleriyle yönetici kullanıcı oluşturulur.
4. `https://panel.alan.adi/panel` adresini açın → `ADMIN_EMAIL` ve `ADMIN_PASSWORD` ile giriş yapın.
5. 3. adımdaki kontrolü yapın: `/.env` ve `/vendor/autoload.php` adresleri 404 ya da 403 vermeli.

## 14. Sorun giderme

Yayın adresi başarısız olsa bile yüklenen sürümü cron 15 dakika içinde kendiliğinden yayına alır; Actions kırmızı görünse de bir süre bekleyip panele bakın.

| Belirti | Neden ve çözüm |
| --- | --- |
| Actions'ta "Yayın adresi 403 döndü" | `DEPLOY_KEY` GitHub'da ve hostingteki `.env`'de farklı ya da 32 karakterden kısa. İkisini aynı yapın. |
| 422 | Sunucuda `RELEASE` dosyası yok: yükleme tamamlanmamış. İşi yeniden çalıştırın. |
| 409 | Yayın başka bir çağrıyla meşgul; Actions 30 sn arayla 10 kez kendisi dener. |
| 500 | Dosya Yöneticisi → `public_html/storage/logs/laravel.log` son satırlarına bakın. |
| FTP adımında sertifika hatası | `FTP_SERVER` sertifikadaki adla aynı değil (8. adım). |
| `/panel` 404 ya da dizin listesi | `public_html/.htaccess` kuralı eksik ya da yanlış (3. adım). Dizin listesi görünüyorsa `.env` de açıkta olabilir: hemen düzeltin. |
| Boş sayfa ya da "PHP sürümü" hatası | Alan adının PHP sürümü 8.3 değil ya da eklenti eksik (1. adım). |
| Giriş yapılamıyor | `.env`'de `ADMIN_*` üçü de dolu mu? İlk yayın başarıyla bittikten sonra kullanıcı oluşur; yayın bitmeden giriş olmaz. |
| E-posta gitmiyor ya da spam'e düşüyor | `MAIL_*` değerlerini ve 7. adımdaki SPF/DKIM sonucunu kontrol edin. |
