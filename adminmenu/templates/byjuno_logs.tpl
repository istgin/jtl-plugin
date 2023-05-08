<style>
    table.table-logs-byjuno {
        width: 100%;
        border-collapse: collapse;
    }

    table.table-logs-byjuno td {
        padding: 3px;
        border: 1px solid #DDDDDD;

    }
</style>
<script>
    $(document).ready(function () {
        $("#search_byjuno").click(function () {
            var search = jQuery('#search_byjuno_str').val();
            if (search !== "") {
                var url = '{$postUrl}&byjuno_search=' + search;
                $("#byjuno_result_search").load(url, function () {
                    $("#byjuno_result").hide();
                    $("#byjuno_result_full").hide();
                    $("#byjuno_result_search").show();
                });
            }
        });
        $("#search_byjuno_clear").click(function () {
            $("#byjuno_result").show();
            $("#byjuno_result_full").hide();
            $("#byjuno_result_search").hide();
        });

    });

    function byjuno_load(load) {
        $("#byjuno_result_full").load(load, function () {
            $("#byjuno_result").hide();
            $("#byjuno_result_search").hide();
            $("#byjuno_result_full").show();
        });
    }
</script>

<input value="" id="search_byjuno_str"/>
<button type="button" id="search_byjuno">Search</button>
<button type="button" id="search_byjuno_clear">Clear</button>
<br><br>
<div id="byjuno_result_search"></div>
<div id="byjuno_result_full"></div>
<div id="byjuno_result">
    <table class="table-logs-byjuno">
        <tr>
            <td>Firstname</td>
            <td>Lastname</td>
            <td>IP</td>
            <td>Status</td>
            <td>Date</td>
            <td>Request ID</td>
            <td>Type</td>
        </tr>
        {foreach from=$byjunoOrders item=log}
            <tr>
                <td>{$log->firstname|escape}</td>
                <td>{$log->lastname|escape}</td>
                <td>{$log->ip|escape}</td>
                <td>{if ($log->status === '0')}Error{else}{$log->status|escape}{/if}</td>
                <td>{$log->creation_date|escape}</td>
                <td>{$log->request_id|escape}</td>
                <td><a style="text-decoration: underline"
                       href="javascript:byjuno_load('{$postUrl}&byjuno_viewxml={$log->byjuno_id}')">{$log->type|escape}</a>
                </td>
            </tr>
        {/foreach}
        {if !$byjunoOrders}
            <tr>
                <td colspan="7" style="padding: 10px">
                    No results found
                </td>
            </tr>
        {/if}
    </table>
</div>