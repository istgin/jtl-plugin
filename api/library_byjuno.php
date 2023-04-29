<?php

use JTL\Checkout\Lieferadresse;
use JTL\Language\LanguageHelper;
use JTL\Plugin\Helper;
use JTL\Session\Frontend;

function byjunoGetClientIp() {
    $ipaddress = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if(!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if(!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if(!empty($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if(!empty($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}

function mapPaymentMethodToSpecs($method){
    $method = strtolower(str_replace(" ", "", $method));
    $IntrumMapping = array(
        'cashondelivery'	=> 'CASH-ON-DELIVERY',
        'checkmo'			=> 'INVOICE',
        'banktransfer'		=> 'PRE-PAY',
        'ccsave'			=> 'CREDIT-CARD',
        'paypal'			=> 'E-PAYMENT',
        'bankwire'			=> 'INVOICE',
        'bill'			    => 'INVOICE',
        'invoice'			=> 'INVOICE',
        'invoicepayment'	=> 'INVOICE',
        'visa'	            => 'CREDIT-CARD',
        'maestro'	        => 'CREDIT-CARD',
        'mastercard'	    => 'CREDIT-CARD',
		'bezahlenperrechnung' => 'INVOICE'
    );

    if(strpos($method, 'paypal')!==false){
        if(array_key_exists('paypal', $IntrumMapping)){
            return $IntrumMapping['paypal'];
        }
    }
    if(strpos($method, 'invoice')!==false){
        return $IntrumMapping['invoice'];
    }
    if(strpos($method, 'maestro')!==false){
        return $IntrumMapping['maestro'];
    }
    if(strpos($method, 'mastercard')!==false){
        return $IntrumMapping['mastercard'];
    }
    if(strpos($method, 'visa')!==false){
        return $IntrumMapping['visa'];
    }
    if(strpos($method, 'rechnung')!==false){
        return $IntrumMapping['bezahlenperrechnung'];
    }
    if(array_key_exists($method, $IntrumMapping)){

        return $IntrumMapping[$method];
    }
    return $method;
}

function mapMethod($method) {
    if ($method == 'installment_3') {
        return "INSTALLMENT";
    } else if ($method == 'installment_36') {
        return "INSTALLMENT";
    } else if ($method == 'installment_12') {
        return "INSTALLMENT";
    } else if ($method == 'installment_24') {
        return "INSTALLMENT";
    } else if ($method == 'installment_4x12') {
        return "INSTALLMENT";
    } else if ($method == 'installment_4x10') {
        return "INSTALLMENT";
    } else if ($method == 'single_invoice') {
        return "INVOICE";
    } else {
        return "INVOICE";
    }
}

function mapRepayment($type) {

    if ($type == 'installment_3') {
        return "10";
    } else if ($type == 'installment_36') {
        return "11";
    } else if ($type == 'installment_12') {
        return "8";
    } else if ($type == 'installment_24') {
        return "9";
    } else if ($type == 'installment_4x12') {
        return "1";
    } else if ($type == 'installment_4x10') {
        return "2";
    } else if ($type == 'single_invoice') {
        return "3";
    } else {
        return "4";
    }
}

function byjunoMapLang($lang) {
    if ($lang == 'ger') {
        $lang = 'DE';
    }
    if ($lang == 'fra') {
        $lang = 'FR';
    }
    if ($lang == 'ita') {
        $lang = 'IT';
    }
    if ($lang == 'eng') {
        $lang = 'EN';
    }
    return $lang;
}

/**
 * @param JTL\Customer\Customer $customer
 * @param JTL\Cart\Cart $cart
 * @param JTL\Checkout\Lieferadresse
 * @param $msgtype
 * @return ByjunoRequest
 * @throws Exception
 */
function CreateJTLCDPShopRequest($customer, $cart, $address, $msgtype) {

    $config = Helper::getPluginById('byjuno')->getConfig();
    $request = new ByjunoRequest();
    $request->setClientId($config->getOption("byjuno_client_id")->value);
    $request->setUserID($config->getOption("byjuno_user_id")->value);
    $request->setPassword($config->getOption("byjuno_password")->value);
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail($config->getOption("byjuno_tech_email")->value);
    } catch (Exception $e) {
    }
    $custId = uniqid("guest_");
    if ($customer->nRegistriert == 1) {
        $custId =  uniqid("registered_");
    }
    $langIso = LanguageHelper::getIsoFromLangID($customer->kSprache);
    $lang = 'DE';
    if (!empty($langIso->cISO)) {
        $lang = byjunoMapLang($langIso->cISO);
    }
    $request->setRequestId($custId);
    $request->setCustomerReference($custId);
    $request->setFirstName(html_entity_decode(($customer->cVorname), ENT_COMPAT, 'UTF-8'));
    $request->setLastName(html_entity_decode($customer->cNachname, ENT_COMPAT, 'UTF-8'));
    $request->setFirstLine(html_entity_decode(trim($customer->cStrasse), ENT_COMPAT, 'UTF-8'));
    $request->setHouseNumber(html_entity_decode(trim($customer->cHausnummer), ENT_COMPAT, 'UTF-8'));
    $request->setCountryCode(strtoupper($customer->cLand));
    $request->setPostCode($customer->cPLZ);
    $request->setTown(html_entity_decode($customer->cOrt, ENT_COMPAT, 'UTF-8'));
    $request->setLanguage($lang);
    $request->setTelephonePrivate($customer->cTel);
    $request->setMobile($customer->cMobil);
    $request->setEmail($customer->cMail);

    if (!empty($customer->cFirma)) {
        $request->setCompanyName1($customer->cFirma);
    }

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = 'NO';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $cart->gibGesamtsummeWaren(true);
    $request->setExtraInfo($extraInfo);

    $currency = $cart->Waehrung ?? Frontend::getCurrency();
    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $currency->getCode();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = byjunoGetClientIp();
    $request->setExtraInfo($extraInfo);

    /*
    if (Configuration::get("INTRUM_ENABLETMX") == 'true' && Configuration::get("INTRUM_TMXORGID") != '' && !empty($cookie->intrumId)) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $cookie->intrumId;
        $request->setExtraInfo($extraInfo);
    }
*/

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = html_entity_decode($address->cVorname, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = html_entity_decode($address->cNachname, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = html_entity_decode(trim($address->cStrasse), ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = html_entity_decode(trim($address->cHausnummer), ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = strtoupper($address->cLand);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $address->cPLZ;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = html_entity_decode($address->cOrt, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'MESSAGETYPESPEC';
    $extraInfo["Value"] = $msgtype;//'ORDERREQUEST';
    $request->setExtraInfo($extraInfo);

    if (!empty($address->cFirma)) {
        $request->setDeliveryCompanyName1($address->cFirma);
    }

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Byjuno JTL 5.2 module 1.0.0';
    $request->setExtraInfo($extraInfo);

    return $request;

}


/**
 * @param JTL\Checkout\Bestellung $order
 * @param $msgtype
 * @param $repayment
 * @param $invoiceDelivery
 * @param $riskOwner
 * @param $transaction
 * @param $selected_gender
 * @param $selected_birthday
 * @return ByjunoRequest
 * @throws Exception
 */
function CreateJTLOrderShopRequest($order, $msgType, $repayment, $invoiceDelivery, $riskOwner, $transaction, $selected_gender = "", $selected_birthday = "", $orderClosed = "NO") {

    /* @var $config JTL\Plugin\Data\Config */
    $config = Helper::getPluginById('byjuno')->getConfig();
    $request = new ByjunoRequest();
    $request->setClientId($config->getOption("byjuno_client_id")->value);
    $request->setUserID($config->getOption("byjuno_user_id")->value);
    $request->setPassword($config->getOption("byjuno_password")->value);
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail($config->getOption("byjuno_tech_email")->value);
    } catch (Exception $e) {
    }
    $langIso = LanguageHelper::getIsoFromLangID($order->kSprache);
    $lang = 'DE';
    if (!empty($langIso->cISO)) {
        $lang = byjunoMapLang($langIso->cISO);
    }
    $custId = uniqid("customer_");
    $request->setRequestId($custId);
    $request->setCustomerReference($custId);
    $request->setFirstName(html_entity_decode($order->oRechnungsadresse->cVorname, ENT_COMPAT, 'UTF-8'));
    $request->setLastName(html_entity_decode($order->oRechnungsadresse->cNachname, ENT_COMPAT, 'UTF-8'));
    $request->setFirstLine(html_entity_decode(trim($order->oRechnungsadresse->cStrasse), ENT_COMPAT, 'UTF-8'));
    $request->setHouseNumber(html_entity_decode(trim($order->oRechnungsadresse->cHausnummer), ENT_COMPAT, 'UTF-8'));
    $request->setCountryCode(strtoupper($order->oRechnungsadresse->cLand));
    $request->setPostCode($order->oRechnungsadresse->cPLZ);
    $request->setTown(html_entity_decode($order->oRechnungsadresse->cOrt, ENT_COMPAT, 'UTF-8'));
    $request->setLanguage($lang);
    $request->setTelephonePrivate($order->oRechnungsadresse->cTel);
    $request->setMobile($order->oRechnungsadresse->cMobil);
    $request->setEmail($order->oRechnungsadresse->cMail);

    if (!empty($order->oRechnungsadresse->cFirma)) {
        $request->setCompanyName1($order->oRechnungsadresse->cFirma);
    }
    /**
     * ask var if possible
     */
    /*
    if (!empty($invoice_address->vat_number)) {
        $request->setCompanyVatId($invoice_address->vat_number);
    }
    */
    if ($selected_gender != "") {
        $request->setGender($selected_gender);
    }
    if ($selected_birthday != "") {
        $request->setDateOfBirth($selected_birthday);
    }

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = $orderClosed;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $order->fGesamtsumme;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $order->Waehrung->getCode();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = byjunoGetClientIp();
    $request->setExtraInfo($extraInfo);

    /*
    if (Configuration::get("INTRUM_ENABLETMX") == 'true' && Configuration::get("INTRUM_TMXORGID") != '' && !empty($cookie->intrumId)) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $cookie->intrumId;
        $request->setExtraInfo($extraInfo);
    }
*/

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = html_entity_decode($order->Lieferadresse->cVorname, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = html_entity_decode($order->Lieferadresse->cNachname, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = html_entity_decode(trim($order->Lieferadresse->cStrasse), ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = html_entity_decode(trim($order->Lieferadresse->cHausnummer), ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = strtoupper($order->Lieferadresse->cLand);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $order->Lieferadresse->cPLZ;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = html_entity_decode($order->Lieferadresse->cOrt, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    if ($msgType != "") {
        $extraInfo["Name"] = 'MESSAGETYPESPEC';
        $extraInfo["Value"] = $msgType;//'ORDERREQUEST';
        $request->setExtraInfo($extraInfo);
    }

    if (!empty($order->Lieferadresse->cFirma)) {
        $request->setDeliveryCompanyName1($order->Lieferadresse->cFirma);
    }

    $extraInfo["Name"] = 'PAYMENTMETHOD';
    $extraInfo["Value"] = mapMethod($repayment);
    $request->setExtraInfo($extraInfo);

    if ($repayment != "") {
        $extraInfo["Name"] = 'REPAYMENTTYPE';
        $extraInfo["Value"] = mapRepayment($repayment);
        $request->setExtraInfo($extraInfo);
    }

    if ($invoiceDelivery == 'postal') {
        $extraInfo["Name"] = 'PAPER_INVOICE';
        $extraInfo["Value"] = 'YES';
        $request->setExtraInfo($extraInfo);
    }

    if ($riskOwner != "") {
        $extraInfo["Name"] = 'RISKOWNER';
        $extraInfo["Value"] = $riskOwner;
        $request->setExtraInfo($extraInfo);
    }

    if (!empty($transaction)) {
        $extraInfo["Name"] = 'TRANSACTIONNUMBER';
        $extraInfo["Value"] = $transaction;
        $request->setExtraInfo($extraInfo);
    }

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Byjuno JTL 5.2 module 1.0.0';
    $request->setExtraInfo($extraInfo);

    return $request;

}

function byjunoIsStatusOk($status, $position)
{
    $config = Helper::getPluginById('byjuno')->getConfig();
    $settings = $config->getOption($position)->value;
    try {
        $config = trim($settings); // todo settings
        if ($config === "")
        {
            return false;
        }
        $stateArray = explode(",", $settings);
        if (in_array($status, $stateArray)) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function CreatePrestaShopRequestAfterPaid(Cart $cart, OrderCore $order, Currency $currency, $repayment, $riskOwner, $invoiceDelivery, $selected_gender = "", $selected_birthday = "", $transaction = "") {

    global $cookie;
    $customer = new Customer($order->id_customer);
    $invoice_address = new Address($order->id_address_invoice);
    $shipping_address = new Address($order->id_address_delivery);
    $country = new Country($invoice_address->id_country);
    $country_shipping = new Country($shipping_address->id_country);
    $request = new ByjunoRequest();
    $request->setClientId(Configuration::get("INTRUM_CLIENT_ID"));
    $request->setUserID(Configuration::get("INTRUM_USER_ID"));
    $request->setPassword(Configuration::get("INTRUM_PASSWORD"));
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail(Configuration::get("INTRUM_TECH_EMAIL"));
    } catch (Exception $e) {

    }
    $request->setRequestId(uniqid($customer->id."_"));
    $request->setCustomerReference($customer->id);
    $request->setFirstName(html_entity_decode($invoice_address->firstname, ENT_COMPAT, 'UTF-8'));
    $request->setLastName(html_entity_decode($invoice_address->lastname, ENT_COMPAT, 'UTF-8'));
    if ($customer->id_gender != '0') {
        $request->setGender($customer->id_gender);
    }
    if (substr($customer->birthday, 0, 4) != '0000') {
        try {
            $request->setDateOfBirth($customer->birthday);
        } catch (Exception $e) {

        }
    }

    $request->setFirstLine(html_entity_decode(trim($invoice_address->address1.' '.$invoice_address->address2), ENT_COMPAT, 'UTF-8'));
    $request->setCountryCode(strtoupper($country->iso_code));
    $request->setPostCode($invoice_address->postcode);
    $request->setTown(html_entity_decode($invoice_address->city, ENT_COMPAT, 'UTF-8'));
    $request->setLanguage(Context::getContext()->language->iso_code);

    $request->setTelephonePrivate($invoice_address->phone);
    $request->setMobile($invoice_address->phone_mobile);
    $request->setEmail($customer->email);

    if (!empty($invoice_address->company)) {
        $request->setCompanyName1($invoice_address->company);
    }

    if (!empty($invoice_address->vat_number)) {
        $request->setCompanyVatId($invoice_address->vat_number);
    }

    if (!empty($shipping_address->company)) {
        $request->setDeliveryCompanyName1($shipping_address->company);
    }

    if ($selected_gender != "") {
        $request->setGender($selected_gender);
    }
    if ($selected_birthday != "") {
        $request->setDateOfBirth($selected_birthday);
    }

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = 'YES';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $order->total_paid_tax_incl;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $currency->iso_code;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = byjunoGetClientIp();
    $request->setExtraInfo($extraInfo);

    if (Configuration::get("INTRUM_ENABLETMX") == 'true' && Configuration::get("INTRUM_TMXORGID") != '' && !empty($cookie->intrumId)) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $cookie->intrumId;
        $request->setExtraInfo($extraInfo);
    }

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = html_entity_decode($shipping_address->firstname, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = html_entity_decode($shipping_address->lastname, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = html_entity_decode(trim($shipping_address->address1.' '.$shipping_address->address2), ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = '';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = strtoupper($country_shipping->iso_code);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $shipping_address->postcode;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = html_entity_decode($shipping_address->city, ENT_COMPAT, 'UTF-8');
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERID';
    $extraInfo["Value"] = $order->reference;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'PAYMENTMETHOD';
    $extraInfo["Value"] = mapMethod($repayment);
    $request->setExtraInfo($extraInfo);

    if ($repayment != "") {
        $extraInfo["Name"] = 'REPAYMENTTYPE';
        $extraInfo["Value"] = mapRepayment($repayment);
        $request->setExtraInfo($extraInfo);
    }

    if ($invoiceDelivery == 'postal') {
        $extraInfo["Name"] = 'PAPER_INVOICE';
        $extraInfo["Value"] = 'YES';
        $request->setExtraInfo($extraInfo);
    }

    if ($riskOwner != "") {
        $extraInfo["Name"] = 'RISKOWNER';
        $extraInfo["Value"] = $riskOwner;
        $request->setExtraInfo($extraInfo);
    }

    if (!empty($transaction)) {
        $extraInfo["Name"] = 'TRANSACTIONNUMBER';
        $extraInfo["Value"] = $transaction;
        $request->setExtraInfo($extraInfo);
    }

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Byjuno Prestashop 1.7, 1.8 module 1.0.7';
    $request->setExtraInfo($extraInfo);	

    return $request;

}

function CreateShopRequestS4($doucmentId, $amount, $orderAmount, $orderCurrency, $orderId, $customerId, $date)
{
    $request = new ByjunoS4Request();
    $request->setClientId(Configuration::get("INTRUM_CLIENT_ID"));
    $request->setUserID(Configuration::get("INTRUM_USER_ID"));
    $request->setPassword(Configuration::get("INTRUM_PASSWORD"));
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail(Configuration::get("INTRUM_TECH_EMAIL"));
    } catch (Exception $e) {

    }
    $request->setRequestId(uniqid((String)$orderId . "_"));
    $request->setOrderId($orderId);
    $request->setClientRef($customerId);
    $request->setTransactionDate($date);
    $request->setTransactionAmount(number_format($amount, 2, '.', ''));
    $request->setTransactionCurrency($orderCurrency);
    $request->setAdditional1("INVOICE");
    $request->setAdditional2($doucmentId);
    $request->setOpenBalance(number_format($orderAmount, 2, '.', ''));
    return $request;
}
function CreateShopRequestS5Refund($documentId, $amount, $orderCurrency, $orderId, $customerId, $date)
{
    $request = new ByjunoS5Request();
    $request->setClientId(Configuration::get("INTRUM_CLIENT_ID"));
    $request->setUserID(Configuration::get("INTRUM_USER_ID"));
    $request->setPassword(Configuration::get("INTRUM_PASSWORD"));
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail(Configuration::get("INTRUM_TECH_EMAIL"));
    } catch (Exception $e) {

    }
    $request->setRequestId(uniqid((String)$orderId . "_"));
    $request->setOrderId($orderId);
    $request->setClientRef($customerId);
    $request->setTransactionDate($date);
    $request->setTransactionAmount(number_format($amount, 2, '.', ''));
    $request->setTransactionCurrency($orderCurrency);
    $request->setTransactionType("REFUND");
    $request->setAdditional2($documentId);
    return $request;
}
function CreateShopRequestS5Cancel($amount, $orderCurrency, $orderId, $customerId, $date)
{
    $request = new ByjunoS5Request();
    $request->setClientId(Configuration::get("INTRUM_CLIENT_ID"));
    $request->setUserID(Configuration::get("INTRUM_USER_ID"));
    $request->setPassword(Configuration::get("INTRUM_PASSWORD"));
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail(Configuration::get("INTRUM_TECH_EMAIL"));
    } catch (Exception $e) {

    }
    $request->setRequestId(uniqid((String)$orderId . "_"));
    $request->setOrderId($orderId);
    $request->setClientRef($customerId);
    $request->setTransactionDate($date);
    $request->setTransactionAmount(number_format($amount, 2, '.', ''));
    $request->setTransactionCurrency($orderCurrency);
    $request->setAdditional2('');
    $request->setTransactionType("EXPIRED");
    $request->setOpenBalance("0");
    return $request;
}