<?php
/**
 * Admin-side cosmetics only. Nothing here may affect provisioning.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Relabels the server form's "Access Hash" field for this module.
 *
 * WHMCS renders a single-line "API Token" input only for the module literally
 * named cpanel - the check is hardcoded in admin/configservers.php - so every
 * other module gets the "Access Hash" textarea regardless of what it actually
 * wants there. This just makes the label say what to paste.
 */
add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    if (!isset($vars['filename']) || $vars['filename'] !== 'configservers') {
        return '';
    }

    return <<<'HTML'
<script>
(function () {
    var LABEL = 'API Token / Access Hash';

    // The server form's module dropdown. Not select[name="type"] on its own:
    // the admin search box at the top of the page uses that same name and comes
    // first in the document.
    function moduleSelects() {
        var found = [];
        ['#inputServerType', '#addType'].forEach(function (sel) {
            var el = document.querySelector(sel);
            if (el) { found.push(el); }
        });
        return found;
    }

    function apply() {
        var label = document.querySelector('span.access-hash');
        if (!label) { return; }

        if (!label.dataset.dnahostingOriginal) {
            label.dataset.dnahostingOriginal = label.textContent;
        }

        var isOurs = moduleSelects().some(function (s) { return s.value === 'dnahosting'; });
        label.textContent = isOurs ? LABEL : label.dataset.dnahostingOriginal;
    }

    function init() {
        apply();
        moduleSelects().forEach(function (s) { s.addEventListener('change', apply); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
HTML;
});
