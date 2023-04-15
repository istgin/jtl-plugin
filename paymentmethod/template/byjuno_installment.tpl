{if !empty($error_msg)}
    <div id="byjuno_error" class="show">{$error_msg}</div><br>
{/if}

{if !empty($byjuno_iframe)}
    {if !empty($byjuno_logo)}
        <!--<img src="{$byjuno_logo}"><br>-->
    {/if}
    <script>
        getElem = function (name) {
            return document.getElementById(name);
        }
    </script>
    {$byjuno_iframe}
{/if}
YYYY