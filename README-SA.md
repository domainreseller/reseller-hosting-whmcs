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
</div>

<div align="center">

# DNA Reseller Hosting

**أدِر حسابات cPanel وPlesk الخاصة بالـ reseller من وحدة WHMCS واحدة.**

وحدة واحدة، لوحتا تحكم. يُكتشف نوع اللوحة تلقائيًا لكل خادم على حدة — ويمكن ربط المنتج نفسه
بمجموعة تضم خوادم cPanel وخوادم Plesk معًا.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-%D9%85%D8%AF%D8%B9%D9%88%D9%85-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-%D9%85%D8%AF%D8%B9%D9%88%D9%85-53BCE6?style=flat-square)

</div>

---

## ✨ ما الذي تقوم به

| الميزة | cPanel/WHM | Plesk |
|---|:---:|:---:|
| إنشاء الحسابات | ✅ | ✅ |
| التعليق / إلغاء التعليق | ✅ | ✅ |
| الإنهاء | ✅ | ✅ |
| تغيير كلمة المرور | ✅ | ✅ |
| تغيير الباقة / الخطة | ✅ | ✅ |
| الدخول إلى لوحة العميل بنقرة واحدة | ✅ | ✅ |
| مزامنة استهلاك القرص وحركة البيانات | ✅ | ✅ |
| دخول مدير الخادم (Log in to Server) | ✅ | — |
| تجاوز حدود القرص / حركة البيانات (على مستوى المنتج) | ✅ | تحدّدها الخطة |
| Dedicated IP | ✅ | تحدّدها الخطة |

> 💡 **مصمَّمة للـ reseller.** لا تتطلب صلاحيات root أو admin؛ تعمل الوحدة بصلاحيات حساب
> الـ reseller الخاص بك، وتُحتسب الحسابات التي تُنشأ ضمن حصتك.

---

## 📋 المتطلبات

- **WHMCS** 7.8 أو أحدث
- **PHP** 7.2 – 8.4
- إضافات PHP: `curl`، `json`، `libxml`، `simplexml`، `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;أو&nbsp; **Plesk** (بروتوكول XML-API 1.6.3.0+)

> ✅ لا تُنشئ الوحدة أي جدول في قاعدة البيانات، ولا تحتاج إلى إعداد cron، ولا توجد تبعيات composer.
> التثبيت لا يتعدّى نسخ مجلد واحد.

---

## 🚀 التثبيت

### 1️⃣ ثبِّت الوحدة

انسخ مجلد `dnahosting` إلى الدليل `modules/servers/` داخل تثبيت WHMCS لديك.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← هنا
```

### 2️⃣ أضف الخادم

**Configuration → System Settings → Servers → Add New Server**

| الحقل | ما الذي تكتبه |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | عنوان الخادم — **بدون** `https://` في البداية و**بدون** رقم منفذ في النهاية |
| **Username** | اسم مستخدم الـ reseller الخاص بك |
| **Password** | كلمة مرور الـ reseller *(ليست إلزامية عند وجود token، لكن يُفضَّل إدخالها)* |
| **API Token / Access Hash** | WHM API token في cPanel، وAPI key في Plesk |

