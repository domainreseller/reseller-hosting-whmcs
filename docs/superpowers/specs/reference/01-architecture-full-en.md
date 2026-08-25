> **rhpanel v1.0 — Final Architecture**
> Unified cPanel/WHM + Plesk server module for WHMCS 7.8 → 8.x, PHP 7.2+, ionCube-encodable, commercial.
> Base: *Declared Pipeline — Operations over Explicit Dialects*, trimmed (~60 source files, was ~133) and hardened against all BLOCKER/HIGH findings from three review lenses.

---

## 0. The one invariant everything else serves

**No behaviour may be ambient, inherited, or implicit.** Concretely:

- The wire dialect of every panel call is resolved by an explicit lookup in a declared array keyed on `(panel, operator, verb)`, then passed as a **required constructor argument** of `Wire\RequestSpec`. `Wire\Plesk\PleskPacketSerializer` reads `<packet version="…">` from `$spec->dialect()->wire()` and from nowhere else.
- `lib/` contains no `ReflectionMethod`, no `getDeclaringClass`, no `__call`, no `debug_backtrace`, no `eval`, no `create_function`, no closure inside any array returned to WHMCS. Enforced by `tests/Lint/AntiPatternTest.php`.
- Every destructive panel call is gated on a **positively-proven object identity** (a numeric id or GUID we read back in this same operation), never on a name filter, never on a heuristic, never on an empty filter.

---

## 1. File tree

```
modules/servers/rhpanel/
├── rhpanel.php                     ~24 rhpanel_* globals. Each 1–4 lines. No logic.
├── hooks.php                       Optional advisory hooks only (see §11.5). Every body try/catch-swallowed.
├── index.php                       redirect stub
├── whmcs.json                      {"schema":"1.0","type":"servers","name":"rhpanel","license":…,"version":"1.0.0"}
├── logo.png  logo_small.png
├── lang/english.php  lang/turkish.php
├── templates/
│   ├── overview.tpl                tabOverviewModuleOutputTemplate (see §12.4 — one slot only)
│   ├── usage.tpl                   included by overview.tpl
│   ├── sso_post.tpl                Plesk auto-POST fallback, rendered inside overview.tpl
│   ├── error.tpl                   client-safe degraded state
│   ├── admin/server_badge.tpl      rendered by _RenderRemoteMetaData (NOT _AdminLink — see §12.3)
│   └── admin/service_tab.tpl       _AdminServicesTabFields
├── docs/  INSTALL.md  UPGRADE-FROM-BUNDLED.md  CREDENTIALS.md  TROUBLESHOOTING.md
│         SUPPORTED-VERSIONS.md  ARCHITECTURE.md  CHANGELOG.md
├── build/ encode.sh  manifest.txt   (never shipped; see §15)
└── lib/                             namespace WHMCS\Module\Server\RhPanel
    ├── Kernel.php                  dispatch() / dispatchPure(); the only catch(\Throwable) boundary
    ├── Bootstrap.php               autoload fallback, schema-version check, licence grace check
    ├── Registry.php                THE declared tables: entries, reporters, operations, panel bindings
    ├── Context/
    │   ├── ContextInterface.php  ContextFactory.php
    │   ├── ServiceContext.php    ServerContext.php    ConfigContext.php
    │   ├── ServerCredentials.php ProductConfig.php    ClientIdentity.php
    ├── Panel/
    │   ├── PanelProfile.php  PanelResolver.php  PanelProbe.php  ProbeResult.php
    │   ├── Capabilities.php  ServerProfileRepository.php
    ├── Dialect/
    │   ├── Dialect.php  DialectKey.php  DialectResolver.php
    │   ├── PleskDialectTable.php  CpanelDialectTable.php  VersionCompare.php
    ├── Plan/
    │   ├── Plan.php  Bag.php  Executor.php  CompensatorStack.php  CommitCertainty.php
    │   ├── StepInterface.php  LocalStep.php  RemoteStep.php
    │   ├── SpecStep.php            generic single-call RemoteStep driven by a declared StepSpec
    │   └── StepSpec.php            {dialectKey, payloadBuilder, absorbKeys, compensatorSpec}
    ├── Strategy/
    │   ├── StrategyInterface.php  StrategyRegistry.php
    │   ├── Cpanel/CpanelStrategies.php     declared table + 2 real classes (Usage, Sso)
    │   └── Plesk/  PleskCreateStrategy.php  PleskUnsuspendStrategy.php
    │                PleskChangePasswordStrategy.php  PleskTerminateStrategy.php
    │                PleskUsageStrategy.php  PleskSsoStrategy.php  PleskDeclared.php
    ├── Wire/
    │   ├── RequestSpec.php  RawResponse.php  PanelResponse.php  Secret.php  Selector.php
    │   ├── Cpanel/CpanelRequestSerializer.php  CpanelResponseParser.php  CpanelErrorMap.php
    │   └── Plesk/ PacketBuilder.php  PleskPacketSerializer.php  PleskPacketParser.php
    │              OperatorMap.php  PleskErrorMap.php
    ├── Transport/
    │   ├── TransportInterface.php  CurlTransport.php  LoggingTransport.php
    │   ├── TlsPolicy.php  TransportOptions.php  SecretVault.php  ModuleLog.php
    ├── Dto/
    │   ├── Quota.php  UsageRecord.php  UsageBatch.php  AccountRef.php  AccountSpec.php
    │   ├── PackageRef.php  SuspensionState.php  SsoRequest.php  SsoTarget.php
    │   ├── AccountSummary.php  ConnectionReport.php  ServerMetaData.php  UserCount.php
    ├── Normalise/
    │   ├── ByteParser.php  Unit.php  CpanelUsageNormaliser.php  PleskUsageNormaliser.php
    │   └── StatusNormaliser.php  LoginDeriver.php
    ├── Persist/
    │   ├── Schema.php  Migrations.php  MetaRepository.php
    │   ├── ServiceWriter.php  AccountLinkRepository.php  CacheRepository.php  EventLog.php
    ├── Report/
    │   ├── ReporterInterface.php  StringReporter.php  ArrayReporter.php  SsoReporter.php
    │   ├── UsageReporter.php  ClientAreaReporter.php  HtmlReporter.php  ErrorCatalogue.php
    ├── Config/
    │   ├── ConfigOptionMap.php  OrdinalLock.php  AdvancedOptions.php  PackageSelector.php
    │   └── OptionLoader.php
    ├── Support/  Lang.php  Arr.php  RequestVar.php  InstallToken.php  Licence.php  Compat.php
    └── Exception/  RhPanelException.php  ConfigurationException.php  TransportException.php
                    ProtocolException.php  DialectUnavailableException.php  PanelErrorException.php
                    PartialFailureException.php  PanelTypeUnknownException.php
```

**Exception count is 8, not 12.** `ObjectNotFound / ObjectExists / PermissionDenied / QuotaExceeded` collapse into `PanelErrorException::kind()` returning a class constant (`KIND_NOT_FOUND`, `KIND_EXISTS`, `KIND_PERMISSION`, `KIND_QUOTA`, `KIND_OTHER`). Call sites branch on `kind()`, which is equally precise and equally testable, and removes a taxonomy decision from every future error-map addition.

---

## 2. Dispatch layer

### 2.1 Two paths, not one — *resolves contract BLOCKER "ConfigOptions has no Operation"*

`_ConfigOptions`, `_MetaData`, `_ClientAreaAllowedFunctions`, `_ClientAreaCustomButtonArray`, `_AdminCustomButtonArray` and `_LoginLink` are **pure / no-server** functions. They must never touch `Bootstrap::init()` (DB, licence) and must never reach `PanelResolver` (which fail-closes when `serverid` is absent — and `ConfigContext` has no `serverid` by definition).

```php
final class Kernel
{
    const AUDIENCE_ADMIN  = 'admin';
    const AUDIENCE_CLIENT = 'client';

    /** Panel-touching operations. */
    public static function dispatch($entryName, array $params, $audience = self::AUDIENCE_ADMIN)
    {
        // 1. Reporter is chosen from a STATIC TABLE by NAME, before anything can throw.
        $reporter = Registry::reporterFor($entryName);          // never null; defaults per table
        $ref      = Support\Arr::ref();                          // 8-hex correlation id
        try {
            Bootstrap::init();                                   // schema-version check + licence grace
            $op      = Registry::operation($entryName);
            $ctx     = Context\ContextFactory::build($op['context'], $params, $audience);
            $profile = Panel\PanelResolver::instance()->resolve($ctx);
            $bag     = Plan\Executor::instance()->run(
                           Strategy\StrategyRegistry::instance()->plan($entryName, $ctx, $profile),
                           $ctx, $profile
                       );
            $outcome = Plan\Outcome::success(Registry::finish($entryName, $bag, $ctx));
        } catch (\Throwable $t) {
            $outcome = Plan\Outcome::failure(Report\ErrorCatalogue::classify($t, $entryName, $ref));
        }
        // 2. Reporter render is ITSELF guarded — a Lang miss must not fatal the cron.
        try {
            return $reporter->render($outcome, $audience);
        } catch (\Throwable $t2) {
            return $reporter->lastResort($ref);                  // hardcoded, touches no DB/Lang
        }
    }

    /** Pure functions: no Bootstrap, no PanelResolver, no DB, no network. */
    public static function dispatchPure($entryName, array $params)
    {
        return Registry::pureHandler($entryName)->handle($params);
    }
}
```

`Kernel::dispatch` catches `\Throwable`, not `\Exception`, because `ModuleCallFunction` catches `\Exception` only — a PHP 7 `TypeError` from a malformed panel response would otherwise escape and fatal the whole request. `WHMCS\Service::moduleCall()` (the client-area path) wraps **nothing**, so the guard is mandatory there too.

### 2.2 The entry table — *resolves contract HIGH "half the entry points have no reporter"*

`Registry::$entries` is the single source of truth. Every `rhpanel_*` global in `rhpanel.php` MUST appear in it, and `tests/Contract/EntrySurfaceTest.php` asserts the two sets are identical by reflection.

| `rhpanel_*` | Path | Context | Reporter | Audience |
|---|---|---|---|---|
| `MetaData` | pure | — | — | — |
| `ConfigOptions` | pure | Config | — | admin |
| `ClientAreaAllowedFunctions` | pure | — | — | client |
| `ClientAreaCustomButtonArray` | pure | — | — | client |
| `AdminCustomButtonArray` | pure | — | — | admin |
| `LoginLink` | pure | — | — | admin |
| `CreateAccount` `SuspendAccount` `UnsuspendAccount` `TerminateAccount` `ChangePassword` `ChangePackage` | dispatch | Service | `StringReporter` | admin |
| `TestConnection` | dispatch | Server | `ArrayReporter` | admin |
| `ListAccounts` | dispatch | Server | `ArrayReporter` (fail shape `['success'=>false,'accounts'=>[],'error'=>str]`) | admin |
| `GetUserCount` `GetRemoteMetaData` | dispatch | Server | `ArrayReporter` | admin |
| `AutoPopulateServerConfig` | dispatch | Server | `ArrayReporter` (fail shape below) | admin |
| `RenderRemoteMetaData` | dispatch | Server | `HtmlReporter` | admin |
| `AdminServicesTabFields` | dispatch | Service | `HtmlReporter` | admin |
| `ServiceSingleSignOn` | dispatch | Service | `SsoReporter` | client |
| `AdminSingleSignOn` | dispatch | **Server** | `SsoReporter` | admin |
| `UsageUpdate` | dispatch | **Server** | `UsageReporter` | admin |
| `ClientArea` | dispatch | Service | `ClientAreaReporter` | client |
| `ClientAreaResync` (custom fn) | dispatch | Service | `ClientAreaReporter` (json) | client |

