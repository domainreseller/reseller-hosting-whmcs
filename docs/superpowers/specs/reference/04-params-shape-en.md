## Definitive `$params` shape

Assembled by `WHMCS\Module\Server::buildParams()` (`Server.php:108`) = `buildServiceParams()` (155) or `buildAddonParams()` (230), merged with `getServerParams()` (295), plus `clientsdetails`, plus `prepareParams()` injections, plus `Server::call()`'s `action`.

**Critical: context determines which keys exist.** Three distinct shapes:

- **(F) Full service context** — Create/Suspend/Unsuspend/Terminate/ChangePassword/ChangePackage/Renew/ClientArea/AdminLink/AdminServicesTabFields.
- **(S) Server params only** — `UsageUpdate`, `TestConnection`, `ListAccounts`, `GetUserCount`, `GetRemoteMetaData`, `AdminSingleSignOn`, `ConfigOptions` Loader. **No service, no username, no domain, no configoptionN.**
- **(C) ConfigOptions** — only `producttype`, `isAddon`, `action`, `whmcsVersion`.

| Key | Type | Ctx | Source / meaning |
|---|---|---|---|
| `accountid` | int | F | tblhosting id (= serviceid) |
| `serviceid` | int | F | tblhosting id |
| `addonId` | int | F | 0 for services |
| `userid` | int | F | tblclients id |
| `pid` / `packageid` | int | F | tblproducts id (duplicates) |
| `serverid` | int | F,S | tblservers id. **Present whenever a server exists** — the no-server branch of `getServerParams` omits it, so guard with `isset`. |
| `status` | string | F | Pending/Active/Suspended/Terminated/Cancelled/Fraud |
| `type` | string | F | `$product->type` |
| `producttype` | string | F,C | **Duplicate of `type`.** cPanel reads `type` in lifecycle fns but `producttype` in SSO/ConfigOptions. Support both. |
| `moduletype` | string | F | module dir name |
| `domain` | string | F | tblhosting.domain |
| `username` | string | F | tblhosting.username |
| `password` | string | F | **PLAINTEXT** — already `decrypt()`ed then `Sanitize::decode()`d (`Server.php:172`). Do NOT decrypt again. |
| `configoption1..configoption24` | string | F | Hard-capped at 24 (verified `Server.php:186-191`: `while ($counter <= 24)`). Always all 24 present, `''` when unset. **Positional binding to the `ConfigOptions` array order.** |
| `customfields` | array | F | `name => value`. **Both key AND value truncated at the first `\|`.** |
| `configoptions` | array | F | Configurable options `name => value`. For optiontype 3 (yes/no) and 4 (quantity) the value is the **quantity**, not the label. Also `\|`-truncated. |
| `model` | object | F | Live Eloquent `WHMCS\Service\Service` (or `\Addon`). Do not serialize/print_r the params array. |
| `clientsdetails` | array | F | `WHMCS\Client::getDetails()`, `state` overwritten by `statecode`, whole array through `foreignChrReplace()`. |
| `server` | bool | F,S | false when no server assigned |
| `serverip` | string | F,S | tblservers.ipaddress |
| `serverhostname` | string | F,S | tblservers.hostname |
| `serverusername` | string | F,S | `Sanitize::decode()` |
| `serverpassword` | string | F,S | **PLAINTEXT** — `Sanitize::decode(decrypt())` |
| `serveraccesshash` | string | F,S | `Sanitize::decode()` only. **STORED PLAINTEXT IN THE DB** (`Admin/Setup/Servers.php:33` encrypts `password` but writes `accesshash` raw). |
| `serversecure` | bool | F,S | tblservers.secure |
| `serverhttpprefix` | string | F,S | `'http'` / `'https'`, derived purely from `secure` |
| `serverport` | string\|int | F,S | tblservers.port, else MetaData `DefaultSSLPort`/`DefaultNonSSLPort` |
| `whmcsVersion` | string | all | injected by `prepareParams()` |
| `action` | string | all | injected by `Server::call()` — `create/suspend/unsuspend/terminate/changepw/upgrade`, else the function name. **Never use `action` as your own param name.** |
| `suspendreason` | string | F | Suspend only; defaults `'Overdue on Payment'` |
| `keepZone` | bool | F | Terminate **from the admin button only**. Guard with `array_key_exists`. |
| `isAddon` | bool | C | ConfigOptions only |
| `remoteData` | object | S | `RenderRemoteMetaData` only |

### Verified merge order

`Server::call()` (`Server.php:312-346`, read directly): builds params only if `serviceID`/`addonId` is set on the object, forces `$params["action"]`, then `array_merge($builtParams, $params)` — **caller-supplied params win**. `ModuleCallFunction` merges `$extraParams` BEFORE the `PreModule` hook stage, so a hook can clobber them.

### Addon params are a different shape

- **No `configoption1..24` at all.** Module settings arrive as `$params[settingName]` AND `$params[friendlyName]` — a FriendlyName colliding with `domain`/`username` silently overwrites it.
- Custom fields literally named `Username`/`Password`/`Domain` are hoisted out of `customfields` and overwrite the top-level keys.
- `$params['service']` sub-array exists but is **buggy**: `Server.php:141` sets it to the parent service params, then line 142 immediately overwrites it. Do not trust it.

### Write-back

Supported API is `$params['model']->serviceProperties->save([...])`. Native keys → tblhosting: `username, password (auto-encrypted), domain, license (silently rewritten to domain), dedicatedip, diskusage, disklimit, bwusage, bwlimit, lastupdate, subscriptionid`. **Any unrecognised key silently auto-creates an admin-only custom field** — typos become permanent schema noise.

`buildParams()` also populates the global `$GLOBALS['moduleparams']`, which legacy `updateService()` depends on.
