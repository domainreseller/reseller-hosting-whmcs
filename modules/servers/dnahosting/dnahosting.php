<?php
/**
 * dnahosting - Reseller Panel provisioning module for WHMCS.
 *
 * Provisions shared hosting on cPanel/WHM and Plesk from one module, using
 * reseller credentials: server IP, username and API token.
 *
 * The panel type is a per-product setting with an Auto option that probes the
 * server. Nothing is cached in a module table: Plesk objects are found again on
 * each call by external-id (the customer) and by domain (the subscription), so
 * the module has no schema of its own to install, migrate or corrupt.
 *
 * Target: PHP 7.2+, WHMCS 7.8 and later.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Exception.php';
require_once __DIR__ . '/lib/Cache.php';
require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/Cpanel.php';
require_once __DIR__ . '/lib/Plesk.php';

// ---------------------------------------------------------------------------
// Module definition
// ---------------------------------------------------------------------------

/**
 * @return array
 */
function dnahosting_MetaData()
{
    // Declaring the ports is what makes WHMCS render the Port field on the
    // server form at all. Without them an admin has no way to enter 8443 for a
    // Plesk box, which matters more than the fact that the defaults shown are
    // cPanel's: each driver recognises the other panel's port and corrects it,
    // so a server left on the default still connects, and a server that needs
    // something else can override it.
    return [
        'DisplayName' => 'DNA Reseller Hosting',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultSSLPort' => '2087',
        'DefaultNonSSLPort' => '2086',
        'AutoGenerateUsernameAndPassword' => true,
        'ServiceSingleSignOnLabel' => 'Log in to Control Panel',
        'AdminSingleSignOnLabel' => 'Log in to Server',
    ];
}

/**
 * Product settings.
 *
 * These bind to configoption1..N by POSITION, so entries may only ever be
 * appended - inserting, removing or reordering one silently reassigns every
 * stored value on every existing product.
 *
 * The map, which must not change once anyone is running this in production:
 *
 *     configoption1  Panel Type
 *     configoption2  Package / Plan
 *     configoption3  Disk Quota (MB)
 *     configoption4  Bandwidth (MB)
 *     configoption5  Dedicated IP
 *
 * @return array
 */
function dnahosting_ConfigOptions()
{
    return [
        'Panel Type' => [
            'Type' => 'dropdown',
            'Options' => 'Auto,cPanel,Plesk',
            'Default' => 'Auto',
            'Description' => 'Auto detects the panel by probing the server.',
        ],
        'Package / Plan' => [
            'Type' => 'text',
            'Size' => '30',
            'Description' => 'Name of the hosting package on the server. On cPanel this is the WHM '
                . 'package (the reseller prefix is added automatically); on Plesk it is the service '
                . 'plan. Use the same name on both panels so one product can serve either.',
        ],
        'Disk Quota (MB)' => [
            'Type' => 'text',
            'Size' => '10',
            'Description' => 'cPanel only. Leave blank to use the package default. '
                . 'On Plesk the service plan decides the limits.',
        ],
        'Bandwidth (MB)' => [
            'Type' => 'text',
            'Size' => '10',
            'Description' => 'cPanel only. Leave blank to use the package default. '
                . 'On Plesk the service plan decides the limits.',
        ],
        'Dedicated IP' => [
            'Type' => 'yesno',
            'Description' => 'cPanel only. Plesk assigns IPs at the plan level.',
        ],
    ];
}

// ---------------------------------------------------------------------------
// Panel resolution
// ---------------------------------------------------------------------------

/**
 * Which panel this server runs.
 *
 * @param array $params
 * @return string cpanel|plesk
 * @throws DnaHosting_Exception
 */
function dnahosting_panelType(array $params)
{
    $configured = isset($params['configoption1']) ? trim($params['configoption1']) : '';

    if (strcasecmp($configured, 'cPanel') === 0) {
        return 'cpanel';
    }
    if (strcasecmp($configured, 'Plesk') === 0) {
        return 'plesk';
    }

    return dnahosting_detectPanel($params);
}

/**
 * Probes the server to find out what it is.
 *
 * Used when the product is set to Auto, and by UsageUpdate, which receives
 * server parameters only and therefore has no product setting to read.
 *
 * The configured port is a hint about which panel to try first, never the
 * answer: an admin who leaves the field blank or types the wrong number should
 * still get a working module.
 *
 * @param array $params
 * @return string cpanel|plesk
 * @throws DnaHosting_Exception
 */
