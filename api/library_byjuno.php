<?php

use JTL\Checkout\Lieferadresse;
use JTL\Language\LanguageHelper;
use JTL\Plugin\Helper;
use JTL\Session\Frontend;
use Plugin\byjuno\paymentmethod\ByjunoBase;

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
    $lng = "DE";
    if ($lang == 'ger') {
        $lng = 'DE';
    }
    if ($lang == 'fra') {
        $lng = 'FR';
    }
    if ($lang == 'ita') {
        $lng = 'IT';
    }
    if ($lang == 'eng') {
        $lng = 'EN';
    }
    return $lng;
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

    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
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
    $lang = 'DE';
    if (!empty($customer->kSprache)) {
        $langIso = LanguageHelper::getIsoFromLangID($customer->kSprache);
        if (!empty($langIso->cISO)) {
            $lang = byjunoMapLang($langIso->cISO);
        }
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
    $extraInfo["Value"] = 'Byjuno JTL 5.2 module 1.1.0';
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
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
    $request = new ByjunoRequest();
    $request->setClientId($config->getOption("byjuno_client_id")->value);
    $request->setUserID($config->getOption("byjuno_user_id")->value);
    $request->setPassword($config->getOption("byjuno_password")->value);
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail($config->getOption("byjuno_tech_email")->value);
    } catch (Exception $e) {
    }
    $lang = 'DE';
    if (!empty($order->kSprache)) {
        $langIso = LanguageHelper::getIsoFromLangID($order->kSprache);
        if (!empty($langIso->cISO)) {
            $lang = byjunoMapLang($langIso->cISO);
        }
    }
    $customerRef = $order->Lieferadresse->kKunde;
    $requestId = uniqid("customer_");
    if (!empty($customerRef)) {
        $custId = $customerRef;
    } else {
        $custId = $requestId;
    }
    $request->setRequestId($requestId);
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
    if (!empty($selected_gender)) {
        $request->setGender($selected_gender);
    }
    if (!empty($selected_birthday)) {
        $request->setDateOfBirth($selected_birthday);
    }

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = $orderClosed;
    $request->setExtraInfo($extraInfo);

    if ($orderClosed == "YES") {
        $extraInfo["Name"] = 'ORDERID';
        $extraInfo["Value"] = $order->cBestellNr;
        $request->setExtraInfo($extraInfo);
    }

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $order->fGesamtsumme;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $order->Waehrung->getCode();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = byjunoGetClientIp();
    $request->setExtraInfo($extraInfo);

    if ($config->getOption("byjuno_threatmetrix")->value == "true" &&  $config->getOption("byjuno_threatmetrix_org")->value != '' && !empty($_SESSION["byjuno_session_id"])) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $_SESSION["byjuno_session_id"];
        $request->setExtraInfo($extraInfo);
    }

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
        $extraInfo["Value"] = $msgType;
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
    $extraInfo["Value"] = 'Byjuno JTL 5.2 module 1.1.0';
    $request->setExtraInfo($extraInfo);

    return $request;

}

function byjunoIsStatusOk($status, $position)
{
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
    $settings = $config->getOption($position)->value;
    try {
        $config = trim($settings);
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

/**
 * @param $status
 * @return
void|nullconst BESTELLUNG_STATUS_STORNO                 = -1;
const BESTELLUNG_STATUS_OFFEN                  = 1;
const BESTELLUNG_STATUS_IN_BEARBEITUNG         = 2;
const BESTELLUNG_STATUS_BEZAHLT                = 3;
const BESTELLUNG_STATUS_VERSANDT               = 4;
const BESTELLUNG_STATUS_TEILVERSANDT           = 5;
 */
function byjunoOrderMapStatus($status)
{
    switch ($status) {
        case "open":
            return BESTELLUNG_STATUS_OFFEN;
            break;
        case "in_progress":
            return BESTELLUNG_STATUS_IN_BEARBEITUNG;
            break;
        case "paid":
            return BESTELLUNG_STATUS_BEZAHLT;
            break;
        case "shipped":
            return BESTELLUNG_STATUS_VERSANDT;
            break;
        case "partially_shipped":
            return BESTELLUNG_STATUS_TEILVERSANDT;
            break;
        case "cancelled":
            return BESTELLUNG_STATUS_STORNO;
            break;
        default:
            return null;

    }
}

function CreateShopRequestS4($doucmentId, $amount, $orderAmount, $orderCurrency, $orderId, $customerId, $date)
{
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
    $request = new ByjunoS4Request();
    $request->setClientId($config->getOption("byjuno_client_id")->value);
    $request->setUserID($config->getOption("byjuno_user_id")->value);
    $request->setPassword($config->getOption("byjuno_password")->value);
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail($config->getOption("byjuno_tech_email")->value);
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
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
    $request = new ByjunoS5Request();
    $request->setClientId($config->getOption("byjuno_client_id")->value);
    $request->setUserID($config->getOption("byjuno_user_id")->value);
    $request->setPassword($config->getOption("byjuno_password")->value);
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail($config->getOption("byjuno_tech_email")->value);
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
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
    $request = new ByjunoS5Request();
    $request->setClientId($config->getOption("byjuno_client_id")->value);
    $request->setUserID($config->getOption("byjuno_user_id")->value);
    $request->setPassword($config->getOption("byjuno_password")->value);
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail($config->getOption("byjuno_tech_email")->value);
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