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

**Verwalten Sie cPanel- und Plesk-Reseller-Konten aus einem einzigen WHMCS-Modul.**

Ein Modul, zwei Panels. Der Panel-Typ wird pro Server automatisch erkannt — dasselbe Produkt kann
mit einer Gruppe verbunden werden, die sowohl cPanel- als auch Plesk-Server enthält.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-unterst%C3%BCtzt-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-unterst%C3%BCtzt-53BCE6?style=flat-square)

</div>

---

## 📑 Inhalt

- [✨ Was es kann](#-was-es-kann)
- [📋 Voraussetzungen](#-voraussetzungen)
- [🚀 Installation](#-installation)
- [🔍 Protokolle und Fehlersuche](#-protokolle-und-fehlersuche)
- [🧩 Wissenswertes](#-wissenswertes)
- [📄 Changelog](#-changelog)

---

## ✨ Was es kann

| Funktion | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Konten anlegen | ✅ | ✅ |
| Sperren / Entsperren | ✅ | ✅ |
| Kündigung | ✅ | ✅ |
| Passwortänderung | ✅ | ✅ |
| Paket- / Planwechsel | ✅ | ✅ |
| Ein-Klick-Anmeldung im Kundenpanel | ✅ | ✅ |
| Abgleich von Speicher- und Trafficverbrauch | ✅ | ✅ |
| Anmeldung als Serververwalter (Log in to Server) | ✅ | — |
| Speicher-/Traffic-Override (pro Produkt) | ✅ | vom Plan bestimmt |
| Dedicated IP | ✅ | vom Plan bestimmt |

> 💡 **Für Reseller entwickelt.** Es sind keine Root- oder Admin-Rechte im Spiel; das Modul arbeitet
> mit den Berechtigungen Ihres Reseller-Kontos, und die angelegten Konten werden auf Ihr Kontingent
> angerechnet.

---

## 📋 Voraussetzungen

- **WHMCS** 7.8 oder neuer
- **PHP** 7.2 – 8.4
- PHP-Erweiterungen: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;oder&nbsp; **Plesk** (XML-API-Protokoll 1.6.3.0+)

> ✅ Es werden keine Datenbanktabellen angelegt, kein Cronjob eingerichtet und keine
> Composer-Abhängigkeiten benötigt. Die Installation besteht allein aus dem Kopieren eines Ordners.

---

## 🚀 Installation

### 1️⃣ Modul installieren

Kopieren Sie den Ordner `dnahosting` in das Verzeichnis `modules/servers/` Ihrer WHMCS-Installation.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← hierhin
```

### 2️⃣ Server hinzufügen

**Configuration → System Settings → Servers → Add New Server**

| Feld | Was einzutragen ist |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | Serveradresse — **ohne** vorangestelltes `https://` und **ohne** Port am Ende |
| **Username** | Ihr Reseller-Benutzername |
| **Password** | Ihr Reseller-Passwort *(bei vorhandenem Token nicht zwingend, tragen Sie es dennoch ein)* |
| **API Token / Access Hash** | bei cPanel ein WHM-API-Token, bei Plesk ein API-Key |

Diese Daten erhalten Sie nach der Bestellung. Sie können sie jederzeit auf
**[Ihrer Reseller-Hosting-Seite](https://dm.domainnameapi.com/hosting)** einsehen: Klicken Sie neben
dem Dienst auf das **⚙️ Zahnradsymbol** und wechseln Sie auf den Reiter **Kontrollpanel**.

![Bildschirm zum Hinzufügen eines Servers](docs/images/sunucu-ekleme.png)

### 3️⃣ Verbindung testen

**Go to Advanced Mode** → **Test Connection**

Sind die Daten korrekt, erscheint eine Erfolgsmeldung. **Speichern** Sie den Server anschließend.

> ⚠️ **Schlägt die Verbindung fehl, prüfen Sie zuerst das Port-Feld.**
> cPanel verwendet `2087`, Plesk `8443`. Lassen Sie das Feld leer, wählt das Modul den passenden
> Port je nach Panel selbst; nutzen Sie einen abweichenden Port, aktivieren Sie
> **Override with Custom Port** und tragen ihn manuell ein.

### 4️⃣ Servergruppe anlegen

Legen Sie über **Servers → Create New Group** eine neue Gruppe an und nehmen Sie den Server darin
auf, oder fügen Sie ihn einer bestehenden Gruppe hinzu. Produkte werden nicht direkt mit dem Server,
sondern **über die Gruppe** verbunden.

### 5️⃣ Produkt konfigurieren

Legen Sie ein neues Produkt an oder bearbeiten Sie ein vorhandenes und wechseln Sie auf den Reiter
**Module Settings**:

| Einstellung | Wert |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | Die von Ihnen angelegte Gruppe |
| **Panel Type** | `Auto` *(oder wählen Sie direkt aus, wenn Sie Ihren Server kennen)* |
| **Package / Plan** | Der Name des in Ihrem Reseller-Panel definierten Pakets |

> 💡 **Lassen Sie bei cPanel das Paket-Präfix weg.** Für ein Paket, das im Panel als
> `bakcay328_paket2` erscheint, genügt im Produkt der Eintrag `paket2` — das Präfix `benutzername_`
> löst das Modul selbst auf.

**Speichern — das Modul ist einsatzbereit.** 🎉

**‼️Ab diesem Moment verwalten Sie cPanel- und Plesk-Reseller-Konten über das Modul in allen Abläufen von WHMCS. Anlegen, Sperren und Löschen liegen vollständig in der Hand von WHMCS.**

---

## 🔍 Protokolle und Fehlersuche

Es gibt zwei getrennte Protokollorte, die sich unterschiedlich verhalten:

| Protokoll | Wann es schreibt | Was es enthält |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Immer** | Jeden fehlgeschlagenen Vorgang, mit dem Präfix `dnahosting:` sowie Servicenummer und Domain |
| **Module Log**<br>*Utilities → Logs → Module Log* | Nur bei aktiviertem **Module Debug Mode** | Jede an das Panel gesendete Anfrage und die zugehörige Antwort |

> 💡 Module Debug Mode: **Setup → General Settings → Other → Module Debug Mode**.
> Schalten Sie ihn ein, bevor Sie das Problem reproduzieren, und danach wieder aus. Zeilen mit dem
> Präfix `note:` sind keine Anfragen, sondern die Begründung des Moduls selbst.

> 🔐 API-Token und Kundenpasswörter werden **nicht im Klartext** protokolliert.

### Häufige Fehler

| Symptom | Ursache und Lösung |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Keines der beiden Panels hat geantwortet. Prüfen Sie Port und Token, oder wählen Sie **Panel Type** in den Produkteinstellungen ausdrücklich aus |
| *WHM refused this login* | Der Benutzer ist kein Reseller, oder das Token wurde in der cPanel-Oberfläche erzeugt. Das Token muss **in WHM** erzeugt werden |
| *Server returned HTTP 3xx (redirect)* | Falscher Port, oder das Panel leitet auf eine Anmeldeseite um |
| Plesk **11003** | Der API-Key wurde für eine andere IP erzeugt — erzeugen Sie ihn neu |
| Plesk **1010** | Das Panel sperrt die IP nach mehreren fehlgeschlagenen Versuchen; warten Sie einige Minuten |
| Plesk **2204** | Das Panel hat die Anfrage angenommen, ist aber bei der Konfiguration seines eigenen Webservers abgebrochen — ein serverseitiges Problem |

---

## 🧩 Wissenswertes

<details>
<summary><b>Unterschiede zwischen den Panels</b></summary>

<br>

- Die Produkteinstellungen **Disk Quota / Bandwidth** greifen nur bei **cPanel**. Bei Plesk legt der
  Serviceplan die Limits fest; ausgefüllte Felder werden ignoriert und im Module Log vermerkt.
- **Dedicated IP** gilt ausschließlich für cPanel.
- Die **Ein-Klick-Anmeldung bei Plesk** funktioniert über die Schaltfläche *Log in to Panel* im
  Kundenbereich. Da Plesk keine weiterleitungsbasierte Anmeldung unterstützt, steht die Schaltfläche
  *Log in to Server* in der Serverliste für Plesk-Server nicht zur Verfügung.

</details>

<details>
<summary><b>Zwischenspeicherung</b></summary>

<br>

Pro Server werden zwei Informationen **sieben Tage** lang zwischengespeichert:

- Welches Panel darauf läuft
- Welche XML-API-Protokollversion es spricht

Beides ändert sich im laufenden Betrieb nicht, und eine erneute Erkennung bei jeder Anfrage würde
nur unnötige Roundtrips verursachen.

Der Cache wird in diesen Fällen ungültig:

- **Test Connection** leert ihn immer und führt die Erkennung erneut durch
- Automatisch, sobald sich Adresse, Port, Benutzername oder Token des Servers ändern
- Nach Ablauf der sieben Tage

> 🔐 Im Cache werden keinerlei Zugangsdaten abgelegt; das Token wird lediglich als Hash zur
> Schlüsselbildung verwendet.

</details>

<details>
<summary><b>Reihenfolge der Produkteinstellungen</b></summary>

<br>

WHMCS speichert Produkteinstellungen **positionsbasiert** (`configoption1..5`). Deshalb darf die
Einstellungsliste des Moduls nur **am Ende** erweitert werden:

| # | Einstellung |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Ein Einfügen dazwischen oder eine geänderte Reihenfolge verschiebt die gespeicherten Werte in allen
bestehenden Produkten stillschweigend.

</details>

---

## 📄 Changelog

Die Änderungen Version für Version stehen in [CHANGELOG.md](CHANGELOG.md).

---

<div align="center">

**DNA Reseller Hosting** · cPanel- & Plesk-Reseller-Modul für WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Reseller-Panel](https://dm.domainnameapi.com/hosting)

</div>
