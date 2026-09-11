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

**在单个 WHMCS 模块中管理 cPanel 与 Plesk 经销商账户。**

一个模块，两种面板。面板类型按服务器自动识别 —— 同一个产品可以绑定到同时包含 cPanel
和 Plesk 服务器的服务器组。

![WHMCS](https://img.shields.io/badge/WHMCS-7.8%20%E2%80%93%208.x-4A90D9?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777BB4?style=flat-square&logo=php&logoColor=white)
![cPanel](https://img.shields.io/badge/cPanel%2FWHM-%E6%94%AF%E6%8C%81-FF6C2C?style=flat-square)
![Plesk](https://img.shields.io/badge/Plesk-%E6%94%AF%E6%8C%81-53BCE6?style=flat-square)

</div>

---

## 📑 目录

- [✨ 功能一览](#-功能一览)
- [📋 环境要求](#-环境要求)
- [🚀 安装](#-安装)
- [🔍 日志与故障排查](#-日志与故障排查)
- [🧩 须知事项](#-须知事项)
- [📄 更新日志](#-更新日志)

---

## ✨ 功能一览

| 功能 | cPanel/WHM | Plesk |
|---|:---:|:---:|
| 创建账户 | ✅ | ✅ |
| 暂停 / 恢复 | ✅ | ✅ |
| 终止 | ✅ | ✅ |
| 修改密码 | ✅ | ✅ |
| 更换套餐 / 方案 | ✅ | ✅ |
| 一键登录客户面板 | ✅ | ✅ |
| 磁盘与流量用量同步 | ✅ | ✅ |
| 服务器管理员登录（Log in to Server） | ✅ | — |
| 磁盘 / 流量覆盖设置（按产品） | ✅ | 由方案决定 |
| Dedicated IP | ✅ | 由方案决定 |

> 💡 **专为 reseller 场景设计。** 模块不需要 root 或管理员权限，它以你的 reseller
> 账户权限运行，创建出来的账户会计入你的配额。

---

## 📋 环境要求

- **WHMCS** 7.8 或更高版本
- **PHP** 7.2 – 8.4
- PHP 扩展：`curl`、`json`、`libxml`、`simplexml`、`mbstring`
- **cPanel/WHM** 11.68+ &nbsp;或&nbsp; **Plesk**（XML-API 协议 1.6.3.0+）

> ✅ 模块不会创建数据库表，不需要配置 cron，也没有 composer 依赖。
> 安装过程只是复制一个文件夹而已。

---

## 🚀 安装

### 1️⃣ 部署模块

把 `dnahosting` 文件夹复制到 WHMCS 安装目录下的 `modules/servers/` 中。

```
whmcs/
└── modules/
    └── servers/
        └── dnahosting/     ← 复制到这里
```

### 2️⃣ 添加服务器

**Configuration → System Settings → Servers → Add New Server**

| 字段 | 填写内容 |
|---|---|
| **Module** | `DNA Reseller Hosting` |
| **Hostname or IP Address** | 服务器地址 —— 开头**不要**加 `https://`，结尾**不要**加端口 |
| **Username** | 你的 reseller 用户名 |
| **Password** | 你的 reseller 密码 *（有 token 时并非必填，但仍建议填写）* |
| **API Token / Access Hash** | cPanel 上填 WHM API token，Plesk 上填 API key |

这些信息会在下单后发送给你。你也可以随时在
**[Reseller Hosting 页面](https://dm.domainnameapi.com/hosting)** 中点击服务旁边的
**⚙️ 齿轮图标**，在「控制面板」标签页里查看。

![添加服务器界面](docs/images/sunucu-ekleme.png)

### 3️⃣ 测试连接

**Go to Advanced Mode** → **Test Connection**

信息填写无误时会看到成功提示。随后**保存**该服务器。

> ⚠️ **连接失败时，第一个要检查的就是端口。**
> cPanel 使用 `2087`，Plesk 使用 `8443`。留空时模块会根据面板自动选择正确的端口；
> 如果你使用的是其他端口，请勾选 **Override with Custom Port** 并手动填写。

### 4️⃣ 创建服务器组

用 **Servers → Create New Group** 新建一个组并把服务器加进去，或者加入已有的组。
产品不是直接绑定到服务器，而是**通过服务器组**绑定的。

### 5️⃣ 配置产品

新建产品，或编辑现有产品并切换到 **Module Settings** 标签页：

| 设置项 | 值 |
|---|---|
| **Module Name** | `DNA Reseller Hosting` |
| **Server Group** | 你刚创建的组 |
| **Panel Type** | `Auto` *（如果确定服务器类型，也可以直接选择）* |
| **Package / Plan** | 你的 reseller 面板中已定义的套餐名称 |

> 💡 **cPanel 上不要写套餐前缀。** 面板中显示为 `bakcay328_paket2` 的套餐，在产品里只需填
> `paket2` —— 模块会自行补全 `用户名_` 前缀。

**保存即可 —— 模块已就绪。** 🎉

**‼️从这一刻起，你就可以在 WHMCS 的全部流程中通过该模块管理 cPanel 和 Plesk 经销商账户了。创建、暂停、删除等流程完全由 WHMCS 控制。**

---

## 🔍 日志与故障排查

日志有两处，行为各不相同：

| 日志 | 何时写入 | 记录内容 |
|---|---|---|
| **Activity Log**<br>*Utilities → Logs → Activity Log* | **始终写入** | 每一次失败的操作，带 `dnahosting:` 前缀，并附上服务编号和域名 |
| **Module Log**<br>*Utilities → Logs → Module Log* | 仅在开启 **Module Debug Mode** 时 | 发往面板的每个请求及其返回的响应 |

> 💡 Module Debug Mode 位于 **Setup → General Settings → Other → Module Debug Mode**。
> 复现问题前打开，事后记得关掉。带 `note:` 前缀的行不是请求，而是模块自身的说明。

> 🔐 API token 和客户密码**不会以明文写入日志**。

### 常见错误

| 现象 | 原因与解决办法 |
|---|---|
| *Could not determine whether this server runs cPanel or Plesk* | 两种面板都没有响应。检查端口和 token，或者在产品设置中明确指定 **Panel Type** |
| *WHM refused this login* | 该用户不是 reseller，或者 token 是在 cPanel 界面里生成的。token 必须在 **WHM** 中生成 |
| *Server returned HTTP 3xx (redirect)* | 端口错误，或者面板把请求重定向到了登录页 |
| Plesk **11003** | API 密钥是为另一个 IP 生成的 —— 请重新生成 |
| Plesk **1010** | 连续多次登录失败后面板限制了该 IP，等几分钟再试 |
| Plesk **2204** | 面板接受了请求，但在配置自身 Web 服务器时失败 —— 属于服务器端问题 |

---

## 🧩 须知事项

<details>
<summary><b>两种面板的差异</b></summary>

<br>

- **Disk Quota / Bandwidth** 产品设置只在 **cPanel** 上生效。Plesk 的限制由服务方案决定；
  即使填了这些字段也会被忽略，并在模块日志中留下一条说明。
- **Dedicated IP** 仅对 cPanel 有效。
- **Plesk 的一键登录**通过客户端区域的 *Log in to Panel* 按钮实现。由于 Plesk 不支持基于
  跳转的登录方式，服务器列表中的 *Log in to Server* 按钮无法用于 Plesk 服务器。

</details>

<details>
<summary><b>缓存机制</b></summary>

<br>

每台服务器有两项信息会缓存**七天**：

- 它运行的是哪种面板
- 它使用的 XML-API 协议版本

这两项在正常运行中不会变化，每次请求都重新探测只会带来多余的往返开销。

缓存会在以下情况下失效：

- **Test Connection** 每次都会清空缓存并重新探测
- 服务器地址、端口、用户名或 token 发生变化时自动失效
- 满七天后失效

> 🔐 缓存中不保存任何凭据；token 仅以哈希形式参与缓存键的生成。

</details>

<details>
<summary><b>产品设置项的顺序</b></summary>

<br>

WHMCS 按**位置**保存产品设置（`configoption1..5`）。因此模块的设置列表只能在**末尾**追加：

| # | 设置项 |
|---|---|
| 1 | Panel Type |
| 2 | Package / Plan |
| 3 | Disk Quota (MB) |
| 4 | Bandwidth (MB) |
| 5 | Dedicated IP |

在中间插入或调整顺序，会让所有现有产品中已保存的值悄悄发生错位。

</details>

---

## 📄 更新日志

各版本的变更记录见 [CHANGELOG.md](CHANGELOG.md)。

---

<div align="center">

**DNA Reseller Hosting** · 适用于 WHMCS 的 cPanel & Plesk 经销商模块

[domainnameapi.com](https://www.domainnameapi.com) · [Reseller 面板](https://dm.domainnameapi.com/hosting)

</div>
