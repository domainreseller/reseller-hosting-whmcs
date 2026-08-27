<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/Http.php';

/**
 * cPanel/WHM driver, speaking WHM API 1 as a reseller.
 *
 * Every method either returns its result or throws DnaHosting_Exception. The
 * literal string 'success' that WHMCS wants lives in dnahosting.php, not here.
 */
class DnaHosting_Cpanel
{
    /** @var array */
    private $params;

    /** @var DnaHosting_Http */
    private $http;

    /** @var string Host used in the URL: the hostname when we have one. */
    private $address;

    /** @var string Configured IP, used to pin the connection. */
    private $connectIp = '';

    /** @var int */
    private $port;

    /** @var bool */
    private $secure = true;

    /** @var string */
    private $scheme = 'https';

    /** @var string */
    private $user;

    /** @var string */
    private $secret;

    /** @var array|null Cached listpkgs result. */
    private $packages = null;

    /**
     * Calls that carry nothing secret and may safely travel as GET.
     *
     * @var array
     */
    private static $readOnlyCalls = [
        'version', 'listaccts', 'listpkgs', 'myprivs', 'accountsummary',
        'showbw', 'systemloadavg', 'get_tweaksetting', 'applist', 'listresellers',
    ];

    /**
     * @param array $params WHMCS module params.
     */
    public function __construct(array $params)
    {
        $this->params = $params;
        $this->http = new DnaHosting_Http(60);

        $ip = isset($params['serverip']) ? trim($params['serverip']) : '';
        $host = isset($params['serverhostname']) ? trim($params['serverhostname']) : '';
        // When both are present the URL is built from the HOSTNAME and curl is
        // told to resolve that name to the configured IP. Putting the IP in the
        // URL instead would make certificate verification impossible: curl only
        // consults the resolve cache for the host that actually appears in the
        // URL, and a certificate never matches a bare IP.
        $this->connectIp = $ip;
        $this->address = $host !== '' ? $host : $ip;

        $this->secure = !empty($params['serversecure']);

        $port = isset($params['serverport']) ? (int) $params['serverport'] : 0;
        if ($port <= 0 || $port === 8443 || $port === 8880) {
            // 8443/8880 are Plesk ports; if they were left in the field they are
            // certainly not what WHM is listening on.
            $port = $this->secure ? 2087 : 2086;
        }
        $this->port = $port;

        // 2086 speaks plaintext HTTP. Sending https to it fails with a TLS
        // error that says nothing about the real problem.
        $this->scheme = ($this->secure || $port === 2087) ? 'https' : 'http';

        $this->user = isset($params['serverusername']) ? trim($params['serverusername']) : '';

        // The access hash field holds the API token. Fall back to the password
        // so an install using password auth still works.
        $hash = isset($params['serveraccesshash']) ? trim($params['serveraccesshash']) : '';
        $this->secret = $hash !== '' ? $hash : (isset($params['serverpassword']) ? $params['serverpassword'] : '');
    }

    /**
     * Calls a WHM API 1 function.
     *
     * @param string $function
     * @param array  $args
     * @return array Decoded JSON payload.
     * @throws DnaHosting_Exception
     */
    public function call($function, array $args = [])
    {
        if ($this->address === '' || $this->user === '' || $this->secret === '') {
            throw new DnaHosting_Exception(
                'This server is missing an address, username or API token.'
            );
        }

        $args['api.version'] = 1;

        // Read-only calls go out as GET, writes as POST.
        //
        // createacct and passwd carry the customer's plaintext password, and a
        // GET would write it into WHM's access log, any proxy in between, and
        // the shell history of whoever reads it back - so those stay POST. But
        // some firewalls in front of WHM reject POSTs outright, and a plain
        // read has nothing worth hiding, so sending reads as GET keeps the
        // module working behind them.
        $isRead = in_array($function, self::$readOnlyCalls, true);

        $url = $this->scheme . '://' . $this->address . ':' . $this->port . '/json-api/' . $function;
        if ($isRead) {
            $url .= '?' . http_build_query($args);
        }

        // A pasted access hash contains newlines; leaving them in produces a
        // malformed header and an opaque 401.
        $token = preg_replace('/\s+/', '', $this->secret);

        $headers = [
            'Authorization: WHM ' . $this->user . ':' . $token,
        ];

        // Reads that have not answered in 30s are dead; writes may legitimately
        // take minutes on a busy panel.
        $this->http->setTimeout($isRead ? 30 : 400);

        $transportOptions = [
            'verify'   => $this->shouldVerify(),
            'hostname' => $this->address,
            'ip'       => $this->connectIp,
            'port'     => $this->port,
        ];

        $raw = $isRead
            ? $this->http->get($url, $headers, $transportOptions)
            : $this->http->post($url, $headers, $args, $transportOptions);

        $logArgs = $args;
        foreach (['password', 'pass'] as $secretArg) {
            if (isset($logArgs[$secretArg])) {
                $logArgs[$secretArg] = '********';
            }
        }
        DnaHosting_Http::log('WHM ' . $function, $logArgs, $raw === false ? $this->http->error : $raw, $token);

        if ($raw === false) {
            throw new DnaHosting_Exception($this->http->error);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // A proxy or the panel's own login page answers with HTML and a 200.
            throw new DnaHosting_Exception(
                'The server did not return a valid API response. Check that WHM is reachable on port '
                . $this->port . ' and that the API token is valid.'
            );
        }

        $this->assertOk($data, $function);

        return $data;
    }

