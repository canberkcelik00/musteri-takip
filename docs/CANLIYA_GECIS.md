# Canlıya Geçiş Kontrol Listesi

Bu liste, [KURULUM.md](KURULUM.md) ile hosting kurulup ilk yayın yapıldıktan sonra, müşteri ve hizmet verisini girmeden önce uygulanır. SSH gerekmez: her şey cPanel ve panel ekranlarından yapılır. Yer tutucular KURULUM.md ile aynıdır (`panel.alan.adi`, `KULLANICI`). Her madde için "Kontrol" satırındaki sonucu görmeden bir sonrakine geçmeyin; sonucu görünce kutuyu işaretleyin.

## Ortak kural: `.env` düzenlemesinden sonra

Hosting, ayarları hızlı okumak için önbelleğe alır (`bootstrap/cache/config.php`). Yayın adresi bunu yalnız **yeni bir sürüm** çekildiğinde yeniler; aynı sürümde adresi tekrar açmak yenilemez. Bu yüzden `.env`'de yaptığınız her değişiklik, önbellek silinene kadar etkisiz kalır (ve silinen `ADMIN_PASSWORD` satırı önbellekte kalmaya devam eder). Her `.env` düzenlemesinde:

1. cPanel → **Dosya Yöneticisi** (File Manager) → `public_html/.env` → sağ tık → **Düzenle** (Edit) → değişikliği yapın → **Değişiklikleri Kaydet** (Save Changes).
2. `public_html/bootstrap/cache` klasörüne girin → `config.php` dosyasına sağ tık → **Sil** (Delete). Dosya yoksa sorun değildir. Aynı klasördeki diğer dosyalara dokunmayın. Laravel o andan itibaren `.env`'i doğrudan okur; bir sonraki yayın önbelleği yeniden oluşturur.

Aşağıdaki kontrolleri bu iki adımdan **sonra** yapın.

## 1. Canlı ortam ayarları

- [ ] `.env`'de şu satırlar böyle olmalı (yoksa ekleyin): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://panel.alan.adi`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`. Ortak kuralı uygulayın.
- [ ] Kontrol (hata ayrıntıları kapalı): tarayıcıda `https://panel.alan.adi/panel/olmayan-sayfa` açın. Sade bir "bulunamadı" sayfası görünmeli; dosya yolu, kod ya da hata ayrıntısı görünmemeli.
- [ ] Kontrol (HTTPS): `http://panel.alan.adi` adresini açın. Kendiliğinden `https://` adresine geçmeli, adres çubuğunda kilit simgesi olmalı. Geçmiyorsa KURULUM 4. adıma dönün.
- [ ] Kontrol (dosyalar kapalı): `https://panel.alan.adi/.env` ve `https://panel.alan.adi/vendor/autoload.php` 404 ya da 403 vermeli.
- [ ] Kontrol (güvenli çerez): panele giriş yapın, `F12` → **Uygulama** (Application) → **Çerezler** (Cookies) → `panel.alan.adi`. Oturum çerezinin (`…_session`) **Secure** sütunu işaretli olmalı. `SESSION_SECURE_COOKIE`'yi yeni eklediyseniz çıkış yapıp yeniden girin.

## 2. APP_KEY yedeği

- [ ] `.env`'deki `APP_KEY` değerini parola yöneticinizde kayıtlı olanla karşılaştırın. Aynıysa tamamdır; kayıtlı değilse ya da farklıysa **hostingteki değeri** parola yöneticisine kaydedin.

Anahtar değişirse ya da kaybolursa şifreli lisans anahtarları okunamaz. Canlıdaki anahtarı bir daha **üretmeyin**, değiştirmeyin; yeni `.env` yazarken de bu değeri koruyun.

## 3. Şifreyi değiştirme ve `ADMIN_PASSWORD` satırını silme

İlk giriş `.env`'deki `ADMIN_PASSWORD` ile yapıldı; bu şifre `.env` dosyasında ve önbellekte düz metin olarak durur. Panelden değiştirilir, satır silinir.

