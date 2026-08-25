## WHMCS 7.8 Provisioning Module Contract (verified against decoded source)

### 0. Ground rules

- A module is **flat global functions** named `<moduledir>_<FunctionName>` in `modules/servers/<moduledir>/<moduledir>.php`. No class, no interface. Capability is discovered by `function_exists()` only. Verified: `AbstractModule.php:108-118` does `call_user_func($module . "_" . $function, $params)`.
- Missing function → `call()` returns sentinel `"!Function not found in module!"` (`AbstractModule::FUNCTIONDOESNTEXIST`); `ModuleCallFunction` reports `"Function Not Supported by Module"` instead.
- Module dir name must pass `[0-9a-z_-]` and match the main file name.
- Boilerplate: `if (!defined("WHMCS")) { exit(...); }` at top; an `index.php` redirect stub in the dir.

### 1. TWO DISPATCHERS, INCOMPATIBLE SUCCESS CONTRACTS

| | `ModuleCallFunction()` (`includes/modulefunctions.php:30`) | `WHMCS\Service::moduleCall()` (`lib/Service.php:357`) |
|---|---|---|
| Used by | cron, invoice payment, admin buttons, API | client area `ClientArea` + custom functions, module-queue retry |
| Success test | `if ($result == "success")` — exact string | `is_array($r) && empty($r['error'])`, or `is_object($r)` |
| Other string | error message; logged + queued | becomes `['error'=>$s,'data'=>$s]` |
| Catches | `\Exception` → `getMessage()` becomes the error string | — |

**Verified at `includes/modulefunctions.php:92`.** `true`, `1`, `"Success"`, `""`, `null` are ALL failure on the provisioning path. PHP 7 `\Error`/`\Throwable` is **not** caught — a TypeError escapes and fatals the request. Catch broadly yourself.

### 2. Lifecycle functions — return literal `"success"` or an error string

| Function | injected `action` | Notes |
|---|---|---|
| `_CreateAccount($params)` | `create` | Core sets `domainstatus='Active'` on success. Never write `domainstatus` yourself. |
| `_SuspendAccount($params)` | `suspend` | `suspendreason` always present, defaults to `'Overdue on Payment'` |
| `_UnsuspendAccount($params)` | `unsuspend` | |
| `_TerminateAccount($params)` | `terminate` | `keepZone` (bool) present **only** from the admin button. Core cascades terminate to all Active/Suspended addons on success. |
| `_ChangePassword($params)` | `changepw` | `$params['password']` is **already the NEW password**; core wrote it (encrypted) to tblhosting first and rolls back only if you return non-success. |
| `_ChangePackage($params)` | `upgrade` | Params already reflect the NEW product's `configoption1..24`. |
| `_Renew($params)` | `Renew` | Optional. If absent, `ServerRenew` converts to `'notsupported'`; nothing logged or queued. |

Verb mapping is literal (`modulefunctions.php:86`): only `Create|Suspend|Unsuspend|Terminate` get the `Account` suffix.

### 3. Array-returning functions

| Function | Contract |
|---|---|
| `_MetaData()` | Array. Called at **module load**, before any service context, cached. Must be side-effect free — no DB, no network. |
| `_ConfigOptions(array $params)` | Ordered assoc array `FriendlyName => spec`. Position N → `configoption{N}`. Receives ONLY `producttype`, `isAddon`, `action`, `whmcsVersion`. |
| `_TestConnection($params)` | `['success'=>true]` or `['error'=>'msg']` (`error` must be a string). |
| `_ServiceSingleSignOn` / `_AdminSingleSignOn` | `['success'=>true,'redirectTo'=>$url]` or `['success'=>false,'errorMsg'=>$msg]`. Verified consumer `Server.php:359-381`: reads `redirectTo` when `success == true`; throws `SingleSignOnError($results['errorMsg'])`; else throws `'Unable to auto-login.'` |
| `_ListAccounts(array $params)` | `['success'=>true,'accounts'=>[...]]` or `['success'=>false,'accounts'=>[],'error'=>msg]`. Each account: `name,email,username,domain,uniqueIdentifier,product,primaryip,created,status` (status = `WHMCS\Service\Status::ACTIVE|SUSPENDED`). |
| `_GetUserCount(array $params)` | `['success'=>true,'totalAccounts'=>N,'ownedAccounts'=>M]` or `['success'=>false,'error'=>msg]`. |
| `_GetRemoteMetaData(array $params)` | `['version'=>..,'load'=>['one','five','fifteen'],'max_accounts'=>..]` or `['success'=>false,'error'=>msg]`. |
| `_RenderRemoteMetaData(array $params)` | HTML string. Reads `$params['remoteData']->metaData`. |
| `_ClientArea($params)` | Array of directives, or a raw HTML string (injected verbatim), or nothing (falls back to `clientarea.tpl` in the module dir). |
| `_ClientAreaAllowedFunctions()` | **No args.** Flat array of custom function-name suffixes. |
| `_AdminCustomButtonArray($params)` / `_ClientAreaCustomButtonArray()` | `['Button Label' => 'FunctionSuffix']`. ClientArea variant auto-whitelists its values. |
| `_AdminServicesTabFields($params)` | `['Row Label' => '<html>']`. Paired with `_AdminServicesTabFieldsSave($params)`. |
| `_AdminLink($params)` | HTML string, echoed on the Servers page. Called WITH params. |
| `_LoginLink($params)` | HTML string. **Called with NO params** (`$moduleInterface->call('LoginLink')`). |
| `_UsageUpdate($params)` | Returns **nothing**. Receives **server params only**. Must write tblhosting itself. Any non-empty return other than `'success'`/sentinel is logged as failure (`modulefunctions.php:314-317`, verified). |
| `_CreateApplicationLink` / `_DeleteApplicationLink` | Array of error strings; **empty array = success**. Presence of BOTH auto-enables app-link support. |