    /**
     * WHM API 1 reports status in metadata; some older calls use result[0].
     *
     * @param array  $data
     * @param string $function
     * @throws DnaHosting_Exception
     */
    private function assertOk(array $data, $function)
    {
        // WHM answers a refused or unauthorised call with a cpanelresult
        // envelope that carries none of the status keys below. Without this
        // branch a permission-denied createacct falls straight through the
        // checks and is reported to WHMCS as a success - the service goes
        // Active, the welcome mail goes out, and no account exists.
        if (isset($data['cpanelresult'])) {
            $err = isset($data['cpanelresult']['error']) ? $data['cpanelresult']['error'] : '';
            if (is_array($err)) {
                $err = implode('. ', $err);
            }
            if ((string) $err === '' && isset($data['cpanelresult']['data']['reason'])) {
                $err = $data['cpanelresult']['data']['reason'];
            }
            $err = (string) $err !== '' ? (string) $err : 'Access denied';

            // A cpanelresult envelope means the request was answered by the
            // cPanel user API rather than WHM - WHM API 1 always replies with a
            // metadata envelope. The port is right, the credentials reached the
            // server, and the server decided this login is not entitled to WHM.
            throw new DnaHosting_Exception(
                'WHM refused this login (' . $err . '). The server answered with a cPanel user-level '
                . 'response instead of a WHM one, which means this account has no WHM access. '
                . 'Check that the username is a reseller account, and that the API token was created '
                . 'in WHM under Development > Manage API Tokens - a token made in the cPanel '
                . 'interface does not work here.'
            );
        }

        if (isset($data['metadata']['result'])) {
            if ((int) $data['metadata']['result'] === 1) {
                return;
            }
            $reason = isset($data['metadata']['reason']) ? $data['metadata']['reason'] : 'Unknown error';
            throw new DnaHosting_Exception($reason);
        }

        if (isset($data['result'][0]['status'])) {
            if ((int) $data['result'][0]['status'] === 1) {
                return;
            }
            $reason = isset($data['result'][0]['statusmsg']) ? $data['result'][0]['statusmsg'] : 'Unknown error';
            throw new DnaHosting_Exception($reason);
        }

        if (isset($data['status'])) {
            if ((int) $data['status'] === 1) {
                return;
            }
            $reason = isset($data['statusmsg']) ? $data['statusmsg'] : 'Unknown error';
            throw new DnaHosting_Exception($reason);
        }

        // A few read-only calls genuinely answer without a status field. Every
        // other function - and every call that writes - must present one, or we
        // have no idea whether it worked and must not assume it did.
        $statuslessReads = ['version', 'listpkgs', 'myprivs', 'showbw', 'listaccts', 'accountsummary'];
        if (!in_array($function, $statuslessReads, true)) {
            throw new DnaHosting_Exception(
                'The server returned an unrecognised response for ' . $function . '.'
            );
        }
    }

