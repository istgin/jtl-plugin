<?php

use JTL\Checkout\Lieferadresse;
use JTL\Language\LanguageHelper;
use JTL\Plugin\Helper;
use JTL\Session\Frontend;
use JTL\Shop;
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

function Cembra_MapPayment($type)
{
    if ($type == 'installment_3') {
        return CembraPayConstants::$INSTALLMENT_3;
    } else if ($type == 'installment_4') {
        return CembraPayConstants::$INSTALLMENT_4;
    } else if ($type == 'installment_6') {
        return CembraPayConstants::$INSTALLMENT_6;
    } else if ($type == 'installment_12') {
        return CembraPayConstants::$INSTALLMENT_12;
    } else if ($type == 'installment_24') {
        return CembraPayConstants::$INSTALLMENT_24;
    } else if ($type == 'installment_36') {
        return CembraPayConstants::$INSTALLMENT_36;
    } else if ($type == 'installment_48') {
        return CembraPayConstants::$INSTALLMENT_48;
    } else if ($type == 'single_invoice') {
        return CembraPayConstants::$SINGLEINVOICE;
    } else {
        return CembraPayConstants::$CEMBRAPAYINVOICE;
    }
}

function Cembra_MapToc($lang, $type, $config)
{
    if ($type == 'installment_3'
        || $type == 'installment_4'
        || $type == 'installment_6'
        || $type == 'installment_12'
        || $type == 'installment_24'
        || $type == 'installment_36'
        || $type == 'installment_48') {

        switch ($lang) {
            case "DE":
                return $config->getOption("byjuno_toc_de_installment")->value;
            case "FR":
                return $config->getOption("byjuno_toc_fr_installment")->value;
            case "EN":
                return $config->getOption("byjuno_toc_en_installment")->value;
            case "IT":
                return $config->getOption("byjuno_toc_it_installment")->value;

        }

    } else {
        switch ($lang) {
            case "DE":
                return $config->getOption("byjuno_toc_de_invoice")->value;
            case "FR":
                return $config->getOption("byjuno_toc_fr_invoice")->value;
            case "EN":
                return $config->getOption("byjuno_toc_en_invoice")->value;
            case "IT":
                return $config->getOption("byjuno_toc_it_invoice")->value;

        }
    }

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
 * @return CembraPayCheckoutAutRequest
 * @throws Exception
 */

function CreateJTLScreeningShopRequest($customer, $cart, $address) {

    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
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
    $currency = $cart->Waehrung ?? Frontend::getCurrency();
    $b2b = false;
    if (!empty($customer->cFirma)) {
        $b2b = true;
    }

    $request = new CembraPayCheckoutAutRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_SCREENING;
    $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
    $request->merchantOrderRef = null;
    $request->amount = round($cart->gibGesamtsummeWaren(true) * 100);
    $request->currency = $currency->getCode();
    $request->custDetails->merchantCustRef = (string)$custId;
    if ($customer->nRegistriert == 1) {
        $request->custDetails->loggedIn = true;
    } else {
        $request->custDetails->loggedIn = false;
    }
    if (!empty($billingAddress["company"]) && $b2b) {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_BUSINESS;
        $request->custDetails->companyName = $customer->cFirma;
    } else {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_PRIVATE;
    }

    $request->custDetails->firstName = (string)html_entity_decode(($customer->cVorname), ENT_COMPAT, 'UTF-8');
    $request->custDetails->lastName = (string)html_entity_decode($customer->cNachname, ENT_COMPAT, 'UTF-8');
    $request->custDetails->language = (string)$lang;
    $request->custDetails->salutation = CembraPayConstants::$GENTER_UNKNOWN;
    $request->billingAddr->addrFirstLine = (string)html_entity_decode(trim($customer->cStrasse), ENT_COMPAT, 'UTF-8');
    $request->billingAddr->postalCode = (string)$customer->cPLZ;
    $request->billingAddr->town = (string)html_entity_decode($customer->cOrt, ENT_COMPAT, 'UTF-8');
    $request->billingAddr->country = strtoupper($customer->cLand);
    $request->custContacts->email = (string)$customer->cMail;
    $request->custContacts->phoneMobile = $customer->cMobil;
    $request->custContacts->phonePrivate = $customer->cTel;

    $request->deliveryDetails->deliveryDetailsDifferent = true;
    $request->deliveryDetails->deliveryFirstName = html_entity_decode($address->cVorname, ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliverySecondName =  html_entity_decode($address->cNachname, ENT_COMPAT, 'UTF-8');
    if (!empty($address->cFirma) && $b2b) {
        $request->deliveryDetails->deliveryCompanyName = $address->cFirma;
    }
    $request->deliveryDetails->deliverySalutation = CembraPayConstants::$GENTER_UNKNOWN;
    $request->deliveryDetails->deliveryAddrFirstLine = html_entity_decode(trim($address->cStrasse), ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrPostalCode = $address->cPLZ;
    $request->deliveryDetails->deliveryAddrTown = html_entity_decode($address->cOrt, ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrCountry = strtoupper($address->cLand);

    if ($config->getOption("byjuno_threatmetrix")->value == "true" &&  $config->getOption("byjuno_threatmetrix_org")->value != '' && !empty($_SESSION["byjuno_session_id"])) {
        $request->sessionInfo->tmxSessionId = $_SESSION["byjuno_session_id"];
    }

    $request->sessionInfo->sessionIp = byjunoGetClientIp();

    $customerConsents = new CustomerConsents();
    $customerConsents->consentType = "SCREENING";
    $customerConsents->consentProvidedAt = "MERCHANT";
    $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
    $customerConsents->consentReference = "MERCHANT DATA PRIVACY";
    $request->customerConsents = array($customerConsents);

    $request->merchantDetails->transactionChannel = "WEB";
    $request->merchantDetails->integrationModule = "Byjuno JTL 5.4 module 2.0.0";

    return $request;
}


function CreateJTLAuthShopRequest($order, $repayment, $invoiceDelivery, $riskOwner, $selected_gender = "", $selected_birthday = "") {

    /* @var $config JTL\Plugin\Data\Config */
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();

    $request = new CembraPayCheckoutAutRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_AUTH;
    $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
    $request->merchantOrderRef = $order->cBestellNr;
    $request->amount = round(number_format($order->fGesamtsumme, 2, '.', '') * 100);
    $request->currency = $order->Waehrung->getCode();

    $customerRef = $order->Lieferadresse->kKunde;
    $requestId = uniqid("customer_");
    if (!empty($customerRef)) {
        $request->custDetails->merchantCustRef = (string)$customerRef;
        $request->custDetails->loggedIn = false;
    } else {
        $request->custDetails->merchantCustRef = (string)$requestId;
        $request->custDetails->loggedIn = true;
    }

    $isB2B = false;
    if (!empty($order->oRechnungsadresse->cFirma)) {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_BUSINESS;
        $request->custDetails->companyName = $order->oRechnungsadresse->cFirma;
        $isB2B = true;
    } else {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_PRIVATE;
    }
    $lang = 'DE';
    if (!empty($order->kSprache)) {
        $langIso = LanguageHelper::getIsoFromLangID($order->kSprache);
        if (!empty($langIso->cISO)) {
            $lang = byjunoMapLang($langIso->cISO);
        }
    }


    $request->custDetails->firstName = (string)html_entity_decode($order->oRechnungsadresse->cVorname, ENT_COMPAT, 'UTF-8');
    $request->custDetails->lastName = (string)html_entity_decode($order->oRechnungsadresse->cNachname, ENT_COMPAT, 'UTF-8');
    $request->custDetails->language = (string)$lang;


    if (!empty($selected_gender)) {
        if ($selected_gender == 1) {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_MALE;
        } else if ($selected_gender == 2) {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_FEMALE;
        }
    }
    if (!empty($selected_birthday)) {
        $request->custDetails->dateOfBirth = $selected_birthday;
    }

    $request->billingAddr->addrFirstLine =
        (string)html_entity_decode(trim($order->oRechnungsadresse->cStrasse), ENT_COMPAT, 'UTF-8'). " ".
        (string)html_entity_decode(trim($order->oRechnungsadresse->cHausnummer), ENT_COMPAT, 'UTF-8');
    $request->billingAddr->postalCode = (string)$order->oRechnungsadresse->cPLZ;
    $request->billingAddr->town = (string)html_entity_decode($order->oRechnungsadresse->cOrt, ENT_COMPAT, 'UTF-8');
    $request->billingAddr->country = (string)strtoupper($order->oRechnungsadresse->cLand);

    $request->custContacts->phoneMobile = (string)$order->oRechnungsadresse->cMobil;
    $request->custContacts->phonePrivate = (string)$order->oRechnungsadresse->cTel;
    $request->custContacts->email = (string)$order->oRechnungsadresse->cMail;

    $request->deliveryDetails->deliveryMethod = CembraPayConstants::$DELIVERY_POST;
    $request->deliveryDetails->deliveryFirstName = (string)html_entity_decode($order->Lieferadresse->cVorname, ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliverySecondName = (string)html_entity_decode($order->Lieferadresse->cNachname, ENT_COMPAT, 'UTF-8');
    if (!empty($order->Lieferadresse->cFirma)) {
        $request->deliveryDetails->deliveryCompanyName = (string)$order->Lieferadresse->cFirma;
    }
    $request->deliveryDetails->deliverySalutation = null;

    $request->deliveryDetails->deliveryAddrFirstLine = (string)html_entity_decode(trim($order->Lieferadresse->cStrasse), ENT_COMPAT, 'UTF-8')." ".
                                                        (string)html_entity_decode(trim($order->Lieferadresse->cHausnummer), ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrPostalCode = (string)$order->Lieferadresse->cPLZ;
    $request->deliveryDetails->deliveryAddrTown = (string)html_entity_decode($order->Lieferadresse->cOrt, ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrCountry = (string)strtoupper($order->Lieferadresse->cLand);

    $request->order->basketItemsGoogleTaxonomies = array();
    $request->order->basketItemsPrices = array();

    if ($config->getOption("byjuno_threatmetrix")->value == "true" &&  $config->getOption("byjuno_threatmetrix_org")->value != '' && !empty($_SESSION["byjuno_session_id"])) {
        $request->sessionInfo->tmxSessionId = $_SESSION["byjuno_session_id"];
    }
    $request->sessionInfo->sessionIp = byjunoGetClientIp();

    if ($riskOwner != "") {
        $request->cembraPayDetails->riskOnlyOnCembraPay = false;
    } else {
        $request->cembraPayDetails->riskOnlyOnCembraPay = true;
    }
    $request->cembraPayDetails->cembraPayPaymentMethod = Cembra_MapPayment($repayment);
    if ($invoiceDelivery == 'postal') {
        $request->cembraPayDetails->invoiceDeliveryType = "POSTAL";
    } else {
        $request->cembraPayDetails->invoiceDeliveryType = "EMAIL";
    }

    $customerConsents = new CustomerConsents();
    $customerConsents->consentType = "CEMBRAPAY-TC";
    $customerConsents->consentProvidedAt = "MERCHANT";
    $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
    $link = Cembra_MapToc($lang, $repayment, $config);
    $exLink = explode("/", $link);
    $consentReference = end($exLink);
    if (empty($consentReference) && isset($exLink[count($exLink) - 1])) {
        $consentReference = $exLink[count($exLink) - 2];
    }
    $customerConsents->consentReference = base64_encode($consentReference);
    $request->customerConsents = array($customerConsents);
    $request->merchantDetails->transactionChannel = "WEB";
    $request->merchantDetails->integrationModule = "Cembrapay JTL 5.2 module 2.0.0";

    return $request;

}

function CreateJTLChekoutShopRequest($order, $successUrl, $cancelUrl, $errorUrl) {

    /* @var $config JTL\Plugin\Data\Config */
    $config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();

    $request = new CembraPayCheckoutAutRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_CHK;
    $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
    $request->merchantOrderRef = $order->cBestellNr;
    $request->amount = round(number_format($order->fGesamtsumme, 2, '.', '') * 100);
    $request->currency = $order->Waehrung->getCode();

    $customerRef = $order->Lieferadresse->kKunde;
    $requestId = uniqid("customer_");
    if (!empty($customerRef)) {
        $request->custDetails->merchantCustRef = (string)$customerRef;
        $request->custDetails->loggedIn = false;
    } else {
        $request->custDetails->merchantCustRef = (string)$requestId;
        $request->custDetails->loggedIn = true;
    }

    $isB2B = false;
    if (!empty($order->oRechnungsadresse->cFirma)) {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_BUSINESS;
        $request->custDetails->companyName = $order->oRechnungsadresse->cFirma;
        $isB2B = true;
    } else {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_PRIVATE;
    }
    $lang = 'DE';
    if (!empty($order->kSprache)) {
        $langIso = LanguageHelper::getIsoFromLangID($order->kSprache);
        if (!empty($langIso->cISO)) {
            $lang = byjunoMapLang($langIso->cISO);
        }
    }


    $request->custDetails->firstName = (string)html_entity_decode($order->oRechnungsadresse->cVorname, ENT_COMPAT, 'UTF-8');
    $request->custDetails->lastName = (string)html_entity_decode($order->oRechnungsadresse->cNachname, ENT_COMPAT, 'UTF-8');
    $request->custDetails->language = (string)$lang;

    $request->billingAddr->addrFirstLine =
        (string)html_entity_decode(trim($order->oRechnungsadresse->cStrasse), ENT_COMPAT, 'UTF-8'). " ".
        (string)html_entity_decode(trim($order->oRechnungsadresse->cHausnummer), ENT_COMPAT, 'UTF-8');
    $request->billingAddr->postalCode = (string)$order->oRechnungsadresse->cPLZ;
    $request->billingAddr->town = (string)html_entity_decode($order->oRechnungsadresse->cOrt, ENT_COMPAT, 'UTF-8');
    $request->billingAddr->country = (string)strtoupper($order->oRechnungsadresse->cLand);

    $request->custContacts->phoneMobile = (string)$order->oRechnungsadresse->cMobil;
    $request->custContacts->phonePrivate = (string)$order->oRechnungsadresse->cTel;
    $request->custContacts->email = (string)$order->oRechnungsadresse->cMail;

    $request->deliveryDetails->deliveryMethod = CembraPayConstants::$DELIVERY_POST;
    $request->deliveryDetails->deliveryFirstName = (string)html_entity_decode($order->Lieferadresse->cVorname, ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliverySecondName = (string)html_entity_decode($order->Lieferadresse->cNachname, ENT_COMPAT, 'UTF-8');
    if (!empty($order->Lieferadresse->cFirma)) {
        $request->deliveryDetails->deliveryCompanyName = (string)$order->Lieferadresse->cFirma;
    }
    $request->deliveryDetails->deliverySalutation = null;

    $request->deliveryDetails->deliveryAddrFirstLine = (string)html_entity_decode(trim($order->Lieferadresse->cStrasse), ENT_COMPAT, 'UTF-8')." ".
        (string)html_entity_decode(trim($order->Lieferadresse->cHausnummer), ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrPostalCode = (string)$order->Lieferadresse->cPLZ;
    $request->deliveryDetails->deliveryAddrTown = (string)html_entity_decode($order->Lieferadresse->cOrt, ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrCountry = (string)strtoupper($order->Lieferadresse->cLand);

    $request->order->basketItemsGoogleTaxonomies = array();
    $request->order->basketItemsPrices = array();

    if ($config->getOption("byjuno_threatmetrix")->value == "true" &&  $config->getOption("byjuno_threatmetrix_org")->value != '' && !empty($_SESSION["byjuno_session_id"])) {
        $request->sessionInfo->tmxSessionId = $_SESSION["byjuno_session_id"];
    }
    $request->sessionInfo->sessionIp = byjunoGetClientIp();

    $request->cembraPayDetails->cembraPayPaymentMethod = null;
    $request->merchantDetails->returnUrlSuccess = base64_encode($successUrl);
    $request->merchantDetails->returnUrlCancel = base64_encode($cancelUrl);
    $request->merchantDetails->returnUrlError = base64_encode($errorUrl);

    $request->merchantDetails->transactionChannel = "WEB";
    $request->merchantDetails->integrationModule = "Cembrapay JTL 5.2 module 2.0.0";

    return $request;

}


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
    $extraInfo["Value"] = 'Byjuno JTL 5.2 module 2.0.0';
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
function CreateJTLOrderShopRequest($order, $repayment, $invoiceDelivery, $riskOwner, $transaction, $selected_gender = "", $selected_birthday = "", $orderClosed = "NO") {

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
    $extraInfo["Value"] = 'Byjuno JTL 5.2 module 2.0.0';
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

function CreateShopRequestSettle($doucmentId, $amount, $orderCurrency, $orderId, $tx)
{

    $request = new CembraPayCheckoutSettleRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_SET;
    $request->requestMsgId = CembraPayCheckoutSettleRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutSettleRequest::Date();
    $request->transactionId = $tx;
    $request->merchantOrderRef = $orderId;
    $request->amount = round(number_format($amount, 2, '.', '') * 100);
    $request->currency = $orderCurrency;
    $request->settlementDetails->merchantInvoiceRef = $doucmentId;
    $request->settlementDetails->isFinal = true;
    return $request;

}
function CreateShopRequestBCDPCancel($amount, $orderCurrency, $orderId, $tx)
{
    $request = new CembraPayCheckoutCancelRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_CAN;
    $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
    $request->transactionId = $tx;
    $request->merchantOrderRef = $orderId;
    $request->amount = round(number_format($amount, 2, '.', '') * 100);
    $request->currency = $orderCurrency;
    $request->isFullCancelation = true;
    return $request;
}

function CreateShopRequestCreditRefund($doucmentId, $amount, $orderCurrency, $orderId, $tx, $settlementId)
{
    $request = new CembraPayCheckoutCreditRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_CNL;
    $request->requestMsgId = CembraPayCheckoutCreditRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutCreditRequest::Date();
    $request->transactionId = $tx;
    $request->merchantOrderRef = $orderId;
    $request->amount = round(number_format($amount, 2, '.', '') * 100);
    $request->currency = $orderCurrency;
    $request->settlementDetails->merchantInvoiceRef = $doucmentId;
    $request->settlementDetails->settlementId = $settlementId;
    return $request;
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

function CembraAuthorizationResponse($response)
{
    $responseObject = json_decode($response);
    $result = new CembraPayCheckoutAuthorizationResponse();
    if (empty($responseObject->processingStatus)) {
        $result->processingStatus = CembraPayConstants::$REQUEST_ERROR;
    } else {
        $result->processingStatus = $responseObject->processingStatus;
        if ($responseObject->processingStatus == CembraPayConstants::$AUTH_OK) {
            $result->transactionId = $responseObject->transactionId;
        }
    }
    return $result;
}

function CembraConfirmTransaction($transactionId)
{
    $request = new CembraPayConfirmRequest();
    $request->requestMsgId = CembraPayCheckoutChkRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutChkRequest::Date();
    $request->transactionId = $transactionId;

    return $request;
}

function CembraConfirmTransactionResponse($response)
{
    $responseObject = json_decode($response);
    $result = new CembraPayConfirmResponse();
    if (empty($responseObject->transactionStatus->transactionStatus)) {
        $result->transactionStatus->transactionStatus= CembraPayConstants::$REQUEST_ERROR;
    } else {
        $result->requestMerchantId = $responseObject->requestMerchantId;
        $result->requestMsgId = $responseObject->requestMsgId;
        $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
        $result->replyMsgId = $responseObject->replyMsgId;
        $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
        $result->isTokenDeleted = !empty($responseObject->isTokenDeleted) ? $responseObject->isTokenDeleted : false;
        $result->transactionStatus->transactionStatus = $responseObject->transactionStatus->transactionStatus;
    }
    return $result;
}

function CembraGetTransaction($transactionId)
{
    $request = new CembraPayGetStatusRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_STATUS;
    $request->requestMsgId = CembraPayCheckoutChkRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutChkRequest::Date();
    $request->transactionId = $transactionId;
    return $request;
}

function CembraTransactionResponse($response)
{
    $responseObject = json_decode($response);
    $result = new CembraPayGetStatusResponse();
    if (empty($responseObject->transactionStatus->transactionStatus)) {
        $result->transactionStatus->transactionStatus= CembraPayConstants::$REQUEST_ERROR;
    } else {
        $result->requestMerchantId = $responseObject->requestMerchantId;
        $result->requestMsgType = $responseObject->transactionId;
        $result->requestMsgId = $responseObject->requestMsgType;
        $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
        $result->replyMsgId = $responseObject->replyMsgId;
        $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
        $result->isTokenDeleted = !empty($responseObject->isTokenDeleted) ? $responseObject->isTokenDeleted : false;
        $result->merchantOrderRef = $responseObject->merchantOrderRef;
        $result->transactionStatus->transactionStatus = $responseObject->transactionStatus->transactionStatus;
    }
    return $result;
}
function CembraCheckoutResponse($response)
{
    $responseObject = json_decode($response);
    $result = new CembraPayCheckoutChkResponse();
    if (empty($responseObject->processingStatus)) {
        $result->processingStatus = CembraPayConstants::$REQUEST_ERROR;
    } else {
        $result->processingStatus = $responseObject->processingStatus;
        if ($responseObject->processingStatus == CembraPayConstants::$CHK_OK) {
            $result->transactionId = $responseObject->transactionId;
            $result->redirectUrlCheckout = $responseObject->redirectUrlCheckout;
        }
    }
    return $result;
}

function CembraScreeningResponse($response)
{
    $responseObject = json_decode($response);
    $result = new CembraPayCheckoutScreeningResponse();
    if (empty($responseObject->processingStatus)) {
        $result->processingStatus = CembraPayConstants::$REQUEST_ERROR;
    } else {
        if ($responseObject->processingStatus == CembraPayConstants::$SCREENING_OK) {
            $result->merchantCustRef = $responseObject->merchantCustRef;
            $result->processingStatus = $responseObject->processingStatus;
            $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
            $result->replyMsgId = $responseObject->replyMsgId;
            $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
            $result->requestMsgId = $responseObject->requestMsgId;
            $result->transactionId = $responseObject->transactionId;
            if (!empty($responseObject->screeningDetails) && !empty($responseObject->screeningDetails->allowedCembraPayPaymentMethods)) {
                $result->screeningDetails->allowedCembraPayPaymentMethods = $responseObject->screeningDetails->allowedCembraPayPaymentMethods;
            }
        } else {
            $result->processingStatus = $responseObject->processingStatus;
        }
    }
    return $result;
}

function CembraGetAccessDataWebshop($config, $mode) {
    $accessData = new CembraPayLoginDto();
    $accessData->helperObject = "CembraSaveToken";
    $accessData->timeout = (int)$config->getOption("byjuno_timeout")->value;

    $accessToken = "";
    $instance = ByjunoAccess::getInstance();
    if ($mode == 'test') {
        $key = $instance->getAccessKey("access_token_test");
        $accessData->mode = 'test';
        $accessData->username = $config->getOption("cembra_test_client_id")->value;
        $accessData->password = $config->getOption("cembra_test_password")->value;
        $accessData->audience = "59ff4c0b-7ce8-42f0-983b-306706936fa1/.default";
        if (!empty($key->access_value)) {
            $accessToken = $key->access_value;
        }
    } else {
        $key = $instance->getAccessKey("access_token_test");
        $accessData->mode = 'live';
        $accessData->username = $config->getOption("cembra_live_client_id")->value;
        $accessData->password = $config->getOption("cembra_live_password")->value;
        $accessData->audience = "80d0ac9d-9d5c-499c-876e-71dd57e436f2/.default";
        if (!empty($key->access_value)) {
            $accessToken = $key->access_value;
        }
    }
    $tkn = explode(CembraPayConstants::$tokenSeparator, $accessToken);
    $hash = $accessData->username.$accessData->password.$accessData->audience;
    if ($hash == $tkn[0] && !empty($tkn[1])) {
        $accessData->accessToken = $tkn[1];
    }
    return $accessData;
}

function CembraSaveToken($token, $accessData) {
    /* @var $cofing JTL\Plugin\Data\Config */
    /* @var $accessData CembraPayLoginDto */
    $hash = $accessData->username.$accessData->password.$accessData->audience.CembraPayConstants::$tokenSeparator;
    $instance = ByjunoAccess::getInstance();
    if ($accessData->mode == 'test') {
        $instance->addOrUpdateAccessKey(Array(
            "access_key" => "access_token_test",
            "access_value" => $hash.$token
        ));
    } else {
        $instance->addOrUpdateAccessKey(Array(
            "access_key" => "access_token_live",
            "access_value" => $hash.$token
        ));
    }
}