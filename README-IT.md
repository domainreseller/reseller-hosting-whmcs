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

**Gestisca gli account reseller cPanel e Plesk da un unico modulo WHMCS.**

Un solo modulo, due pannelli. Il tipo di pannello viene rilevato automaticamente per ogni server —
lo stesso prodotto può essere collegato a un gruppo che contiene sia server cPanel sia server Plesk.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-supportato-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-supportato-53BCE6?style=flat-square)

</div>

---

## 📑 Indice

- [✨ Cosa fa](#-cosa-fa)
- [📋 Requisiti](#-requisiti)
- [🚀 Installazione](#-installazione)
- [🔍 Log e risoluzione dei problemi](#-log-e-risoluzione-dei-problemi)
- [🧩 Cose da sapere](#-cose-da-sapere)
- [📄 Changelog](#-changelog)

---

## ✨ Cosa fa

| Funzione | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Creazione dell'account | ✅ | ✅ |
| Sospensione / riattivazione | ✅ | ✅ |
| Terminazione | ✅ | ✅ |
| Cambio password | ✅ | ✅ |
| Cambio di pacchetto / piano | ✅ | ✅ |
| Accesso al pannello cliente con un clic | ✅ | ✅ |
| Sincronizzazione dell'uso di disco e traffico | ✅ | ✅ |
| Accesso come amministratore del server (Log in to Server) | ✅ | — |
| Override di disco / traffico (a livello di prodotto) | ✅ | definito dal piano |
| Dedicated IP | ✅ | definito dal piano |

> 💡 **Progettato per i rivenditori.** Non richiede privilegi di root o di amministratore: il modulo
> opera con i permessi del Suo account rivenditore e gli account creati vengono conteggiati sulla Sua quota.

---

## 📋 Requisiti

- **WHMCS** 7.8 o superiore
- **PHP** 7.2 – 8.4
- Estensioni PHP: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;oppure&nbsp; **Plesk** (protocollo XML-API 1.6.3.0+)

> ✅ Il modulo non crea tabelle nel database, non richiede cron e non ha dipendenze composer.
> L'installazione consiste unicamente nel copiare una cartella.

---

## 🚀 Installazione

### 1️⃣ Installi il modulo

Copi la cartella `dnahosting` nella directory `modules/servers/` della Sua installazione WHMCS.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← qui
```

### 2️⃣ Aggiunga il server

**Configuration → System Settings → Servers → Add New Server**

| Campo | Cosa inserire |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | Indirizzo del server — **senza** `https://` all'inizio e **senza** porta alla fine |
| **Username** | Il Suo nome utente rivenditore |
| **Password** | La Sua password rivenditore *(non obbligatoria se dispone di un token, la inserisca comunque)* |
| **API Token / Access Hash** | Token API WHM su cPanel, API key su Plesk |

Questi dati Le vengono comunicati dopo l'ordine. Può consultarli in qualsiasi momento dalla
**[Sua pagina Reseller Hosting](https://dm.domainnameapi.com/hosting)**, facendo clic sull'
**icona ⚙️ dell'ingranaggio** accanto al servizio e aprendo la scheda **Pannello di controllo**.

![Schermata di aggiunta del server](docs/images/sunucu-ekleme.png)

### 3️⃣ Verifichi la connessione

**Go to Advanced Mode** → **Test Connection**

Se i dati sono corretti comparirà il messaggio di esito positivo. A questo punto **salvi** il server.

> ⚠️ **Se la connessione fallisce, il primo punto da controllare è il campo della porta.**
> cPanel usa la `2087`, Plesk la `8443`. Lasciandolo vuoto il modulo sceglie da sé la porta corretta
> in base al pannello; se utilizza una porta diversa, selezioni **Override with Custom Port** e la inserisca manualmente.

### 4️⃣ Crei un gruppo di server

Con **Servers → Create New Group** crei un nuovo gruppo e vi inserisca il server, oppure lo aggiunga
a un gruppo esistente. I prodotti non vengono collegati direttamente al server, ma **tramite il gruppo**.

### 5️⃣ Configuri il prodotto

Crei un nuovo prodotto oppure modifichi uno esistente e apra la scheda **Module Settings**:

| Impostazione | Valore |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | Il gruppo che ha creato |
| **Panel Type** | `Auto` *(oppure selezioni direttamente il pannello, se lo conosce)* |
| **Package / Plan** | Il nome del pacchetto definito nel Suo pannello rivenditore |

> 💡 **Su cPanel non indichi il prefisso del pacchetto.** Per un pacchetto che nel pannello compare
> come `bakcay328_paket2` è sufficiente scrivere `paket2` nel prodotto: il modulo risolve da sé il
> prefisso `nomeutente_`.

**Salvi — il modulo è pronto all'uso.** 🎉

**‼️Da questo momento può gestire gli account reseller cPanel e Plesk tramite il modulo in tutti i flussi di WHMCS. I flussi di creazione, sospensione ed eliminazione restano interamente sotto il controllo di WHMCS.**

---

## 🔍 Log e risoluzione dei problemi

Esistono due punti di registrazione distinti, con comportamenti diversi:

| Log | Quando scrive | Cosa contiene |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Sempre** | Ogni operazione fallita, con il prefisso `dnahosting:`, il numero del servizio e il dominio |
| **Module Log**<br>*Utilities → Logs → Module Log* | Solo con **Module Debug Mode** attivo | Ogni richiesta inviata al pannello e la relativa risposta |

> 💡 Module Debug Mode: **Setup → General Settings → Other → Module Debug Mode**.
> Lo attivi prima di riprodurre il problema e lo disattivi subito dopo. Le righe con prefisso `note:`
> non sono richieste, ma la motivazione registrata dal modulo stesso.

> 🔐 I token API e le password dei clienti **non vengono scritti in chiaro** nei log.

### Errori più frequenti

| Sintomo | Causa e soluzione |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Nessuno dei due pannelli ha risposto. Controlli la porta e il token, oppure selezioni esplicitamente **Panel Type** nelle impostazioni del prodotto |
| *WHM refused this login* | L'utente non è un rivenditore, oppure il token è stato generato dall'interfaccia cPanel. Il token deve essere generato **in WHM** |
| *Server returned HTTP 3xx (redirect)* | Porta errata, oppure il pannello reindirizza a una pagina di login |
| Plesk **11003** | La chiave API è stata generata per un altro IP — la rigeneri |
| Plesk **1010** | Il pannello sta limitando l'IP dopo una serie di tentativi falliti; attenda qualche minuto |
| Plesk **2204** | Il pannello ha accettato la richiesta ma si è interrotto durante la configurazione del proprio web server — è un problema lato server |

---

## 🧩 Cose da sapere

<details>
<summary><b>Differenze tra i pannelli</b></summary>

<br>

- Le impostazioni di prodotto **Disk Quota / Bandwidth** vengono applicate solo su **cPanel**. Su Plesk
  i limiti sono definiti dal piano di servizio; anche se i campi vengono compilati, sono ignorati e
  ne viene annotata una nota nel log del modulo.
- **Dedicated IP** è valido solo su cPanel.
- **L'accesso con un clic su Plesk** funziona tramite il pulsante *Log in to Panel* nell'area cliente.
  Poiché Plesk non supporta l'autenticazione basata su redirect, il pulsante *Log in to Server*
  presente nell'elenco dei server non è utilizzabile sui server Plesk.

</details>

<details>
<summary><b>Caching</b></summary>

<br>

Per ogni server vengono memorizzate in cache due informazioni per **sette giorni**:

- Quale pannello è in esecuzione
- La versione del protocollo XML-API utilizzata

Questi dati non cambiano durante il normale esercizio e rilevarli a ogni richiesta comporterebbe
scambi di rete inutili.

La cache viene invalidata nei casi seguenti:

- **Test Connection** la svuota sempre ed esegue un nuovo rilevamento
- Automaticamente, se cambiano indirizzo, porta, nome utente o token del server
- Alla scadenza dei sette giorni

> 🔐 Nella cache non viene conservata alcuna credenziale; il token è usato soltanto come hash per
> generare la chiave.

</details>

<details>
<summary><b>Ordine delle impostazioni di prodotto</b></summary>

<br>

WHMCS memorizza le impostazioni di prodotto **in base alla posizione** (`configoption1..5`). Per questo
motivo l'elenco delle impostazioni del modulo può essere ampliato solo **in coda**:

| # | Impostazione |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Un inserimento intermedio o una modifica dell'ordine sposterebbe silenziosamente i valori già salvati
in tutti i prodotti esistenti.

</details>

---

## 📄 Changelog

Le modifiche versione per versione sono in [CHANGELOG.md](CHANGELOG.md).

---

<div align="center">

**DNA Reseller Hosting** · Modulo reseller cPanel & Plesk per WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Pannello rivenditore](https://dm.domainnameapi.com/hosting)

</div>
