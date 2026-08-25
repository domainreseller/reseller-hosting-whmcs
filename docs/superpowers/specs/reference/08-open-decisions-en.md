# Decisions still requiring a human call

1. **Day-one verification spike, before writing 60 files: does the core autoloader resolve `WHMCS\Module\Server\RhPanel\…` on BOTH 7.8 and 8.x, and with what path mapping?** The whole namespace layout depends on the answer, and the hand-rolled fallback autoloader is currently the least-tested code carrying 100% of the load on whichever generation the assumption is wrong about. Cheap to check, expensive to retrofit.

2. **Does WHMCS 7.8's product-setup path invoke `Loader` + `SimpleMode` at all?** The live package/plan dropdown is a locked v1 headline feature and every line number cited for `ProductSetup` came from one codebase. If 7.8 does not support it, we ship a documented degradation (text field + a Description note + the plan list in TestConnection) and must say so on the sales page — that is a product/marketing decision, not just an engineering one.

3. **Live-panel acceptance gate — needs one real cPanel and one real Plesk 18.0.8x (ideally plus one Plesk 12.x and one WHM 11.68).** Five items cannot be settled without it: (1) `<customer><set>` + `gen_info/passwd` is the correct password operator on both old and new Plesk; (2) `rsession_init.php` GET behaviour and, failing that, the exact POST-form field names for the fallback; (3) whether a reseller-scoped API key can actually issue `webspace.switch-subscription`; (4) whether `customer.set` accepts a late `<external-id>` write during adoption; (5) `create_user_session` survival under strict `cookieipvalidation`. Each becomes a permanent cassette once run.

4. **Does core `curlCall()` apply `outbound_http_ssl_verifypeer` AFTER merging caller options on 7.8 and 8.x?** If it does, our explicit `VERIFYPEER=1` is silently overwritten and the module's central security claim is false. If so we must bypass `curlCall` and re-implement proxy support directly — which trades away the proxy-honouring benefit. Needs a deliberate bad-cert test on both versions.

5. **`ListAccountsProductField` and Server Sync.** It is a single static MetaData value read once per module, so on a dual-panel product it can only ever point at ordinal 1 (the cPanel package). We have set it to `moduleConfigOption1` and documented that Server Sync product matching is meaningful for cPanel servers only. The alternative is omitting it and declaring Server Sync unsupported. Human call on which is the better commercial story.

6. **Plesk SSO tier default.** Shipping `sso.plesk_tier=post` is safer (no session id in a URL, works regardless of the unverified GET) but adds a page load and cannot serve admin SSO from the standard button. Shipping `get` is a better experience if the live-panel gate passes. Recommend `post` for 1.0 and flipping the default in 1.1 once verified — but that is a product decision.

7. **Licence failure policy.** Proposed: 14-day offline grace, fail-open inside it, and after expiry block only CreateAccount and ChangePackage while Terminate/Suspend/UsageUpdate/SSO/TestConnection always run. That is deliberately generous to the customer and deliberately weak against piracy. The grace window length and the blocked-operation set are commercial decisions.

8. **Product addons: confirmed out of scope for v1?** The design now throws a clear `ConfigurationException` when `addonId` is non-zero and removes the half-built addon paths entirely. If addons are actually required, that is a real scope addition (a fourth params shape with no `configoptionN` at all, settings addressed by FriendlyName with a documented collision list) and should be planned rather than discovered.

9. **Reserved ordinal plan.** 8–17 for v2 reseller (mirroring the bundled cPanel module's 15–24 semantics so migration is a shift, not a remap) and 18–24 for a third panel. If a third panel is NOT on the roadmap, 18–24 could go to reseller and the v2 surface gets more room. Needs the roadmap answer before 1.0.0 freezes the map forever.

10. **Advanced-key deprecation policy.** Ordinal 7 will accumulate keys. Do we ever remove one? Proposed: keys are append-only and a removed key becomes a hard error naming its replacement (never a silent ignore). Confirm, because it constrains every future release.

11. **Turkish translation ownership and review.** The lint gates force every user-visible string into lang/ and the parity test enforces identical key AND placeholder sets, but somebody has to write and review the Turkish copy — including the ~40 error messages whose whole value is being actionable. Machine translation will produce technically-parity-passing, operationally-useless strings.

12. **Staging-clone protection UX.** The SystemURL-mismatch guard refuses all destructive operations until acknowledged. Where does the acknowledgement live — the server badge, an addon page, or a config file constant? An admin who legitimately moves a production install to a new domain will hit this, and the recovery path must not require a support ticket.

