<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Everything the drivers throw.
 *
 * The message is admin-facing and must never contain a token, a password, or a
 * raw panel response body.
 */
class DnaHosting_Exception extends Exception
{
}
