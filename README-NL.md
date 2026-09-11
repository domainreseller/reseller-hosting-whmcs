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

**Beheer cPanel- en Plesk-reselleraccounts vanuit één WHMCS-module.**

Eén module, twee panelen. Het paneeltype wordt per server automatisch herkend — hetzelfde product
kan gekoppeld worden aan een groep met zowel cPanel- als Plesk-servers.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-ondersteund-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-ondersteund-53BCE6?style=flat-square)

</div>

---

## 📑 Inhoud

- [✨ Wat de module doet](#-wat-de-module-doet)
- [📋 Vereisten](#-vereisten)
- [🚀 Installatie](#-installatie)
- [🔍 Logs en probleemoplossing](#-logs-en-probleemoplossing)
- [🧩 Goed om te weten](#-goed-om-te-weten)
- [📄 Changelog](#-changelog)

---

## ✨ Wat de module doet

| Functie | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Account aanmaken | ✅ | ✅ |
| Opschorten / heractiveren | ✅ | ✅ |
| Beëindigen | ✅ | ✅ |
| Wachtwoord wijzigen | ✅ | ✅ |
| Pakket / plan wijzigen | ✅ | ✅ |
| Met één klik inloggen op het klantpaneel | ✅ | ✅ |
| Synchronisatie van schijf- en dataverbruik | ✅ | ✅ |
| Inloggen als serverbeheerder (Log in to Server) | ✅ | — |
| Override van schijfruimte / dataverkeer (per product) | ✅ | plan bepaalt |
| Dedicated IP | ✅ | plan bepaalt |

> 💡 **Gebouwd voor resellers.** Dit is geen root- of adminbevoegdheid; de module werkt met de
> rechten van uw eigen reselleraccount en de aangemaakte accounts tellen mee in uw quota.

---

## 📋 Vereisten

- **WHMCS** 7.8 of hoger
- **PHP** 7.2 – 8.4
- PHP-extensies: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;of&nbsp; **Plesk** (XML-API-protocol 1.6.3.0+)

> ✅ De module maakt geen databasetabellen aan, heeft geen cronjob nodig en kent geen
> composer-afhankelijkheden. De installatie bestaat uit niets meer dan het kopiëren van één map.

---

## 🚀 Installatie

### 1️⃣ Installeer de module

Kopieer de map `dnahosting` naar de map `modules/servers/` van uw WHMCS-installatie.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← hierheen
```

### 2️⃣ Voeg de server toe

**Configuration → System Settings → Servers → Add New Server**

| Veld | Wat u invult |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | Het serveradres — **zonder** `https://` ervoor en **zonder** poort erachter |
| **Username** | Uw resellergebruikersnaam |
| **Password** | Uw resellerwachtwoord *(niet verplicht als u een token heeft, vul het toch in)* |
| **API Token / Access Hash** | Bij cPanel het WHM API-token, bij Plesk de API key |

Deze gegevens ontvangt u na uw bestelling. U kunt ze ook altijd terugvinden via
**[uw Reseller Hosting-pagina](https://dm.domainnameapi.com/hosting)**: klik op het
**⚙️ tandwielpictogram** naast de dienst en open het tabblad **Configuratiescherm**.

![Scherm voor het toevoegen van een server](docs/images/sunucu-ekleme.png)

### 3️⃣ Test de verbinding

**Go to Advanced Mode** → **Test Connection**

Kloppen de gegevens, dan verschijnt er een succesmelding. **Sla** de server daarna op.

> ⚠️ **Mislukt de verbinding, kijk dan eerst naar het poortveld.**
> cPanel gebruikt `2087`, Plesk `8443`. Laat u het veld leeg, dan kiest de module zelf de juiste
> poort op basis van het paneel; gebruikt u een afwijkende poort, vink dan
> **Override with Custom Port** aan en vul die handmatig in.

### 4️⃣ Maak een servergroep

Maak met **Servers → Create New Group** een nieuwe groep aan en plaats de server daarin, of voeg
hem toe aan een bestaande groep. Producten worden niet rechtstreeks aan een server gekoppeld, maar
**via een groep**.

### 5️⃣ Configureer het product

Maak een nieuw product aan of bewerk een bestaand product en ga naar het tabblad **Module Settings**:

| Instelling | Waarde |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | De groep die u heeft aangemaakt |
| **Panel Type** | `Auto` *(of kies direct het juiste paneel als u uw server kent)* |
| **Package / Plan** | De naam van het pakket zoals gedefinieerd in uw resellerpaneel |

> 💡 **Laat bij cPanel het pakketvoorvoegsel weg.** Voor een pakket dat in het paneel als
> `bakcay328_paket2` verschijnt, volstaat `paket2` in het product — de module vult het voorvoegsel
> `gebruikersnaam_` zelf aan.

**Opslaan — de module is klaar voor gebruik.** 🎉

**‼️Vanaf dit moment beheert u cPanel- en Plesk-reselleraccounts via de module binnen alle
processen van WHMCS. Aanmaken, opschorten en verwijderen verlopen volledig onder regie van WHMCS.**

---

## 🔍 Logs en probleemoplossing

Er zijn twee aparte logs en ze gedragen zich verschillend:

| Log | Wanneer het schrijft | Wat erin staat |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Altijd** | Elke mislukte bewerking, met het voorvoegsel `dnahosting:`, inclusief servicenummer en domeinnaam |
| **Module Log**<br>*Utilities → Logs → Module Log* | Alleen als **Module Debug Mode** aanstaat | Elk verzoek naar het paneel en het bijbehorende antwoord |

> 💡 Module Debug Mode: **Setup → General Settings → Other → Module Debug Mode**.
> Zet het aan voordat u het probleem reproduceert en daarna weer uit. Regels met het voorvoegsel
> `note:` zijn geen verzoeken, maar de toelichting van de module zelf.

> 🔐 API-tokens en klantwachtwoorden worden **niet als platte tekst** naar de logs geschreven.

### Veelvoorkomende fouten

| Symptoom | Oorzaak en oplossing |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Geen van beide panelen antwoordde. Controleer poort en token, of kies **Panel Type** expliciet in de productinstellingen |
| *WHM refused this login* | De gebruiker is geen reseller, of het token is in de cPanel-interface aangemaakt. Het token moet in **WHM** worden aangemaakt |
| *Server returned HTTP 3xx (redirect)* | Verkeerde poort, of het paneel stuurt door naar een inlogpagina |
| Plesk **11003** | De API key is voor een ander IP-adres aangemaakt — maak hem opnieuw aan |
| Plesk **1010** | Het paneel blokkeert het IP na meerdere mislukte pogingen op rij; wacht een paar minuten |
| Plesk **2204** | Het paneel accepteerde het verzoek, maar liep vast bij het configureren van zijn eigen webserver — dit is een probleem aan serverzijde |

---

## 🧩 Goed om te weten

<details>
<summary><b>Verschillen tussen de panelen</b></summary>

<br>

- De productinstellingen **Disk Quota / Bandwidth** worden alleen op **cPanel** toegepast. Bij Plesk
  bepaalt het serviceplan de limieten; ingevulde velden worden genegeerd en er komt een notitie in
  het modulelog.
- **Dedicated IP** geldt alleen voor cPanel.
- **Inloggen met één klik bij Plesk** werkt via de knop *Log in to Panel* in het klantgedeelte.
  Omdat Plesk geen op doorverwijzing gebaseerde login ondersteunt, is de knop *Log in to Server* in
  de serverlijst niet bruikbaar voor Plesk-servers.

</details>

<details>
<summary><b>Caching</b></summary>

<br>

Per server worden twee gegevens **zeven dagen** gecachet:

- Welk paneel de server draait
- Welke versie van het XML-API-protocol hij spreekt

Deze veranderen bij normaal gebruik niet, en ze bij elk verzoek opnieuw vaststellen levert alleen
maar overbodige rondjes op.

De cache vervalt in de volgende gevallen:

- **Test Connection** wist de cache altijd en stelt alles opnieuw vast
- Automatisch zodra het adres, de poort, de gebruikersnaam of het token van de server wijzigt
- Na zeven dagen

> 🔐 In de cache worden geen inloggegevens opgeslagen; het token wordt uitsluitend als hash gebruikt
> bij het genereren van de sleutel.

</details>

<details>
<summary><b>De volgorde van de productinstellingen</b></summary>

<br>

WHMCS slaat productinstellingen **op positie** op (`configoption1..5`). Daarom mag er alleen
**achteraan** iets aan de lijst met module-instellingen worden toegevoegd:

| # | Instelling |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Iets ertussen schuiven of de volgorde wijzigen verschuift stilzwijgend de opgeslagen waarden van
alle bestaande producten.

</details>

---

## 📄 Changelog

De wijzigingen per versie staan in [CHANGELOG.md](CHANGELOG.md).

---

<div align="center">

**DNA Reseller Hosting** · cPanel- & Plesk-resellermodule voor WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Resellerpaneel](https://dm.domainnameapi.com/hosting)

</div>
