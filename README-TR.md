<div align="center">
  <a href="README-TR.md">TR <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/TR.png" alt="TR" height="20" /></a>
  <a href="README.md"> | EN <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/US.png" alt="EN" height="20" /></a>
  <a href="README-DE.md"> | DE <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/DE.png" alt="DE" height="20" /></a>
  <a href="README-SA.md"> | AR <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/SA.png" alt="AR" height="20" /></a>
  <a href="README-NL.md"> | NL <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/NL.png" alt="NL" height="20" /></a>
  <a href="README-AZ.md"> | AZ <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/AZ.png" alt="AZ" height="20" /></a>
  <a href="README-CN.md"> | CN <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/CN.png" alt="CN" height="20" /></a>
  <a href="README-FR.md"> | FR <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/FR.png" alt="FR" height="20" /></a>
  <a href="README-IT.md"> | IT <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/IT.png" alt="IT" height="20" /></a>
  <a href="README-RU.md"> | RU <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/RU.png" alt="RU" height="20" /></a>
  <a href="README-ES.md"> | ES <img style="padding-top: 8px" src="https://raw.githubusercontent.com/yammadev/flag-icons/master/png/ES.png" alt="ES" height="20" /></a>
</div>

<div align="center">

# DNA Reseller Hosting

**cPanel ve Plesk reseller hesaplarını tek WHMCS modülünden yönetin.**

Tek modül, iki panel. Panel tipi sunucu başına otomatik algılanır — aynı ürün hem cPanel
hem Plesk sunucusu barındıran bir gruba bağlanabilir.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-desteklenir-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-desteklenir-53BCE6?style=flat-square)

</div>

---

## 📑 İçindekiler

