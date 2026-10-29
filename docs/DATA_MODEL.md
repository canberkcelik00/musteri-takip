# Veri modeli – alanlar

Her tablonun alanları, türleri ve kısıtları. İş kurallarının kaynağı `docs/PRD.md`'dir; çelişki olursa PRD geçerlidir ve durum kullanıcıya sorulur.

## Genel kurallar

- **Tutarlar** kuruş/sent cinsinden tam sayı (`unsignedBigInteger`) tutulur; 4.000,00 TL = 400000. Ondalıklı sayı (float) kullanılmaz.
- **Para birimi** PHP backed enum: `Currency` = `TRY`, `USD`, `EUR`. Her tutar alanının yanında `currency` alanı bulunur.
- **Sabit listeler** PHP backed enum olarak tanımlanır ve modelde enum cast edilir: `ServiceType` = `hosting`, `domain`, `maintenance`, `ssl`, `email`, `license`, `other`.
- **Arşiv** `archived_at` (nullable timestamp) ile yapılır. Borcu veya ödemesi olan kayıt silinmez.
- **Türe özel bilgiler** `details` (json, array cast) alanında tutulur. Hangi türün hangi anahtarları alacağı `ServiceType` enum'unda tanımlanır; tüm değerler serbest metindir.
- **Tarihler** saat içermeyen `date` alanlardır (başlangıç, bitiş, vade, ödeme tarihi).
- **Türetilen değerler saklanmaz**, hesaplanır: borcun kalan tutarı, kısmi ödenmiş ve gecikmiş durumları, alacak bakiyesi, teklifin süresinin dolması.
- Tüm tablolarda `id` ve `created_at` / `updated_at` bulunur; aşağıdaki tablolarda tekrar yazılmadı.

## Müşteriler ve ilgili kişiler

**customers**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| short_name | string | Evet | Benzersiz |
| legal_title | string | Hayır | Ticari ünvan |
| tax_office | string | Hayır | |
| tax_number | string | Hayır | |
| billing_address | text | Hayır | |
| notes | text | Hayır | |
| pcloud_url | string | Hayır | Müşterinin pCloud klasörünün paylaşım bağlantısı; yalnız https ve `pcloud.com` / `pcloud.link` (alt alan adları dahil) |
| archived_at | timestamp | Hayır | |

**contacts**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| customer_id | FK customers | Evet | |
| name | string | Evet | Ad soyad |
| title | string | Hayır | Görev |
| phone | string | Hayır | |
| email | string | Hayır | |
| is_primary | boolean | Evet | Varsayılan false |

Kısıtlar: her müşteride tam olarak bir `is_primary = true` kişi bulunur (ilk eklenen otomatik birincil; birincil değişince önceki kaldırılır; birincil kişi silinemez). Bu kural uygulama kodunda uygulanır ve testle korunur.

## Katalog hizmetleri ve paketler

**catalog_services**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| type | enum ServiceType | Evet | |
| name | string | Evet | Örn. "Linux Hosting 1000 MB" |
| price | integer | Evet | Referans fiyat, kuruş |
| currency | enum Currency | Evet | Varsayılan TRY |
| details | json | Hayır | Türe özel katalog bilgileri (sağlayıcı, disk alanı vb.) |
| notes | text | Hayır | |
| archived_at | timestamp | Hayır | |

**packages**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| name | string | Evet | Örn. "Kurumsal Paket" |
| price | integer | Evet | Paketin kendi referans fiyatı |
| currency | enum Currency | Evet | Varsayılan TRY |
| notes | text | Hayır | |
| archived_at | timestamp | Hayır | |

**package_items**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| package_id | FK packages | Evet | |
| catalog_service_id | FK catalog_services | Evet | |

Kısıt: `package_id` + `catalog_service_id` benzersizdir. Katalogdaki fiyat değişikliği müşteri hizmetlerini ve teklifleri etkilemez.

## Müşteri hizmetleri ve kalemleri