function dnahosting_detectPanel(array $params)
{
    // Which panel a server runs does not change between one order and the next,
    // so probing for it on every operation is pure overhead - and on a server
    // whose other panel's port is firewalled, an overhead measured in
    // connection timeouts.
    $cacheKey = DnaHosting_Cache::keyFor($params);
    $cached = DnaHosting_Cache::get($cacheKey);
    if (is_array($cached) && !empty($cached['panel'])) {
        return $cached['panel'];
    }

    $port = isset($params['serverport']) ? (int) $params['serverport'] : 0;

    // The port only ORDERS the attempts; it never rules a panel out.
    //
    // It cannot be trusted as a decision because WHMCS back-fills a blank Port
    // field from this module's MetaData defaults (Server.php:303), which are
    // cPanel's. A Plesk server left on the default therefore arrives here
    // looking like 2087, and treating that as proof would mean Plesk is never
    // even tried - the module would be unusable on exactly the setup its own
    // README describes.
    //
    // The cost of being wrong once is a single failed connection, and the
    // result is cached for seven days.
    if ($port === 8443 || $port === 8880) {
        $order = ['plesk', 'cpanel'];
    } else {
        $order = ['cpanel', 'plesk'];
    }

    $errors = [];
    $pleskAnswered = false;

    foreach ($order as $panel) {
        try {
            if ($panel === 'cpanel') {
                $api = new DnaHosting_Cpanel($params);
                $api->testConnection();
            } else {
                $api = new DnaHosting_Plesk($params);
                $api->testConnection();
            }
            $existing = DnaHosting_Cache::get($cacheKey);
            $entry = is_array($existing) ? $existing : [];
            $entry['panel'] = $panel;
            DnaHosting_Cache::put($cacheKey, $entry);

            return $panel;
        } catch (DnaHosting_Exception $e) {
            $errors[$panel] = $e->getMessage();

            // Plesk answering with its own XML packet identifies the panel even
            // when it refused the credentials. Reporting "could not determine
            // the panel" in that case hides the real problem, which is the key.
            if ($panel === 'plesk' && isset($api) && $api->answered()) {
                $pleskAnswered = true;
                break;
            }
        }
    }

    if ($pleskAnswered) {
        throw new DnaHosting_Exception(
            'This is a Plesk server, but it rejected the credentials. ' . $errors['plesk']
        );
    }

    throw new DnaHosting_Exception(
        'Could not determine whether this server runs cPanel or Plesk. '
        . 'WHM: ' . (isset($errors['cpanel']) ? $errors['cpanel'] : 'not tried') . ' '
        . 'Plesk: ' . (isset($errors['plesk']) ? $errors['plesk'] : 'not tried') . ' '
        . 'Set the Port field to 2087 for cPanel or 8443 for Plesk, or set Panel Type explicitly '
        . 'on the product to skip detection entirely.'
    );
}

/**
 * The external id used to find this service's Plesk customer again.
 *
 * @param array $params
 * @return string
 */
function dnahosting_externalId(array $params)
{
    $id = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    return 'whmcs-' . $id;
}

/**
 * @param array  $params
 * @param string $key
 * @return string
 */
function dnahosting_option(array $params, $key)
{
    return isset($params[$key]) ? trim((string) $params[$key]) : '';
}

/**
 * Records a failed operation and returns the string WHMCS expects.
 *
 * WHMCS shows the returned string to the admin and queues the operation, but it
 * does not write it anywhere durable. The module log only records anything when
 * Module Debug Mode is on, so the activity log gets a copy too - that one is
 * written unconditionally and is where a support case starts.
 *
 * @param string    $operation
 * @param array     $params
 * @param Exception $e
 * @return string
 */
function dnahosting_fail($operation, array $params, Exception $e)
{
    $serviceId = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    $domain = dnahosting_option($params, 'domain');

    DnaHosting_Http::note($operation, $e->getMessage(), [
        'serviceid' => $serviceId,
        'domain' => $domain,
        'username' => dnahosting_option($params, 'username'),
        'server' => dnahosting_option($params, 'serverip'),
    ]);

    DnaHosting_Http::alert(
        $operation . ' failed for service #' . $serviceId
        . ($domain !== '' ? ' (' . $domain . ')' : '') . ': ' . $e->getMessage()
    );

    return $operation . ' failed: ' . $e->getMessage();
}

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

/**
 * @param array $params
 * @return string
 */
