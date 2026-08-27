<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Small cache for the two facts about a server that rarely change: which panel
 * it runs, and which XML-API protocol version it speaks.
 *
 * Without this every single operation re-negotiates the protocol and re-probes
 * the identity before doing any actual work, which turns a suspend into nine
 * round trips.
 *
 * Storage is WHMCS's own settings table rather than a table of our own, so the
 * module still installs by copying a directory. Entries are keyed by a hash of
 * the connection details, so changing the host, port, user or token
 * invalidates them automatically. Nothing secret is stored: the token only
 * contributes to the key as a hash.
 */
class DnaHosting_Cache
{
    /** Seven days. A server's panel and protocol change about as often as never. */
    const TTL = 604800;

    /** @var array Per-request memo, in front of the persistent store. */
    private static $memo = [];

    /**
     * @param array $params WHMCS module params.
     * @return string
     */
    public static function keyFor(array $params)
    {
        $parts = [
            isset($params['serverip']) ? $params['serverip'] : '',
            isset($params['serverhostname']) ? $params['serverhostname'] : '',
            isset($params['serverport']) ? $params['serverport'] : '',
            isset($params['serverusername']) ? $params['serverusername'] : '',
            isset($params['serveraccesshash']) ? substr(sha1((string) $params['serveraccesshash']), 0, 12) : '',
        ];
        return 'dnahosting_' . substr(sha1(implode('|', $parts)), 0, 20);
    }

    /**
     * @param string $key
     * @return array|null
     */
    public static function get($key)
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $value = null;
        try {
            if (class_exists('\WHMCS\Config\Setting')) {
                $raw = \WHMCS\Config\Setting::getValue($key);
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && isset($decoded['expires']) && $decoded['expires'] > time()) {
                        $value = isset($decoded['data']) ? $decoded['data'] : null;
                    }
                }
            }
        } catch (Exception $e) {
            $value = null;
        }

        self::$memo[$key] = $value;
        return $value;
    }

    /**
     * @param string $key
     * @param array  $data
     * @return void
     */
    public static function put($key, array $data)
    {
        self::$memo[$key] = $data;

        try {
            if (class_exists('\WHMCS\Config\Setting')) {
                \WHMCS\Config\Setting::setValue($key, json_encode([
                    'expires' => time() + self::TTL,
                    'data' => $data,
                ]));
            }
        } catch (Exception $e) {
            // A cache that cannot be written is slow, not broken.
        }
    }

    /**
     * @param string $key
     * @return void
     */
    public static function forget($key)
    {
        unset(self::$memo[$key]);

        try {
            if (class_exists('\WHMCS\Config\Setting')) {
                \WHMCS\Config\Setting::deleteValue($key);
            }
        } catch (Exception $e) {
            // Nothing to do.
        }
    }
}
