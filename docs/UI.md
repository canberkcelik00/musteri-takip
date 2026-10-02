# Arayüz ilkeleri

Bu sekme, panelin nasıl daha kolay kullanılacağına dair kuralları toplar: ne nerede durur, nereden erişilir, nasıl görünür. Onaylandığında repoya `docs/UI.md` olarak girer ve G-4.12–G-4.18'in kabul ölçütü olur.

**Ölçüt:** Her günlük iş, Excel'de yapmaktan daha az tık ve daha az düşünmeyle yapılabilmeli.

## 1. Kullanıcı ve görevler

Tek kullanıcı var: işletme sahibi. Panel bir veri ambarı değil, iş listesidir; açıldığında "bugün ne yapmalıyım" sorusunu cevaplamalı.

| Görev | Sıklık | Hedef erişim |
| --- | --- | --- |
| Bugün ne yapmalıyım (gecikmişler, yaklaşan yenilemeler, ücretsiz kararlar) | Her gün | 0 tık: açılış sayfası |
| Müşteri bulmak (kısa ad veya alan adıyla) | Her gün | 1 tık + yazma, her sayfadan |
| Ödeme kaydetmek | Haftada birkaç | Her sayfadan 1 tıkla başlar; toplam 3 tık + tutar |
| Müşteri kartına bakmak | Her gün | Arama sonucundan 1 tık |
| Hizmet eklemek | Haftalık | Kartın üstünden 1 tık |
| Yeni müşteri eklemek | Aylık | Global "Yeni" menüsünden 1 tık |
| Borcu düzenlemek, bölmek, iptal etmek | Nadir | Borç satırından 2 tık |
| Katalog, paket, ayarlar | Nadir | Menünün altında |

**Kural 1 — Sıklık yakınlıktır:** Bir iş ne kadar sık yapılıyorsa o kadar az tıkla ve o kadar görünür yerden erişilir. Nadir işler menünün altına ve ikinci düzeye iner.

## 2. Genel ilkeler

