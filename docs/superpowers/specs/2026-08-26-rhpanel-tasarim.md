# rhpanel — Birleşik cPanel/Plesk WHMCS Sunucu Modülü

**Tasarım Dokümanı** · 2026-08-26 · Durum: onay bekliyor · Hedef sürüm: 1.0.0

---

## 1. Ne yapıyoruz ve neden

Tek bir WHMCS provisioning (server) modülü; hem cPanel/WHM hem Plesk sunucularını **reseller
kimlik bilgileriyle** (sunucu IP + kullanıcı + API token) sürer. Bir WHMCS ürünü, içinde her iki
panel tipinden sunucu bulunan bir sunucu grubuna bağlanabilir; modül, işlem anında sunucunun
hangi panel olduğunu çözer ve doğru sürücüye yönlendirir.

### Neden var — gerekçe kanıtlanmış bir arıza

WHMCS'in kendi Plesk modülü **güncel Plesk'te bozuk**. Kaynak üzerinde doğrulandı:

- `_setWebspaceStatus` ve `_setWebspacePassword` yalnızca `Plesk_Manager_V1000` içinde tanımlı ve
  yalnızca 1.0.0.0 şablonları mevcut.
- Her ikisi de `<domain>` operatörüne köklenmiş; Plesk 18.0.80+ bunu **errcode 1014** ile reddediyor.
- `Plesk_Manager_Base::__call`, paket sürümünü **metodu tanımlayan sınıfa** sabitlediği için bu iki
  metot kalıcı olarak 1.0.0.0'a hapsolmuş durumda.

Sonuç: güncel Plesk'te **suspend, unsuspend ve change-password'ün FTP yarısı çalışmıyor**.
Terminate çalışıyor, çünkü `V1630` içinde `<webspace>` ile override edilmiş.
WHMCS tarafında WHMCS-27532 olarak Critical/Backlog, ETA yok.

Bu, modülün tasarımındaki **tek merkezi ilkeyi** belirliyor (bkz. §4.1): arızanın sebebi esneklik
değil, esnekliğin *örtük* uygulanmasıydı.

### Ek olarak doğrulanmış iki blocker

- **`serveraccesshash` çakışması.** cPanel modülü bu alanı API token olarak okuyor
  (`cpanel.php:687`); Plesk modülü aynı alanı **port numarası** olarak okuyor
  (`Loader.php:14`, `plesk.php:43`, `V1000.php:85`, `V1635.php:24`). Tek modülde tek anlam seçmek
  zorunludur.
- **Plesk modülü `serverip`'i hiç kullanmıyor**, yalnızca `serverhostname` gönderiyor. IP tabanlı
  yapılandırma bu modülle mümkün değil.

---

## 2. Kilitli kararlar

Bunlar onaylanmış ve tasarımın geri kalanı bunların üzerine kuruludur.

| # | Konu | Karar |
|---|------|-------|
| 1 | Modül sayısı | **Tek** modül dizini, WHMCS Type dropdown'unda tek giriş. Kod adı: `rhpanel` |
| 2 | Panel kapsamı | Bir ürün hem cPanel hem Plesk sunucuya düşebilir. Ürün config'i **iki** paket alanı taşır; modül çalışma anında doğru olanı seçer |
| 3 | Panel tipi kaynağı | Modüle ait tablo; TestConnection auto-probe ile tohumlanır; port yalnızca ipucu; bilinmiyorsa **fail-closed** |
| 4 | Sürüm hedefi | PHP 7.2+, WHMCS 7.8 → 8.x. Enum/typed property/match/arrow fn yok; WHMCS sınıfları için feature-detection |
| 5 | Teslim biçimi | Satılacak ürün: `lang/` (en+tr), `whmcs.json`, kurulum dökümanı, temiz admin mesajları, ionCube'a uygun yapı |
| 6 | v1 kapsam | create, suspend, unsuspend, terminate, changepassword, changepackage, testconnection, client SSO, admin SSO, **UsageUpdate**, **zengin client area**, **canlı paket/plan dropdown'ı** |
| 6b | v1 dışı | Yedekleme, DNS zone, SSL kurulumu, e-posta hesabı yönetimi, reseller ürün tipi, ürün addon'ları |
| 7 | Yetki seviyesi | **Yalnızca reseller.** cPanel paket prefix'i (`<reseller>_<Ad>`) şeffaf yönetilir |
| 8 | Plesk nesne modeli | 1 WHMCS servisi = 1 Plesk customer + 1 webspace. Paylaşılan customer modeli **kullanılmaz** |
| 9 | Plesk transport | XML-API (`/enterprise/control/agent.php`, `KEY:` başlığı), **`<webspace>` operatörü** — asla `<domain>` |
| 10 | Sürüm desteği | Geniş (eski cPanel/Plesk dahil). **Ama** dialect her operasyon için çalışma anında **açıkça** çözülür; miras zincirine gömülmez |
| 11 | Ürün tipi | Yalnızca hosting hesabı. Config ordinal'leri v2 reseller için rezerve, append-only |
| 12 | TLS | Doğrulama **açık** varsayılan; opsiyonel hostname ile IP'ye bağlanıp hostname'e karşı doğrulama |
| 13 | `serveraccesshash` | **Her iki panel için de API token.** Plesk portu `serverport`, yoksa `serversecure ? 8443 : 8880` |
| 14 | Adres alanı | Plesk sürücüsü `serverip ?: serverhostname` kabul eder |
| 15 | Geçiş | Sıfırdan / yeni kurulumlar. Mevcut hizmetleri devralma v1 dışı (veri modeli engellemez) |

---

## 3. Doğrulanmış WHMCS gerçekleri

Bu maddelerin her biri WHMCS 7.8.0 kaynağı üzerinde doğrulandı. Tasarımın çoğu doğrudan bunlara
cevap verir; varsayım değildirler.

### 3.1 İki dispatcher, uyumsuz başarı sözleşmesi

| | `ModuleCallFunction()` | `WHMCS\Service::moduleCall()` |
|---|---|---|
| Kullanan | cron, fatura ödemesi, admin butonları, API | client area, modül kuyruğu yeniden denemesi |
| Başarı testi | `$result == "success"` — **birebir string** | `is_array($r) && empty($r['error'])` |

`true`, `1`, `"Success"`, `""`, `null` — provisioning yolunda **hepsi başarısızlık**.
PHP 7 `\Error`/`\Throwable` core tarafından **yakalanmıyor**; bir TypeError isteği fatal ile öldürür.

### 3.2 `TestConnection` her zaman `serverid = 0` alır

Bu, panel-tipi tohumlama planını doğrudan etkileyen kritik bulgudur.

```php
// vendor/whmcs/whmcs-foundation/lib/Module/Server.php — getServerParams()
"serverid" => (int) $server->id,
```

Her iki çağrı noktası da (`admin/configservers.php:55`, `Admin/Setup/Servers.php:64`) **kaydedilmemiş,
yeni** bir `WHMCS\Product\Server()` kurar ve `id` atamaz → `serverid` daima `0`.
`configservers.php:47`'de `serverid` request'ten okunur ama yalnızca saklı şifreyi çözmek içindir.

Mantıken de böyle olmak zorundadır: Add Server sihirbazında Test Connection'a basıldığında sunucu
henüz kaydedilmemiştir. Çözüm §5.2'de.

### 3.3 Boş port MetaData'dan doldurulur

```php
$portNum = $server->port;
if (!$portNum) {
    $portNum = $this->getMetaDataValue("Default" . ($server->secure ? "" : "Non") . "SSLPort");
}
```

Bu yüzden MetaData'da **port anahtarları hiç verilmeyecek**. Verilirse 13. kararın Plesk port
fallback'i kalıcı olarak erişilemez olur.

### 3.4 `configoptionN` sıraya (ordinal) bağlanır

Bağlama pozisyonaldır (`while ($counter <= 24)`). Yayınlandıktan sonra bir girdi eklemek, çıkarmak
veya sırasını değiştirmek, kayıtlı tüm ürün değerlerini sessizce kaydırır. Harita **append-only**
olmak zorundadır.

### 3.5 `UsageUpdate` yalnızca sunucu parametreleri alır

Sunucu başına bir kez, `getServerParams()` ile çağrılır: `serviceid` yok, `username` yok, `domain`
yok, `configoptions` yok. Hiçbir şey döndürmez; `'success'` dışındaki boş olmayan her dönüş
başarısızlık olarak loglanır. Modül, o sunucudaki hizmetleri **kendisi** sorgulamak zorundadır.

### 3.6 Diğer tuzaklar

- `_AdminLink`, `_AdminSingleSignOn` tanımlıysa **hiç çağrılmaz** → tanı yüzeyi
  `_RenderRemoteMetaData`'ya taşınmalı.
- `_MetaData` modül **yükleme anında** çağrılır ve önbelleklenir → DB yok, ağ yok, yan etki yok.
- `_ConfigOptions` yalnızca `producttype`, `isAddon`, `action`, `whmcsVersion` alır.
- `APIVersion` `1.1` → tüm parametreler `Sanitize::decode()` edilir; `<1.1` → `convertToCompatHtml()`.
  Yanlış değer, `&`/`<`/tırnak içeren şifreleri sessizce bozar.