    /**
     * Whether the certificate can be meaningfully verified.
     *
     * Verification needs a NAME to check the certificate against. WHMCS copies
     * whatever the admin typed into both the hostname and IP fields, so a
     * server configured by IP arrives here with the hostname field holding that
     * same IP - and no certificate matches a bare IP. Turning verification on
     * in that case does not add security, it just makes every call fail.
     *
     * @return bool
     */
    private function shouldVerify()
    {
        $host = isset($this->params['serverhostname']) ? trim($this->params['serverhostname']) : '';
        $ip = isset($this->params['serverip']) ? trim($this->params['serverip']) : '';

        if ($host === '' || $host === $ip) {
            return false;
        }

        // A hostname field that actually contains an address is not a name.
        return filter_var($host, FILTER_VALIDATE_IP) === false;
    }

    // -----------------------------------------------------------------------
    // Operations
    // -----------------------------------------------------------------------

    /**
     * @return array{version: string, identity: string, missingAcls: array}
     * @throws DnaHosting_Exception
     */
    public function testConnection()
    {
        // listaccts is the connectivity test, not version. A reseller token is
        // expected to hold list-accts, whereas `version` can require a
        // privilege a reseller does not have - which turns a perfectly good
        // token into an opaque 403 before anything useful is tried.
        $probe = $this->call('listaccts', ['want' => 'user']);
        $accts = isset($probe['data']['acct']) ? $probe['data']['acct'] : (isset($probe['acct']) ? $probe['acct'] : []);
        $accountCount = is_array($accts) ? count($accts) : 0;

        // Version is informational, so it must never fail the connection.
        $v = '';
        try {
            $version = $this->call('version');
            $v = isset($version['version']) ? $version['version'] : '';
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::note('version', 'Version unavailable to this token: ' . $e->getMessage());
        }

        // The account is always a reseller - that is what this module is for.
        // myprivs is still worth calling, but only to report which privileges
        // the token is missing, which is the difference between "it failed" and
        // "add create-acct to the token".
        $missing = [];
        try {
            $privs = $this->call('myprivs');

            // WHM wraps the privilege map in a list: data.privileges[0]. Reading
            // data.privileges directly yields the wrapper, every lookup misses,
            // and a fully privileged token gets reported as missing everything.
            $data = [];
            if (isset($privs['data']['privileges'][0]) && is_array($privs['data']['privileges'][0])) {
                $data = $privs['data']['privileges'][0];
            } elseif (isset($privs['data']['privileges']) && is_array($privs['data']['privileges'])) {
                $data = $privs['data']['privileges'];
            } elseif (isset($privs['data']['privs']) && is_array($privs['data']['privs'])) {
                $data = $privs['data']['privs'];
            }

            if (empty($data)) {
                DnaHosting_Http::note('myprivs', 'No privilege map in the response; skipping the ACL check.');
            } elseif (empty($data['all'])) {
                $required = [
                    'create-acct', 'suspend-acct', 'kill-acct', 'passwd',
                    'upgrade-account', 'list-accts', 'list-pkgs',
                    'create-user-session', 'show-bandwidth',
                    // Needed to re-apply per-product disk/bandwidth overrides
                    // after a package change; without them an upgrade silently
                    // drops the account back to the plan default.
                    'quota', 'limit-bandwidth',
                ];
                foreach ($required as $acl) {
                    if (empty($data[$acl])) {
                        $missing[] = $acl;
                    }
                }
            }
        } catch (DnaHosting_Exception $e) {
            // Not fatal; it only enriches the report.
            DnaHosting_Http::note('myprivs', 'Could not read this token\'s privileges: ' . $e->getMessage());
        }

        return [
            'version' => $v,
            'identity' => 'reseller',
            'missingAcls' => $missing,
            'accounts' => $accountCount,
        ];
    }

    /**
     * Package names as the panel knows them, mapped to a friendly label.
     *
     * WHM prefixes a reseller's own packages with "<reseller>_", which is why
     * the admin sees "Gold" but the API needs "acme_Gold".
     *
     * @return array<string,string>
     */
    public function listPackages()
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $out = [];
        try {
            $res = $this->call('listpkgs');
            $pkgs = isset($res['package']) ? $res['package'] : (isset($res['data']['pkg']) ? $res['data']['pkg'] : []);
            foreach ($pkgs as $pkg) {
                $name = isset($pkg['name']) ? $pkg['name'] : (isset($pkg['package']) ? $pkg['package'] : '');
                if ($name === '') {
                    continue;
                }
                $out[$name] = $this->dePrefix($name);
            }
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::note('listpkgs', 'Could not list packages: ' . $e->getMessage());
            $out = [];
        }