Araştırmadan (başta Nielsen Norman Group'un uygulama tasarımı bulguları) bu projeye uyarlanan kurallar:

1. **Tutarlılık:** Aynı iş her ekranda aynı kelimeyle, aynı yerde ve aynı görünümle durur. "Ödeme al" bir yerde "Ödeme kaydet", başka yerde "Tahsilat" olamaz.
2. **Varsayılanlar dolu gelir:** Her formda en olası değer hazır gelir (tarih bugün, para birimi açık borçtan, yöntem son kullanılan). Kullanıcı yalnızca farklı olanı yazar.
3. **Sistem durumu görünür:** Her işlemden sonra ne olduğu tek cümleyle söylenir ("1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi"). Bekleme 2 saniyeyi aşarsa yükleme göstergesi çıkar.
4. **Hata mesajı neden ve nasıl söyler:** "Bir hata oluştu" yasak. Her mesaj sorunu ve çözümü söyler ("Önce müşterinin hizmetlerini iptal edin.").
5. **Etiketsiz simge yok:** Her düğmede metin vardır; simge yalnızca metnin yanında yardımcıdır.
6. **"⋮" çöp çekmecesi yok:** İçinde ne olduğu belli olmayan "⋮" veya "Diğer" menüleri kullanılmaz. Sık işlemler satırda metinli durur; nadir işlemler kaydın kendi sayfasında/kartında, adlandırılmış bir bölümde durur.
7. **Tehlikeli işlem ayrı durur:** Sil, İptal et gibi işlemler Kaydet'in yanında değil, ayrı, kırmızı ve onaylıdır. Yanlış tıklama ihtimali tasarımla düşürülür.
8. **Bağlamı kapatma:** Formlar ortada ekranı kapatan pencere yerine yandan açılan panelde açılır; arkadaki kart görünür kalır (ödeme alırken açık borçları görmek gibi).
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
| 3 | **Tahsilat** | "Açık borçlar" ve "Ödemeler" aynı grupta |
| 4 | **Katalog** | Hizmetler, Paketler (grup, kapalı başlar) |
| 5 | **Ayarlar** | Genel ayarlar, Ödeme yöntemleri (en altta) |

**Üst çubuk (her sayfada aynı yerde):**

- Solda: arama kutusu (`Ctrl+K`). Kısa ad, ticari ünvan, ilgili kişi ve **alan adıyla** arar; müşteriler çoğu zaman alan adıyla anılır ("logi.com.tr'nin hosting'i").
- Sağda: **"Ödeme al"** birincil düğmesi (en sık iş her sayfadan tek tık) ve **"Yeni"** menüsü (Müşteri, Elle borç).

## 4. Yer kuralları: ne nerede durur

| İşlem türü | Yeri | Örnek |
| --- | --- | --- |
| Her yerden gereken iş | Üst çubuk | Ara, Ödeme al, Yeni |
| Sayfanın ana işi | Sayfa başlığının sağı, ikincil düğme | Müşteriler: "Yeni müşteri" |
| Kayda ait sık iş | Satırda, metinli, en fazla iki | Borç: "Ödeme al", "Düzenle" |
| Kayda ait nadir iş | Kaydın kartında/sayfasında, adlandırılmış bölümde | Hizmet: "Ücretsiz yap", "Böl" |
| Tehlikeli iş | En sonda, ayrı, kırmızı, onaylı | Sil, Hizmeti iptal et |
| Ayar | Yalnızca Ayarlar menüsünde | Yenileme gün sayısı |

**Birincil düğme kuralı:** Her ekranda tek bir birincil (dolu renkli) düğme olur: üst çubuktaki "Ödeme al". Sayfa başlığındaki ana iş ve karttaki düğmeler ("Yeni müşteri", karttaki "Ödeme al", "Hizmet ekle" vb.) yerinde kalır ama ikincil (gri/çizgili) görünür.

## 5. Ekran kuralları

### Bugün (açılış sayfası)

- **Üst satır, dört sayı:** Toplam alacak (para birimi başına), Gecikmiş tutar ve adet, Bu ay vadesi gelen, 30 gün içinde yenilenecek hizmet sayısı. Her sayı tıklanınca ilgili süzülmüş listeye gider.
- **Altında "Yapılacaklar" listesi,** öncelik sırasıyla: gecikmiş borçlar (en eski üstte), 7 gün içinde vadesi gelenler, ücretsiz devam kararı bekleyenler. Her satırda doğrudan iş düğmesi vardır ("Ödeme al", "Ücretsiz devam et", "Ücretli yap"); karta gitmeden iş bitirilebilir.
- Liste boşsa "Bugün yapılacak bir şey yok" yazar.

### Müşteri listesi

- Sütunlar: Kısa ad (kalın) + ticari ünvan (alt satırda, soluk), Açık borç (sağa hizalı; gecikmiş varsa kırmızı), En yakın yenileme, Aktif hizmet sayısı.
- Satıra tıklamak kartı açar; satırda ayrıca "Ödeme al" kısayolu vardır.
- Varsayılan sıralama: gecikmiş borcu olanlar üstte, sonra kısa ad.

### Müşteri kartı

İki sütun; geniş sol sütun işe, dar sağ sütun bilgiye ayrılır.

- **Başlık şeridi (tam genişlik):** Kısa ad ve ticari ünvan; büyük yazıyla açık borç ve alacak bakiyesi; sağda "Ödeme al" ve "Hizmet ekle", ikisi de ikincil (ekranın tek birincil düğmesi üst çubuktaki "Ödeme al").
- **Sol sütun, sırayla:**
  - Hizmetler: aktifler, kompakt satırlar (ad, alan adı, bitiş, fiyat, durum). İptal edilmişler katlanmış "İptal edilenler (2)" altında.
  - Açık borçlar: yalnızca açıklar; "Tüm borçlar" bağlantısı geçmişi açar.
  - Son ödemeler: son 5; "Tüm ödemeler" bağlantısı.
- **Sağ sütun:** Birincil kişi (telefon ve e-posta tıklanınca aranır/yazılır), diğer kişiler, fatura bilgileri, notlar. Düzenleme buradan.
- **En altta, ayrı:** Arşivle, Sil.

### Tablolar (tüm listeler)

- Tablolar dört işi desteklemeli: kayıt bulmak (arama, filtre), karşılaştırmak (hizalı sütunlar), tek kaydı görmek/düzenlemek ve kayıt üzerinde işlem yapmak.
- Tutarlar sağa hizalı ve eş genişlikte rakamlarla yazılır; tarihler gg.aa.yyyy.
- Durum her zaman renk **ve** metinle gösterilir (renk körlüğü ve yazdırma için).
- Boş tablo ne yapılacağını söyler ve iş düğmesini gösterir ("Henüz hizmet yok — Hizmet ekle").
- Varsayılan filtre görünür bir çip olarak durur ("Durum: Açık ×"); gizli filtre yoktur.

### Formlar

- Tek sütun, etiket alanın üstünde, zorunlu alanlar işaretli.
- Varsayılanlar dolu; nadir alanlar katlanmış "Ayrıntılar" bölümünde.
- Kaydet sağ altta birincil; Vazgeç onun solunda ikincil; tehlikeli işlem formda yok.
- Hata alanın hemen altında, alanı nasıl düzelteceğini söyleyerek.

## 6. Görsel dil

- **Tema:** Açık tema varsayılan (uzun süre tablo okuma ve Excel alışkanlığı); koyu tema kullanıcı menüsünden seçilebilir.
- **Renk:** Marka rengi #ff3800 yalnızca logo simgesinde ve seçili menü çizgisinde; kırmızı/turuncu durum renkleriyle karışmaması için birincil düğmede kullanılmaz. Filament primary koyu lacivert: birincil düğme ve etkileşim öğeleri (bağlantı, onay kutusu); marka veya durum rengi değildir. Durum renkleri sabit ve başka işte kullanılmaz: kırmızı (danger) = gecikmiş/tehlikeli, turuncu (warning = Amber) = yaklaşan/kısmi, yeşil (success) = ödendi, gri (gray) = iptal/arşiv. Normal durum (vadesi uzak açık borç, bitişi uzak aktif hizmet) rozetsiz düz metindir; info kullanılmaz. "Yaklaşan": borçta 7 gün içinde vadesi gelen, hizmette ayarlardaki yenileme gün sayısı içinde bitecek; tanım modelde tek yerde durur.
- **Yazı:** Tek yazı tipi Inter (yerel sunucudan, dış bağlantı yok), sayılarda eş genişlikte rakamlar; tablo metni 14 px, başlıklar sade.
- **Yoğunluk:** Tablolarda kompakt satırlar (Excel'e yakın); formlarda rahat boşluk.
- **Logo ve ad:** Sol üstte logo simgesi (#ff3800) ve "Müşteri Takip"; şimdilik yer tutucu simge, firma logosu gelince değiştirilir.
- **Tasarım tokenları:** Seçilen yön C · Lacivert çerçeve ([tasarım tuvali](https://claude.ai/artifact/Ev7Y2ZP2hbn3jhv47Q7UP6)). Değerler aşağıdaki tabloda; koyu tema değerleri G-4B.2'de doğrulandı: tüm metin/zemin çiftleri iki temada da en az 4,5:1 (`DesignTokensTest`). Değerler kodda tek yerde tanımlı: gri ve primary skalası `AdminPanelProvider::colors()` içinde (Filament düğme metni gibi kontrast seçimlerini bu paletten hesaplar); diğer tüm tokenlar `resources/css/filament/panel/theme.css` başındaki token bloğunda `--mt-*` değişkenleri olarak. Bileşen stilleri yalnızca bu değişkenleri ve palet sınıflarını kullanır; sabit renk kodu yazılmaz.

| Token | Açık tema | Koyu tema |
| --- | --- | --- |
| Kenar menü zemini | #0F1D33 | #0B1626 |
| Kenar menü metni / grup başlığı | #C3CCD9 / #8A97AB | aynı |
| Seçili menü öğesi | zemin #1B2D4B, metin #FFFFFF, solda 3 px #FF3800 çizgi | aynı |
| Sayfa zemini | #F4F6F9 | #0E1520 |
| Panel ve üst çubuk | #FFFFFF, kenarlık #E0E4EA | #151E2B, kenarlık #253142 |
| Satır ayırıcı / tablo başlığı zemini | #EEF0F3 / #F8F9FB | #1E2836 / #19222F |
| Metin / soluk metin | #121A27 / #566173 | #E6EAF0 / #9AA5B5 |
| Birincil (primary) | #1D3A6A, üzerine gelince #152C52 | #6F93D6, düğme metni #0B1626 |
| Tehlikeli düğme | metin #B42318, kenarlık #F1C4C0, zemin beyaz | metin #F38B82, kenarlık #5C2A2A |
| Rozet: kırmızı (zemin / metin / nokta) | #FDECEC / #9F1A1D / #D92D2D | zemin noktanın %16'sı (#151E2B üzerine: #34202B), metin #F5A3A3, nokta #D92D2D |
| Rozet: amber | #FDF2D8 / #7F4C00 / #D98A00 | zemin noktanın %16'sı (#151E2B üzerine: #342F24), metin #F2C46B, nokta #D98A00 |
| Rozet: yeşil | #E5F3E9 / #1D6633 / #2F9E55 | zemin noktanın %16'sı (#151E2B üzerine: #193232), metin #8FD3A6, nokta #2F9E55 |
| Rozet: gri | #EEF0F3 / #4A5361 / #8B94A1 | zemin #232D3B, metin #B4BDC9 |
| Rozet biçimi | 6 px renk noktası + metin, 12 px / 600, köşe 4 px | aynı |
| Yazı ölçeği | sayfa başlığı 26/700, büyük tutar 30/700 (−0,01em), bölüm başlığı 15/600, gövde ve tablo 14/400, yardımcı 12–13/400 | aynı |
| Tablo metni | 14 px / 400 | aynı |
| Kenarlık | 1 px düz, renk #E0E4EA | 1 px düz, renk #253142 |
| Boşluk | sayfa kenarı 24×28 px, bloklar arası 18 px, tablo hücresi 9×14 px, panel başlığı 12×16 px | aynı |
| Çerçeve ölçüleri | kenar menü 232 px, üst çubuk 62 px, arama kutusu 380 px | aynı |
| Köşe | panel, düğme, giriş alanı 6 px; rozet 4 px | aynı |
| Gölge | yok; ayrım kenarlıkla | yok |
| Odak halkası | 2 px #1D3A6A, 2 px boşlukla; kenar menüde #6F93D6 | 2 px #6F93D6 (kenar menüde de aynı) |
| Gri skala (50–950) | #F8F9FB, #EEF0F3, #E0E4EA, #C3CCD9, #8B94A1, #6B7585, #566173, #3B4657, #253142, #151E2B, #0E1520 | aynı skala |
| Primary lacivert skala (50–950) | #F1F5FB, #E1E9F6, #C6D5EE, #9DB6E2, #6F93D6, #3F68B0, #1D3A6A, #152C52, #10233F, #0C1A30, #08111F | aynı skala |

Skalalar tablodaki değerlere sabitlidir. Gri skalada 50 tablo başlığı zemini, 100 satır ayırıcı, 200 kenarlık, 600 soluk metin, 800 koyu kenarlık, 900 koyu panel ve 950 koyu sayfa zeminidir. Primary skalada 600 düğme, 700 üzerine gelme ve 400 koyu temadaki primary'dir. Durum paletleri (danger = Red, warning = Amber, success = Green) Filament'in hazır paletleridir; rozetlerin kesin tonları yukarıdaki rozet satırlarıdır.

## 7. Hedef tık sayıları (kabul ölçütü)

| Akış | Hedef |
| --- | --- |
| Paneli açıp bugünkü işleri görmek | 0 tık |
| Müşteriyi alan adıyla bulup kartını açmak | `Ctrl+K` + yazma + 1 |
| Herhangi bir sayfadan ödeme kaydetmek | 1 (Ödeme al) + müşteri seç + tutar + 1 (Kaydet) |
| Bugün listesinden gecikmiş borcun ödemesini almak | 1 (Ödeme al) + tutar + 1 |
| Karttan hizmet eklemek | 1 + seçim + 1 |
| Gecikmiş tüm borçları görmek | 1 (Bugün'deki sayı kartı) |

## 8. Şu anki panelden farklar

- Açılış sayfası boş: "Bugün" sayfası yok (G-5.4 ana panel buraya çekilmeli).
- Her sayfadan ödeme kaydetme ve alan adıyla arama yok. (Her sayfadan ödeme alma G-4.13'te giderildi; arama G-4.14'te.)
- Satır işlemleri "⋮" menülerinde gizli.
- Müşteri kartı tek sütun ve çok uzun; kişiler ve geçmiş kayıtlar işin önüne geçiyor.
- Menü sırası ve adları sıklığa göre değil; "Tahsilat" grubu yok. (G-4.13'te giderildi.)
- Koyu tema ve varsayılan Filament görünümü; marka rengi, logo, durum rengi kuralları yok. (G-4.12'de giderildi.)

## 9. Kaynaklar

- Nielsen Norman Group, *Top 10 Application-Design Mistakes* (geri bildirim, tutarlılık, hata mesajları, varsayılanlar, etiketsiz simgeler, modal aşırı kullanımı, anlamsız bilgi, "çöp çekmecesi" menüler, tehlikeli düğme yakınlığı): https://www.nngroup.com/articles/top-10-application-design-mistakes/
- Nielsen Norman Group, *Data Tables: Four Major User Tasks*: https://www.nngroup.com/articles/data-tables/
- WHMCS müşteri özeti sayfası (hosting sektöründeki yaygın düzen: özet, kişiler, hızlı işlemler bir arada): https://docs.whmcs.com/8-13/clients/client-profile/summary-tab/
- Komut paleti (`Ctrl+K`) deseni: Linear, Notion, VS Code gibi araçlardaki yaygın kullanım.