### 2.3 Reporters

- **`StringReporter`** — the literal `'success'` appears in **exactly one place in the codebase**: `StringReporter::render()`. It is structurally incapable of returning `true`, `1`, `'Success'`, `''` or `null`, all of which core treats as failure (`$result == "success"`). Failure → `$e->message($audience) . ' [' . $code . '/' . $ref . ']'`.
- **`ArrayReporter`** — `['success'=>true] + payload` or `['success'=>false,'error'=>(string)$msg]`, with `error` guaranteed `is_string`. Fail shapes are `Kernel::FAIL_*` class constants so no caller writes an array literal.
- **`SsoReporter`** — `['success'=>true,'redirectTo'=>$url]` / `['success'=>false,'errorMsg'=>$msg]`; `errorMsg` is always populated, because the consumer throws a bare "Unable to auto-login." when it is absent.
- **`UsageReporter`** — *resolves contract MEDIUM "VoidReporter always success"*. Returns the literal `'success'` on success and the **admin error string** on failure, because `ServerUsageUpdate()` logs any non-empty, non-`'success'` return as `Server Usage Update Failed: … - Server ID: N`. That is the only failure channel core offers. It additionally writes `last_usage_error` / `last_usage_success_at` to `mod_rhpanel_servers` and an `mod_rhpanel_events` row.
- **`ClientAreaReporter`** — *resolves contract MEDIUM "ClientArea has no reporter"*. ALWAYS returns `['tabOverviewModuleOutputTemplate' => 'templates/overview.tpl', 'templateVariables' => [...]]`. On failure it swaps in `error.tpl` variables. It never returns `'success'` and never returns a bare array without a template key (which would fall through to a non-existent `clientarea.tpl`).
- **`HtmlReporter`** — returns a string of HTML; on failure returns a small styled warning block, never an exception.

### 2.4 Audience — *resolves contract HIGH "adminMessage leaks to customers"*

`ClassifiedError` carries `adminMessage()` (server id + name, panel product version, plan name, verbatim-but-redacted panel text, RHP code) and `clientMessage()` (generic, no hostname, no version, no plan name, no code). Audience is an explicit `dispatch()` argument, never inferred. `tests/Contract/AudienceLeakTest.php` invokes every `AUDIENCE_CLIENT` entry against every failure persona and asserts the returned string contains none of: `serverhostname`, `serverip`, panel product version, plan name, `RHP-`.

---

## 3. Context layer

```php
interface ContextInterface {
    public function serverId();                 // int, >0 guaranteed or ctor threw
    public function credentials();              // ServerCredentials
    public function audience();                 // 'admin'|'client'
    public function secrets();                  // Wire\Secret[]  — registered at build time
}

final class ContextFactory {
    const KIND_SERVICE = 'service';
    const KIND_SERVER  = 'server';
    const KIND_CONFIG  = 'config';
    /** @throws ConfigurationException|PanelTypeUnknownException */
    public static function build($kind, array $params, $audience);
}
```

**`ServerContext` has no `username()`, `domain()`, `serviceId()` or `productConfig()` method AND overrides `raw($key)` to throw `ConfigurationException` for any key matching `/^(serviceid|username|domain|password|configoption\d+|clientsdetails)$/`.** Both routes fail — the missing-method route is a `\Error` mapped by `ErrorCatalogue` to a *distinct* code `RHP-9001` whose message names the context kind and the method (so it is self-describing even ionCube-encoded), and the array-key route is a clean `ConfigurationException`. This is a runtime guard plus a test, not a "compile-time" guarantee; the docs say so.

`ServiceContext` exposes `productConfig()` returning `Config\ProductConfig`, which is the **only** object in the codebase that touches `configoptionN` (lint-enforced).

Addons: **out of v1 scope, explicitly.** `ContextFactory::build()` throws `ConfigurationException('rhpanel does not support product addons in v1')` when `$params['addonId']` is non-zero. No `addonid` column, no addon branch in the usage enumerator. Deleting the half-built path beats shipping it.

`ClientIdentity` truncates to panel-safe lengths and normalises non-ASCII before it reaches any serialiser.

---

## 4. Panel resolution, probing, and DDL

### 4.1 Tables