        $this->packages = $out;
        return $out;
    }

    /**
     * Strips only a leading "<reseller>_" for display.
     *
     * Deliberately not explode('_', $name)[1], which is what the bundled module
     * does and which mangles any package name containing an underscore.
     *
     * @param string $name
     * @return string
     */
    public function dePrefix($name)
    {
        $prefix = $this->user . '_';
        if ($this->user !== '' && strpos($name, $prefix) === 0) {
            return substr($name, strlen($prefix));
        }
        return $name;
    }

    /**
     * Resolves the package name an admin typed into what WHM will accept.
     *
     * @param string $configured
     * @return string
     * @throws DnaHosting_Exception
     */
    public function resolvePackage($configured)
    {
        $configured = trim($configured);
        if ($configured === '') {
            throw new DnaHosting_Exception(
                'No cPanel package name is set on this product. Set it in the product\'s Module Settings.'
            );
        }

        $available = $this->listPackages();
        if (empty($available)) {
            // Could not list packages (permission, or an old WHM). Send what we
            // were given and let WHM decide.
            DnaHosting_Http::note('resolvePackage',
                'Package list unavailable, sending "' . $configured . '" to WHM unresolved');
            return $configured;
        }

        $prefixed = $this->user . '_' . $configured;

        // A reseller's own package is the more likely intent, so it wins.
        if (isset($available[$prefixed])) {
            return $prefixed;
        }
        if (isset($available[$configured])) {
            return $configured;
        }

        // Case-insensitive retry. mb_strtolower rather than strcasecmp: under a
        // Turkish locale strcasecmp maps I to i-dotless and breaks plan names.
        $wantA = $this->lower($prefixed);
        $wantB = $this->lower($configured);
        foreach (array_keys($available) as $name) {
            $low = $this->lower($name);
            if ($low === $wantA || $low === $wantB) {
                return $name;
            }
        }

        throw new DnaHosting_Exception(
            'Package "' . $configured . '" was not found on this server. Available: '
            . implode(', ', array_slice(array_values($available), 0, 10))
        );
    }

    /**
     * @param string $s
     * @return string
     */
    private function lower($s)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }

    /**
     * @param array $account username, password, domain, email, plan, ip
     * @return array{ip: string} The address the account was allocated.
     * @throws DnaHosting_Exception
     */
    public function createAccount(array $account)
    {
        $args = [
            'username'    => $account['username'],
            'password'    => $account['password'],
            'domain'      => $account['domain'],
            'contactemail'=> $account['email'],
            'plan'        => $account['plan'],
            'savepkg'     => 0,
            'reseller'    => 0,
        ];

        if (!empty($account['ip'])) {
            $args['ip'] = 'y';
        }
        if (isset($account['quota']) && $account['quota'] !== null) {
            $args['quota'] = (int) $account['quota'];
        }
        if (isset($account['bwlimit']) && $account['bwlimit'] !== null) {
            $args['bwlimit'] = (int) $account['bwlimit'];
        }

        try {
            $result = $this->call('createacct', $args);
        } catch (DnaHosting_Exception $e) {
            // WHM may have created the account and then failed to answer in
            // time, or a previous attempt may already have finished. Check the
            // panel before deciding this is a failure, otherwise every retry
            // from the module queue is permanently doomed and the service sits
            // in Pending while the account exists on the server.
            $summary = null;
            try {
                $summary = $this->accountSummary($account['username']);
            } catch (DnaHosting_Exception $lookup) {
                throw $e; // account genuinely absent - report the original error
            }

            $existingDomain = isset($summary['domain']) ? strtolower((string) $summary['domain']) : '';
            if ($existingDomain !== strtolower($account['domain'])) {
                throw new DnaHosting_Exception(
                    'The username "' . $account['username'] . '" already exists on this server and '
                    . 'is serving ' . ($existingDomain !== '' ? $existingDomain : 'another domain')
                    . ', not ' . $account['domain'] . '. Choose a different username for this service.'
                );
            }

            DnaHosting_Http::alert('createacct reported "' . $e->getMessage() . '" but the account '
                . $account['username'] . ' exists and serves ' . $account['domain']
                . '; treating the creation as complete.');

            return ['ip' => isset($summary['ip']) ? (string) $summary['ip'] : ''];
        }

        // The response carries the IP the account was actually given, which is
        // the only place a dedicated allocation is reported. Handing it back
        // lets the caller record it on the service rather than billing for an
        // address nobody can see.
        $out = ['ip' => ''];
        foreach ([['data', 'ip'], ['result', 0, 'options', 'ip']] as $path) {
            $node = $result;
            foreach ($path as $step) {
                if (!is_array($node) || !isset($node[$step])) {
                    $node = null;
                    break;
                }
                $node = $node[$step];
            }
            if (is_string($node) && $node !== '') {
                $out['ip'] = $node;
                break;
            }
        }

        return $out;
    }

    /**
     * @param string $username
     * @param string $reason
     * @throws DnaHosting_Exception
     */
    public function suspendAccount($username, $reason = '')
    {
        $this->call('suspendacct', ['user' => $username, 'reason' => $reason]);
        $this->assertSuspendState($username, true);
    }

    /**
     * @param string $username
     * @throws DnaHosting_Exception
     */
    public function unsuspendAccount($username)
    {
        $this->call('unsuspendacct', ['user' => $username]);
        $this->assertSuspendState($username, false);
    }

    /**
     * WHM can answer "ok" without the state having changed, so the result is
     * read back rather than trusted.
     *
     * @param string $username
     * @param bool   $shouldBeSuspended
     * @throws DnaHosting_Exception
     */
    private function assertSuspendState($username, $shouldBeSuspended)
    {
        try {
            $summary = $this->accountSummary($username);
        } catch (DnaHosting_Exception $e) {
            // Read-back is a safety net, not a hard requirement.
            DnaHosting_Http::note('accountsummary',
                'Could not confirm the new state of ' . $username . ': ' . $e->getMessage());
            return;
        }

        $isSuspended = !empty($summary['suspended']);
        if ($isSuspended !== (bool) $shouldBeSuspended) {
            throw new DnaHosting_Exception(
                'The server accepted the request but the account is still '
                . ($isSuspended ? 'suspended' : 'active') . '.'
            );
        }
    }

    /**
     * @param string $username
     * @param bool   $keepDns
     * @throws DnaHosting_Exception
     */
    public function terminateAccount($username, $keepDns = false)
    {
        try {
            $this->call('removeacct', ['user' => $username, 'keepdns' => $keepDns ? 1 : 0]);
        } catch (DnaHosting_Exception $e) {
            // Already gone is the desired end state.
            if (stripos($e->getMessage(), 'does not exist') !== false
                || stripos($e->getMessage(), 'not found') !== false) {
                DnaHosting_Http::note('removeacct',
                    'Account ' . $username . ' was already absent: ' . $e->getMessage());
                return;
            }
            throw $e;
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @throws DnaHosting_Exception
     */
    public function changePassword($username, $password)
    {
        $this->call('passwd', ['user' => $username, 'password' => $password]);
    }

    /**
     * @param string $username
     * @param string $plan
     * @param int|null $quotaMb
     * @param int|null $bwMb
     * @return array Descriptions of any overrides that could not be applied.
     * @throws DnaHosting_Exception
     */
    public function changePackage($username, $plan, $quotaMb = null, $bwMb = null)
    {
        $this->call('changepackage', ['user' => $username, 'pkg' => $plan]);

        // changepackage resets the account to the new plan's limits, so any
        // per-product override has to be re-applied afterwards - otherwise an
        // upgrade silently shrinks the account back to the plan default while
        // the customer is billed for the override.
        //
        // Failures are collected and returned rather than swallowed: the plan
        // change itself succeeded and must not be rolled back, but the customer
        // is not getting what they pay for and somebody has to know.
        $failed = [];

        if ($quotaMb !== null) {
            try {
                $this->call('editquota', ['user' => $username, 'quota' => (int) $quotaMb]);
            } catch (DnaHosting_Exception $e) {
                $failed[] = 'disk quota (' . $e->getMessage() . ')';
            }
        }

        if ($bwMb !== null) {
            try {
                $this->call('limitbw', ['user' => $username, 'bwlimit' => (int) $bwMb]);
            } catch (DnaHosting_Exception $e) {
                $failed[] = 'bandwidth limit (' . $e->getMessage() . ')';
            }
        }

        return $failed;
    }

    /**
     * @param string $username
     * @return array
     * @throws DnaHosting_Exception
     */
    public function accountSummary($username)
    {
        $res = $this->call('accountsummary', ['user' => $username]);
        if (isset($res['data']['acct'][0])) {
            return $res['data']['acct'][0];
        }
        if (isset($res['acct'][0])) {
            return $res['acct'][0];
        }
        throw new DnaHosting_Exception('Account "' . $username . '" was not found on this server.');
    }

    /**
     * All accounts owned by this reseller, keyed by username.
     *
     * @return array<string,array>
     */
    public function listAccounts()
    {
        $res = $this->call('listaccts', [
            'want' => 'domain,user,plan,ip,suspended,email,owner,diskused,disklimit',
        ]);
        $accts = isset($res['data']['acct']) ? $res['data']['acct'] : (isset($res['acct']) ? $res['acct'] : []);

        $out = [];
        foreach ($accts as $a) {
            if (empty($a['user'])) {
                continue;
            }
            $out[$a['user']] = $a;
        }
        return $out;
    }

    /**
     * Bandwidth used this month, in bytes, keyed by username.
     *
     * Note listaccts reports disk in megabyte strings while showbw reports
     * bytes — the two are not interchangeable and conflating them is a common
     * source of wrong usage figures.
     *
     * @return array<string,float>
     */
    public function bandwidthUsage()
    {
        $out = [];
        try {
            $res = $this->call('showbw');
            $accts = isset($res['bandwidth'][0]['acct']) ? $res['bandwidth'][0]['acct'] : [];
            if (!$accts && isset($res['data']['acct'])) {
                $accts = $res['data']['acct'];
            }
            foreach ($accts as $a) {
                if (empty($a['user'])) {
                    continue;
                }
                $row = ['used' => null, 'limit' => null];

                if (isset($a['totalbytes'])) {
                    $row['used'] = (float) $a['totalbytes'];
                }

                // showbw is the only place the limit is reported in bytes.
                // listaccts also has a bwlimit, but in a different unit, and
                // mixing the two produces a limit off by a factor of a million.
                if (isset($a['limit'])) {
                    $limit = $a['limit'];
                    $row['limit'] = (strcasecmp((string) $limit, 'unlimited') === 0)
                        ? 'unlimited'
                        : (float) $limit;
                }

                $out[$a['user']] = $row;
            }
        } catch (DnaHosting_Exception $e) {
            // Bandwidth is optional; disk figures are still worth writing.
            // The limit is deliberately left untouched rather than guessed.
            DnaHosting_Http::alert('bandwidth usage unavailable on this server: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * A one-shot login URL for the customer.
     *
     * @param string $username
     * @param string $service cpaneld|webmaild|whostmgrd
     * @param string $clientIp
     * @return string
     * @throws DnaHosting_Exception
     */
    public function createSession($username, $service = 'cpaneld', $clientIp = '')
    {
        // No remote_ip argument: it is not part of WHM API 1's create_user_session
        // and WHMCS's own module does not send it either. WHM binds the session
        // to the address it sees, which is this server's outbound IP.
        $args = ['user' => $username, 'service' => $service];

        $res = $this->call('create_user_session', $args);
        $url = isset($res['data']['url']) ? $res['data']['url'] : '';
        if ($url === '') {
            throw new DnaHosting_Exception('The server did not return a login URL.');
        }

        // WHM always mints an https URL on its SSL port. On a non-SSL server the
        // scheme AND the port have to come down together - rewriting only the
        // scheme produces http://host:2083, which nothing is listening on.
        if (empty($this->params['serversecure'])) {
            $url = str_replace(
                ['https:', ':2087', ':2083', ':2096'],
                ['http:', ':2086', ':2082', ':2095'],
                $url
            );
        }

        // The URL is handed to a browser, so refuse anything that is not a
        // plain http(s) address - a panel that answered with something else is
        // not somewhere we should be sending a customer.
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])
            || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new DnaHosting_Exception('The server returned an unusable login URL.');
        }

        return $url;
    }
}
