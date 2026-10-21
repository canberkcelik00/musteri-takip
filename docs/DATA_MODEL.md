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
| sent_at | timestamp | Hayır | Son gönderim |
| sent_to | string | Hayır | Son gönderilen adres |
| accepted_at | timestamp | Hayır | |
| rejected_at | timestamp | Hayır | |
| notes | text | Hayır | PDF'te görünen açıklama |

Kısıtlar: `customer_id` boşsa `recipient_name` ve `recipient_email` zorunludur. "Süresi doldu" saklanmaz: `status = sent` ve `valid_until` geçmişse hesaplanır. `sent` durumundaki teklif ve kalemleri düzenlenemez.

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
| company_details | — | Teklif PDF'inde görünecek firma bilgileri ve logo (Faz 6) |
| usage_warning_percent | 90 | Kullanım uyarı eşiği (%, 1–100); disk, trafik, e-posta kutusu ve sayaçlar için (Faz 9) |
| usage_refresh_minutes | 60 | Kullanım yenileme aralığı (dakika, 15–43200); ayar sayfasında sayı + birim (Dakika, Saat, Gün) olarak girilir, birim saklanmaz (Faz 9) |