function dnahosting_CreateAccount(array $params)
{
    try {
        $panel = dnahosting_panelType($params);
        $domain = dnahosting_option($params, 'domain');
        $username = dnahosting_option($params, 'username');
        $password = isset($params['password']) ? $params['password'] : '';

        if ($domain === '') {
            throw new DnaHosting_Exception('This service has no domain set.');
        }
        if ($username === '') {
            throw new DnaHosting_Exception('This service has no username set.');
        }

        $disk = dnahosting_option($params, 'configoption3');
        $bw = dnahosting_option($params, 'configoption4');

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $created = $api->createAccount([
                'username' => $username,
                'password' => $password,
                'domain' => $domain,
                'email' => isset($params['clientsdetails']['email']) ? $params['clientsdetails']['email'] : '',
                'plan' => $api->resolvePackage(dnahosting_option($params, 'configoption2')),
                'ip' => !empty($params['configoption5']),
                'quota' => $disk !== '' ? (int) $disk : null,
                'bwlimit' => $bw !== '' ? (int) $bw : null,
            ]);

            // Record the allocated address, otherwise a dedicated IP is billed
            // for but never shown to anyone.
            if (!empty($params['configoption5']) && !empty($created['ip'])) {
                dnahosting_setDedicatedIp($params, $created['ip']);
            }
        } else {
            $api = new DnaHosting_Plesk($params);
            $api->ready();
            $plan = $api->resolvePlan(dnahosting_option($params, 'configoption2'));

            if ($disk !== '' || $bw !== '') {
                // Not applied: sending <limits> would mark the subscription as
                // customized, which makes the next plan switch silently revert
                // it and fails outright without reseller rights.
                DnaHosting_Http::note('create', 'Disk/Bandwidth product settings are ignored on '
                    . 'Plesk; the service plan "' . $plan['name'] . '" decides the limits.');
            }

            $client = isset($params['clientsdetails']) ? $params['clientsdetails'] : [];
            $name = trim(
                (isset($client['firstname']) ? $client['firstname'] : '') . ' '
                . (isset($client['lastname']) ? $client['lastname'] : '')
            );

            $api->createAccount([
                'username' => $username,
                'password' => $password,
                'domain' => $domain,
                'email' => isset($client['email']) ? $client['email'] : '',
                'name' => $name !== '' ? $name : $username,
                'company' => isset($client['companyname']) ? $client['companyname'] : '',
                'plan' => $plan['name'],
                'externalId' => dnahosting_externalId($params),
            ]);
        }

        return 'success';
    } catch (Exception $e) {
        return dnahosting_fail('Create', $params, $e);
    }
}

/**
 * @param array $params
 * @return string
 */
function dnahosting_SuspendAccount(array $params)
{
    try {
        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $api->suspendAccount(
                dnahosting_option($params, 'username'),
                isset($params['suspendreason']) ? $params['suspendreason'] : ''
            );
        } else {
            list($api, $ids) = dnahosting_pleskLocate($params);
            $api->suspend($ids['webspaceId'], $ids['customerId'], $ids['ourBit']);
        }

        return 'success';
    } catch (Exception $e) {
        return dnahosting_fail('Suspend', $params, $e);
    }
}

/**
 * @param array $params
 * @return string
 */
function dnahosting_UnsuspendAccount(array $params)
{
    try {
        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $api->unsuspendAccount(dnahosting_option($params, 'username'));
        } else {
            list($api, $ids) = dnahosting_pleskLocate($params);
            $api->unsuspend($ids['webspaceId'], $ids['customerId'], $ids['ourBit']);
        }

        return 'success';
    } catch (Exception $e) {
        return dnahosting_fail('Unsuspend', $params, $e);
    }
}

/**
 * @param array $params
 * @return string
 */
function dnahosting_TerminateAccount(array $params)
{
    try {
        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $api->terminateAccount(dnahosting_option($params, 'username'), !empty($params['keepZone']));
        } else {
            dnahosting_assertDomainNotShared($params);

            list($api, $ids) = dnahosting_pleskLocate($params, false);
            if ($ids['webspaceId'] === '') {
                // Nothing resolved. Never send an unfiltered delete - in Plesk
                // that matches every subscription on the server. But do not
                // claim success either: the subscription may well still be
                // running and WHMCS would stop billing for it.
                throw new DnaHosting_Exception(
                    'No Plesk subscription could be resolved for ' . dnahosting_option($params, 'domain')
                    . '. Nothing was deleted. Check the subscription in Plesk and remove it by hand '
                    . 'if it is still there.'
                );
            }
            $api->terminate($ids['webspaceId'], $ids['customerId'], dnahosting_externalId($params));
        }

        return 'success';
    } catch (Exception $e) {
        return dnahosting_fail('Terminate', $params, $e);
    }
}

/**
 * @param array $params
 * @return string
 */
function dnahosting_ChangePassword(array $params)
{
    try {
        $panel = dnahosting_panelType($params);
        $password = isset($params['password']) ? $params['password'] : '';

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $api->changePassword(dnahosting_option($params, 'username'), $password);
        } else {
            list($api, $ids) = dnahosting_pleskLocate($params);
            $both = $api->changePassword($ids['customerId'], $ids['webspaceId'], $password);
            if (!$both) {
                // The panel login now matches what WHMCS stores, which is the
                // credential the customer actually uses. Reporting failure here
                // would make WHMCS roll its own copy back and leave the two
                // permanently out of step.
                return 'success';
            }
        }

        return 'success';
    } catch (Exception $e) {
        return dnahosting_fail('Password change', $params, $e);
    }
}