- [✨ Neler yapar](#-neler-yapar)
- [📋 Gereksinimler](#-gereksinimler)
- [🚀 Kurulum](#-kurulum)
- [🔍 Kayıtlar ve sorun giderme](#-kayıtlar-ve-sorun-giderme)
- [🧩 Bilinmesi gerekenler](#-bilinmesi-gerekenler)
- [📄 Değişiklik günlüğü](#-değişiklik-günlüğü)

---

## ✨ Neler yapar

| Özellik | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Hesap oluşturma | ✅ | ✅ |
| Askıya alma / geri alma | ✅ | ✅ |
| Sonlandırma | ✅ | ✅ |
| Şifre değişikliği | ✅ | ✅ |
| Paket / plan değişikliği | ✅ | ✅ |
| Müşteri paneline tek tıkla giriş | ✅ | ✅ |
| Disk & trafik kullanım senkronu | ✅ | ✅ |
| Sunucu yöneticisi girişi (Log in to Server) | ✅ | — |
| Disk / trafik override'ı (ürün bazlı) | ✅ | plan belirler |
| Dedicated IP | ✅ | plan belirler |

> 💡 **Reseller için tasarlandı.** Root veya admin yetkisi değildir; modül sizin reseller
> hesabınızın yetkileriyle çalışır ve açılan hesaplar sizin kotanıza işlenir.

---

## 📋 Gereksinimler

- **WHMCS** 7.8 veya üzeri
- **PHP** 7.2 – 8.4
- PHP eklentileri: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;veya&nbsp; **Plesk** (XML-API protokol 1.6.3.0+)

> ✅ Veritabanı tablosu oluşturulmaz, cron ayarı gerekmez, composer bağımlılığı yoktur.
> Kurulum yalnızca bir klasör kopyalamaktan ibarettir.

---

## 🚀 Kurulum

### 1️⃣ Modülü yükleyin

`dnahosting` klasörünü WHMCS kurulumunuzdaki `modules/servers/` dizinine kopyalayın.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← buraya
```

### 2️⃣ Sunucuyu ekleyin

**Configuration → System Settings → Servers → Add New Server**

| Alan | Ne yazılacak |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | Sunucu adresi — başında `https://` **olmadan**, sonunda port **olmadan** |
| **Username** | Reseller kullanıcı adınız |
| **Password** | Reseller şifreniz *(token varsa zorunlu değil, yine de girin)* |
| **API Token / Access Hash** | cPanel'de WHM API token, Plesk'te API key |

Bu bilgiler sipariş sonrası size iletilir. Dilediğiniz zaman
**[Reseller Hosting sayfanızdan](https://dm.domainnameapi.com/hosting)** hizmetin yanındaki
**⚙️ çark simgesine** tıklayıp **Kontrol Paneli** sekmesinden de görebilirsiniz.

![Sunucu ekleme ekranı](docs/images/sunucu-ekleme.png)

### 3️⃣ Bağlantıyı test edin

**Go to Advanced Mode** → **Test Connection**

Bilgiler doğruysa başarılı mesajını görürsünüz. Ardından sunucuyu **kaydedin**.

> ⚠️ **Bağlantı başarısız olursa ilk bakılacak yer port alanıdır.**
> cPanel `2087`, Plesk `8443` kullanır. Boş bıraktığınızda modül panele göre doğru portu kendisi
> seçer; farklı bir port kullanıyorsanız **Override with Custom Port** işaretleyip elle girin.

### 4️⃣ Sunucu grubu oluşturun

**Servers → Create New Group** ile yeni bir grup açıp sunucuyu içine alın, ya da mevcut bir gruba
ekleyin. Ürünler sunucuya doğrudan değil, **grup üzerinden** bağlanır.

### 5️⃣ Ürünü ayarlayın

Yeni ürün oluşturun veya mevcut ürünü düzenleyip **Module Settings** sekmesine geçin:

| Ayar | Değer |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | Oluşturduğunuz grup |
| **Panel Type** | `Auto` *(veya sunucunuzu biliyorsanız doğrudan seçin)* |
| **Package / Plan** | Reseller panelinizde tanımlı paketin adı |

> 💡 **cPanel'de paket önekini yazmayın.** Panelde `bakcay328_paket2` görünen paket için ürüne
> yalnızca `paket2` yazmanız yeterlidir — modül `kullaniciadi_` önekini kendisi çözer.

**Kaydedin — modül kullanıma hazır.** 🎉

**‼️Bu andan itibaren WHMCS'in tüm akışlarında modül üzerinden cPanel ve Plesk reseller hesapları yönetebilirsiniz. Oluşturma askıya alma silme akışları tamamen WHMCS'in kontrolü altındadır.**

---

## 🔍 Kayıtlar ve sorun giderme

İki ayrı kayıt yeri vardır ve farklı davranırlar:

| Kayıt | Ne zaman yazar | Ne içerir |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Her zaman** | Başarısız her işlem, `dnahosting:` önekiyle, servis numarası ve alan adıyla |
| **Module Log**<br>*Utilities → Logs → Module Log* | Yalnızca **Module Debug Mode** açıkken | Panele giden her istek ve dönen yanıt |

> 💡 Module Debug Mode: **Setup → General Settings → Other → Module Debug Mode**.
> Sorunu tekrar üretmeden önce açın, sonra kapatın. `note:` önekli satırlar istek değil, modülün
> kendi gerekçesidir.

> 🔐 API token'ları ve müşteri şifreleri kayıtlara **düz metin olarak yazılmaz**.

### Sık karşılaşılan hatalar

| Belirti | Sebep ve çözüm |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Her iki panel de yanıt vermedi. Port ve token'ı kontrol edin, ya da ürün ayarında **Panel Type**'ı açıkça seçin |
| *WHM refused this login* | Kullanıcı reseller değil, ya da token cPanel arayüzünde üretilmiş. Token **WHM'de** üretilmelidir |
| *Server returned HTTP 3xx (redirect)* | Yanlış port, ya da panel bir giriş sayfasına yönlendiriyor |
| Plesk **11003** | API anahtarı başka bir IP için üretilmiş — yeniden üretin |
| Plesk **1010** | Panel art arda başarısız denemeden sonra IP'yi kısıtlıyor; birkaç dakika bekleyin |
| Plesk **2204** | Panel isteği kabul edip kendi web sunucusunu yapılandırırken düştü — sunucu tarafı sorunudur |

---

## 🧩 Bilinmesi gerekenler

<details>
<summary><b>Panel farkları</b></summary>

<br>

- **Disk Quota / Bandwidth** ürün ayarları yalnızca **cPanel**'de uygulanır. Plesk'te limitleri
  servis planı belirler; alanlar doldurulsa bile yok sayılır ve modül log'una not düşülür.
- **Dedicated IP** yalnızca cPanel'de geçerlidir.
- **Plesk'te tek tıkla giriş** müşteri alanındaki *Log in to Panel* düğmesiyle çalışır. Plesk
  yönlendirme tabanlı oturum açmayı desteklemediği için sunucu listesindeki *Log in to Server*
  düğmesi Plesk sunucularında kullanılamaz.

</details>

<details>
<summary><b>Önbellekleme</b></summary>

<br>

Her sunucu için iki bilgi **yedi gün** önbelleklenir:

- Hangi paneli çalıştırdığı
- Konuştuğu XML-API protokol sürümü

Bunlar normal işleyişte değişmez ve her istekte yeniden tespit etmek gereksiz tur atmaya yol açar.

Önbellek şu durumlarda geçersiz olur:

- **Test Connection** her zaman temizler ve yeniden tespit eder
- Sunucunun adresi, portu, kullanıcı adı veya token'ı değişirse otomatik
- Yedi gün sonunda

> 🔐 Önbellekte hiçbir kimlik bilgisi saklanmaz; token yalnızca anahtar üretiminde hash olarak
> kullanılır.

</details>

<details>
<summary><b>Ürün ayarlarının sırası</b></summary>

<br>

WHMCS ürün ayarlarını **konuma göre** saklar (`configoption1..5`). Bu yüzden modülün ayar listesine
yalnızca **sona** ekleme yapılabilir:

| # | Ayar |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Araya ekleme veya sıra değişikliği, mevcut tüm ürünlerde kayıtlı değerleri sessizce kaydırır.

</details>

---

## 📄 Değişiklik günlüğü

Sürüm sürüm değişiklikler [CHANGELOG.md](CHANGELOG.md) dosyasında.

---

<div align="center">

**DNA Reseller Hosting** · WHMCS için cPanel & Plesk reseller modülü

[domainnameapi.com](https://www.domainnameapi.com) · [Reseller paneli](https://dm.domainnameapi.com/hosting)

</div>
