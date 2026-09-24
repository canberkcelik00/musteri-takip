# Görev listesi (ROADMAP)

Her görev tek oturumda bitirilebilecek, kabul kriterleriyle doğrulanabilen bir iştir. Kabul kriterleri mümkün olduğunca Pest testi olarak yazılır; görev ancak testler, Pint ve Larastan temizken tamamlanmış sayılır.

**Çalışma döngüsü:** plan modunda planı onayla → uygulat → kontroller geçsin → commit → bu listede işaretle → `/clear`.

**G** ile başlayanlar Claude Code görevleridir. **K** ile başlayanlar kullanıcının elle yapacağı işlerdir (hesap açmak, şifre girmek gibi); Claude Code bunları yapmaz, gerektiğinde kullanıcıya hatırlatır.

## Faz 1 – İskelet

Hedef: Yerelde çalışan, otomatik test edilen bir Laravel 13 + Filament 5 projesi ve projeyi çalıştırabileceği doğrulanmış bir hosting. Yayına alma Faz 5'te kurulur.

- [x] **K-1.1 GitHub deposu ve bilgisayar:** Özel (private) `musteri-takip` deposunu aç. Git for Windows'u kur (Claude Code Windows'ta Git Bash kullanır), `git config --global core.autocrlf false` ayarını yap, Docker Desktop'ın çalıştığını kontrol et.
- [x] **K-1.2 Hosting uyumluluk kontrolü:** cPanel'de PHP sürümünü 8.3 yap ve "PHP Uzantıları" ekranında şunların açık olduğunu kontrol et: `intl`, `mbstring`, `pdo_mysql`, `gd`, `dom`, `xml`, `zip`, `fileinfo`, `curl`, `openssl`, `tokenizer`, `ctype`. Kapalı olanı aç; açılamıyorsa hosting firmasına sor.
- [x] **G-1.1 Proje kurulumu** — Bağımlılık: K-1.1
  - Yapılacak: Windows klasöründe, projeye ait Docker Compose ortamıyla (`compose.yaml` + `Dockerfile`) Laravel 13 projesi: PHP 8.3 uygulama konteyneri, MySQL 8.0 (uygulama ve test veritabanı), Mailpit; `vendor` ve `node_modules` Docker biriminde; `.gitattributes` ile LF satır sonları. Proje konteyner içindeki Composer ile oluşturulur. Klasör ve depo adı `musteri-takip`, `APP_NAME` "Müşteri Takip". `composer.json` içinde platform PHP 8.3. Git başlatılır, depoya bağlanır.
  - Kabul: `docker compose up -d` ile ana sayfa açılır; `docker compose exec app php artisan --version` 13.x gösterir; platform ayarı 8.3; README'de yerel kurulum adımları var.
- [x] **G-1.2 Kalite araçları** — Bağımlılık: G-1.1
  - Yapılacak: Pest, Pint ve Larastan kurulur; `composer test`, `composer lint`, `composer analyse` komutları tanımlanır.
  - Kabul: Üç komut da temiz projede hatasız geçer.
- [x] **G-1.3 Claude Code altyapısı** — Bağımlılık: G-1.2
  - Yapılacak: Laravel Boost kurulur. Dosya setinin (`CLAUDE.md`, `docs/*.md`) repoda doğru yerde olduğu doğrulanır; Boost'un ürettiği yönergeler CLAUDE.md ile çelişiyorsa kullanıcıya sorulur.
  - Kabul: Dosyalar repoda; Boost çalışıyor.
- [x] **G-1.4 Filament paneli ve Türkçe ayarlar** — Bağımlılık: G-1.2
  - Yapılacak: Filament 5 kurulur, panel `/panel` adresinde. Dil Türkçe, saat dilimi Europe/Istanbul, tarih biçimi gg.aa.yyyy. İlk yönetici kullanıcı `.env`'deki bilgilerden oluşturulur.
  - Kabul: Giriş yapılmadan panel açılmaz, giriş yapılınca açılır (testli); arayüz Türkçe.
- [x] **G-1.5 Sürekli test (CI)** — Bağımlılık: G-1.2
  - Yapılacak: Her push ve pull request'te GitHub Actions; MySQL 8.0 servisiyle Pint, Larastan ve Pest çalışır.
  - Kabul: GitHub'da ilk çalışma yeşil.

**Faz 1 bittiğinde:** Yerelde çalışan boş bir Türkçe panel var; her kod değişikliği GitHub'da otomatik test ediliyor; hostingin projeyi çalıştırabileceği doğrulanmış.

