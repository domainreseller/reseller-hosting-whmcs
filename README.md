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

**Manage cPanel and Plesk reseller accounts from a single WHMCS module.**

One module, two panels. The panel type is detected automatically per server — the same product can
point at a group that holds both cPanel and Plesk servers.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-supported-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-supported-53BCE6?style=flat-square)

</div>

---

## ✨ What it does

| Feature | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Account creation | ✅ | ✅ |
| Suspend / unsuspend | ✅ | ✅ |
| Termination | ✅ | ✅ |
| Password change | ✅ | ✅ |
| Package / plan change | ✅ | ✅ |
| One-click login to the client panel | ✅ | ✅ |
| Disk & bandwidth usage sync | ✅ | ✅ |
| Server admin login (Log in to Server) | ✅ | — |
| Disk / bandwidth override (per product) | ✅ | set by the plan |
| Dedicated IP | ✅ | set by the plan |

> 💡 **Built for resellers.** It does not need root or admin access; the module works with the
> permissions of your own reseller account, and every account it creates counts against your quota.

---

## 📋 Requirements

- **WHMCS** 7.8 or newer
- **PHP** 7.2 – 8.4
- PHP extensions: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;or&nbsp; **Plesk** (XML-API protocol 1.6.3.0+)

> ✅ No database table is created, no cron job is required, and there are no composer dependencies.
> Installation is nothing more than copying a folder.

---

## 🚀 Installation

### 1️⃣ Install the module

Copy the `dnahosting` folder into the `modules/servers/` directory of your WHMCS installation.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← here
```

### 2️⃣ Add the server

**Configuration → System Settings → Servers → Add New Server**

| Field | What to enter |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | The server address — **without** a leading `https://` and **without** a trailing port |
| **Username** | Your reseller username |
| **Password** | Your reseller password *(not strictly required if you have a token, but enter it anyway)* |
| **API Token / Access Hash** | A WHM API token on cPanel, an API key on Plesk |

These details are sent to you after your order. You can also look them up at any time from
**[your Reseller Hosting page](https://dm.domainnameapi.com/hosting)**: click the
**⚙️ gear icon** next to the service and open the **Control Panel** tab.

![Add server screen](docs/images/sunucu-ekleme.png)

### 3️⃣ Test the connection

**Go to Advanced Mode** → **Test Connection**

If the credentials are correct you will see a success message. Then **save** the server.

> ⚠️ **If the connection fails, the port field is the first place to look.**
> cPanel uses `2087`, Plesk uses `8443`. Leave it empty and the module picks the right port for the
> detected panel; if you run a different port, tick **Override with Custom Port** and enter it manually.

### 4️⃣ Create a server group

Use **Servers → Create New Group** to create a new group and add the server to it, or add it to an
existing group. Products are never linked to a server directly — always **through a group**.

### 5️⃣ Configure the product

Create a new product or edit an existing one and open the **Module Settings** tab:

| Setting | Value |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | The group you created |
| **Panel Type** | `Auto` *(or pick the panel directly if you know your server)* |
| **Package / Plan** | The name of the package defined in your reseller panel |

> 💡 **Do not include the package prefix on cPanel.** For a package that shows up as
> `bakcay328_paket2` in the panel, just enter `paket2` on the product — the module resolves the
> `username_` prefix itself.

**Save it — the module is ready to use.** 🎉

**‼️From this point on you can manage cPanel and Plesk reseller accounts through the module across every WHMCS workflow. Creation, suspension and deletion are entirely under WHMCS's control.**

---

## 🔍 Logs and troubleshooting

There are two separate logs, and they behave differently:

| Log | When it writes | What it contains |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Always** | Every failed operation, prefixed with `dnahosting:`, along with the service ID and domain |
| **Module Log**<br>*Utilities → Logs → Module Log* | Only while **Module Debug Mode** is on | Every request sent to the panel and the response it returned |

> 💡 Module Debug Mode lives at **Setup → General Settings → Other → Module Debug Mode**.
> Turn it on before reproducing the problem, then turn it back off. Lines prefixed with `note:` are
> not requests — they are the module explaining its own reasoning.

> 🔐 API tokens and client passwords are **never written to the logs in plain text**.

### Common errors

| Symptom | Cause and fix |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Neither panel responded. Check the port and the token, or set **Panel Type** explicitly in the product settings |
| *WHM refused this login* | The user is not a reseller, or the token was generated in the cPanel interface. The token must be created **in WHM** |
| *Server returned HTTP 3xx (redirect)* | Wrong port, or the panel is redirecting to a login page |
| Plesk **11003** | The API key was issued for a different IP — generate a new one |
| Plesk **1010** | The panel is rate-limiting your IP after repeated failed attempts; wait a few minutes |
| Plesk **2204** | The panel accepted the request but failed while configuring its own web server — this is a server-side problem |

---

## ⚙️ Things worth knowing

<details>
<summary><b>Panel differences</b></summary>

<br>

- The **Disk Quota / Bandwidth** product settings apply on **cPanel** only. On Plesk the limits come
  from the service plan; the fields are ignored even when filled in, and a note is written to the
  module log.
- **Dedicated IP** applies to cPanel only.
- **One-click login on Plesk** works through the *Log in to Panel* button in the client area. Because
  Plesk does not support redirect-based sign-on, the *Log in to Server* button in the server list is
  unavailable for Plesk servers.

</details>

<details>
<summary><b>Caching</b></summary>

<br>

Two pieces of information are cached per server for **seven days**:

- Which panel it runs
- The XML-API protocol version it speaks

Neither changes during normal operation, and re-detecting them on every request would mean an extra
round trip each time.

The cache is invalidated when:

- **Test Connection** runs — it always clears the cache and re-detects
- The server's address, port, username or token changes — automatically
- Seven days have passed

> 🔐 No credentials are stored in the cache; the token is only used as a hash when building the key.

</details>

<details>
<summary><b>The order of the product settings</b></summary>

<br>

WHMCS stores product settings **by position** (`configoption1..5`). That is why new entries can only
be appended to the **end** of the module's settings list:

| # | Setting |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Inserting a setting in the middle, or reordering the list, silently shifts the stored values on every
existing product.

</details>

---

<div align="center">

**DNA Reseller Hosting** · cPanel & Plesk reseller module for WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Reseller panel](https://dm.domainnameapi.com/hosting)

</div>
