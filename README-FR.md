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

**Gérez vos comptes revendeur cPanel et Plesk depuis un seul module WHMCS.**

Un module, deux panneaux. Le type de panneau est détecté automatiquement pour chaque serveur —
un même produit peut donc être rattaché à un groupe contenant à la fois des serveurs cPanel et
des serveurs Plesk.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-pris%20en%20charge-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-pris%20en%20charge-53BCE6?style=flat-square)

</div>

---

## ✨ Ce que fait le module

| Fonction | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Création de compte | ✅ | ✅ |
| Suspension / réactivation | ✅ | ✅ |
| Résiliation | ✅ | ✅ |
| Changement de mot de passe | ✅ | ✅ |
| Changement de package / plan | ✅ | ✅ |
| Connexion au panneau client en un clic | ✅ | ✅ |
| Synchronisation de l'usage disque et trafic | ✅ | ✅ |
| Connexion administrateur au serveur (Log in to Server) | ✅ | — |
| Surcharge disque / trafic (au niveau du produit) | ✅ | défini par le plan |
| Dedicated IP | ✅ | défini par le plan |

> 💡 **Conçu pour les revendeurs.** Il ne s'agit pas d'un accès root ou admin : le module
> fonctionne avec les droits de votre propre compte revendeur, et les comptes créés sont
> décomptés de votre quota.

---

## 📋 Prérequis

- **WHMCS** 7.8 ou supérieur
- **PHP** 7.2 – 8.4
- Extensions PHP : `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;ou&nbsp; **Plesk** (protocole XML-API 1.6.3.0+)

> ✅ Aucune table de base de données n'est créée, aucun cron n'est à configurer, aucune dépendance
> composer. L'installation se résume à copier un dossier.

---

## 🚀 Installation

### 1️⃣ Installez le module

Copiez le dossier `dnahosting` dans le répertoire `modules/servers/` de votre installation WHMCS.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← ici
```

### 2️⃣ Ajoutez le serveur

**Configuration → System Settings → Servers → Add New Server**

| Champ | Ce qu'il faut saisir |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | L'adresse du serveur — **sans** `https://` au début, **sans** port à la fin |
| **Username** | Votre nom d'utilisateur revendeur |
| **Password** | Votre mot de passe revendeur *(facultatif si vous avez un token, saisissez-le quand même)* |
| **API Token / Access Hash** | Le token API WHM pour cPanel, la clé API pour Plesk |