/**
 * @param array $params
 * @return string
 */
function dnahosting_ChangePackage(array $params)
{
    try {
        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $disk = dnahosting_option($params, 'configoption3');
            $bw = dnahosting_option($params, 'configoption4');
            $failed = $api->changePackage(
                dnahosting_option($params, 'username'),
                $api->resolvePackage(dnahosting_option($params, 'configoption2')),
                $disk !== '' ? (int) $disk : null,
                $bw !== '' ? (int) $bw : null
            );

            if (!empty($failed)) {
                // The plan changed but the account is not what the customer is
                // billed for. Reporting success here would bury that forever.
                DnaHosting_Http::alert('package changed for service #'
                    . (isset($params['serviceid']) ? (int) $params['serviceid'] : 0)
                    . ' but these overrides did not apply: ' . implode('; ', $failed));

                return 'Package changed, but these limits could not be applied: '
                    . implode('; ', $failed)
                    . '. The account is running on the plan defaults. Check that the API token '
                    . 'holds the quota and limit-bandwidth privileges.';
            }
        } else {
            list($api, $ids) = dnahosting_pleskLocate($params);
            $api->changePlan($ids['webspaceId'], $api->resolvePlan(dnahosting_option($params, 'configoption2')));
        }

        return 'success';
    } catch (Exception $e) {
        return dnahosting_fail('Package change', $params, $e);
    }
}

/**
 * Records the dedicated IP a panel allocated, on the service.
 *
 * @param array  $params
 * @param string $ip
 * @return void
 */
function dnahosting_setDedicatedIp(array $params, $ip)
{
    $serviceId = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    if ($serviceId <= 0 || $ip === '') {
        return;
    }

    try {
        \WHMCS\Database\Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->update(['dedicatedip' => $ip]);
    } catch (Exception $e) {
        DnaHosting_Http::note('CreateAccount', 'Could not record the dedicated IP ' . $ip
            . ' on service #' . $serviceId . ': ' . $e->getMessage());
    }
}

/**
 * Refuses to proceed when another live service on the same server holds the
 * same domain.
 *
 * A domain cancelled by one client and re-ordered by another leaves two WHMCS
 * services pointing at one panel object. Terminating the older one would delete
 * the newer client's site, and no panel-side check can tell them apart because
 * the panel only ever saw one domain.
 *
 * @param array $params
 * @return void
 * @throws DnaHosting_Exception
 */
function dnahosting_assertDomainNotShared(array $params)
{
    $domain = dnahosting_option($params, 'domain');
    $serviceId = isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    $serverId = isset($params['serverid']) ? (int) $params['serverid'] : 0;

    if ($domain === '' || $serviceId <= 0 || $serverId <= 0) {
        return;
    }

    try {
        $others = \WHMCS\Database\Capsule::table('tblhosting')
            ->where('server', $serverId)
            ->where('domain', $domain)
            ->where('id', '!=', $serviceId)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->pluck('id');

        $others = is_object($others) && method_exists($others, 'all') ? $others->all() : (array) $others;
    } catch (Exception $e) {
        // If the check itself cannot run, do not block the operation - but say so.
        DnaHosting_Http::note('terminate', 'Could not check for duplicate domains: ' . $e->getMessage());
        return;
    }

    if (!empty($others)) {
        throw new DnaHosting_Exception(
            'Refusing to delete: ' . $domain . ' is also held by active service(s) #'
            . implode(', #', $others) . ' on this server. Deleting it would remove the panel '
            . 'objects those services depend on. Terminate this service by hand once you have '
            . 'confirmed which one owns the subscription.'
        );
    }
}

/**
 * Finds this service's Plesk objects.
 *
 * @param array $params
 * @param bool  $required Throw when the subscription is missing.
 * @return array{0: DnaHosting_Plesk, 1: array}
 * @throws DnaHosting_Exception
 */