- [ ] `https://panel.alan.adi/panel` adresinde giriş yapın → sağ üstteki kullanıcı menüsüne (**Kullanıcı menüsü**) tıklayın → **Profil**.
- [ ] **Güncel şifre**'ye `.env`'deki şifreyi, **Yeni şifre** ve **Yeni şifreyi onayla**'ya yeni (güçlü, parola yöneticisinde saklanan) şifreyi yazın → **Değişiklikleri Kaydet** → "Kaydedildi" bildirimi görünmeli.
- [ ] Kullanıcı menüsü → **Oturumu kapat**; yeni şifreyle yeniden giriş yapın. Eski şifre artık girmemeli.
- [ ] Dosya Yöneticisi → `.env` → `ADMIN_PASSWORD="…"` satırını silin → Kaydet. Ortak kuralı uygulayın (önbellek silinir). `ADMIN_NAME` ve `ADMIN_EMAIL` kalabilir.
- [ ] Kontrol: `https://panel.alan.adi/deploy?key=DEPLOY_KEY` adresini açın. Hata vermemeli ("Bu sürüm zaten yayında, bir şey yapılmadı." ya da "Yeni sürüm yayına alındı." yazar; kullanıcıya dokunulmaz). Yeni şifre hâlâ girer.

## 4. Ayarların doldurulması

- [ ] **Ayarlar → Genel**: yenileme gün sayısı, özet sıklığı (Günlük / Aylık), ayın günü (aylıkta), özet saati ve **Bildirim adresi** (özetin gideceği adres) doldurulur → **Kaydet** → "Ayarlar kaydedildi."
- [ ] **Ayarlar → Ödeme yöntemleri**: kullanacağınız yöntemler tanımlı (varsayılanlar: Havale/EFT, Kredi Kartı; eksik olanı ekleyin).
- [ ] **Katalog → Hizmetler** ve **Paketler**: verdiğiniz hizmet türleri ve paketler tanımlı. Müşteri verisini girerken (K-5.3) bunlardan seçilir.

## 5. Test e-postası

- [ ] **Ayarlar → Genel** → **Test e-postası gönder**. "Test e-postası kayıtlı bildirim adresine (…) gönderildi." bildirimi çıkar. Düğme **kayıtlı** adresi kullanır: adresi az önce değiştirdiyseniz önce **Kaydet**; kaydetmediyseniz bildirim bunu da söyler.
- [ ] Kontrol: bildirim adresinin gelen kutusunda "Test e-postası" konulu ileti görünmeli, **spam klasöründe olmamalı**. Düğmedeki "Panele git" bağlantısı paneli açmalı.
- [ ] "Test e-postası gönderilemedi" bildirimi çıkarsa mesajı okuyun; KURULUM 6. (e-posta hesabı), 7. (SPF/DKIM) ve 9. adımdaki `MAIL_*` değerlerini ve Sorun giderme tablosunu kontrol edin. `.env` düzeltince ortak kuralı uygulayıp yeniden deneyin. Ayrıntı `public_html/storage/logs/laravel.log` içindedir.

## 6. Örnek veri yok

Örnek veri yalnız yerel ortamda oluşur; canlıda çalışması engellenmiştir. Yine de görerek doğrulayın.

- [ ] **Müşteriler**, **Açık borçlar** ve **Ödemeler** listeleri boş olmalı.
- [ ] Üst çubuktaki arama kutusuna `example.com` ve `Murat Kaya` yazın: sonuç çıkmamalı.

## 7. Zamanlayıcı (cron)

- [ ] **Bugün** sayfasında zamanlayıcı uyarısı (kırmızı kutuda "Zamanlayıcı …" metni) olmamalı. Uyarı varsa en çok 15 dakika bekleyip sayfayı yenileyin; sürerse KURULUM 10. adımı (cron) kontrol edin. "Yayın adresini açın" uyarısı çıkarsa adresi açın.

## 8. Bitti

Tüm kutular işaretliyse sistem canlıya hazır. Sıradaki iş: mevcut müşterileri ve hizmetleri "Bu dönemin borcu: Oluşturma" ile girmek (ROADMAP K-5.3). Veri girişinden sonra ilk özet, ayarlanan sıklığın sıradaki planlı anında gelir.