- `_ChangePassword`'e gelen `$params['password']` **zaten yeni şifredir**; core onu şifreli olarak
  tblhosting'e yazmıştır ve yalnızca non-success dönüşünde geri alır.
- `AutoPopulateServerConfig`, başarılı TestConnection'dan sonra **koşulsuz** çağrılır ve sonucuna
  `implode()` uygulanır → tanımsızsa PHP 8'de fatal.
- `_ClientAreaAllowedFunctions` **parametresizdir**.
- `tabOverviewReplacementTemplate`, `moduleParams` almaz; `tabOverviewModuleOutputTemplate` alır.
- `SimpleModuleConfigOption` sınıfı 7.8'de **yoktur** (tüm ağaçta sıfır eşleşme).

---

## 4. Mimari

### 4.1 Tek değişmez ilke

> **Hiçbir davranış ortamdan gelemez, miras alınamaz, örtük olamaz.**

Somut karşılıkları:

- Her panel çağrısının wire dialect'i, `(panel, operator, verb)` üçlüsüyle anahtarlanmış **bildirimsel
  bir tablodan** çözülür ve `Wire\RequestSpec`'in **zorunlu constructor argümanı** olarak taşınır.
  `PleskPacketSerializer`, `<packet version="…">` değerini yalnızca `$spec->dialect()->wire()`'dan
  okur; başka hiçbir yerden.
- `lib/` içinde `ReflectionMethod`, `getDeclaringClass`, `__call`, `debug_backtrace`, `eval`,
  `create_function` **yasaktır** ve WHMCS'e döndürülen hiçbir dizide closure bulunamaz.
  `tests/Lint/AntiPatternTest.php` bunu zorlar.
- Her yıkıcı panel çağrısı, **aynı işlem içinde okunmuş pozitif nesne kimliğine** (id veya GUID)
  bağlanır. Asla isim filtresine, asla sezgiye, asla boş filtreye.

Bu üç kural, WHMCS-27532'yi *düzeltilmiş* değil **inşa edilemez** kılar.

### 4.2 Dosya ağacı

```
modules/servers/rhpanel/
├── rhpanel.php                 ~24 adet rhpanel_* global. Her biri 1–4 satır. Mantık yok.
├── hooks.php                   Yalnızca tavsiye niteliğinde; her gövde try/catch ile yutulur.
├── index.php                   yönlendirme stub'ı
├── whmcs.json                  {"schema":"1.0","type":"servers","name":"rhpanel",...}
├── logo.png  logo_small.png
├── lang/english.php  lang/turkish.php
├── templates/
│   ├── overview.tpl            tabOverviewModuleOutputTemplate (tek slot)
│   ├── usage.tpl               overview.tpl içinden include
│   ├── sso_post.tpl            Plesk auto-POST fallback
│   ├── error.tpl               müşteriye güvenli bozulma durumu
│   ├── admin/server_badge.tpl  _RenderRemoteMetaData ile render (AdminLink DEĞİL)
│   └── admin/service_tab.tpl   _AdminServicesTabFields
├── docs/  INSTALL.md  CREDENTIALS.md  TROUBLESHOOTING.md
│         SUPPORTED-VERSIONS.md  ARCHITECTURE.md  CHANGELOG.md
├── build/ encode.sh  manifest.txt      (asla paketlenmez)
└── lib/                                 namespace WHMCS\Module\Server\RhPanel
    ├── Kernel.php              dispatch() / dispatchPure(); tek catch(\Throwable) sınırı
    ├── Bootstrap.php           autoload fallback, şema sürüm kontrolü, lisans grace
    ├── Registry.php            BİLDİRİMSEL TABLOLAR: entries, reporters, operations, panel binding
    ├── Context/                ServiceContext · ServerContext · ConfigContext · ServerCredentials
    │                           ProductConfig · ClientIdentity · ContextFactory
    ├── Panel/                  PanelProfile · PanelResolver · PanelProbe · ProbeResult
    │                           Capabilities · ServerProfileRepository
    ├── Dialect/                Dialect · DialectKey · DialectResolver
    │                           PleskDialectTable · CpanelDialectTable · VersionCompare
    ├── Plan/                   Plan · Bag · Executor · CompensatorStack · CommitCertainty
    │                           StepInterface · LocalStep · RemoteStep · SpecStep · StepSpec
    ├── Strategy/               StrategyRegistry + bildirimsel tablo
    │                           Gerçek sınıf yalnızca çok adımlı Plesk planları ve usage/sso için
    ├── Wire/                   RequestSpec · RawResponse · PanelResponse · Secret · Selector
    │                           Cpanel/…Serializer,…Parser,…ErrorMap
    │                           Plesk/PacketBuilder · PleskPacketSerializer · PleskPacketParser
    │                                 OperatorMap · PleskErrorMap
    ├── Transport/              TransportInterface · CurlTransport · LoggingTransport
    │                           TlsPolicy · TransportOptions · SecretVault · ModuleLog
    ├── Dto/                    Quota · UsageRecord · UsageBatch · AccountRef · AccountSpec
    │                           PackageRef · SuspensionState · SsoRequest · SsoTarget
    │                           AccountSummary · ConnectionReport · ServerMetaData · UserCount
    ├── Normalise/              ByteParser · Unit · CpanelUsageNormaliser · PleskUsageNormaliser
    │                           StatusNormaliser · LoginDeriver
    ├── Persist/                Schema · Migrations · MetaRepository · ServiceWriter
    │                           AccountLinkRepository · CacheRepository · EventLog
    ├── Report/                 ReporterInterface · StringReporter · ArrayReporter · SsoReporter
    │                           UsageReporter · ClientAreaReporter · HtmlReporter · ErrorCatalogue
    ├── Config/                 ConfigOptionMap · OrdinalLock · AdvancedOptions
    │                           PackageSelector · OptionLoader
    ├── Support/                Lang · Arr · RequestVar · InstallToken · Licence · Compat
    └── Exception/              RhPanelException · ConfigurationException · TransportException
                                ProtocolException · DialectUnavailableException
                                PanelErrorException · PartialFailureException
                                PanelTypeUnknownException
```