function dnahosting_pleskLocate(array $params, $required = true)
{
    $api = new DnaHosting_Plesk($params);
    $api->ready();

    $domain = dnahosting_option($params, 'domain');
    $webspace = $domain !== '' ? $api->findWebspace($domain) : null;
    $customer = $api->findCustomer(dnahosting_externalId($params));

    if ($required && ($webspace === null || $webspace['id'] === '')) {
        throw new DnaHosting_Exception(
            'No Plesk subscription was found for ' . $domain . ' on this server.'
        );
    }

    // Ownership gate. The subscription is found by domain, the customer by our
    // external id; if they do not agree, this domain belongs to somebody else
    // on this server and acting on it would suspend, re-password or delete an
    // uninvolved customer's live site.
    //
    // The dangerous case is a domain that was ordered, cancelled, and later
    // ordered again by a DIFFERENT client: the old WHMCS service still points
    // at the domain, and terminating it would destroy the new owner's site.
    if ($webspace !== null && $webspace['ownerId'] !== '') {
        if ($customer !== null) {
            if ($webspace['ownerId'] !== $customer['id']) {
                throw new DnaHosting_Exception(
                    'The Plesk subscription for ' . $domain . ' belongs to a different customer '
                    . '(owner id ' . $webspace['ownerId'] . ', expected ' . $customer['id']
                    . '). Refusing to act on it.'
                );
            }
        } else {
            // No customer carries our external id, yet the domain exists and is
            // owned by someone. Prove it is not another service before touching it.
            $ownerTag = $api->customerExternalId($webspace['ownerId']);

            if ($ownerTag !== '' && $ownerTag !== dnahosting_externalId($params)) {
                throw new DnaHosting_Exception(
                    'The Plesk subscription for ' . $domain . ' belongs to another service ('
                    . $ownerTag . '). Refusing to act on it.'
                );
            }
            // An empty external-id means the customer predates this module
            // (created by hand or by the bundled module), so it is adoptable.
        }
    }

    return [$api, [
        'webspaceId' => $webspace === null ? '' : $webspace['id'],
        'customerId' => $customer === null ? '' : $customer['id'],
        // An administrator's suspension is bit 16 and a reseller's is 32. Which
        // one we may set and clear depends on what our credentials actually are.
        'ourBit' => $api->suspensionBit(),
    ]];
}

// ---------------------------------------------------------------------------
// Server
// ---------------------------------------------------------------------------

/**
 * @param array $params
 * @return array
 */
