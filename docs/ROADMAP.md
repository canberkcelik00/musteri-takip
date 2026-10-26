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
  - Yapılacak: `payment_methods` (varsayılan: Havale/EFT, Nakit, Kredi Kartı; Nakit G-5B.3 ile kaldırıldı); `settings` tablosu ve varsayılan değerleri döndüren ayar servisi.
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
  - Yapılacak: Tutar ve vade değiştirme, borcu ikiye bölme, gerekçeyle iptal, kurala bağlı olmayan elle borç ekleme (bakiye uygulanır; elle borç ekleme G-5B.3 ile kaldırıldı).
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
  - Yapılacak: Borç listesi (açık, gecikmiş, para birimi filtreleri), elle borç ekleme (G-5B.3 ile kaldırıldı), düzenleme, bölme, gerekçeli iptal.
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
  - Yapılacak: Menü `docs/UI.md` §3'e göre kurulur: Bugün, Müşteriler, Tahsilat (Açık borçlar, Ödemeler), Katalog (Hizmetler, Paketler; kapalı başlar), Ayarlar (Genel, Ödeme yöntemleri; en altta). Üst çubuk her sayfada aynı yerde: solda arama kutusu, sağda "Ödeme al" birincil düğmesi ve "Yeni" menüsü (Müşteri, Elle borç; G-5B.3 ile "Yeni müşteri" düğmesi oldu). Ödeme formu (G-4.7) her sayfada yandan açılan panelde çalışır.
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
- [x] **G-4B.2 Tasarım tokenları** — Bağımlılık: G-4B.1, K-4B.2
  - Yapılacak: Seçilen yön değerleriyle `docs/UI.md` §6'ya eklenir: nötr gri skalası, primary lacivert skalası, durum renklerinin tonları (açık ve koyu tema), yazı ölçeği (sayfa başlığı, bölüm başlığı, gövde, tablo 14 px, yardımcı metin), boşluk ölçeği, köşe yarıçapı, kenarlık, gölge, odak halkası. Değerler Arayüz ilkeleri §6'daki C yönü token tablosundan alınır ve Filament temasında tek yerde tanımlanır; bileşen stillerinde sabit renk kodu yok. DECISIONS.md'ye tek satır eklenir: "Faz 4B: görsel yön C · Lacivert çerçeve seçildi; referans docs/design/referans/ (tuval: https://claude.ai/artifact/Ev7Y2ZP2hbn3jhv47Q7UP6)."
  - Kabul: Metin kontrastı açık ve koyu temada en az 4,5:1; tema CI'da derleniyor; DECISIONS'a seçim satırı eklenmiş; testler yeşil.
- [x] **G-4B.3 Çerçeve: menü, üst çubuk, sayfa başlığı** — Bağımlılık: G-4B.2
  - Yapılacak: Kenar menü (arka plan, grup başlıkları, seçili öğede marka çizgisi), üst çubuk (arama kutusu, "Ödeme al", "Yeni"), logo alanı ve sayfa başlıkları tokenlara göre stillenir.
  - Kabul: Tüm sayfalarda çerçeve aynı görünür ve tuvaldeki C artboard'larıyla yan yana karşılaştırıldığında fark yok; testler yeşil.