Ces informations vous sont transmises après la commande. Vous pouvez aussi les consulter à tout
moment depuis **[votre page Reseller Hosting](https://dm.domainnameapi.com/hosting)** : cliquez sur
l'**icône d'engrenage ⚙️** en face du service, puis ouvrez l'onglet **Panneau de contrôle**.

![Écran d'ajout de serveur](docs/images/sunucu-ekleme.png)

### 3️⃣ Testez la connexion

**Go to Advanced Mode** → **Test Connection**

Si les informations sont correctes, un message de réussite s'affiche. **Enregistrez** ensuite le serveur.

> ⚠️ **En cas d'échec de connexion, commencez toujours par vérifier le port.**
> cPanel utilise `2087`, Plesk `8443`. Si vous laissez le champ vide, le module choisit lui-même le
> bon port selon le panneau détecté ; si vous utilisez un port différent, cochez
> **Override with Custom Port** et saisissez-le manuellement.

### 4️⃣ Créez un groupe de serveurs

Créez un nouveau groupe via **Servers → Create New Group** et placez-y le serveur, ou ajoutez-le à
un groupe existant. Les produits ne sont jamais rattachés directement à un serveur, mais
**au groupe**.

### 5️⃣ Configurez le produit

Créez un nouveau produit ou modifiez un produit existant, puis ouvrez l'onglet **Module Settings** :

| Réglage | Valeur |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | Le groupe que vous venez de créer |
| **Panel Type** | `Auto` *(ou sélectionnez directement le panneau si vous le connaissez)* |
| **Package / Plan** | Le nom du package défini dans votre panneau revendeur |

> 💡 **Sur cPanel, n'indiquez pas le préfixe du package.** Pour un package affiché comme
> `bakcay328_paket2` dans le panneau, il suffit d'écrire `paket2` dans le produit — le module
> résout le préfixe `nomutilisateur_` tout seul.

**Enregistrez — le module est prêt à l'emploi.** 🎉

**‼️À partir de cet instant, vous pouvez gérer vos comptes revendeur cPanel et Plesk via le module dans tous les flux de WHMCS. Les flux de création, de suspension et de suppression restent entièrement sous le contrôle de WHMCS.**

---

## 🔍 Journaux et dépannage

Il existe deux emplacements de journalisation distincts, au comportement différent :

| Journal | Quand il écrit | Ce qu'il contient |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Toujours** | Chaque opération en échec, préfixée par `dnahosting:`, avec le numéro de service et le nom de domaine |
| **Module Log**<br>*Utilities → Logs → Module Log* | Uniquement quand **Module Debug Mode** est actif | Chaque requête envoyée au panneau et la réponse reçue |

> 💡 Module Debug Mode : **Setup → General Settings → Other → Module Debug Mode**.
> Activez-le avant de reproduire le problème, puis désactivez-le. Les lignes préfixées par `note:`
> ne sont pas des requêtes : c'est le module qui explique sa propre décision.

> 🔐 Les tokens API et les mots de passe des clients ne sont **jamais écrits en clair** dans les journaux.

### Erreurs fréquentes

| Symptôme | Cause et solution |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | Aucun des deux panneaux n'a répondu. Vérifiez le port et le token, ou sélectionnez explicitement le **Panel Type** dans les réglages du produit |
| *WHM refused this login* | L'utilisateur n'est pas un revendeur, ou le token a été généré depuis l'interface cPanel. Le token doit être généré **dans WHM** |
| *Server returned HTTP 3xx (redirect)* | Port incorrect, ou le panneau redirige vers une page de connexion |
| Plesk **11003** | La clé API a été générée pour une autre IP — régénérez-la |
| Plesk **1010** | Le panneau bloque l'IP après plusieurs tentatives échouées d'affilée ; patientez quelques minutes |
| Plesk **2204** | Le panneau a accepté la requête puis a échoué en configurant son propre serveur web — le problème est côté serveur |

---

## ⚙️ Bon à savoir

<details>
<summary><b>Différences entre les panneaux</b></summary>

<br>

- Les réglages produit **Disk Quota / Bandwidth** ne s'appliquent qu'à **cPanel**. Sur Plesk, les
  limites sont déterminées par le plan de service ; même renseignés, ces champs sont ignorés et une
  note est ajoutée au journal du module.
- **Dedicated IP** n'est valable que sur cPanel.
- **La connexion en un clic sur Plesk** fonctionne via le bouton *Log in to Panel* de l'espace
  client. Plesk ne prenant pas en charge l'ouverture de session par redirection, le bouton
  *Log in to Server* de la liste des serveurs est inutilisable sur les serveurs Plesk.

</details>

<details>
<summary><b>Mise en cache</b></summary>

<br>

Deux informations sont mises en cache pendant **sept jours** pour chaque serveur :

- Le panneau qu'il exécute
- La version du protocole XML-API qu'il utilise

Ces éléments ne changent pas en fonctionnement normal, et les redétecter à chaque requête
entraînerait des allers-retours inutiles.

Le cache est invalidé dans les cas suivants :

- **Test Connection** le vide systématiquement et relance la détection
- Automatiquement si l'adresse, le port, le nom d'utilisateur ou le token du serveur change
- Au bout de sept jours

> 🔐 Aucune information d'identification n'est stockée dans le cache ; le token sert uniquement,
> sous forme de hash, à générer la clé.

</details>

<details>
<summary><b>Ordre des réglages produit</b></summary>

<br>

WHMCS enregistre les réglages produit **par position** (`configoption1..5`). La liste des réglages
du module ne peut donc être complétée qu'**à la fin** :

| # | Réglage |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Toute insertion intermédiaire ou modification de l'ordre décalerait silencieusement les valeurs
enregistrées de tous les produits existants.

</details>

---

<div align="center">

**DNA Reseller Hosting** · Module revendeur cPanel & Plesk pour WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Panneau revendeur](https://dm.domainnameapi.com/hosting)

</div>
