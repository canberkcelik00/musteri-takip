# Arayüz ilkeleri

Bu dosya panelin nasıl daha kolay kullanılacağına dair kuralları toplar: ne nerede durur, nereden erişilir, nasıl görünür. G-4.12–G-4.18'in kabul ölçütüdür.

**Ölçüt:** Her günlük iş, Excel'de yapmaktan daha az tık ve daha az düşünmeyle yapılabilmeli.

## 1. Kullanıcı ve görevler

Tek kullanıcı var: işletme sahibi. Panel bir veri ambarı değil, iş listesidir; açıldığında "bugün ne yapmalıyım" sorusunu cevaplamalı.

| Görev | Sıklık | Hedef erişim |
| --- | --- | --- |
| Bugün ne yapmalıyım (gecikmişler, yaklaşan yenilemeler, ücretsiz kararlar) | Her gün | 0 tık: açılış sayfası |
| Müşteri bulmak (kısa ad veya alan adıyla) | Her gün | 1 tık + yazma, her sayfadan |
| Ödeme almak | Haftada birkaç | Her sayfadan 1 tıkla başlar; toplam 3 tık + tutar |
| Müşteri kartına bakmak | Her gün | Arama sonucundan 1 tık |
| Hizmet eklemek | Haftalık | Kartın üstünden 1 tık |
| Yeni müşteri eklemek | Aylık | Global "Yeni" menüsünden 1 tık |
| Borcu düzenlemek, bölmek, iptal etmek | Nadir | Borç satırından 2 tık |
| Katalog, paket, ayarlar | Nadir | Menünün altında |

**Kural 1 — Sıklık yakınlıktır:** Bir iş ne kadar sık yapılıyorsa o kadar az tıkla ve o kadar görünür yerden erişilir. Nadir işler menünün altına ve ikinci düzeye iner.

## 2. Genel ilkeler

