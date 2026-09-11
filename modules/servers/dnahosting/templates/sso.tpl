{*
    Rendered by dnahosting_panelLogin through outputTemplateFile, which replaces
    the whole client-area page rather than a fragment of it - so this file has
    to stand on its own.
*}
<div class="container" style="max-width:640px;margin:60px auto;text-align:center">

    {if $redirectUrl}
        {* cPanel returns a complete one-time URL. *}
        <h2>{$LANG.clientarealoggingin|default:"Signing you in..."}</h2>
        <p><a href="{$redirectUrl|escape}" class="btn btn-primary" rel="noopener">
            {$LANG.continue|default:"Continue"}
        </a></p>
        <script>
            window.location.href = {$redirectUrl|json_encode nofilter};
        </script>

    {elseif $action}
        {*
            Plesk returns a bare session id that has to be POSTed. Sending it as
            a form field rather than in a query string keeps it out of the URL
            bar, the browser history and any proxy log in between.
        *}
        <h2>{$LANG.clientarealoggingin|default:"Signing you in..."}</h2>
        <form method="post" action="{$action|escape}" id="dnahostingSso">
            <input type="hidden" name="PLESKSESSID" value="{$sessionId|escape}">
            <noscript>
                <button type="submit" class="btn btn-primary">
                    {$LANG.continue|default:"Continue"}
                </button>
            </noscript>
        </form>
        <script>
            document.getElementById('dnahostingSso').submit();
        </script>

    {else}
        <div class="alert alert-warning">
            {$LANG.clientareaerror|default:"Could not open a control panel session."}
        </div>
        <p><a href="clientarea.php" class="btn btn-default">
            {$LANG.clientareanavservices|default:"Back to services"}
        </a></p>
    {/if}

</div>