function dnahosting_TestConnection(array $params)
{
    try {
        // An explicit test always re-checks, so clear anything remembered.
        DnaHosting_Cache::forget(DnaHosting_Cache::keyFor($params));

        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $info = $api->testConnection();

            if (!empty($info['missingAcls'])) {
                return [
                    'success' => false,
                    'error' => 'Connected to WHM ' . $info['version'] . ', but this API token is missing '
                        . 'required privileges: ' . implode(', ', $info['missingAcls'])
                        . '. Add them to the token in WHM under Development > Manage API Tokens.',
                ];
            }

            DnaHosting_Http::note('connection', 'cPanel/WHM'
                . ($info['version'] !== '' ? ' ' . $info['version'] : '')
                . ', ' . $info['accounts'] . ' account(s) visible to this login.'
                . ' Panel type cached for 7 days.');
        } else {
            $api = new DnaHosting_Plesk($params);
            $info = $api->testConnection();

            DnaHosting_Http::note('connection', 'Plesk ' . $info['version']
                . ', packet version ' . $info['protocol']
                . '. Suspensions use reseller status bit 32.'
                . ' Cached for 7 days; Test Connection clears it.');
        }

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Daily disk and bandwidth import.
 *
 * Receives server parameters only: no service id, no username, no product
 * settings. The module has to find its own services and write tblhosting
 * itself.
 *
 * @param array $params
 * @return void
 */
function dnahosting_UsageUpdate(array $params)
{
    try {
        $serverId = isset($params['serverid']) ? (int) $params['serverid'] : 0;
        if ($serverId <= 0) {
            return;
        }

        $services = dnahosting_servicesOnServer($serverId);
        if (empty($services)) {
            return;
        }

        $panel = dnahosting_detectPanel($params);

        if ($panel === 'cpanel') {
            dnahosting_usageCpanel($params, $services);
        } else {
            dnahosting_usagePlesk($params, $services);
        }
    } catch (Exception $e) {
        DnaHosting_Http::note('UsageUpdate', $e->getMessage(), [
            'serverid' => isset($params['serverid']) ? $params['serverid'] : 0,
        ]);
        DnaHosting_Http::alert('usage sync failed for server #'
            . (isset($params['serverid']) ? (int) $params['serverid'] : 0) . ': ' . $e->getMessage());
    }
}

/**
 * Active services on this server that belong to this module.
 *
 * @param int $serverId
 * @return array
 */
function dnahosting_servicesOnServer($serverId)
{
    try {
        return \WHMCS\Database\Capsule::table('tblhosting')
            ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
            ->where('tblhosting.server', $serverId)
            ->where('tblproducts.servertype', 'dnahosting')
            ->whereIn('tblhosting.domainstatus', ['Active', 'Suspended'])
            ->select('tblhosting.id', 'tblhosting.username', 'tblhosting.domain')
            ->get()
            ->all();
    } catch (Exception $e) {
        DnaHosting_Http::note('UsageUpdate', 'Could not list services on server #' . $serverId . ': ' . $e->getMessage());
        return [];
    }
}

/**
 * @param array $params
 * @param array $services
 * @return void
 */
function dnahosting_usageCpanel(array $params, array $services)
{
    $api = new DnaHosting_Cpanel($params);
    $accounts = $api->listAccounts();
    $bandwidth = $api->bandwidthUsage();

    foreach ($services as $service) {
        $row = is_array($service) ? $service : (array) $service;
        $username = isset($row['username']) ? $row['username'] : '';
        if ($username === '' || !isset($accounts[$username])) {
            continue;
        }

        $acct = $accounts[$username];
        $update = [];

        // listaccts reports disk in megabytes, with a unit suffix.
        if (isset($acct['diskused'])) {
            $update['diskusage'] = dnahosting_mb($acct['diskused']);
        }
        if (isset($acct['disklimit'])) {
            $limit = $acct['disklimit'];
            $update['disklimit'] = (strcasecmp((string) $limit, 'unlimited') === 0) ? 0 : dnahosting_mb($limit);
        }
        // showbw reports BYTES for both figures. listaccts also carries a
        // bwlimit but in a different unit; taking it from there and running it
        // through the megabyte parser inflates the limit by a factor of a
        // million, which silently disables every overage rule.
        if (isset($bandwidth[$username])) {
            $bw = $bandwidth[$username];

            if ($bw['used'] !== null) {
                $update['bwusage'] = (int) floor($bw['used'] / 1048576);
            }
            if ($bw['limit'] !== null) {
                $update['bwlimit'] = $bw['limit'] === 'unlimited'
                    ? 0
                    : (int) floor($bw['limit'] / 1048576);
            }
        }

        dnahosting_writeUsage((int) $row['id'], $update);
    }
}

/**
 * @param array $params
 * @param array $services
 * @return void
 */
function dnahosting_usagePlesk(array $params, array $services)
{
    $api = new DnaHosting_Plesk($params);
    $api->ready();

    $domains = [];
    $byDomain = [];
    foreach ($services as $service) {
        $row = is_array($service) ? $service : (array) $service;
        $domain = isset($row['domain']) ? $row['domain'] : '';
        if ($domain === '') {
            continue;
        }

        // Send the domain exactly as stored, but match on a normalised key:
        // WHMCS keeps whatever was typed at order time while Plesk normalises,
        // and a case difference would silently freeze that service's usage
        // figures forever. One domain can also map to more than one service.
        $domains[] = $domain;
        $key = dnahosting_domainKey($domain);
        if (!isset($byDomain[$key])) {
            $byDomain[$key] = [];
        }
        $byDomain[$key][] = (int) $row['id'];
    }

    $usage = $api->usage($domains);
    $matched = [];

    foreach ($usage as $domain => $row) {
        $key = dnahosting_domainKey($domain);
        if (!isset($byDomain[$key])) {
            continue;
        }

        $update = [];
        // Plesk reports bytes, and -1 for unlimited. A missing figure is left
        // out entirely rather than written as zero, which would wipe a good
        // reading whenever the panel omits it.
        if ($row['diskUsed'] !== null) {
            $update['diskusage'] = (int) floor($row['diskUsed'] / 1048576);
        }
        if ($row['diskLimit'] !== null) {
            $update['disklimit'] = $row['diskLimit'] === 'unlimited'
                ? 0
                : (int) floor($row['diskLimit'] / 1048576);
        }
        if ($row['bwUsed'] !== null) {
            $update['bwusage'] = (int) floor($row['bwUsed'] / 1048576);
        }
        if ($row['bwLimit'] !== null) {
            $update['bwlimit'] = $row['bwLimit'] === 'unlimited'
                ? 0
                : (int) floor($row['bwLimit'] / 1048576);
        }

        foreach ($byDomain[$key] as $serviceId) {
            dnahosting_writeUsage($serviceId, $update);
            $matched[$serviceId] = true;
        }
    }

    // A service the panel never reported keeps its old figures, which look
    // valid. Say so rather than letting it quietly go stale.
    $missing = [];
    foreach ($byDomain as $ids) {
        foreach ($ids as $serviceId) {
            if (!isset($matched[$serviceId])) {
                $missing[] = $serviceId;
            }
        }
    }
    if (!empty($missing)) {
        DnaHosting_Http::note('UsageUpdate', 'No usage returned for service(s) #'
            . implode(', #', $missing) . ' - their figures were left unchanged.');
    }
}

/**
 * Normalises a domain so WHMCS's stored spelling and the panel's own can be
 * compared.
 *
 * mb_strtolower, not strtolower: under a Turkish locale the ASCII version maps
 * I to a dotless i and would break exactly the domains this module's audience
 * hosts.
 *
 * @param string $domain
 * @return string
 */
function dnahosting_domainKey($domain)
{
    $domain = rtrim(trim((string) $domain), '.');
    return function_exists('mb_strtolower') ? mb_strtolower($domain, 'UTF-8') : strtolower($domain);
}

/**
 * Parses a cPanel size that may carry a unit suffix, returning megabytes.
 *
 * A bare number from listaccts is already megabytes. Treating it as bytes is a
 * classic way to turn a 512 MB account into 0 MB, which then reads as
 * unlimited.
 *
 * @param mixed $value
 * @return int
 */
function dnahosting_mb($value)
{
    $value = trim((string) $value);
    if ($value === '' || strcasecmp($value, 'unlimited') === 0) {
        return 0;
    }

    if (preg_match('/^([0-9.]+)\s*([KMGT])?/i', $value, $m)) {
        $n = (float) $m[1];
        $unit = isset($m[2]) ? strtoupper($m[2]) : 'M';
        switch ($unit) {
            case 'K':
                return (int) floor($n / 1024);
            case 'G':
                return (int) floor($n * 1024);
            case 'T':
                return (int) floor($n * 1048576);
            default:
                return (int) floor($n);
        }
    }

    return 0;
}

/**
 * @param int   $serviceId
 * @param array $columns
 * @return void
 */
function dnahosting_writeUsage($serviceId, array $columns)
{
    if (empty($columns)) {
        return;
    }

    $allowed = ['diskusage', 'disklimit', 'bwusage', 'bwlimit'];
    $clean = [];
    foreach ($columns as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $clean[$key] = $value;
        }
    }
    if (empty($clean)) {
        return;
    }

    $clean['lastupdate'] = date('Y-m-d H:i:s');

    try {
        \WHMCS\Database\Capsule::table('tblhosting')->where('id', $serviceId)->update($clean);
    } catch (Exception $e) {
        // A single service failing to update must not stop the run.
        DnaHosting_Http::note('UsageUpdate', 'Could not write usage for service #' . $serviceId . ': ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Single sign-on
// ---------------------------------------------------------------------------

/**
 * @param array $params
 * @return array
 */
function dnahosting_ServiceSingleSignOn(array $params)
{
    try {
        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $url = $api->createSession(dnahosting_option($params, 'username'), 'cpaneld', dnahosting_clientIp());
            return ['success' => true, 'redirectTo' => $url];
        }

        // Plesk hands back a session id that has to be POSTed, which cannot be
        // expressed as a redirect. The client area renders a form instead.
        return [
            'success' => false,
            'errorMsg' => 'Plesk does not support redirect-based sign-on. Open the client\'s '
                . 'product page and use the "Log in to Panel" button there.',
        ];
    } catch (Exception $e) {
        return ['success' => false, 'errorMsg' => $e->getMessage()];
    }
}

/**
 * @param array $params
 * @return array
 */
function dnahosting_AdminSingleSignOn(array $params)
{
    try {
        // Server-level: there is no service here, so the reseller's own login
        // is the account being signed in to.
        $panel = dnahosting_detectPanel($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $url = $api->createSession(
                dnahosting_option($params, 'serverusername'),
                'whostmgrd',
                dnahosting_clientIp()
            );
            return ['success' => true, 'redirectTo' => $url];
        }

        $creds = isset($params['serverhostname']) && $params['serverhostname'] !== ''
            ? $params['serverhostname']
            : (isset($params['serverip']) ? $params['serverip'] : '');

        return [
            'success' => false,
            'errorMsg' => 'Plesk does not support redirect-based sign-on for server logins. '
                . 'Open the panel directly at https://' . $creds . ':8443/ and sign in with the '
                . 'reseller credentials configured on this server.',
        ];
    } catch (Exception $e) {
        return ['success' => false, 'errorMsg' => $e->getMessage()];
    }
}

/**
 * The browser's IP, which is what the panel validates the session against.
 *
 * @return string
 */
function dnahosting_clientIp()
{
    // WHMCS exposes this through CurrentUser, not a get_ip() function - that
    // name does not exist in WHMCS and the old guard here always fell through
    // to REMOTE_ADDR, which is the proxy's address on any install behind one.
    if (class_exists('\\WHMCS\\Utility\\Environment\\CurrentUser')) {
        try {
            $ip = (string) \WHMCS\Utility\Environment\CurrentUser::getIP();
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        } catch (Exception $e) {
            // fall through
        }
    }

    $fallback = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return filter_var($fallback, FILTER_VALIDATE_IP) ? $fallback : '';
}

// ---------------------------------------------------------------------------
// Client area
// ---------------------------------------------------------------------------

/**
 * @param array $params
 * @return array
 */
function dnahosting_ClientArea(array $params)
{
    $vars = [
        'panel' => '',
        'domain' => dnahosting_option($params, 'domain'),
        'username' => dnahosting_option($params, 'username'),
        'error' => '',
    ];

    try {
        $panel = dnahosting_panelType($params);
        $vars['panel'] = $panel === 'cpanel' ? 'cPanel' : 'Plesk';
    } catch (Exception $e) {
        // Nothing panel-specific to show, but the page must still render.
        $vars['error'] = 'The control panel is temporarily unreachable.';
    }

    // The login button itself comes from ClientAreaCustomButtonArray - WHMCS
    // renders it and builds its own URL, so the template must not try to.

    return [
        'tabOverviewModuleOutputTemplate' => 'overview.tpl',
        'templateVariables' => $vars,
    ];
}

/**
 * @return array
 */
function dnahosting_ClientAreaCustomButtonArray()
{
    return ['Log in to Panel' => 'panelLogin'];
}

/**
 * Opens the control panel for the customer.
 *
 * cPanel hands back a ready-to-use URL, so this redirects. Plesk hands back a
 * bare session id that has to be POSTed, so this renders a form that submits
 * itself - which also keeps the session id out of the URL, the browser history
 * and any proxy log along the way.
 *
 * @param array $params
 * @return array
 */
function dnahosting_panelLogin(array $params)
{
    try {
        $panel = dnahosting_panelType($params);

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $url = $api->createSession(dnahosting_option($params, 'username'), 'cpaneld', dnahosting_clientIp());

            // outputTemplateFile is the key the custom-function branch of
            // clientarea.php actually reads. tabOverviewModuleOutputTemplate is
            // only honoured for _ClientArea, so returning it here renders
            // nothing at all and the session is minted and thrown away.
            return [
                'outputTemplateFile' => 'sso.tpl',
                'templateVariables' => [
                    'action' => '',
                    'sessionId' => '',
                    'redirectUrl' => $url,
                ],
            ];
        }

        list($api, $ids) = dnahosting_pleskLocate($params);
        $session = $api->createSession(dnahosting_option($params, 'username'), dnahosting_clientIp());

        return [
            'outputTemplateFile' => 'sso.tpl',
            'templateVariables' => [
                'action' => $session['action'],
                'sessionId' => $session['sessionId'],
                'redirectUrl' => '',
            ],
        ];
    } catch (Exception $e) {
        DnaHosting_Http::note('panelLogin', $e->getMessage());
        DnaHosting_Http::alert('panel login failed for service #'
            . (isset($params['serviceid']) ? (int) $params['serviceid'] : 0) . ': ' . $e->getMessage());

        // Return the template, not ['error' => ...]. Service::moduleCall wraps
        // ANY array under 'data' and reports success, so an 'error' key here is
        // never seen and the customer gets an unchanged page with no
        // explanation. sso.tpl's {else} branch already draws the failure box,
        // and passing every variable keeps Smarty from emitting notices.
        return [
            'outputTemplateFile' => 'sso.tpl',
            'templateVariables' => [
                'action' => '',
                'sessionId' => '',
                'redirectUrl' => '',
            ],
        ];
    }
}

/**
 * Client area functions reachable without a button. Called with no arguments.
 *
 * @return array
 */
function dnahosting_ClientAreaAllowedFunctions()
{
    return ['panelLogin'];
}

/**
 * @param array $params
 * @return array
 */
function dnahosting_AdminServicesTabFields(array $params)
{
    $rows = [];

    try {
        $panel = dnahosting_panelType($params);
        $rows['Panel'] = htmlspecialchars($panel === 'cpanel' ? 'cPanel / WHM' : 'Plesk');

        if ($panel === 'cpanel') {
            $api = new DnaHosting_Cpanel($params);
            $summary = $api->accountSummary(dnahosting_option($params, 'username'));
            $rows['Package'] = htmlspecialchars($api->dePrefix(isset($summary['plan']) ? $summary['plan'] : ''));
            $rows['Status'] = !empty($summary['suspended']) ? 'Suspended' : 'Active';
            $rows['IP Address'] = htmlspecialchars(isset($summary['ip']) ? $summary['ip'] : '');
        } else {
            list($api, $ids) = dnahosting_pleskLocate($params, false);
            $rows['Subscription ID'] = htmlspecialchars($ids['webspaceId'] !== '' ? $ids['webspaceId'] : 'not found');
            $rows['Customer ID'] = htmlspecialchars($ids['customerId'] !== '' ? $ids['customerId'] : 'not found');
        }
    } catch (Exception $e) {
        $rows['Panel'] = htmlspecialchars($e->getMessage());
    }

    return $rows;
}
