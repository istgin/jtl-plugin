{if !empty($byjuno_error)}
    <div id="byjuno_error" class="show alert alert-danger">{$byjuno_error}</div>
    <br>
{/if}


{if ($byjuno_tmx == true)}
    <script>
        window.addEventListener('load', function () {
            window.setTimeout(function() {
                const s = document.createElement('script');
                s.type = 'text/javascript';
                s.src = 'https://h.online-metrix.net/fp/tags.js?org_id=={$byjuno_tmx_org_id}&session_id={$byjuno_tmx_session_id}&pageid=checkout';
                s.async = true;
                document.body.appendChild(s);
            }, 0);
        });
    </script>
    <link rel="preload" href="https://h.online-metrix.net/fp/tags.js?org_id={$byjuno_tmx_org_id}&session_id={$byjuno_tmx_session_id}&pageid=checkout" as="script">
    <noscript>
        <iframe style="width: 100px; height: 100px; border: 0; position: absolute; top: -5000px;" src="https://h.online-metrix.net/tags?org_id={$byjuno_tmx_org_id}&session_id={$byjuno_tmx_session_id}&pageid=checkout"></iframe>
    </noscript>
{/if}

<input type="hidden" name="byjuno_form" value="1">
<div id="panel-form">
    <div class="row ">
        <div class="col col-12">
            <div class="h2">
                {$byjuno_invoice}
            </div>
        </div>
        {if (count($selected_payments_invoice) > 1)}
            <div class="col col-12">
                <hr>
            </div>
            <div class="col col-md-4 col-12">
                <div class="h3">{$l_select_payment_plan}</div>
            </div>
            <div class="col  col-md-8">
                <div class="form-row">
                    <div class="col col-12">
                        {foreach from=$selected_payments_invoice item=s_payment}
                            <input type="radio" name="byjuno_payment"
                                   value="{$s_payment.id}" {if $s_payment.id == $selected_payment_invoice} checked="checked"{/if}>
                            &nbsp;{$s_payment.name}
                            <br/>
                        {/foreach}
                    </div>
                </div>
            </div>
            <div class="col-md-9 form-control-comment">
            </div>
        {/if}
        {if (count($selected_payments_invoice) == 1)}
            <input type="hidden" name="byjuno_payment" value="{$selected_payments_invoice[0].id}">
        {/if}

        {if ($byjuno_allowpostal == 1)}
            <div class="col col-12">
                <hr>
            </div>
            <div class="col col-md-4 col-12">
                <div class="h3">{$l_select_invoice_delivery_method}</div>
            </div>
            <div class="col col-md-8">
                <div class="form-row">
                    <div class="col col-12">
                        <input type="radio" name="byjuno_send_method" {if $invoice_send == "email"} checked="checked"{/if}
                               value="email"> &nbsp;{$l_by_email}: {$email}<br/>
                        <input type="radio"
                               name="byjuno_send_method" {if $invoice_send == "postal"} checked="checked"{/if}
                               value="postal"> &nbsp;{$l_by_post}: {$address}<br/>
                    </div>
                </div>
            </div>
            <div class="col-md-9 form-control-comment">
            </div>
        {/if}

        {if ($byjuno_gender_show == 1)}
            <div class="col col-12">
                <hr>
            </div>
            <div class="col col-md-4 col-12">
                <div class="h3">{$l_gender}</div>
            </div>
            <div class="col col-md-8">
                <div class="form-row">
                    <div class="col col-12">
                        <div class="form-group" role="group">
                            <div class="d-flex flex-column-reverse">
                                <select name="byjuno_gender" id="byjuno_gender" class="form-control">
                                    <option value="1" {if $sl_gender == 1} selected="selected"{/if}>{$l_male}</option>
                                    <option value="2" {if $sl_gender == 2} selected="selected"{/if}>{$l_female}</option>
                                </select>
                                <label id="form-group-label-643cf816233ae" for="billing_address-country"
                                       class="col-form-label pt-0">
                                    {$l_gender}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
        {if ($byjuno_birthday_show == 1)}
            <div class="col col-12">
                <hr>
            </div>
            <div class="col col-md-4 col-12">
                <div class="h3">{$l_date_of_birth}</div>
            </div>
            <div class="col col-md-8">
                <div class="form-row">
                    <div class="col col-md-4 col-12">
                        <div id="643cf81623160" aria-labelledby="form-group-label-643cf81623160" class="form-group "
                             role="group">
                            <div class="d-flex flex-column-reverse">
                                <select id="byjuno_day" name="byjuno_day" class="form-control">
                                    {foreach from=$days item=day}
                                        <option value="{$day|escape:'html':'UTF-8'}" {if $sl_day == $day} selected="selected"{/if}>{$day|escape:'html':'UTF-8'}
                                            &nbsp;&nbsp;
                                        </option>
                                    {/foreach}
                                </select>
                                <label id="form-group-label-643cf81623160" for="street" class="col-form-label pt-0">
                                    {$l_day}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col col-md-4 col-12">
                        <div id="643cf816231dc" aria-labelledby="form-group-label-643cf816231dc" class="form-group "
                             role="group">
                            <div class="d-flex flex-column-reverse">
                                <select id="byjuno_month" name="byjuno_month" class="form-control">
                                    {foreach from=$months key=k item=month}
                                        <option value="{$month|escape:'html':'UTF-8'}" {if $sl_month == $month} selected="selected"{/if}>{$month}
                                            &nbsp;
                                        </option>
                                    {/foreach}
                                </select>
                                <label id="form-group-label-643cf816231dc" for="streetnumber"
                                       class="col-form-label pt-0">
                                    {$l_month}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col col-md-4 col-12">
                        <div id="643cf816231dc" aria-labelledby="form-group-label-643cf816231dc" class="form-group "
                             role="group">
                            <div class="d-flex flex-column-reverse">
                                <select id="byjuno_year" name="byjuno_year" class="form-control">
                                    {foreach from=$years item=year}
                                        <option value="{$year|escape:'html':'UTF-8'}" {if $sl_year == $year} selected="selected"{/if}>{$year|escape:'html':'UTF-8'}
                                            &nbsp;&nbsp;
                                        </option>
                                    {/foreach}
                                </select>
                                <label id="form-group-label-643cf816231dc" for="streetnumber"
                                       class="col-form-label pt-0">
                                    {$l_year}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
    <div class="form-fields">
        <div class="form-group byjuno_toc">
            <input type="checkbox" value="terms_conditions" name="byjyno_terms" id="byjyno_terms"
                   style="display: inline-block"/> &nbsp;
            <a href="{$toc_url_invoice}" target="_blank"
               style="font-weight: bold; text-decoration: underline">{$l_i_agree_with_terms_and_conditions}</a>
        </div>
    </div>
</div>