## Faz 2 – Veri katmanı

Hedef: `docs/DATA_MODEL.md`'deki tabloların, modellerin ve hesaplanan değerlerin testleriyle hazır olması. Teklif tabloları Faz 6'da eklenir.

- [x] **G-2.1 Enum'lar** — Bağımlılık: Faz 1
  - Yapılacak: `Currency`, `ServiceType`, `CustomerServiceStatus`, `ChargeStatus`. `ServiceType` her tür için Türkçe etiketi ve katalog ile müşteri düzeyindeki `details` anahtarlarını tanımlar.
  - Kabul: Her türün etiketleri ve anahtarları PRD'deki tabloyla birebir aynı (testli).
- [x] **G-2.2 Para işlemleri** — Bağımlılık: G-2.1
  - Yapılacak: Kuruş cinsinden tutarlar için yardımcı: "4.000,50" gibi girdiyi 400050'ye çevirme, "4.000,50 TL" biçiminde gösterme, para birimi başına toplama.
  - Kabul: Türkçe biçimler, sıfır, büyük tutarlar ve farklı para birimlerini karıştırmama testli; hiçbir yerde float yok.
- [x] **G-2.3 Müşteriler ve ilgili kişiler** — Bağımlılık: G-2.1
  - Yapılacak: Migration, model, ilişkiler, factory; arşiv kapsamı; birincil kişi kuralları.
  - Kabul: Kısa ad benzersiz; ilk kişi otomatik birincil; birincil değişince önceki kalkar; birincil kişi silinemez (hepsi testli).
- [x] **G-2.4 Katalog ve paketler** — Bağımlılık: G-2.1
  - Yapılacak: `catalog_services`, `packages`, `package_items`; modeller, ilişkiler, factory'ler, arşiv.
  - Kabul: Aynı hizmet bir pakete iki kez eklenemez; arşivlenen kalem listelerde görünmez ama mevcut ilişkiler bozulmaz (testli).
- [x] **G-2.5 Ödeme yöntemleri ve ayarlar** — Bağımlılık: G-2.1
  - Yapılacak: `payment_methods` (varsayılan: Havale/EFT, Nakit, Kredi Kartı); `settings` tablosu ve varsayılan değerleri döndüren ayar servisi.
  - Kabul: Kayıt yokken varsayılanlar döner, kayıt varsa o döner (testli).
