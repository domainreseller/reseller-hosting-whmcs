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

**Управляйте реселлерскими аккаунтами cPanel и Plesk из одного модуля WHMCS.**

Один модуль, две панели. Тип панели определяется автоматически для каждого сервера — один и тот же
продукт может указывать на группу, в которой есть и cPanel-, и Plesk-серверы.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-%D0%BF%D0%BE%D0%B4%D0%B4%D0%B5%D1%80%D0%B6%D0%B8%D0%B2%D0%B0%D0%B5%D1%82%D1%81%D1%8F-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-%D0%BF%D0%BE%D0%B4%D0%B4%D0%B5%D1%80%D0%B6%D0%B8%D0%B2%D0%B0%D0%B5%D1%82%D1%81%D1%8F-53BCE6?style=flat-square)

</div>

---

## 📑 Содержание

- [✨ Что умеет модуль](#-что-умеет-модуль)
- [📋 Требования](#-требования)
- [🚀 Установка](#-установка)
- [🔍 Логи и диагностика](#-логи-и-диагностика)
- [🧩 Что полезно знать](#-что-полезно-знать)
- [📄 История изменений](#-история-изменений)

---

## ✨ Что умеет модуль

| Возможность | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Создание аккаунта | ✅ | ✅ |
| Приостановка / возобновление | ✅ | ✅ |
| Удаление | ✅ | ✅ |
| Смена пароля | ✅ | ✅ |
| Смена пакета / плана | ✅ | ✅ |
| Вход в клиентскую панель одним кликом | ✅ | ✅ |
| Синхронизация диска и трафика | ✅ | ✅ |
| Вход администратора на сервер (Log in to Server) | ✅ | — |
| Переопределение диска / трафика (в продукте) | ✅ | задаёт план |
| Выделенный IP | ✅ | задаёт план |

> 💡 **Сделано для реселлеров.** Root или админ-доступ не нужен: модуль работает с правами вашего
> собственного реселлерского аккаунта, и каждый созданный аккаунт расходует вашу квоту.

---

## 📋 Требования

- **WHMCS** 7.8 или новее
- **PHP** 7.2 – 8.4
- Расширения PHP: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;или&nbsp; **Plesk** (протокол XML-API 1.6.3.0+)

> ✅ Таблицы в базе не создаются, cron не нужен, зависимостей composer нет. Установка сводится к
> копированию папки.

---

## 🚀 Установка

### 1️⃣ Установите модуль

Скопируйте папку `dnahosting` в каталог `modules/servers/` вашей установки WHMCS.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← сюда
```

### 2️⃣ Добавьте сервер

**Configuration → System Settings → Servers → Add New Server**

| Поле | Что вводить |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | Адрес сервера — **без** `https://` в начале и **без** порта в конце |
| **Username** | Ваше реселлерское имя пользователя |
| **Password** | Ваш реселлерский пароль *(при наличии токена не обязателен, но введите его)* |
| **API Token / Access Hash** | Токен WHM API на cPanel, ключ API на Plesk |

Эти данные высылаются вам после заказа. Их всегда можно посмотреть на
**[странице вашего реселлер-хостинга](https://dm.domainnameapi.com/hosting)**: нажмите
**⚙️ шестерёнку** рядом с услугой и откройте вкладку **Control Panel**.

![Экран добавления сервера](docs/images/sunucu-ekleme.png)

### 3️⃣ Проверьте соединение

**Go to Advanced Mode** → **Test Connection**

Если данные верны, появится сообщение об успехе. После этого **сохраните** сервер.

> ⚠️ **Если соединение не проходит, первым делом смотрите на поле порта.**
> cPanel использует `2087`, Plesk — `8443`. Оставьте поле пустым, и модуль сам подберёт порт под
> обнаруженную панель; если у вас нестандартный порт, отметьте **Override with Custom Port** и
> введите его вручную.

### 4️⃣ Создайте группу серверов

Через **Servers → Create New Group** создайте новую группу и добавьте в неё сервер либо добавьте его
в существующую группу. Продукты никогда не привязываются к серверу напрямую — только **через
группу**.

### 5️⃣ Настройте продукт

Создайте новый продукт или откройте существующий и перейдите на вкладку **Module Settings**:

| Настройка | Значение |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | Созданная вами группа |
| **Panel Type** | `Auto` *(или выберите панель напрямую, если знаете свой сервер)* |
| **Package / Plan** | Имя пакета, заданного в вашей реселлерской панели |

> 💡 **Не указывайте префикс пакета на cPanel.** Для пакета, который в панели виден как
> `bakcay328_paket2`, в продукте достаточно ввести `paket2` — префикс `username_` модуль разрешает
> сам.

**Сохраните — модуль готов к работе.** 🎉

**‼️С этого момента вы управляете реселлерскими аккаунтами cPanel и Plesk через модуль во всех процессах WHMCS. Создание, приостановка и удаление полностью подчинены WHMCS.**

---

## 🔍 Логи и диагностика

Логов два, и ведут они себя по-разному:

| Лог | Когда пишется | Что содержит |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Всегда** | Каждая неуспешная операция с префиксом `dnahosting:`, ID услуги и домен |
| **Module Log**<br>*Utilities → Logs → Module Log* | Только при включённом **Module Debug Mode** | Каждый запрос к панели и полученный ответ |

> 💡 Module Debug Mode находится в **Setup → General Settings → Other → Module Debug Mode**.
> Включите его перед воспроизведением проблемы и выключите после. Строки с префиксом `note:` — это
> не запросы, а пояснения модуля о собственных решениях.

> 🔐 Токены API и пароли клиентов **никогда не пишутся в логи в открытом виде**.

### Частые ошибки

| Симптом | Причина и решение |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Ни одна панель не ответила. Проверьте порт и токен либо задайте **Panel Type** явно в настройках продукта |
| *WHM refused this login* | Пользователь не является реселлером, или токен создан в интерфейсе cPanel. Токен нужно создавать **в WHM** |
| *Server returned HTTP 3xx (redirect)* | Неверный порт либо панель перенаправляет на страницу входа |
| Plesk **11003** | Ключ API выпущен для другого IP — выпустите новый |
| Plesk **1010** | Панель ограничивает ваш IP после серии неудачных попыток; подождите несколько минут |
| Plesk **2204** | Панель приняла запрос, но упала при настройке собственного веб-сервера — это проблема на стороне сервера |

---

## 🧩 Что полезно знать

<details>
<summary><b>Различия панелей</b></summary>

<br>

- Настройки продукта **Disk Quota / Bandwidth** действуют только на **cPanel**. На Plesk лимиты
  берутся из сервисного плана; заполненные поля игнорируются, а в module log пишется пометка.
- **Dedicated IP** работает только на cPanel.
- **Вход одним кликом на Plesk** выполняется кнопкой *Log in to Panel* в клиентской части.
  Plesk не поддерживает вход через редирект, поэтому кнопка *Log in to Server* в списке серверов для
  Plesk-серверов недоступна.

</details>

<details>
<summary><b>Кеширование</b></summary>

<br>

Для каждого сервера на **семь дней** кешируются два факта:

- какая панель на нём работает;
- версия протокола XML-API, на которой он говорит.

В обычной работе они не меняются, а повторное определение на каждом запросе означало бы лишний
круг обращения.

Кеш сбрасывается, когда:

- выполняется **Test Connection** — он всегда очищает кеш и определяет панель заново;
- меняется адрес, порт, имя пользователя или токен сервера — автоматически;
- прошло семь дней.

> 🔐 Учётные данные в кеше не хранятся; токен используется только как хеш при построении ключа.

</details>

<details>
<summary><b>Порядок настроек продукта</b></summary>

<br>

WHMCS хранит настройки продукта **по позиции** (`configoption1..5`). Поэтому новые пункты можно
добавлять только **в конец** списка настроек модуля:

| # | Настройка |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Вставка настройки в середину или перестановка списка молча сдвигает сохранённые значения во всех
существующих продуктах.

</details>

---

## 📄 История изменений

Изменения по версиям — в файле [CHANGELOG.md](CHANGELOG.md).

---

<div align="center">

**DNA Reseller Hosting** · Реселлерский модуль cPanel и Plesk для WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Панель реселлера](https://dm.domainnameapi.com/hosting)

</div>
