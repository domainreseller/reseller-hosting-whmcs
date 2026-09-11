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

**Gestione cuentas de revendedor de cPanel y Plesk desde un único módulo de WHMCS.**

Un módulo, dos paneles. El tipo de panel se detecta automáticamente en cada servidor: un mismo
producto puede apuntar a un grupo que contenga servidores cPanel y Plesk a la vez.

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-compatible-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-compatible-53BCE6?style=flat-square)

</div>

---

## 📑 Índice

- [✨ Qué hace](#-qué-hace)
- [📋 Requisitos](#-requisitos)
- [🚀 Instalación](#-instalación)
- [🔍 Registros y diagnóstico](#-registros-y-diagnóstico)
- [🧩 Cosas que conviene saber](#-cosas-que-conviene-saber)
- [📄 Historial de cambios](#-historial-de-cambios)

---

## ✨ Qué hace

| Función | cPanel/WHM | Plesk |
|---|:---:|:---:|
| Creación de cuenta | ✅ | ✅ |
| Suspender / reactivar | ✅ | ✅ |
| Cancelación | ✅ | ✅ |
| Cambio de contraseña | ✅ | ✅ |
| Cambio de paquete / plan | ✅ | ✅ |
| Acceso al panel del cliente con un clic | ✅ | ✅ |
| Sincronización de disco y tráfico | ✅ | ✅ |
| Acceso del administrador al servidor (Log in to Server) | ✅ | — |
| Sobrescritura de disco / tráfico (por producto) | ✅ | lo fija el plan |
| IP dedicada | ✅ | lo fija el plan |

> 💡 **Pensado para revendedores.** No necesita acceso root ni de administrador; el módulo trabaja
> con los permisos de su propia cuenta de revendedor, y cada cuenta que crea consume su cuota.

---

## 📋 Requisitos

- **WHMCS** 7.8 o superior
- **PHP** 7.2 – 8.4
- Extensiones de PHP: `curl`, `json`, `libxml`, `simplexml`, `mbstring`
- **cPanel/WHM** 11.68+ &nbsp;o&nbsp; **Plesk** (protocolo XML-API 1.6.3.0+)

> ✅ No se crea ninguna tabla en la base de datos, no hace falta cron y no hay dependencias de
> composer. La instalación consiste en copiar una carpeta.

---

## 🚀 Instalación

### 1️⃣ Instale el módulo

Copie la carpeta `dnahosting` en el directorio `modules/servers/` de su instalación de WHMCS.

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← aquí
```

### 2️⃣ Añada el servidor

**Configuration → System Settings → Servers → Add New Server**

| Campo | Qué introducir |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | La dirección del servidor — **sin** `https://` delante y **sin** puerto al final |
| **Username** | Su usuario de revendedor |
| **Password** | Su contraseña de revendedor *(no es imprescindible si tiene un token, pero introdúzcala igualmente)* |
| **API Token / Access Hash** | Un token de la API de WHM en cPanel, una clave de API en Plesk |

Estos datos se le envían tras el pedido. También puede consultarlos en cualquier momento en
**[su página de Reseller Hosting](https://dm.domainnameapi.com/hosting)**: pulse el
**⚙️ icono del engranaje** junto al servicio y abra la pestaña **Control Panel**.

![Pantalla de alta de servidor](docs/images/sunucu-ekleme.png)

### 3️⃣ Pruebe la conexión

**Go to Advanced Mode** → **Test Connection**

Si las credenciales son correctas verá un mensaje de éxito. Después **guarde** el servidor.

> ⚠️ **Si la conexión falla, lo primero que hay que mirar es el campo del puerto.**
> cPanel usa `2087` y Plesk `8443`. Si lo deja vacío, el módulo elige el puerto correcto según el
> panel detectado; si usa un puerto distinto, marque **Override with Custom Port** e introdúzcalo a
> mano.

### 4️⃣ Cree un grupo de servidores

Use **Servers → Create New Group** para crear un grupo y añadir el servidor, o añádalo a un grupo
existente. Los productos nunca se enlazan directamente a un servidor: siempre **a través de un
grupo**.

### 5️⃣ Configure el producto

Cree un producto nuevo o edite uno existente y abra la pestaña **Module Settings**:

| Ajuste | Valor |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | El grupo que ha creado |
| **Panel Type** | `Auto` *(o elija el panel directamente si conoce su servidor)* |
| **Package / Plan** | El nombre del paquete definido en su panel de revendedor |

> 💡 **No incluya el prefijo del paquete en cPanel.** Para un paquete que en el panel aparece como
> `bakcay328_paket2`, basta con escribir `paket2` en el producto: el módulo resuelve el prefijo
> `username_` por su cuenta.

**Guárdelo: el módulo ya está listo.** 🎉

**‼️A partir de aquí puede gestionar cuentas de revendedor de cPanel y Plesk desde el módulo en todos los flujos de WHMCS. La creación, la suspensión y la eliminación quedan enteramente bajo el control de WHMCS.**

---

## 🔍 Registros y diagnóstico

Hay dos registros separados y se comportan de forma distinta:

| Registro | Cuándo escribe | Qué contiene |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **Siempre** | Cada operación fallida, con el prefijo `dnahosting:`, junto al ID del servicio y el dominio |
| **Module Log**<br>*Utilities → Logs → Module Log* | Solo mientras **Module Debug Mode** está activo | Cada petición enviada al panel y la respuesta recibida |

> 💡 Module Debug Mode está en **Setup → General Settings → Other → Module Debug Mode**.
> Actívelo antes de reproducir el problema y desactívelo después. Las líneas con el prefijo `note:`
> no son peticiones: son el módulo explicando su propio razonamiento.

> 🔐 Los tokens de API y las contraseñas de los clientes **nunca se escriben en texto plano en los
> registros**.

### Errores frecuentes

| Síntoma | Causa y solución |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | No respondió ningún panel. Revise el puerto y el token, o fije **Panel Type** explícitamente en los ajustes del producto |
| *WHM refused this login* | El usuario no es revendedor, o el token se generó en la interfaz de cPanel. El token debe crearse **en WHM** |
| *Server returned HTTP 3xx (redirect)* | Puerto incorrecto, o el panel redirige a una página de acceso |
| Plesk **11003** | La clave de API se emitió para otra IP: genere una nueva |
| Plesk **1010** | El panel está limitando su IP tras varios intentos fallidos; espere unos minutos |
| Plesk **2204** | El panel aceptó la petición pero falló al configurar su propio servidor web: es un problema del lado del servidor |

---

## 🧩 Cosas que conviene saber

<details>
<summary><b>Diferencias entre paneles</b></summary>

<br>

- Los ajustes de producto **Disk Quota / Bandwidth** solo se aplican en **cPanel**. En Plesk los
  límites vienen del plan de servicio; los campos se ignoran aunque estén rellenos y se escribe una
  nota en el module log.
- **Dedicated IP** solo se aplica a cPanel.
- **El acceso con un clic en Plesk** funciona con el botón *Log in to Panel* del área de cliente.
  Como Plesk no admite el inicio de sesión por redirección, el botón *Log in to Server* de la lista
  de servidores no está disponible para servidores Plesk.

</details>

<details>
<summary><b>Caché</b></summary>

<br>

Se guardan en caché dos datos por servidor durante **siete días**:

- qué panel ejecuta;
- qué versión del protocolo XML-API habla.

Ninguno cambia durante el funcionamiento normal, y volver a detectarlos en cada petición supondría
un viaje de ida y vuelta adicional cada vez.

La caché se invalida cuando:

- se ejecuta **Test Connection**, que siempre la borra y vuelve a detectar;
- cambia la dirección, el puerto, el usuario o el token del servidor, de forma automática;
- han pasado siete días.

> 🔐 En la caché no se guarda ninguna credencial; el token solo se usa como hash al construir la
> clave.

</details>

<details>
<summary><b>El orden de los ajustes del producto</b></summary>

<br>

WHMCS guarda los ajustes del producto **por posición** (`configoption1..5`). Por eso las entradas
nuevas solo pueden añadirse **al final** de la lista de ajustes del módulo:

| # | Ajuste |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

Insertar un ajuste en medio, o reordenar la lista, desplaza en silencio los valores guardados de
todos los productos existentes.

</details>

---

## 📄 Historial de cambios

Los cambios versión a versión están en [CHANGELOG.md](CHANGELOG.md).

---

<div align="center">

**DNA Reseller Hosting** · Módulo de revendedor cPanel y Plesk para WHMCS

[domainnameapi.com](https://www.domainnameapi.com) · [Panel de revendedor](https://dm.domainnameapi.com/hosting)

</div>