تصلك هذه البيانات بعد إتمام الطلب. ويمكنك الاطلاع عليها في أي وقت من
**[صفحة Reseller Hosting الخاصة بك](https://dm.domainnameapi.com/hosting)** بالنقر على
**⚙️ أيقونة الترس** بجوار الخدمة ثم فتح تبويب **لوحة التحكم**.

![شاشة إضافة الخادم](docs/images/sunucu-ekleme.png)

### 3️⃣ اختبر الاتصال

**Go to Advanced Mode** ← **Test Connection**

إذا كانت البيانات صحيحة ستظهر لك رسالة نجاح. بعدها **احفظ** الخادم.

> ⚠️ **إذا فشل الاتصال فأول ما يجب فحصه هو حقل المنفذ.**
> يستخدم cPanel المنفذ `2087` ويستخدم Plesk المنفذ `8443`. إذا تركت الحقل فارغًا فستختار الوحدة
> المنفذ الصحيح بنفسها حسب اللوحة؛ أما إذا كنت تستخدم منفذًا مختلفًا فعلِّم
> **Override with Custom Port** وأدخله يدويًا.

### 4️⃣ أنشئ مجموعة خوادم

أنشئ مجموعة جديدة عبر **Servers → Create New Group** وأضف الخادم إليها، أو أضفه إلى مجموعة
قائمة. فالمنتجات لا تُربط بالخادم مباشرةً، بل **عبر المجموعة**.

### 5️⃣ اضبط المنتج

أنشئ منتجًا جديدًا أو عدِّل منتجًا قائمًا ثم انتقل إلى تبويب **Module Settings**:

| الإعداد | القيمة |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | المجموعة التي أنشأتها |
| **Panel Type** | `Auto` *(أو اخترها مباشرةً إذا كنت تعرف نوع خادمك)* |
| **Package / Plan** | اسم الباقة المعرَّفة في لوحة الـ reseller لديك |

> 💡 **لا تكتب بادئة الباقة في cPanel.** إذا كانت الباقة تظهر في اللوحة باسم `bakcay328_paket2`
> فيكفي أن تكتب في المنتج `paket2` فقط — إذ تتولى الوحدة إضافة البادئة `اسم_المستخدم_` بنفسها.

**احفظ الإعدادات — الوحدة جاهزة للاستخدام.** 🎉

**‼️من هذه اللحظة يمكنك إدارة حسابات cPanel وPlesk الخاصة بالـ reseller عبر الوحدة ضمن جميع مسارات عمل WHMCS. تبقى عمليات الإنشاء والتعليق والحذف تحت سيطرة WHMCS بالكامل.**

---

## 🔍 السجلات وحل المشكلات

هناك موضعان منفصلان للسجلات، ولكلٍّ منهما سلوك مختلف:

| السجل | متى يُكتب | ماذا يحتوي |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **دائمًا** | كل عملية فاشلة، مسبوقة بالبادئة `dnahosting:` مع رقم الخدمة واسم النطاق |
| **Module Log**<br>*Utilities → Logs → Module Log* | فقط عندما يكون **Module Debug Mode** مفعّلًا | كل طلب يُرسَل إلى اللوحة وكل رد يعود منها |

> 💡 مكان Module Debug Mode: **Setup → General Settings → Other → Module Debug Mode**.
> فعِّله قبل إعادة إنتاج المشكلة ثم أوقفه بعدها. والأسطر التي تبدأ بالبادئة `note:` ليست طلبات،
> بل تفسير الوحدة لما قامت به.

> 🔐 لا تُكتب API tokens ولا كلمات مرور العملاء في السجلات **كنص صريح**.

### الأخطاء الشائعة

| العَرَض | السبب والحل |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | لم تستجب أي من اللوحتين. تحقق من المنفذ ومن الـ token، أو حدِّد **Panel Type** صراحةً في إعدادات المنتج |
| *WHM refused this login* | المستخدم ليس reseller، أو أن الـ token أُنشئ من واجهة cPanel. يجب إنشاء الـ token **في WHM** |
| *Server returned HTTP 3xx (redirect)* | منفذ خاطئ، أو أن اللوحة تعيد التوجيه إلى صفحة تسجيل دخول |
| Plesk **11003** | مفتاح API أُنشئ لعنوان IP آخر — أعد إنشاءه |
| Plesk **1010** | تقيّد اللوحة عنوان IP بعد محاولات فاشلة متتالية؛ انتظر بضع دقائق |
| Plesk **2204** | قبِلت اللوحة الطلب ثم تعطّلت أثناء تهيئة خادم الويب الخاص بها — المشكلة من جانب الخادم |

---

## ⚙️ أمور ينبغي معرفتها

<details>
<summary><b>الفروق بين اللوحتين</b></summary>

<br>

- إعدادا المنتج **Disk Quota / Bandwidth** يُطبَّقان في **cPanel** فقط. أما في Plesk فتحدّد خطة
  الخدمة الحدود؛ ويُتجاهَل الحقلان حتى لو مُلئا، مع تسجيل ملاحظة بذلك في module log.
- **Dedicated IP** متاح في cPanel فقط.
- **الدخول بنقرة واحدة في Plesk** يعمل عبر زر *Log in to Panel* في منطقة العميل. ولأن Plesk لا
  يدعم تسجيل الدخول القائم على إعادة التوجيه، فإن زر *Log in to Server* في قائمة الخوادم غير
  قابل للاستخدام مع خوادم Plesk.

</details>

<details>
<summary><b>التخزين المؤقت</b></summary>

<br>

تُخزَّن معلومتان مؤقتًا لكل خادم لمدة **سبعة أيام**:

- أي لوحة تعمل عليه
- إصدار بروتوكول XML-API الذي يتحدث به

هاتان المعلومتان لا تتغيران في التشغيل الاعتيادي، وإعادة اكتشافهما مع كل طلب تعني جولات اتصال لا داعي لها.

يصبح التخزين المؤقت لاغيًا في الحالات التالية:

- **Test Connection** يمسحه دائمًا ويعيد الاكتشاف
- تلقائيًا عند تغيّر عنوان الخادم أو منفذه أو اسم المستخدم أو الـ token
- بانقضاء سبعة أيام

> 🔐 لا تُحفَظ أي بيانات اعتماد في التخزين المؤقت؛ ويُستخدم الـ token فقط بصيغة hash عند توليد
> المفتاح.

</details>

<details>
<summary><b>ترتيب إعدادات المنتج</b></summary>

<br>

يحفظ WHMCS إعدادات المنتج **حسب موضعها** (`configoption1..5`). ولهذا لا يمكن إضافة أي إعداد جديد
إلى قائمة إعدادات الوحدة إلا في **آخرها**:

| # | الإعداد |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

فأي إدراج في الوسط أو تغيير في الترتيب سيُزحزح القيم المحفوظة في جميع المنتجات القائمة دون أي تنبيه.

</details>

---

<div align="center">

**DNA Reseller Hosting** · وحدة cPanel و Plesk للـ reseller على WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [لوحة الـ reseller](https://dm.domainnameapi.com/hosting)

</div>
