# Kararlar (DECISIONS)

Projede verilmiş kararlar ve gerekçeleri. Claude Code bu kararları kendi başına değiştirmez; bir kararın değişmesi gerektiğini düşünürse işi durdurur ve kullanıcıya sorar. Yeni bir karar alındığında en alta eklenir.

Karar yetkisi: Sadece kodun içini ilgilendiren ve işleyişe etkisi olmayan kararları Claude Code verebilir ve buraya gerekçesiyle ekler. İşe, kullanıcı deneyimine, altyapıya veya bağımlılıklara dokunan kararlar kullanıcıya sorulur.

## Ürün kararları

| No | Karar | Gerekçe |
| --- | --- | --- |
| U-01 | Sistem Excel'den basit olmalı; her özellik "Excel'den daha kolay mı?" testinden geçer. | Sistemi yönetmek ayrı bir iş olmamalı. |
| U-02 | Sistem varsayılanı önerir, kullanıcı her zaman değiştirebilir; geçmiş kayıtlar iz bırakmadan değişmez. | Her müşteri farklı; ama para kayıtları güvenilir kalmalı. |
| U-03 | Hizmet türleri sabit, katalog kalemleri panelden eklenir. | Türe özel alanlar sade kalır, kural motoru gerekmez. |
| U-04 | Katalog fiyatı referanstır; müşteri hizmeti satış anının kopyasını tutar. | Katalog değişikliği mevcut müşterileri etkilememeli. |
| U-05 | Tüm hizmetler yıllık; ödeme planı yok, her dönemde tek borç. | Ödeme planları fazla karmaşıktı; istisnalar elle düzenlemeyle çözülür. |
| U-06 | Müşteri hizmetinde tek başlangıç/bitiş tarihi; ayrı tahsilat tarihi yok. | Sadelik. |
| U-07 | Yenileme borcu bitişten 30 gün önce doğar; vade bitiş tarihi. | Müşteriyi önceden arayabilmek için. |
| U-08 | Dönem, bitiş tarihi gelince ödeme durumundan bağımsız ilerler. | Tarihler hep güncel kalır; ödenmemiş borç gecikmiş olarak izlenir. |
| U-09 | Ücretsiz işareti elle yönetilir; sistem kendiliğinden ücretliye geçmez. | Karar kullanıcıda kalmalı; 30 gün kala özet e-postada sorulur. |
| U-10 | Ödeme en eski vadeden dağıtılır, elle değiştirilebilir; fazlası müşteri bazında alacak bakiyesi olur ve yeni borca otomatik düşer. | Müşteriler çoğunlukla toplu ödüyor. |
| U-11 | Para birimleri TL/USD/EUR; kur çevrimi yok, toplamlar ayrı gösterilir. | Kur hesabı istenmedi; karışık toplam yanıltıcı olur. |
| U-12 | Gecikmede sadece işaret ve hatırlatma; askıya alma WHM entegrasyonunda. | MVP sadeliği. |
| U-13 | İptalde yaklaşan dönemin ödenmemiş borcu varsayılan olarak iptal edilir; geçmiş dönem borcu asla otomatik iptal edilmez. | Gerçek alacak yanlışlıkla silinmemeli. |
| U-14 | Borcu/ödemesi olan kayıt silinmez, arşivlenir; yanlış ödeme silinebilir ve dağıtımları geri alınır. | Geçmişin korunması. |
| U-15 | Excel'den geçişte atama ekranında "Bu dönemin borcu: Oluşturma" seçeneği; toplu içe aktarma yok. | Sahte borç/ödeme girmeden geçiş. |
| U-16 | Teklifler MVP sonrası (Faz 6); çok kalemli, e-postayla gönderilir, kabulde müşteri ve hizmet otomatik oluşur. | Excel'i bırakmak için teklif gerekmiyor; ayrı büyük parça. |
| U-17 | Günlük tek özet e-posta; boş günde gönderilmez. | Bildirim kalabalığını önlemek. |

## Teknik kararlar

