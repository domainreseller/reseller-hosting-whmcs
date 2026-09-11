<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Minimal HTTP client shared by both panel drivers.
 *
 * Two deliberate choices here, both learned from the bundled WHMCS modules:
 *
 *  - Redirects are NEVER followed. Both panels are addressed with a credential
 *    in a request header, and curl re-sends headers to the redirect target, so
 *    following one leaks the reseller token to whatever host answered. A 3xx is
 *    also the correct diagnostic: it usually means a proxy or the panel's login
 *    page, and a followed redirect turns POST into GET and silently drops the
 *    request body.
 *
 *  - Connecting by IP (which is how this module is meant to be configured)
 *    guarantees a certificate name mismatch, so verification is off by default
 *    but can be turned on by filling in the server's Hostname field.
 */
class DnaHosting_Http
{
    /** @var string */
    public $error = '';

    /** @var int */
    public $statusCode = 0;

    /** @var int */
    private $timeout;

    /**
     * @param int $timeout Total request timeout in seconds.
     */
    public function __construct($timeout = 60)
    {
        $this->timeout = (int) $timeout;
    }

    /**
     * Adjusts the total timeout for the next request.
     *
     * Account creation on a busy panel legitimately takes minutes - WHMCS gives
     * its own cPanel module 400 seconds - while a read that has not answered in
     * half a minute is not going to. One value cannot serve both: too short
     * strands half-created accounts, too long stalls the usage cron behind a
     * dead server.
     *
     * @param int $seconds
     * @return void
     */
    public function setTimeout($seconds)
    {
        $this->timeout = (int) $seconds;
    }

    /**
     * @param string $url
     * @param array  $headers
     * @param mixed  $body     Array (form encoded) or string (sent verbatim).
     * @param array  $options  verify => bool, hostname => string, ip => string, port => int
     * @return string|false Response body, or false on transport failure.
     */
    public function post($url, array $headers = [], $body = null, array $options = [])
    {
        return $this->request('POST', $url, $headers, $body, $options);
    }

    /**
     * @param string $url
     * @param array  $headers
     * @param array  $options
     * @return string|false
     */
    public function get($url, array $headers = [], array $options = [])
    {
        return $this->request('GET', $url, $headers, null, $options);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $headers
     * @param mixed  $body
     * @param array  $options
     * @return string|false
     */
    private function request($method, $url, array $headers, $body, array $options)
    {
        $this->error = '';
        $this->statusCode = 0;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
            }
        }

