# Arayüz ilkeleri

Bu sekme, panelin nasıl daha kolay kullanılacağına dair kuralları toplar: ne nerede durur, nereden erişilir, nasıl görünür. Onaylandığında repoya `docs/UI.md` olarak girer ve G-4.12–G-4.18'in kabul ölçütü olur.

**Ölçüt:** Her günlük iş, Excel'de yapmaktan daha az tık ve daha az düşünmeyle yapılabilmeli.

## 1. Kullanıcı ve görevler

İki rol var (Faz 12, T-157): **yönetici** (işletme sahibi, her şeyi görür ve yapar) ve **yazılımcı** (en çok 2-3 kişi; parayı hiç görmez). Aşağıdaki görev tablosu yöneticinin görevleridir; yazılımcı yalnızca müşteri bulma ve müşteri kartına bakma işlerini kullanır. Panel bir veri ambarı değil, iş listesidir; açıldığında "bugün ne yapmalıyım" sorusunu cevaplamalı.

| Görev | Sıklık | Hedef erişim |
| --- | --- | --- |
| Bugün ne yapmalıyım (gecikmişler, yaklaşan yenilemeler, ücretsiz kararlar) | Her gün | 0 tık: açılış sayfası |
| Müşteri bulmak (kısa ad veya alan adıyla) | Her gün | 1 tık + yazma, her sayfadan |
| Ödeme kaydetmek | Haftada birkaç | Her sayfadan 1 tıkla başlar; toplam 3 tık + tutar |
| Müşteri kartına bakmak | Her gün | Arama sonucundan 1 tık |
| Hizmet eklemek | Haftalık | Kartın üstünden 1 tık |
| Yeni müşteri eklemek | Aylık | Üst çubuktaki "Yeni müşteri" düğmesinden 1 tık |
| Borcu düzenlemek, bölmek, iptal etmek | Nadir | Borç satırından 2 tık |
| Katalog, paket, ayarlar | Nadir | Menünün altında |

**Kural 1 — Sıklık yakınlıktır:** Bir iş ne kadar sık yapılıyorsa o kadar az tıkla ve o kadar görünür yerden erişilir. Nadir işler menünün altına ve ikinci düzeye iner.

## 2. Genel ilkeler