- [x] **G-2.6 Müşteri hizmetleri ve kalemleri** — Bağımlılık: G-2.3, G-2.4
  - Yapılacak: `customer_services`, `customer_service_items`; modeller, ilişkiler, factory durumları (tek hizmet, paket, ücretsiz, iptal). Lisans anahtarı şifreli.
  - Kabul: Katalog hizmeti ve paketten tam olarak biri dolu olmalı; lisans anahtarı veritabanında şifreli duruyor (testli).
  - Not: Müşteri hizmeti kalemi kaydedilirken `details` içinde yalnızca türün `customerDetailKeys()` anahtarları tutulur, diğerleri atılır; tür değişince eski anahtarlar silinir (G-2.4'teki katalog kuralıyla aynı; testli).
- [x] **G-2.7 Borçlar, ödemeler ve dağıtımlar** — Bağımlılık: G-2.2, G-2.5, G-2.6
  - Yapılacak: `charges`, `payments`, `payment_allocations`; modeller ve hesaplanan değerler: borcun kalan tutarı, kısmi ödenmiş, gecikmiş, müşterinin para birimi başına alacak bakiyesi.
  - Kabul: Aynı dönem için ikinci borç veritabanı düzeyinde engellenir; tüm hesaplanan değerler örnek senaryolarla testli.
- [x] **G-2.8 Örnek veri** — Bağımlılık: G-2.7
  - Yapılacak: Sadece yerelde çalışan gerçekçi Türkçe örnek veri: müşteriler, katalog, paketler, farklı tarihli hizmetler, ücretsiz ve iptal edilmiş kayıtlar, ödenmiş, kısmi ve gecikmiş borçlar, USD kayıtlar.
  - Kabul: `docker compose exec app php artisan migrate:fresh --seed` hatasız çalışır; canlıda çalıştırılamaz.

**Faz 2 bittiğinde:** Veritabanı ve modeller hazır, hesaplanan değerler testli; ekranda henüz yeni bir şey görünmez.

## Faz 3 – İş mantığı

Hedef: Borç ve ödeme kurallarının tamamı arayüzden bağımsız Action sınıfları olarak, kapsamlı testlerle hazır olması. Tarih testleri zaman yolculuğu (Carbon) ile yazılır. Bu fazda hiçbir kural test olmadan eklenmez.

- [ ] **G-3.1 Ödeme kaydı ve dağıtım** — Bağımlılık: Faz 2
  - Yapılacak: Ödemeyi aynı para birimindeki açık borçlara en eski vadeden başlayarak dağıtma; elle dağıtım; artanı alacak bakiyesine bırakma; ödeme silinince dağıtımları geri alma.
  - Kabul: Tam, kısmi ve fazla ödeme; farklı para birimindeki borca düşmeme; bir borca tutarından fazla dağıtılamama; silmede borcun yeniden açılması (hepsi testli). Ödeme yalnızca aynı müşterinin ve aynı para birimindeki borçlarına dağıtılabilir; başka müşterinin borcuna dağıtma reddedilir (testli).
- [ ] **G-3.2 Bakiyenin yeni borca uygulanması** — Bağımlılık: G-3.1
  - Yapılacak: Yeni borç doğduğunda müşterinin aynı para birimindeki alacak bakiyesini otomatik olarak o borca düşme.
  - Kabul: Bakiye borçtan büyük, eşit ve küçük durumları; farklı para biriminde bakiyenin kullanılmaması (testli).
- [ ] **G-3.3 Hizmet atama ve ilk dönem borcu** — Bağımlılık: G-3.2
  - Yapılacak: Katalog hizmetini veya paketi müşteriye atama: ad, fiyat ve para birimi kopyalanır, pakette her iç hizmet için kalem oluşur, bitiş = başlangıç + 12 ay, ücretsiz değilse ve "Bu dönemin borcu: Oluşturma" seçilmediyse ilk dönem borcu doğar ve bakiye uygulanır.
  - Kabul: Tek hizmet, paket, ücretsiz, değiştirilmiş fiyatla ve "Oluşturma" seçeneğiyle (Excel'den geçiş) atama; katalog fiyatı sonradan değişince müşteri hizmetinin etkilenmemesi (testli). Hizmete bağlı borç oluşturulurken borcun müşterisi hizmetin müşterisiyle aynı olmalı (testli).
- [ ] **G-3.4 Yenileme borcu** — Bağımlılık: G-3.3
  - Yapılacak: Aktif ve ücretsiz olmayan hizmetlerde bitişe ayarlardaki gün sayısı (30) kaldığında sonraki dönemin borcunu oluşturma; vade = bitiş tarihi.
  - Kabul: Tam sınır günü, sınırı geçmiş ama borcu oluşmamış kayıt (kaçırılan cron), iki kez çalıştırmada tek borç, ücretsiz ve iptal edilmişleri atlama (testli). Hizmete bağlı borç oluşturulurken borcun müşterisi hizmetin müşterisiyle aynı olmalı (testli).
- [ ] **G-3.5 Dönem ilerletme** — Bağımlılık: G-3.4
  - Yapılacak: Bitiş tarihi gelen aktif hizmetlerin (ücretsizler dahil) tarihlerini bir dönem ilerletme.
  - Kabul: Ödeme durumundan bağımsız ilerler; iptal edilmişler ilerlemez; iki kez çalıştırmada tek ilerleme (testli).
- [ ] **G-3.6 Ücretsiz işaretinin kaldırılması** — Bağımlılık: G-3.4
  - Yapılacak: İşaret yenileme penceresi içinde ve dönem başlamadan kaldırılırsa yaklaşan dönemin borcunu hemen oluşturma.
  - Kabul: Pencere içinde ve dışında kaldırma senaryoları (testli).
- [ ] **G-3.7 İptal ve yeniden aktif etme** — Bağımlılık: G-3.4
  - Yapılacak: İptal; isteğe bağlı olarak henüz başlamamış dönemin ödenmemiş borcunu iptal; yeniden aktif etme.
  - Kabul: Geçmiş dönem borcu hiçbir durumda iptal edilmez; dağıtımı olan borç iptal edilmez (testli).
  - Not: Aynı dönem için tek borç kuralı iptal edilmiş borçları da kapsar; yeniden aktif etmede iptal edilmiş dönem borcunun nasıl ele alınacağı bu görevin planında kullanıcıya sorulmalı.
- [ ] **G-3.8 Borçları elle düzenleme** — Bağımlılık: G-3.1
  - Yapılacak: Tutar ve vade değiştirme, borcu ikiye bölme, gerekçeyle iptal, kurala bağlı olmayan elle borç ekleme (bakiye uygulanır).
  - Kabul: Dağıtımı olan borçta tutar değişmez; bölmede toplam korunur; gerekçesiz iptal edilemez (testli). Hizmete bağlı borç oluşturulurken borcun müşterisi hizmetin müşterisiyle aynı olmalı (testli).
- [ ] **G-3.9 Zamanlayıcı** — Bağımlılık: G-3.5
  - Yapılacak: Yenileme borcu ve dönem ilerletme `routes/console.php` içinde her 15 dakikada "henüz yapılmadıysa yap" mantığıyla çalışır; o gün oluşan borçlar günlük özet için izlenebilir.
  - Kabul: Üst üste çalışmada yinelenen kayıt oluşmaz; günler boyunca atlanan cron sonrası ilk çalışma eksikleri tamamlar (testli).
- [ ] **G-3.10 Uçtan uca senaryo** — Bağımlılık: G-3.1…G-3.9
  - Yapılacak: Aşağıdaki Logi senaryosunun (atama, fazla ödeme, 30 gün kala yenileme, bakiyenin düşmesi, dönem ilerlemesi, iptal) tek bir testte zaman ilerletilerek doğrulanması.
  - Kabul: Senaryodaki her adımın tutarları ve tarihleri birebir aynı.

**Logi senaryosu (G-3.10 için referans):**

1. Logi'ye Kurumsal Paket (hosting + alan adı + bakım) 4.000 TL ile atanır; dönem 01.03.2026 – 01.03.2027. İlk dönem borcu 4.000 TL, vade 01.03.2026.
2. Logi 5.000 TL öder: 4.000 TL borca düşer, borç ödendi olur; 1.000 TL alacak bakiyesi kalır.
3. 30.01.2027'de (bitişe 30 gün kala) yeni dönem borcu oluşur: 4.000 TL, vade 01.03.2027, dönem 01.03.2027 – 01.03.2028. 1.000 TL bakiye otomatik düşer, 3.000 TL açık kalır.
4. 01.03.2027'de müşteri hizmetinin tarihleri 01.03.2027 – 01.03.2028 olur.

**Faz 3 bittiğinde:** Sistemin tüm para ve tarih kuralları testlerle kanıtlanmış durumda; arayüz bu kuralların üzerine sadece ekran olarak eklenecek.

## Faz 4 – Arayüz

Hedef: Faz 3'teki kuralların Filament ekranlarıyla kullanılabilir olması. Ekranlar iş mantığı içermez, sadece Action sınıflarını çağırır. Her ekranın temel akışı Livewire testiyle korunur. Ölçüt: "Excel'den daha kolay mı?"

- [ ] **G-4.1 Ortak bileşenler** — Bağımlılık: Faz 3
  - Yapılacak: Türkçe biçimli tutar girişi ve para birimi seçimi, tutar gösterimi, para birimine göre ayrı toplamlar, gg.aa.yyyy tarih.
  - Kabul: "4.000,50" girişi 400050 olarak kaydedilir ve aynı biçimde görünür (testli).
- [ ] **G-4.2 Katalog ekranları** — Bağımlılık: G-4.1
  - Yapılacak: Katalog hizmetleri (seçilen türe göre alanların değiştiği form), paketler (içerik seçimi ve kendi fiyatı), arşivleme.
  - Kabul: Hosting seçilince hosting planı alanları, domain seçilince domain alanları görünür (testli).
  - Not: Paket formunda hizmet seçimi yalnızca arşivlenmemiş katalog hizmetlerini listeler.
- [ ] **G-4.3 Müşteri listesi ve formu** — Bağımlılık: G-4.1
  - Yapılacak: Kısa adla arama; listede hizmet sayısı, para birimi başına açık borç ve en yakın yenileme; müşteri formu; ilgili kişiler ve birincil işareti. Müşterinin en az bir ilgili kişisi olmalı; müşteri oluşturma formunda ilk kişi zorunlu.
  - Kabul: Listedeki özet değerler örnek veriyle doğru (testli).
- [ ] **G-4.4 Müşteri kartı** — Bağımlılık: G-4.3
  - Yapılacak: Tek sayfada ilgili kişiler, müşteri hizmetleri (tarih, fiyat, durum), borçlar (açık, kısmi, gecikmiş), ödemeler ve para birimi başına alacak bakiyesi.
  - Kabul: Logi senaryosundaki durum kartta PRD'deki gibi görünür (testli).
- [ ] **G-4.5 Hizmet atama** — Bağımlılık: G-4.4, G-4.2
  - Yapılacak: Müşteri kartından "Hizmet ekle": katalog hizmeti veya paket seçimi; fiyat, para birimi ve başlangıç önerilir, değiştirilebilir; ücretsiz; "Bu dönemin borcu"; türe göre müşteriye özel alanlar (alan adı, sunucu, cPanel kullanıcı adı vb.). Not: Lisans anahtarı `details`'te değil, `customer_service_items.license_key` sütununda (şifreli) tutulur; lisans türü için formda `ServiceType` anahtarlarından ayrı olarak gösterilmelidir.
  - Kabul: Tek hizmet ve paket atama akışları doğru kayıt ve borç oluşturur (testli).
- [ ] **G-4.6 Müşteri hizmeti işlemleri** — Bağımlılık: G-4.5
  - Yapılacak: Düzenleme (fiyat, tarihler, kalem bilgileri), ücretsiz işareti, iptal penceresi (yaklaşan dönem borcunu iptal kutusu işaretli gelir, geçmiş borç bilgi olarak gösterilir), yeniden aktif etme.
  - Kabul: İptal penceresi PRD'deki kurallara uyar (testli).
  - Not: Kalem türü düzenleme ekranında değiştirilemez; tür satış anında katalogdan kopyalanır.
- [ ] **G-4.7 Ödeme kaydı** — Bağımlılık: G-4.4
  - Yapılacak: İki alanlı form (müşteri, tutar); tarih bugün, yöntem son kullanılan, para birimi açık borçtan gelir; dağıtım önizlemesi isteğe bağlı açılır ve değiştirilebilir; sonuç mesajı ("1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi"); onaylı silme.
  - Kabul: Varsayılanlar ve sonuç mesajı doğru (testli).
- [ ] **G-4.8 Borç ekranları** — Bağımlılık: G-4.4
  - Yapılacak: Borç listesi (açık, gecikmiş, para birimi filtreleri), elle borç ekleme, düzenleme, bölme, gerekçeli iptal.
  - Kabul: Dağıtımı olan borçta tutar alanı kilitli görünür (testli).
- [ ] **G-4.9 Ayarlar ve ödeme yöntemleri** — Bağımlılık: G-4.1
  - Yapılacak: Ayarlar sayfası (yenileme gün sayısı, özet saati, bildirim adresi) ve ödeme yöntemleri yönetimi.
  - Kabul: Değişen ayar kurallara hemen yansır (testli).
- [ ] **G-4.10 Silme ve arşiv davranışı** — Bağımlılık: G-4.2…G-4.9
  - Yapılacak: Borcu veya ödemesi olmayan kayıtta "Sil", olanda sadece "Arşivle" görünür; arşiv filtresi ve geri alma.
  - Kabul: Tüm listelerde kural tutarlı (testli).

**Faz 4 bittiğinde:** Sistem elle kullanılabilir durumda; mevcut müşteriler ve hizmetler girilebilir.

## Faz 5 – Yayına alma, panel ve MVP kapanışı

Hedef: Sistemin hostingde çalışması, kullanıcıya ne yapması gerektiğini kendiliğinden söylemesi ve güvenle canlıya alınması. Yayına alma fazın başında kurulur ki panel ve özet e-postası canlıda denenebilsin.

- [ ] **G-5.1 Yayın uç noktası ve cron yedeği** — Bağımlılık: Faz 4
  - Yapılacak: Gizli anahtarla korunan yayın adresi; migration, önbellek yenileme ve gerekirse storage bağlantısını çalıştırır. Aynı kontrol zamanlayıcıda "yeni sürüm varsa bir kez yap" görevi olarak çalışır.
  - Kabul: Yanlış veya eksik anahtar reddedilir; doğru anahtarla komutlar bir kez çalışır; aynı sürüm için tekrar çalışmaz (hepsi testli).
- [ ] **G-5.2 Otomatik yayına alma** — Bağımlılık: G-1.5, G-5.1
  - Yapılacak: `main` dalına her güncellemede testler geçerse `vendor` ve arayüz dosyalarıyla paket hazırlanır, FTPS ile yüklenir, ardından yayın adresi çağrılır. `.env` ve `storage` içeriği asla üzerine yazılmaz. Filament'in public/ altındaki dosyaları git'te değil, composer install sırasında üretilir; paket hazırlanırken Composer betikleri kapatılmamalı ve paketin Filament dosyalarını içerdiği kontrol edilmeli.
  - Kabul: Gerekli GitHub secret'ları belgelenmiş; test başarısızsa yükleme yapılmaz.
- [ ] **G-5.3 Kurulum rehberi** — Bağımlılık: G-5.2
  - Yapılacak: `docs/KURULUM.md`: cPanel'de PHP 8.3, veritabanı, kök dizini `public`'e yönlendirme, `.env`, */15 cron, e-posta hesabı, SPF/DKIM kontrolü, GitHub secret'ları.
  - Kabul: Rehber ekran adımlarıyla, SSH gerektirmeden takip edilebilir.
- [ ] **K-5.1 Hosting kurulumu ve ilk yayın:** Rehberi uygula, GitHub secret'larını gir, ilk yayını başlat ve panele canlıda giriş yap.
- [ ] **G-5.4 Ana panel** — Bağımlılık: Faz 4
  - Yapılacak: Panel açılış sayfasında: para birimi başına toplam alacak, gecikmiş borçlar (kaç gün geciktiğiyle), 30 gün içinde yenilenecekler, ücretsiz devam kararı bekleyenler. Her satır ilgili kayda gider.
  - Kabul: Örnek veriyle tüm kutular doğru sayı ve tutarı gösterir (testli).
- [ ] **G-5.5 Günlük özet e-postası** — Bağımlılık: G-5.4
  - Yapılacak: Türkçe, sade e-posta; bölümler: bugün oluşan borçlar (bakiyeden kapatılanlar belirtilir), ücretsiz devam kararı bekleyenler, vadesi geçmiş borçlar; her satırda bağlantı. Ayarlanan saatten sonraki ilk zamanlayıcı çalışmasında günde bir kez gönderilir; tüm bölümler boşsa gönderilmez.
  - Kabul: Günde tek gönderim, boş günde gönderilmeme, saatten önce gönderilmeme, atlanan cron sonrası gönderim (testli); Mailpit'te görünüm kontrol edilir.
- [ ] **G-5.6 Zamanlayıcı sağlık kontrolü** — Bağımlılık: G-5.4
  - Yapılacak: Zamanlayıcının son çalışma zamanı kaydedilir; 1 saatten uzun süredir çalışmadıysa panelde uyarı görünür.
  - Kabul: Süre aşılınca uyarı görünür, çalışınca kaybolur (testli).
- [ ] **G-5.7 MVP son kontrol** — Bağımlılık: G-5.1…G-5.6
  - Yapılacak: Ana akışların ekran üzerinden uçtan uca testi (müşteri ekle → hizmet ata → ödeme al → yenileme → iptal); birkaç yüz müşterilik örnek veriyle listelerin hız kontrolü; tüm dokümanların güncelliği.
  - Kabul: Tüm testler yeşil; ROADMAP ve DECISIONS güncel.
- [ ] **G-5.8 Canlıya geçiş kontrol listesi** — Bağımlılık: G-5.7
  - Yapılacak: `docs/CANLIYA_GECIS.md`: canlı ortam ayarları (hata ayrıntıları kapalı, HTTPS), test e-postası, ayarların doldurulması, "İlk girişten sonra panelden şifreni değiştir ve hostingdeki .env'den ADMIN_PASSWORD satırını sil.", "Canlı ortamın APP_KEY değerini güvenli bir yerde yedekle; değişir veya kaybolursa şifreli lisans anahtarları okunamaz.", örnek verinin canlıda olmadığının kontrolü.
  - Kabul: Liste ekran adımlarıyla takip edilebilir.
- [ ] **K-5.2 Yedek kontrolü:** Hosting panelinde veritabanı yedeklerinin günlük alındığını ve bir yedeğin geri yüklenebildiğini kontrol et.
- [ ] **K-5.3 Canlıya geçiş ve veri girişi:** Kontrol listesini uygula, mevcut müşterileri ve hizmetleri "Bu dönemin borcu: Oluşturma" ile gir.
- [ ] **K-5.4 Paralel kullanım:** 2–4 hafta boyunca Excel'i bırakmadan sistemle birlikte kullan; tutarlılığı görünce Excel'i bırak.

**Faz 5 bittiğinde:** MVP tamam; sistem günlük işi Excel'in yerine yürütüyor.

## Faz 6 ve sonrası

Teklifler ve sonraki fazların görevleri, MVP bitip sistem bir süre kullanıldıktan sonra yazılacak. Claude Code bu fazlara ait bir işi, görevleri bu dosyaya eklenmeden yapmaz.
