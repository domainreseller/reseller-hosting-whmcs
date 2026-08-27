{if $error}
    <div class="alert alert-warning" role="alert">{$error}</div>
{/if}

<table class="table table-condensed">
    <tbody>
        <tr>
            <td width="35%"><strong>{$LANG.domain|default:"Domain"}</strong></td>
            <td>{$domain|escape}</td>
        </tr>
        <tr>
            <td><strong>{$LANG.clientareahostingusername|default:"Username"}</strong></td>
            <td>{$username|escape}</td>
        </tr>
        {if $panel}
            <tr>
                <td><strong>{$LANG.controlpanel|default:"Control Panel"}</strong></td>
                <td>{$panel|escape}</td>
            </tr>
        {/if}
    </tbody>
</table>

{* The login button is rendered by WHMCS from ClientAreaCustomButtonArray. *}