Yaklaşık 60 kaynak dosya. İstisna sınıfı sayısı 8'dir: `ObjectNotFound / ObjectExists /
PermissionDenied / QuotaExceeded` ayrı sınıflar değil, `PanelErrorException::kind()` sabitleridir.

### 4.3 Dispatch — iki yol, bir tane değil

`_MetaData`, `_ConfigOptions`, `_ClientAreaAllowedFunctions`, `_ClientAreaCustomButtonArray`,
`_AdminCustomButtonArray` ve `_LoginLink` **saf / sunucusuz** fonksiyonlardır.
`Kernel::dispatchPure()` ile çalışırlar: Bootstrap yok, DB yok, PanelResolver yok.

Diğer her şey `Kernel::dispatch($entryName, $params, $audience)` ile gider. Reporter,
`Registry::reporterFor($entryName)` ile **Bootstrap'tan ve registry aramasından ÖNCE** seçilir —
böylece operasyon nesnesi kurulamadan oluşan bir hata bile doğru **şekli** döndürür.
Reporter'ın kendi `render()`'ı da ikinci bir try/catch ile sarılıdır; son çare
`lastResort($ref)` ne DB'ye ne Lang dosyasına dokunur.

**Audience (hedef kitle)** `dispatch()`'in açık bir argümanıdır. Hata nesnesi `adminMessage` ve
`clientMessage` taşır; `AudienceLeakTest` her müşteri-hedefli girişi her arıza personasına karşı
çalıştırır ve çıktıda hostname, IP, sürüm, plan adı veya `RHP-` kodunun **bulunmadığını** doğrular.

---

## 5. Panel tipi çözümleme

Tasarımın en kritik yapısal parçası. `serverid`, sağlama (provisioning) ve cron bağlamlarında
mevcuttur — **ama TestConnection'da değildir** (§3.2).

### 5.1 Neden ürün config'inde olamaz

`configoptionN` ürün başınadır ve **yalnızca sunucu parametresi** alan bağlamlarda hiç bulunmaz:
`UsageUpdate`, `TestConnection`, `ListAccounts`, `GetUserCount`, `GetRemoteMetaData`,
`AdminSingleSignOn` ve ConfigOptions `Loader`. Panel tipi ürün config'inde tutulursa bu yedi giriş
noktasının sürücü seçme imkânı olmaz.

### 5.2 Anahtarlama: kimlik parmak izi + serverid

`mod_rhpanel_servers` **birincil anahtarı `cred_fingerprint`**'tir, `serverid` değil:

```
cred_fingerprint = sha1(ip | hostname | port | secure | username | sha1(token))
serverid         = nullable ikincil kolon + index
```

Akış:

1. **TestConnection** (`serverid = 0`): probe eder, sonucu **fingerprint** ile yazar.
   Sunucu henüz kaydedilmemişken bile çalışır — Add Server sihirbazının doğal akışı budur.
2. **İlk provisioning/cron çağrısı** (`serverid` mevcut): önce `serverid` ile arar; bulamazsa
   fingerprint ile arar ve satırı o `serverid`'ye **bağlar** (adopt).
3. Kimlik bilgisi değişirse fingerprint değişir; satır kendiliğinden geçersizleşir.

`AutoPopulateServerConfig` de aynı `serverid = 0` parametreleriyle çağrıldığından aynı yolu kullanır.

### 5.3 `PanelResolver::resolve()` — öncelik sırası

1. **`serverid` yalnızca `ServiceContext` ve cron `ServerContext`'inde zorunludur.** Bu iki bağlamda
   `ContextFactory`, `serverid` yok veya `<= 0` ise **atar** (fail-closed giriş kapısı) — çünkü
   `getServerParams()`'ın sunucusuz dalı anahtarı tamamen atlar, dolayısıyla `isset` kontrolü şarttır.
   TestConnection ve AutoPopulateServerConfig **istisnadır**: bunlar `serverid = 0` ile gelen
   *probe bağlamı*dır (§3.2) ve yalnızca fingerprint ile çalışırlar; `ContextFactory` bu iki giriş
   için `serverid` istemez.
2. Per-request memo **`cred_fingerprint` ile anahtarlanır**, `$sid` ile değil — çünkü probe
   bağlamında `$sid` daima `0`'dır ve `0` ile anahtarlanmış bir memo, art arda test edilen iki farklı
   sunucuyu birbirine karıştırır. Anahtarsız bir static ise usage cron'u tüm sunucuları tek proseste
   dolaştığı için sunucular arası veri sızıntısıdır.
3. `panel_type_source === 'manual'` ve tip biliniyorsa → **her zaman onu döndür.** Fingerprint
   uyuşmazlığı yalnızca `fingerprint_stale` uyarısı üretir; tipi asla değiştirmez.
4. Aksi hâlde fingerprint uyuşmazlığı satırı tamamen geçersiz kılar.
5. `panel_type === 'conflict'` → `PanelTypeUnknownException`. Açık admin onayı gerekir.
6. Tip biliniyor ve fingerprint uyuyorsa → döndür. **`panel_type` ve `identity_kind` asla
   zaman aşımına uğramaz.** Yalnızca `product_version` / `wire_protos` / `capabilities` 24 saatlik
   tazelik bayrağı taşır ve **yalnızca etkileşimli** bağlamlarda yenilenir — sağlama ve cron sıcak
   yolunda asla.
7. Iska → probe, **bloklamayan `GET_LOCK('rhpanel.probe.'.$fingerprint, 0)`** altında
   (aynı sebeple fingerprint; `$sid` probe bağlamında `0`'dır ve tüm probe'ları tek kilide toplar). Kilidi alamayan
   mevcut satırı sunar, yoksa atar. **`probe_status` probe başında asla yazılmaz** (thundering herd
   ve cPHulk kilitlenmesi buradan doğar). Başarısız probe yalnızca hata alanlarını yazar;
   `panel_type` veya `identity_kind`'a **asla dokunmaz**. Farklı tip döndüren **başarılı** bir probe
   `panel_type='conflict'` yazar ve onaya kadar her şeyi reddeder.

### 5.4 `PanelProbe` — yalnızca kimlik doğrulanmış pozitifler

Port probe'u **sıralar, karar vermez.** `resolved_port`, probe'un başarılı olduğu porttur ve
`Transport` onu `serverport`'a tercih eder.

- **cPanel ancak şu iki koşul birden sağlanırsa doğrulanır:** `/json-api/version?api.version=1`
  HTTP 200 + makul sürüm taşıyan çözümlenebilir JSON, **ve** `/json-api/myprivs?api.version=1`
  beklenen anahtarları taşıyan bir yetki haritası. Proxy, WAF veya captive portal ikisini birden
  sağlayamaz. `myprivs` ayrıca `identity_kind`'ı ve eksik ACL listesini doldurur
  (`create-acct, suspend-acct, kill-acct, passwd, upgrade-account, list-accts, list-pkgs,
  create-user-session, show-bandwidth`).
- **Plesk ancak** `KEY:` başlığıyla `<packet version="1.6.3.0"><server><get_protos/></server></packet>`
  çözümlenip ≥1 `//protos/proto` verirse doğrulanır. errcode 1001'de **bir kez**
  `HTTP_AUTH_LOGIN`/`HTTP_AUTH_PASSWD` ile yeniden denenir ve `capabilities.authMode='legacy'`
  kaydedilir. Kimlik, kapsamlı bir izin probe'u ile belirlenir — **asla `'admin' === $login`
  karşılaştırmasıyla değil.**
- Probe sonucu üç durumludur: `confirmed` / `auth-failed` / `unreachable`; ayrı istisna tipleri ve
  ayrı admin mesajları. "Yanlış token" asla "panel tipi bilinmiyor" olarak görünmez.
- Zaman aşımı: bağlantı 5 sn, toplam 20 sn.
- **Dialect bayatlaması:** `wire_protos` şu üç durumda geçersizleşir — (a) `product_version`
  değiştiğinde, (b) errcode **1014** taşıyan herhangi bir `ProtocolException` (anında yeniden
  müzakere + tam bir kez yeniden deneme), (c) panel-tipi TTL'inden bağımsız 7 günlük sabit TTL.

### 5.5 Bilinmeyen panel tipinde ne döner

| Giriş | Dönüş |
|---|---|
| Yaşam döngüsü | Hata string'i: sunucu adı, her iki probe'un sonucu, `[RHP-1001/ref]`. **Hiçbir panel çağrısı denenmez** |
| `TestConnection` | `['error' => …]` + tam transkript events tablosunda |
| `ListAccounts` | `['success'=>false,'accounts'=>[],'error'=>…]` |
| `GetUserCount` / `GetRemoteMetaData` / `AutoPopulateServerConfig` | `['success'=>false,'error'=>…]` (+ `assignedIps'=>[]`) |
| SSO | `['success'=>false,'errorMsg'=>…]` |
| `UsageUpdate` | Hata string'i + events satırı + `last_usage_error` |
| `ClientArea` | `error.tpl` + `clientMessage()` |
| ConfigOptions `Loader` | `InvalidConfiguration` atar |

### 5.6 TLS politikası

`Transport\TlsPolicy` yalnızca yerli `tblservers` alanlarını okur; uydurma UI yoktur.

| Yapılandırma | Davranış | `tls_mode` |
|---|---|---|
| IP **ve** hostname | URL host'u hostname, `CURLOPT_RESOLVE` ile IP'ye bağlan, `VERIFYPEER=1`, `VERIFYHOST=2` | `pinned` |
| Yalnızca hostname | Düz doğrulanmış TLS | `verified` |
| Yalnızca IP | **TOFU**: ilk başarılı probe'da leaf SHA-256 kaydedilir; sonrasında pin'e karşı doğrulanır, uyuşmazlıkta **fail-closed** | `tofu` |
| `tls.insecure=1` | Doğrulama kapalı; TestConnection her çalıştırmada uyarır | `insecure` |

Çıkış proxy'si yapılandırılmışsa curl `CURLOPT_RESOLVE`'u yok sayar; `TlsPolicy` bunu tespit eder,
numarayı atlar ve uyarı kaydeder. **Sahip olmadığımız bir doğrulama duruşunu asla iddia etmeyiz.**

**Her API çağrısında `CURLOPT_FOLLOWLOCATION = false`.** Yönlendirme takibi, `KEY:`/`Authorization`
başlığını yönlendirme hedefine sızdırır. Herhangi bir 3xx, `Location` başlığını adıyla anan bir
`ProtocolException`'dır — ki bu aynı zamanda doğru tanıdır: 3xx, POST'u GET'e çevirip paket gövdesini
düşürür ve `agent.php`'nin HTML giriş sayfasını HTTP 200 ile üretir.

---

## 6. Veritabanı şeması

Beş tablo. Sahibi `Persist\Schema`'dır; başka hiçbir yer DDL çalıştırmaz.