Araştırmadan (başta Nielsen Norman Group'un uygulama tasarımı bulguları) bu projeye uyarlanan kurallar:

1. **Tutarlılık (düğme ve mesaj etiketleri için):** Aynı iş her ekranda aynı kelimeyle, aynı yerde ve aynı görünümle durur. "Ödeme al" bir yerde "Ödeme kaydet", başka yerde "Tahsilat" olamaz.
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
12. **Klavye:** Her sayfadan `Ctrl+K` ile arama/komut kutusu açılır; müşteriye gitmek ve ödeme almak fare kullanmadan yapılabilir.

## 3. Bilgi mimarisi (menü)

Menü en fazla altı ana öğe taşır ve sıklık sırasıyla dizilir.

| Sıra | Öğe | İçerik |
| --- | --- | --- |
| 1 | Bugün | Açılış sayfası: yapılacaklar ve özet sayılar |
| 2 | Müşteriler | Liste ve kartlar |
| 3 | Tahsilat | "Açık borçlar" ve "Ödemeler" aynı grupta |
| 4 | Katalog | Hizmetler, Paketler (grup, kapalı başlar) |
| 5 | Ayarlar | Genel ayarlar, Ödeme yöntemleri (en altta) |

**Üst çubuk (her sayfada aynı yerde):**

- Solda: arama kutusu (`Ctrl+K`). Kısa ad, ticari ünvan, ilgili kişi ve alan adıyla arar; müşteriler çoğu zaman alan adıyla anılır ("logi.com.tr'nin hosting'i"). (Arama davranışı G-4.14'te.)
- Sağda: "Ödeme al" birincil düğmesi (en sık iş her sayfadan tek tık) ve "Yeni" menüsü (Müşteri, Elle borç). Dar ekranda arama önce daralır, "Yeni" menüsü `sm` altında gizlenir; "Ödeme al" her genişlikte kalır.

## 4. Yer kuralları: ne nerede durur

| İşlem türü | Yeri | Örnek |
| --- | --- | --- |
| Her yerden gereken iş | Üst çubuk | Ara, Ödeme al, Yeni |
| Sayfanın ana işi | Sayfa başlığının sağı, tek birincil düğme | Müşteriler: "Yeni müşteri" |
| Kayda ait sık iş | Satırda, metinli, en fazla iki | Borç: "Ödeme al", "Düzenle" |
| Kayda ait nadir iş | Kaydın kartında/sayfasında, adlandırılmış bölümde | Hizmet: "Ücretsiz yap", "Böl" |
| Tehlikeli iş | En sonda, ayrı, kırmızı, onaylı | Sil, Hizmeti iptal et |
| Ayar | Yalnızca Ayarlar menüsünde | Yenileme gün sayısı |

**Birincil düğme kuralı:** Her ekranda tek bir birincil (dolu renkli) düğme olur — üst çubuktaki "Ödeme al". Sayfanın kendi ana işi (ör. "Yeni müşteri", "Borç ekle") aynı yerde durur ama ikincil (gri) görünür; kartın "Ödeme al" ve "Hizmet ekle" düğmeleri de ikincildir.

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

- **Başlık şeridi (tam genişlik):** Kısa ad ve ticari ünvan; büyük yazıyla açık borç ve alacak bakiyesi; sağda ikincil "Ödeme al" ve "Hizmet ekle" (birincil düğme üst çubuktaki "Ödeme al"dır, §4).
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
- **Marka rengi (#ff3800):** Yalnızca logo simgesinde ve seçili menü öğesinin sol kenar çizgisinde durur. Birincil düğmede veya durum rozetlerinde kullanılmaz; marka rengi kırmızı/turuncu durum renkleriyle karışır.
- **`primary` (Filament rengi):** Birincil düğme ve etkileşim öğeleri (bağlantı, onay kutusu, odak çerçevesi) içindir; koyu lacivert. Marka veya durum rengi değildir.
- **Durum renkleri:** Sabit ve yalnızca aşağıdaki dört anlam için kullanılır; `info` hiç kullanılmaz, renksiz (rozetsiz düz metin) beşinci bir "normal durum" seçeneğidir.

  | Renk | Filament adı | Anlam |
  | --- | --- | --- |
  | Kırmızı | `danger` | Gecikmiş borç; Sil/İptal et |
  | Turuncu | `warning` | Kısmi ödenmiş borç; vadesi yaklaşan borç; yenilemesi yaklaşan hizmet |
  | Yeşil | `success` | Ödendi |
  | Gri | `gray` | İptal edilmiş, arşivlenmiş |
  | Renksiz | rozet yok | Açık borç (vadesi uzak), aktif hizmet (bitişi uzak) |

- **"Yaklaşan" tanımı:** Borç için vade bugün ile bugün+7 gün arasında (dahil); hizmet için bitiş bugün ile bugün+yenileme gün sayısı arasında (dahil, Ayarlar'daki "yenileme gün sayısı"na bağlı). Görüntüleme durumudur; veritabanında veya borç/hizmet kayıtlarında saklanmaz, her gösterimde hesaplanır.
- **Yazı:** Tek yazı tipi (Inter, yerelden yüklenir — dış bağlantı yok), sayılarda eş genişlikte rakamlar; tablo metni 14 px, başlıklar sade.
- **Yoğunluk:** Tablolarda kompakt satırlar (Excel'e yakın); formlarda rahat boşluk.
- **Logo ve ad:** Sol üstte firma logosu (yer tutucu simge) ve "Müşteri Takip".

## 7. Hedef tık sayıları (kabul ölçütü)

| Akış | Hedef |
| --- | --- |
| Paneli açıp bugünkü işleri görmek | 0 tık |
| Müşteriyi alan adıyla bulup kartını açmak | Ctrl+K + yazma + 1 |
| Herhangi bir sayfadan ödeme almak | 1 (Ödeme al) + müşteri seç + tutar + 1 (Kaydet) |
| Bugün listesinden gecikmiş borcun ödemesini almak | 1 (Ödeme al) + tutar + 1 |
| Karttan hizmet eklemek | 1 + seçim + 1 |
| Gecikmiş tüm borçları görmek | 1 (Bugün'deki sayı kartı) |

## 8. Şu anki panelden farklar

- Açılış sayfası boş: "Bugün" sayfası yok (G-4.15'te yapılacak).
- ~~Her sayfadan ödeme alma yok.~~ (G-4.13'te yapıldı.)
- Alan adıyla arama yok (G-4.14'te yapılacak).
- Satır işlemleri "⋮" menülerinde gizli.
- Müşteri kartı tek sütun ve çok uzun; kişiler ve geçmiş kayıtlar işin önüne geçiyor.
- ~~Menü sırası ve adları sıklığa göre değil; "Tahsilat" grubu yok.~~ (G-4.13'te yapıldı.)
- ~~Koyu tema ve varsayılan Filament görünümü; marka rengi, logo, durum rengi kuralları yok.~~ (G-4.12'de yapıldı.)

## 9. Kaynaklar

- Nielsen Norman Group, *Top 10 Application-Design Mistakes* (geri bildirim, tutarlılık, hata mesajları, varsayılanlar, etiketsiz simgeler, modal aşırı kullanımı, anlamsız bilgi, "çöp çekmecesi" menüler, tehlikeli düğme yakınlığı): https://www.nngroup.com/articles/top-10-application-design-mistakes/
- Nielsen Norman Group, *Data Tables: Four Major User Tasks*: https://www.nngroup.com/articles/data-tables/
- WHMCS müşteri özeti sayfası (hosting sektöründeki yaygın düzen: özet, kişiler, hızlı işlemler bir arada): https://docs.whmcs.com/8-13/clients/client-profile/summary-tab/
- Komut paleti (`Ctrl+K`) deseni: Linear, Notion, VS Code gibi araçlardaki yaygın kullanım.