| No | Karar | Gerekçe |
| --- | --- | --- |
| T-01 | Laravel 13, PHP 8.3 (composer platform 8.3). | Laravel 11 güvenlik desteği bitti; hosting PHP 8.3. |
| T-02 | Filament 5 (panel `/panel`). | İç yönetim aracı; daha az özel kod, daha az hata yüzeyi. |
| T-03 | Modellerde PHP Attribute stili; tüm projede tek stil. | Laravel 13'ün güncel kalıbı; tutarlılık. |
| T-04 | Tutarlar kuruş cinsinden integer; float yasak. | Para hesabında yuvarlama hatası olmamalı. |
| T-05 | Sabit listeler PHP backed enum. | Tip güvenliği. |
| T-06 | Türetilen değerler (kalan tutar, kısmi, gecikmiş, bakiye, teklif süresi) saklanmaz, hesaplanır. | Kayıtlarla hiçbir zaman çelişmez. |
| T-07 | Türe özel bilgiler `details` json alanında; anahtarlar `ServiceType` enum'unda. | Tüm değerler serbest metin; tür başına tablo gereksiz. |
| T-08 | İş mantığı `app/Actions` altında; Filament ekranları sadece Action çağırır. | Kurallar tek yerde ve arayüzden bağımsız test edilir. |
| T-09 | Lisans anahtarı `encrypted` cast ile şifreli. | Hassas veri. |
| T-10 | MySQL 8.0 (hosting 8.0.45), utf8mb4; kod 8.4 ile de uyumlu. | Hosting ile birebir aynı ortam. |
| T-11 | Reseller hosting (cPanel): SSH yok, Composer yok, cron en sık */15. | Altyapı kısıtı. |
| T-12 | Kuyruk yok; e-postalar anında (senkron) gönderilir. | Kuyruk işçisi çalıştırılamıyor; 15 dk gecikme kabul edilemez. |
| T-13 | Zamanlanmış işler "henüz yapılmadıysa yap" mantığıyla, tekrar çalışmaya dayanıklı. | */15 cron ve atlanabilecek çalışmalar. |
| T-14 | Yayına alma: GitHub Actions → test → `vendor` dahil paket → FTPS → gizli anahtarlı yayın adresi (migration, önbellek); cron yedek. | SSH ve Composer olmadan güvenli yayın. |
| T-15 | PDF için dompdf, Türkçe için DejaVu font. | Sunucuda tarayıcı yok. |
| T-16 | Önbellek ve oturum database/dosya; Redis yok. | Hosting kısıtı. |
| T-17 | E-posta cPanel hesabı SMTP; SPF/DKIM açık. | Ek servis ve maliyet yok. |
| T-18 | Yerel ortam: Windows + Docker Desktop; WSL/Ubuntu ve Sail yok. Proje Windows klasöründe; projeye ait `compose.yaml` (PHP 8.3, MySQL 8.0, Mailpit); tüm komutlar `app` konteynerinde; `vendor`/`node_modules` Docker biriminde; LF satır sonları; Claude Code Windows'ta Git Bash ile. | Kullanıcı tercihi. Windows klasöründen dosya erişimi WSL'e göre yavaştır; birim kullanımı bunu hafifletir. |
| T-19 | Test ve kalite: Pest, Pint, Larastan, GitHub Actions; Laravel Boost. | Claude Code'un kendini doğrulaması; sürüme özel dokümantasyon. |
| T-20 | Kod, değişken ve yorumlar İngilizce; arayüz metinleri Türkçe (`lang/tr`); commit mesajları Türkçe ve görev koduyla başlar (örn. "G-2.3: Müşteri ve ilgili kişiler"). | Laravel/Filament ekosistemiyle uyum; görev takibi. |
| T-21 | Proje adı: klasör ve GitHub deposu `musteri-takip`; uygulama adı (`APP_NAME`) "Müşteri Takip". | Sade ve açıklayıcı; uygulama adı ileride ayarlardan değiştirilebilir. |
| T-22 | Yayına alma Faz 5'in başına taşındı; Faz 1'de CI ve hosting uyumluluk kontrolü (PHP 8.3 ve eklentiler) yapılır. | Faz 4'ten önce canlıda kullanılacak bir şey yok; hosting riski erken kontrol ve sürüm eşitlemesiyle düşürüldü. |
| T-23 | Yerel web sunucusu `php artisan serve` (taban imaj `php:8.3-cli`); Apache kullanılmaz. | Apache `www-data` ile, CLI komutları `root` ile çalışınca CLI'ın oluşturduğu dosyalar (log, önbellek) web sürecini engelliyordu; tek kullanıcı aynı kişi olunca izin sorunu kalmaz. `.htaccess` hostingde (Faz 5) denenir. |
| T-24 | Yerel portlar: uygulama `8000`, Mailpit arayüzü `8025`, MySQL dışarıya `127.0.0.1:3307` (konteynerler arası `mysql:3306`). | Yerelde kurulu başka bir MySQL ile çakışmasın. |
| T-25 | Test veritabanı `musteri_takip_testing`, MySQL konteynerinin ilk açılışında `docker/mysql/init` betiğiyle oluşturulur; `phpunit.xml` bu veritabanını kullanır. | Testler hostingle aynı motorda (MySQL 8.0) ve uygulama verisinden ayrı çalışır. |
| T-26 | Pest 4 kullanılır; Larastan seviye 6, analiz yolları `app`, `config`, `database`, `routes` (`tests/` hariç). | Pest 5 PHP 8.4 istiyor, platform 8.3. Seviye 6 tip eksiklerini yakalar ama iskeleti gürültüye boğmaz; Pest closure'larındaki `$this` PHPStan'de yanlış hata üretir. |
| T-27 | Laravel Boost: MCP sunucusu `docker compose exec -T app php artisan boost:mcp` ile çalışır; yönergeler Boost'un yönettiği `AGENTS.md`'de, CLAUDE.md'den `@AGENTS.md` ile içe aktarılır ve çelişkide CLAUDE.md geçerlidir; Laravel Cloud yönergesi/skill'i hariç tutulur (`boost.json` `cloud: false`, `config/boost.php` exclude); `.ai/rules` ve `record-rule` kapalıdır (`rules.enabled` false). | Tüm PHP komutları konteynerde (T-18); hosting reseller cPanel; kararların tek kaynağı bu dosya. Kullanıcı onayıyla. |