        // Verification is only meaningful when we have a name to verify against.
        // With a hostname present we still connect to the IP, but tell curl to
        // treat it as that hostname so the certificate can actually be checked.
        $verify = !empty($options['verify']);
        if ($verify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if (!empty($options['hostname']) && !empty($options['ip']) && !empty($options['port'])
                && defined('CURLOPT_RESOLVE')) {
                curl_setopt($ch, CURLOPT_RESOLVE, [
                    $options['hostname'] . ':' . (int) $options['port'] . ':' . $options['ip'],
                ]);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $this->statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $this->error = 'Connection failed: ' . curl_error($ch);
            curl_close($ch);
            return false;
        }

        if ($this->statusCode >= 300 && $this->statusCode < 400) {
            $this->error = 'Server returned HTTP ' . $this->statusCode
                . ' (redirect). Check the address, port and SSL setting for this server.';
            curl_close($ch);
            return false;
        }

        // A 401/403 body can still be valid JSON or XML, so without this the
        // caller would happily parse an authentication failure as a result.
        if ($this->statusCode >= 400) {
            $this->error = 'Server returned HTTP ' . $this->statusCode
                . ($this->statusCode === 401 || $this->statusCode === 403
                    ? '. The username or API token was rejected.'
                    : '. Check the address and port for this server.');

            // The panel usually explains itself in the body - a brute-force
            // block, a missing privilege, an expired token - and without it
            // every 4xx looks identical to whoever has to fix it.
            $detail = self::summarise($response);
            if ($detail !== '') {
                $this->error .= ' Server said: ' . $detail;
            }

            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $response;
    }

    /**
     * Reduces a response body to one short line of explanation.
     *
     * HTML is stripped because an error page is mostly markup and the useful
     * sentence is buried inside it.
     *
     * @param string $body
     * @return string
     */
    private static function summarise($body)
    {
        $body = (string) $body;
        if (trim($body) === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['cpanelresult']['error']) && is_string($decoded['cpanelresult']['error'])) {
                return trim($decoded['cpanelresult']['error']);
            }
            // WHM's own failure shape.
            if (isset($decoded['metadata']['reason']) && is_string($decoded['metadata']['reason'])) {
                return trim($decoded['metadata']['reason']);
            }
            foreach (array('error', 'message', 'reason', 'statusmsg') as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
                    return trim($decoded[$key]);
                }
            }
        }

        // Replace tags with a space rather than removing them, otherwise
        // adjacent block elements run their words together.
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<[^>]*>/', ' ', $body))));
        if ($text === '') {
            return '';
        }

        return strlen($text) > 200 ? substr($text, 0, 200) . '...' : $text;
    }

    /**
     * Writes a request/response pair to the module log.
     *
     * Note this only records anything when Module Debug Mode is switched on in
     * WHMCS - logModuleCall returns immediately otherwise. Anything an admin
     * must be able to see without that setting has to go through alert().
     *
     * Masking is literal string replacement of the values passed in, so the
     * secret has to be given exactly as it appears on the wire.
     *
     * @param string $action
     * @param mixed  $request
     * @param mixed  $response
     * @param string $secret
     * @return void
     */
    public static function log($action, $request, $response, $secret = '')
    {
        if (!function_exists('logModuleCall')) {
            return;
        }
        $mask = [];
        if ($secret !== '') {
            $mask[] = $secret;
            // Whitespace is stripped from the token before it goes on the wire,
            // so the stripped form is what actually needs masking.
            $stripped = preg_replace('/\s+/', '', $secret);
            if ($stripped !== $secret && $stripped !== '') {
                $mask[] = $stripped;
            }
        }
        // A decodable JSON response is handed over as an array so WHMCS renders
        // it with print_r - a one-line JSON body with nested metadata/data is
        // near unreadable in the log viewer.
        //
        // Anything that does NOT parse is left exactly as it arrived: when a
        // response is malformed, the raw text IS the diagnostic, and rewriting
        // it would throw away the only clue. Plesk's XML also falls through
        // here untouched, and it already comes back pretty-printed.
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }

        // The fifth argument must stay an empty STRING, not an empty array.
        // WHMCS runs print_r() over an array and stores the result in arrdata,
        // and the module log's list view replaces the response with arrdata
        // whenever arrdata is non-empty - so passing [] hides every response
        // behind the text "Array()".
        logModuleCall('dnahosting', $action, $request, $response, '', $mask);
    }

    /**
     * Records a decision rather than a request: why a lookup came back empty,
     * which branch was taken, what was skipped.
     *
     * Without these the module log shows the traffic but not the reasoning, and
     * a support case turns into guesswork.
     *
     * @param string $action
     * @param string $message
     * @param mixed  $detail
     * @return void
     */
    public static function note($action, $message, $detail = '')
    {
        // Prefixed so the module log makes it obvious at a glance which rows
        // are traffic and which are the module explaining itself.
        self::log('note: ' . $action, $detail === '' ? $message : $detail, $message);
    }

    /**
     * Records something an admin needs to see whether or not Module Debug Mode
     * is on. This goes to the activity log, which is written unconditionally.
     *
     * @param string $message
     * @return void
     */
    public static function alert($message)
    {
        if (function_exists('logActivity')) {
            logActivity('dnahosting: ' . $message);
        }
    }
}
