# Görev listesi

Her görev Claude Code'un tek oturumda bitirebileceği, kabul kriterleriyle doğrulanabilen bir iştir. Kabul kriterleri mümkün olduğunca Pest testi olarak yazılır; görev ancak testler, Pint ve Larastan temizken tamamlanmış sayılır.

**Çalışma döngüsü:** plan modunda planı onayla → uygulat → kontroller geçsin → commit → bu listede işaretle → `/clear`.

**G** ile başlayanlar Claude Code görevleridir. **K** ile başlayanlar senin elle yapacağın işlerdir (hesap açmak, şifre girmek gibi).

## Faz 1 – İskelet

Hedef: boş ama çalışan, test edilen ve hostinge otomatik yüklenen bir Laravel 13 + Filament 5 projesi.

- [x] **K-1.1 GitHub deposu ve bilgisayar:** Özel (private) `musteri-takip` deposunu aç. Git for Windows'u kur (Claude Code Windows'ta Git Bash kullanır), `git config --global core.autocrlf false` ayarını yap, Docker Desktop'ın çalıştığını kontrol et.
- [x] **K-1.2 Hosting uyumluluk kontrolü:** cPanel'de PHP sürümünü 8.3 yap ve "PHP Uzantıları" ekranında şunların açık olduğunu kontrol et: `intl`, `mbstring`, `pdo_mysql`, `gd`, `dom`, `xml`, `zip`, `fileinfo`, `curl`, `openssl`, `tokenizer`, `ctype`. Kapalı olanı aç; açılamıyorsa hosting firmasına sor.
- [x] **G-1.1 Proje kurulumu** — Bağımlılık: K-1.1
  - Yapılacak: Windows klasöründe, projeye ait Docker Compose ortamıyla Laravel 13 projesi: PHP 8.3 uygulama konteyneri, MySQL 8.0 (uygulama ve test veritabanı), Mailpit; `vendor` ve `node_modules` Docker biriminde; `.gitattributes` ile LF satır sonları. Proje konteyner içindeki Composer ile oluşturulur. Klasör ve depo adı `musteri-takip`, `APP_NAME` "Müşteri Takip". `composer.json` içinde platform PHP 8.3. Git başlatılır, depoya bağlanır.
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

**Faz 1 bittiğinde:** Yerelde çalışan boş bir Türkçe panel var; her kod değişikliği GitHub'da otomatik test ediliyor; hostingin projeyi çalıştırabileceği doğrulanmış. Yayına alma Faz 5'te kurulur.

## Faz 2 – Veri katmanı

Hedef: "Veri modeli – alanlar" sekmesindeki tabloların, modellerin ve hesaplanan değerlerin testleriyle hazır olması. Teklif tabloları Faz 6'da eklenir.