- [x] **G-4B.4 Tablolar, rozetler ve filtre çipleri** — Bağımlılık: G-4B.2
  - Yapılacak: Başlık satırı, satır yüksekliği, ayırıcı çizgi ve üzerine gelme rengi, tutar sütunları, durum rozetleri, filtre çipleri, boş tablo görünümü.
  - Kabul: Durum rozetleri UI.md §6'daki renk anlamlarına uyar; tüm listeler aynı görünür ve tuvaldeki C artboard'larıyla yan yana karşılaştırıldığında fark yok; testler yeşil.
  - Kapanış notu (T-80): Referansa uymak için yerleşim istisnası yapıldı (Açık borçlar'da Durum sütunu ve Toplam satırı, Müşteriler'de sayaç, Bugün'de tek tablo; kullanıcı kararı). G-4B.6'da karşılaştırılacak kalan farklar: "Karar bekliyor" referansta gri rozet, uygulamada düz metin (UI.md §6); satır işlem düğmeleri bağlantı görünümünde (G-4B.5); arama yer tutucusu "Ara" (referansta "Kısa ad veya ünvanla ara") ve sütun başlığı "Açıklama" (referansta "Borç"), metinler Faz 4B'de değişmediği için aynen kaldı; tutarlar ",00" göstermiyor (`Money::format` biçimi); filtre ikonu düğmesi ve Müşteriler'de "Durum" (Arşivlendi) sütunu referansta yok.
- [x] **G-4B.5 Kartlar, formlar ve yan panel** — Bağımlılık: G-4B.2
  - Yapılacak: Bugün sayı kartları, müşteri kartı başlık şeridi (büyük tutarlar) ve bölüm kartları; form alanları, odak ve hata görünümü; yan panel; düğme hiyerarşisi (birincil, ikincil, tehlikeli); bildirimler.
  - Kabul: Her ekranda tek birincil düğme görsel olarak belirgin; tehlikeli düğmeler ayırt edilir; kartlar, formlar ve yan panel tuvaldeki C artboard'larıyla yan yana karşılaştırıldığında fark yok; testler yeşil.
  - G-4B.4'ten devredilenler (T-80): müşteri kartındaki "Hizmetler" ve "Açık borçlar" tablolarında Durum sütunu, "3 aktif" sayacı ve T-70'teki 1280 px taşma sorunu (kartın tabloları G-4B.4'te yalnız stil aldı, yerleşimleri aynen kaldı); tablo satırlarındaki ve Bugün'deki işlem düğmelerinin görünümü (düğme hiyerarşisi: tek birincil, ikincil, tehlikeli).
  - Kapanış notu (T-81): Kart başlığı tek şerit kartı oldu; kartta Açık borçlar satırında yalnız "Ödeme al" kaldı ("Düzenle" açıklamaya tıklamayla açılır), Hizmetler başlığına "N aktif" sayacı geldi ve satırdaki "Düzenle" kalktı; iki tabloda da Durum (ve borçlarda Vade) `xl`'den sütun (kullanıcı kararı; karta özgü kural). Koyu temada primary hover #9DB6E2; tehlikeli düğme onay penceresi dahil her yerde çizgili. G-4B.6'da karşılaştırılacak kalan farklar: Bugün sayı kartlarındaki metinler ("N borç", "+ 1.200,00 USD", "En yakını …", "Yenilemesi yaklaşan müşteri" / "N hizmet"); kartta "/ total" yerine referanstaki "TL'den" yazımı; kartta "Borç ekle" ve "Kişi ekle" düğmeleri ile "İlgili kişiler" başlığı (referansta "Kişiler · Düzenle"); tutarlarda ",00"; Hizmetler'de alan adı ayrı sütun değil adın altında. Açık madde: 1024 px'te kartın "Son ödemeler" (30–38 px) ve "İlgili kişiler" (19–22 px) tabloları kayıyor (G-4B.5'ten önce de kayıyordu, ölçüm T-81); G-4B.6'nın kabul kriteri olarak devredildi.
- [x] **G-4B.6 Koyu tema ve son kontrol** — Bağımlılık: G-4B.3, G-4B.4, G-4B.5, G-4B.7, G-4B.8
  - Açık madde (G-4B.5'ten devredilmişti): 1024 px'te müşteri kartının "Son ödemeler" ve "İlgili kişiler" tabloları kartın içinde yatay kayıyordu; kullanıcı kararıyla G-4B.7'ye taşındı ve orada giderildi (T-85). Kartın tablolarının 1024 px dahil 375–1440 px'te kaymaması G-4B.6'nın kabul ölçütünde de durur.
  - Kabul edilen farklar (kullanıcı onaylı, G-4B.4): Açık borçlar'ın Toplam satırı referanstaki gibi tablonun sağ kenarında değil, Kalan sütununun altındadır (375 px'te Kalan'la hizalı kalması için).
  - Yapılacak: Tüm ekranlar koyu temada gözden geçirilir. Bugün, Müşteriler, müşteri kartı, Açık borçlar, ödeme paneli ve hizmet ekleme formunun açık/koyu ekran görüntüleri `docs/design/ekranlar/` altına alınır.
  - G-4B.5'ten devreden: Bugün sayı kartı metinleri ve kartta "/ toplam" yerine "TL'den" yazımı referansla eşitlenir.
  - [x] Tehlikeli düğme hover'ı (G-4B.5'ten devredildi, T-83): üzerine gelince zemin `--mt-badge-red-bg`, kenarlık ve metin `--mt-danger-text` (açık ve koyu); kullanıcı onayıyla uygulandı, UI.md §6'ya yazıldı, ölçümler DECISIONS T-83'te.
  - Kabul: Müşteri kartının hiçbir tablosu 1024 px dahil 375–1440 px'te kartın içinde yatay kaymaz (ölçülerek); yerleşim ve tık sayıları (UI.md §7) değişmemiş; her ekran açık ve koyu temada tuvaldeki C artboard'larıyla yan yana karşılaştırılmış, kalan fark yok ya da kullanıcı tarafından kabul edilmiş; tüm testler yeşil; kullanıcı onayı.
  - Kapanış notu (T-86): 6 ekran × 2 tema referansla karşılaştırıldı, son hâlin görüntüleri `docs/design/ekranlar/` altında. Koyu temada açık zemin kalan yüzey yok; renkler UI.md §6 ile aynı. Kayma regresyonu 375–1440 px'te iki temada 0. Düzeltmeler: Toplam satırının gri zemini, Bugün'de iki düğmenin `xl`'den yan yana durması (`md`'de tablo taşıyordu), aktif hizmet sayısının sağa hizalanması, liste sayfalarında yol satırının gizlenmesi, "+" ve "⋮" simgelerinin kaldırılması. Metin eşitlemesi: Bugün sayı kartları (yenileme kartı artık hizmet sayar, bağlantı müşteri listesine gider) ve kartta "TL'den". Kalan farklar kullanıcı tarafından kabul edildi, liste T-86'da.
- [x] **G-4B.7 Dar ekran düzeni: listelerde ve müşteri kartı tablolarında yatay kayma olmaması, dar ekranda arama** — Bağımlılık: G-4B.4
  - Not: Kapsam kullanıcı kararıyla genişletildi (ilk metin yalnız Müşteriler ve Açık borçlar'dı; T-80 yalnız Bugün'ün tablosunu düzeltmişti, öteki listeler önceden de kayıyordu). G-4B.6'dan öne alınan "1024 px'te kartın Son ödemeler ve İlgili kişiler tabloları" maddesi de buraya taşındı. Sayfanın kendisi hiçbir genişlikte kaymıyordu; kayan, tablonun kart içindeki alanıydı.
  - Yapılacak: (1) Bugün, Müşteriler, Açık borçlar (Borçlar), Ödemeler, Katalog hizmetleri ve Paketler listeleri ile müşteri kartının tüm tabloları 1440, 1280, 1024, 768 ve 375 px'te kaymayacak şekilde düzeltilir: önce sarma, gerekirse `visibleFrom`; iş mantığı ve Action'lar değişmez. (2) Açık borçlar'da `md` altında Müşteri sütunu önde, açıklama ve vade altında soluk satırlar; Toplam satırı Kalan'la hizalı kalır. (3) Dar ekranda (`sm` altı) üst çubuk aramasının yerine metinli "Ara" düğmesi (UI.md §2 Kural 5), `aria-controls` ve `aria-expanded` ile; 320 ve 375 px'te ölçülür. (4) 1280 px görünümü değişmez (önce/sonra karşılaştırması; katalog adının bağlantı görünümü beklenen fark).
  - Kabul: Her liste ve kart tablosu 375–1440 px'te kartın içinde yatay kaymaz (ölçülerek); 320 px'te sayfa kaymaz ve tablo ölçümü DECISIONS'a yazılır; 1280 px'te yalnız beklenen fark vardır; arama 320 ve 375 px'te kullanılabilir; Toplam satırı Kalan'la hizalı; UI.md §7 tık sayıları değişmez (mobilde borç düzenleme için kullanıcı kararıyla 3 tık, UI.md §4); testler yeşil.
  - Kapanış notu (T-85): Düzen kararları, önce/sonra ölçümleri ve 1280 px karşılaştırması T-85'te. 375–1440 px'te her sayfada ve kartın her tablosunda kayma 0. 320 px'te kalan tablo taşmaları (kabul ölçütünde tablo için sıfır şartı yok): Bugün +7, Borçlar +36, Ödemeler +31, Katalog +1, Paketler +47, kartın Açık borçlar'ı +30; hepsi hücre yan dolgusunu `md` altında 10 px yapmakla kapanır, kullanıcı bu genel değişikliği seçmedi (G-4B.6'da ya da sonra karar verilebilir).
- [x] **G-4B.8 Formlar ortada pencere + katalog formu düzeltmesi** — Bağımlılık: G-4B.5
  - Not: G-4B.6'dan önce yapılır (kullanıcı kararı); G-4B.6 buna bağlıdır.
  - Yapılacak: (1) Katalog Hizmeti formu okunmuyor ("Tür" kesiliyor, "Hizmet adı" iki satır, fiyat girişi görünmüyor): kök neden bulunur, tüm formlar aynı sorun için taranır, düzeltme tek yerde yapılır. (2) Tüm formlar yan panel yerine ortada açılan pencerede açılır (kullanıcı kararı): slide-over ve global ayarı kalkar, ortak genişlik `--mt-modal-width`, mobilde pencere ekrana sığar. Korunanlar: metinli "Kapat", başlık 20/700 + alt çizgi, alt çubukta Vazgeç · Kaydet sağda, pencerede tek birincil düğme, tık sayıları. (3) UI.md §2 "Bağlamı kapatma" kuralı ve ilgili ölçüler güncellenir, DECISIONS T-84; slide-over'a özgü CSS, token ve testler modal karşılıklarına çevrilir (karar değişikliği, test zayıflatılmaz). (4) Gerçek Chrome'da 1280/1024/768/375 px, açık ve koyu temada katalog, hizmet ekle ve ödeme formları; tek sütun ayarının etkisi için müşteri kartı, Bugün ve iki filtre panelinin 1280 px'te öncesi/sonrası karşılaştırması; 375 px'te klavye açıkken sabit alt çubuğun odaklı alanı örtmemesi.
  - Kabul: Katalog formu dört genişlikte okunur; hiçbir form slide-over değil; pencere 640 px, 375 px'te ekrana sığar, yatay kayma yok; korunanlar yerinde; `ClickCountTest` değişmeden yeşil; müşteri kartı ve Bugün'ün yerleşimi değişmemiş; 375 px'te odaklı alan sabit çubukların altında kalmaz; test, lint ve analiz yeşil.
  - Kapanış notu (T-84): Kök neden CSS değil, Filament'in liste sayfası formlarına verdiği iki sütunlu ızgaraydı; `Schema::configureUsing(->columns(1))` ile tek yerde düzeltildi. Filtre paneli de ortada pencere oldu, uzun formda başlık ve alt çubuk sabit; 384 px'ten kısa ekranda formla kayar (kullanıcı kararları). Ölçümler T-84'te.

**Faz 4B bittiğinde:** Panel aynı akışlarla, tutarlı ve bilinçli seçilmiş bir görsel dille çalışıyor; stil değerleri tek yerde ve belgelenmiş.

## Faz 4C – Apple tarzı tasarım

Hedef: Panelin görünümünü Faz 4B'nin lacivert çerçevesinden Apple tarzına taşımak (kullanıcı kararı, T-130). Faz 4B'deki "yalnızca stil" ilkesi burada yalnız şu yerleşim eklemeleri için gevşer: simge işlemleri (WhatsApp, ara, e-posta, siteyi aç, kopyala), müşteri avatarları, dar ekranda alt sekme çubuğu. Sütunlar, metinler, düğme yerleri, iş kuralları, Action'lar ve süzgeç davranışı değişmez. Referans: `docs/design/apple/referans.html`.

- [x] **G-4C.1 Kararlar ve belgeler** — Bağımlılık: G-4B.6
  - Yapılacak: UI.md §2 Kural 5, §3 (dar ekran), §6 (Apple paleti, menü kareleri, simge işlemleri, avatar, ölçüler) yazılır; referans sayfa repoya alınır; DECISIONS'a T-130.
  - Kabul: Belgeler birbiriyle çelişmiyor; kod değişmez.
- [x] **G-4C.2 Tokenlar** — Bağımlılık: G-4C.1
  - Yapılacak: `theme.css` token bloğu, `AdminPanelProvider` skalaları ve `DesignTokensTest` yeni değerlere.
  - Kabul: Metin çiftleri iki temada ≥ 4,5:1; sabit renk yalnız token bloğunda; tema derleniyor.
- [x] **G-4C.3 Çerçeve: menü, üst çubuk, başlık, alt sekme çubuğu** — Bağımlılık: G-4C.2
  - Kabul: Menü kareleri her öğede; `lg` altında alt sekme çubuğu her sayfada; testler yeşil.
- [x] **G-4C.4 Tablolar, rozetler, süzgeç çipleri** — Bağımlılık: G-4C.2
- [x] **G-4C.5 Kartlar, düğmeler, form penceresi** — Bağımlılık: G-4C.2
- [x] **G-4C.6 Simge işlemleri** — Bağımlılık: G-4C.2
  - Kabul: WhatsApp, ara, e-posta, siteyi aç, kopyala simgeli, ipuculu ve erişilebilir adlı; davranış aynı.
- [x] **G-4C.7 Müşteri avatarları** — Bağımlılık: G-4C.2
- [ ] **G-4C.8 Koyu tema ve son kontrol** — Bağımlılık: G-4C.3, G-4C.4, G-4C.5, G-4C.6, G-4C.7
  - Kabul: Altı ekran açık ve koyu temada 375–1440 px'te yatay kayma olmadan; tam paket yeşil.
  - Durum: otomatik denetimler yapıldı (kontrast, token, sekme çubuğu, avatar, simge testleri; tam paket). Açık kalan: gerçek tarayıcıda altı ekranın açık ve koyu temada 375–1440 px'te gözle kontrolü ve `docs/design/ekranlar/` görüntülerinin yenilenmesi; bu oturumda tarayıcı aracı yoktu, kullanıcı bakıp onaylayınca kutu işaretlenir.

**Faz 4C bittiğinde:** Panel Apple tarzında, aynı akışlarla çalışıyor; stil değerleri tek yerde ve belgelenmiş.

## Faz 5 – Yayına alma, panel ve MVP kapanışı

Hedef: Sistemin hostingde çalışması, kullanıcıya ne yapması gerektiğini kendiliğinden söylemesi ve güvenle canlıya alınması. Yayına alma fazın başında kurulur ki panel ve özet e-postası canlıda denenebilsin.

- [x] **G-5.1 Yayın uç noktası ve cron yedeği** — Bağımlılık: Faz 4B
  - Yapılacak: Gizli anahtarla korunan yayın adresi; migration, önbellek yenileme ve gerekirse storage bağlantısını çalıştırır. Aynı kontrol zamanlayıcıda "yeni sürüm varsa bir kez yap" görevi olarak çalışır.
  - Kabul: Yanlış veya eksik anahtar reddedilir; doğru anahtarla komutlar bir kez çalışır; aynı sürüm için tekrar çalışmaz (hepsi testli).
  - Kapanış notu (T-87): Sürüm paketteki `RELEASE` dosyasıyla anlaşılır; `storage:link` çalıştırılmaz (kullanıcı kararı). Durum kodları: 200 yayınlandı veya güncel, 403 anahtar hatalı, 409 meşgul, 422 `RELEASE` yok, 500 hata (yalnız genel mesaj). G-5.2 için notlar T-87'de.
- [x] **G-5.2 Otomatik yayına alma** — Bağımlılık: G-1.5, G-5.1
  - Yapılacak: `main` dalına her push'ta testler geçerse CI canlı paketi hazırlar (`composer install --no-dev`, Composer betikleri açık, `npm run build`, Filament `public/` dosyaları) ve yalnız paket değiştiyse `main`'e commit eder. Hosting `public_html`'de cPanel Git ile `main`'i izler. Yayın: cPanel "Uzaktan Güncelle", ardından `/deploy?key=…`. Yayın kimliği sunucudaki git commit'idir. `.env` ve `storage` asla üzerine yazılmaz.
  - Kabul: Test başarısızsa paket commit edilmez; paket değişmediyse commit atılmaz; yayın adımları README ve `docs/KURULUM.md`'de yazılı.
  - Kapanış notu (T-88): Yükleme FTP-Deploy-Action (SHA'ya sabit) + lftp (eski önbellekleri silme, en son `RELEASE`); secret'lar README "Yayına alma" bölümünde; hiçbiri girilmemişse yayın uyarıyla atlanır. Gerçek yükleme K-5.1'de secret'lar girilince denenecek.
  - Düzeltme (T-90): Canlıda FTP yüklemesi bağlantı kesintileriyle tamamlanamadı; paket artık `production` dalına yazılır, hosting cPanel Git ile çeker (`.cpanel.yml` + cron yedeği). Secret gerekmez.
  - Düzeltme (T-91): Ayrı dal, `.cpanel.yml` ve yayın cron'u kalktı. CI paketi (`vendor`, derlenmiş dosyalar, `RELEASE`) `main`'e `[skip ci]` commit'i olarak ekler; hosting `main`'i çeker, yayın `/deploy?key=…` adresi tarayıcıda açılınca tamamlanır. Eski önbellekleri `ClearStaleBootstrapCache` Laravel açılmadan siler.
  - Düzeltme (T-93): Paketi yerelde `./yayin.sh` hazırlar (testler, `vendor --no-dev` geçici konteynerde, tema, `RELEASE`) ve `main`'e gönderir; CI yalnız test çalıştırır, bot commit'i yok.
  - Düzeltme (T-94): Paketi yine CI hazırlar ama yalnız değiştiyse `main`'e commit eder (mesaj neyin değiştiğini ve kaynak commit'i söyler); `RELEASE` ve `yayin.sh` kalktı, yayın kimliği sunucudaki git commit'i.
- [x] **G-5.3 Kurulum rehberi** — Bağımlılık: G-5.2
  - Yapılacak: `docs/KURULUM.md`: cPanel'de PHP 8.3, veritabanı, kök dizini `public`'e yönlendirme, `.env`, \*/15 cron, e-posta hesabı, SPF/DKIM kontrolü, GitHub secret'ları.
  - Kabul: Rehber ekran adımlarıyla, SSH gerektirmeden takip edilebilir.
  - Kapanış notu (T-89): Rehber 14 adımdır (Türkçe cPanel menü adları); cron ilk yayından önce kurulur; mevcut alt alan adı, proje `public_html` içinde (`.htaccess` ile `public/` yönlendirmesi) ve `FTP_SERVER_DIR=/` (README secret örnekleri buna göre güncellendi). Test eklenmedi (kullanıcı kararı); gerçek uygulama K-5.1'de.
  - Güncelleme (T-90): Rehber yeni yayın düzenine göre 13 adım oldu: FTP hesabı ve secret adımları kalktı; depo cPanel Git ile `public_html`'e klonlanır, ilk yayında `production` dalına geçilir.
  - Güncelleme (T-91): Hosting `main`'i izler; `DEPLOY_KEY` geri geldi; cron yalnız `schedule:run`; yayın adımları "Uzaktan Güncelle → yayın adresini aç".
  - Güncelleme (T-93): Yayın adımları "`./yayin.sh` → Uzaktan Güncelle → yayın adresini aç".
  - Güncelleme (T-94): Yayın adımları "push → CI yeşil → Uzaktan Güncelle → yayın adresini aç".
- [x] **K-5.1 Hosting kurulumu ve ilk yayın:** Rehberi uygula, hostingi `main` dalına geçir, yayın adresini aç ve panele canlıda giriş yap.
  - Kapanış notu: 2026-10-12 canlıda tamamlandı (T-94 düzeni). Yolda çıkan ve düzeltilen hatalar: ilk yayında olmayan `cache` tablosu (T-91), hostingin MyISAM varsayılanı (T-92). `production` dalı, eski secret'lar ve FTP hesabı silindi; `/.env` ve `/vendor/autoload.php` web'den erişilemiyor.
- [x] **G-5.4 Ana panel** — G-4.15'e (Bugün sayfası) taşındı; numara boşluk kalmasın diye durur.
  - Yapılacak: Yok; iş G-4.15'te yapılır.
  - Kabul: G-4.15'in kabul ölçütleri.
- [x] **G-5.5 Günlük özet e-postası** — Bağımlılık: G-4.15
  - Yapılacak: Türkçe, sade e-posta; bölümler: bugün oluşan borçlar (bakiyeden kapatılanlar belirtilir), ücretsiz devam kararı bekleyenler, vadesi geçmiş borçlar; her satırda bağlantı. Ayarlanan saatten sonraki ilk zamanlayıcı çalışmasında günde bir kez gönderilir; tüm bölümler boşsa gönderilmez. Bakiyeden kapatılanlar, dağıtımın kaynağı alanıyla ("alacak bakiyesi") bulunur (G-3.2).
  - Kabul: Günde tek gönderim, boş günde gönderilmeme, saatten önce gönderilmeme, atlanan cron sonrası gönderim (testli); Mailpit'te görünüm kontrol edilir.
  - Kapanış notu (T-95): Kapsam kullanıcı kararıyla genişledi: özet seçilebilir sıklıkta gider (Günlük / Aylık, varsayılan aylık, ayın 15'i 09:00; gün 1–28). "Bugün oluşan borçlar" yerine "Son özetten bu yana oluşan borçlar"; boş dönem gönderilmez ama kapanır; gönderim hatasında sonraki çalışma yeniden dener; ilk çalışma yalnız başlangıç anını kaydeder. Kabul ölçütleri hem günlük hem aylık için testli.
- [x] **G-5.6 Zamanlayıcı sağlık kontrolü** — Bağımlılık: G-4.15
  - Yapılacak: Zamanlayıcının son çalışma zamanı kaydedilir; 1 saatten uzun süredir çalışmadıysa Bugün sayfasında uyarı görünür. Son çalışmada hata veren hizmet varsa (G-3.9'daki hata yalıtımı) Bugün sayfasında bu da uyarı olarak gösterilir.
  - Kabul: Süre aşılınca uyarı görünür, çalışınca kaybolur (testli).
  - Kapanış notu (T-98): Kayıt `app:process-customer-services` sonunda ayarlara yazılır. Kullanıcı kararları: hiç çalışmamışsa da uyarı görünür; bekleyen yayın yüzünden atlanıyorsa ayrı metin ("Yayın adresini açın"); hata veren hizmetler en çok 5 adla, müşteri kartına bağlantılı. Yayından sonra ilk cron çalışmasına kadar (en çok 15 dk) 'hiç çalışmadı' uyarısı görünebilir.
- [x] **G-5.7 MVP son kontrol** — Bağımlılık: G-5.1…G-5.6
  - Yapılacak: Ana akışların ekran üzerinden uçtan uca testi (müşteri ekle → hizmet ata → ödeme al → yenileme → iptal); birkaç yüz müşterilik örnek veriyle listelerin hız kontrolü; tüm dokümanların güncelliği.
  - Kabul: Tüm testler yeşil; ROADMAP ve DECISIONS güncel.
  - Kapanış notu (T-99): Ana akış `MvpFlowTest` ile ekran bileşenleri üzerinden, hız `ListPerformanceTest` ile (5 ve 300 müşteride eşit sorgu sayısı) doğrulandı; Bugün'ün Yapılacaklar listesindeki satır başına sorgu düzeltildi, ölçümler ve doküman düzeltmeleri T-99'da.
- [x] **G-5.8 Canlıya geçiş kontrol listesi** — Bağımlılık: G-5.7
  - Yapılacak: `docs/CANLIYA_GECIS.md`: canlı ortam ayarları (hata ayrıntıları kapalı, HTTPS), test e-postası, ayarların doldurulması, ilk girişten sonra panelden şifrenin değiştirilmesi ve hostingdeki .env'den ADMIN\_PASSWORD satırının silinmesi, örnek verinin canlıda olmadığının kontrolü, canlı ortamın APP\_KEY değerinin güvenli bir yerde yedeklenmesi (değişir veya kaybolursa şifreli lisans anahtarları okunamaz).
  - Kabul: Liste ekran adımlarıyla takip edilebilir.
  - Kapanış notu (T-101): Kullanıcı kararıyla Genel ayarlara ikincil "Test e-postası gönder" düğmesi eklendi (kayıtlı adrese, senkron; hata mesajı maskelenmiş ve kısaltılmış). Yayın adresi aynı sürümde config önbelleğini yenilemediği için listede her `.env` düzenlemesinden sonra `bootstrap/cache/config.php` silinir; kod değişmedi. Listeye `SESSION_SECURE_COOKIE=true` ve zamanlayıcı uyarısı kontrolü eklendi.
- [x] **K-5.2 Yedek kontrolü:** Hosting panelinde veritabanı yedeklerinin günlük alındığını ve bir yedeğin geri yüklenebildiğini kontrol et. Kapanış: Gerek görülmedi (kullanıcı kararı, 15.10.2026).
- [x] **K-5.3 Canlıya geçiş ve veri girişi:** Kontrol listesini uygula, mevcut müşterileri ve hizmetleri "Bu dönemin borcu: Oluşturma" ile gir. Kapanış: Gerek görülmedi (kullanıcı kararı, 15.10.2026).
- [x] **K-5.4 Paralel kullanım:** 2–4 hafta boyunca Excel'i bırakmadan sistemle birlikte kullan; günlük özetin ve borçların Excel'le tutarlı olduğunu görünce Excel'i bırak. Kapanış: Gerek görülmedi (kullanıcı kararı, 15.10.2026).

**Faz 5 bittiğinde:** MVP tamam; sistem günlük işini Excel'in yerine yürütüyor.

## Faz 5B – Müşteri yenileme hatırlatması

Hedef: Yenileme borcu oluştuğunda müşterinin birincil ilgili kişisine sistemin kendiliğinden Türkçe hatırlatma e-postası göndermesi. MVP kapandıktan sonra, Faz 6'dan önce yapılır; MVP'deki tek e-posta kullanıcıya giden özetti, bu faz müşteriye giden ilk e-postadır.

- [x] **G-5B.1 Hatırlatma e-postası** — Bağımlılık: G-5.7
  - Yapılacak: Yenileme borcu oluşunca (bitişten 30 gün önce, G-3.4) müşterinin birincil ilgili kişisine hizmet adı, tutar, vade ve bitiş tarihini içeren sade Türkçe e-posta. Aynı "henüz yapılmadıysa yap" mantığıyla zamanlayıcıda çalışır. Aynı borç için en fazla bir kez gönderilir; ödenmiş, ücretsiz, iptal edilmiş hizmetler ve arşivli müşteriler atlanır. Ayarlar sayfasına "Müşteriye yenileme hatırlatması gönder" anahtarı eklenir (varsayılan kapalı).
  - Kabul: Tek gönderim, tekrar çalışmada yinelenmeme, atlanan cron sonrası gönderim, ücretsiz/iptal/arşivli/ödenmiş atlanması, anahtar kapalıyken gönderilmemesi, birincil kişinin e-postası yoksa gönderilmeyip günlük özette uyarı çıkması (hepsi testli); Mailpit'te görünüm kontrol edilir.
  - Kapanış notu (T-102, T-103): Yenileme borcu `charges.is_renewal` ile işaretlenir (ilk dönem borcuna, elle borca ve bölünen parçaya gitmez); gönderim `charges.reminder_sent_at` ile bir kez. Kullanıcı kararları: yalnız vadesi gelmemiş borçlar, tek tarih + yeni dönem içerikli e-posta, `Reply-To` bildirim adresi, boş ya da geçersiz adres özette "Hatırlatma gönderilemedi" bölümü olur. Bilinen sınır: önceki dönem borcu olmayan ve dönemi kaymış eski kayıtlar işaretlenmez (belirsizde hatırlatma gitmez).
- [x] **G-5B.2 Gönderim kaydı ve elle gönderim** — Bağımlılık: G-5B.1
  - Yapılacak: Müşteri kartındaki borç satırında "Hatırlatma gönderildi (tarih)" bilgisi; gönderilmemiş veya yeniden gönderilmesi istenen borç için tek tıkla elle gönderim.
  - Kabul: Gönderim tarihi görünür; elle gönderim kaydı günceller (Livewire testi).
  - Kapanış notu (T-104): Tarih açıklamanın altında soluk satır; elle gönderim borç düzenleme panelinin "Borç işlemleri" bölümünde (kullanıcı kararı, UI.md §4 düzeni değişmedi) ve alıcıyı gösteren onay penceresi ister. Açık yenileme borçlarında, vadesi geçmiş ve daha önce hatırlatılmış olsa da çalışır; ayar anahtarından bağımsızdır. Gönder-ve-kaydet kuralı otomatik gönderimle ortak `SendRenewalReminder` Action'ındadır.
- [x] **G-5B.3 Elle borç ve nakit ödemenin kaldırılması** — Bağımlılık: G-5B.2
  - Yapılacak: İşletme elle borç ve nakit ödeme kullanmıyor. Elle borç ekleme (Action, form, üst çubuk, Borçlar listesi ve müşteri kartı düğmeleri) kaldırılır; "Yeni" menüsü "Yeni müşteri" düğmesi olur. "Nakit" ödeme yöntemi bir migration ile kaldırılır (kullanılmışsa arşivlenir).
  - Kabul: Hiçbir ekranda elle borç eylemi yok; mevcut kurallı borçlar, düzenleme, bölme ve iptal çalışır; migration kullanılmayan Nakit'i siler, kullanılanı arşivler, tek aktif yöntemse dokunmaz ve ikinci çalışmada bir şey değiştirmez (hepsi testli).
  - Kapanış notu (T-105): Eski elle borçlar ve nakit ödemeler yerinde kalır.
- [x] **K-5B.1 Kullanıcı kararları** — Bağımlılık: yok
  - Yapılacak: E-posta metnini onayla; gönderen adresini ve hostingdeki SMTP bilgilerini hazırla; ilk haftalarda anahtarı açmadan önce Mailpit/test adresiyle birkaç örnek gönder.
  - Kapanış: Kullanıcı tarafından tamamlandı (20.10.2026).
- [x] **G-5B.4 WhatsApp ile borç hatırlatması** — Bağımlılık: G-5B.2, G-6.7 (kullanıcı kararı U-25)
  - Yapılacak: Bugün'deki borç satırlarında ve müşteri kartının Açık borçlar satırında, "Ödeme al"ın yanında "WhatsApp" bağlantısı: birincil kişinin sohbeti hazır yazılmış borç mesajıyla (kişi, alan adı ve açıklama, kalan tutar, vade, firma adı ve IBAN) yeni sekmede açılır; kayıt tutulmaz. `BuildWhatsAppReminder` Action'ı telefonu uluslararası biçime çevirir ve bağlantıyı üretir. Genel ayarlardaki firma bilgilerine IBAN alanı eklenir. Hazır veri (seeder, factory) ve testlerdeki telefonlar belirgin sahte numaralarla değiştirilir.
  - Kabul: Telefon biçimleri (0'lı, +90'lı, 00'lı, yabancı, geçersiz), gecikmiş ve vadesi gelmemiş mesaj farkı, kısmi ödenmişte kalan tutar, para birimi, alan adı öneki, firma adı ve IBAN'lı/IBAN'sız mesaj, URL kodlama; kapalı borç, arşivli müşteri, kişisiz ya da telefonsuz müşteride bağlantı yok; IBAN doğrulama ve kayıt; Bugün ve kartta düğmenin görünmesi ve yeni sekmede açılması; ücretsiz karar satırlarında yok; liste sorgu sayısının veriyle artmaması (hepsi testli).

**Faz 5B bittiğinde:** Yaklaşan yenilemeden müşteri sistemden haberdar oluyor; kullanıcının her yenileme için elle e-posta yazması gerekmiyor.

## Faz 8 – Sunucu entegrasyonu (WHM reseller + çok alan adlı cPanel)

Hedef: Birden fazla WHM / cPanel bağlantısı tanımlayıp "Sunucudan çek" ile paketleri hosting katalog hizmeti, hesapları ve alan adlarını müşteri + müşteri hizmeti olarak yerleştirmek. Kararlar: U-18, T-106. Kullanıcı kararıyla Faz 6'dan önce yapılır. Her görev tek oturumdur; WHM/cPanel çağrıları testlerde `Http::fake()` ile yapılır, gerçek sunucuya istek atılmaz.

- [x] **K-8.1 API token'ları ve erişim:** WHM'de (Geliştirme → Manage API Tokens) ve cPanel'de (Güvenlik → Manage API Tokens) yalnız gerekli izinli token oluştur; hostingin 2087/2083 portlarına dışarı bağlanabildiğini G-8.1'deki "Bağlantıyı dene" ile canlıda doğrula.
- [x] **G-8.1 Sunucular** — Bağımlılık: Faz 5B
  - Yapılacak: Migration, `Server` modeli (Attribute stili, arşiv), `ServerType` enum + `lang/tr`, factory; Ayarlar altında "Sunucular" ekranı (ortada pencere, satırda en fazla iki işlem); `TestServerConnection` Action'ı ve "Bağlantıyı dene". Token formda bir daha gösterilmez, boş bırakılırsa değişmez.
  - Kabul: Token veritabanında şifreli; cPanel türünde katalog hizmeti zorunlu; bağlantı denemesi başarı, yetki hatası ve zaman aşımı mesajları (testli).
  - Kapanış notu (T-107): Faz 8'in `catalog_services` ve `customer_service_items` alanları da bu görevde eklendi.
- [x] **G-8.2 API istemcileri** — Bağımlılık: G-8.1
  - Yapılacak: `WhmApi::packages()/accounts()`, `CpanelApi::accounts()` (ana + addon domain → `RemoteAccount`); yanıt hatalarını tek istisna türüne çevirme.
  - Kabul: Örnek WHM/UAPI JSON'larıyla doğru değer nesneleri; `metadata.result=0` ile HTTP 401 ayırt edilir (testli).
- [x] **G-8.3 Paket içe aktarma** — Bağımlılık: G-8.2
  - Yapılacak: `ImportServerPackages` Action'ı: yeni paket → hosting katalog hizmeti (verilen fiyat ve para birimiyle), mevcut paket → yalnız details güncellenir.
  - Kabul: İki çekmede tek katalog kaydı; ad ve fiyat değişmez; birim dönüşümleri ve "Limitsiz" (testli).
- [x] **G-8.4 Hesap içe aktarma** — Bağımlılık: G-8.3
  - Yapılacak: `ImportServerAccounts` Action'ı: eşleştir → yoksa müşteri + birincil kişi → `AssignCustomerService` (`create_first_charge: false`, yıldönümü başlangıcı). Sonuç nesnesi: oluşan, bağlanan, zaten bağlı, askıda atlanan, sunucuda bulunamayan, hata.
  - Kabul: İdempotent ikinci çekme; elle girilmiş eşleşen hizmetin çiftlenmemesi; askıdakilerin atlanması; yıldönümü (29 Şubat dahil, `travelTo`); hiç borç oluşmaması; cPanel'de her alan adının ayrı müşteri olması; katalog kaydı olmayan paketin hesabının hata olarak raporlanması (testli).
- [x] **G-8.5 "Sunucudan çek" ekranı** — Bağımlılık: G-8.4
  - Yapılacak: Sunucular satırında "Sunucudan çek": pencerede yeni paketlerin fiyat girişleri (WHM) veya başlangıç tarihi (cPanel) ve sayı önizlemesi; onayla G-8.3 + G-8.4 çalışır; sonuç mesajı ("12 müşteri oluştu, 3 hizmet bağlandı, 2 askıda atlandı") ve sorunlu hesap listesi.
  - Kabul: Livewire testi; tık sayısı `docs/UI.md` §7'ye eklenir (1 tık + fiyatlar + 1 tık).
- [x] **G-8.6 Uçtan uca ve canlı deneme** — Bağımlılık: G-8.5, K-8.1
  - Kabul: Sahte WHM yanıtıyla müşteri kartında hizmet doğru görünür (testli); canlıda bir sunucuyla deneme (kullanıcı); ardından yenileme borçları normal kuralla doğar.
- [x] **G-8.7 Çekmede müşteri adı seçimi** — Bağımlılık: G-8.5 (canlı denemede ana alan adı çoğu zaman alt alan adı çıktı)
  - Yapılacak: "Sunucudan çek" penceresi her yeni hesap için hesabın tüm alan adlarını listeler (ana alan adı seçili gelir) ve "Diğer…" ile elle yazmaya izin verir; seçilen ad müşteri kısa adı, birincil kişi adı ve kalemdeki alan adı olur.
  - Kabul: Listeden ve elle girilen ad testli; alan adları okunamazsa ana alan adına düşülür; mevcut kısa ad seçilirse hizmet o müşteriye eklenir.
- [x] **G-8.8 Çekmede hesapları listeden çıkarma** — Bağımlılık: G-8.7
  - Yapılacak: "Sunucudan çek" penceresinde her yeni hesap satırı silme düğmesiyle listeden çıkarılabilir; çıkarılan hesap aktarılmaz, kaydedilmez ve sonraki çekmede yine listelenir. Paketler çıkarılamaz.
  - Kabul: Çıkarılan hesabın müşterisi oluşmaz, bildirimde "N hesap çıkarıldı" yazar (testli).

**Faz 8 bittiğinde:** Hosting hesapları elle girilmeden sunucudan gelir; askıya alma, kota uygulama ve hesap/paket için zamanlanmış senkron ayrı öneri olarak park listesinde kalır; kullanım okuma ve kota uyarıları Faz 9'dadır.

## Faz 9 – Sunucu kullanımı ve kota uyarıları

Hedef: Sunucuya bağlı hosting hesaplarının disk, trafik, e-posta kutusu kotaları, veritabanı ve sayaç kullanımını okuyup saklamak, arayüzde göstermek ve eşik aşılınca e-posta, özet ve Bugün ile uyarmak. Kararlar: U-19, T-112. Yalnız okur; kota uygulama ve askıya alma parkta kalır. Her görev tek oturumdur; WHM/cPanel çağrıları testlerde `Http::fake()` ile yapılır, gerçek sunucuya istek atılmaz.

- [x] **G-9.0 Doküman güncellemesi:** PRD "Sunucu kullanımı ve kota uyarıları (Faz 9)" bölümü, DECISIONS U-19 ve T-112, DATA_MODEL `account_usages` / `mailboxes` ve `usage_warning_percent`, UI.md kullanım ekranı kuralları, bu faz.
- [x] **K-9.1 Token izni (kullanıcı):** WHM token'ının hesap adına UAPI çağırma izni `domainsOf` ile zaten çalışıyor; gerekirse izin eklenir. Canlıda G-9.4'teki "Şimdi yenile" ile doğrula.
- [x] **G-9.1 Kullanım verisinin okunması** — Bağımlılık: Faz 8
  - Yapılacak: `RemoteServerApi::usageOf(RemoteAccount)`; `WhmApi` (`uapi_cpanel`) ve `CpanelApi` (`execute`) uygulamaları; ortak UAPI isteği `ServerApiClient`'ta. Çağrılar: `ResourceUsage::get_usages`, `Email::list_pops_with_disk`, `Mysql::list_databases`. Değer nesneleri (`RemoteAccountUsage`, `RemoteResource`, `RemoteMailbox`, `RemoteDatabase`); boyutlar bayt, sınırsız `null`; tek boyut biçimlendirme yardımcısı ("1,2 GB").
  - Kabul: Örnek JSON'larla doğru değer nesneleri; limitsiz kota; cPanel'de e-posta kutularının hesabın tümünün okunması (alan adına göre süzme G-9.4'te gösterimde); modülü olmayan eski cPanel için anlaşılır hata; UAPI `status=0` ile HTTP 401 ayırt edilir (testli).
- [x] **G-9.2 Saklama ve zamanlanmış yenileme** — Bağımlılık: G-9.1
  - Yapılacak: `account_usages` ve `mailboxes` migration, model, factory; `RefreshAccountUsage` (tek hesap) ve `RefreshStaleUsages` Action'ları; `app:refresh-server-usage` komutu `routes/console.php`'de (`everyMinute`, `withoutOverlapping`, bekleyen yayında atlanır). Bağlı ve aktif hizmeti olan, 1 saatten eski hesaplar eskiden yeniye işlenir; çalışma en çok 60 sn.
  - Kabul: 1 saat kuralı (`travelTo`); süre sınırında kalan hesabın sonraki çalışmada tamamlanması; ikinci çalışmanın satır çoğaltmaması; sunucudan kalkan e-posta satırının silinmesi; hata alan hesabın eski verisini koruması ve hatanın kaydı; cPanel'de tek hesap satırı (testli).
- [x] **G-9.3 Uyarılar: e-posta, özet, Bugün** — Bağımlılık: G-9.2
  - Yapılacak: "Kullanım uyarı eşiği" ayarı (%90; Genel ayarlar formu); eşik/durum hesabı tek yerde; `QuotaWarningMail` (eşiği yeni aşan her şey tek e-postada, müşteri kartı bağlantılı); `warning_sent_at` ile tek seferlik uyarı; `BuildDigest`'e "Kotası dolmak üzere olanlar" bölümü (`isEmpty` dahil); Bugün'e kota satırları ("Kullanımı gör", G-9.4 gelene kadar müşteri kartına).
  - Kabul: Eşik ve tek seferlik uyarı; eşiğin altına inip yeniden aşınca yeni uyarı; gönderim hatasında sonraki çalışmada yeniden deneme; limitsizin uyarmaması; özet bölümü; Bugün satırları; arşivli müşteri ve iptal edilmiş hizmetin görünmemesi (testli).
- [x] **G-9.4 Kullanımın arayüzde gösterilmesi** — Bağımlılık: G-9.2
  - Yapılacak: `docs/UI.md` §5 "Kullanım penceresi" ve ilgili satırlar: salt okunur "Kullanım" penceresi ("Şimdi yenile", "Kapat"); müşteri kartı hizmet satırında yalnız eşik aşılınca rozet; hizmet düzenleme penceresinde özet satırı ve "Kullanımı gör"; Bugün'deki kota satırlarının düğmesi pencereyi Bugün'de açar; Sunucular ekranında "Kullanım" sütunu.
  - Kabul: Bölümün yalnız sunucuya bağlı hosting hizmetinde görünmesi; değerler, limitsiz durum ve e-posta tablosu; cPanel'de alan adına göre süzme; rozetin yalnız eşik aşılınca çıkması; "Şimdi yenile" ile güncelleme ve hata mesajı; Sunucular sütunu; tık sayıları `docs/UI.md` §7'ye eklenir (Livewire testi).
- [x] **G-9.5 Kullanım yenileme aralığı ayarı** — Bağımlılık: G-9.2
  - Yapılacak: Genel ayarlara "Kullanım yenileme aralığı" (sayı + Dakika/Saat/Gün; 15 dakika – 30 gün; varsayılan 1 saat); `usage_refresh_minutes` ayarı, `RefreshIntervalUnit`; `RefreshStaleUsages` süreyi ayardan okur.
  - Kabul: Varsayılan 60; birim ve dakika dönüşümü; aralık dışı değerin reddi ve okumada 60'a dönüş; form açılışı ve kaydı; ayarlanan aralıkta yenileme (`travelTo`) (testli).
- [x] **G-9.6 Bayi (reseller) toplam kullanımı** — Bağımlılık: G-9.3, G-9.4 (kullanıcı kararı U-20)
  - Yapılacak: WHM sunucusunun bayi toplamı (disk, trafik, hesap sayısı ve limitleri) `resellerstats`/`acctcounts` ile, reddedilirse `listaccts`/`showbw` toplamıyla okunur (`RemoteServerApi::resellerTotals`); `server_usages` tablosu; `RefreshServerTotals`/`RefreshStaleServerTotals` ayarlı aralıkta; eşik aşımı hesap uyarılarıyla aynı yoldan (e-posta, özet, Bugün'de "Sunucu: X"); Sunucular ekranında "Bayi: ..." satırı.
  - Kabul: Limitli/limitsiz okuma, bayta çeviri, reddedilince geri düşüş ve limitin "okunamadı" olması, yetki hatasında geri düşmeme; aralık kuralı; arşivli/cPanel atlama; tek seferlik e-posta ve kötüleşince yeniden uyarı; özet ve Bugün satırı; Sunucular satırı (testli).
- [x] **K-9.2 Bayi toplamı canlı doğrulaması (kullanıcı):** Canlı bayi token'ıyla Sunucular ekranında "Bayi: ..." satırının geldiğini ve limitlerin görünüp görünmediğini kontrol et; limit görünmüyorsa token `resellerstats`/`acctcounts`'e kapalıdır (toplam yine görünür).
- [x] **G-9.7 Hesabın tüm alan adları** — Bağımlılık: G-9.2, G-9.4 (kullanıcı kararı U-21)
  - Yapılacak: Kullanım yenilemesi WHM hesabının ana, ek ve park alan adlarını da okur ve `customer_service_items.remote_domains`'e yazar; hizmet satırında "+N alan adı", hizmet düzenleme ve Kullanım pencerelerinde tam liste; `Customer::search` bu alan adlarıyla da bulur.
  - Kabul: Alan adı okuma (küçük harf, alt alan adı yok, cPanel'de yok, okunamazsa kullanım yine okunur ve eski liste kalır); yalnız hesaba bağlı kalemlere yazma; `otherDomains`; satır, düzenleme ve Kullanım penceresi metinleri; harf duyarsız arama ve iptal edilmiş hizmetin alan adıyla bulma (testli).
- [x] **G-9.8 Sunucu kullanım penceresi** — Bağımlılık: G-9.6 (kullanıcı kararı U-22)
  - Yapılacak: Bayi yenilemesi hesap listesini de saklar (`server_usages.accounts`, `listaccts` + `showbw`); Sunucular ekranında "Kullanım" sütununa tıklayınca salt okunur sunucu penceresi: bayi göstergeleri, tahsis edilen disk/trafik toplamı, sayaçlar ve bütün hesapların tablosu (müşteri, disk, trafik, askı ve eşik rozetleri); "Şimdi yenile".
  - Kabul: Hesap satırlarının okunması (bayta çeviri, limitsiz, askı nedeni), `resellerstats` reddedilse de hesap listesi, `showbw` okunamazsa trafik "—"; rapor sıralaması, müşteri eşlemesi (bağlı değil, arşivli), tahsis toplamı, eşik ayarı; pencere metinleri, limit okunamadı notu, hata ve boş durum, "Şimdi yenile" ve hata bildirimi, cPanel'de pencerenin olmaması (testli).
- [x] **K-9.3 Sunucu penceresi canlı doğrulaması (kullanıcı):** Yayından sonra Sunucular ekranında "Kullanım" sütununa tıklayıp hesap tablosunun, müşteri adlarının ve (token izin veriyorsa) bayi limitlerinin geldiğini kontrol et.

**Faz 9 bittiğinde:** Kotası dolmak üzere olan hesaplar kullanıcı bakmadan e-posta, özet ve Bugün ile haber verir; kota uygulama ve askıya alma parkta kalır.

## Faz 6 – Teklifler

Hedef: Katalogdaki her hizmet ve paketten teklif hazırlamak, PDF ekiyle e-postayla göndermek ve kabulde müşteri ile müşteri hizmetlerinin kendiliğinden oluşması. Kurallar PRD §Teklifler'de, kararlar U-16 ve U-23'te. Faz 8 ve 9'dan sonra yapılır. Her görev tek oturumdur; e-posta testlerde `Mail::fake()` ile yapılır.

- [x] **G-6.0 Doküman güncellemesi:** PRD §Teklifler (tekrar gönderim, süresi dolan teklif, kabul penceresi, karışık para birimi), DATA_MODEL (`quotes` kısıtları, `company_details`), UI.md (menü, Teklifler ekran kuralları, tık sayıları), DECISIONS U-23, bu faz.
- [x] **K-6.1 Gönderen ve firma bilgileri (kullanıcı):** cPanel'de teklif gönderen adresini aç (örn. teklif@…). SMTP'nin canlıda çalıştığını Genel ayarlardaki "Test e-postası gönder" ile doğrula. SPF/DKIM'i kontrol et, logo dosyasını hazırla.
- [x] **G-6.1 Veri katmanı** — Bağımlılık: Faz 5B
  - Yapılacak: `QuoteStatus` enum'u (`draft`, `sent`, `accepted`, `rejected`) ve `lang/tr` metinleri. `quotes` ve `quote_items` migration'ları, `customer_services.quote_item_id` (boş olabilir). Modeller Attribute stilinde, ilişkileriyle. Factory durumları: taslak, gönderilmiş, süresi dolmuş, kabul, red, elle alıcı. Hesaplanan değerler: `Quote::isExpired()`, görünen durum ve para birimi başına toplam. Ayar anahtarları `quote_validity_days`, `quote_bcc_email` ve `company_details`, `Settings` metotlarıyla.
  - Kabul: Kalemde katalog hizmeti ve paketten tam olarak biri dolu olur. Numara benzersizdir. "Süresi doldu" hesaplanır: `valid_until` günü boyunca geçerlidir, ertesi gün dolar (`travelTo`). Toplamlarda farklı para birimleri karışmaz, ücretsiz kalem toplama katılmaz. Ayar varsayılanları döner (hepsi testli).
- [x] **G-6.2 Teklif oluşturma ve düzenleme Action'ları** — Bağımlılık: G-6.1
  - Yapılacak: `app/Actions/Quotes`: `CreateQuote` (başlangıç kalemi, numara, `valid_until`), `UpdateQuote` (alıcı, notlar, kalemler; yalnız taslakta) ve `DeleteQuote` (yalnız taslakta). Fiyat ve para birimi katalogdan kopyalanır, değiştirilebilir.
  - Kabul: Yıl içinde sıralı numara verilir, yeni yılda 0001'den başlar. Eşzamanlı iki oluşturmada numara çakışmaz. Elle alıcıda ad ve e-posta zorunludur. Gönderilmiş teklif düzenlenemez ve silinemez. Katalog fiyatı sonradan değişince teklif etkilenmez (hepsi testli).
- [x] **G-6.3 Teklif PDF'i** — Bağımlılık: G-6.2; paket: `barryvdh/laravel-dompdf` (onaylandı, U-23)
  - Yapılacak: Paket kurulur, DejaVu font ayarlanır. `RenderQuotePdf` Action'ı ve Blade görünümü: firma bilgileri ve logo, numara, tarih, geçerlilik, alıcı, kalemler (paket içeriği alt satırda), "Ücretsiz", para birimi başına toplam, notlar.
  - Kabul: PDF üretilir. Metinde Türkçe karakterler, "Ücretsiz" ve ayrı toplamlar bulunur. Logo yoksa da PDF üretilir (testli). Dosya Türkçe karakterlerle gözle kontrol edilir.
- [x] **G-6.4 E-postayla gönderim** — Bağımlılık: G-6.3
  - Yapılacak: `QuoteMail` (PDF ekli; HTML ve düz metin sürümü) ve `SendQuote` Action'ı: alıcı varsayılanı, BCC, `sent_at`/`sent_to`, taslaktan "gönderildi" durumuna geçiş. Tekrar gönderimde içerik aynı kalır, `valid_until` yenilenir.
  - Kabul: Başarılı gönderimde durum, tarih ve adres kaydedilir. Hata olursa teklif taslak kalır. BCC ayarlı adrese, ayar boşsa bildirim adresine gider. Tekrar gönderim süresi dolmuş teklifi yeniden geçerli yapar. Kabul edilmiş veya reddedilmiş teklif gönderilemez (hepsi testli). Görünüm Mailpit'te kontrol edilir.
- [x] **G-6.5 Kabul ve red** — Bağımlılık: G-6.2
  - Yapılacak: `AcceptQuote`: mevcut müşteride doğrudan atama; elle alıcıda `CreateCustomer` (kısa ad verilir, teklifteki kişi birincil kişi olur). Her kalem için `AssignCustomerService` (`price`, `currency`, `is_free`, `start_date`, `quote_item_id`); teklifin `customer_id`'si doldurulur; hepsi tek transaction. `RejectQuote` hiçbir kayıt oluşturmaz.
  - Kabul: Her kalem için ayrı müşteri hizmeti oluşur, hepsi aynı başlangıç tarihiyle. Ücretli kalemde ilk dönem borcu doğar, ücretsiz kalemde doğmaz. Kısa ad çakışırsa hiçbir kayıt oluşmaz. Süresi dolmuş veya taslak teklif kabul edilemez ve reddedilemez; iki kez kabul edilemez. Arşivli müşteriye kabul reddedilir. Alacak bakiyesi yeni borca düşer (hepsi testli).
- [x] **G-6.6 Teklif ekranları** — Bağımlılık: G-6.4, G-6.5 (U-24: kapsam G-6.7 ile ikiye bölündü)
  - Yapılacak: UI.md §5 "Teklifler" kurallarına göre: "Teklifler" menü öğesi ve listesi (numara + alıcı, toplam, geçerlilik, durum; durum süzgeci ve arama); katalog hizmeti ve paket satırlarında "Teklif oluştur" ile listede "Yeni teklif"; taslak için düzenlenebilir, gönderilmiş için kilitli teklif penceresi; PDF indir, Gönder / Tekrar gönder, Kabul et, Reddet ve (yalnız taslakta) Sil.
  - Kabul: Akışlar Livewire testleriyle korunur. Gönderilmiş teklifin penceresi kilitli açılır. Satırda en çok iki metinli işlem. Durum rozetleri UI.md §6 renklerine uyar (gri = pasif: taslak, reddedilmiş). Tık sayıları UI.md §7'deki hedeflerde (`ClickCountTest`).
- [x] **G-6.7 Ayarlar ve müşteri kartı** — Bağımlılık: G-6.6
  - Yapılacak: Genel ayarlara teklif geçerliliği (gün), teklif BCC adresi ve firma bilgileri (ad, adres, telefon, e-posta, vergi dairesi/no) ile logo yükleme (`local` disk, projedeki ilk dosya yükleme); `UpdateSettings` bunları yazar, değiştirilen/kaldırılan logonun eski dosyası silinir. Müşteri kartında, "Son ödemeler"in altında yalnız teklifi olan müşteride "Teklifler" bölümü (numara, toplam, durum; yeni satır düğmesi yok), `QUOTES_CHANGED_EVENT` ile yenilenir.
  - Kabul: Ayarlar kaydedilir ve `Settings` okuyucularında görünür; geçerlilik aralığı dışı ve geçersiz e-posta reddedilir; logo yüklenir, PDF'te çıkar, kaldırılınca dosya silinir (testli). Kartta bölüm yalnız teklifi olan müşteride görünür ve kabulden sonra yenilenir. Ekranlar 375–1440 px'te yatay kaymaz.
- [x] **G-6.8 Uçtan uca ve canlı deneme** — Bağımlılık: G-6.7, K-6.1
  - Kabul: Tek test katalogdan teklif oluşturmadan e-postaya, elle alıcının kabulüne, müşteri kartında hizmetlerin ve ilk borcun görünmesine kadar gider. Canlıda kullanıcı kendi adresine bir teklif gönderip PDF'i kontrol eder.

**Faz 6 bittiğinde:** Fiyat verilen kişiye teklif sistemden gider ve kabul edilince müşteri ve hizmet elle girilmeden oluşur.

## Faz 10 – Filament'ten çıkış, cam arayüz

Hedef: Arayüzü Apple cam (Liquid Glass) tasarımıyla birebir, Filament'siz yeniden yazmak (kullanıcı kararı, T-135). İş mantığı (`app/Actions`, modeller, zamanlayıcı, e-postalar) değişmez; ekranlar aynı sütun, süzgeç, işlem ve pencere içeriğiyle taşınır. Yeni arayüz `/app` altında kurulur, Filament `/panel`'de taşınma bitene kadar çalışır; her görev ayrı commit ve yayınlanabilir. Her görev sonunda `npm run build`, test, lint ve analiz tam yeşil.

- [x] **G-10.0 Kararlar ve belgeler** — UI.md §6 cam dili, DECISIONS T-135, CLAUDE.md sürüm satırı, bu faz.
- [x] **G-10.1 Temel** — Bağımlılık: G-10.0
  - Yapılacak: `livewire/livewire` + `blade-ui-kit/blade-heroicons` açık bağımlılık, `routes/app.php`, `layouts/app` ve `layouts/guest`, cam tema CSS ve tokenlar, bileşen kiti (`x-ui.*`), `WindowHost`, giriş/çıkış/profil.
  - Kabul: `/app` girişsiz erişilemez, giriş/çıkış/profil çalışır; bileşenler ve cam tokenları testli (kontrast, sabit renk yalnız token bloğunda).
- [x] **G-10.2 Tablo altyapısı, Müşteriler listesi, global arama, üst çubuk** — Bağımlılık: G-10.1
- [x] **G-10.3 Müşteri kartı ve pencereleri** — Bağımlılık: G-10.2
- [x] **G-10.4 Açık borçlar ve Ödemeler** — Bağımlılık: G-10.3
- [x] **G-10.5 Bugün** — Bağımlılık: G-10.4
- [x] **G-10.6 Teklifler** — Bağımlılık: G-10.3
- [x] **G-10.7 Katalog ve Ayarlar** — Bağımlılık: G-10.3
- [x] **G-10.8 Geçiş** — Bağımlılık: G-10.5, G-10.6, G-10.7
  - Yapılacak: yeni arayüz `/app` yerine site kökünden sunulur (yönlendirme gerekmez: `/panel` zaten kaldırılacak), e-posta bağlantıları `route('app.*')`. Bütünleşik testlerin yeni ekranlara taşınması Filament'in kaldırıldığı G-10.9'da yapılır (eski ekranları sınadıkları için).
- [x] **G-10.9 Filament'i kaldır** — Bağımlılık: G-10.8
  - Yapılacak: paket, `app/Filament`, panel sağlayıcısı, görünümler, CSS/varlıklar, dağıtım ve CI adımları, `tests/Feature/Filament` → `tests/Feature/Ui`.
  - Yapıldı: `filament/filament` ve bağımlılıkları kaldırıldı (`livewire/livewire` 4.4.6 ve diğer paketler aynı sürümde kaldı), `tests/Feature/Filament` silindi (karşılıkları `tests/Feature/Ui` altında), bütünleşik testler (MvpFlow, ListPerformance, QuoteFlow, ServerPull, AccountDomains, MailTheme, Deploy*) cam ekranlara taşındı.
- [x] **G-10.11 Kritik görsel hatalar** — Bağımlılık: G-10.9
  - Yapılacak: müşteri kartındaki bozuk `{{ … }` düğme metinleri ve bunu yakalayan tarama testi; Genel ayarlar kartı; dar ekranda açılan aramanın zemini; pencere içindeki seçim/tarih panellerinin kırpılması (T-145); Bugün ve WhatsApp ipucundan yatay kayma; azaltılmış saydamlık kuralı; kilitli alan görünümü.
- [x] **G-10.12 Performans** — Bağımlılık: G-10.11
  - Yapılacak: bulanıklığı yalnız içerik altından kayan yüzeylere indirmek (`.ui-pane`, iç içe cam yok, üst çubuk `::before`), müşteri kartı/Settings/Açık borçlar toplamı/Bugün sorguları, `wire:navigate`.
  - Kabul: müşteri kartı sayfasında en çok 2 bulanık yüzey; kartta sorgu sayısı hizmet sayısından bağımsız.
- [x] **G-10.13 UI.md uyumu** — Bağımlılık: G-10.12
  - Yapılacak: tutarlar sağa hizalı, pencerede "Kapat", Müşteriler'de "Yeni müşteri", simgeli düğmelere metin, katalog/sunucu satır işlemleri, boş durum/kart başlığı/ipucu tek biçim, varsayılan filtre çipi; UI.md §6 mevcut cam görünüme göre güncellenir.
- [x] **G-10.14 Görsel düzeltmeler ve Inter** — Bağımlılık: G-10.13
  - Yapıldı: kart başlığı dolgusu (`is-flush`), satır sonu düğme + "›" tek satır, arama alanı (büyüteç, temizle, yumuşak odak halkası), tema/cam tercihinin `wire:navigate` sonrası korunması, Inter Variable yazı tipi (T-148).
- [ ] **G-10.10 Gözle kontrol (kullanıcı)** — Bağımlılık: G-10.14
  - Kabul: Tüm ekranlar açık ve koyu temada, telefon genişliğinde ve cam performansı kullanıcıca onaylanır.

**Faz 10 bittiğinde:** Panel cam tasarımıyla, Filament'siz, aynı akışlarla çalışıyor.

## Faz 11 – Tasarım ve tasarım performansı cilası

Karar (kullanıcı): cam dili, UI.md kuralları ve tık sayıları aynen kalır (cilalama); performansta yeni paket yok, cam efekti azaltılmaz (güvenli hızlandırma). Plan: önce performans (A), sonra tasarım (B).

- [x] **G-11.1 Yazı tipi yükü** — Bağımlılık: G-10.14
  - Yapıldı: kullanılmayan `Instrument Sans` (bunny) yazı tipi bloğu `vite.config.js`'ten kaldırıldı (7 dosya, ~120 KB); latin Inter woff2 `head.blade.php`'te `preload` edilir.
  - Kabul: derlemede `instrument-sans` yok; sayfa başında Inter latin preload'u var (`PerformanceTest`).
- [x] **G-11.2 Arka plan ve gezinme** — Bağımlılık: G-11.1
  - Yapıldı: zemin degradesi zaten `position: fixed` `body::before` katmanındaydı (testle güvence altına alındı); kenar menü, alt sekme çubuğu ve müşteri kartı bağlantıları `wire:navigate.hover` kullanır.
  - Kabul: `PerformanceTest` (hover ile ön yükleme, sabit blursuz zemin katmanı).
- [x] **G-11.3 Sorgu hafifletme** — Bağımlılık: G-11.1
  - Yapıldı: Açık borçlar ve Ödemeler müşteri filtre listesi sayfa ömrü boyunca bir kez okunur, `customers-changed` olayında yenilenir; üst arama en az 2 karakterde çalışır, yalnız gösterilen müşteri sütunlarını ve alan adı için gereken ilişki sütunlarını okur. `simplePaginate` uygulanmadı: her listede kayıt sayacı (`total()`) var (UI.md).
  - Kabul: `PerformanceTest` (2 karakter altında sorgu yok, dar sütunlar, filtre listesi güncellemede yeniden sorgulanmaz); `ListPerformanceTest` sorgu sayısı sabit kalır.
- [x] **G-11.4 Tasarım tokenları** — Bağımlılık: G-11.1
  - Yapıldı: köşe (`--mt-radius-*`), boşluk (`--mt-space-*`) ve yazı (`--mt-text-*`) tokenları eklendi, sabit köşe ve yazı boyutları tokena çevrildi (T-152); `tables.css` `date.css`, `customer.css`, `usage.css`, `today.css` olarak bölündü. Kabul: `PerformanceTest` (tokenlar tanımlı, kuralda sabit köşe/yazı boyutu yok, bölünmüş dosyalar içe aktarılır); `UiTokensTest` yeşil.
  - Kapsam: köşe ve boşluk tokenları, yazı ölçeği; sabit değerlerin tokena çevrilmesi; `tables.css`'in müşteri kartı/kullanım/Bugün/tarih seçici bölümlerinin ayrı dosyalara bölünmesi.
- [x] **G-11.5 Erişilebilirlik** — Bağımlılık: G-11.4
  - Yapıldı: 3:1 opak odak çizgisi ve görünür alan kenarı (`--mt-focus-edge`, `--mt-input-border`), `prefers-reduced-motion`, devre dışı alan kesik kenar, hata "!" işareti, dokunmatikte 40 px tıklama alanı, tooltip taşma düzeltmesi (T-153).
  - Kabul: `AccessibilityTest`; `UiTokensTest` odak ve kenar için 3:1.
- [x] **G-11.6 Tablo hiyerarşisi** — Bağımlılık: G-11.4
  - Yapılacak: tablo başlığı, yapışkan başlık, satır hover/seçili, sayı/ad boyut ayrımı, taşma yedeği.
  - Yapıldı: ayrışık sütun başı, geniş ekranda yapışkan başlık, hover yalnız gerçek işaretçide + odak, seçili satır, ad/sayı boyut ayrımı, taşma yedeği (T-154).
  - Kabul: `AccessibilityTest` tablo testleri.
- [ ] **G-11.7 Ortak bileşenler** — Bağımlılık: G-11.4
  - Yapılacak: `page-header`, `card`, `link-button`, `checkbox`, `radio`, `file-input`, `select` genişlik prop'u, `empty-state` simgesi, zorunlu alan işareti; satır içi `style` ve ham `<table>`/arama girişi temizliği.

## Faz 7 ve sonrası

Fiyat/tarih geçmişi, %50 ön ödeme kuralı ve sonraki fazların görevleri, sistem bir süre kullanıldıktan sonra yazılacak. Böylece gerçek kullanımdan çıkan ihtiyaçlar planlamaya dahil edilir.

