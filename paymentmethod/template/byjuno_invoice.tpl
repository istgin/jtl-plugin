{if !empty($byjuno_error)}
    <div id="byjuno_error" class="show">{$byjuno_error}</div>
    <br>
{/if}

{if !empty($byjuno_iframe)}
    <input type="hidden" name="byjuno_form" value="1">
    <table border="1">
        <tr>
            <td>gender:</td>
            <td>
                <select name="byjuno_gender" id="byjuno_gender">
                    <option value="Mr.">Mr</option>
                    <option value="Mrs.">Mrs</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>birthday:</td>
            <td>
                <input type="text" name="byjuno_year" style="width: 40px"
                       value="1" size="40">
                <input type="text" name="byjuno_month"style="width: 40px"
                       value="2}" size="40">
                <input type="text" name="byjuno_day"style="width: 40px"
                       value="3" size="40">
            </td>
        </tr>
    </table>
{/if}