- [x] **G-2.1 Enum'lar** — Bağımlılık: Faz 1
  - Yapılacak: `Currency`, `ServiceType`, `CustomerServiceStatus`, `ChargeStatus`. `ServiceType` her tür için Türkçe etiketi ve katalog ile müşteri düzeyindeki `details` anahtarlarını tanımlar.
  - Kabul: Her türün etiketleri ve anahtarları planlama dokümanındaki tabloyla birebir aynı (testli).
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
  - Yapılacak: `customer_services`, `customer_service_items`; modeller, ilişkiler, factory durumları (tek hizmet, paket, ücretsiz, iptal). Lisans anahtarı şifreli. Kalem kaydedilirken details içinde yalnızca türün müşteri düzeyi anahtarları tutulur (G-2.4'teki katalog kuralıyla aynı).
  - Kabul: Katalog hizmeti ve paketten tam olarak biri dolu olmalı; lisans anahtarı veritabanında şifreli duruyor (testli).
- [x] **G-2.7 Borçlar, ödemeler ve dağıtımlar** — Bağımlılık: G-2.2, G-2.5, G-2.6
  - Yapılacak: `charges`, `payments`, `payment_allocations`; modeller ve hesaplanan değerler: borcun kalan tutarı, kısmi ödenmiş, gecikmiş, müşterinin para birimi başına alacak bakiyesi.
  - Kabul: Aynı dönem için ikinci borç veritabanı düzeyinde engellenir; tüm hesaplanan değerler örnek senaryolarla testli.
- [x] **G-2.8 Örnek veri** — Bağımlılık: G-2.7
  - Yapılacak: Sadece yerelde çalışan gerçekçi Türkçe örnek veri: müşteriler, katalog, paketler, farklı tarihli hizmetler, ücretsiz ve iptal edilmiş kayıtlar, ödenmiş, kısmi ve gecikmiş borçlar, USD kayıtlar.
  - Kabul: `docker compose exec app php artisan migrate:fresh --seed` hatasız çalışır; canlıda çalıştırılamaz.

**Faz 2 bittiğinde:** Veritabanı ve modeller hazır, hesaplanan değerler testli; ekranda henüz yeni bir şey görünmez.

## Faz 3 – İş mantığı

Hedef: Borç ve ödeme kurallarının tamamı arayüzden bağımsız Action sınıfları olarak, kapsamlı testlerle hazır olması. Tarih testleri zaman yolculuğu (Carbon) ile yazılır. Bu fazda hiçbir kural test olmadan eklenmez.

- [x] **G-3.1 Ödeme kaydı ve dağıtım** — Bağımlılık: Faz 2
  - Yapılacak: Ödemeyi aynı para birimindeki açık borçlara en eski vadeden başlayarak dağıtma; elle dağıtım; artanı alacak bakiyesine bırakma; ödeme silinince dağıtımları geri alma.
  - Kabul: Tam, kısmi ve fazla ödeme; farklı para birimindeki borca düşmeme; bir borca tutarından fazla dağıtılamama; silmede borcun yeniden açılması; ödemenin yalnızca aynı müşterinin ve aynı para birimindeki borçlarına dağıtılabilmesi, başka müşterinin borcuna dağıtmanın reddedilmesi (hepsi testli).
- [x] **G-3.2 Bakiyenin yeni borca uygulanması** — Bağımlılık: G-3.1
  - Yapılacak: Yeni borç doğduğunda müşterinin aynı para birimindeki alacak bakiyesini otomatik olarak o borca düşme.
  - Kabul: Bakiye borçtan büyük, eşit ve küçük durumları; farklı para biriminde bakiyenin kullanılmaması (testli).
- [x] **G-3.3 Hizmet atama ve ilk dönem borcu** — Bağımlılık: G-3.2
  - Yapılacak: Katalog hizmetini veya paketi müşteriye atama: ad, fiyat ve para birimi kopyalanır, pakette her iç hizmet için kalem oluşur, bitiş = başlangıç + 12 ay, ücretsiz değilse ve "Bu dönemin borcu: Oluşturma" seçilmediyse ilk dönem borcu doğar ve bakiye uygulanır.
  - Kabul: Tek hizmet, paket, ücretsiz, değiştirilmiş fiyatla ve "Oluşturma" seçeneğiyle (Excel'den geçiş) atama; katalog fiyatı sonradan değişince müşteri hizmetinin etkilenmemesi; borcun müşterisinin hizmetin müşterisiyle aynı olması (testli).
- [x] **G-3.4 Yenileme borcu** — Bağımlılık: G-3.3
  - Yapılacak: Aktif ve ücretsiz olmayan hizmetlerde bitişe ayarlardaki gün sayısı (30) kaldığında sonraki dönemin borcunu oluşturma; vade = bitiş tarihi.
  - Kabul: Tam sınır günü, sınırı geçmiş ama borcu oluşmamış kayıt (kaçırılan cron), iki kez çalıştırmada tek borç, ücretsiz ve iptal edilmişleri atlama; borcun müşterisinin hizmetin müşterisiyle aynı olması (testli).
- [x] **G-3.5 Dönem ilerletme** — Bağımlılık: G-3.4
  - Yapılacak: Bitiş tarihi gelen aktif hizmetlerin (ücretsizler dahil) tarihlerini bir dönem ilerletme.
  - Kabul: Ödeme durumundan bağımsız ilerler; iptal edilmişler ilerlemez; iki kez çalıştırmada tek ilerleme (testli).
- [x] **G-3.6 Ücretsiz işaretinin kaldırılması** — Bağımlılık: G-3.4
  - Yapılacak: İşaret yenileme penceresi içinde ve dönem başlamadan kaldırılırsa yaklaşan dönemin borcunu hemen oluşturma.
  - Kabul: Pencere içinde ve dışında kaldırma senaryoları (testli).
- [x] **G-3.7 İptal ve yeniden aktif etme** — Bağımlılık: G-3.4
  - Yapılacak: İptal; isteğe bağlı olarak henüz başlamamış dönemin ödenmemiş borcunu iptal; yeniden aktif etme.
  - Kabul: Geçmiş dönem borcu hiçbir durumda iptal edilmez; dağıtımı olan borç iptal edilmez (testli). Not: Aynı dönem için tek borç kuralı iptal edilmiş borçları da kapsar; yeniden aktif etmede iptal edilmiş dönem borcunun nasıl ele alınacağı bu görevin planında kullanıcıya sorulur. Ek: Ücretsiz işareti eklenirken yaklaşan dönemin ödenmemiş borcunu iptal etme seçeneği sunulur (varsayılan işaretli); dağıtımı olan borca dokunulmaz; iptal akışıyla aynı yardımcıyı kullanır (testli).
- [x] **G-3.8 Borçları elle düzenleme** — Bağımlılık: G-3.1
  - Yapılacak: Tutar ve vade değiştirme, borcu ikiye bölme, gerekçeyle iptal, kurala bağlı olmayan elle borç ekleme (bakiye uygulanır).
  - Kabul: Dağıtımı olan borçta tutar değişmez; bölmede toplam korunur; gerekçesiz iptal edilemez; hizmete bağlı elle borcun müşterisi hizmetin müşterisiyle aynı olmalı (testli).
- [x] **G-3.9 Zamanlayıcı** — Bağımlılık: G-3.5
  - Yapılacak: Yenileme borcu ve dönem ilerletme `routes/console.php` içinde her 15 dakikada "henüz yapılmadıysa yap" mantığıyla çalışır; o gün oluşan borçlar günlük özet için izlenebilir.
  - Kabul: Üst üste çalışmada yinelenen kayıt oluşmaz; günler boyunca atlanan cron sonrası ilk çalışma eksikleri tamamlar (testli).
- [x] **G-3.10 Uçtan uca senaryo** — Bağımlılık: G-3.1…G-3.9
  - Yapılacak: Planlama dokümanındaki Logi hikayesinin (atama, fazla ödeme, 30 gün kala yenileme, bakiyenin düşmesi, dönem ilerlemesi, iptal) tek bir testte zaman ilerletilerek doğrulanması.
  - Kabul: Senaryodaki her adımın tutarları ve tarihleri dokümanla birebir aynı. Ayrıca örnek veri (SampleDataSeeder) hizmet atama, borç ve ödeme dağıtımı için doğrudan veritabanına yazmak yerine Faz 3 Action'larını kullanacak şekilde yeniden düzenlenir; DatabaseSeederTest yeşil kalır.

**Faz 3 bittiğinde:** Sistemin tüm para ve tarih kuralları testlerle kanıtlanmış durumda; arayüz bu kuralların üzerine sadece ekran olarak eklenecek.

## Faz 4 – Arayüz

Hedef: Faz 3'teki kuralların Filament ekranlarıyla kullanılabilir olması. Ekranlar iş mantığı içermez, sadece Action sınıflarını çağırır. Her ekranın temel akışı Livewire testiyle korunur. Ölçüt: "Excel'den daha kolay mı?"

- [x] **G-4.1 Ortak bileşenler** — Bağımlılık: Faz 3
  - Yapılacak: Türkçe biçimli tutar girişi ve para birimi seçimi, tutar gösterimi, para birimine göre ayrı toplamlar, gg.aa.yyyy tarih.
  - Kabul: "4.000,50" girişi 400050 olarak kaydedilir ve aynı biçimde görünür (testli).
- [x] **G-4.2 Katalog ekranları** — Bağımlılık: G-4.1
  - Yapılacak: Katalog hizmetleri (seçilen türe göre alanların değiştiği form), paketler (içerik seçimi ve kendi fiyatı), arşivleme. Paket formunda hizmet seçimi yalnızca arşivlenmemiş katalog hizmetlerini listeler.
  - Kabul: Hosting seçilince hosting planı alanları, domain seçilince domain alanları görünür (testli).
- [x] **G-4.3 Müşteri listesi ve formu** — Bağımlılık: G-4.1
  - Yapılacak: Kısa adla arama; listede hizmet sayısı, para birimi başına açık borç ve en yakın yenileme; müşteri formu; ilgili kişiler ve birincil işareti. Müşterinin en az bir ilgili kişisi olmalı; müşteri oluşturma formunda ilk kişi zorunlu.
  - Kabul: Listedeki özet değerler örnek veriyle doğru (testli).
- [x] **G-4.4 Müşteri kartı** — Bağımlılık: G-4.3
  - Yapılacak: Tek sayfada ilgili kişiler, müşteri hizmetleri (tarih, fiyat, durum), borçlar (açık, kısmi, gecikmiş), ödemeler ve para birimi başına alacak bakiyesi.
  - Kabul: Logi örneğindeki durum kartta dokümandaki gibi görünür (testli).
- [x] **G-4.5 Hizmet atama** — Bağımlılık: G-4.4, G-4.2
  - Yapılacak: Müşteri kartından "Hizmet ekle": katalog hizmeti veya paket seçimi; fiyat, para birimi ve başlangıç önerilir, değiştirilebilir; ücretsiz; "Bu dönemin borcu"; türe göre müşteriye özel alanlar (alan adı, sunucu, cPanel kullanıcı adı vb.). Lisans anahtarı ayrı şifreli sütunda tutulduğu için lisans türünde formda ayrıca gösterilir. Arşivlenmiş katalog hizmetleri ve paketler de seçilebilir (Excel'den geçişte eski planlar için), "(arşivlendi)" etiketiyle listenin sonunda.
  - Kabul: Tek hizmet ve paket atama akışları doğru kayıt ve borç oluşturur (testli).
- [x] **G-4.6 Müşteri hizmeti işlemleri** — Bağımlılık: G-4.5
  - Yapılacak: Düzenleme (fiyat, tarihler, kalem bilgileri), ücretsiz işareti, iptal penceresi (yaklaşan dönem borcunu iptal kutusu işaretli gelir, geçmiş borç bilgi olarak gösterilir), yeniden aktif etme. Ücretsiz işareti ekleme ve kaldırma G-3.6 ve G-3.7 Action'ları üzerinden yapılır; kalem türü değiştirilemez. Arşivli müşterinin hizmetinde Yeniden aktif et düğmesi, önce müşterinin arşivden çıkarılması gerektiğini söyler.
  - Kabul: İptal penceresi dokümandaki kurallara uyar (testli).
- [x] **G-4.7 Ödeme kaydı** — Bağımlılık: G-4.4
  - Yapılacak: İki alanlı form (müşteri, tutar); tarih bugün, yöntem son kullanılan, para birimi açık borçtan gelir; dağıtım önizlemesi isteğe bağlı açılır ve değiştirilebilir; sonuç mesajı ("1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi"); onaylı silme.
  - Kabul: Varsayılanlar ve sonuç mesajı doğru (testli).
- [x] **G-4.8 Borç ekranları** — Bağımlılık: G-4.4
  - Yapılacak: Borç listesi (açık, gecikmiş, para birimi filtreleri), elle borç ekleme, düzenleme, bölme, gerekçeli iptal.
  - Kabul: Dağıtımı olan borçta tutar alanı kilitli görünür (testli).
- [x] **G-4.9 Ayarlar ve ödeme yöntemleri** — Bağımlılık: G-4.1
  - Yapılacak: Ayarlar sayfası (yenileme gün sayısı, özet saati, bildirim adresi) ve ödeme yöntemleri yönetimi.
  - Kabul: Değişen ayar kurallara hemen yansır (testli).
- [x] **G-4.10 Silme ve arşiv davranışı** — Bağımlılık: G-4.2…G-4.9
  - Yapılacak: Borcu veya ödemesi olmayan kayıtta "Sil", olanda sadece "Arşivle" görünür; arşiv filtresi ve geri alma. Aktif hizmeti olan müşterinin Arşivle düğmesi, önce hizmetlerin iptal edilmesi gerektiğini söyler.
  - Kabul: Tüm listelerde kural tutarlı (testli).
- [x] **K-4.1 Arayüz geri bildirimi:** Ekranları kullanırken zorlanan, çirkin veya Excel'den yavaş gelen her yerin ekran görüntüsünü kısa notla topla (örn. "ödeme almak için 4 tık", "müşteri kartı çok uzun"). Karşılığı: Arayüz ilkeleri sekmesindeki "Şu anki panelden farklar" bölümü.
- [x] **G-4.11 Arayüz ilkelerinin repoya alınması** — Bağımlılık: G-4.10, K-4.1
  - Yapılacak: "Arayüz ilkeleri" sekmesi `docs/UI.md` olarak repoya eklenir; `CLAUDE.md` ve `docs/*.md` listesine referans verilir. Önce "Ödeme al" / "Ödeme kaydet" tutarsızlığı (UI.md Kural 1) giderilir: her yerde "Ödeme al". Kod değişmez.
  - Kabul: Dosya repoda; kullanıcı `docs/UI.md`'yi onaylamış.
- [x] **G-4.12 Tema ve görsel dil** — Bağımlılık: G-4.11
  - Yapılacak: Filament özel teması `docs/UI.md` §6'ya göre hazırlanır. Açık tema varsayılan, koyu tema kullanıcı menüsünden seçilir. Marka rengi (#ff3800) yalnızca logo simgesinde ve seçili menü çizgisinde; birincil düğme (Filament primary) koyu lacivert. Durum renkleri sabit ve başka işte kullanılmaz (kırmızı gecikmiş/tehlikeli, turuncu (Amber) yaklaşan/kısmi, yeşil ödendi, gri iptal/arşiv); normal durum rozetsiz düz metin. "Yaklaşan" tanımı modelde tek yerde: borçta 7 gün içinde vadesi gelen (`Charge::isDueSoon`), hizmette ayarlardaki yenileme gün sayısı içinde bitecek (`CustomerService::isEndingSoon`). Tek yazı tipi (Inter, yerelden), sayılarda eş genişlikte rakamlar, tablo metni 14 px. Tablolarda kompakt, formlarda rahat boşluk. Sol üstte logo simgesi ve "Müşteri Takip". Tema derlemesi CI'a eklenir.
  - Kabul: Durum rozetleri her yerde renk ve metinle görünür; tüm testler yeşil.
- [x] **G-4.13 Menü ve üst çubuk** — Bağımlılık: G-4.12
  - Yapılacak: Menü `docs/UI.md` §3'e göre kurulur: Bugün, Müşteriler, Tahsilat (Açık borçlar, Ödemeler), Katalog (Hizmetler, Paketler; kapalı başlar), Ayarlar (Genel, Ödeme yöntemleri; en altta). Üst çubuk her sayfada aynı yerde: solda arama kutusu, sağda "Ödeme al" birincil düğmesi ve "Yeni" menüsü (Müşteri, Elle borç). Ödeme formu (G-4.7) her sayfada yandan açılan panelde çalışır.
  - Kabul: Üst çubuk her sayfada aynı yerde (Livewire testi); ödeme kaydı 1 tık + müşteri + tutar + 1 tık.
- [x] **G-4.14 Ctrl+K arama ve komut kutusu** — Bağımlılık: G-4.13
  - Yapılacak: Kısa ad, ticari ünvan, ilgili kişi ve alan adıyla arama; fare kullanmadan müşteriye gitme ve ödeme kaydetme. Alan adı `details` JSON'unda durduğu için arama yöntemi planda gösterilir.
  - Kabul: "logi.com.tr" yazınca Logi bulunur (testli); Ctrl+K + yazma + 1 tık.
- [x] **G-4.15 Bugün sayfası (G-5.4'ten taşındı)** — Bağımlılık: G-4.13
  - Yapılacak: Açılış sayfası `docs/UI.md` §5'e göre yapılır. Üst satırda dört sayı: para birimi başına toplam alacak, gecikmiş tutar ve adet, bu ay vadesi gelen, 30 gün içinde yenilenecek hizmet sayısı; her sayı süzülmüş listeye gider. Altında öncelik sırasıyla Yapılacaklar: gecikmiş borçlar (en eski üstte, kaç gün geciktiğiyle), 7 gün içinde vadesi gelenler, ücretsiz devam kararı bekleyenler. Her satırda doğrudan iş düğmesi: "Ödeme al", "Ücretsiz devam et", "Ücretli yap". Liste boşsa "Bugün yapılacak bir şey yok". "Ücretsiz devam et" kararını tutacak bir alan veri modelinde yok; planda kullanıcıya sorulur.
  - Kabul: Örnek veriyle sayılar ve tutarlar doğru; gecikmiş borcun ödemesi 1 tık + tutar + 1 tık (testli).
- [x] **G-4.16 Müşteri listesi ve kartı** — Bağımlılık: G-4.13
  - Yapılacak: Liste sütunları: kısa ad (kalın) + ünvan (soluk), açık borç (gecikmişse kırmızı), en yakın yenileme, aktif hizmet sayısı; satırda "Ödeme al" kısayolu; varsayılan sıralama gecikmiş borçlular üstte. Müşteri kartı iki sütun olur: başlık şeridinde açık borç, alacak bakiyesi, "Ödeme al" ve "Hizmet ekle"; sol sütunda hizmetler ("İptal edilenler (n)" katlanmış), açık borçlar, son 5 ödeme; sağ sütunda kişiler, fatura bilgileri, notlar; Arşivle ve Sil en altta, ayrı.
  - Kabul: Karttan hizmet ekleme 1 tık + seçim + 1 tık; Logi örneği kartta doğru görünür (testli).
- [x] **G-4.17 Tablo, form ve eylem kuralları** — Bağımlılık: G-4.16
  - Yapılacak: Tüm listelerde ve formlarda: "⋮" menüleri kalkar, satırda en fazla iki metinli işlem kalır, nadir işlemler kartta adlandırılmış bölüme gider; Sil ve İptal et ayrı, kırmızı ve onaylı; formlar yandan açılan panelde açılır, Kaydet sağ altta, Vazgeç solunda; hata mesajı alanın altında ve düzeltmeyi söyler; id görünmez, ilk sütun tanıdık bilgidir; varsayılan filtre görünür çip olarak durur; boş tablo iş düğmesini gösterir; tutarlar sağa hizalıdır. İş kuralları ve Action'lar değişmez.
  - Kabul: Kurallar tüm listelerde tutarlı (testli).
- [x] **G-4.18 Tık sayısı doğrulaması ve onay** — Bağımlılık: G-4.14, G-4.15, G-4.17
  - Yapılacak: `docs/UI.md` §7'deki her akışın tık sayısı kontrol edilir.
  - Kabul: Günlük akışlar hedef tık sayılarında; mevcut testler yeşil; kullanıcı onayı.

**Faz 4 bittiğinde:** Sistem elle kullanılabilir durumda; mevcut müşterilerini ve hizmetlerini girmeye başlayabilirsin.

## Faz 4B – Görsel tasarım (yalnızca stil)

Hedef: Faz 4'te oturan yerleşim ve akışlar aynen kalırken panelin görünümünün elden geçirilmesi. Bu fazda yalnızca stil değişir: tema CSS'i, renk, yazı, boşluk, köşe, kenarlık, gölge. Sayfa yerleşimi, sütunlar, düğme yerleri, metinler, iş kuralları ve Action'lar değişmez; iş mantığı hiç değişmez. Ölçüt: panel tasarım tuvalindeki C artboard'larıyla birebir aynı görünür. Önce CSS ve Filament ayarlarıyla yaklaşılır; CSS yetmezse yalnızca o bileşenin Blade görünümü geçersiz kılınır (işaretleme ve stil sınıfı; Action'lar, sorgular ve davranış aynı kalır).

- [x] **K-4B.1 Tasarım geri bildirimi ve referanslar:** Görünüşünü beğenmediğin ekranların görüntüsünü kısa notla topla (örn. "her şey aynı gri", "kartlar sönük", "tablo sıkışık"). Görünüşünü beğendiğin 2–3 uygulamadan ekran görüntüsü ekle.
- [x] **G-4B.1 Tasarım yönü önerisi** — Bağımlılık: K-4B.1
  - Yapılacak: Tasarım yönleri Claude tuvalinde hazırlandı: [Müşteri Takip — Tasarım yönleri](https://claude.ai/artifact/Ev7Y2ZP2hbn3jhv47Q7UP6). Seçilen yön **C · Lacivert çerçeve**: koyu lacivert kenar menü, açık içerik, iri ve kalın başlık ve tutarlar, gölgesiz ve kenarlıkla ayrılan paneller, noktalı durum rozetleri. Seçim G-4B.2'de DECISIONS'a yazılır; uygulama koduna dokunulmaz. Birebir referans için C yönünün altı ekranı açık ve koyu temada tuvale eklendi ve onaylandı: Bugün, Müşteriler, müşteri kartı, Açık borçlar, ödeme paneli, hizmet ekleme formu. Tuval panelin tek görsel referansıdır.
  - Kabul: Kullanıcı C yönünü seçti; tuvaldeki tüm ekranlar açık ve koyu temada hazır ve kullanıcı onaylı.
- [x] **K-4B.2 Referans ekranların repoya alınması** — Bağımlılık: G-4B.1
  - Yapılacak: Tuvaldeki C artboard'larını (6 ekran × açık/koyu = 12) tuvalin dışa aktarma menüsünden PNG olarak indir; `acik-bugun.png`, `koyu-bugun.png`, `acik-musteriler.png`, `koyu-musteriler.png`, `acik-kart.png`, `koyu-kart.png`, `acik-borclar.png`, `koyu-borclar.png`, `acik-odeme.png`, `koyu-odeme.png`, `acik-hizmet.png`, `koyu-hizmet.png` adlarıyla repoda `docs/design/referans/` altına koy ve commit et. Claude Code tuvale erişemez.
  - Kabul: 12 dosya repoda. G-4B.3–G-4B.6'daki "tuvaldeki C artboard'ları" ifadesi bu dosyalardır.
- [ ] **G-4B.2 Tasarım tokenları** — Bağımlılık: G-4B.1, K-4B.2
  - Yapılacak: Seçilen yön değerleriyle `docs/UI.md` §6'ya eklenir: nötr gri skalası, primary lacivert skalası, durum renklerinin tonları (açık ve koyu tema), yazı ölçeği (sayfa başlığı, bölüm başlığı, gövde, tablo 14 px, yardımcı metin), boşluk ölçeği, köşe yarıçapı, kenarlık, gölge, odak halkası. Değerler Arayüz ilkeleri §6'daki C yönü token tablosundan alınır ve Filament temasında tek yerde tanımlanır; bileşen stillerinde sabit renk kodu yok. DECISIONS.md'ye tek satır eklenir: "Faz 4B: görsel yön C · Lacivert çerçeve seçildi; referans docs/design/referans/ (tuval: https://claude.ai/artifact/Ev7Y2ZP2hbn3jhv47Q7UP6)."
  - Kabul: Metin kontrastı açık ve koyu temada en az 4,5:1; tema CI'da derleniyor; DECISIONS'a seçim satırı eklenmiş; testler yeşil.
- [ ] **G-4B.3 Çerçeve: menü, üst çubuk, sayfa başlığı** — Bağımlılık: G-4B.2
  - Yapılacak: Kenar menü (arka plan, grup başlıkları, seçili öğede marka çizgisi), üst çubuk (arama kutusu, "Ödeme al", "Yeni"), logo alanı ve sayfa başlıkları tokenlara göre stillenir.
  - Kabul: Tüm sayfalarda çerçeve aynı görünür ve tuvaldeki C artboard'larıyla yan yana karşılaştırıldığında fark yok; testler yeşil.
- [ ] **G-4B.4 Tablolar, rozetler ve filtre çipleri** — Bağımlılık: G-4B.2
  - Yapılacak: Başlık satırı, satır yüksekliği, ayırıcı çizgi ve üzerine gelme rengi, tutar sütunları, durum rozetleri, filtre çipleri, boş tablo görünümü.
  - Kabul: Durum rozetleri UI.md §6'daki renk anlamlarına uyar; tüm listeler aynı görünür ve tuvaldeki C artboard'larıyla yan yana karşılaştırıldığında fark yok; testler yeşil.
- [ ] **G-4B.5 Kartlar, formlar ve yan panel** — Bağımlılık: G-4B.2
  - Yapılacak: Bugün sayı kartları, müşteri kartı başlık şeridi (büyük tutarlar) ve bölüm kartları; form alanları, odak ve hata görünümü; yan panel; düğme hiyerarşisi (birincil, ikincil, tehlikeli); bildirimler.
  - Kabul: Her ekranda tek birincil düğme görsel olarak belirgin; tehlikeli düğmeler ayırt edilir; kartlar, formlar ve yan panel tuvaldeki C artboard'larıyla yan yana karşılaştırıldığında fark yok; testler yeşil.
- [ ] **G-4B.6 Koyu tema ve son kontrol** — Bağımlılık: G-4B.3, G-4B.4, G-4B.5
  - Yapılacak: Tüm ekranlar koyu temada gözden geçirilir. Bugün, Müşteriler, müşteri kartı, Açık borçlar, ödeme paneli ve hizmet ekleme formunun açık/koyu ekran görüntüleri `docs/design/ekranlar/` altına alınır.
  - Kabul: Yerleşim ve tık sayıları (UI.md §7) değişmemiş; her ekran açık ve koyu temada tuvaldeki C artboard'larıyla yan yana karşılaştırılmış, kalan fark yok ya da kullanıcı tarafından kabul edilmiş; tüm testler yeşil; kullanıcı onayı.

**Faz 4B bittiğinde:** Panel aynı akışlarla, tutarlı ve bilinçli seçilmiş bir görsel dille çalışıyor; stil değerleri tek yerde ve belgelenmiş.

## Faz 5 – Yayına alma, panel ve MVP kapanışı

Hedef: Sistemin hostingde çalışması, kullanıcıya ne yapması gerektiğini kendiliğinden söylemesi ve güvenle canlıya alınması. Yayına alma fazın başında kurulur ki panel ve özet e-postası canlıda denenebilsin.

- [ ] **G-5.1 Yayın uç noktası ve cron yedeği** — Bağımlılık: Faz 4B
  - Yapılacak: Gizli anahtarla korunan yayın adresi; migration, önbellek yenileme ve gerekirse storage bağlantısını çalıştırır. Aynı kontrol zamanlayıcıda "yeni sürüm varsa bir kez yap" görevi olarak çalışır.
  - Kabul: Yanlış veya eksik anahtar reddedilir; doğru anahtarla komutlar bir kez çalışır; aynı sürüm için tekrar çalışmaz (hepsi testli).
- [ ] **G-5.2 Otomatik yayına alma** — Bağımlılık: G-1.5, G-5.1
  - Yapılacak: `main` dalına her güncellemede testler geçerse `vendor` ve arayüz dosyalarıyla paket hazırlanır, FTPS ile yüklenir, ardından yayın adresi çağrılır. `.env` ve `storage` içeriği asla üzerine yazılmaz. Paket, CI'da derlenen tema çıktısını (public/build) içermeli. Filament'in `public/` altındaki dosyaları git'te değil, `composer install` sırasında üretilir; paket hazırlanırken Composer betikleri kapatılmamalı ve paketin Filament dosyalarını içerdiği kontrol edilmeli.
  - Kabul: Gerekli GitHub secret'ları belgelenmiş; test başarısızsa yükleme yapılmaz.
- [ ] **G-5.3 Kurulum rehberi** — Bağımlılık: G-5.2
  - Yapılacak: `docs/KURULUM.md`: cPanel'de PHP 8.3, veritabanı, kök dizini `public`'e yönlendirme, `.env`, \*/15 cron, e-posta hesabı, SPF/DKIM kontrolü, GitHub secret'ları.
  - Kabul: Rehber ekran adımlarıyla, SSH gerektirmeden takip edilebilir.
- [ ] **K-5.1 Hosting kurulumu ve ilk yayın:** Rehberi uygula, GitHub secret'larını gir, ilk yayını başlat ve panele canlıda giriş yap.
- [x] **G-5.4 Ana panel** — G-4.15'e (Bugün sayfası) taşındı; numara boşluk kalmasın diye durur.
  - Yapılacak: Yok; iş G-4.15'te yapılır.
  - Kabul: G-4.15'in kabul ölçütleri.
- [ ] **G-5.5 Günlük özet e-postası** — Bağımlılık: G-4.15
  - Yapılacak: Türkçe, sade e-posta; bölümler: bugün oluşan borçlar (bakiyeden kapatılanlar belirtilir), ücretsiz devam kararı bekleyenler, vadesi geçmiş borçlar; her satırda bağlantı. Ayarlanan saatten sonraki ilk zamanlayıcı çalışmasında günde bir kez gönderilir; tüm bölümler boşsa gönderilmez. Bakiyeden kapatılanlar, dağıtımın kaynağı alanıyla ("alacak bakiyesi") bulunur (G-3.2).
  - Kabul: Günde tek gönderim, boş günde gönderilmeme, saatten önce gönderilmeme, atlanan cron sonrası gönderim (testli); Mailpit'te görünüm kontrol edilir.
- [ ] **G-5.6 Zamanlayıcı sağlık kontrolü** — Bağımlılık: G-4.15
  - Yapılacak: Zamanlayıcının son çalışma zamanı kaydedilir; 1 saatten uzun süredir çalışmadıysa Bugün sayfasında uyarı görünür. Son çalışmada hata veren hizmet varsa (G-3.9'daki hata yalıtımı) Bugün sayfasında bu da uyarı olarak gösterilir.
  - Kabul: Süre aşılınca uyarı görünür, çalışınca kaybolur (testli).
- [ ] **G-5.7 MVP son kontrol** — Bağımlılık: G-5.1…G-5.6
  - Yapılacak: Ana akışların ekran üzerinden uçtan uca testi (müşteri ekle → hizmet ata → ödeme al → yenileme → iptal); birkaç yüz müşterilik örnek veriyle listelerin hız kontrolü; tüm dokümanların güncelliği.
  - Kabul: Tüm testler yeşil; ROADMAP ve DECISIONS güncel.
- [ ] **G-5.8 Canlıya geçiş kontrol listesi** — Bağımlılık: G-5.7
  - Yapılacak: `docs/CANLIYA_GECIS.md`: canlı ortam ayarları (hata ayrıntıları kapalı, HTTPS), test e-postası, ayarların doldurulması, ilk girişten sonra panelden şifrenin değiştirilmesi ve hostingdeki .env'den ADMIN\_PASSWORD satırının silinmesi, örnek verinin canlıda olmadığının kontrolü, canlı ortamın APP\_KEY değerinin güvenli bir yerde yedeklenmesi (değişir veya kaybolursa şifreli lisans anahtarları okunamaz).
  - Kabul: Liste ekran adımlarıyla takip edilebilir.
- [ ] **K-5.2 Yedek kontrolü:** Hosting panelinde veritabanı yedeklerinin günlük alındığını ve bir yedeğin geri yüklenebildiğini kontrol et.
- [ ] **K-5.3 Canlıya geçiş ve veri girişi:** Kontrol listesini uygula, mevcut müşterileri ve hizmetleri "Bu dönemin borcu: Oluşturma" ile gir.
- [ ] **K-5.4 Paralel kullanım:** 2–4 hafta boyunca Excel'i bırakmadan sistemle birlikte kullan; günlük özetin ve borçların Excel'le tutarlı olduğunu görünce Excel'i bırak.

**Faz 5 bittiğinde:** MVP tamam; sistem günlük işini Excel'in yerine yürütüyor.

## Faz 6 ve sonrası

Teklifler ve sonraki fazların görevleri, MVP bitip sistem bir süre kullanıldıktan sonra yazılacak. Böylece gerçek kullanımdan çıkan ihtiyaçlar planlamaya dahil edilir.
