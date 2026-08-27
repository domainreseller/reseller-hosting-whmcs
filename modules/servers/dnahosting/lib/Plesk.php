<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/Cache.php';

/**
 * Plesk driver, speaking the XML-API as a reseller.
 *
 * The single most important detail in this file: every subscription operation
 * is rooted at the <webspace> operator, never <domain>. WHMCS's own Plesk
 * module suspends, unsuspends and sets the FTP password through <domain>, and
 * Plesk 18.0.80 and newer reject that with error 1014 — which is why those
 * three operations are simply dead in the field today. Its version-negotiation
 * code pins those packets to protocol 1.0.0.0 permanently, so the panel never
 * even gets a chance to accept a newer form.
 *
 * Object model: one WHMCS service maps to one Plesk customer plus one webspace
 * beneath it. That deliberately differs from the bundled module, which shares a
 * customer across all of a client's services and therefore has to be careful
 * never to suspend or delete the wrong thing. One-to-one makes Plesk behave the
 * way cPanel already does.
 */
class DnaHosting_Plesk
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

    /** @var string */
    private $user;

    /** @var string */
    private $secret;

    /** @var string Protocol version used for outgoing packets. */
    private $protocol = '1.6.9.1';

    /** @var bool Use login/password headers instead of the KEY header. */
    private $legacyAuth = false;

    /** @var array|null */
    private $plans = null;

    /** @var bool True once the server has replied with a Plesk XML packet. */
    private $answered = false;

    /** @var bool Set when the API key was refused and we retried with a password. */
    private $triedPassword = false;

    /** @var string */
    private $password = '';

    /** @var string */
    private $cacheKey = '';

    /** @var bool True once protocol and identity are known. */
    private $ready = false;

    /** Plesk status bits: 16 was set by an administrator, 32 by a reseller. */
    const STATUS_ADMIN = 16;
    const STATUS_RESELLER = 32;

    /**
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
        // Plesk create can take minutes: it configures the web server, the FTP
        // user and the vhost before answering. WHMCS's own Plesk module allows
        // 300 seconds for the same work.
        $this->http = new DnaHosting_Http(300);

        $ip = isset($params['serverip']) ? trim($params['serverip']) : '';
        $host = isset($params['serverhostname']) ? trim($params['serverhostname']) : '';

        // Accept either. The bundled Plesk module only ever passes the hostname,
        // which is why configuring it by IP does not work there.
        //
        // When both are present the URL is built from the HOSTNAME and curl is
        // told to resolve that name to the configured IP. Putting the IP in the
        // URL instead would make certificate verification impossible: curl only
        // consults the resolve cache for the host that actually appears in the
        // URL, and a certificate never matches a bare IP.
        $this->connectIp = $ip;
        $this->address = $host !== '' ? $host : $ip;

        $port = isset($params['serverport']) ? (int) $params['serverport'] : 0;
        if ($port <= 0 || $port === 2087 || $port === 2086) {
            $port = !empty($params['serversecure']) ? 8443 : 8880;
        }
        $this->port = $port;

        $this->user = isset($params['serverusername']) ? trim($params['serverusername']) : '';

        $hash = isset($params['serveraccesshash']) ? trim($params['serveraccesshash']) : '';
        // Guard against an install migrated from the bundled module, which
        // stores the PORT NUMBER in the access hash field.
        if ($hash !== '' && preg_match('/^\d{2,5}$/', $hash)) {
            $hash = '';
        }
        $this->cacheKey = DnaHosting_Cache::keyFor($params);
        $this->password = isset($params['serverpassword']) ? (string) $params['serverpassword'] : '';
        $this->secret = $hash !== '' ? $hash : $this->password;
        $this->legacyAuth = ($hash === '');
    }

    // -----------------------------------------------------------------------
    // Transport
    // -----------------------------------------------------------------------

    /**
     * Sends one XML packet.
     *
     * @param string $bodyXml Inner XML, without the <packet> wrapper.
     * @param string $action  Label for the module log.
     * @return SimpleXMLElement
     * @throws DnaHosting_Exception
     */
    public function request($bodyXml, $action = 'request')
    {
        if ($this->address === '' || $this->user === '' || $this->secret === '') {
            throw new DnaHosting_Exception('This server is missing an address, username or API token.');
        }

        $packet = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<packet version="' . $this->protocol . '">' . $bodyXml . '</packet>';

        $url = 'https://' . $this->address . ':' . $this->port . '/enterprise/control/agent.php';

        $headers = ['Content-Type: text/xml', 'HTTP_PRETTY_PRINT: TRUE'];
        if ($this->legacyAuth) {
            // When we fell back after a refused key, the credential to send is
            // the password, not the key we already know is rejected.
            $secret = $this->triedPassword ? $this->password : $this->secret;
            $headers[] = 'HTTP_AUTH_LOGIN: ' . $this->user;
            $headers[] = 'HTTP_AUTH_PASSWD: ' . $secret;
        } else {
            $headers[] = 'KEY: ' . $this->secret;
        }

        $raw = $this->http->post($url, $headers, $packet, [
            'verify'   => $this->shouldVerify(),
            'hostname' => $this->address,
            'ip'       => $this->connectIp,
            'port'     => $this->port,
        ]);

        // The packet carries the customer's plaintext password on create and on
        // password change; it must not be written to the module log verbatim.
        $logPacket = preg_replace(
            ['#<passwd>.*?</passwd>#s', '#(<name>ftp_password</name><value>).*?(</value>)#s'],
            ['<passwd>********</passwd>', '$1********$2'],
            $packet
        );
        DnaHosting_Http::log('Plesk ' . $action, $logPacket, $raw === false ? $this->http->error : $raw, $this->secret);

        if ($raw === false) {
            throw new DnaHosting_Exception($this->http->error);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        libxml_use_internal_errors($previous);

        if ($xml === false || $xml->getName() !== 'packet') {
            throw new DnaHosting_Exception(
                'The server did not return a valid XML response. Check that Plesk is reachable on port '
                . $this->port . '.'
            );
        }

        // A <packet> reply is Plesk's own signature. Record that even when the
        // packet carries an error: whether the credentials work is a separate
        // question from what panel this is, and conflating the two turns
        // "your key was rejected" into the far less useful "not a Plesk server".
        $this->answered = true;

        // The API key was refused. If a password is also configured, fall back
        // to it once - an IP-bound key that was generated elsewhere is a common
        // setup mistake and the password usually still works.
        //
        // Deliberately NOT retried on 1010: that is the rate limiter, and
        // another attempt only digs the hole deeper.
        if (!$this->legacyAuth && !$this->triedPassword && $this->password !== '' && isset($xml->system)) {
            $code = isset($xml->system->errcode) ? (string) $xml->system->errcode : '';
            if ($code === '11003' || $code === '1001') {
                $this->triedPassword = true;
                $this->legacyAuth = true;
                DnaHosting_Http::note('auth', 'API key refused (' . $code . '), retrying with the password');
                return $this->request($bodyXml, $action . '.retry');
            }
        }

        return $xml;
    }

    /**
     * Throws when a result node reports an error.
     *
     * @param SimpleXMLElement $result
     * @throws DnaHosting_Exception
     */
    private function assertOk($result)
    {
        $status = isset($result->status) ? (string) $result->status : '';
        if ($status === 'ok') {
            return;
        }

        $code = isset($result->errcode) ? (string) $result->errcode : '';
        $text = isset($result->errtext) ? (string) $result->errtext : 'Unknown error';

        if ($code === '1014') {
            throw new DnaHosting_Exception(
                'Plesk rejected the request as an unsupported operation (1014): ' . $text,
                (int) $code
            );
        }

        if ($code === '2204') {
            // Plesk accepted the request and failed while configuring the web
            // server itself. Nothing in the packet can fix this.
            throw new DnaHosting_Exception(
                'Plesk could not configure hosting on the server (2204). This is a fault on the '
                . 'panel host, not in the request. Panel message: ' . trim($text),
                (int) $code
            );
        }

        // The code travels on the exception so callers can distinguish "already
        // exists" - which is recoverable - from everything else, which is not.
        throw new DnaHosting_Exception(
            $text . ($code !== '' ? ' (code ' . $code . ')' : ''),
            (int) $code
        );
    }

    /**
     * Finds the first <result> node under a response, whatever the operator.
     *
     * @param SimpleXMLElement $xml
     * @param string           $operator
     * @param string           $verb
     * @return SimpleXMLElement
     * @throws DnaHosting_Exception
     */
    private function result($xml, $operator, $verb)
    {
        // A packet-level failure comes back under <system>, not under the
        // operator, so without this every protocol error reads as the useless
        // "unexpected response".
        if (isset($xml->system)) {
            $code = isset($xml->system->errcode) ? (string) $xml->system->errcode : '';
            $text = isset($xml->system->errtext) ? (string) $xml->system->errtext : 'Unknown error';
            throw new DnaHosting_Exception($this->explainSystemError($code, $text));
        }

        if (!isset($xml->{$operator}) || !isset($xml->{$operator}->{$verb})) {
            throw new DnaHosting_Exception('Unexpected response from Plesk for ' . $operator . '.' . $verb . '.');
        }
        $node = $xml->{$operator}->{$verb};
        if (isset($node->result)) {
            return $node->result;
        }
        return $node;
    }

    /**
     * Whether a failure means the object was already there.
     *
     * @param DnaHosting_Exception $e
     * @return bool
     */
    private function isAlreadyExists(DnaHosting_Exception $e)
    {
        if ((int) $e->getCode() === 1007) {
            return true;
        }
        return stripos($e->getMessage(), 'already exists') !== false;
    }

    /**
     * Turns a packet-level error into something an admin can act on.
     *
     * @param string $code
     * @param string $text
     * @return string
     */
    private function explainSystemError($code, $text)
    {
        switch ($code) {
            case '11003':
                return 'Plesk refused the API key (11003). Plesk binds a secret key to the IP address '
                    . 'it was created for, so a key generated elsewhere will not work from this WHMCS '
                    . 'server. Recreate it on the Plesk box with: plesk bin secret_key --create '
                    . '-ip-address <this WHMCS server\'s outgoing IP> -description "WHMCS". '
                    . 'Alternatively clear the API Token field and use the Username and Password '
                    . 'fields instead.';

            case '1010':
                return 'Plesk is rate-limiting this IP after repeated failed authentication (1010). '
                    . 'Wait a few minutes before retrying, and check that the panel has not banned '
                    . 'this address under Tools & Settings > IP Address Banning.';

            case '1001':
                return 'Plesk rejected these credentials (1001). Check the username, and that the '
                    . 'reseller has the "Ability to use XML API" permission, which is off by default.';
        }

        return 'Plesk rejected the request: ' . $text . ($code !== '' ? ' (code ' . $code . ')' : '');
    }

    /**
     * Whether the server has replied with a Plesk packet at any point, which
     * identifies the panel regardless of whether the credentials were accepted.
     *
     * @return bool
     */
    public function answered()
    {
        return $this->answered;
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

    /**
     * @param string $v
     * @return string
     */
    private function esc($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // -----------------------------------------------------------------------
    // Connection
    // -----------------------------------------------------------------------

    /**
     * @return array{version: string, identity: string}
     * @throws DnaHosting_Exception
     */
    public function testConnection()
    {
        // An explicit connection test is the one place that should always talk
        // to the server, so drop anything remembered first.
        DnaHosting_Cache::forget($this->cacheKey);
        $this->ready = false;

        // The probe itself has to go out at a version every Plesk understands,
        // otherwise version negotiation cannot run on the very servers that
        // need it - and the failure surfaces as "this is not a Plesk server".
        $negotiating = $this->protocol;
        $this->protocol = '1.6.3.0';

        try {
            $xml = $this->request('<server><get_protos/></server>', 'get_protos');
        } catch (DnaHosting_Exception $e) {
            $this->protocol = '1.0.0.0';
            try {
                $xml = $this->request('<server><get_protos/></server>', 'get_protos');
            } catch (DnaHosting_Exception $inner) {
                $this->protocol = $negotiating;
                throw $e;
            }
        }

        $result = $this->result($xml, 'server', 'get_protos');
        $this->assertOk($result);

        $supported = [];
        if (isset($result->protos->proto)) {
            foreach ($result->protos->proto as $p) {
                $supported[] = (string) $p;
            }
        }

        // Pick the highest version we have actually built packets for, not
        // simply the highest the server offers - a newer dialect may have moved
        // nodes we depend on.
        $known = ['1.6.9.1', '1.6.7.0', '1.6.6.0', '1.6.5.0', '1.6.4.0', '1.6.3.2', '1.6.3.0'];
        $chosen = '';
        foreach ($known as $candidate) {
            if (in_array($candidate, $supported, true)) {
                $chosen = $candidate;
                break;
            }
        }
        $this->protocol = $chosen !== '' ? $chosen : '1.6.3.0';

        $version = '';
        try {
            $info = $this->request('<server><get><gen_info/></get></server>', 'server.get');
            $r = $this->result($info, 'server', 'get');
            if (isset($r->gen_info->server_version)) {
                $version = (string) $r->gen_info->server_version;
            }
        } catch (DnaHosting_Exception $e) {
            // Version is informational only.
        }

        DnaHosting_Cache::put($this->cacheKey, [
            'protocol' => $this->protocol,
            'version'  => $version,
        ]);

        $this->ready = true;

        return [
            'version'  => $version,
            'identity' => 'reseller',
            'protocol' => $this->protocol,
        ];
    }

    /**
     * Makes sure the protocol version and identity are known, using whatever
     * was learned last time rather than re-probing on every operation.
     *
     * @return void
     * @throws DnaHosting_Exception
     */
    public function ready()
    {
        if ($this->ready) {
            return;
        }

        $cached = DnaHosting_Cache::get($this->cacheKey);
        if (is_array($cached) && !empty($cached['protocol'])) {
            $this->protocol = $cached['protocol'];
            $this->ready = true;
            return;
        }

        // Nothing remembered - fall back to a full negotiation and store it.
        $this->testConnection();
    }

    /**
     * The suspension bit this module sets and clears.
     *
     * Always the reseller bit. This module exists to drive a reseller account,
     * and asking the panel which one we are cost a request and a confusing
     * "Permission denied" line in the log on every connection. Writing 32 is
     * also correct when the credentials happen to be an administrator's: the
     * bit still suspends the subscription, and unsuspend clears exactly the bit
     * it wrote. What it must never do is clear bit 16, which an administrator
     * set and a reseller has no authority over - and that guard lives in
     * unsuspend(), not here.
     *
     * @return int
     */
    public function suspensionBit()
    {
        return self::STATUS_RESELLER;
    }

    /** @return string */
    public function protocol()
    {
        return $this->protocol;
    }

    // -----------------------------------------------------------------------
    // Plans
    // -----------------------------------------------------------------------

    /**
     * Service plans visible to this login, name => guid.
     *
     * @return array<string,string>
     */
    public function listPlans()
    {
        if ($this->plans !== null) {
            return $this->plans;
        }

        $out = [];
        try {
            $xml = $this->request('<service-plan><get><filter/></get></service-plan>', 'service-plan.get');
            $node = $xml->{'service-plan'}->get;
            foreach ($node->result as $r) {
                if ((string) $r->status !== 'ok') {
                    continue;
                }
                $name = isset($r->name) ? (string) $r->name : '';
                $guid = isset($r->guid) ? (string) $r->guid : '';
                if ($name !== '') {
                    $out[$name] = $guid;
                }
            }
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::note('service-plan.get', 'Could not list service plans: ' . $e->getMessage());
            $out = [];
        }

        $this->plans = $out;
        return $out;
    }

    /**
     * @param string $configured
     * @return array{name: string, guid: string}
     * @throws DnaHosting_Exception
     */
    public function resolvePlan($configured)
    {
        $configured = trim($configured);
        if ($configured === '') {
            throw new DnaHosting_Exception(
                'No Plesk service plan is set on this product. Set it in the product\'s Module Settings.'
            );
        }

        $plans = $this->listPlans();
        if (isset($plans[$configured])) {
            return ['name' => $configured, 'guid' => $plans[$configured]];
        }

        $want = function_exists('mb_strtolower') ? mb_strtolower($configured, 'UTF-8') : strtolower($configured);
        foreach ($plans as $name => $guid) {
            $low = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if ($low === $want) {
                return ['name' => $name, 'guid' => $guid];
            }
        }

        if (empty($plans)) {
            // Could not enumerate; let Plesk validate the name itself.
            return ['name' => $configured, 'guid' => ''];
        }

        throw new DnaHosting_Exception(
            'Service plan "' . $configured . '" was not found on this server. Available: '
            . implode(', ', array_slice(array_keys($plans), 0, 10))
        );
    }

    // -----------------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------------

    /**
     * @param string $externalId
     * @return array|null id/guid/login, or null when absent.
     */
    public function findCustomer($externalId)
    {
        try {
            $xml = $this->request(
                '<customer><get><filter><external-id>' . $this->esc($externalId)
                . '</external-id></filter><dataset><gen_info/></dataset></get></customer>',
                'customer.get'
            );
            $r = $this->result($xml, 'customer', 'get');
            if ((string) $r->status !== 'ok') {
                $code = isset($r->errcode) ? (string) $r->errcode : '';

                // 1013 is "object not found" - a real, trustworthy answer.
                // Anything else means we do not KNOW whether the customer
                // exists, and callers use null to mean "there is none", which
                // would disable the ownership guard built on top of it.
                if ($code !== '' && $code !== '1013') {
                    throw new DnaHosting_Exception(
                        'Could not determine whether a customer exists for ' . $externalId . ': '
                        . (isset($r->errtext) ? (string) $r->errtext : 'unknown error')
                        . ' (code ' . $code . ')',
                        (int) $code
                    );
                }
                return null;
            }
            return [
                'id'    => isset($r->id) ? (string) $r->id : '',
                'guid'  => isset($r->data->gen_info->guid) ? (string) $r->data->gen_info->guid : '',
                'login' => isset($r->data->gen_info->login) ? (string) $r->data->gen_info->login : '',
            ];
        } catch (DnaHosting_Exception $e) {
            // Deliberately NOT swallowed. A transport or protocol failure is
            // not evidence that the customer is absent, and returning null here
            // would silently switch off the ownership check that protects other
            // customers' subscriptions from being suspended or deleted.
            DnaHosting_Http::note('customer.get', 'Customer lookup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * The external-id recorded against a Plesk customer, if any.
     *
     * Used to establish whether a subscription found by domain belongs to this
     * WHMCS install and to this service, when the usual external-id lookup came
     * back empty.
     *
     * @param string $customerId
     * @return string Empty when the customer carries no external-id.
     * @throws DnaHosting_Exception
     */
    public function customerExternalId($customerId)
    {
        if ($customerId === '') {
            return '';
        }

        $xml = $this->request(
            '<customer><get><filter><id>' . $this->esc($customerId) . '</id></filter>'
            . '<dataset><gen_info/></dataset></get></customer>',
            'customer.get.externalid'
        );
        $r = $this->result($xml, 'customer', 'get');
        $this->assertOk($r);

        return isset($r->data->gen_info->{'external-id'})
            ? trim((string) $r->data->gen_info->{'external-id'})
            : '';
    }

    /**
     * @param string $domain
     * @return array|null
     */
    public function findWebspace($domain)
    {
        try {
            $xml = $this->request(
                '<webspace><get><filter><name>' . $this->esc($domain)
                . '</name></filter><dataset><gen_info/></dataset></get></webspace>',
                'webspace.get'
            );
            $r = $this->result($xml, 'webspace', 'get');
            if ((string) $r->status !== 'ok') {
                DnaHosting_Http::note('webspace.get', 'No subscription named ' . $domain
                    . ' (' . (isset($r->errtext) ? (string) $r->errtext : 'no match') . ')');
                return null;
            }
            return [
                'id'      => isset($r->id) ? (string) $r->id : '',
                'ownerId' => isset($r->data->gen_info->{'owner-id'}) ? (string) $r->data->gen_info->{'owner-id'} : '',
                'status'  => isset($r->data->gen_info->status) ? (int) $r->data->gen_info->status : 0,
            ];
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::note('webspace.get', 'Subscription lookup failed for ' . $domain . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The IPv4 address to create new subscriptions on.
     *
     * Asks the panel for its shared addresses and falls back to the IP
     * configured on the WHMCS server record. The fallback matters: reading the
     * address list is an administrator-level call, and this module runs as a
     * reseller.
     *
     * @return string
     */
    private function firstSharedIp()
    {
        try {
            $xml = $this->request('<ip><get/></ip>', 'ip.get');
            $r = $this->result($xml, 'ip', 'get');

            // The node is <ip>, not <ip_info>. Only 'shared' addresses qualify:
            // a dedicated one belongs to somebody in particular, and handing it
            // to a new subscription would take it from them.
            if (isset($r->addresses->ip)) {
                foreach ($r->addresses->ip as $entry) {
                    $addr = isset($entry->ip_address) ? trim((string) $entry->ip_address) : '';
                    $type = isset($entry->type) ? (string) $entry->type : '';
                    if ($addr === '' || $type !== 'shared' || strpos($addr, ':') !== false) {
                        continue;
                    }
                    DnaHosting_Http::note('ip', 'Using the panel\'s shared address ' . $addr);
                    return $addr;
                }
            }
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::note('ip.get',
                'Could not read the panel address list, falling back to the server record: '
                . $e->getMessage());
        }

        // What the admin typed on the server record. Plesk normally hosts from
        // the same address it is reached on, so this is a sound fallback.
        if ($this->connectIp !== '' && strpos($this->connectIp, ':') === false) {
            DnaHosting_Http::note('ip', 'Using the server record IP address ' . $this->connectIp);
            return $this->connectIp;
        }

        // Only a hostname was configured. Resolve it, since Plesk demands an
        // address and refuses to pick one itself.
        if ($this->address !== '' && !filter_var($this->address, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($this->address);
            if ($resolved !== $this->address && filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                DnaHosting_Http::note('ip', 'Resolved ' . $this->address . ' to ' . $resolved
                    . ' for the subscription address');
                return $resolved;
            }
        }

        return '';
    }

    // -----------------------------------------------------------------------
    // Provisioning
    // -----------------------------------------------------------------------

    /**
     * Creates the customer and the webspace.
     *
     * Written to be resumable: WHMCS retries a failed create from its module
     * queue, so each step checks whether the object already exists before
     * creating it. A half-finished create then completes rather than piling up
     * duplicate customers.
     *
     * @param array $a username, password, domain, email, name, plan, externalId
     * @return array{customerId: string, webspaceId: string}
     * @throws DnaHosting_Exception
     */
    public function createAccount(array $a)
    {
        $customer = $this->findCustomer($a['externalId']);

        if ($customer === null) {
            $xml = $this->request(
                '<customer><add><gen_info>'
                . '<cname>' . $this->esc($a['company'] !== '' ? $a['company'] : $a['name']) . '</cname>'
                . '<pname>' . $this->esc($a['name']) . '</pname>'
                . '<login>' . $this->esc($a['username']) . '</login>'
                . '<passwd>' . $this->esc($a['password']) . '</passwd>'
                . '<email>' . $this->esc($a['email']) . '</email>'
                . '<external-id>' . $this->esc($a['externalId']) . '</external-id>'
                . '</gen_info></add></customer>',
                'customer.add'
            );
            $r = $this->result($xml, 'customer', 'add');
            $this->assertOk($r);
            $customer = ['id' => (string) $r->id, 'guid' => '', 'login' => $a['username']];
        }

        $webspace = $this->findWebspace($a['domain']);

        // A subscription for this domain may already exist and belong to
        // somebody else: a former client whose service was removed without
        // terminating, one created by hand, or a duplicate order. Adopting it
        // would report success while the new customer gets nothing, and would
        // arm every later operation - including terminate - against a live site
        // that is not ours.
        if ($webspace !== null && $webspace['ownerId'] !== '' && $webspace['ownerId'] !== $customer['id']) {
            throw new DnaHosting_Exception(
                'A subscription for ' . $a['domain'] . ' already exists on this server and belongs to a '
                . 'different customer (owner id ' . $webspace['ownerId'] . '). Refusing to take it over.'
            );
        }

        if ($webspace === null) {
            // Plesk validates packets against an XSD sequence, so the order
            // here is not cosmetic: name, owner-id, ip_address, htype, status.
            // The address is required inside gen_setup - the panel says so
            // outright with "Element 'ip_address' should be specified in
            // 'gen_setup'" - and is repeated inside vrt_hst, which is what
            // assigns the address to the hosting itself.
            $ip = $this->firstSharedIp();
            if ($ip === '') {
                throw new DnaHosting_Exception(
                    'Could not determine which IP address to create the subscription on. Plesk '
                    . 'requires one and refuses to choose. Reading the panel address list needs '
                    . 'administrator rights, which a reseller does not have, so fill in the '
                    . '"IP Address" field on this server under Setup > Products/Services > Servers '
                    . '- use the IP the reseller\'s sites are hosted on.'
                );
            }
            $ipNode = '<ip_address>' . $this->esc($ip) . '</ip_address>';

            $xml = $this->request(
                '<webspace><add>'
                . '<gen_setup>'
                . '<name>' . $this->esc($a['domain']) . '</name>'
                . '<owner-id>' . $this->esc($customer['id']) . '</owner-id>'
                . $ipNode
                . '<htype>vrt_hst</htype>'
                . '<status>0</status>'
                . '</gen_setup>'
                . '<hosting><vrt_hst>'
                . '<property><name>ftp_login</name><value>' . $this->esc($a['username']) . '</value></property>'
                . '<property><name>ftp_password</name><value>' . $this->esc($a['password']) . '</value></property>'
                . $ipNode
                . '</vrt_hst></hosting>'
                . '<prefs><www>true</www></prefs>'
                . ($a['plan'] !== '' ? '<plan-name>' . $this->esc($a['plan']) . '</plan-name>' : '')
                . '</add></webspace>',
                'webspace.add'
            );

            $r = $this->result($xml, 'webspace', 'add');
            try {
                $this->assertOk($r);
                $webspace = ['id' => (string) $r->id, 'ownerId' => $customer['id'], 'status' => 0];
            } catch (DnaHosting_Exception $e) {
                // Only "already exists" justifies carrying on: it means an
                // earlier attempt got this far and we are resuming it.
                //
                // Any other failure must propagate. Looking the object up and
                // continuing regardless would swallow a real error - Plesk
                // leaves a partial subscription behind when it fails while
                // configuring the web server, so the lookup SUCCEEDS and the
                // failure disappears, handing WHMCS a "success" for a
                // subscription that has no hosting.
                if (!$this->isAlreadyExists($e)) {
                    throw $e;
                }

                $existing = $this->findWebspace($a['domain']);
                if ($existing === null) {
                    throw $e;
                }
                if ($existing['ownerId'] !== '' && $existing['ownerId'] !== $customer['id']) {
                    throw new DnaHosting_Exception(
                        'A subscription for ' . $a['domain'] . ' already exists on this server and belongs '
                        . 'to a different customer.'
                    );
                }
                $webspace = $existing;
            }
        }

        // Plesk can accept the subscription and still leave htype at 'none',
        // which means the domain exists but has no web space, no FTP user and
        // no document root - the customer gets a subscription they cannot use.
        // It happens when the service plan itself carries no hosting, and the
        // plan wins over the htype we asked for.
        $this->ensureHosting($webspace['id']);

        return ['customerId' => $customer['id'], 'webspaceId' => $webspace['id']];
    }

    /**
     * Checks the subscription actually got physical hosting.
     *
     * Only reports - it does not try to repair. WHMCS's own module creates the
     * webspace in a single call and does nothing afterwards, and when hosting
     * fails to attach the cause is on the server (a broken vhost or FastCGI
     * configuration), which no XML packet can fix.
     *
     * What it will not do is stay quiet: a subscription left at htype "none"
     * is a domain with no web space, and reporting that as success bills the
     * customer for a site that does not exist.
     *
     * @param string $webspaceId
     * @return void
     * @throws DnaHosting_Exception
     */
    private function ensureHosting($webspaceId)
    {
        if ($webspaceId === '') {
            return;
        }

        try {
            $xml = $this->request(
                '<webspace><get><filter><id>' . $this->esc($webspaceId) . '</id></filter>'
                . '<dataset><gen_info/></dataset></get></webspace>',
                'webspace.get.htype'
            );
            $r = $this->result($xml, 'webspace', 'get');
            $htype = isset($r->data->gen_info->htype) ? (string) $r->data->gen_info->htype : '';
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::note('hosting', 'Could not confirm the hosting type: ' . $e->getMessage());
            return;
        }

        if ($htype === 'vrt_hst') {
            return;
        }

        throw new DnaHosting_Exception(
            'The subscription was created but Plesk did not attach web hosting to it (htype "'
            . $htype . '"), so the domain has no web space and the service is not usable. '
            . 'Plesk accepted the request and failed while configuring the web server. '
            . 'Check the panel\'s own error log for the subscription - on Windows this is usually '
            . 'a vhost or FastCGI configuration problem on the server itself, not something the '
            . 'provisioning request can correct.'
        );
    }

    /**
     * Suspends the subscription and the customer login.
     *
     * Plesk's status is a bitmask: 16 was set by an administrator, 32 by a
     * reseller. We OR in our own bit rather than overwriting, so an
     * administrator's suspension survives.
     *
     * @param string $webspaceId
     * @param string $customerId
     * @param int    $ourBit
     * @throws DnaHosting_Exception
     */
    public function suspend($webspaceId, $customerId, $ourBit = 32)
    {
        $current = $this->webspaceStatus($webspaceId);
        $new = $current | $ourBit;

        $this->setWebspaceStatus($webspaceId, $new);
        if ($customerId !== '') {
            $this->setCustomerStatus($customerId, $new);
        }
    }

    /**
     * Clears only our own suspension bit.
     *
     * @param string $webspaceId
     * @param string $customerId
     * @param int    $ourBit
     * @throws DnaHosting_Exception
     */
    public function unsuspend($webspaceId, $customerId, $ourBit = 32)
    {
        $current = $this->webspaceStatus($webspaceId);

        // An administrator may clear a reseller's bit as well as their own; a
        // reseller may clear only theirs. Without this an account suspended
        // while the module held reseller credentials becomes permanently
        // un-clearable if those credentials are later swapped for admin ones.
        $clearable = ($ourBit === self::STATUS_ADMIN) ? (self::STATUS_ADMIN | self::STATUS_RESELLER) : $ourBit;
        $residual = $current & ~$clearable;

        if ($residual !== 0) {
            // Writing 0 here is what the bundled module does, and it silently
            // clears an administrator's suspension that a reseller has no
            // authority over. Refuse instead.
            throw new DnaHosting_Exception(
                'This subscription carries a suspension these credentials cannot clear (remaining '
                . 'status ' . $residual . '). An administrator must clear it in Plesk.'
            );
        }

        $this->setWebspaceStatus($webspaceId, 0);
        if ($customerId !== '') {
            $this->setCustomerStatus($customerId, 0);
        }
    }

    /**
     * @param string $webspaceId
     * @return int
     * @throws DnaHosting_Exception
     */
    private function webspaceStatus($webspaceId)
    {
        $xml = $this->request(
            '<webspace><get><filter><id>' . $this->esc($webspaceId)
            . '</id></filter><dataset><gen_info/></dataset></get></webspace>',
            'webspace.get.status'
        );
        $r = $this->result($xml, 'webspace', 'get');
        $this->assertOk($r);
        return isset($r->data->gen_info->status) ? (int) $r->data->gen_info->status : 0;
    }

    /**
     * @param string $webspaceId
     * @param int    $status
     * @throws DnaHosting_Exception
     */
    private function setWebspaceStatus($webspaceId, $status)
    {
        if ($webspaceId === '') {
            throw new DnaHosting_Exception('Cannot change subscription status without a subscription id.');
        }

        $xml = $this->request(
            '<webspace><set><filter><id>' . $this->esc($webspaceId) . '</id></filter>'
            . '<values><gen_setup><status>' . (int) $status . '</status></gen_setup></values>'
            . '</set></webspace>',
            'webspace.set.status'
        );
        $this->assertOk($this->result($xml, 'webspace', 'set'));

        $observed = $this->webspaceStatus($webspaceId);
        if ($observed !== (int) $status) {
            throw new DnaHosting_Exception(
                'Plesk accepted the status change but the subscription still reports status ' . $observed . '.'
            );
        }
    }

    /**
     * @param string $customerId
     * @param int    $status
     */
    private function setCustomerStatus($customerId, $status)
    {
        if ($customerId === '') {
            return;
        }
        try {
            $xml = $this->request(
                '<customer><set><filter><id>' . $this->esc($customerId) . '</id></filter>'
                . '<values><gen_info><status>' . (int) $status . '</status></gen_info></values>'
                . '</set></customer>',
                'customer.set.status'
            );
            $this->assertOk($this->result($xml, 'customer', 'set'));
        } catch (DnaHosting_Exception $e) {
            // The subscription is the thing that serves the site; a customer
            // login that stays enabled is not worth failing the operation over.
            DnaHosting_Http::note('customer.set.status',
                'Subscription status changed but the customer login was not updated: ' . $e->getMessage());
        }
    }

    /**
     * Deletes the subscription, and the customer only when it is safe.
     *
     * @param string $webspaceId
     * @param string $customerId
     * @param string $externalId
     * @throws DnaHosting_Exception
     */
    public function terminate($webspaceId, $customerId, $externalId)
    {
        if ($webspaceId === '') {
            throw new DnaHosting_Exception(
                'Refusing to delete: no Plesk subscription id is recorded for this service. '
                . 'A filter without an id would match every subscription on the server.'
            );
        }

        $xml = $this->request(
            '<webspace><del><filter><id>' . $this->esc($webspaceId) . '</id></filter></del></webspace>',
            'webspace.del'
        );
        $r = $this->result($xml, 'webspace', 'del');
        if ((string) $r->status !== 'ok') {
            $code = isset($r->errcode) ? (string) $r->errcode : '';
            // 1013 is "object not found", which is the end state we want.
            if ($code !== '1013') {
                $this->assertOk($r);
            }
        }

        if ($customerId === '') {
            return;
        }

        // Only remove the customer when it is ours and owns nothing else.
        $owned = $this->countWebspacesOwnedBy($customerId);
        if ($owned > 0) {
            return;
        }

        $customer = $this->findCustomer($externalId);
        if ($customer === null || $customer['id'] !== $customerId) {
            return;
        }

        try {
            $xml = $this->request(
                '<customer><del><filter><id>' . $this->esc($customerId) . '</id></filter></del></customer>',
                'customer.del'
            );
            $this->assertOk($this->result($xml, 'customer', 'del'));
        } catch (DnaHosting_Exception $e) {
            // Leaving an empty customer behind is untidy but harmless; failing
            // the termination would leave WHMCS and the panel disagreeing.
            DnaHosting_Http::alert('subscription deleted but its empty Plesk customer (id ' . $customerId
                . ') could not be removed: ' . $e->getMessage());
        }
    }

    /**
     * @param string $customerId
     * @return int
     */
    private function countWebspacesOwnedBy($customerId)
    {
        try {
            $xml = $this->request(
                '<webspace><get><filter><owner-id>' . $this->esc($customerId)
                . '</owner-id></filter><dataset><gen_info/></dataset></get></webspace>',
                'webspace.get.byowner'
            );
            $node = $xml->webspace->get;
            $count = 0;
            foreach ($node->result as $r) {
                if ((string) $r->status === 'ok') {
                    $count++;
                }
            }
            return $count;
        } catch (DnaHosting_Exception $e) {
            // If we cannot prove the customer is empty, assume it is not.
            DnaHosting_Http::note('webspace.get.byowner',
                'Could not count subscriptions for customer ' . $customerId . ', so it will not be deleted: '
                . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sets both passwords this service has: the panel login and the FTP user.
     *
     * The panel login is set first because that is the credential WHMCS's
     * stored password actually represents. If the FTP half then fails we report
     * success and flag the mismatch, rather than rolling the panel back to a
     * third value that nobody holds.
     *
     * @param string $customerId
     * @param string $webspaceId
     * @param string $password
     * @return bool True when both halves succeeded.
     * @throws DnaHosting_Exception
     */
    public function changePassword($customerId, $webspaceId, $password)
    {
        if ($customerId === '') {
            throw new DnaHosting_Exception('No Plesk customer id is recorded for this service.');
        }

        // <customer><set> with gen_info/passwd. There is no set_password
        // operator in the Plesk XML-API.
        $xml = $this->request(
            '<customer><set><filter><id>' . $this->esc($customerId) . '</id></filter>'
            . '<values><gen_info><passwd>' . $this->esc($password) . '</passwd></gen_info></values>'
            . '</set></customer>',
            'customer.set.passwd'
        );
        $this->assertOk($this->result($xml, 'customer', 'set'));

        if ($webspaceId === '') {
            return false;
        }

        try {
            $xml = $this->request(
                '<webspace><set><filter><id>' . $this->esc($webspaceId) . '</id></filter>'
                . '<values><hosting><vrt_hst>'
                . '<property><name>ftp_password</name><value>' . $this->esc($password) . '</value></property>'
                . '</vrt_hst></hosting></values></set></webspace>',
                'webspace.set.ftp'
            );
            $this->assertOk($this->result($xml, 'webspace', 'set'));
            return true;
        } catch (DnaHosting_Exception $e) {
            DnaHosting_Http::alert('panel password changed but the FTP password was not, for subscription '
                . $webspaceId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $webspaceId
     * @param array  $plan name/guid from resolvePlan()
     * @throws DnaHosting_Exception
     */
    public function changePlan($webspaceId, array $plan)
    {
        if ($webspaceId === '') {
            throw new DnaHosting_Exception('No Plesk subscription id is recorded for this service.');
        }

        $ref = $plan['guid'] !== ''
            ? '<plan-guid>' . $this->esc($plan['guid']) . '</plan-guid>'
            : '<plan-name>' . $this->esc($plan['name']) . '</plan-name>';

        $xml = $this->request(
            '<webspace><switch-subscription><filter><id>' . $this->esc($webspaceId) . '</id></filter>'
            . $ref . '</switch-subscription></webspace>',
            'webspace.switch-subscription'
        );
        $this->assertOk($this->result($xml, 'webspace', 'switch-subscription'));
    }

    /**
     * Disk and bandwidth for a set of domains.
     *
     * Plesk reports bytes throughout and uses -1 for "unlimited". A missing
     * node means the panel did not report the figure at all, which is a
     * different thing from zero, so it is simply left out of the result.
     *
     * @param array $domains
     * @return array<string,array> domain => [diskUsed, diskLimit, bwUsed, bwLimit] in bytes, null = unknown
     */
    public function usage(array $domains)
    {
        $out = [];
        if (empty($domains)) {
            return $out;
        }

        foreach (array_chunk($domains, 50) as $chunk) {
            $filter = '';
            foreach ($chunk as $d) {
                $filter .= '<name>' . $this->esc($d) . '</name>';
            }

            try {
                // The webspace stat dataset was replaced by resource-usage at
                // 1.6.4.0; asking for the wrong one yields nothing at all.
                $modern = version_compare($this->protocol, '1.6.4.0', '>=');
                $dataset = $modern
                    ? '<gen_info/><limits/><resource-usage/>'
                    : '<gen_info/><limits/><stat/>';

                $xml = $this->request(
                    '<webspace><get><filter>' . $filter . '</filter>'
                    . '<dataset>' . $dataset . '</dataset></get></webspace>',
                    'webspace.get.usage'
                );
            } catch (DnaHosting_Exception $e) {
                DnaHosting_Http::note('webspace.get.usage',
                    'Usage batch of ' . count($chunk) . ' subscriptions failed: ' . $e->getMessage());
                continue;
            }

            if (!isset($xml->webspace) || !isset($xml->webspace->get)) {
                DnaHosting_Http::note('webspace.get.usage',
                    'Usage response carried no webspace data for ' . count($chunk) . ' subscriptions');
                continue;
            }

            foreach ($xml->webspace->get->result as $r) {
                if ((string) $r->status !== 'ok') {
                    continue;
                }
                $name = isset($r->data->gen_info->name) ? (string) $r->data->gen_info->name : '';
                if ($name === '') {
                    continue;
                }

                $row = ['diskUsed' => null, 'diskLimit' => null, 'bwUsed' => null, 'bwLimit' => null];

                // real_size lives under gen_info at every protocol version, not
                // under stat.
                if (isset($r->data->gen_info->real_size)) {
                    $row['diskUsed'] = (float) $r->data->gen_info->real_size;
                }

                if ($modern) {
                    if (isset($r->data->{'resource-usage'}->resource)) {
                        foreach ($r->data->{'resource-usage'}->resource as $res) {
                            if ((string) $res->name === 'max_traffic') {
                                $row['bwUsed'] = (float) $res->value;
                                break;
                            }
                        }
                    }
                } elseif (isset($r->data->stat->traffic)) {
                    $row['bwUsed'] = (float) $r->data->stat->traffic;
                }

                // Two shapes exist depending on protocol version:
                //   <limits><limit><name>x</name><value>n</value></limit></limits>
                //   <limits><x>n</x></limits>
                $limits = [];
                if (isset($r->data->limits->limit)) {
                    foreach ($r->data->limits->limit as $limit) {
                        $lname = isset($limit->name) ? (string) $limit->name : '';
                        if ($lname !== '') {
                            $limits[$lname] = isset($limit->value) ? (float) $limit->value : 0.0;
                        }
                    }
                } elseif (isset($r->data->limits)) {
                    foreach ($r->data->limits->children() as $child) {
                        $limits[$child->getName()] = (float) $child;
                    }
                }

                if (isset($limits['disk_space'])) {
                    $row['diskLimit'] = $limits['disk_space'] < 0 ? 'unlimited' : $limits['disk_space'];
                }
                if (isset($limits['max_traffic'])) {
                    $row['bwLimit'] = $limits['max_traffic'] < 0 ? 'unlimited' : $limits['max_traffic'];
                }

                $out[$name] = $row;
            }
        }

        return $out;
    }

    /**
     * A one-shot session for the panel.
     *
     * server.create_session returns a bare session id rather than a URL, so the
     * caller has to POST it to rsession_init.php. That is why this returns the
     * id and the action separately instead of a redirect target.
     *
     * @param string $login
     * @param string $clientIp
     * @return array{sessionId: string, action: string}
     * @throws DnaHosting_Exception
     */
    public function createSession($login, $clientIp)
    {
        if ($login === '') {
            throw new DnaHosting_Exception('No Plesk login is recorded for this service.');
        }

        $xml = $this->request(
            '<server><create_session><login>' . $this->esc($login) . '</login>'
            . '<data><user_ip>' . $this->esc(base64_encode($clientIp)) . '</user_ip>'
            . '<source_server></source_server></data>'
            . '</create_session></server>',
            'server.create_session'
        );

        $r = $this->result($xml, 'server', 'create_session');
        $this->assertOk($r);

        $id = isset($r->id) ? (string) $r->id : '';
        if ($id === '') {
            throw new DnaHosting_Exception('Plesk did not return a session id.');
        }

        return [
            'sessionId' => $id,
            'action'    => 'https://' . $this->address . ':' . $this->port . '/enterprise/rsession_init.php',
        ];
    }
}