Araştırmadan (başta Nielsen Norman Group'un uygulama tasarımı bulguları) bu projeye uyarlanan kurallar:

1. **Tutarlılık:** Aynı iş her ekranda aynı kelimeyle, aynı yerde ve aynı görünümle durur. "Ödeme al" bir yerde "Ödeme kaydet", başka yerde "Tahsilat" olamaz.
2. **Varsayılanlar dolu gelir:** Her formda en olası değer hazır gelir (tarih bugün, para birimi açık borçtan, yöntem son kullanılan). Kullanıcı yalnızca farklı olanı yazar.
3. **Sistem durumu görünür:** Her işlemden sonra ne olduğu tek cümleyle söylenir ("1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi"). Bekleme 2 saniyeyi aşarsa yükleme göstergesi çıkar.
4. **Hata mesajı neden ve nasıl söyler:** "Bir hata oluştu" yasak. Her mesaj sorunu ve çözümü söyler ("Önce müşterinin hizmetlerini iptal edin.").
5. **Etiketsiz simge yalnız tanınır iletişim işlemlerinde (T-130):** Her düğmede metin vardır; tek istisna WhatsApp, ara (telefon), e-posta, siteyi aç ve kopyala düğmeleridir: bunlar yalnız simgedir, ama her birinin Türkçe ipucu ve erişilebilir adı vardır. Başka hiçbir düğme simgeyle tek başına durmaz; simge diğerlerinde metnin yanında yardımcıdır.
6. **"⋮" çöp çekmecesi yok:** İçinde ne olduğu belli olmayan "⋮" veya "Diğer" menüleri kullanılmaz. Sık işlemler satırda metinli durur; nadir işlemler kaydın kendi sayfasında/kartında, adlandırılmış bir bölümde durur.
7. **Tehlikeli işlem ayrı durur:** Sil, İptal et gibi işlemler Kaydet'in yanında değil, ayrı, kırmızı ve onaylıdır. Yanlış tıklama ihtimali tasarımla düşürülür.
8. **Bağlamı kapatma:** Formlar sayfanın üstünde, ortada açılan pencerede açılır; ayrı bir sayfaya gidilmez, pencere kapanınca kullanıcı aynı yerde kalır. Pencere bütün formlarda aynı genişliktedir ve dar ekranda ekrana sığar. Uzun formda başlık ile Vazgeç · Kaydet sabit kalır, yalnız alanlar kayar; klavyesi açık küçük telefonda olduğu gibi ekran çok kısaysa ikisi de formla birlikte kayar. Tablo filtreleri de aynı biçimde ortada açılır. (Kullanıcı kararı, T-84; önceki kural: formlar yandan açılan panelde açılır ve arkadaki kart görünür kalır.)
9. **Anlamsız bilgi yok:** Kayıt numarası (id), teknik kodlar tablolarda görünmez. İlk sütun her zaman insanın tanıdığı bilgidir (kısa ad, açıklama).
10. **Kademeli gösterim:** Önce gereken azı gösterilir; ayrıntı ihtiyaç olunca açılır (ödeme dağıtımı, vergi bilgileri, kalem ayrıntıları katlanır bölümlerde).
11. **Önemli olan üst-soldadır:** Göz sayfayı önce üstten soldan sağa, sonra sol kenardan aşağı tarar. En önemli sayılar üst satırda, en önemli sütun en soldadır.
12. **Klavye:** Her sayfadan `Ctrl+K` ile arama/komut kutusu açılır; müşteriye gitmek ve ödeme kaydetmek fare kullanmadan yapılabilir.

## 3. Bilgi mimarisi (menü)

Menü en fazla altı ana öğe taşır ve sıklık sırasıyla dizilir.

| Sıra | Öğe | İçerik |
| --- | --- | --- |
| 1 | **Bugün** | Açılış sayfası: yapılacaklar ve özet sayılar |
| 2 | **Müşteriler** | Liste ve kartlar |
| 3 | **Teklifler** | Teklif listesi (Faz 6, U-23) |
| 4 | **Tahsilat** | "Açık borçlar" ve "Ödemeler" aynı grupta |
| 5 | **Katalog** | Hizmetler, Paketler (grup, kapalı başlar) |
| 6 | **Ayarlar** | Genel ayarlar, Ödeme yöntemleri, Sunucular (Faz 8), Kullanıcılar (en altta; Faz 12, yalnız yönetici; satırdan ad/rol düzenleme ve silme penceresi açılır) |

**Yazılımcının menüsü (Faz 12, T-157):** Yalnız **Müşteriler** görünür. Bugün, Teklifler, Tahsilat, Katalog ve Ayarlar menüde yoktur; adresle açılırsa 403 verir (`/` adresi yazılımcıyı Müşteriler'e yönlendirir). Alt sekme çubuğu: Müşteriler · Menü. Üst çubukta "Ödeme al" gösterilmez; "Yeni müşteri" kalır. Müşteri kartında tutar, borç, ödeme ve teklif içeren hiçbir alan görünmez; hizmetler ad, alan adı, sunucu ve bitiş tarihiyle fiyatsız listelenir. Müşteri listesinde açık borç/bakiye sütunu gizlidir.

**Dar ekranda (`lg` altında):** Kenar menünün yerine altta sekme çubuğu durur: Bugün · Müşteriler · Açık borçlar · Menü ("Menü" tam menüyü kayan panelde açar). Aktif sekme vurgulu mavidir (T-130).

**Üst çubuk (her sayfada aynı yerde):**

- Solda: arama kutusu (`Ctrl+K`). Kısa ad, ticari ünvan, ilgili kişi ve **alan adıyla** arar; müşteriler çoğu zaman alan adıyla anılır ("logi.com.tr'nin hosting'i").
- Sağda: **"Ödeme al"** birincil düğmesi (en sık iş her sayfadan tek tık) ve **"Yeni müşteri"** düğmesi (G-5B.3: elle borç kaldırıldı, "Yeni" menüsü tek seçenekli kaldığı için düğme oldu).
- **Dar ekranda (`sm`, 640 px altı):** arama kutusunun yerinde ikincil, metinli bir **"Ara"** düğmesi durur (Kural 5: etiketsiz simge yok); tıklanınca arama üst çubuğun tamamını kaplayarak açılır, yanında metinli **"Kapat"** durur; "Kapat" veya Esc kapatır. `Ctrl+K` bu genişlikte de aynı alanı açıp odağı ona verir. Sonuçlar ve sonuçlardaki "Ödeme al" aynıdır. "Yeni müşteri" düğmesi bu genişlikte gizlidir. (G-4B.7)

## 4. Yer kuralları: ne nerede durur

| İşlem türü | Yeri | Örnek |
| --- | --- | --- |
| Her yerden gereken iş | Üst çubuk | Ara, Ödeme al, Yeni |
| Sayfanın ana işi | Sayfa başlığının sağı, ikincil düğme | Müşteriler: "Yeni müşteri" |
| Kayda ait sık iş | Satırda, metinli, en fazla iki | Borç: "Ödeme al", "Düzenle" (Bugün ve kartta: "Ödeme al" ve WhatsApp simgesi); Sunucu: "Düzenle", "Sunucudan çek" ("Bağlantıyı dene" düzenleme penceresinin altında) (Açık borçlar listesinde `md` altında yalnız "Ödeme al"; aşağıdaki not) |
| Kayda ait nadir iş | Kaydın kartında/sayfasında, adlandırılmış bölümde | Hizmet: "Ücretsiz yap", "Böl" |
| Tehlikeli iş | En sonda, ayrı, kırmızı, onaylı | Sil, Hizmeti iptal et |
| Ayar | Yalnızca Ayarlar menüsünde | Yenileme gün sayısı |

**Dar ekranda borç satırı (kullanıcı kararı, G-4B.7):** Açık borçlar listesinde `md` (768 px) altında satırda yalnız "Ödeme al" vardır; borcu düzenlemek, bölmek ve iptal etmek müşteri kartından yapılır. Bu genişlikte §1'deki "Borç satırından 2 tık" hedefi aşılır: müşteri adı → karttaki borç açıklaması → işlem = 3 tık. Katalog hizmetlerinde `md` altında satır düğmesi yerine ad aynı düzenleme penceresini açar (tık sayısı aynı). Ödemeler'de `md` altında satırdaki "Sil" yoktur; silmek için tutara tıklanır, açılan ödeme ayrıntı penceresinin en altındaki ayrı "Sil" kullanılır: 2 tık + onay (tutar → Sil → onay). Tutara tıklamak hiçbir zaman doğrudan silmez.

**Birincil düğme kuralı:** Her ekranda tek bir birincil (dolu renkli) düğme olur: üst çubuktaki "Ödeme al" (form penceresi açıkken pencerenin "Kaydet"i); tablo satırlarındaki, Bugün'deki ve filtre panelindeki düğmeler ikincildir. Sayfa başlığındaki ana iş ve karttaki düğmeler ("Yeni müşteri", karttaki "Ödeme al", "Hizmet ekle" vb.) yerinde kalır ama ikincil (gri/çizgili) görünür.

**Genel ayarlar:** Form alt çubuğunda sağda birincil "Kaydet", solunda ikincil gri "Test e-postası gönder" durur. Düğme formdaki değil **kayıtlı** bildirim adresine, senkron gönderir; sonucu bildirimle söyler (G-5.8, T-101).

## 5. Ekran kuralları

### Bugün (açılış sayfası)

- **Üst satır, dört sayı:** Toplam alacak (para birimi başına), Gecikmiş tutar ve adet, Bu ay vadesi gelen, 30 gün içinde yenilenecek hizmet sayısı. Her sayı tıklanınca ilgili süzülmüş listeye gider.
- **Altında "Yapılacaklar" tek tablo olarak,** başlıkta iş sayısı ("6 iş"); sütunlar: Müşteri (kısa ad + ünvan), Konu, Vade / bitiş, Durum, Tutar, işlem. Satırlar öncelik sırasıyla: gecikmiş borçlar (en eski üstte), 7 gün içinde vadesi gelenler, ücretsiz devam kararı bekleyenler. Her satırda doğrudan iş düğmesi vardır ("Ödeme al", "Ücretsiz devam et", "Ücretli yap"); karta gitmeden iş bitirilebilir. Borç satırlarında, birincil kişinin telefonu varsa "Ödeme al"ın yanında ikincil bir WhatsApp simge düğmesi durur (yazısız; ipucu "WhatsApp") (G-5B.4): hazır borç mesajıyla sohbeti yeni sekmede açar, kayıt tutmaz; `md` altında gizlidir. Kısmi ödenmiş borcun konusu "… · kısmi ödendi", ücretsiz hizmetin "… · ücretsiz" olur; ücretsiz hizmetin durumu rozetsiz düz metin "Karar bekliyor", tutarı "—" yazılır (gri rozet yalnız iptal/arşivdir). `md` altında Konu ve Vade gizlenir.
- Liste boşsa "Bugün yapılacak bir şey yok" yazar.
- **Zamanlayıcı uyarısı (G-5.6):** yalnız sorun varken, dört sayının üstünde kırmızı (danger) kutu: zamanlayıcı hiç çalışmadıysa, 1 saatten uzun süredir çalışmıyorsa (bekleyen yayın varsa "yayın adresini açın") ve son çalışmada hata veren hizmet varsa (en çok 5 hizmet adı müşteri kartına bağlantıyla, fazlası "ve N hizmet daha"). Sorun yokken hiçbir şey görünmez.

### Müşteri listesi

- Sütunlar: Kısa ad (kalın) + ticari ünvan (alt satırda, soluk), Açık borç (sağa hizalı; gecikmiş varsa kırmızı), En yakın yenileme, Aktif hizmet sayısı.
- Satıra tıklamak kartı açar; satırda ayrıca "Ödeme al" kısayolu vardır.
- Varsayılan sıralama: gecikmiş borcu olanlar üstte, sonra kısa ad.
- Tablonun sağ üstünde süzülmüş kayıt sayısı durur ("8 müşteri").
- Dar ekran (G-4B.7): kısa ad ve ünvan sarar; En yakın yenileme `md`'den, Aktif hizmet sayısı `lg`'den görünür; satırdaki "Ödeme al" `md`'den görünür, altında satıra tıklayıp karta gidilir.

### Açık borçlar listesi

- Sütunlar: Müşteri (kalın), Açıklama, Vade, Durum, Kalan (sağa hizalı). Durum rozeti `lg` (1024 px) ve üstünde kendi sütunundadır (gecikmiş kırmızı, yaklaşan ve kısmi ödenmiş amber, ödendi yeşil, iptal gri; vadesi uzak açık borç rozetsiz "Açık"); `lg` altında rozet Kalan'ın altına iner. Kısmi ödenmiş borçta Kalan'ın altında ilk tutar yazar ("4.000,00 TL'den").
- Altta, para birimi başına "Toplam" satırı: süzülmüş ve aranmış listedeki açık borçların kalanı, Kalan sütununun altında; para birimleri toplanmaz. Açık borç listelenmiyorsa satır yoktur. Toplam her genişlikte Kalan ile aynı kenardadır.
- Dar ekran (G-4B.7): Müşteri ve Açıklama sarar; Açıklama ve Vade sütunları `md`'den görünür. `md` altında Müşteri sütunu önde kalır, açıklama ve "Vade: gg.aa.yyyy" müşteri adının altında soluk satırlar olur; satırda yalnız "Ödeme al" kalır (§4'teki not). Durum rozeti sarar.

### Müşteri kartı

İki sütun; geniş sol sütun işe, dar sağ sütun bilgiye ayrılır.

- **Başlık şeridi (tam genişlik, tek kenarlıklı kart):** Yol (Müşteriler / kısa ad), kısa ad ve ticari ünvan; büyük yazıyla açık borç ve alacak bakiyesi (para birimi başına, toplanmaz); sağda "Ödeme al" ve "Hizmet ekle", ikisi de ikincil (ekranın tek birincil düğmesi üst çubuktaki "Ödeme al"). `xl` (1280 px) altında ad kendi satırına geçer, tutarlar ve düğmeler altına iner.
- **Sol sütun, sırayla:**
  - Hizmetler: aktifler, kompakt satırlar (ad ve altında alan adı, bitiş, fiyat, durum); başlıkta sağda "N aktif" sayacı. Satırda düğme yoktur, hizmet adına tıklamak düzenleme panelini açar. "Durum" sütunu `xl`'den görünür; `xl` altında rozet (yalnız iptal ve yenilemesi yaklaşan) adın altına iner. İptal edilmişler katlanmış "İptal edilenler (2)" altında.
  - Açık borçlar: yalnızca açıklar; "Tüm borçlar" bağlantısı geçmişi açar. Satırda "Ödeme al" ve (birincil kişinin telefonu varsa) WhatsApp simge düğmesi vardır (G-5B.4; simge `md` altında gizlidir); borç açıklamasına tıklamak düzenleme panelini açar; panelin "Borç işlemleri" bölümünde yenileme borcu için "Hatırlatma gönder" de durur ve hatırlatılmış borcun açıklamasının altında soluk "Hatırlatma gönderildi: gg.aa.yyyy" satırı görünür (G-5B.2). "Vade" ve "Durum" sütunları `xl`'den görünür (karta özgü kural: sol sütun dar olduğu için Açık borçlar sayfasındaki `lg` kuralından farklıdır); `xl` altında vade açıklamanın altına, durum rozeti "Kalan"ın altına iner.
  - Son ödemeler: son 5; "Tüm ödemeler" bağlantısı.
- **Sağ sütun:** Birincil kişi (telefon ve e-posta tıklanınca aranır/yazılır), diğer kişiler, fatura bilgileri, notlar. Düzenleme buradan.
- **En altta, ayrı:** Arşivle, Sil.

### Teklifler (Faz 6, U-23)

- **Giriş noktaları:** Katalog hizmetleri ve Paketler listelerinde satırda "Düzenle" yanında "Teklif oluştur" (en fazla iki işlem kuralına uyar; `md` altında ad düzenlemeyi açtığı için bu genişlikte "Teklif oluştur" satırda tek kalır). Teklifler listesinde sayfa başlığının sağında "Yeni teklif" (ikincil; penceredeki ilk alan katalog hizmeti ya da paket seçimidir). "Kaydet" teklifi taslak olarak açar, Teklifler listesine götürür ve "Teklif TKL-… taslak olarak oluşturuldu." bildirimini gösterir; kalem eklemek için "Düzenle", göndermek için "Gönder" kullanılır (U-24).
- **Liste:** Numara (kalın) + alıcı (soluk), toplam (para birimi başına, sağa hizalı), geçerlilik, durum rozeti (Taslak gri, Gönderildi renksiz düz metin, Süresi doldu amber, Kabul edildi yeşil, Reddedildi gri). Satırda en çok iki metinli işlem: taslakta "Düzenle" + "Gönder"; gönderilmişte "Kabul et" + "Reddet" (süresi dolmuşta "Tekrar gönder"). PDF ve diğer işlemler satıra tıklayınca açılan teklif penceresindedir.
- **Teklif formu:** Ortada açılan pencere. Alıcı (müşteri seç ya da elle yaz), kalemler (ekle/çıkar, fiyat, ücretsiz), notlar. Gönderilmiş teklifin formu kilitli, salt okunur açılır; altında "PDF indir" ve "Tekrar gönder" durur. Sil yalnız taslakta, en sonda, ayrı ve onaylı.
- **Gönder penceresi:** Tek alan, alıcı e-postası (varsayılan gelir). Kabul penceresi: başlangıç tarihi ve (elle alıcıda) kısa ad. Red onaylıdır.
- **Müşteri kartı:** Sol sütunda, "Son ödemeler"in altında yalnız teklifi olan müşteride "Teklifler" bölümü (numara, toplam, durum); yeni satır düğmesi yoktur.

### Kullanım penceresi (Faz 9)

Sunucuya bağlı hosting hizmetinin kullanımı **hizmetin düzenleme formuna gömülmez** (Vazgeç · Kaydet düğmeli formda salt okunur veri "neyi kaydediyorum?" sorusunu doğurur). Ödeme ayrıntı penceresi gibi ayrı, salt okunur bir pencere açılır; altında yalnız "Kapat" (ve solda "Şimdi yenile") vardır. Her giriş noktası aynı pencereyi açar.

- **İlke: istisna odaklı.** Kullanıma her gün bakılmaz; önemli olan sınırı zorlayan hesaptır. Normal durumda hiçbir ekran değişmez ve rozet çıkmaz; sorun olan hesap kendini Bugün'de, kartta ve e-postada gösterir, ayrıntı ihtiyaç olunca açılır (Kural 10).
- **Eşik ve renk her yerde aynıdır** (çubuk, rozet, Bugün satırı, e-posta, özet): ayardaki eşiğin (varsayılan %90) altı renksiz; eşik ile %99 arası amber "Dolmak üzere"; %100 ve üstü kırmızı "Dolu". Renk her zaman metinle birlikte verilir. Limitsiz kaynakta çubuk ve uyarı yok, "1,2 GB · Limitsiz" yazılır. Her yerde "kullanılan / sınır" gösterilir, hiçbir yerde "kalan" gösterilmez.
- **Pencere:** Başlıkta alan adı · sunucu adı ve soluk "Son güncelleme: gg.aa.yyyy ss:dd". Üstte ana göstergeler (disk, aylık trafik, inode): "1,2 GB / 5 GB · %24" ve yatay çubuk. Altında sayaçlar iki sütunlu kompakt listede ("E-posta hesabı 4 / 10", veritabanı, ek / alt / park alan adı, yönlendirici, otomatik yanıtlayıcı, e-posta listesi, FTP); eşiği aşan sayaç amber/kırmızı yazılır. "E-posta hesapları (n)" tablosu: adres, kullanılan / kota, %, durum; en dolu üstte; askıdaki kutuda gri "Oturum askıda" / "Gelen posta askıda" rozeti; dar ekranda % ve durum adresin altına iner, yatay kayma olmaz. "Veritabanları (n)" katlanır, kapalı başlar.
- **cPanel türü:** ana göstergelerin üstünde "Hesap geneli · N alan adıyla paylaşılıyor" notu; e-posta tablosu yalnız o alan adının kutularını gösterir.
- **Hata:** amber kutuda neden ve çözüm ("Son yenileme başarısız: yetki hatası. Sunucu token'ını kontrol edin. Gösterilen veriler gg.aa.yyyy ss:dd'den."); hiç veri yoksa ne yapılacağı söylenir ve "Şimdi yenile" gösterilir.
- **"Şimdi yenile"** yalnız bu hesabı senkron yeniler, yükleme göstergesi gösterir ve sonucu tek cümleyle söyler; pencere yeni veriyle açık kalır.
- **Müşteri kartı, Hizmetler satırı:** satır normalde değişmez. Eşik aşılınca alan adının altına amber/kırmızı rozet gelir ("Disk %94", "2 e-posta dolu"; birden çok sorunda en kötüsü + "ve 1 sorun daha"); rozete tıklamak Kullanım penceresini açar (1 tık), hizmet adına tıklamak yine düzenlemeyi açar. Satıra düğme eklenmez.
- **Hizmet düzenleme penceresi:** sunucuya bağlı hosting kaleminin bölümünde tek satırlık soluk özet ("Disk 1,2 / 5 GB · 4 e-posta · 14:30") ve ikincil "Kullanımı gör" düğmesi; sorunsuz hesaba da böylece 2 tıkla bakılır.
- **Bugün:** kota satırları yapılacakların sonunda; konu "Disk %94 · logi.com.tr" veya "info@logi.com.tr kotası %97", durum "Dolmak üzere" / "Dolu" rozeti, tutar "—", düğme "Kullanımı gör" Kullanım penceresini Bugün'de açar (karta gitmeden iş bitirilebilir); penceredeki müşteri adı karta bağlantıdır.
- **Sunucular ekranı:** satır işlemleri ikide kalır; `md`'den "Kullanım" sütunu: "gg.aa.yyyy ss:dd · 3 uyarı", başarısızsa kırmızı "Yenilenemedi: yetki hatası".
- **Bayi toplamı (WHM):** Sunucular ekranında "Kullanım" sütununun altında soluk tek satır: "Bayi: Disk 38 GB / 50 GB · Trafik 120 GB / sınırsız · 24 / 30 hesap"; limit okunamadıysa yalnız toplam ("Bayi: Disk 38 GB · 24 hesap"). Eşik aşan kaynak varsa satır amber/kırmızı olur. Bugün'de satır "Sunucu: X · Bayi diski %94 · Dolmak üzere" ve "Sunucuya git" (Sunucular ekranı; ayrıntı oradan "Kullanım" sütununa tıklayınca açılır). Yeni düğme yoktur.
- **Hesabın diğer alan adları (WHM):** Müşteri kartında hizmet satırının soluk satırı kısa kalır: "logi.com.tr +3 alan adı" (diğer alan adı yoksa ek metin çıkmaz). Hizmet düzenleme penceresinde, sunucuya bağlı kalemin bölümünde kullanım özetinin üstünde soluk satır: "Hesaptaki diğer alan adları: ikinci.com, logi.net"; Kullanım penceresinde başlık notlarının arasında aynı satır. Yeni düğme yoktur.
- **Sunucu kullanım penceresi (WHM):** Sunucular ekranında "Kullanım" sütununa tıklamak (1 tık) salt okunur pencere açar; satır işlemleri ikide kalır. Başlıkta sunucu adı, altında adresi ve soluk "Son güncelleme". Üstte bayi göstergeleri (disk, aylık trafik, hesap sayısı: "32,1 / 50 GB · %64" ve çubuk; limitsizde "Limitsiz"); limit okunamadıysa soluk not ("Bayi limitleri sunucudan okunamadı (token yetkisi); yalnız toplamlar gösteriliyor.") ve yalnız toplam. Altında iki sütunlu sayaçlar: aktif / askıdaki hesap, müşteriye bağlı / bağlı olmayan hesap, hesaplara tahsis edilen disk ve trafik (bayi limitiyle yan yana, "+ N limitsiz hesap"). "Hesaplar (n)" listesi sunucudaki bütün hesapları gösterir: alan adı, kullanıcı · paket, müşteri (karta bağlantı; arşivliyse "arşivli"; yoksa "Bağlı değil"), disk ve trafik "kullanılan / sınır · %" ve çubukla, "Askıda" ve "Dolmak üzere" / "Dolu" rozetleri; eşik aşan hesaplar üstte, sonra en çok disk kullanan; dar ekranda bloklar alt alta iner, yatay kayma olmaz. Hesap başına e-posta uyarısı yoktur, renk yalnız gösterimdir. Altta "Kapat" ve solda "Şimdi yenile": yalnız sunucu toplamlarını ve hesap listesini yeniler, bağlı hesapların kullanımını değil. Hata ve hiç veri yok durumları hesap penceresindeki gibidir. cPanel bağlantısında pencere yoktur.

### Tablolar (tüm listeler)

- Tablolar dört işi desteklemeli: kayıt bulmak (arama, filtre), karşılaştırmak (hizalı sütunlar), tek kaydı görmek/düzenlemek ve kayıt üzerinde işlem yapmak.
- Tutarlar sağa hizalı ve eş genişlikte rakamlarla yazılır; tarihler gg.aa.yyyy.
- Durum her zaman renk **ve** metinle gösterilir (renk körlüğü ve yazdırma için).
- Boş tablo ne yapılacağını söyler ve iş düğmesini gösterir ("Henüz hizmet yok — Hizmet ekle").
- Varsayılan filtre görünür bir çip olarak durur ("Durum: Açık ×"); gizli filtre yoktur. Arama kutusu solda, çipler yanında, kayıt sayısı sağda tek satırdadır; çipin "×" düğmesi tıklanır ve klavyeyle erişilir.
- **Dar ekran (G-4B.7):** hiçbir liste ve kart tablosu 375–1440 px'te kartın içinde yatay kaymaz. Tanıdık bilgi ve tutar hep görünür ve uzun metin sarar; ikincil sütunlar `md`/`lg`'den görünür. Ödemeler: Yöntem `md`'den, Not `lg`'den; `md` altında satırdaki "Sil" yoktur. Tutara tıklamak her genişlikte (kartın Son ödemeler'inde de) ödeme ayrıntı penceresini açar: tarih, müşteri, tutar, yöntem, açıklama ve varsa "… alacak bakiyesine eklendi" satırı; dağıtım satırları (borç, tutar, kaynak) "Dağıtım (n)" başlıklı katlanır bölümde, kapalı başlar; en altta, ayrı bir "Ödeme işlemleri" bölümünde kırmızı çizgili, onaylı "Sil" durur ve altında pencerenin metinli "Kapat" düğmesi vardır. Silme sonrası bildirim sonucu tek cümleyle söyler ("Ödeme silindi, 2 borç yeniden açıldı."). Katalog hizmetleri: Tür ve rozet `md`'den; hizmet adı bağlantı gibi çizilir ve her genişlikte düzenlemeyi açar, `md` altında satırdaki "Düzenle" yoktur. Paketler: içerdiği hizmetler ve rozet `md`'den. Müşteri kartında uzun alan adı ve e-posta `xl` altında kelime ortasından kırılabilir.

### Formlar

- Tek sütun, etiket alanın üstünde, zorunlu alanlar işaretli.
- Varsayılanlar dolu; nadir alanlar katlanmış "Ayrıntılar" bölümünde.
- Kaydet sağ altta birincil; Vazgeç onun solunda ikincil; tehlikeli işlem formda yok. Form penceresinin sağ üstünde simgesiz, metinli "Kapat" düğmesi durur.
- Hata alanın hemen altında, alanı nasıl düzelteceğini söyleyerek.

## 6. Görsel dil

Faz 4C'den beri (T-130, T-132) Apple "Ayarlar" tarzı: gri gruplu zemin üstünde beyaz yuvarlak gruplar, seçili menü satırı mavi dolgu, yeşil anahtarlar, hap düğmeler. **Buzlu cam yoktu (tarihçe):** o zaman her kaydırmada sayfayı yeniden boyuyor ve üst çubuğa konunca "Ödeme al" gibi pencereleri içine hapsediyordu; Faz 10 (T-135) camı geri getirdi ve aşağıdaki Faz 10 kuralları ile cam token tablosu geçerlidir. Bu paragraf ve "Tasarım tokenları" altındaki düz tasarım tablosu tarihçedir (T-147). İlk taslak: `docs/design/apple/referans.html` ([tasarım sayfası](https://claude.ai/artifact/4hcfCNE5ARAoJdUYr5NAYG)); buzlu cam ve renkler taslakta eskidir, güncel değerler bu bölümdedir.

**Faz 10 (T-135): cam (Apple Liquid Glass) görsel dil.** Arayüz Filament'siz, Blade + Livewire + Alpine + Tailwind ile yeniden yazılır; yukarıdaki "buzlu cam yoktur" kuralı Faz 10 ile kalkar ve yerini aşağıdaki kurallar alır. Eski Filament paneli (`/panel`) ekranlar taşınana kadar eski kurallarla çalışır. Referans: [cam taslağı](https://claude.ai/artifact/S6DERxwuyB24zjCsoVksPn).

- **Zemin:** Renkli, yumuşak degrade (açık ve koyu temada ayrı); tüm yüzeyler bunun üstünde yarı saydam cam (`backdrop-filter`) olarak durur: kenar menü, üst çubuk, kartlar, tablo kartı, pencereler, açılır menüler.
- **Pencereler** `body` sonunda tek katmanda (Livewire `WindowHost`) çizilir, çubuğun içinde değil; T-132'deki "pencere üst çubuğa hapsoldu" hatası bu yapıda oluşmaz.
- **Performans (T-146):** Gerçek bulanıklık (`backdrop-filter`) yalnız altından içerik kayan yüzeylerde kalır: üst çubuk (`::before` katmanı), dar ekranda alt sekme çubuğu ve kayan kenar menü, pencere, açılır menü/seçim/tarih/arama panelleri ve bildirim. Kartlar, tablo kartları, sayı kartları, şerit ve giriş kartı `.ui-pane` olur: aynı yarı saydam dolgu, kenar ve kenar ışığı, ama bulanıklık yok (sabit zemin degradesi zaten yumuşaktır, bulanığı kendisine benzer). Bulanık yüzeyin içine ikinci bir cam konmaz; kart içindeki tablo `<x-ui.table :card="false">` ile kartsız çizilir. Masaüstünde kalıcı bulanık yüzey yalnız üst çubuktur; kaydırılan içerik tek katman, satırlar bulanık değil. `prefers-reduced-transparency` ve kullanıcı menüsündeki "Cam: kapalı" seçeneği yüzeyleri düz opak renge düşürür.
- **Kontrast:** Cam üstündeki metin için tokenlar zemin en kötü durumda (en az %70 opak beyaz/siyah) varsayılarak 4,5:1 ölçülür; `UiTokensTest` yeni CSS'i okur.
- **Güncel ölçüler (T-147, mevcut cam görünüm esas):** köşeler kart 16 px, üst çubuk ve pencere 18 px, kenar menü 22 px; form etiketi 13/500 (aşağıdaki tablodaki 14/600 tarihçedir); menü karesi 26 px; seçili menü satırı soluk beyaz; aktif çip mavi; üst çubuk avatarı düz gri. Tablo üstü: arama solda, süzgeç çipleri yanında, süzülmüş kayıt sayısı araç çubuğunun sağında; varsayılan süzgeç ("Durum: Açık") "×" düğmeli çip olarak görünür. Form pencerelerinin sağ üstünde metinli "Kapat" durur.
- **Korunanlar:** Renk anlamları (durum renkleri, rozet biçimi, mavi birincil, yeşil anahtar), yazı ölçeği, yoğunluk, tık sayıları (§7) ve tüm yer kuralları aynen geçerlidir; yalnız yüzey malzemesi (düz → cam) değişir. Sabit renk yalnız `:root` token bloğunda yazılır. Cam tokenlarının kesin değerleri aşağıdaki tablodadır; kaynak `resources/css/ui/tokens.css` (`--mt-*`, açık `:root`, koyu `:root.dark`), `UiTokensTest` ölçer.

| Token (cam arayüz) | Açık tema | Koyu tema |
| --- | --- | --- |
| Zemin tabanı ve lekeler (a mavi, b pembe, c sarı, d yeşil) | #CFD8FF; #7FB4FF, #FF9FCF, #FFD37A, #8BE0C3 | #0A0B14; #1C3A78, #5A2A58, #5A4216, #124D3E |
| Cam dolgu: normal / güçlü / menü ve pencere | beyaz %42 / %50 / %78 | #1C1C1E %50 / #242428 %58 / #2C2C2E %86 |
| Cam kenarlık, bulanıklık | beyaz %65, 20 px | beyaz %14, 20 px |
| Düz yüzey (Cam: kapalı, azaltılmış saydamlık) | #F5F5F9 | #1C1C1E |
| Metin / soluk / üçüncül | #1D1D1F / #3A3A3D / #505055 | #F5F5F7 / #D4D4D9 / #C0C0C6 |
| Bağlantı, WhatsApp, tehlikeli metin | #00479B, #0B5E33, #920012 | #A9D3FF, #30D158, #FFB4AF |

Metin tokenları cam dolgunun (üç dolgu) beş zemin renginin her biri üstündeki bileşik rengine karşı en az 4,5:1 ölçülür; bu yüzden açık temada metin ve bağlantı renkleri aşağıdaki skala tablosundan koyudur. Menü karesi simgeleri grafik sayıldığından 3:1 yeterlidir.

- **Tema:** Açık tema varsayılan; koyu tema kullanıcı menüsünden seçilebilir. Koyu tema gerçek siyah zeminli, #1C1C1E kartlıdır.
- **Renk:** Marka rengi #ff3800 yalnızca logo simgesinde; durum renkleriyle karışmaması için düğmede ve menüde kullanılmaz. Filament primary Apple mavisi: birincil düğme ve etkileşim öğeleri (bağlantı, onay kutusu); marka veya durum rengi değildir. Durum renkleri sabit ve başka işte kullanılmaz: kırmızı (danger) = gecikmiş/tehlikeli, turuncu (warning = Amber) = yaklaşan/kısmi, yeşil (success) = ödendi, gri (gray) = pasif/kapalı: iptal, arşiv, taslak teklif ve reddedilmiş teklif (U-24). Normal durum (vadesi uzak açık borç, bitişi uzak aktif hizmet) rozetsiz düz metindir; info kullanılmaz. "Yaklaşan": borçta 7 gün içinde vadesi gelen, hizmette ayarlardaki yenileme gün sayısı içinde bitecek; tanım modelde tek yerde durur. Yeşil anahtar (açık durum) iOS yeşilidir; ödendi rozetinin yeşilinden ayrı bir iştir ve yalnız anahtarda kullanılır.
- **Menü:** Kenar menü sayfa zemini renginde, sağında ince çizgi. Her öğenin solunda renkli yuvarlak köşeli kare ve içinde beyaz simge durur (iOS Ayarlar gibi): Bugün mavi, Müşteriler çivit, Teklifler turuncu, Açık borçlar mor (kırmızı değil, gecikmiş rengiyle karışmasın), Ödemeler yeşil, Katalog deniz mavisi, Ayarlar gri. Üzerine gelinen satır açık gri, seçili satır soluk beyaz dolgu ve kalın yazıdır (T-147; eski düz tasarımdaki mavi dolgu tarihçedir); seçili satırın karesi yarı saydam beyaz olur.
- **Simge işlemleri (Kural 5'in istisnası):** WhatsApp, ara, e-posta, siteyi aç ve kopyala yazısız, simgeli düğmedir; ipucu ve erişilebilir ad Türkçe, `lang/tr`'den gelir.
- **Yazı tipi (T-148):** SF Pro web'de lisanslı değildir; yığın `-apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Inter Variable', 'Segoe UI'`: Apple cihazlarda SF, diğerlerinde paketlenmiş Inter. Arama alanında solda büyüteç, doluyken sağda yuvarlak temizle düğmesi; odak halkası 3 px yumuşak (`--mt-focus-ring`).
- **Avatar:** Müşteri adının solunda, kısa adın iki baş harfi (Türkçe büyük harf) ve müşteriye sabit bir renk; süstür, ekran okuyucudan gizlidir.
- **Denetimler:** Anahtar açıkken yeşil, kapalıyken gri, topuz beyaz ve gölgeli; onay kutusu ve radyo işaretliyken mavi; açılır menüler gölgeli beyaz yuvarlak levha, üzerine gelinen öğe mavi dolgu ve beyaz yazı; sayfalamada geçerli sayfa mavi dolgu; giriş sayfası gri zeminde tek beyaz kart.
- **Seçim kutusu:** Hiçbir seçim kutusu yerel değildir (işletim sisteminin listesi biçimlendirilemez); kapalıyken gri dolgulu alan ve iOS çift ok, açıkken menülerle aynı levha: 32 px satır, 8 px köşe, vurgulanan satır mavi dolgu ve beyaz yazı, seçili öğe bağlantı renginde kalın.
- **Sekmeler:** iOS bölümlü denetim: gri kapsül, seçili parça açık ve gölgeli, yazı koyu (mavi değil).
- **Satır ucu:** Tıklanabilir satırın sonunda gri "›" (süs, ekran okuyucudan gizli). Üst çubuk avatarı gri degrade üstünde iki baş harf; ipucu koyu hap; pencerede çizgi yok, başlık ortalı; form etiketi 13/500 gri.
- **Yazı:** Sistem yazı tipi (`-apple-system`, SF Pro); olmayan cihazda panelin yerel yazı tipi (dış bağlantı yok). Sayılarda eş genişlikte rakamlar.
- **Yoğunluk:** Orta: tablo satırı ~48 px (avatarlı ~52 px), formlarda rahat boşluk.
- **Logo ve ad:** Sol üstte logo simgesi (#ff3800) ve "Müşteri Takip"; şimdilik yer tutucu simge, firma logosu gelince değiştirilir.
- **Dar ekran:** `lg` altında kenar menü yerine altta iOS tarzı sekme çubuğu: Bugün · Müşteriler · Borçlar · Menü ("Menü" kayan menüyü açar).
- **Tasarım tokenları:** Değerler aşağıdaki tabloda; tüm metin/zemin çiftleri iki temada da en az 4,5:1 (`UiTokensTest`). Değerler kodda tek yerde tanımlı: `resources/css/ui/tokens.css` içindeki `--mt-*` değişkenleri (Faz 10'da Filament kalktığı için gri ve primary skalası artık kodda yoktur; aşağıdaki skala tabloları tasarım referansıdır). E-posta teması (`resources/views/vendor/mail`) CSS değişkenlerini okuyamadığından bu tokenların renklerini kopyalar; `MailThemeTest` kopyadaki her rengin bir token rengi olduğunu doğrular. Bileşen stilleri yalnızca bu değişkenleri ve palet sınıflarını kullanır; sabit renk kodu yazılmaz.

**Tarihçe: Faz 4C düz tasarım tokenları.** Aşağıdaki tablo ve skalalar eski düz tasarımı anlatır; kodda geçerli değerler yukarıdaki cam token tablosu ve `resources/css/ui/tokens.css` içindedir (T-147).

| Token | Açık tema | Koyu tema |
| --- | --- | --- |
| Sayfa zemini | #F2F2F7 | #000000 |
| Kart, grup ve panel | #FFFFFF, kenarlık yok, çok hafif gölge | #1C1C1E, kenarlık ve gölge yok |
| Kenar menü zemini | #F2F2F7, sağda #D1D1D6 çizgi | #000000, sağda #38383A çizgi |
| Kenar menü metni / grup başlığı | #1D1D1F / #636366 | #F5F5F7 / #98989D |
| Menü satırı: üzerine gelme / seçili | zemin #E5E5EA / zemin #0071E3, metin #FFFFFF, kalın | zemin #1C1C1E / zemin #0A72E0, metin #FFFFFF, kalın |
| Menü kareleri | mavi #0A74E0, çivit #5E5CE6, turuncu #D9730D, mor #AF52DE, yeşil #2E9D4B, deniz mavisi #2A8FA3, gri #7C7C82; simge beyaz, 24 px kare, köşe 6 px; seçili satırda yarı saydam beyaz | aynı |
| Üst çubuk | #F2F2F7 (%94 opak), altında ince çizgi | #000000 (%92 opak) |
| Çizgi / satır ayırıcı / tablo başlığı zemini | #D1D1D6 / #E5E5EA / #FFFFFF (kartla aynı) | #38383A / #38383A / #1C1C1E |
| Satır üzerine gelme zemini | #F2F2F7 | #2C2C2E |
| Metin / soluk metin | #1D1D1F / #6C6C70 | #F5F5F7 / #98989D |
| Birincil (primary) | dolgu #0071E3, üzerine gelince #0066CC, düğme metni #FFFFFF; bağlantı #0066CC | dolgu #0A72E0, üzerine gelince #0A62C2, düğme metni #FFFFFF; bağlantı #409CFF |
| Yeşil anahtar | #34C759 | #30D158 |
| Tehlikeli düğme | metin #D70015, zemin #FEF3F2, üzerine gelince #FDE8E5, kenarlık yok; dolu kırmızı zemin yoktur | metin #FF8A84, zemin #3D2423, üzerine gelince #4D2C2A |
| Rozet: kırmızı (zemin / metin / nokta) | #FDECEA / #B3261E / #FF3B30 | #4A2A29 / #FFA39D / #FF453A |
| Rozet: amber | #FFF3DB / #8A5300 / #FF9F0A | #4A3A1E / #FFC25C / #FF9F0A |
| Rozet: yeşil | #E3F6E8 / #1B6E32 / #34C759 | #1F4A2B / #6FDC8C / #30D158 |
| Rozet: gri | #EBEBF0 / #55555B / #8E8E93 | #3A3A3C / #C7C7CC / #8E8E93 |
| Rozet biçimi | 6 px renk noktası + metin, 12 px / 600, tam yuvarlak (hap) köşe; yalnız kırmızı, amber, yeşil ve gri vardır; renk sınıfsız rozet (Filament'in varsayılan grisi) gri çizilir, bu yüzden uygulamada rengi verilmemiş rozet olmaz | aynı |
| Filtre çipi | gri dolgulu, tam yuvarlak köşe, 14 px, nokta yok; "×" düğmesi 24 px hedef | aynı |
| Yazı ölçeği | sayfa başlığı 34/700 (−0,022em), alt başlık 15, form penceresi başlığı 17/600, büyük tutar 26/600 (−0,02em), bölüm başlığı 17/600, form etiketi 14/600, gövde ve tablo 14/400, yardımcı 12–13/400 | aynı |
| Tablo metni | 14 px / 400 | aynı |
| Kenarlık | kart ve tablolarda yok (zemin farkı ayırır); giriş alanı gri dolgulu, kenarlıksız | aynı |
| Boşluk | sayfa kenarı 24×28 px, bloklar arası 22 px, tablo hücresi 12×16 px, panel başlığı 14×18 px | aynı |
| Çerçeve ölçüleri | kenar menü 272 px, üst çubuk 56 px, arama kutusu 340 px, tablo arama kutusu 300 px, form penceresi 640 px, alt sekme çubuğu 56 px (+ güvenli alan), dar ekranda ekran genişliği − 2×16 px (iç boşluk 24 px) | aynı |
| Köşe | kart 12 px, açılır menü 12 px, form penceresi 18 px, giriş alanı 10 px, menü karesi 6 px, düğme ve rozet tam yuvarlak | aynı |
| Gölge | kartta `0 1px 2px rgba(0,0,0,.05)`; açılır menüde `0 8px 30px rgba(0,0,0,.14)`; pencerede `0 30px 80px rgba(0,0,0,.3)`; menü, çubuklar ve satırlarda yok. Bulanıklık (`backdrop-filter`) hiçbir yerde yok | kartta yok; menü ve pencerede daha koyu gölge |
| Odak halkası | 2 px #0066CC, 2 px boşlukla; kenar menüde de aynı | 2 px #409CFF |
| Gri skala (50–950) | #FFFFFF, #E5E5EA, #D1D1D6, #C7C7CC, #AEAEB2, #8E8E93, #6C6C70, #48484A, #38383A, #1C1C1E, #000000 | aynı skala |
| Primary mavi skala (50–950) | #F0F7FF, #DCEBFC, #B9D8FA, #8BBDF5, #409CFF, #0071E3, #0066CC, #0057AD, #0A4A91, #0B3B73, #082B55 | aynı skala |

Skalalar tablodaki değerlere sabitlidir. Gri skalada 50 tablo başlığı zemini, 100 satır ayırıcı, 200 çizgi, 600 soluk metin, 800 koyu çizgi, 900 koyu panel ve 950 koyu sayfa zeminidir. Primary skalada 500 düğme dolgusu, 600 üzerine gelme ve bağlantı, 400 koyu temadaki bağlantıdır. Durum paletleri (danger = Red, warning = Amber, success = Green) Tailwind paletlerindendir; rozetlerin kesin tonları yukarıdaki rozet satırlarıdır.

## 7. Hedef tık sayıları (kabul ölçütü)

| Akış | Hedef |
| --- | --- |
| Paneli açıp bugünkü işleri görmek | 0 tık |
| Müşteriyi alan adıyla bulup kartını açmak | `Ctrl+K` + yazma + 1 |
| Herhangi bir sayfadan ödeme kaydetmek | 1 (Ödeme al) + müşteri seç + tutar + 1 (Kaydet) |
| Bugün listesinden gecikmiş borcun ödemesini almak | 1 (Ödeme al) + tutar + 1 |
| Bir borcu WhatsApp'tan hatırlatmak (Bugün'den ya da kartın borç satırından) | 1 (WhatsApp; mesaj hazır gelir, gönderme WhatsApp'ta) |
| Karttan hizmet eklemek | 1 + seçim + 1 |
| Gecikmiş tüm borçları görmek | 1 (Bugün'deki sayı kartı) |
| Sunucudan hesap çekmek | 1 (Sunucudan çek) + yeni paketlerin fiyatları (WHM) ya da başlangıç tarihi (cPanel) + gerekirse müşteri adı seçimi (varsayılan ana alan adı gelir) + 1 (Çek) |
| Kotası dolmak üzere hesabın kullanımını görmek (Bugün'den) | 1 (Kullanımı gör) |
| Karttaki hizmetin kullanımını görmek | 1 (uyarı rozeti) ya da 2 (hizmet adı → Kullanımı gör) |
| Hesabın kullanımını sunucudan hemen yenilemek | Kullanım penceresinde 1 (Şimdi yenile) |
| Sunucunun bayi toplamını ve bütün hesaplarını görmek | 1 ("Kullanım" sütununa tıklamak) |
| Katalogdaki hizmetten teklif hazırlayıp göndermek | 1 (Teklif oluştur) + alıcı + 1 (Kaydet) + 1 (Gönder) + 1 (Gönder penceresinde onay) |
| Gönderilmiş teklifi kabul etmek | 1 (Kabul et) + 1 (pencerede onay; tarih ve kısa ad varsayılanlarla gelir) |

## 8. Şu anki panelden farklar

- Açılış sayfası boş: "Bugün" sayfası yok. (G-4.15'te giderildi; G-5.4 buraya taşınmıştı.)
- Her sayfadan ödeme kaydetme ve alan adıyla arama yok. (Her sayfadan ödeme alma G-4.13'te giderildi; arama G-4.14'te.)
- Satır işlemleri "⋮" menülerinde gizli. (G-4.17'de giderildi.)
- Müşteri kartı tek sütun ve çok uzun; kişiler ve geçmiş kayıtlar işin önüne geçiyor. (G-4.16'da giderildi.)
- Menü sırası ve adları sıklığa göre değil; "Tahsilat" grubu yok. (G-4.13'te giderildi.)
- Koyu tema ve varsayılan Filament görünümü; marka rengi, logo, durum rengi kuralları yok. (G-4.12'de giderildi.)

## 9. Kaynaklar

- Nielsen Norman Group, *Top 10 Application-Design Mistakes* (geri bildirim, tutarlılık, hata mesajları, varsayılanlar, etiketsiz simgeler, modal aşırı kullanımı, anlamsız bilgi, "çöp çekmecesi" menüler, tehlikeli düğme yakınlığı): https://www.nngroup.com/articles/top-10-application-design-mistakes/
- Nielsen Norman Group, *Data Tables: Four Major User Tasks*: https://www.nngroup.com/articles/data-tables/
- WHMCS müşteri özeti sayfası (hosting sektöründeki yaygın düzen: özet, kişiler, hızlı işlemler bir arada): https://docs.whmcs.com/8-13/clients/client-profile/summary-tab/
- Komut paleti (`Ctrl+K`) deseni: Linear, Notion, VS Code gibi araçlardaki yaygın kullanım.
