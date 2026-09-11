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

**cPanel və Plesk reseller hesablarını tək WHMCS modulundan idarə edin.**

Tək modul, iki panel. Panel tipi hər server üçün avtomatik aşkarlanır — eyni məhsul həm cPanel,
həm də Plesk serveri saxlayan bir qrupa bağlana bilər.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-d%C9%99st%C9%99kl%C9%99nir-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-d%C9%99st%C9%99kl%C9%99nir-53BCE6?style=flat-square)

</div>

---

## 📑 Mündəricat

- [✨ Nə edir](#-nə-edir)
- [📋 Tələblər](#-tələblər)
- [🚀 Quraşdırma](#-quraşdırma)
- [🔍 Loglar və problemlərin həlli](#-loglar-və-problemlərin-həlli)
- [🧩 Bilinməsi lazım olanlar](#-bilinməsi-lazım-olanlar)
- [📄 Dəyişiklik jurnalı](#-dəyişiklik-jurnalı)

---

## ✨ Nə edir

| Xüsusiyyət | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Hesab yaratma | ✅ | ✅ |
| Dayandırma / bərpa etmə | ✅ | ✅ |
| Ləğv etmə | ✅ | ✅ |
| Parol dəyişikliyi | ✅ | ✅ |
| Paket / plan dəyişikliyi | ✅ | ✅ |
| Müştəri panelinə tək kliklə giriş | ✅ | ✅ |
| Disk və trafik istifadəsinin sinxronizasiyası | ✅ | ✅ |
| Server admini girişi (Log in to Server) | ✅ | — |
| Disk / trafik override-u (məhsul əsaslı) | ✅ | planı müəyyən edir |
| Dedicated IP | ✅ | planı müəyyən edir |

> 💡 **Reseller üçün hazırlanıb.** Bu, root və ya admin səlahiyyəti deyil; modul sizin reseller
> hesabınızın səlahiyyətləri ilə işləyir və açılan hesablar sizin kvotanıza yazılır.

---

## 📋 Tələblər

- **WHMCS** 7.8 və ya daha yeni
- **PHP** 7.2 – 8.4
- PHP genişləndirmələri: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;və ya&nbsp; **Plesk** (XML-API protokol 1.6.3.0+)

> ✅ Verilənlər bazasında cədvəl yaradılmır, cron tənzimləməsi tələb olunmur, composer asılılığı
> yoxdur. Quraşdırma sadəcə bir qovluğun kopyalanmasından ibarətdir.

---

## 🚀 Quraşdırma

### 1️⃣ Modulu yükləyin

`dnahosting` qovluğunu WHMCS quraşdırmanızdakı `modules/servers/` qovluğuna kopyalayın.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← bura
```

### 2️⃣ Serveri əlavə edin

**Configuration → System Settings → Servers → Add New Server**

| Sahə | Nə yazılmalıdır |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | Serverin ünvanı — əvvəlində `https://` **olmadan**, sonunda port **olmadan** |
| **Username** | Reseller istifadəçi adınız |
| **Password** | Reseller parolunuz *(token varsa məcburi deyil, yenə də daxil edin)* |
| **API Token / Access Hash** | cPanel-də WHM API token, Plesk-də API açarı |

Bu məlumatlar sifarişdən sonra sizə göndərilir. İstədiyiniz vaxt
**[Reseller Hosting səhifənizdən](https://dm.domainnameapi.com/hosting)** xidmətin yanındakı
**⚙️ dişli çarx işarəsinə** klikləyib **Kontrol Paneli** bölməsindən də baxa bilərsiniz.

![Server əlavə etmə ekranı](docs/images/sunucu-ekleme.png)

### 3️⃣ Bağlantını yoxlayın

**Go to Advanced Mode** → **Test Connection**

Məlumatlar düzgündürsə uğur mesajını görəcəksiniz. Sonra serveri **yadda saxlayın**.

> ⚠️ **Bağlantı alınmazsa ilk baxılacaq yer port sahəsidir.**
> cPanel `2087`, Plesk isə `8443` istifadə edir. Boş buraxsanız, modul panelə uyğun düzgün portu
> özü seçir; fərqli port istifadə edirsinizsə **Override with Custom Port** seçimini işarələyib
> əl ilə daxil edin.

### 4️⃣ Server qrupu yaradın

**Servers → Create New Group** ilə yeni qrup açıb serveri onun içinə salın və ya mövcud bir qrupa
əlavə edin. Məhsullar serverə birbaşa deyil, **qrup vasitəsilə** bağlanır.

### 5️⃣ Məhsulu tənzimləyin

Yeni məhsul yaradın və ya mövcud məhsulu redaktə edib **Module Settings** bölməsinə keçin:

| Parametr | Dəyər |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | Yaratdığınız qrup |
| **Panel Type** | `Auto` *(və ya serverinizi bilirsinizsə birbaşa seçin)* |
| **Package / Plan** | Reseller panelinizdə təyin edilmiş paketin adı |

> 💡 **cPanel-də paket prefiksini yazmayın.** Paneldə `bakcay328_paket2` kimi görünən paket üçün
> məhsulda yalnız `paket2` yazmağınız kifayətdir — modul `kullaniciadi_` prefiksini özü həll edir.

**Yadda saxlayın — modul istifadəyə hazırdır.** 🎉

**‼️Bu andan etibarən WHMCS-in bütün axınlarında modul vasitəsilə cPanel və Plesk reseller hesablarını idarə edə bilərsiniz. Yaratma, dayandırma və silmə axınları tamamilə WHMCS-in nəzarəti altındadır.**

---

## 🔍 Loglar və problemlərin həlli

İki ayrı log yeri var və hər biri fərqli davranır:

| Log | Nə vaxt yazır | Nə saxlayır |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Həmişə** | Uğursuz olan hər əməliyyat — `dnahosting:` prefiksi, xidmət nömrəsi və domen adı ilə |
| **Module Log**<br>*Utilities → Logs → Module Log* | Yalnız **Module Debug Mode** aktiv olduqda | Panelə gedən hər sorğu və qayıdan cavab |

> 💡 Module Debug Mode: **Setup → General Settings → Other → Module Debug Mode**.
> Problemi yenidən yaratmazdan əvvəl aktiv edin, sonra söndürün. `note:` prefiksli sətirlər sorğu
> deyil, modulun öz izahıdır.

> 🔐 API tokenləri və müştəri parolları loglara **açıq mətn kimi yazılmır**.

### Tez-tez rast gəlinən xətalar

| Əlamət | Səbəb və həlli |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Hər iki panel də cavab vermədi. Portu və tokeni yoxlayın, ya da məhsul parametrlərində **Panel Type**-ı açıq şəkildə seçin |
| *WHM refused this login* | İstifadəçi reseller deyil, ya da token cPanel interfeysində yaradılıb. Token **WHM-də** yaradılmalıdır |
| *Server returned HTTP 3xx (redirect)* | Səhv port, ya da panel giriş səhifəsinə yönləndirir |
| Plesk **11003** | API açarı başqa bir IP üçün yaradılıb — yenidən yaradın |
| Plesk **1010** | Panel ardıcıl uğursuz cəhdlərdən sonra IP-ni məhdudlaşdırır; bir neçə dəqiqə gözləyin |
| Plesk **2204** | Panel sorğunu qəbul edib öz veb serverini konfiqurasiya edərkən çökdü — bu, server tərəfli problemdir |

---

## 🧩 Bilinməsi lazım olanlar

<details>
<summary><b>Panel fərqləri</b></summary>

<br>

- **Disk Quota / Bandwidth** məhsul parametrləri yalnız **cPanel**-də tətbiq olunur. Plesk-də
  limitləri xidmət planı müəyyən edir; sahələr doldurulsa belə nəzərə alınmır və modul loquna
  qeyd düşülür.
- **Dedicated IP** yalnız cPanel-də keçərlidir.
- **Plesk-də tək kliklə giriş** müştəri sahəsindəki *Log in to Panel* düyməsi ilə işləyir. Plesk
  yönləndirmə əsaslı sessiya açmanı dəstəkləmədiyi üçün server siyahısındakı *Log in to Server*
  düyməsi Plesk serverlərində istifadə oluna bilmir.

</details>

<details>
<summary><b>Keşləmə</b></summary>

<br>

Hər server üçün iki məlumat **yeddi gün** keşlənir:

- Hansı paneli işlətdiyi
- Danışdığı XML-API protokol versiyası

Bunlar normal iş axınında dəyişmir və hər sorğuda yenidən aşkarlamaq lazımsız gediş-gəlişə səbəb olur.

Keş aşağıdakı hallarda etibarsız sayılır:

- **Test Connection** hər zaman keşi təmizləyir və yenidən aşkarlayır
- Serverin ünvanı, portu, istifadəçi adı və ya tokeni dəyişəndə avtomatik
- Yeddi günün sonunda

> 🔐 Keşdə heç bir kimlik məlumatı saxlanmır; token yalnız açar yaradılarkən hash kimi istifadə
> olunur.

</details>

<details>
<summary><b>Məhsul parametrlərinin sırası</b></summary>

<br>

WHMCS məhsul parametrlərini **mövqeyə görə** saxlayır (`configoption1..5`). Buna görə də modulun
parametr siyahısına yalnız **sona** əlavə etmək olar:

| # | Parametr |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Araya əlavə etmək və ya sıranı dəyişmək mövcud bütün məhsullarda yazılmış dəyərləri səssizcə
sürüşdürür.

</details>

---

## 📄 Dəyişiklik jurnalı

Versiya-versiya dəyişikliklər [CHANGELOG.md](CHANGELOG.md) faylındadır.

---

<div align="center">

**DNA Reseller Hosting** · WHMCS üçün cPanel və Plesk reseller modulu

[domainnameapi.com](https://www.domainnameapi.com) · [Reseller paneli](https://dm.domainnameapi.com/hosting)

</div>