```sql
CREATE TABLE IF NOT EXISTS `mod_rhpanel_meta` (
  `k` VARCHAR(64) NOT NULL,
  `v` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- rows: schema_version, install_token, install_url, licence_grace_until, module_version

CREATE TABLE IF NOT EXISTS `mod_rhpanel_servers` (
  `serverid`             INT UNSIGNED NOT NULL,
  `panel_type`           VARCHAR(16)  NOT NULL DEFAULT 'unknown', -- cpanel|plesk|unknown|conflict
  `panel_type_source`    VARCHAR(16)  NOT NULL DEFAULT 'probe',   -- probe|manual
  `product_version`      VARCHAR(32)  NOT NULL DEFAULT '',
  `wire_protos`          TEXT             NULL,                    -- JSON ["1.6.9.1", ...]
  `identity_kind`        VARCHAR(16)  NOT NULL DEFAULT 'unknown',  -- admin|reseller|customer|unknown
  `identity_login`       VARCHAR(191) NOT NULL DEFAULT '',
  `capabilities`         TEXT             NULL,                    -- JSON
  `resolved_port`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,     -- the port the probe SUCCEEDED on
  `tls_mode`             VARCHAR(16)  NOT NULL DEFAULT 'unknown',  -- verified|pinned|tofu|insecure
  `tls_cert_sha256`      CHAR(64)     NOT NULL DEFAULT '',
  `cred_fingerprint`     CHAR(40)     NOT NULL DEFAULT '',
  `probe_ok_at`          DATETIME         NULL,
  `probe_last_error`     TEXT             NULL,
  `probe_fail_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `warnings`             TEXT             NULL,                    -- JSON []
  `last_usage_success_at` DATETIME        NULL,
  `last_usage_error`     TEXT             NULL,
  PRIMARY KEY (`serverid`),
  KEY `panel_type` (`panel_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_rhpanel_accounts` (
  `serviceid`      INT UNSIGNED NOT NULL,
  `serverid`       INT UNSIGNED NOT NULL,
  `panel_type`     VARCHAR(16)  NOT NULL,          -- panel this service was PROVISIONED on
  `external_id`    VARCHAR(191) NOT NULL DEFAULT '',
  `customer_id`    VARCHAR(64)  NOT NULL DEFAULT '',
  `customer_guid`  VARCHAR(64)  NOT NULL DEFAULT '',
  `webspace_id`    VARCHAR(64)  NOT NULL DEFAULT '',
  `webspace_guid`  VARCHAR(64)  NOT NULL DEFAULT '',
  `panel_login`    VARCHAR(191) NOT NULL DEFAULT '',   -- authoritative; mirrors tblhosting.username
  `webspace_name`  VARCHAR(191) NOT NULL DEFAULT '',
  `plan_ref`       VARCHAR(191) NOT NULL DEFAULT '',   -- native ref actually used
  `our_status_bit` SMALLINT     NOT NULL DEFAULT 0,    -- the bit WE set at suspend time
  `last_status`    INT              NULL,              -- last observed raw status
  `owner_shared`   TINYINT(1)   NOT NULL DEFAULT 0,    -- migrated bundled-module shared customer
  `pending_sync`   VARCHAR(64)  NOT NULL DEFAULT '',   -- csv: password,plan,verify
  `orphan_since`   DATETIME         NULL,
  `orphan_misses`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`     DATETIME         NULL,
  PRIMARY KEY (`serviceid`),
  UNIQUE KEY `external_id` (`external_id`),
  KEY `serverid` (`serverid`),
  KEY `panel_login` (`panel_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_rhpanel_cache` (
  `serverid`   INT UNSIGNED NOT NULL,
  `cache_key`  VARCHAR(128) NOT NULL,
  `payload`    MEDIUMTEXT       NULL,
  `expires_at` DATETIME     NOT NULL,          -- NOT NULL: never ambiguous
  PRIMARY KEY (`serverid`,`cache_key`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_rhpanel_events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref`        CHAR(8)      NOT NULL,
  `serverid`   INT UNSIGNED NOT NULL DEFAULT 0,
  `serviceid`  INT UNSIGNED NOT NULL DEFAULT 0,
  `severity`   VARCHAR(8)   NOT NULL DEFAULT 'error',  -- error|repair|warn
  `code`       VARCHAR(16)  NOT NULL DEFAULT '',
  `step_id`    VARCHAR(64)  NOT NULL DEFAULT '',
  `dialect`    VARCHAR(32)  NOT NULL DEFAULT '',
  `detail`     TEXT             NULL,                  -- ALWAYS redacted
  `created_at` DATETIME     NOT NULL,
  `resolved_at` DATETIME        NULL,
  PRIMARY KEY (`id`),
  KEY `ref` (`ref`), KEY `serverid` (`serverid`), KEY `serviceid` (`serviceid`),
  KEY `sev_time` (`severity`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

`mod_rhpanel_events` is the answer to *maintainability HIGH "debugContext only reaches logModuleCall, which is a no-op with ModuleDebugMode off"*. It is written unconditionally, capped by a rolling delete to the newest 2,000 rows per install, and rendered on the server badge and the service admin tab. Every admin error string ends with `[RHP-xxxx/ref]` and the ref indexes this table.

### 4.2 Schema ownership and migrations — *resolves maintainability BLOCKER "two DDL owners, no migration runner" and failure MEDIUM "DDL on the hot path"*

- `Persist\Migrations::$list` is an ordered array `int version => 'methodName'`. `Persist\Schema::ensure()` reads `mod_rhpanel_meta.schema_version`, takes a **non-blocking** `GET_LOCK('rhpanel.schema', 0)`, runs pending migrations in order, stamps the new version, releases.
- `Schema::ensure()` runs from **exactly two places**: `rhpanel_TestConnection` and an explicit "Run upgrade" admin action reachable from the server badge. **Never from a provisioning path.**
- `Bootstrap::init()` on the hot path does one memoised `SELECT v FROM mod_rhpanel_meta WHERE k='schema_version'`. If the row is missing or behind, it throws `ConfigurationException(RHP-1002)`: *"rhpanel's database tables are missing or out of date. Open Setup → Products/Services → Servers → <name> → Test Connection once to initialise."* If the SELECT fails because the table does not exist, same message.
- If DDL fails for permissions, the message names the exact statement and says the DB user needs CREATE/ALTER; the statements are also printed in `INSTALL.md`.

### 4.3 `PanelResolver::resolve(ContextInterface $ctx)`

```php
final class PanelResolver {
    public static function instance();
    /** @throws PanelTypeUnknownException */
    public function resolve(Context\ContextInterface $ctx, $force = false);   // Panel\PanelProfile
}
```

1. `$sid = $ctx->serverId()`. `ContextFactory` already threw if `serverid` was absent or `<= 0` — the no-server branch of `getServerParams()` omits the key entirely, so `isset` is mandatory. **Fail-closed entry gate.**
2. Per-request memo **keyed on `$sid`** (the usage cron walks every server in one process; an unkeyed static is a cross-server data leak).
3. Load the row. Compute `$fp = sha1(ip|hostname|port|secure|username|sha1(token))`.
4. **Precedence, stated explicitly** (*resolves failure HIGH "fingerprint vs manual override contradiction"*):
   - `panel_type_source === 'manual'` and `panel_type` in {cpanel,plesk} → **return it, always.** A fingerprint mismatch on a manual row sets a `fingerprint_stale` warning surfaced on the badge; it never changes `panel_type`.
   - Otherwise a fingerprint mismatch invalidates the cached row entirely.
5. `panel_type === 'conflict'` → throw `PanelTypeUnknownException` with the conflict message. Requires an explicit admin acknowledgement action (which also offers to clear the now-garbage link rows for that server).
6. `panel_type` in {cpanel,plesk} and fingerprint matches → return. **`panel_type` and `identity_kind` never expire.** `product_version` / `wire_protos` / `capabilities` carry a 24h freshness flag that triggers a refresh only in *interactive* contexts (TestConnection, RenderRemoteMetaData, ConfigOptions Loader) — never on the provisioning or cron hot path.
7. Miss → probe, under a **non-blocking `GET_LOCK('rhpanel.probe.'.$sid, 0)`** (*resolves failure HIGH "probe_status='pending' thundering herd"*). If the lock is not acquired, serve the existing row if any, else throw. **`probe_status` is never written at probe start.** A failed probe writes only `probe_last_error`, `probe_fail_count`, and a timestamp — **it never mutates `panel_type` or `identity_kind`** (*resolves failure MEDIUM "unspecified whether a failed probe overwrites panel_type"*). A *successful* probe returning a panel type that differs from a stored non-empty one writes `panel_type='conflict'` and refuses everything until acknowledged.

### 4.4 `PanelProbe` — authenticated positives only

*Resolves failure HIGH "positive identification from an unauthenticated signal, cached forever".*

- **Port orders the probe; it never decides.** `resolved_port` is what the probe *succeeded* on, and `Transport` uses that in preference to `serverport` (*resolves failure HIGH "endpoint trusts serverport while probe whitelists it"*).
- Candidate ports are tried per panel regardless of the configured value: WHM {`serverport` if in 2087/2086, then 2087, 2086}, Plesk {`serverport` if in 8443/8880, then 8443, 8880}. If the panel answers on a port other than the configured one, `probe_last_error` says so explicitly: *"Plesk responded on 8443 but this server's Port field says 2087 — correct the Port field."* The row is **not** silently rewritten.
- **cPanel confirmed iff** `/json-api/version?api.version=1` returns HTTP 200 with a decodable JSON body carrying a plausible `data.version`/`version` **AND** `/json-api/myprivs?api.version=1` returns a privilege map with expected keys. A proxy, WAF or captive portal cannot satisfy both. `myprivs` also populates `identity_kind` and the missing-ACL list against the required set (`create-acct, suspend-acct, kill-acct, passwd, upgrade-account, list-accts, list-pkgs, create-user-session, show-bandwidth`).
- **Plesk confirmed iff** `POST /enterprise/control/agent.php` with `KEY: <token>` and `<packet version="1.6.3.0"><server><get_protos/></server></packet>` parses and yields ≥1 `//protos/proto`. On errcode 1001 retry **once** with `HTTP_AUTH_LOGIN`/`HTTP_AUTH_PASSWD` and record `capabilities.authMode='legacy'`. Identity via a scoped `<customer><get>` / `<server><get><stat/>` permission probe — **never** `'admin' === $login`.
- Probe outcomes are three-state: `confirmed` / `auth-failed` / `unreachable`, with distinct exception types and distinct admin messages. "Wrong token" never surfaces as "unknown panel type".
- Timeouts: connect 5s, total 20s per probe.
- `get_protos` result is intersected with the versions our tables actually build for, and the survivor set is stored in `wire_protos`.
- **Dialect staleness** (*resolves failure HIGH "cached dialect keeps emitting `<domain>` after a Plesk upgrade"*): `wire_protos` is invalidated by (a) `product_version` differing from the last probe — the cheap `<server><get><gen_info/>` version read runs on every interactive context; (b) any `ProtocolException` carrying Plesk errcode **1014**, which forces immediate renegotiation and exactly one retry at the new dialect; (c) a hard 7-day TTL independent of the panel-type TTL.

### 4.5 Fail-closed rendering per entry point

| Entry | On `PanelTypeUnknownException` |
|---|---|
| Lifecycle | `"rhpanel: cannot determine the control panel type for server #14 (srv-de-01). WHM 2087: HTTP 401 Access denied. Plesk 8443: cURL 7 Connection refused. Run Test Connection on this server. [RHP-1001/a4f19c]"` — no panel call attempted. |
| `TestConnection` | `['error' => <same>]` + full transcripts in the events table. |
| `ListAccounts` | `['success'=>false,'accounts'=>[],'error'=>msg]` |
| `GetUserCount` / `GetRemoteMetaData` / `AutoPopulateServerConfig` | `['success'=>false,'error'=>msg]` (+ `assignedIps'=>[]` for the last — see §12.5) |
| SSO | `['success'=>false,'errorMsg'=>msg]` |
| `UsageUpdate` | error string (core logs it) + events row + `last_usage_error` |
| `ClientArea` | `error.tpl` with `clientMessage()` |
| `ConfigOptions` Loader | throws `InvalidConfiguration` (see §11.4) |

### 4.6 TLS — *resolves failure HIGH "insecure is the modal configuration"*

`Transport\TlsPolicy` reads native `tblservers` fields only (no invented UI):

- `serverip` **and** `serverhostname` set → URL host is the **hostname**, `CURLOPT_RESOLVE = ["{host}:{port}:{ip}"]`, `VERIFYPEER=1`, `VERIFYHOST=2` → `tls_mode='pinned'`. **If an outbound proxy is configured, `CURLOPT_RESOLVE` is ignored by curl** — so `TlsPolicy` detects a configured proxy, skips the RESOLVE trick, connects to the hostname directly, and records a warning. We never claim a verification posture we do not have.
- hostname only → plain verified TLS → `tls_mode='verified'`.
- IP only → **TOFU pinning**: on the first successful probe record `CURLINFO_CERTINFO`'s leaf SHA-256 into `tls_cert_sha256`; thereafter verify the presented cert against the pin with CA verification off, and **fail closed on mismatch** with *"the server's TLS certificate changed"*. `tls_mode='tofu'`.
- Verification fully off requires the literal advanced key `tls.insecure=1`, and TestConnection reports it as a warning on every run. `tls_mode='insecure'`.
- **`CURLOPT_FOLLOWLOCATION = false` on every API call** (*resolves failure HIGH "redirect leaks the token"*). Any 3xx is a `ProtocolException` naming the `Location` header — which is also the correct diagnostic, since a 3xx converts POST→GET and drops the packet body, producing agent.php's HTML login page with HTTP 200.

---

## 5. Dialect layer — the WHMCS-27532 fix

```php
final class DialectKey {
    public function __construct($panel, $operator, $verb);
    public function panel(); public function operator(); public function verb();
    public function id();          // 'plesk.webspace.set'
}
final class Dialect {
    const PANEL_CPANEL = 'cpanel';
    const PANEL_PLESK  = 'plesk';
    const PANEL_UNKNOWN = 'unknown';
    public function __construct($panel, $wire, $band);
    public function panel(); public function wire(); public function band();
    public function __toString();  // 'plesk:1.6.7.0'
}
final class DialectResolver {
    /** @throws DialectUnavailableException */
    public function resolve(DialectKey $key, Panel\PanelProfile $profile);   // Dialect
}
```

`PleskDialectTable::$candidates` — declared data, most-preferred first:

```php
'plesk.server.get_protos'            => array('1.6.9.1','1.6.3.0','1.0.0.0'),
'plesk.server.get'                   => array('1.6.9.1','1.6.3.0'),
'plesk.server.create_session'        => array('1.6.9.1','1.6.3.5'),
'plesk.customer.add'                 => array('1.6.9.1','1.6.6.0','1.6.3.0'),
'plesk.customer.get'                 => array('1.6.9.1','1.6.7.0','1.6.3.5','1.6.3.0'),
'plesk.customer.set'                 => array('1.6.9.1','1.6.3.0'),
'plesk.customer.del'                 => array('1.6.9.1','1.6.3.0'),
'plesk.webspace.add'                 => array('1.6.9.1','1.6.7.0','1.6.3.2'),   // 1.0.0.0 ABSENT
'plesk.webspace.set'                 => array('1.6.9.1','1.6.7.0','1.6.3.2'),   // 1.0.0.0 ABSENT
'plesk.webspace.get'                 => array('1.6.9.1','1.6.4.0','1.6.3.2'),
'plesk.webspace.del'                 => array('1.6.9.1','1.6.3.0'),
'plesk.webspace.switch-subscription' => array('1.6.9.1','1.6.3.0'),
'plesk.service-plan.get'             => array('1.6.9.1','1.6.3.0'),
'plesk.domain.set'                   => array('1.0.0.0'),   // legacy island, different KEY
'plesk.domain.get'                   => array('1.0.0.0'),
```

Reaching pre-11.5 Plesk is **not a fallback** — it is a *different `DialectKey`*, emitted only by `PleskLegacyDomainStrategy`, registered only for `productVersion < 11.5`, with its own golden files. A maintainer must consciously choose it; no operation can fall into it.

`CpanelDialectTable` selects `api.version=1` vs the legacy unversioned form per endpoint, and the `whmToken` vs `whmHash` auth band, so a 2014 WHM and a 2026 WHM travel explicitly different, explicitly tested paths.

**Golden guards (all four run over the whole corpus):**
1. No Plesk golden emitted by any strategy other than `PleskLegacyDomainStrategy` contains the token `<domain>`.
2. Every Plesk golden's `<packet version="X">` equals the wire version `DialectResolver` returned for that step's key.
3. No `webspace`-operator candidate list contains `1.0.0.0`.
4. **No golden for a `set`, `del` or filtered `get` verb contains an empty `<filter>` or `<filter/>`.** (See §7.1.)

Comparison is **canonicalised XML** (DOMDocument, normalised whitespace, sorted attributes), not raw bytes, plus independent XPath structural assertions ("`webspace.set.status` at negotiated 1.6.9.1 has a `<webspace>` root operator and never `<domain>`") that a bulk `UPDATE_GOLDENS=1` regeneration cannot bless away.

---

## 6. Plan / Step / Executor

Two step kinds only.

```php
interface StepInterface { public function id(); public function shouldRun(Plan\Bag $bag); }

interface LocalStep extends StepInterface { public function apply(Plan\Bag $bag); }

interface RemoteStep extends StepInterface {
    public function dialectKey();                                   // Dialect\DialectKey
    public function request(Plan\Bag $bag, Dialect\Dialect $d);      // Wire\RequestSpec
    public function absorb(Plan\Bag $bag, Wire\PanelResponse $r);
    public function compensator(Plan\Bag $bag);                      // RemoteStep|null
}
```

**`Plan\SpecStep` is a generic `RemoteStep` driven by a declared `StepSpec`** — this is the class-count trim. A `StepSpec` is `{dialectKey, payloadBuilderCallableName, absorbMap, compensatorSpec, shouldRunFlag}`. Single-call operations (all of cPanel's lifecycle, Plesk's `service-plan.get`, `webspace.del`) are one table row, not a class. Real classes exist only for the six genuinely multi-step / conditional Plesk plans and the two usage strategies.

### 6.1 The executor, with commit-certainty compensation

*Resolves failure BLOCKER "compensator fires on parse failures and deletes provisioned accounts", and the mirror bug where the failing step's own compensator is never pushed.*

```php
final class CommitCertainty {
    const NOT_COMMITTED = 1;   // request never left / connect refused / DNS fail / explicit panel rejection
    const UNKNOWN       = 2;   // timeout, non-2xx, unparseable body — on a MUTATING verb
    const COMMITTED     = 3;   // parsed OK
}
```

```php
foreach ($plan->steps() as $step) {
    if (!$step->shouldRun($bag)) { continue; }
    if ($step instanceof LocalStep) { $step->apply($bag); continue; }

    $dialect = $this->dialects->resolve($step->dialectKey(), $profile);
    $spec    = $step->request($bag, $dialect);
    try {
        $raw = $this->transport->send($spec, $profile);
    } catch (TransportException $e) {
        $bag->markCertainty($step->id(), $e->isConnectFailure()
            ? CommitCertainty::NOT_COMMITTED : CommitCertainty::UNKNOWN);
        throw $e;
    }
    // Compensator is pushed as soon as ANY response came back — before parsing.
    $stack->push($step->compensator($bag));
    try {
        $parsed = $spec->parserFor()->parse($raw, $spec);
    } catch (\Throwable $t) {
        $bag->markCertainty($step->id(), $spec->isMutating()
            ? CommitCertainty::UNKNOWN : CommitCertainty::NOT_COMMITTED);
        throw $t;
    }
    $bag->markCertainty($step->id(), $parsed->isPanelError()
        ? CommitCertainty::NOT_COMMITTED : CommitCertainty::COMMITTED);
    $step->absorb($bag, $parsed);
}
```

`CompensatorStack::unwind()` **runs a compensator only when the failing step's certainty is `NOT_COMMITTED`.** On `UNKNOWN` it compensates nothing, writes `pending_sync` += `verify` on the link row, writes a `repair` event naming the exact object to inspect, and the operation returns a `PartialFailureException` message telling the admin what to check by hand. A parse-format change in Plesk 18.1 therefore leaves the working account alone.

Compensator failures are logged as `repair` events and never rethrown.

---

## 7. Per-mismatch strategy detail

### 7.1 Selectors — the mass-delete guard

*Resolves failure BLOCKER "empty `<filter/>` means ALL objects".*

```php
final class Selector {
    const BY_ID = 'id'; const BY_GUID = 'guid'; const BY_NAME = 'name'; const BY_EXTERNAL_ID = 'external-id';
    /** @throws ConfigurationException when $value is null|''|0|'0' */
    public static function of($kind, $value);
    public function kind(); public function value();
}
```

- `Selector::of()` **throws** on an empty value. A `RequestSpec` for a `set`/`del`/filtered-`get` verb requires `Selector[]` with `count >= 1`; the constructor throws otherwise.
- `PleskPacketSerializer` throws `ProtocolException` if any filter node would render with an empty value or if a filter block would render childless.
- **Destructive Plesk verbs (`webspace.del`, `customer.del`, `customer.set_password`, `webspace.set`) accept `BY_ID`/`BY_GUID` only.** `BY_NAME` is rejected at construction. Names are read-only lookup keys, never mutation targets. (*Also resolves failure HIGH "`webspace.set` by name silently no-ops after a domain rename, reporting BOTH passwords applied".*)
- Behaviour test: run Terminate with a wiped link row and assert **zero** packets were sent and a `ConfigurationException` was returned.

### 7.2 Mismatch 1 — Plesk customer + webspace pair (locked #8)

One WHMCS service = one Plesk customer + one webspace. `external_id = 'rhp-' . InstallToken::get() . '-' . $serviceId`.

**`Support\InstallToken`** (*resolves failure HIGH "installToken has no defined storage or clone detection"*): generated once into `mod_rhpanel_meta.install_token` as `substr(sha1(random_bytes(32)), 0, 12)`. `mod_rhpanel_meta.install_url` stores the SystemURL at generation time. On every `Bootstrap::init()`, if the live SystemURL differs from the stored one, **all destructive operations (Terminate, ChangePackage) refuse** with *"this WHMCS installation appears to be a clone — confirm in the rhpanel server badge before rhpanel will delete panel objects."* Additionally, every probe samples external-ids on the Plesk server; any carrying a **different** `rhp-` token makes TestConnection report *"another WHMCS install manages customers on this server"*. This single guard prevents the staging-clone-deletes-production catastrophe.

**Create plan (`PleskCreateStrategy`)** — resumable, not merely retry-safe (*resolves failure BLOCKER "create is not idempotent but the module queue retries it"*):

| # | Kind | Step | shouldRun |
|---|---|---|---|
| 1 | Local | `login.derive` — see §7.9 | always |
| 2 | Local | `link.upsert` — INSERT…ON DUPLICATE KEY UPDATE preserving already-absorbed ids | always |
| 3 | Local | `plan.resolve` — see §7.5 | always |
| 4 | Remote | `plesk.customer.get` by `external-id` → absorb `customer_id`, `customer_guid` | always |
| 5 | Remote | `plesk.customer.add` (`<external-id>`, gen_info, passwd) | `!$bag->has('customer_id')` |
| 6 | Remote | `plesk.webspace.get` by `name` → absorb `webspace_id`, `webspace_guid` | always |
| 7 | Remote | `plesk.webspace.add` (`gen_setup{name, owner-id, htype=vrt_hst, ip_address}`, `hosting/vrt_hst{ftp_login, ftp_password}`, `plan-name`) | `!$bag->has('webspace_id')` |
| 8 | Local | `link.commit` + `ServiceWriter` username/password write-back | always |

Step 5's compensator is `plesk.customer.del` by **`customer_id`** — and it only fires when step 7's certainty is `NOT_COMMITTED`. A `webspace.add` returning "object already exists" is treated as *already provisioned*: fetch it, verify `owner-id` matches our customer, continue.

Migrated (bundled-module) services: on external-id miss, `webspace.get` by domain → `owner-id` → `customer.get` by id. Adoption **writes the external-id and nothing else**, sets `owner_shared=1` when the owner holds more than one webspace, and never authorises a destructive operation in the same call.

### 7.3 Mismatch 2 — Suspend: bitmask vs flag + reason

`Dto\SuspensionState{suspended, byAdmin, byReseller, raw}`; `Normalise\StatusNormaliser` is the only code that knows 0/16/32/48/64 or cPanel's boolean.

- **cPanel:** `suspendacct?user&reason` / `unsuspendacct`, then `accountsummary?user=` read-back asserting `suspended` matches intent. A `result:1` that did not actually change the state is a failure, not a success.
- **Plesk suspend:** read `webspace.get gen_info` → `$new = $cur | $ourBit`, where `$ourBit` = 32 for a reseller identity, 16 for admin, taken from the probe (never from a login string compare). **Both** the customer object and the webspace are set, matching cPanel semantics where suspension also blocks panel login. `$ourBit` is persisted to `mod_rhpanel_accounts.our_status_bit`.
- **Plesk unsuspend** (*resolves failure HIGH "clears the bit implied by the CURRENT identity"*): the bit cleared is `our_status_bit` from the link row, falling back to the identity-derived bit only when the column is 0. Compute the residual **before writing**: if it contains bits outside our authority, **do not write at all** — read-only, return `RHP-2204`: *"Cleared nothing: this subscription is also suspended by the Plesk administrator (status bit 16). Reseller credentials cannot clear an administrator suspension."* If the write is legitimate, re-read `gen_info` afterwards and assert the observed status equals the intent; a mismatch is a hard failure, never `'success'`. Both objects are verified.
- `suspendreason`: Plesk has no field for it. Default `suspendreason.mode=log` → `logActivity` + `mod_rhpanel_accounts` + module log. Opt-in `=description` writes `gen_info/description` (customer-visible, off by default). Never silently dropped — the docs say so and TestConnection says so.

### 7.4 Mismatch 3 — Two passwords on Plesk

*Resolves failure BLOCKER "`customer.set_password` is not a real operator" and TDC's "compensation creates a third unknown password".*

- The operator used is **`<customer><set>` with `<values><gen_info><passwd>`**, not `set_password`. This is on the live-panel verification gate (§16) with golden packets per dialect.
- **Order: panel login FIRST, FTP/system second.** The panel password is the credential WHMCS's stored password actually represents.
  - Panel-set fails → nothing changed → return failure, state consistent.
  - Panel-set succeeds, FTP-set fails → **return `'success'`** (the panel accepted the credential WHMCS is storing), write `pending_sync += password`, write a `repair` event, and surface a "Resync FTP password" action on the service admin tab and in the client area.
- **We never write a third value nobody holds.** No revert-to-random branch exists.
- The error string states only what the module knows and never asserts a WHMCS-side rollback it cannot guarantee.
- cPanel: one `passwd` call satisfies both targets.

### 7.5 Mismatch 4 — Package prefix vs GUID

```php
final class PackageRef {
    const KIND_NAME='name'; const KIND_GUID='guid';
    public function configuredName(); public function nativeRef(); public function kind(); public function displayName();
}
```

- **`Config\PackageSelector::select(ProductConfig $c, $panelType)` throws `ConfigurationException` before any panel I/O when the ordinal for the resolved panel is blank** (*resolves failure HIGH "blank plan ordinal → unfiltered service-plan.get → arbitrary plan"*). Message names the product, the ordinal, the server and the panel.
- **cPanel candidate order is identity-aware** (*resolves failure HIGH "exact match preferred over the reseller's own package"*): when `identity_kind === 'reseller'`, try `"{$serverusername}_{$name}"` **first**, then exact, then case-insensitive variants. When `identity_kind === 'admin'`, exact first. If **both** the prefixed and bare forms exist in `listpkgs`, that is an **ambiguity error**, not a first-match win: *"Both 'Gold' and 'acme_Gold' exist on srv-de-01. Set `cpanel.pkgprefix=force` or `=never` to disambiguate."*
- Case-insensitive comparison uses `mb_strtolower($s, 'UTF-8')`, **never `strcasecmp`** (which maps I↔ı under a `tr_TR` locale and breaks Turkish plan names — a real risk given the Turkish-market brief).
- De-prefixing for display strips only a leading `$serverusername . '_'` via `strpos(...) === 0 && substr(...)`. Never `explode('_', $p)[1]`, never `str_replace($u.'_', '', $p)`. If de-prefixing would collide with another entry in the same list, the raw name is shown.
- **Plesk:** `service-plan.get` filtered by exact name. `absorb()` **asserts exactly one `<result>` node AND that the returned plan name string-equals the requested name**; anything else throws. `webspace.add` uses `plan-name`; `switch-subscription` uses `plan-guid`. Cached in `mod_rhpanel_cache` (300s).
- The resolved native ref is persisted to `mod_rhpanel_accounts.plan_ref`, so drift is visible and ChangePackage can compare.
- Failure lists up to ten plan names actually visible to this login.

### 7.6 Mismatch 5 — Usage units and sentinels

```php
final class Unit { const BYTES='b'; const MEGABYTES='mb'; }

final class ByteParser {
    /** @param string $defaultUnit REQUIRED — Unit::BYTES|Unit::MEGABYTES
     *  @return float|null   null === unlimited sentinel seen ('unlimited' or -1)
     *  @throws ProtocolException on a value that is neither numeric nor a known sentinel */
    public static function parse($value, $defaultUnit);
}

final class Quota {
    public static function bytes($n);       // rejects <0 and >2^50 → ProtocolException
    public static function megabytes($n);   // int-typed
    public static function unlimited();
    public function isUnlimited(); public function toBytes(); public function toMegabytes();
    public function toWhmcsField();         // '0' when unlimited (core's convention)
}

final class UsageRecord {   // each field: Quota|null,  null === PANEL DID NOT REPORT
    public $diskUsed = null;  public $diskLimit = null;
    public $bwUsed   = null;  public $bwLimit   = null;
}
```

Three states, unambiguously: `Quota::bytes(n)` = a real value; `Quota::unlimited()` = unlimited (writes `0`); `null` = **unknown → the column is omitted from the UPDATE entirely, preserving the last good value**, and an unknown-count is reported through `UsageReporter`.

- `ByteParser::parse()` has **no unit-less call site** — `'512'` from an old WHM `listaccts` is parsed with `Unit::MEGABYTES` and cannot become 512 bytes → 0 MB → "unlimited" (*resolves failure HIGH*).
- The "`0` on a cPanel `disklimit` means unlimited" rule lives in `CpanelUsageNormaliser`, **scoped to that field only** — never inside `ByteParser`. `ByteParser::parse('0', MB) === 0.0`.
- Plesk `-1` becomes `unlimited` **at the parse boundary, before any arithmetic**. Nothing ever divides a limit uniformly.
- `ServiceWriter` performs the single conversion `(int) floor($bytes / 1048576)`, floored at 1 MB for any non-zero input, and returns `float|null` from the parser so 32-bit builds do not `TypeError` inside `intdiv` on multi-TB values.
- The cPanel reseller path and account path share one normaliser.

### 7.7 Mismatch 6 — SSO shape difference

`Dto\SsoTarget{url, postAction, postFields, method, expiresAt}` with `isRedirectable()`.

- **cPanel:** `create_user_session` (`api.version=1`, `user`, `service=cpaneld|whostmgrd|webmaild`, optional `app`) → `data.url`. Preserve the `https:`→`http:` / port rewrite when `serversecure` is false. The **client's** IP (via WHMCS `get_ip()`, which honours the install's proxy header config — not raw `REMOTE_ADDR`) is what matters for session-IP validation; the probe reads `get_tweaksetting?key=cookieipvalidation` and warns when strict, since WHM mints the session for WHMCS's outbound IP but the customer's browser consumes it.
- **Plesk tier 1:** `server.create_session` with `<login>` and base64(`get_ip()`), then `…/enterprise/rsession_init.php?PLESKSESSID={id}&success_redirect_url={…}` — **gated on `capabilities.rsessionGet`**, which starts `unknown` and is probed once (unauthenticated GET, inspecting the `Location` header, not merely asserting "a 3xx", because `rsession_init.php` 302s to the login page on an invalid session).
- **Plesk tier 2 (default until tier 1 is proven on a live 18.0.8x):** `SsoTarget::form()`. `ServiceSingleSignOn` returns `success=false` with a precise `errorMsg`, and `overview.tpl` renders `sso_post.tpl` — an auto-submitting POST form — so the customer still gets one click. The session id never appears in a URL, browser history, `Referer`, or proxy log. **Tier ordering is a config decision (`sso.plesk_tier=post|get`), defaulting to `post`.**
- **`AdminSingleSignOn` uses `ServerContext`** — it has no `username()` and rejects `$params['username']`. On a Plesk that fails the redirectable probe, it returns a precise `errorMsg` **and** the server badge (rendered by `_RenderRemoteMetaData`, §12.3) carries a working auto-POST admin button.

### 7.8 Mismatch 7 — `UsageUpdate` is server-params-only, and must survive scale

*Resolves failure HIGH "OOM/timeout at 5,000 accounts; a PHP fatal is not catchable".*

`ServerContext` only. Plan:

1. `usage.enumerate` (Local): `tblhosting` **joined to `tblproducts` on `packageid` with `tblproducts.servertype = 'rhpanel'`** (*resolves failure LOW "enumerates services this module does not own"*), `server = :sid`, `domainstatus IN ('Active','Suspended')`, left-joined to `mod_rhpanel_accounts`. Paged by a cursor stored in `mod_rhpanel_cache` so a very large server resumes next cron rather than failing forever. Hard cap per pass (`usage.max_per_pass`, default 1500).
2. `usage.fetch` (Remote, panel-specific):
   - cPanel: `listaccts` (with `want=` when `product_version >= 11.44`, else full) + `showbw`. Response body is **size-capped** (`usage.max_body`, default 32 MB) — above it, `ProtocolException`, no `json_decode`. A `memory_get_usage()` headroom check runs before decode and aborts with a real exception rather than a fatal.
   - Plesk: `webspace.get` with `resource-usage + limits + gen_info`, **chunked at 100 `<name>` filters** per packet. `PleskPacketParser` asserts `count(results) === count(filters)` and maps per-result status individually; a 1013 on one name is *skip that row*, never a chunk-level failure (*resolves failure HIGH "one missing webspace kills a 200-service chunk"*).
3. **Wall-clock budget** (`usage.deadline`, default 240s) — deadline-truncated rows are **skipped**, explicitly not recorded as misses.
4. `usage.write` (Local): batched updates through `ServiceWriter`, chunked at 200, **omitting any column whose `UsageRecord` field is `null`**.
5. Orphans: a service the panel omits increments `orphan_misses`; `orphan_since` is set only after **3 consecutive** misses, so a truncated `listaccts` cannot mass-orphan a server.
6. `UsageReporter` returns `'success'` or the error string; either way it stamps `last_usage_success_at` / `last_usage_error` and the server badge shows a red state after N days without a success.

Per-operation transport profile: connect 8s, total 30s per request for usage; 180s for lifecycle; 20s for probe. Bulk reads are **excluded from the idempotent-read retry whitelist** — retrying a 30 MB response after a timeout is how a slow cron becomes a dead cron.

### 7.9 Mismatch 8 — natural-key divergence (username vs domain vs webspace name)

`mod_rhpanel_accounts.panel_login` is **authoritative** for the panel-side identity; `tblhosting.username` is kept in lockstep with it.

`Normalise\LoginDeriver::derive($proposed, $panelType, Capabilities $c)` validates against the panel's real constraints (no leading digit or `-` for a Plesk system user; charset `[a-z0-9_.-]`; per-panel length cap; deny-list `root, admin, mysql, postfix, apache, www, ftp, test`). Any adjustment (including a collision-retry suffix) is written to **both** `panel_login` and `tblhosting.username` via `ServiceWriter` — `username` is on the write whitelist precisely for this reason. The two never diverge silently, so the welcome email, the client area, and the usage join all agree.

Join keys: cPanel matches on `panel_login` first then `webspace_name`/domain; Plesk matches on `webspace_guid` then `webspace_name`. The divergence never escapes the driver — records come back keyed by `serviceid`.

**Create collision handling** (*resolves TDC BLOCKER, applied here*): a conflict on create triggers `reconcile()` **first** — read the existing object back (`accountsummary?user=` / `customer.get` by external-id). Same domain and ours → **adopt**, write back, return success. Different domain → *then* derive a distinct login and retry. Never rename without a read-back. `AutoGenerateUsernameAndPassword => true` is declared explicitly, so core generates and persists the initial pair before `CreateAccount` runs and our write-back corrects it.

### 7.10 Mismatch 9 — dialect / version negotiation

Covered in §5. The mechanism the bundled module used (`__call` + declaring-class + mutable registry global) is banned by lint.

### 7.11 Mismatch 10 — `serveraccesshash` = TOKEN for both panels (locked #13)

`ServerCredentials::token()` returns `serveraccesshash` for both panels. Plesk port = `resolved_port` ?: `serverport` ?: (`serversecure ? 8443 : 8880`). **`_MetaData` declares NO `DefaultSSLPort` / `DefaultNonSSLPort`** (*resolves contract HIGH*), because core backfills `serverport` from them when the field is blank, which would make the per-panel fallback permanently unreachable and would pre-fill 2087 into the Add-Server form for Plesk boxes. `docs/INSTALL.md` states the port explicitly per panel.

Migration warning: if `serveraccesshash` parses as an integer in `{8443, 8880, 2086, 2087}`, TestConnection emits *"The API Token field contains what looks like a port number. The bundled Plesk module stored the port here — see UPGRADE-FROM-BUNDLED.md."*

### 7.12 Mismatch 11 — `serverip ?: serverhostname` (locked #14)

`ServerCredentials::connectHost()` returns `serverip ?: serverhostname` for **both** panels; `TlsPolicy` decides which of the two goes in the URL (§4.6). The bundled Plesk module's hostname-only behaviour is not inherited.

### 7.13 Mismatch 12 — TLS verification

§4.6.

### 7.14 Mismatch 13 — positional `configoptionN` binding / 24-slot cap

§11.

### 7.15 Mismatch 14 — Plesk cannot honour per-service limit overrides; Dedicated IP is admin-only

*Resolves failure HIGH "identical products yield different entitlements per panel, silently".*

- **Dedicated IP (ordinal 5) = yes on a Plesk server with `identity_kind !== 'admin'` → `ConfigurationException` at create. We do not provision.** Message: *"Dedicated IP requires Plesk administrator credentials; this server is configured with reseller credentials (locked policy). Remove the Dedicated IP option from this product or use a cPanel server."*
- **Disk / Bandwidth overrides (ordinals 3, 4) on Plesk:** the plan-bound subscription silently reverts overrides on the next plan sync. So: set them, then **immediately re-read `webspace.get limits` and assert they stuck**. If they did not, throw with an actionable message rather than provisioning a mis-sold account.
- `TestConnection` ships a **catalogue diff sub-report**: for every product whose server group can reach this server, it prints the configured disk/BW/plan against the panel plan's actual limits side by side. This is the mitigation for "the admin is the integrity constraint" and it lives on the one button admins actually press.

---

## 8. Wire layer and secret handling

### 8.1 Redaction at the boundary, not by substring

*Resolves failure HIGH "SecretVault guesses encodings; short secrets are dropped; the cPanel session URL leaks".*

```php
final class Secret {
    public function __construct($value, $label);
    public function reveal();        // only CurlTransport calls this
    public function token();         // '__RHP_SECRET_3__'
    public function __toString();    // returns token() — so an accidental echo is safe
}
```

Payload values carrying credentials are `Secret` instances. The serialiser emits `$secret->token()` into the **loggable** byte stream and substitutes the real value only into the buffer handed to curl. **The loggable form never contained the secret in any encoding** — the guarantee is structural, not empirical.

`Transport\SecretVault` remains as the second layer for anything that escapes that discipline (reactively-discovered values in *responses*): it registers each value in raw, `urlencode`, `rawurlencode`, `htmlspecialchars(ENT_COMPAT|ENT_HTML401)`, `htmlspecialchars(ENT_QUOTES|ENT_XML1)` (derived by calling the *same* escape helper `PacketBuilder` uses — never a second hardcoded copy), `json_encode`-escaped, `base64_encode($user.':'.$v)`, and the composed `"WHM {$user}:{$token}"` / `"KEY: {$token}"` header strings. Reactively registered: `secret_key`, `PLESKSESSID`, any Plesk session id, and **the entire query string of any URL appearing in a response** (which covers cPanel's `create_user_session` → `data.url?session=user:HASH`, a live login).

**Short secrets are never dropped.** If any registered secret's shortest encoded form is under 6 characters, the request/response bodies are **suppressed entirely** for that call and the log records `[body suppressed: a configured secret is too short to redact safely]` plus the structured `$data` summary. Diagnostics survive; nothing leaks.

Headers are **never** logged at all — `Authorization`, `KEY`, `HTTP_AUTH_PASSWD` are stripped structurally.

`ErrorCatalogue` never calls `getTraceAsString()` (which truncates string args to 15 chars, producing unmaskable fragments). It builds the trace from `getTrace()` with all `args` dropped. The secret list is assembled from raw `$params` as the **first statement** of `ContextFactory::build()`, so it exists even when context construction itself throws.

### 8.2 Panel error text never reaches an admin string verbatim

*Resolves failure HIGH "Plesk `<errtext>` echoes submitted values into `adminMessage()` and `logActivity`".*

`PanelErrorException` carries `panelCode` and the raw `panelText` **for the events table only** (redacted on the way in). `adminMessage()` is built from `lang/` keyed on the code, plus the numeric panel code: *"Plesk rejected the request (error 1014) during plesk.webspace.set. [RHP-3014/a4f19c]"*. A lint rule asserts `RhPanelException` is never constructed with a variable derived from an `errtext`/`reason` node. **Every outbound string** — `adminMessage()`, `clientMessage()`, every `logActivity()` call, every `EventLog::write()` — passes through `SecretVault::redact()`, not just `logModuleCall`.

`Kernel::dispatchPure` for the Loader additionally passes messages through `htmlspecialchars(..., ENT_QUOTES)` because `ProductSetup` injects them into a `title="…"` attribute unescaped.

### 8.3 Module log

```php
ModuleLog::write('rhpanel', $spec->stepId() . ' @ ' . (string) $spec->dialect(), $req, $res, $summary, $vault->replacements());
```

`'plesk.webspace.set @ plesk:1.6.7.0'` in the action field means the chosen wire version is visible in any support ticket — the single fact that would have made the bundled module's 1.0.0.0 pinning obvious. `$summary` carries panel type, negotiated version, chosen operator/verb, elapsed ms, retry count, module version. Expensive payloads are built only when `ModuleDebugMode` is on (`class_exists`-guarded `Setting::getValue`).

---

## 9. Panel binding — a third panel is pure addition

```php
Registry::$panelBindings = array(
    'cpanel' => array('serializer'=>Wire\Cpanel\CpanelRequestSerializer::class,
                      'parser'    =>Wire\Cpanel\CpanelResponseParser::class,
                      'probe'     =>Panel\Probe\CpanelProbe::class,
                      'usage'     =>Normalise\CpanelUsageNormaliser::class,
                      'dialects'  =>Dialect\CpanelDialectTable::class),
    'plesk'  => array(...),
);
```

`RequestSpec::serializer()` / `parserFor()` **look up**; they do not branch. Every other panel branch in the codebase is a `switch` with `default: throw new PanelTypeUnknownException` — a lint gate forbids an `else` following any comparison against `'cpanel'`/`'plesk'`/`PANEL_*` in `lib/` (*resolves failure MEDIUM "cPanel is the structural default on twenty code paths"*). `Dialect::PANEL_UNKNOWN` exists so the type layer helps. A behaviour test seeds `panel_type = ''` and asserts every entry point fail-closes with zero packets sent.

---

## 10. Persistence

```php
final class ServiceWriter {
    const ALLOWED = array('username','password','domain','dedicatedip',
                          'diskusage','disklimit','bwusage','bwlimit','lastupdate','subscriptionid');
    /** @throws ConfigurationException on any key outside ALLOWED */
    public function save(array $fields, $serviceId, $model = null);
}
```

The **only** writer to `tblhosting`. An unrecognised key silently auto-creates a permanent admin-only custom field, so the whitelist throws. `password` is written through `encrypt()` inside the writer, so no call site can get it wrong. Prefers `$params['model']->serviceProperties->save()` when a model is present (feature-detected for 7.8/8.x), falls back to Capsule otherwise; the usage path always uses Capsule because `UsageUpdate` has no model.

`AccountLinkRepository` is the only reader/writer of `mod_rhpanel_accounts`. It **rejects** any write where `serviceid` is 0.

`CacheRepository::get()` uses `expires_at > NOW()` against a `NOT NULL` column — never ambiguous.

**Panel-type drift guard** (*resolves failure BLOCKER "a repointed server routes a cPanel service into Plesk's destructive operators"*): before any mutator, `Executor` asserts `$link->panel_type === '' || $link->panel_type === $profile->panelType()`. Mismatch → `ConfigurationException`: *"service #500 was provisioned on cPanel but server #7 now reports Plesk. No panel call was attempted."* + repair event + explicit admin re-bind required.

---

## 11. Config options

### 11.1 The frozen ordinal map — *resolves both ordinal BLOCKERs*

`Config\ConfigOptionMap` is the single source of truth; `_ConfigOptions` is **generated by walking it**; a lint rule forbids `/configoption\d/` anywhere else.

```php
public static function order() {   // APPEND ONLY. Never insert, reorder, or delete.
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
public static function get(array $params, $slug, $default = '');
```

| # | Slug | FriendlyName | Type | Panel |
|---|---|---|---|---|
| 1 | `cpanel_package` | cPanel Package Name | `text` + `Loader` + `SimpleMode` | cPanel |
| 2 | `plesk_plan` | Plesk Service Plan | `text` + `Loader` + `SimpleMode` | Plesk |
| 3 | `disk_mb` | Disk Quota (MB) | `text` | shared (blank = plan default) |
| 4 | `bw_mb` | Bandwidth Limit (MB) | `text` | shared |
| 5 | `dedicated_ip` | Dedicated IP | `yesno` | shared (Plesk: admin identity required, else hard error) |
| 6 | `login_target` | Panel Login Target | `dropdown` `Control Panel,Webmail,File Manager` | shared |
| 7 | `advanced` | Advanced Options | `textarea` (Rows 6) | shared |
| **8–24** | — | **RESERVED. NOT EMITTED.** | — | v2 reseller 8–17, third panel 18–24 |

**Only 7 entries are returned.** No tombstones — a `(reserved)` FriendlyName is the *outer array key*, so ten of them collapse into one element and silently shift every later ordinal, and they would render as ten editable text boxes admins type into. Omitting trailing entries leaves `configoption8..24` empty; appending later lands on ordinal 8 naturally, because binding is by iteration position.

Six cPanel-only counters (shell, CGI, maxftp, maxpop, maxsql, maxsub) and Plesk add-on plans move into the advanced textarea — one ordinal instead of seven, no code change when cPanel adds a `createacct` field, and 17 genuinely free ordinals instead of zero.

**`_ConfigOptions` returns the identical ordered key list for every combination of `producttype`, `isAddon` and `whmcsVersion`.** Inapplicable options are rendered inert with a capability reason in the `Description`, never omitted. `Config\OrdinalLock` ships a per-producttype hash map, and `tests/Unit/OrdinalLockTest` iterates the cartesian product and asserts `count() === 7` plus byte-identical ordered keys.

### 11.2 Advanced keys (whitelist-validated)

`cpanel.pkgprefix=auto|force|never`, `cpanel.theme=`, `cpanel.hasshell=0|1`, `cpanel.cgi=0|1`, `cpanel.maxftp=`, `cpanel.maxpop=`, `cpanel.maxsql=`, `cpanel.maxsub=`, `cpanel.maxpark=`, `cpanel.maxaddon=`, `cpanel.keepdns=0|1`, `plesk.addon_plans=a,b`, `plesk.ipmode=`, `plesk.htype=vrt_hst|none`, `plesk.poweruser=0|1`, `plesk.delete_owner=0|1`, `suspendreason.mode=log|description|none`, `sso.plesk_tier=post|get`, `sso.ttl=`, `usage.chunk=`, `usage.skip=1`, `tls.insecure=1`.

Booleans are parsed with `filter_var(..., FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`; `false`/`no`/`off` are honoured, and an unparseable boolean is a `ConfigurationException` at use time, not a silent truthy cast. **Every destructive key defaults to the safe value.**

### 11.3 Validation lives where it can actually run — *resolves the misplaced-hook HIGH*

`PreModuleCreate` fires at provisioning, not at product save, and WHMCS has no product-save module hook — so the design does **not** claim one. Instead:

1. `Config\AdvancedOptions::parse()` validates on every read inside `ContextFactory::build()`. An unknown key or bad boolean is a clean, actionable `ConfigurationException` through `StringReporter`.
2. **`TestConnection` lints every product** whose server group contains this server: unknown advanced keys, a blank plan ordinal for a panel present in the group, a Dedicated IP flag against reseller Plesk credentials, and the §7.15 catalogue diff. This is the one button admins press, so validation lands there.
3. Ordinal 7's `Description` lists the valid keys — the only in-UI documentation surface WHMCS offers.
4. `hooks.php` carries only advisory hooks, each body wrapped in its own try/catch that swallows and logs, because a throw there takes down every admin and client page.

### 11.4 The Loader — bypasses the Kernel entirely

*Resolves contract HIGH "the Loader contract is exception-driven and the catch-all Kernel makes it unreachable".*

```php
function rhpanel_LoadPackages(array $params) { return Config\OptionLoader::run($params, 'cpanel'); }
function rhpanel_LoadPlans(array $params)    { return Config\OptionLoader::run($params, 'plesk'); }
```

`OptionLoader::run()` has its own `catch (\Throwable)` and:
- returns a **flat `array(nativeRef => displayLabel)`** on success (asserted `is_string` on both key and value), passed through `Support\Html::escapeOptions()` because `moduleConfigFieldOutput` does not escape dropdown keys or labels;
- **throws** `WHMCS\Exception\Module\InvalidConfiguration` for configuration/auth faults — **`class_exists`-guarded**, falling back to `new \Exception($msg)` when the class is absent on older 7.8 builds (*resolves maintainability HIGH*);
- throws a plain `\Exception` with an **attribute-escaped, redacted** message otherwise, so the admin gets a precise tooltip and no markup break;
- **repairs the empty-`$params` trap**: `getModuleSettingsFields()` populates `$params` only inside `if (!$serverId)`, so when the refresh control posts `server` the Loader receives `array()`. `Support\RequestVar` reads `App::getFromRequest('server')` (feature-detected across 7.8/8.x), falls back to `getServerID($module, $servergroup)`, and rebuilds via `$m = new \WHMCS\Module\Server(); $m->load('rhpanel'); $params = $m->getServerParams((int)$sid);` — an **instance** call, never `::` static (which is an uncatchable `\Error` that white-pages the product page);
- **mixed-group behaviour:** if the resolved server's panel does not match the field's filter, it walks the product's server group for a member of the required type (cache-first, no probe storm). If none exists it **returns `array()`** rather than throwing, so an admin editing a cPanel-only product simply sees an empty Plesk dropdown instead of a scary error. If one exists but is unreachable, it throws with a precise message.
- Results cached 300s in `mod_rhpanel_cache`; the refresh icon bypasses the cache.

**WHMCS 7.8:** `Loader` + `SimpleMode` availability is a **pre-build verification item** (§16). When unsupported, `ConfigOptionMap` appends to ordinals 1 and 2's `Description`: *"Live plan list requires WHMCS 8.0+; type the name manually."* and TestConnection prints the resolvable plan list. The degradation is stated in `INSTALL.md` and on the sales page.

### 11.5 `_MetaData` — explicit, and side-effect free

```php
function rhpanel_MetaData() {
    return array(
        'DisplayName'                       => 'Reseller Hosting Panel (cPanel & Plesk)',
        'APIVersion'                        => '1.1',
        'RequiresServer'                    => true,
        'AutoGenerateUsernameAndPassword'   => true,
        'DefaultNonSSLPort'                 => null,   // OMITTED — see §7.11
        'DefaultSSLPort'                    => null,   // OMITTED
        'ServiceSingleSignOnLabel'          => 'Login to Control Panel',
        'AdminSingleSignOnLabel'            => 'Login to Panel as Administrator',
        'ListAccountsUniqueIdentifierField' => 'domain',
        'ListAccountsProductField'          => 'moduleConfigOption1',
    );
}
```

(The two port keys are simply absent from the returned array; shown here only to document the decision.) `ListAccountsProductField` must be a Product **Eloquent attribute name**, hence `moduleConfigOption1`, and `SUPPORTED-VERSIONS.md` records that Server Sync product matching is meaningful for cPanel servers only. `rhpanel_MetaData()` calls `Kernel::dispatchPure` and touches no DB, no network, no lang file — `MetaDataPurityTest` runs it with the DB connection closed and the transport stubbed to throw.

`AccountSummary.created` is required in exactly `Y-m-d H:i:s`, defaulting to `date('Y-m-d H:i:s', 0)` when the panel does not report it, because Server Sync does an unguarded `Carbon::createFromFormat` that throws on `false`.

---

## 12. WHMCS surface details that bite

### 12.1 Two dispatchers, opposite success tests
`ModuleCallFunction` treats `$result == "success"` as success; `Service::moduleCall()` treats `'' / null / false` as success and anything else as an error array. `StringReporter` returning the literal `'success'` satisfies both. `tests/Contract` asserts against **both** dispatchers explicitly.

### 12.2 `TestConnection` is a boolean with an error string — and nothing else
*Resolves contract HIGH.* Core hardcodes the success growl and discards every extra key. So the diagnostic report (resolved panel + version, negotiated protos, per-step dialect, identity, missing ACLs, TLS verdict, catalogue diff, advanced-key lint, schema version, module version, PHP/ionCube loader version) goes to **three real places**: `mod_rhpanel_servers.warnings` + `mod_rhpanel_events`, the server badge, and `logActivity`. Warnings are **never** smuggled into `error` (which would flip the test to failed and abort the Add-Server wizard). Both consumers call `array_key_exists()` with no `is_array()` guard, so returning a string here is a PHP 8 fatal — `ArrayReporter` makes that unrepresentable.

### 12.3 `_AdminLink` is dead code when `_AdminSingleSignOn` exists
*Resolves TDC's BLOCKER, applied here.* The Servers-page loop calls `AdminLink` only in the `else` branch of `functionExists("AdminSingleSignOn")`, and locked decision #6 guarantees admin SSO exists. So the server badge is rendered by **`_RenderRemoteMetaData`**, which is called unconditionally in the same loop and wrapped in `<div class="remote-meta-data">`. `_GetRemoteMetaData` is implemented so the refresh control appears. `$params['remoteData']` can be null — never dereferenced unchecked. `_AdminLink` is retained only as a no-AdminSSO fallback.

### 12.4 One client-area template slot, not two
Returning both `tabOverviewReplacementTemplate` and `tabOverviewModuleOutputTemplate` suppresses the module-output slot, because the theme wraps every `{$moduleclientarea}` site inside `{if $tplOverviewTabOutput}…{else}`. rhpanel uses **`tabOverviewModuleOutputTemplate` only**; the SSO button and the Plesk auto-POST fallback live inside `overview.tpl`. Note the module-output slot is gated on `status == "Active" && checkContactPermission("manageproducts", true)`, so the button is additionally gated in-template on a `canManage` template variable we compute.

### 12.5 `AutoPopulateServerConfig`
Implemented. Core calls it **unconditionally** after a successful TestConnection and then does `implode("\n", $response['assignedIps'])` with no guard — a missing function returns a sentinel *string* and fatals the Add Server wizard on PHP 8. Returns `['name','hostname','primaryIp','assignedIps'=>array(),'assignedips'=>array(),'nameservers'=>array()]`, populated from the probe plus WHM `listips` / Plesk `<ip><get>`, well-shaped-but-empty on failure. It is also where the correct per-panel port is offered to the admin, compensating for omitting `DefaultSSLPort`.

### 12.6 `_ClientAreaAllowedFunctions`
Declared (with no parameters), returning the flat suffix list for the AJAX custom functions (`Resync`, `UsageRefresh`, `SsoBounce`). Without it, `in_array($moduleAction, $allowedModuleFunctions)` fails silently and the rich client area is inert with zero diagnostics.

**Every client-area custom function's first line is `Context\ClientGuard::assert($params)`**, which verifies (a) a client session exists, (b) `tblhosting.userid` matches the session client, (c) `domainstatus` is Active or Suspended. A lint rule asserts every whitelisted function contains that call. Without it, any authenticated client can pass another client's `serviceid`.

### 12.7 Double `Sanitize::decode`
`APIVersion => '1.1'` makes `prepareParams` decode the whole params array, and `buildServiceParams`/`getServerParams` already decoded the credential fields — so a password literally containing `&amp;` is decoded twice. `ContextFactory` reads credential fields once and a contract test feeds entity-shaped passwords; our own generated passwords exclude `&`, `<`, `>` and `;` so the generator can never produce an entity-shaped literal. The stored-password case is documented in `TROUBLESHOOTING.md` and listed as a residual risk.

---

## 13. Error and logging model

`RhPanelException` → `code()` (`RHP-xxxx`), `kind()`, `adminMessage()`, `clientMessage()`, `retryable()`, `context()`.

`ErrorCatalogue::classify(\Throwable $t, $entry, $ref)` maps known exceptions through; anything else becomes `RHP-9000` with the class name and masked, arg-free trace preserved in `mod_rhpanel_events` only. A `\Error` matching `Call to undefined method …Context::` becomes the distinct `RHP-9001`, whose message names the context kind and the method — self-describing even ionCube-encoded.

Every admin string ends `[RHP-xxxx/ref]`; the ref indexes the events table; the events table is written **regardless of ModuleDebugMode**. The module version, WHMCS version, PHP version and ionCube loader version are in every event row and on the badge, so support never asks for them.

**Terminate / Suspend idempotency:** `PanelErrorException::KIND_NOT_FOUND` on terminate (and on suspend-of-already-suspended, unsuspend-of-already-active) returns `'success'`, clears the link row, and logs an informational event. Otherwise a service deleted manually in the panel can never leave Pending Termination.

**Localisation:** every user-visible string resolves through `Support\Lang::t($key, array $sub, $audience)`. Admin locale from the admin session, client locale from `clientsdetails.language`, both falling back to English. Named placeholders (`:server`, `:panel`, `:code`). `tests/Lint/NoHardcodedStringsTest` fails on any literal >25 chars containing a space inside `lib/Exception/`, `lib/Panel/`, `lib/Wire/*/`, `lib/Config/`. `LangParityTest` asserts identical key sets **and identical placeholder-token sets per key** across `english.php` and `turkish.php`. Templates contain zero literal English outside `{$lang.*}`.

---

## 14. The 14 recon mismatches — explicit answers

| # | Mismatch | Answer |
|---|---|---|
| 1 | Panel type detection (one module, two panels) | `mod_rhpanel_servers` keyed on `serverid`; authenticated two-signal probe; port orders but never decides; manual override beats fingerprint; failed probe never mutates `panel_type`; a differing successful probe → `conflict` state; fail-closed everywhere. §4 |
| 2 | Plesk 1 service = 2 objects (customer + webspace) | Locked 1:1 model; deterministic `rhp-{installToken}-{serviceId}` external-id; get-then-add resumable create; commit-certainty compensation; clone detection via SystemURL. §7.2 |
| 3 | Suspend: Plesk bitmask vs cPanel flag + reason | Read-modify-write on both objects; `our_status_bit` recorded at suspend and cleared at unsuspend; refuse the write when the residual is outside our authority; post-write verification; `suspendreason` sink configurable, never silently dropped. §7.3 |
| 4 | Two passwords on Plesk, one on cPanel | `<customer><set>/gen_info/passwd` first, FTP second; partial failure returns success + `pending_sync` + a resync action; no third unknown password ever written. §7.4 |
| 5 | cPanel prefixed package name vs Plesk plan GUID | Identity-aware candidate order, ambiguity is an error not a first-match win, safe de-prefixing, exact-name assertion on the Plesk side, `plan_ref` persisted, blank-ordinal caught before any I/O. §7.5 |
| 6 | Usage units and unlimited sentinels | `ByteParser::parse($v, $requiredUnit)` + `Quota` (bytes\|unlimited) + `UsageRecord` field `null` = *unreported → omit the column*. `-1` → unlimited at the parse boundary; nothing divides a limit. §7.6 |
| 7 | SSO: cPanel returns a URL, Plesk returns a session id | `SsoTarget` models GET-redirect and POST-form uniformly; Plesk defaults to the POST bounce (no session id in a URL) with the GET tier gated on a real probe; client IP via `get_ip()`; `cookieipvalidation` warning. §7.7 |
| 8 | `UsageUpdate` gets server params only | `ServerContext` (no service accessors, throws on service keys); self-join filtered by `tblproducts.servertype`; cursor paging, body cap, memory guard, wall-clock budget, chunked Plesk filters with per-result status, 3-strike orphan rule; `UsageReporter` surfaces failure. §7.8 |
| 9 | Dialect / packet-version negotiation across old and new panels | Declared `(panel, operator, verb) → ordered candidates`; `Dialect` a required ctor arg of `RequestSpec`; legacy `<domain>` reachable only via a *different key* and a separately-registered strategy; 4 golden guards; invalidation on version change / errcode 1014 / 7-day TTL; reflection banned by lint. §5 |
| 10 | `serveraccesshash` = token (bundled Plesk abused it as a port) | Token for both panels; Plesk port from `resolved_port` ?: `serverport` ?: `secure?8443:8880`; **no `DefaultSSLPort` in MetaData**; numeric-token migration warning. §7.11 |
| 11 | Plesk driver must accept `serverip ?: serverhostname` | `ServerCredentials::connectHost()` identical for both panels; hostname used only as the TLS verification name. §7.12 |
| 12 | TLS verification on by default with IP-based config | RESOLVE-pinned when hostname+IP are both set (skipped and warned when an outbound proxy is configured); TOFU cert pinning for IP-only; `insecure` requires an explicit advanced key; `FOLLOWLOCATION=false`. §4.6 |
| 13 | `configoptionN` positional binding, 24-slot cap | 7 emitted ordinals, no tombstones, 17 free (8–17 v2 reseller, 18–24 third panel); one ordinal map; slug accessor; per-producttype `OrdinalLock` hash; six cPanel counters moved to the advanced textarea. §11.1 |
| 14 | Natural-key divergence (WHM username vs Plesk webspace name vs domain) | `panel_login` authoritative and kept in lockstep with `tblhosting.username` via `LoginDeriver` + `ServiceWriter`; joins by GUID first; conflict-on-create reconciles by read-back before ever renaming. §7.9 |

---

## 15. Packaging, licensing, i18n

**Encode manifest** (`build/manifest.txt`, enforced by a release test that unzips the artifact):
- **Encoded:** `rhpanel.php`, `hooks.php`, `lib/**`.
- **Plaintext:** `lang/*.php`, `templates/*.tpl`, `whmcs.json`, `logo*.png`, `docs/**` — so customers can add languages and reskin without an escalation.
- **Absent:** `tests/**`, `build/**`, `tests/Cassette/**` (which contains real captured panel responses and must be scrubbed or synthesised in-repo anyway).
- Per-PHP-minor encode matrix (7.2 / 7.4 / 8.0 / 8.1 / 8.2 / 8.3) with a smoke test that loads the **encoded** module in a container per target and calls `rhpanel_MetaData()` and `rhpanel_ConfigOptions()`.
- `IonCubeSafetyTest` covers `eval`, `create_function`, `extract`, `$$var`, `new $var`, variable callables, and any closure in an array returned to WHMCS.

**Licensing** — the policy is decided now because it shapes the failure model:
`Support\Licence::assert($operationName)` is called from `Bootstrap::init()` with a signed grace token cached in `mod_rhpanel_meta.licence_grace_until`. It **fails open** inside the grace window (default 14 days) and closed after. Destructive-cleanup and read operations — **Terminate, Suspend, UsageUpdate, TestConnection, ClientArea, SSO** — always proceed regardless of licence state; only **CreateAccount and ChangePackage** are blocked after grace expiry. A licence-server outage must never take down a customer's billing run. Licence state is shown on the badge and in TestConnection.

**Bootstrap / autoload:** `require_once` of a small class-map at the top of `rhpanel.php`, followed by `Bootstrap::selfCheck()` which `function_exists`/`class_exists`-tests one canonical symbol per `lib/` subtree. On failure it enters a **degraded mode** where `MetaData` and `TestConnection` still work and report exactly which file failed to decode, and every lifecycle function returns a clean error string — never a fatal "Call to undefined". The core-autoloader-vs-fallback question is a day-one verification spike (§16), and CI runs the whole suite twice: once with the core autoloader simulated, once with only the fallback.

---

## 16. Test strategy

**Layer 0 — harness.** `define('WHMCS', true)`, function stubs (`logModuleCall`, `logActivity`, `curlCall`, `getServerID`, `build_query_string`, `decrypt`/`encrypt`, `Sanitize::decode`), in-memory SQLite Capsule with the five `mod_rhpanel_*` tables plus minimal `tblservers`/`tblhosting`/`tblproducts`. **Two stub variants**, one shaped like 7.8 and one like 8.x, and every Kernel/Compat path runs against both. A fake `$params['model']` records every key written so the custom-field trap is asserted against directly.

**Layer 1 — unit.** `DialectResolver`; `ByteParser` (every observed form × both units, plus `'0'`); `Quota` (negative and >2^50 rejection, 32-bit float path); `StatusNormaliser` round-trips 0/16/32/48/64; `SecretVault` against `&<>"'/` + non-ASCII + a 4-char secret (asserting **body suppression**, not masking); `ConfigOptionMap` bijection; `AdvancedOptions` whitelist + boolean parsing of `false`/`no`/`off`; `Selector::of('')` throws; `LoginDeriver` against IDN, digits-first, collisions, reserved names.

**Layer 2 — golden wire files.** Canonicalised-XML comparison per `(operation × panel × dialect band)`, `UPDATE_GOLDENS=1` to regenerate, plus the four guard assertions of §5 expressed as independent XPath structural tests that a bulk regeneration cannot bless away.

**Layer 3 — matrix coverage.** Cross-product of every operation × every registered strategy × every wire version in its band: exactly one strategy matches each `(op, profile)` pair (no ambiguity, no gap); every `RemoteStep` resolves to a dialect present in its declared candidate list *and* advertised by the profile; no duplicate step ids.

**Layer 4 — persona simulators.** Stateful `PleskSim` / `WhmSim` implementing `TransportInterface`. Personas: `plesk-18.0.80-reseller` (advertises 1.6.9.1…1.6.3.0 and **rejects `<domain>` with errcode 1014**), `plesk-17.8-reseller`, `plesk-12.5-admin`, `plesk-legacy-11.0-admin`, `whm-11.116-reseller`, `whm-11.68-reseller`, plus adversarial: `auth-fail`, `timeout`, `garbage-html` (proxy login page with HTTP 200), `maintenance-page`, `truncated-xml`, `redirect-to-elsewhere`, `slow`, `success-then-garbage-response`, `mixed-chunk-with-1013s`, `5000-accounts`. **The persona list is the compatibility claim, expressed as code.**

**Layer 5 — behavioural.** Named cases include: create killed after `customer.add` then re-run → exactly one customer + one webspace + `'success'`; `webspace.add` returns garbage after succeeding → **zero deletions**, `pending_sync=verify`, `PartialFailure`; terminate with a wiped link row → **zero packets**, `ConfigurationException`; terminate against a customer owning two webspaces → webspace deleted, customer **not** deleted, warning event; suspend at 16 then reseller unsuspend → **no write**, `RHP-2204`; unsuspend after a credential swap admin→reseller → clears `our_status_bit`, not the identity bit; `'512'` disk limit → 512 MB, never unlimited; missing `resource-usage` → column untouched; usage across 250 services → exactly 3 Plesk packets; 5,000 accounts → bounded memory, cursor advanced; redirect persona → token appears in zero requests to the redirect host; every adversarial persona × every lifecycle function → a **string** return, no notice, no warning, no fatal.

**Layer 6 — WHMCS contract.** Reflection over `rhpanel.php`: entry set === `Registry::$entries` keys; every function invoked against every persona and asserted against the contract table with `===` for `'success'`; `is_string` on `error`/`errorMsg`; `accounts` present even on failure; `AutoPopulateServerConfig` returns `assignedIps` as an array; failures forced at each of the three pre-operation stages (Bootstrap, registry lookup, context build) still return the right **shape**; `AdminSingleSignOn` invoked with `username`/`domain` deleted; `MetaDataPurityTest`; `AudienceLeakTest`; assertions against **both** dispatchers.

**Layer 7 — lint / freeze gates.** `php -l` under 7.2 + PHPCompatibility `7.2-8.3`; grep bans on `??=`, `match(`, `fn(`, typed properties, `?->`, `eval`, `create_function`; `AntiPatternTest` (no `ReflectionMethod`/`getDeclaringClass`/`__call`/`debug_backtrace` in `lib/`); no `/configoption\d/` outside `ConfigOptionMap`; no closure in the ConfigOptions return; `is_string` on every `Loader`; the uppercase-first entry-point whitelist; no `else` after a panel-type comparison; `OrdinalLock` per producttype; `NoHardcodedStringsTest`; `LangParityTest` incl. placeholder sets; `ClientGuard` presence in every whitelisted client-area function; no `logModuleCall(` in `lib/` with a literal `array()` mask argument; no file in `lib/` over 700 lines and no function over 80 (with `PacketBuilder`'s operator switch exempted by name); PHPStan level 6 over `lib/`.

**Ground truth.** `RecordingTransport` wraps `CurlTransport` against a staging cPanel and a staging Plesk 18.0.8x; `ReplayTransport` re-runs Layer 5 against those cassettes in CI with no network. Refreshing cassettes against a new panel release is the documented per-supported-version release ritual — the only step that requires a live panel.