**customer_services**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| customer_id | FK customers | Evet | |
| catalog_service_id | FK catalog_services | Hayır | Tek hizmet satışında dolu |
| package_id | FK packages | Hayır | Paket satışında dolu |
| quote_item_id | FK quote_items | Hayır | Tekliften oluştuysa (Faz 6'da eklenir) |
| name | string | Evet | Satış anındaki adın kopyası |
| price | integer | Evet | Yıllık fiyat, kuruş |
| currency | enum Currency | Evet | |
| period_months | integer | Evet | Varsayılan 12, arayüzde gösterilmez |
| start_date | date | Evet | Geçerli dönemin başlangıcı |
| end_date | date | Evet | Geçerli dönemin bitişi |
| is_free | boolean | Evet | Varsayılan false |
| free_continuation_confirmed_for | date | Hayır | "Ücretsiz devam et" ile o anki `end_date` kopyalanır |
| status | enum | Evet | `active`, `cancelled` |
| cancelled_on | date | Hayır | |
| cancel_note | text | Hayır | |
| notes | text | Hayır | |

Kısıt: `catalog_service_id` ve `package_id` alanlarından tam olarak biri doludur. Bitiş tarihi geldiğinde `start_date` ve `end_date` `period_months` kadar ilerler (iptal edilmişlerde ilerlemez). `free_continuation_confirmed_for`, `end_date`'e eşit değilse (veya boşsa) ücretsiz ve yenilemesi yaklaşan hizmet "Bugün" sayfasında karar bekliyor sayılır (G-4.15); dönem ilerleyince alan eskide kalır ve soru kendiliğinden geri gelir.

**customer_service_items**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| customer_service_id | FK customer_services | Evet | |
| catalog_service_id | FK catalog_services | Hayır | Hangi katalog hizmetinden geldiği |
| type | enum ServiceType | Evet | Kopya |
| name | string | Evet | Kopya |
| details | json | Hayır | Müşteriye özel bilgiler: alan adı, sunucu, cPanel kullanıcı adı, hesap sayısı vb. |
| license_key | text | Hayır | `encrypted` cast ile şifreli |

Tek hizmet satışında bir kalem, pakette her iç hizmet için bir kalem oluşur.

## Sunucular (Faz 8)

**servers**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| name | string | Evet | Benzersiz; kalemin `server` bilgisine de yazılır |
| type | enum ServerType | Evet | `whm`, `cpanel` |
| hostname | string | Evet | |
| port | integer | Evet | Varsayılan 2087 (WHM) / 2083 (cPanel) |
| username | string | Evet | WHM veya cPanel kullanıcı adı |
| api_token | text | Evet | `encrypted` cast ile şifreli; şifre değil API token |
| catalog_service_id | FK catalog_services | cPanel'de evet | cPanel alan adlarına atanacak hosting hizmeti |
| last_pulled_at | timestamp | Hayır | Son "Sunucudan çek" |
| archived_at | timestamp | Hayır | |

Faz 8 ile mevcut tablolara eklenen alanlar:

- **catalog_services:** `server_id` (FK servers, boş olabilir) ve `remote_package` (string, boş olabilir); `server_id` + `remote_package` benzersiz. WHM paketinden gelen hosting hizmetini işaretler.
- **customer_service_items:** `server_id` (FK servers, boş olabilir) ve `remote_key` (string, boş olabilir; WHM'de cPanel kullanıcı adı, cPanel'de alan adı); `server_id` + `remote_key` benzersiz. `details` içindeki `domain`, `server`, `cpanel_username` yine doldurulur.
- **customer_service_items.remote_domains** (json, boş olabilir): WHM hesabındaki ana, ek (addon) ve park alan adları, küçük harfle, ana alan adı ilk sırada; alt alan adları yok. Sunucunun yazdığı önbellektir: kullanım yenilemesi (G-9.2) aynı sunucuda `remote_key` = hesap kullanıcısı olan bütün kalemlere yazar, hiçbir form yazmaz. Okunamazsa eski değer korunur; iptal edilmiş hizmetin kalemi son listeyi saklar (arama için). Kalemin kendi alan adı çıkarılmış hâli `CustomerServiceItem::otherDomains()` ile hesaplanır (T-119).
- **customer_service_items.is_cloudflare** (boolean, boş olabilir) ve **dns_checked_at** (timestamp, boş olabilir): Alan adı kontrolünün (G-9.10) yazdığı önbellek, hiçbir form yazmaz. `is_cloudflare` true ise `details.domain`'in NS kaydı `*.ns.cloudflare.com`'dadır; boş = henüz kontrol edilmedi. `dns_checked_at` son **deneme**dir (okunamayan deneme de yazılır, eski cevap kalır). Kalemin alan adı değişince ikisi boşalır (T-169).

WHM paket alanları katalog `details`'ine şöyle eşlenir: `QUOTA` → `disk_space` ("1000 MB", sınırsız → "Limitsiz"), `BWLIMIT` → `monthly_traffic`, `MAXPOP` → `email_accounts`, `MAXSQL` → `databases`, `MAXADDON` + 1 → `sites`, `provider` = sunucu adı.

## Sunucu kullanımı (Faz 9)

Sunucunun anlık görüntüsüdür (önbellek); geçmiş değildir. Yüzde ve "dolmak üzere / dolu" durumu saklanmaz, hesaplanır. Boyutlar bayt; sınırsız `null`.

**account_usages** (hesap başına bir satır)

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| server_id | FK servers | Evet | `server_id` + `remote_username` benzersiz |
| remote_username | string | Evet | WHM'de cPanel kullanıcı adı; cPanel'de bağlantı kullanıcı adı |
| resources | json | Hayır | Kaynak anahtarı → `used`, `limit` (null = limitsiz): disk, trafik, inode, e-posta hesabı, yönlendirici, otomatik yanıtlayıcı, e-posta listesi, FTP, veritabanı, ek / alt / park alan adı |
| databases | json | Hayır | Veritabanı adı ve bayt listesi |
| refreshed_at | timestamp | Hayır | Son başarılı yenileme; boşsa hiç yenilenmedi |
| failed_at | timestamp | Hayır | Son başarısız denemenin anı; başarıda boşalır |
| failure_reason | enum | Hayır | `RemoteServerErrorReason`; başarıda boşalır |
| warned_levels | json | Hayır | Uyarılmış kaynaklar: kaynak kimliği → `nearly_full` / `full`; kullanım eşiğin altına inince o kaynak silinir, düşük seviyeye inince seviye düşer |
| warning_sent_at | timestamp | Hayır | Son uyarı e-postasının gönderildiği an |

**mailboxes** (e-posta adresi başına bir satır)

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| server_id | FK servers | Evet | `server_id` + `remote_username` + `address` benzersiz |
| remote_username | string | Evet | |
| address | string | Evet | |
| domain | string | Evet | Adresin alan adı; cPanel'de hizmete göre süzmek için |
| used_bytes | bigint | Evet | |
| quota_bytes | bigint | Hayır | Boşsa limitsiz |
| login_suspended | boolean | Evet | Oturum askıda |
| incoming_suspended | boolean | Evet | Gelen posta askıda |
| warned_level | enum UsageLevel | Hayır | Uyarıldığı seviye (`nearly_full`, `full`); kullanım eşiğin altına inince boşalır |
| warning_sent_at | timestamp | Hayır | Son uyarı e-postasının gönderildiği an |

Yenilemede sunucuda artık olmayan e-posta satırı silinir; bağlı hizmeti kalmayan hesap satırı da silinir. Satırlar yalnız bağlı, aktif hizmeti olan ve arşivsiz müşterinin hesapları için doğar. Önbellek olduğu için `server_id` yabancı anahtarı `cascadeOnDelete`'tir; sunucu silme/arşiv kuralı (T-107) değişmez. `account_usages.refreshed_at` son başarılı okumadır; `failed_at` yalnız son başarısız denemedir ve başarılı okumada boşalır. Bir cPanel bağlantısında alan adlarının hepsi aynı kullanıcıya bağlı olduğu için bağlantı başına **tek** `account_usages` satırı vardır; alan adına göre süzme `mailboxes.domain` ile gösterimde yapılır.

**server_usages** (WHM sunucusu başına en çok bir satır; bayi toplamı)

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| server_id | FK servers | Evet | Benzersiz; `cascadeOnDelete` |
| resources | json | Hayır | `{id, label, used, limit, is_bytes, limit_read}` listesi; kimlikler `reseller_disk`, `reseller_bandwidth`, `reseller_accounts`; bayt bayttır, `limit` boşsa limitsiz, `limit_read` yanlışsa limit okunamadı (bilinmiyor, uyarı yok) |
| accounts | json | Hayır | Sunucudaki her hesap için `{username, domain, plan, suspended, suspend_reason, disk, disk_limit, bw, bw_limit}` listesi (`listaccts` + `showbw`); bayt bayttır, limit boşsa limitsizdir, `bw` boşsa trafik okunamadı (T-120) |
| refreshed_at | timestamp | Hayır | Son başarılı okuma |
| failed_at | timestamp | Hayır | Yalnız son başarısız deneme; başarılı okumada boşalır |
| failure_reason | enum RemoteServerErrorReason | Hayır | |
| warned_levels | json | Hayır | Kaynak kimliği → uyarıldığı seviye; eşiğin altına inince boşalır |
| warning_sent_at | timestamp | Hayır | Son uyarı e-postasının gönderildiği an |

Yalnız arşivsiz WHM sunucuları için doğar; sunucu arşivlenince veya cPanel olunca satır silinir. Yüzde ve durum saklanmaz, hesaplanır (T-118).

## Teklifler ve teklif kalemleri (Faz 6)

**quotes**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| number | string | Evet | Benzersiz, `TKL-YYYY-NNNN`, yıl içinde sıralı |
| customer_id | FK customers | Hayır | Mevcut müşteride dolu; kabulde yeni müşteri oluşunca doldurulur |
| recipient_name | string | Hayır | Elle yazılan alıcı |
| recipient_company | string | Hayır | Elle yazılan alıcı |
| recipient_phone | string | Hayır | Elle yazılan alıcı |
| recipient_email | string | Hayır | Elle yazılan alıcı |
| status | enum | Evet | `draft`, `sent`, `accepted`, `rejected` |
| valid_until | date | Evet | Oluşturma tarihi + ayarlardaki süre |
| sent_at | timestamp | Hayır | Son gönderim; tekrar gönderimde `valid_until` bugün + ayardaki süreye yenilenir |
| sent_to | string | Hayır | Son gönderilen adres |
| accepted_at | timestamp | Hayır | |
| rejected_at | timestamp | Hayır | |
| notes | text | Hayır | PDF'te görünen açıklama |

Kısıtlar: `customer_id` boşsa `recipient_name` ve `recipient_email` zorunludur. "Süresi doldu" saklanmaz: `status = sent` ve `valid_until` geçmişse hesaplanır. `sent` durumundaki teklif ve kalemleri düzenlenemez. Kabul ve red yalnız `sent` ve süresi dolmamış teklifte yapılır (`valid_until` günü boyunca geçerlidir). Yalnız `draft` teklif silinebilir. Numara oluşturmada satır kilidiyle verilir.

**quote_items**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| quote_id | FK quotes | Evet | |
| catalog_service_id | FK catalog_services | Hayır | |
| package_id | FK packages | Hayır | |
| name | string | Evet | Kopya |
| price | integer | Evet | Teklif fiyatı, kuruş |
| currency | enum Currency | Evet | |
| is_free | boolean | Evet | Varsayılan false |
| sort_order | integer | Evet | PDF'teki sıra |

Kısıt: `catalog_service_id` ve `package_id` alanlarından tam olarak biri doludur. Kabulde her kalem için bir müşteri hizmeti oluşur.

## Borçlar ve ödemeler

**charges**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| customer_id | FK customers | Evet | |
| customer_service_id | FK customer_services | Hayır | Eski elle borçlarda boş; yeni borçta hep dolu |
| description | string | Evet | Örn. "Kurumsal Paket 2027–2028" |
| amount | integer | Evet | Kuruş |
| currency | enum Currency | Evet | |
| due_date | date | Evet | Vade |
| period_start | date | Hayır | Dönem borcunda dolu |
| period_end | date | Hayır | Dönem borcunda dolu |
| status | enum | Evet | `open`, `paid`, `cancelled` |
| cancelled_at | timestamp | Hayır | |
| cancel_reason | text | Hayır | İptalde zorunlu |
| is_renewal | boolean | Evet | Varsayılan false; yalnız yenileme borcunda (G-3.4) true. Müşteri hatırlatması yalnız bunlara gider (G-5B.1) |
| reminder_sent_at | datetime | Hayır | Müşteriye yenileme hatırlatmasının gönderildiği an; dolu borca bir daha hatırlatma gitmez (G-5B.1) |
| cancelled_by | FK users | Hayır | Faz 12: borcu iptal eden; boş = sistem / önceki kayıt |
| updated_by | FK users | Hayır | Faz 12: borcu son düzenleyen veya bölen; boş = sistem / önceki kayıt |

Kısıtlar: `customer_service_id` + `period_start` benzersizdir; aynı dönem için ikinci borç üretilemez. "Kısmi ödendi" ve "gecikmiş" saklanmaz; dağıtımlardan ve `due_date`'ten hesaplanır. Dağıtımı olan borçta tutar ve para birimi değiştirilemez.

**payments**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| customer_id | FK customers | Evet | |
| amount | integer | Evet | Kuruş |
| currency | enum Currency | Evet | Müşterinin açık borcundan varsayılan gelir |
| paid_on | date | Evet | Varsayılan bugün |
| payment_method_id | FK payment_methods | Evet | Varsayılan son kullanılan |
| note | text | Hayır | |
| recorded_by | FK users | Hayır | Faz 12: ödemeyi kaydeden; boş = önceki kayıt |

**payment_allocations**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| payment_id | FK payments | Evet | Ödeme silinince dağıtımları da silinir |
| charge_id | FK charges | Evet | |
| amount | integer | Evet | Kuruş |
| source | enum AllocationSource | Evet | `payment` (ödeme kaydedilirken) / `credit_balance` (sonradan alacak bakiyesinden); varsayılan `payment` |

Kısıtlar: ödeme ve borcun para birimi aynıdır; bir ödemenin dağıtımları ödeme tutarını, bir borcun dağıtımları borç tutarını aşamaz. `payment_id` + `charge_id` + `source` benzersizdir: bakiyeden yapılan dağıtım, aynı ödemenin aynı borca ödeme anındaki dağıtımıyla birleşmez (günlük özette "bakiyeden kapatılanlar" için). Alacak bakiyesi = müşterinin ödemeleri − dağıtımları, para birimi başına hesaplanır.

**payment_methods**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| name | string | Evet | Örn. "Havale/EFT" |
| sort_order | integer | Evet | |
| archived_at | timestamp | Hayır | |

## Kullanıcılar ve yetkiler (Faz 12)

**users** (mevcut tabloya eklenenler)

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| password | string | Hayır | Faz 12: davet kabul edilene kadar boş; boş şifreyle giriş yapılamaz |
| invited_at | timestamp | Hayır | Son davetin gönderildiği an; imzalı bağlantı bununla eşleşmezse geçersizdir |
| deactivated_at | timestamp | Hayır | Dolu = pasif; para kaydında adı geçen kullanıcı silinmez (G-12.6), pasifleştirilir |

**Roller ve izinler:** `spatie/laravel-permission` tabloları (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`). Roller `Role` enum'u (`Admin`, `Developer`), izinler `Permission` enum'u (`money.view`, `money.manage`, `customers.manage`, `services.view`, `services.manage`, `quotes.manage`, `catalog.manage`, `settings.manage`, `users.manage`, `tasks.manage` (Faz 13), `calendar.manage` (Faz 15)); rol-izin eşlemesi veritabanındadır (Roller ekranından yönetilir, T-165); `SyncRolesAndPermissions` izinleri ve yerleşik rolleri idempotent kurar. `roles.label` (nullable): yerleşik rolün yönetici tarafından verilen görünen adı; boşsa `enums.role.*` çevirisi gösterilir, `name` kodun bulduğu sabit ad olarak kalır (T-166).

**quotes** tablosuna `accepted_by` (FK users, nullable) eklenir: teklifi kabul eden; boş = sistem / önceki kayıt.

## İşler (Faz 13)

**tasks**

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| title | string | Evet | En çok 255 karakter |
| notes | text | Hayır | |
| customer_id | FK customers | Hayır | `restrictOnDelete`; boş = müşterisiz iç iş. İşi olan müşteri silinmez, arşivlenir |
| assigned_to | FK users | Evet | `restrictOnDelete`; işi olan kullanıcı silinmez, pasifleştirilir |
| created_by | FK users | Hayır | `restrictOnDelete`; işi oluşturan |
| completed_by | FK users | Hayır | `restrictOnDelete`; işi bitiren |
| due_date | date | Hayır | |
| completed_at | timestamp | Hayır | Dolu = Bitti; durum saklanmaz |

İndeksler: (`assigned_to`, `completed_at`) ve (`customer_id`, `completed_at`). Açık, gecikmiş ve "bugün" durumları `completed_at` ve `due_date`'ten hesaplanır. Liste sırası: bitiş tarihi olanlar tarih sırasıyla önce (gecikmişler en üstte), tarihsizler sonda.

## Takvim (Faz 15)

**calendar_events** (model `CalendarEvent`; `Event` adı Laravel'in Event sınıfıyla karışacağı için kullanılmaz)

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| title | string | Evet | En çok 255 karakter |
| starts_on | date | Evet | Tekrarlayan etkinlikte serinin ilk günü |
| start_time | time | Hayır | `all_day` doluyken boş |
| end_time | time | Hayır | `start_time`'dan sonra olmalı |
| all_day | boolean | Evet | Varsayılan yanlış |
| customer_id | FK customers | Hayır | `restrictOnDelete`; müşteriye bağlı etkinliği olan müşteri silinmez, arşivlenir |
| location | string | Hayır | |
| notes | text | Hayır | |
| remind_minutes | unsigned integer | Hayır | Başlangıçtan kaç dakika önce; boş = hatırlatma yok |
| repeat | string (enum `EventRepeat`) | Evet | `none`, `weekly`, `monthly`; varsayılan `none` |
| repeat_until | date | Hayır | Boş = süresiz; seri bu tarihten (dahil) sonra çıkmaz |
| created_by | FK users | Evet | `restrictOnDelete`; etkinliği oluşturan kullanıcı silinmez, pasifleştirilir |

İndeksler: (`starts_on`) ve (`customer_id`, `starts_on`). Görünür aralığa düşen tekrarlar saklanmaz, sorguda açılır.

**calendar_event_user** (katılanlar): `calendar_event_id` (FK, cascade) + `user_id` (FK, `restrictOnDelete`), ikisi birlikte benzersiz.

**calendar_event_reminders**: gönderilen hatırlatmaların kaydı. `calendar_event_id` (FK, cascade), `occurs_on` (date, hatırlatılan tekrarın günü), `sent_at` (timestamp); (`calendar_event_id`, `occurs_on`) benzersiz. Satır yoksa hatırlatma gönderilmemiştir; zamanlanmış iş gönderip satırı yazar, böylece iki çalışma iki e-posta üretmez.

**Takvimin okuduğu diğer tablolar (yeni alan yok, hepsi hesaplanır):** `customer_service_items`/`customer_services` (bitiş), `charges` (açık borç vadesi), `quotes` (`valid_until`, `sent_at`), `tasks` (`due_date`, `completed_at`), `payments` (`created_at`), `site_incidents` (Faz 16).

## Alan adı ve site araçları (Faz 16)

**customer_service_items** tablosuna eklenenler (alan adı kaleminin son kontrol sonuçları; kalemin alan adı değişince hepsi boşaltılır, T-169 kalıbı):

| Alan | Tür | Not |
| --- | --- | --- |
| ssl_expires_at | timestamp, boş olabilir | Sertifikanın bitişi; boş = okunamadı ya da HTTPS yok (G-16.3) |
| ssl_checked_at | timestamp, boş olabilir | Son SSL denemesi; günde bir denenir |
| registration_expires_at | date, boş olabilir | WHOIS/RDAP kayıt bitişi (G-16.4) |
| registrar | string, boş olabilir | Kayıt şirketi |
| registration_checked_at | timestamp, boş olabilir | Son WHOIS denemesi |
| points_to_server | boolean, boş olabilir | A kaydı bağlı sunucunun IP'lerinden biri mi; boş = bilinmiyor (Cloudflare, sunucusuz, okunamadı) (G-16.5) |
| points_checked_at | timestamp, boş olabilir | Son eşleşme denemesi |
| site_failures | unsigned integer | Ardışık başarısız site yoklaması sayısı; varsayılan 0 (G-16.6) |
| site_checked_at | timestamp, boş olabilir | Son site yoklaması |

**site_incidents** (G-16.6): `customer_service_item_id` (FK, cascade), `domain` (string, olay anındaki alan adı), `started_at` (timestamp), `ended_at` (timestamp, boş = hâlâ kapalı), `reason` (string, boş olabilir; HTTP durumu ya da bağlantı hatası). Açık olay (`ended_at` boş) alan adı başına en çok bir tanedir. Olay silinmez; geçmiştir.

## Claude erişimi (Faz 18)

**oauth_clients, oauth_auth_codes, oauth_access_tokens, oauth_refresh_tokens, oauth_device_codes** (`laravel/passport` standart tabloları; U-43). Claude'un kendini kaydettiği uygulamalar (`oauth_clients`: uuid kimlik, ad, geri dönüş adresleri) ile onay sonrası verilen kod ve belirteçler burada durur. Belirteçlerin düz metni saklanmaz: erişim belirteci imzalı bir JWT'dir, tabloda yalnız kimliği, kullanıcısı, istemcisi, kapsamı (`mcp:use`), iptal durumu ve bitişi vardır. Cihaz kodu (`oauth_device_codes`) kullanılmaz. Süresi dolan ve iptal edilen satırları günlük `passport:purge` temizler.

**mcp_connections** (U-43; bir kullanıcının bir Claude uygulamasına verdiği izin)

| Alan | Tür | Zorunlu | Not |
| --- | --- | --- | --- |
| user_id | FK → users | Evet | Bağlantının sahibi; kullanıcı silinirse satır da silinir |
| oauth_client_id | FK → oauth_clients (uuid) | Evet | Onaylanan uygulama |
| access | string | Evet | `read` (varsayılan) ya da `read_write`; `McpAccess` enum'u |

`(user_id, oauth_client_id)` benzersizdir. Satır, uygulama o kullanıcı için ilk belirteci aldığında açılır (yazma kapalı); belirteç yenilenince değişmez. "Bağlantıyı kes" belirteçleri iptal eder ve satırı siler. Pasifleştirilen kullanıcının satırları silinir. Eski `personal_access_tokens` (Sanctum) tablosu kaldırıldı.

## Ayarlar

**settings** tablosu anahtar–değer olarak tutulur (`key` benzersiz string, `value` text).

| Anahtar | Varsayılan | Açıklama |
| --- | --- | --- |
| renewal_lead_days | 30 | Yenileme borcunun bitişten kaç gün önce oluşacağı |
| digest_frequency | monthly | Özet e-postasının sıklığı: `daily` (Günlük) / `monthly` (Aylık) |
| digest_day_of_month | 15 | Aylık özetin gönderildiği gün (1–28) |
| digest_time | 09:00 | Özet e-postasının saati |
| notification_email | — | Özetin gideceği adres |
| customer_reminders_enabled | 0 | Müşterinin birincil kişisine yenileme hatırlatması e-postası gönderilsin mi: `1` açık / `0` kapalı (G-5B.1) |
| digest_last_run_at | — | İç durum, ekranda yok: özetin son ele alındığı an (ISO tarih-saat); bir sonraki özetin "yeni borçlar" aralığı buradan başlar |
| scheduler_last_run_at | — | İç durum, ekranda yok: `app:process-customer-services` komutunun son bitiş anı (ISO tarih-saat); Bugün sayfasındaki zamanlayıcı uyarısı buradan hesaplanır (G-5.6) |
| scheduler_failed_service_ids | — | İç durum, ekranda yok: son çalışmada hata veren müşteri hizmetlerinin id'leri (JSON liste); hatasız çalışmada silinir (G-5.6) |
| quote_validity_days | 15 | Teklif geçerlilik süresi (Faz 6) |
| quote_bcc_email | — | Gönderilen tekliflerin kopyasının gideceği adres (Faz 6) |
| company_details | — | Teklif PDF'inde görünecek firma bilgileri ve logo (Faz 6). JSON: ad, adres, telefon, e-posta, vergi dairesi/no, IBAN (TR, boşluksuz; WhatsApp hatırlatmasında görünür, G-5B.4) ve `local` diskteki (`storage/app/private`) logo yolu; logo PDF'e base64 gömülür (symlink gerekmez) |
| usage_warning_percent | 90 | Kullanım uyarı eşiği (%, 1–100); disk, trafik, e-posta kutusu ve sayaçlar için (Faz 9) |
| usage_refresh_minutes | 60 | Kullanım yenileme aralığı (dakika, 15–43200); ayar sayfasında sayı + birim (Dakika, Saat, Gün) olarak girilir, birim saklanmaz (Faz 9) |