```sql
CREATE TABLE IF NOT EXISTS `mod_rhpanel_meta` (
  `k` VARCHAR(64) NOT NULL,
  `v` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- satırlar: schema_version, install_token, install_url, licence_grace_until, module_version

-- DİKKAT: birincil anahtar cred_fingerprint'tir, serverid DEĞİL (bkz. §3.2, §5.2)
CREATE TABLE IF NOT EXISTS `mod_rhpanel_servers` (
  `cred_fingerprint`      CHAR(40)     NOT NULL,
  `serverid`              INT UNSIGNED     NULL,               -- TestConnection'da bilinmez
  `panel_type`            VARCHAR(16)  NOT NULL DEFAULT 'unknown', -- cpanel|plesk|unknown|conflict
  `panel_type_source`     VARCHAR(16)  NOT NULL DEFAULT 'probe',   -- probe|manual
  `product_version`       VARCHAR(32)  NOT NULL DEFAULT '',
  `wire_protos`           TEXT             NULL,                -- JSON ["1.6.9.1", ...]
  `identity_kind`         VARCHAR(16)  NOT NULL DEFAULT 'unknown', -- admin|reseller|customer|unknown
  `identity_login`        VARCHAR(191) NOT NULL DEFAULT '',
  `capabilities`          TEXT             NULL,                -- JSON
  `resolved_port`         SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- probe'un BAŞARILI olduğu port
  `tls_mode`              VARCHAR(16)  NOT NULL DEFAULT 'unknown', -- verified|pinned|tofu|insecure
  `tls_cert_sha256`       CHAR(64)     NOT NULL DEFAULT '',
  `probe_ok_at`           DATETIME         NULL,
  `probe_last_error`      TEXT             NULL,
  `probe_fail_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `warnings`              TEXT             NULL,                -- JSON []
  `last_usage_success_at` DATETIME         NULL,
  `last_usage_error`      TEXT             NULL,
  PRIMARY KEY (`cred_fingerprint`),
  UNIQUE KEY `serverid` (`serverid`),
  KEY `panel_type` (`panel_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_rhpanel_accounts` (
  `serviceid`      INT UNSIGNED NOT NULL,
  `serverid`       INT UNSIGNED NOT NULL,
  `panel_type`     VARCHAR(16)  NOT NULL,            -- bu servisin SAĞLANDIĞI panel
  `external_id`    VARCHAR(191) NOT NULL DEFAULT '',
  `customer_id`    VARCHAR(64)  NOT NULL DEFAULT '',
  `customer_guid`  VARCHAR(64)  NOT NULL DEFAULT '',
  `webspace_id`    VARCHAR(64)  NOT NULL DEFAULT '',
  `webspace_guid`  VARCHAR(64)  NOT NULL DEFAULT '',
  `panel_login`    VARCHAR(191) NOT NULL DEFAULT '', -- yetkili kaynak; tblhosting.username'i yansıtır
  `webspace_name`  VARCHAR(191) NOT NULL DEFAULT '',
  `plan_ref`       VARCHAR(191) NOT NULL DEFAULT '', -- gerçekte kullanılan yerel referans
  `our_status_bit` SMALLINT     NOT NULL DEFAULT 0,  -- suspend anında BİZİM set ettiğimiz bit
  `last_status`    INT              NULL,
  `owner_shared`   TINYINT(1)   NOT NULL DEFAULT 0,
  `pending_sync`   VARCHAR(64)  NOT NULL DEFAULT '', -- csv: password,plan,verify
  `orphan_since`   DATETIME         NULL,
  `orphan_misses`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`     DATETIME         NULL,
  PRIMARY KEY (`serviceid`),
  UNIQUE KEY `external_id` (`external_id`),
  KEY `serverid` (`serverid`),
  KEY `panel_login` (`panel_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_rhpanel_cache` (
  `fingerprint` CHAR(40)     NOT NULL,
  `cache_key`   VARCHAR(128) NOT NULL,
  `payload`     MEDIUMTEXT       NULL,
  `expires_at`  DATETIME     NOT NULL,      -- NOT NULL: asla belirsiz değil
  PRIMARY KEY (`fingerprint`,`cache_key`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_rhpanel_events` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref`         CHAR(8)      NOT NULL,
  `serverid`    INT UNSIGNED NOT NULL DEFAULT 0,
  `serviceid`   INT UNSIGNED NOT NULL DEFAULT 0,
  `severity`    VARCHAR(8)   NOT NULL DEFAULT 'error',   -- error|repair|warn
  `code`        VARCHAR(16)  NOT NULL DEFAULT '',
  `step_id`     VARCHAR(64)  NOT NULL DEFAULT '',
  `dialect`     VARCHAR(32)  NOT NULL DEFAULT '',
  `detail`      TEXT             NULL,                   -- HER ZAMAN redakte
  `created_at`  DATETIME     NOT NULL,
  `resolved_at` DATETIME         NULL,
  PRIMARY KEY (`id`),
  KEY `ref` (`ref`), KEY `serverid` (`serverid`), KEY `serviceid` (`serviceid`),
  KEY `sev_time` (`severity`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

`mod_rhpanel_events`, `logModuleCall`'un ModuleDebugMode kapalıyken **hiçbir şey yapmaması**
sorununun cevabıdır. Koşulsuz yazılır, kurulum başına en yeni 2.000 satırla sınırlanır, sunucu
rozetinde ve servis admin sekmesinde gösterilir. Her admin hata mesajı `[RHP-xxxx/ref]` ile biter
ve `ref` bu tabloyu indeksler.

### 6.1 Şema sahipliği ve migration

- `Persist\Migrations::$list` sıralı bir `int sürüm => 'metodAdı'` dizisidir.
  `Schema::ensure()` mevcut sürümü okur, **bloklamayan** `GET_LOCK('rhpanel.schema', 0)` alır,
  bekleyen migration'ları sırayla çalıştırır, yeni sürümü damgalar, kilidi bırakır.
- `Schema::ensure()` **yalnızca iki yerden** çalışır: `rhpanel_TestConnection` ve sunucu rozetinden
  erişilen açık "Yükseltmeyi çalıştır" admin eylemi. **Sağlama yolundan asla.**
- Sıcak yol tek bir memoize edilmiş `SELECT` yapar. Satır yoksa veya geride kaldıysa
  `ConfigurationException(RHP-1002)` atar: *"rhpanel'in veritabanı tabloları eksik veya güncel değil.
  Setup → Products/Services → Servers → &lt;ad&gt; → Test Connection'ı bir kez çalıştırın."*
- DDL izin hatasında mesaj tam ifadeyi adıyla anar; ifadeler `INSTALL.md`'de de basılıdır.

---

## 7. Dialect katmanı — WHMCS-27532'nin düzeltmesi

Dialect, `DialectResolver` tarafından `(panel, operator, verb)` ile anahtarlanmış bildirimsel bir
adaydan çözülür, profilin `wire_protos` ile advertise ettiği sürümlerle kesiştirilir ve
`RequestSpec`'in **zorunlu argümanı** olarak taşınır.

Dört bağımsız koruma (golden guard) CI'da zorlanır:

1. Üretilen her `<packet version="X">`, o adım için `DialectResolver`'ın döndürdüğü sürüme **eşit**
   olmak zorundadır. Toplu golden yenilemesi bu iddiayı kutsayamaz — bağımsız XPath testidir.
2. `lib/` içinde `ReflectionMethod` / `getDeclaringClass` / `__call` / `debug_backtrace` yasak.
3. Hiçbir `set`/`del` golden'ında boş `<filter/>` bulunamaz.
4. Suspend / unsuspend / şifre yollarında `<domain>` operatörü hiçbir dialect'te üretilemez.

Matris testi ayrıca her `(operasyon, profil)` çifti için **tam olarak bir** strateji eşleştiğini
(belirsizlik yok, boşluk yok) ve her `RemoteStep`'in hem kendi aday listesinde hem profilin advertise
ettiği sürümlerde bulunan bir dialect'e çözüldüğünü doğrular.

---

## 8. 14 uyuşmazlığın çözümü

### 8.0 Selector — toplu silme koruması

Plesk'te **boş `<filter/>` "TÜM nesneler" demektir.** Koşulsuz bir `customer.del` ile birleşince tek
bir eksik bağ satırı reseller çapında bir silme olur.

- `Selector::of()` boş değerde **atar**. `set`/`del`/filtreli `get` için `RequestSpec` en az bir
  Selector ister; yoksa constructor atar.
- Serializer, boş değerli veya çocuksuz render olacak bir filtre düğümünde `ProtocolException` atar.
- **Yıkıcı Plesk verb'leri yalnızca `BY_ID` / `BY_GUID` kabul eder.** `BY_NAME` construction'da
  reddedilir. İsimler salt-okunur arama anahtarıdır, asla mutasyon hedefi değil. (Bu aynı zamanda
  "domain yeniden adlandırıldıktan sonra `webspace.set` sessizce hiçbir şey yapmaz ama iki şifrenin
  de uygulandığını bildirir" hatasını da kapatır.)

### 8.1 Plesk customer + webspace çifti

`external_id = 'rhp-' . InstallToken::get() . '-' . $serviceId`

**`InstallToken`** `mod_rhpanel_meta`'da üretilir; yanında üretim anındaki SystemURL saklanır.
Canlı SystemURL farklıysa **tüm yıkıcı işlemler reddedilir**: *"Bu WHMCS kurulumu bir klon gibi
görünüyor — rhpanel panel nesnelerini silmeden önce sunucu rozetinden onaylayın."*
Ayrıca her probe uzak external-id'leri örnekler; **farklı** bir `rhp-` token'ı taşıyan varsa
TestConnection *"bu sunucuda başka bir WHMCS kurulumu customer yönetiyor"* uyarısı verir.
Bu tek koruma, staging klonunun production'ı silmesi felaketini engeller.

**Create planı — yeniden başlatılabilir (sadece retry-safe değil):**

| # | Tür | Adım | Koşul |
|---|---|---|---|
| 1 | Yerel | `login.derive` | her zaman |
| 2 | Yerel | `link.upsert` — absorbe edilmiş id'leri korur | her zaman |
| 3 | Yerel | `plan.resolve` | her zaman |
| 4 | Uzak | `plesk.customer.get` by `external-id` → `customer_id`, `customer_guid` absorbe | her zaman |
| 5 | Uzak | `plesk.customer.add` | `!$bag->has('customer_id')` |
| 6 | Uzak | `plesk.webspace.get` by `name` → `webspace_id`, `webspace_guid` absorbe | her zaman |
| 7 | Uzak | `plesk.webspace.add` | `!$bag->has('webspace_id')` |
| 8 | Yerel | `link.commit` + ServiceWriter ile username/password geri yazımı | her zaman |

5. adımın telafisi `customer.del` **by `customer_id`**'dir ve yalnızca 7. adımın kesinliği
`NOT_COMMITTED` iken tetiklenir. "Nesne zaten var" dönen bir `webspace.add`, **zaten sağlanmış**
kabul edilir: getir, `owner-id`'nin bizim customer'ımız olduğunu doğrula, devam et.

### 8.2 Telafi (compensation) ve commit kesinliği

`CommitCertainty` üç durumludur: `NOT_COMMITTED` / `UNKNOWN` / `COMMITTED`.

- Telafi, **herhangi bir yanıt döner dönmez, ayrıştırmadan ÖNCE** yığına itilir.
- Telafi **yalnızca `NOT_COMMITTED`** durumunda çalışır. Bir yanıt-ayrıştırma değişikliği, başarıyla
  sağlanmış bir hesabı asla silemez.
- `UNKNOWN` durumunda **hiçbir yıkıcı şey olmaz**: `pending_sync += verify`, nesneyi adıyla anan bir
  repair olayı ve `PartialFailure` mesajı.

### 8.3 Suspend — bitmask ve bayrak+sebep

`Dto\SuspensionState{suspended, byAdmin, byReseller, raw}`; 0/16/32/48/64 ve cPanel'in boolean'ını
bilen tek yer `StatusNormaliser`'dır.

- **cPanel:** `suspendacct?user&reason` / `unsuspendacct`, ardından `accountsummary` ile **geri okuma**
  ve niyetle eşleştiğinin doğrulanması. Durumu gerçekten değiştirmemiş bir `result:1` başarısızlıktır.
- **Plesk suspend:** `gen_info` oku → `$new = $cur | $ourBit`. `$ourBit` reseller kimliğinde 32,
  admin'de 16'dır ve **probe'dan** gelir, asla login string karşılaştırmasından. Hem customer hem
  webspace nesnesi set edilir (cPanel'de askı panel girişini de engellediği için). `$ourBit`
  `mod_rhpanel_accounts.our_status_bit`'e yazılır.
- **Plesk unsuspend:** temizlenen bit, bağ satırındaki `our_status_bit`'tir — mevcut kimlikten
  türetilen bit değil (kimlik bilgisi değişince servisler kalıcı olarak sıkışır). Kalan (residual)
  **yazmadan önce** hesaplanır: yetkimiz dışında bit içeriyorsa **hiç yazılmaz**, salt-okunur
  `RHP-2204` döner: *"Hiçbir şey temizlenmedi: bu abonelik aynı zamanda Plesk yöneticisi tarafından
  askıya alınmış (durum biti 16). Reseller kimlik bilgileri yönetici askısını kaldıramaz."*
  Meşru yazımdan sonra `gen_info` yeniden okunur ve gözlenen durumun niyete eşit olduğu doğrulanır;
  uyuşmazlık sert hatadır, asla `'success'` değil.
- `suspendreason`: Plesk'te alan yok. Varsayılan `suspendreason.mode=log` → `logActivity` + bağ satırı
  + modül logu. İsteğe bağlı `=description` müşteriye görünür alana yazar (varsayılan kapalı).
  **Asla sessizce düşürülmez**; dökümanda ve TestConnection'da belirtilir.

### 8.4 Plesk'te iki şifre

- Kullanılan operatör **`<customer><set>` + `<values><gen_info><passwd>`**'dir — `set_password`
  diye bir operatör **yoktur**.
- **Sıra: önce panel girişi, sonra FTP/sistem.** Panel şifresi, WHMCS'in sakladığı şifrenin gerçekte
  temsil ettiği kimlik bilgisidir.
  - Panel set başarısız → hiçbir şey değişmedi → başarısızlık dön, durum tutarlı.
  - Panel set başarılı, FTP set başarısız → **`'success'` dön** (panel, WHMCS'in sakladığı kimlik
    bilgisini kabul etti), `pending_sync += password` yaz, repair olayı yaz, admin sekmesinde ve
    müşteri alanında "FTP şifresini yeniden eşitle" eylemi göster.
- **Kimsenin bilmediği üçüncü bir değer asla yazılmaz.** Rastgeleye geri döndürme dalı yoktur.
- cPanel'de tek `passwd` çağrısı her iki hedefi de karşılar.

### 8.5 Paket referansı — prefix ve GUID

- `PackageSelector::select()`, çözülen panelin ordinal'i **boşsa hiçbir panel I/O'sundan önce**
  `ConfigurationException` atar. (Aksi hâlde filtresiz `service-plan.get` müşteriyi rastgele bir
  plana geçirir.)
- **cPanel aday sırası kimlik farkındadır:** `identity_kind === 'reseller'` iken önce
  `"{$serverusername}_{$name}"`, sonra birebir, sonra büyük/küçük harf varyantları.
  **Hem** prefix'li **hem** çıplak biçim mevcutsa bu bir **belirsizlik hatasıdır**, ilk-eşleşen
  kazanmaz: *"srv-de-01 üzerinde hem 'Gold' hem 'acme_Gold' var. `cpanel.pkgprefix=force` veya
  `=never` ile netleştirin."*
- Karşılaştırma `mb_strtolower($s, 'UTF-8')` ile yapılır, **asla `strcasecmp` ile değil** —
  `tr_TR` locale'inde I↔ı eşlemesi Türkçe plan adlarını bozar.
- De-prefixing yalnızca baştaki `$serverusername . '_'` dizisini soyar; **asla
  `explode('_', $p)[1]` ile değil** (WHMCS'in modülündeki hata bu ve alt çizgi içeren her paket adını
  bozar). Soyma başka bir girdiyle çakışacaksa ham ad gösterilir.
- **Plesk:** `service-plan.get` birebir adla filtrelenir; `absorb()` **tam olarak bir** `<result>`
  düğümü **ve** dönen plan adının istenenle birebir eşitliğini doğrular. `webspace.add` `plan-name`,
  `switch-subscription` `plan-guid` kullanır. 300 sn önbellek.
- Çözülen yerel referans `plan_ref`'e yazılır; böylece kayma görünür olur.
- Hata mesajı, bu kimliğe gerçekten görünen **on adede kadar plan adını listeler.**

### 8.6 Kullanım birimleri ve sentinel'ler

Üç durum, belirsizlik yok:

| Durum | Anlam | tblhosting'e etkisi |
|---|---|---|
| `Quota::bytes(n)` | Gerçek değer | MB'ye çevrilip yazılır |
| `Quota::unlimited()` | Sınırsız | `0` yazılır (core'un konvansiyonu) |
| `null` | **Panel bildirmedi** | **Kolon UPDATE'ten tamamen çıkarılır** — son iyi değer korunur |

- `ByteParser::parse($value, $requiredUnit)` — **birimsiz çağrı yeri yoktur.** Eski WHM
  `listaccts`'ten gelen `'512'`, `Unit::MEGABYTES` ile ayrıştırılır ve 512 bayt → 0 MB → "sınırsız"
  zincirine giremez.
- "cPanel `disklimit`'te `0` sınırsız demektir" kuralı **yalnızca o alana** kapsamlıdır ve
  `CpanelUsageNormaliser` içindedir, asla `ByteParser` içinde değil.
- Plesk `-1`, **herhangi bir aritmetikten önce**, ayrıştırma sınırında `unlimited` olur. Hiçbir yerde
  bir limit tekdüze bölünmez.
- Tek dönüşüm `ServiceWriter`'da: `(int) floor($bytes / 1048576)`, sıfır olmayan girdiler için en az
  1 MB. Parser `float|null` döndürür ki 32-bit derlemeler çok-TB değerlerde `intdiv` içinde
  `TypeError` vermesin.
- cPanel reseller yolu ile hesap yolu **aynı** normalizer'ı paylaşır (WHMCS'in modülünde paylaşmıyor
  ve reseller rakamları bugün zaten tutarsız).

### 8.7 SSO şekil farkı

- **cPanel:** `create_user_session` → `data.url` (kullanıma hazır tam URL). `serversecure` false ise
  `https:`→`http:` ve port yeniden yazımı korunur. Session-IP doğrulaması için önemli olan
  **müşterinin** IP'sidir; WHMCS `get_ip()` kullanılır (kurulumun proxy başlık yapılandırmasına
  saygı duyar), ham `REMOTE_ADDR` değil. Probe `cookieipvalidation` tweak'ini okur ve katıysa uyarır.
- **Plesk kademe 1:** `server.create_session` → çıplak session id →
  `rsession_init.php?PLESKSESSID={id}&success_redirect_url={…}`. **`capabilities.rsessionGet`
  koşuluna bağlıdır**; bu yetenek `unknown` başlar ve `Location` başlığı incelenerek bir kez
  probe edilir (yalnızca "3xx geldi" demek yetmez — `rsession_init.php` geçersiz oturumda giriş
  sayfasına 302 verir).
- **Plesk kademe 2 (canlı panelde kanıtlanana kadar VARSAYILAN):** otomatik gönderilen POST formu.
  `ServiceSingleSignOn` kesin bir `errorMsg` ile `success=false` döner ve `overview.tpl` içinde
  `sso_post.tpl` render edilir — müşteri yine tek tıkla girer. Session id URL'de, tarayıcı
  geçmişinde, `Referer`'da veya proxy logunda **hiç görünmez**.
  Kademe sırası `sso.plesk_tier=post|get` ile yapılandırılır, varsayılan `post`.
- **`AdminSingleSignOn` `ServerContext` kullanır** — `username()` metodu yoktur ve
  `$params['username']` okumayı reddeder.

### 8.8 `UsageUpdate` — yalnızca sunucu parametresi, ve ölçekte ayakta kalmalı

1. `usage.enumerate`: `tblhosting`, `packageid` üzerinden `tblproducts`'a join edilir ve
   **`tblproducts.servertype = 'rhpanel'`** koşulu uygulanır (aksi hâlde bu modülün sahibi olmadığı
   hizmetler sayılır), `server = :sid`, `domainstatus IN ('Active','Suspended')`,
   `mod_rhpanel_accounts` ile left join. İmleç `mod_rhpanel_cache`'te saklanır; çok büyük bir sunucu
   sonraki cron'da kaldığı yerden devam eder, sonsuza kadar başarısız olmaz.
   Geçiş başına sert üst sınır (`usage.max_per_pass`, varsayılan 1500).
2. Yanıt gövdesi boyut sınırı, decode öncesi `memory_get_usage()` başlık payı kontrolü, duvar saati
   son tarihi. **PHP bellek fatal'ı `catch(\Throwable)` ile yakalanamaz** — bu yüzden sınırlar
   önlemsel olmak zorundadır.
3. Kesilen satırlar **atlanır, kaçırılmaz**; `orphan_since` yalnızca **3 ardışık ıskadan sonra** yazılır.
4. Plesk filtreleri parçalanır (chunk), sonuç başına durum eşlemesi yapılır. 250 hizmet → tam 3 paket.
5. `UsageReporter` başarısızlıkta hata string'i döner (core'un sunduğu tek arıza sinyali budur),
   ayrıca `last_usage_error` / `last_usage_success_at` yazılır ve N gün başarısızlıkta rozet kırmızıya döner.

### 8.9 Kalan uyuşmazlıklar

| # | Konu | Çözüm |
|---|---|---|
| 9 | Doğal anahtar farkı (username / domain / webspace adı) | `AccountRef` üçünü de taşır; her sürücü kendi ihtiyacını okur. `panel_login` yetkili kaynaktır |
| 10 | Dialect / sürüm müzakeresi | §7 — bildirimsel tablo + dört golden guard |
| 11 | `serveraccesshash` | Her iki panelde token. Plesk portu `serverport`, yoksa `serversecure ? 8443 : 8880` |
| 12 | `serverip ?: serverhostname` | Plesk sürücüsü ikisini de kabul eder |
| 13 | TLS doğrulama | §5.6 |
| 14 | Ordinal donması / 24 slot | §9 |
| — | Plesk servis-başı limit override'ı ve Dedicated IP | Reseller kimliğiyle Dedicated IP create'te **sert hata** (sağlamayız). Disk/BW override'ları yazılır, **hemen geri okunur** ve tutmadıysa hata atılır. TestConnection, her erişilebilir ürünün yapılandırılmış limitlerini panel planının gerçek limitleriyle karşılaştıran bir **katalog farkı** raporlar |

---

## 9. Config option haritası — dondurulmuş

`Config\ConfigOptionMap` tek doğruluk kaynağıdır. `_ConfigOptions` bu haritayı **dolaşarak üretilir**
ve bir lint kuralı `/configoption\d/` desenini başka her yerde yasaklar.

```php
public static function order() {   // YALNIZCA EKLE. Asla araya sokma, sıralama, silme.
    return array(
        1 => 'cpanel_package',
        2 => 'plesk_plan',
        3 => 'disk_mb',
        4 => 'bw_mb',
        5 => 'dedicated_ip',
        6 => 'login_target',
        7 => 'advanced',
    );
}
```

| # | Slug | Görünen ad | Tip | Panel |
|---|---|---|---|---|
| 1 | `cpanel_package` | cPanel Package Name | `text` + `Loader` + `SimpleMode` | cPanel |
| 2 | `plesk_plan` | Plesk Service Plan | `text` + `Loader` + `SimpleMode` | Plesk |
| 3 | `disk_mb` | Disk Quota (MB) | `text` | ortak (boş = plan varsayılanı) |
| 4 | `bw_mb` | Bandwidth Limit (MB) | `text` | ortak |
| 5 | `dedicated_ip` | Dedicated IP | `yesno` | ortak (Plesk'te admin kimliği şart, yoksa sert hata) |
| 6 | `login_target` | Panel Login Target | `dropdown` | ortak |
| 7 | `advanced` | Advanced Options | `textarea` | ortak |
| **8–24** | — | **REZERVE, ÜRETİLMEZ** | — | v2 reseller 8–17, üçüncü panel 18–24 |

**Yalnızca 7 girdi döndürülür ve mezar taşı (tombstone) yoktur.** Sebep kritik: `FriendlyName`
dizinin **dış anahtarıdır**, dolayısıyla `(reserved)` adlı on girdi **tek bir eleman**a çöker ve
sonraki her ordinal'i sessizce kaydırır — üstelik admin'lerin içine yazacağı on adet düzenlenebilir
metin kutusu olarak render olur. Sondaki girdileri hiç üretmemek `configoption8..24`'ü boş bırakır;
ileride ekleme doğal olarak 8'e düşer, çünkü bağlama iterasyon pozisyonuna göredir.

Altı cPanel'e özgü sayaç (shell, CGI, maxftp, maxpop, maxsql, maxsub) ve Plesk addon planları
`advanced` metin alanına taşındı: yedi yerine tek ordinal, cPanel `createacct`'e alan eklediğinde
kod değişikliği yok, ve sıfır yerine **17 gerçekten boş ordinal**.

`_ConfigOptions`, `producttype` × `isAddon` × `whmcsVersion`'ın **her kombinasyonu için birebir aynı
sıralı anahtar listesini** döndürür. Uygulanamaz seçenekler, `Description` alanında gerekçesiyle
etkisiz hâle getirilir — **asla listeden çıkarılmaz**. `OrdinalLock` testi kartezyen çarpımı dolaşır
ve `count() === 7` ile bayt-birebir sıralı anahtar eşitliğini doğrular.

### 9.1 Advanced anahtarları (beyaz liste ile doğrulanır)

```
cpanel.pkgprefix=auto|force|never   cpanel.theme=      cpanel.hasshell=0|1   cpanel.cgi=0|1
cpanel.maxftp=  cpanel.maxpop=  cpanel.maxsql=  cpanel.maxsub=  cpanel.maxpark=  cpanel.maxaddon=
cpanel.keepdns=0|1
plesk.addon_plans=a,b   plesk.ipmode=   plesk.htype=vrt_hst|none
plesk.poweruser=0|1     plesk.delete_owner=0|1
suspendreason.mode=log|description|none    sso.plesk_tier=post|get    sso.ttl=
usage.chunk=   usage.skip=1   tls.insecure=1
```

Boolean'lar `filter_var(..., FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)` ile ayrıştırılır;
`false`/`no`/`off` onurlandırılır ve ayrıştırılamayan bir boolean **sessiz truthy cast değil**,
kullanım anında `ConfigurationException`'dır. **Her yıkıcı anahtarın varsayılanı güvenli değerdir.**

### 9.2 Doğrulama nerede çalışır

`PreModuleCreate` sağlama anında tetiklenir, ürün kaydında değil — ve WHMCS'te ürün-kaydı modül
hook'u **yoktur**. Bu yüzden tasarım böyle bir hook iddia etmez. Bunun yerine:

- `AdvancedOptions::parse()` her `ContextFactory` kurulumunda doğrular.
- **TestConnection bir ürün lint'i çalıştırır:** sunucunun gruplarındaki her ürün için bilinmeyen
  anahtar, çözülen panele ait boş plan ordinal'i, Dedicated IP + reseller Plesk çakışması ve katalog
  farkı kontrol edilir.
- `hooks.php` yalnızca tavsiye niteliğindedir ve her gövdesi try/catch ile yutulur.

### 9.3 `_MetaData`

```php
DisplayName, APIVersion => '1.1', RequiresServer => true,
ServiceSingleSignOnLabel, AdminSingleSignOnLabel,
AutoGenerateUsernameAndPassword => true,
ListAccountsUniqueIdentifierField, ListAccountsProductField
```

**`DefaultSSLPort` ve `DefaultNonSSLPort` bilinçli olarak verilmez** (§3.3). Yan etki içermez:
DB yok, ağ yok. `MetaDataPurityTest` bunu, DB bağlantısı kapalı ve transport atacak şekilde
stub'lanmışken çağırıp yine de diziyi döndürdüğünü doğrulayarak zorlar.

---

## 10. Hata, log ve gizlilik

- **Redaksiyon serileştirme sınırındadır**, substring değiştirme ile değil. Sırlar `Wire\Secret`
  değer nesnesi olarak taşınır ve loglanabilir akışa **hiçbir kodlamada** girmez.
  `SecretVault` ikinci katman olarak kalır: yanıtta keşfedilen değerler, XML biçimini
  `PacketBuilder` ile **aynı** kaçış yardımcısından türetir, bir yanıttaki herhangi bir URL'in tüm
  query string'ini ekler ve kısa bir sırrı düşürmek yerine **gövdeyi tamamen bastırır**.
- **Panel hata metni asla doğrudan admin'e ulaşmaz.** `adminMessage`, eşlenen koda göre `lang/`'dan
  kurulur; ham `errtext` yalnızca (redakte edilmiş) events tablosuna gider. Bir lint kuralı,
  `errtext` türevli bir değişkenden istisna mesajı kurulmasını yasaklar. Trace'ler `getTrace()`'ten
  argümanlar düşürülerek kurulur, asla `getTraceAsString()` ile değil.
- `logActivity` dahil her dışa giden string `SecretVault::redact()`'ten geçer.
- Her admin mesajı `[RHP-xxxx/ref]` ile biter; `ref` `mod_rhpanel_events`'i indeksler ve o satır
  modül/WHMCS/PHP/ionCube sürümlerini taşır.

---

## 11. Paketleme, lisans, i18n

**Encode manifest** (`build/manifest.txt`, artefaktı açıp doğrulayan bir release testiyle zorlanır):

| Durum | İçerik |
|---|---|
| Encode edilir | `rhpanel.php`, `hooks.php`, `lib/**` |
| Düz metin | `lang/*.php`, `templates/*.tpl`, `whmcs.json`, `logo*.png`, `docs/**` |
| **Hiç bulunmaz** | `tests/**`, `build/**`, cassette'ler |

Dil ve şablon dosyalarının düz metin kalması bilinçlidir: müşteri dil ekleyebilsin ve arayüzü
değiştirebilsin, destek talebi açmak zorunda kalmasın.

PHP minor sürüm başına encode matrisi (7.2 / 7.4 / 8.0 / 8.1 / 8.2 / 8.3) ve hedef başına
**encode edilmiş** modülü yükleyip `rhpanel_MetaData()` ile `rhpanel_ConfigOptions()` çağıran bir
smoke testi. `IonCubeSafetyTest`, `eval` / `create_function` / `extract` / `$$var` / `new $var` /
değişken callable / WHMCS'e dönen dizide closure durumlarını kapsar.

**Lisans politikası** — arıza modelini şekillendirdiği için şimdi kararlaştırılmıştır:
`Support\Licence::assert()` Bootstrap'tan çağrılır. Grace penceresi içinde (varsayılan 14 gün)
**açık başarısız olur**, sonrasında kapalı. **Terminate, Suspend, UsageUpdate, TestConnection,
ClientArea ve SSO lisans durumundan bağımsız her zaman çalışır**; grace bittikten sonra yalnızca
**CreateAccount ve ChangePackage** engellenir. Bir lisans sunucusu kesintisi asla müşterinin
faturalandırma çalışmasını düşürmemelidir.

**Bootstrap:** `rhpanel.php` başında küçük bir class-map `require_once`, ardından
`Bootstrap::selfCheck()` her `lib/` alt ağacı için bir kanonik sembol test eder. Başarısızlıkta
**bozulmuş moda** girilir: `MetaData` ve `TestConnection` çalışmaya devam eder ve hangi dosyanın
decode edilemediğini bildirir; her yaşam döngüsü fonksiyonu temiz bir hata string'i döner —
asla "Call to undefined" fatal'ı değil.

---

## 12. Test stratejisi

| Katman | İçerik |
|---|---|
| 0 — Harness | `define('WHMCS', true)`, fonksiyon stub'ları, bellek içi SQLite Capsule. **İki stub varyantı** (7.8 şekilli ve 8.x şekilli); her Kernel/Compat yolu ikisine karşı da koşar. Sahte `$params['model']` yazılan her anahtarı kaydeder (özel alan tuzağı doğrudan iddia edilir) |
| 1 — Birim | `DialectResolver`; `ByteParser` (her gözlenen biçim × iki birim + `'0'`); `Quota` (negatif ve >2^50 reddi, 32-bit float yolu); `StatusNormaliser` 0/16/32/48/64 gidiş-dönüş; `SecretVault` (4 karakterlik sır → **gövde bastırma**); `ConfigOptionMap` birebir eşleme; `Selector::of('')` atar; `LoginDeriver` (IDN, rakamla başlama, çakışma, rezerve adlar) |
| 2 — Golden wire | `(operasyon × panel × dialect bandı)` başına kanonikleştirilmiş XML karşılaştırması + §7'nin dört guard'ı **bağımsız XPath testleri** olarak (toplu yenileme bunları kutsayamaz) |
| 3 — Matris | Her `(op, profil)` çifti için tam bir strateji; her `RemoteStep` hem aday listesinde hem profilde bulunan bir dialect'e çözülür; yinelenen adım id'si yok |
| 4 — Persona simülatörleri | Durumlu `PleskSim` / `WhmSim`. Personalar: `plesk-18.0.80-reseller` (**`<domain>`'i errcode 1014 ile reddeder**), `plesk-17.8`, `plesk-12.5`, `plesk-legacy-11.0`, `whm-11.116`, `whm-11.68`; düşmanca: `auth-fail`, `timeout`, `garbage-html` (HTTP 200 ile proxy giriş sayfası), `maintenance-page`, `truncated-xml`, `redirect-to-elsewhere`, `slow`, `success-then-garbage-response`, `mixed-chunk-with-1013s`, `5000-accounts`. **Persona listesi, kodla ifade edilmiş uyumluluk iddiasıdır** |
| 5 — Davranışsal | `customer.add` sonrası öldür → yeniden çalıştır → tam bir customer + bir webspace + `'success'`; `webspace.add` başarıdan sonra çöp döndürür → **sıfır silme**; silinmiş bağ satırıyla terminate → **sıfır paket**; iki webspace sahibi customer'a terminate → webspace silinir, customer **silinmez**; 16'da reseller unsuspend → **yazma yok**, `RHP-2204`; `'512'` disk limiti → 512 MB; eksik `resource-usage` → kolona dokunulmaz; 250 hizmet → tam 3 Plesk paketi; redirect personası → token o host'a giden **sıfır** istekte görünür; her düşmanca persona × her yaşam döngüsü fonksiyonu → **string** dönüş, notice yok, warning yok, fatal yok |
| 6 — WHMCS sözleşmesi | `rhpanel.php` üzerinde reflection: giriş kümesi === `Registry::$entries`; `'success'` için `===` ile karşılaştırma; `error`/`errorMsg` üzerinde `is_string`; başarısızlıkta bile `accounts` mevcut; `AutoPopulateServerConfig` dizi döndürür; üç sağlama-öncesi aşamanın her birinde zorlanan arıza yine doğru **şekli** döndürür; `AdminSingleSignOn` `username`/`domain` silinmiş hâlde çağrılır; `MetaDataPurityTest`; `AudienceLeakTest`; **her iki dispatcher'a karşı** iddialar |
| 7 — Lint / dondurma | PHP 7.2 `php -l` + PHPCompatibility `7.2-8.3`; `??=` / `match(` / `fn(` / tipli property / `?->` / `eval` yasağı; `AntiPatternTest`; `ConfigOptionMap` dışında `/configoption\d/` yok; ConfigOptions dönüşünde closure yok; `OrdinalLock`; `NoHardcodedStringsTest`; `LangParityTest` (placeholder kümeleri dahil); her beyaz listeli client-area fonksiyonunda `ClientGuard`; `lib/` içinde 700 satırdan uzun dosya ve 80 satırdan uzun fonksiyon yok; PHPStan level 6 |

**Gerçek zemin:** `RecordingTransport`, staging bir cPanel ve staging bir Plesk 18.0.8x'e karşı
`CurlTransport`'u sarar; `ReplayTransport` bu cassette'lerle Katman 5'i ağsız olarak CI'da tekrarlar.
Yeni bir panel sürümüne karşı cassette yenilemek, desteklenen sürüm başına dökümante edilmiş
release ritüelidir — **canlı panel gerektiren tek adım budur.**

---

## 13. Riskler

### 13.1 Canlı panel olmadan kapatılamayan riskler

Bunlar dürüstçe listelenmiştir; hiçbiri tasarımla çözülemez.

1. **Plesk `rsession_init.php` GET davranışı doğrulanmadı.** Yalnızca vendor dökümanından türetildi,
   canlı 18.0.8x'te hiç çalıştırılmadı. Azaltma: varsayılan `sso.plesk_tier=post` (her zaman doğru
   olan auto-POST). Kalan risk: POST formunun alan adları da yanlışsa v1'de Plesk client SSO hiç
   çalışmaz.
2. **Plesk şifre operatörü iddia edilmiştir, kanıtlanmamıştır.** `<customer><set>` +
   `<values><gen_info><passwd>` dökümana göre doğru biçimdir ama canlı panelde çalıştırılmadı.
   Bu, düzeltmek için var olduğumuz `<domain>` hatasıyla **aynı risk sınıfıdır**.
3. **Dialect aday tabloları** WHMCS'in şablon dizinlerinden ve vendor dökümanından türetildi.
   `1.6.9.1`'in en üst Plesk adayı olması bir iddiadır. Bir Plesk yapısı, `get_protos` ile bir sürüm
   advertise ederken operatörün **içindeki** düğüm şemasını değiştirirse yine başarısız olur — çok
   daha iyi bir hata mesajıyla ve tek satırlık veri düzeltmesiyle, ama başarısız olur. Sürüm kayması
   *yok edilmedi*, "yapısal olarak düzeltilemez"den "bir dizi literali"ne indirgendi.
4. **Simülatörler ve golden dosyalar bizim inançlarımızı kodlar, panellerin davranışını değil.**
   `PleskSim`'in `<domain>` için 1014 döndürmesi, fixture kılığında bir hipotezdir. Hiçbir golden
   test, kimsenin görmediği bir wire formatını yakalayamaz. Cassette katmanı tek gerçek zemindir ve
   CI'ın sahip olmayacağı staging panel erişimi gerektirir.
5. **PHP 7.2 değişmezliği zorlayamaz.** `RequestSpec`, `Dialect`, `PanelProfile` yalnızca konvansiyon
   ve testlerle değişmezdir. `RequestSpec::setDialect()` ekleyen bir bakımcı, tam olarak WHMCS'in
   modülünü öldüren ortam-durumu hata sınıfını geri getirir. Kısmen azaltıldı: golden guard
   "damgalanan = çözülen" iddiası bunu yakalar.
6. **Kimlik bilgilerinde çift `Sanitize::decode`.** `APIVersion => '1.1'` core'un tüm parametre
   dizisini decode etmesine yol açarken `getServerParams()` kimlik alanlarını zaten decode etmiştir.
   Birebir `&amp;` veya `&nbsp;` içeren saklı bir şifre panele `&` / boşluk olarak ulaşır. Bizim
   üreticimiz asla entity biçimli literal üretmez ve bir sözleşme testi durumu kapsar, ama müşterinin
   seçtiği bir şifre bunu hâlâ tetikleyebilir ve modül core'un davranışını düzeltemez.
7. **Katı `cookieipvalidation` altında cPanel SSO.** WHM oturumu WHMCS'in çıkış IP'sine karşı üretir,
   müşterinin tarayıcısı tüketir. Probe tespit edip uyarır ama sertleştirilmiş bir panelde client SSO
   çalışmaz ve çare müşterinin sunucusundadır.
8. **Karışık sunucu gruplarında bütünlük kısıtı admin'dir.** "Gold"un cPanel'de 10 GB, Plesk'te
   20 GB olmasını hiçbir şey engellemez. TestConnection katalog farkı bunu admin'in ziyaret ettiği
   tek ekranda yüzeye çıkarır, ama iki müşteri aynı fiyata farklı ürün alabilir.
9. **Fail-closed, doğruluğu erişilebilirliğe tercih eder ve destek talebi üretir.** Yeni eklenen bir
   sunucuda ilk probe başarısızlığı o sunucu için her şeyi bloklar. Bölünmüş TTL geçici durumu
   körelttir ama ilk kurulum durumu tasarım gereği sert bir duruştur.
10. **Ordinal 7'nin advanced alanı bir çekmeceye dönüşecek.** Append-only'yi yaşanabilir kılan basınç
    valfidir ve keşfedilebilir değildir; iki sürüm içinde UI'sı olmayan bir düzine anahtar tutacaktır.
11. **32-bit PHP ve çok büyük kotalar.** `ByteParser` float döndürür ve `Quota` sınırlar, ama çok-TB
    reseller planı sağlayan 32-bit bir host test edilmemiş alandır. `SUPPORTED-VERSIONS.md` 64-bit'i
    gereksinim olarak belirtir ve Bootstrap bunu net bir kodla iddia eder.
12. **WHMCS Eloquent model şekilleri public API değildir.** Tasarım bu yüzden Eloquent ilişkileri
    yerine açık join'lerle Capsule kullanır, ama `serviceProperties->save()` ve
    `App::getFromRequest()` hâlâ 7.8 ile 8.x arasında feature-detection tahminleridir.

### 13.2 Senin kararını bekleyen konular

Bunlar mühendislik değil ürün/iş kararlarıdır; kodlamadan önce netleşmeleri gerekir.

1. **Birinci gün doğrulama spike'ı:** Core autoloader `WHMCS\Module\Server\RhPanel\…`'i **hem** 7.8
   **hem** 8.x'te çözüyor mu, hangi yol eşlemesiyle? Tüm namespace düzeni buna bağlı. Kontrolü ucuz,
   sonradan değiştirmesi pahalı.
2. **WHMCS 7.8 `Loader` + `SimpleMode`'u destekliyor mu?** Canlı paket/plan dropdown'ı kilitli bir v1
   başlık özelliği. Desteklemiyorsa dökümante edilmiş bir bozulma ile çıkarız (metin alanı +
   `Description` notu + plan listesi TestConnection'da) ve bunu satış sayfasında söylemek gerekir —
   bu bir ürün kararıdır.
3. **Canlı panel kabul kapısı:** bir gerçek cPanel ve bir gerçek Plesk 18.0.8x (ideali + bir Plesk
   12.x + bir WHM 11.68). Beş madde başka türlü kapatılamaz: Plesk şifre operatörü; `rsession_init.php`
   davranışı ve POST fallback alan adları; reseller kapsamlı bir API anahtarının gerçekten
   `webspace.switch-subscription` yapabilmesi; `customer.set`in geç `<external-id>` yazımını kabul
   etmesi; `create_user_session`'ın katı `cookieipvalidation` altında ayakta kalması.
4. **Lisans arıza politikası.** Önerilen: 14 gün çevrimdışı grace, içinde açık-başarısızlık, sonrasında
   yalnızca CreateAccount ve ChangePackage engellenir. Pencere uzunluğu ve engellenen işlem kümesi
   ticari kararlardır.
5. **Rezerve ordinal planı.** 8–17 v2 reseller, 18–24 üçüncü panel. Üçüncü panel yol haritada
   **değilse** 18–24 de reseller'a verilebilir ve v2 yüzeyi rahatlar. 1.0.0 haritayı sonsuza kadar
   dondurmadan önce yol haritası cevabı gerekiyor.
6. **Türkçe çeviri sahipliği.** Lint kapıları her kullanıcıya görünen string'i `lang/`'a zorlar ve
   parite testi anahtar + placeholder kümelerinin aynılığını doğrular, ama ~40 hata mesajını birinin
   yazıp gözden geçirmesi gerekir. Makine çevirisi, teknik olarak pariteyi geçen ama operasyonel
   olarak işe yaramaz string'ler üretir.
7. **Staging-klon koruması UX'i.** SystemURL uyuşmazlığı onaya kadar tüm yıkıcı işlemleri reddeder.
   Onay nerede yaşıyor — sunucu rozetinde mi, bir addon sayfasında mı? Production kurulumunu meşru
   şekilde yeni bir alan adına taşıyan admin buna çarpacak ve kurtulma yolu destek talebi
   gerektirmemeli.
8. **`ListAccountsProductField` ve Server Sync.** Modül başına bir kez okunan statik bir MetaData
   değeri olduğundan, çift panelli bir üründe yalnızca ordinal 1'i (cPanel paketi) gösterebilir.
   `moduleConfigOption1` olarak ayarlandı ve Server Sync ürün eşleştirmesinin yalnızca cPanel
   sunucularında anlamlı olduğu dökümante edildi. Alternatif: alanı hiç vermeyip Server Sync'i
   desteklenmiyor ilan etmek.

---

## 14. Kapsam sınırı

**v1'de var:** create · suspend · unsuspend · terminate · changepassword · changepackage ·
testconnection · client SSO · admin SSO · UsageUpdate · zengin client area · canlı paket/plan
dropdown'ı · ListAccounts · GetUserCount · GetRemoteMetaData · AutoPopulateServerConfig

**v1'de yok:** yedekleme/geri yükleme · DNS zone yönetimi · SSL kurulumu · e-posta hesabı yönetimi ·
reseller ürün tipi · WHMCS ürün addon'ları · mevcut hizmetleri devralma · üçüncü panel

Ürün addon'ları için `addonId` sıfır değilse tasarım net bir `ConfigurationException` atar ve yarım
kalmış addon yollarını tamamen kaldırır — keşfedilerek değil, planlanarak eklenmesi gereken gerçek
bir kapsam eklentisidir.

---

## 15. Referanslar

Bu dokümanın dayandığı doğrulanmış malzeme `reference/` altındadır:

| Dosya | İçerik |
|---|---|
| `reference/01-architecture-full-en.md` | Tam mimari (İngilizce) — imzalar, adım tabloları, tüm detay |
| `reference/02-capability-matrix-en.md` | WHMCS aksiyonu → cPanel çağrısı vs Plesk çağrısı, uyuşmazlık bayraklarıyla |
| `reference/03-whmcs-module-contract-en.md` | WHMCS 7.8 modül sözleşmesi, dönüş değerleriyle |
| `reference/04-params-shape-en.md` | `$params` anahtar tablosu |
| `reference/05-mismatches-en.md` | 14 semantik uyuşmazlık, `file:line` atıflarıyla |
| `reference/06-resolved-blockers-en.md` | Jüri panelinin bulduğu 41 BLOCKER/HIGH ve çözümleri |
| `reference/07-residual-risks-en.md` | Kapatılamayan riskler (İngilizce tam hâli) |
| `reference/08-open-decisions-en.md` | İnsan kararı bekleyenler (İngilizce tam hâli) |

Kaynak referansı: `/Users/bunyaminakcay/Documents/Projeler/WHMCS-7.8.0-decoded`