### 4. `_ClientArea` return keys honoured by core (`clientarea.php:1153-1234`)

`overrideDisplayTitle`, `overrideBreadcrumb`, `appendToBreadcrumb`, `tabOverviewModuleOutputTemplate`, `templatefile` (fallback for same slot), `tabOverviewReplacementTemplate`, `templateVariables` (or legacy `vars`).

**Trap:** the two template slots get DIFFERENT variable sets. `tabOverviewModuleOutputTemplate` receives `customTemplateVariables + moduleParams + templateVariables`. `tabOverviewReplacementTemplate` receives `customTemplateVariables + templateVariables` only — **no moduleParams**.

Custom client-area functions may additionally return `['jsonResponse'=>[...]]`, which emits JSON and `exit()`s immediately.

### 5. `_MetaData` keys core actually reads

`DisplayName`, `APIVersion`, `RequiresServer`, `DefaultSSLPort`, `DefaultNonSSLPort`, `ServiceSingleSignOnLabel`, `AdminSingleSignOnLabel`, `ApplicationLinkDescription`, `AutoGenerateUsernameAndPassword`, `ChangePackageLabel`, `NoEditModuleSettings`, `NoEditPricing`, `ListAccountsUniqueIdentifierField`, `ListAccountsUniqueIdentifierDisplayName`, `ListAccountsProductField`, `SupportsRechecks`.

- `RequiresServer` is tested `!== false` → omitting it means **server required**.
- `APIVersion` default `'1.1'`. ≥1.1 → every param `Sanitize::decode()`d; <1.1 → `convertToCompatHtml()`. Wrong value silently corrupts passwords containing `&`, `<`, quotes.
- `AutoGenerateUsernameAndPassword => false` is the ONLY way to stop core generating and persisting a username + random password **before** `_CreateAccount` runs.

### 6. `_ConfigOptions` field spec

Keys: `Type`, `FriendlyName`, `Size`, `Default`, `Description`, `Options`, `Multiple`, `Rows`, `Cols`, `Placeholder`, `Disabled`, `ReadOnly`, `SimpleMode`, `Loader`.

Types rendered: `text`, `password`, `yesno`, `dropdown`, `radio`, `textarea`. Unknown Type renders only the Description.

**There is NO `SimpleModuleConfigOption` class in 7.8** — I verified by grepping the whole tree; zero hits. Plain nested arrays only.

**`Loader`** (verified `ProductSetup.php:110-131`) — a callable or global function-name string, invoked `$loader($params)` with **server params only**. Fires ONLY when `Type` is `text|dropdown|radio` AND the field has `SimpleMode => true` AND the admin is in Simple Mode. A `text` field with a working Loader is auto-promoted to `dropdown`. Throw `InvalidConfiguration` for the generic message, any other `Exception` for a custom tooltip.

### 7. Hooks

`PreModule{Verb}` (returning `['abortcmd'=>true]` cancels), `AfterModule{Verb}`, `AfterModule{Verb}Failed`. Hook returns are `array_replace_recursive`d into params.

**`hooks.php` in the module dir is NOT auto-globbed.** `Module::defineHooks()` only includes modules named in the `ModuleHooks` config setting, regenerated by `rebuildModuleHookCache()` **solely when a product is saved** in `admin/configproducts.php`, and only for modules currently assigned to a product/addon. A freshly installed module's hooks stay dead until someone saves a product using it.

### 8. Not part of the 7.8 interface

`_UsageUpdateSingle` — verified absent, zero hits across the whole tree. Do not implement expecting core to call it.
