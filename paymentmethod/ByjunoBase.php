<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use ByjunoCommunicator;
use ByjunoLogger;
use ByjunoRequest;
use ByjunoResponse;
use JTL\Checkout\Bestellung;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

require_once(dirname(__FILE__).'/../api/byjuno.php');

class ByjunoBase extends Method
{
    public const PLUGIN_ID = 'byjuno';

    var $paymethod = '';
    var $localeTexts = array();

    protected $_savedUser = Array(
        "FirstName" => "",
        "LastName" => "",
        "FirstLine" => "",
        "CountryCode" => "",
        "PostCode" => "",
        "Town" => "",
        "CompanyName1",
        "DateOfBirth",
        "Email",
        "Fax",
        "TelephonePrivate",
        "TelephoneOffice",
        "Gender",
        "DELIVERY_FIRSTNAME",
        "DELIVERY_LASTNAME",
        "DELIVERY_FIRSTLINE",
        "DELIVERY_HOUSENUMBER",
        "DELIVERY_COUNTRYCODE",
        "DELIVERY_POSTCODE",
        "DELIVERY_TOWN",
        "DELIVERY_COMPANYNAME"
    );

    /**
     * init
     *
     */
    function init(int $nAgainCheckout = 0)
    {
        parent::init($nAgainCheckout);

        $this->name = 'Byjuno';
        $this->caption = 'Byjuno';

        $this->info = Shop::Container()->getDB()->select('tzahlungsart', 'cModulId', $this->moduleID);

        $this->loadTexts();
    }

    /**
     * @param     $moduleID
     * @param int $nAgainCheckout
     */
    function __construct($moduleID = NULL, $nAgainCheckout = 0)
    {
        $this->pageURL = 'http://' . $_SERVER['HTTP_HOST'] . '/';
        if ($this->isHTTPS()) $this->pageURL = 'https://' . $_SERVER['HTTP_HOST'] . '/';

        if (!empty($moduleID)) {
            parent::__construct($moduleID, $nAgainCheckout);
        } else {
            $this->loadSettings();
            $this->init($nAgainCheckout);
        }

        $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
        $plugin = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
        $conf = $plugin->getConfig()->getAssoc();

    }

    /**
     * validateAdditional
     *
     * @return bool
     */
    public function validateAdditional(): bool
    {
        $_SESSION["byjuno_error_msg"] = "";
        $_SESSION["byjuno_gender"] = "";
        $_SESSION["byjuno_bithday"] = "";
        $_SESSION["byjuno_payment"] = "";
        $_SESSION["byjuno_send_method"] = "";
        $_SESSION["byjyno_terms"] = "";
        if (!isset($_POST["byjuno_form"])) {
            return true;
        }
        if (empty($_POST["byjuno_gender"])) {
            $_SESSION["byjuno_error_msg"] = "Gender is required";
            return false;
        } else {
            $_SESSION["byjuno_gender"] = $_POST["byjuno_gender"];
        }
        if (empty($_POST["byjuno_year"]) || empty($_POST["byjuno_month"]) || empty($_POST["byjuno_day"])) {
            $_SESSION["byjuno_error_msg"] = "Birthday is incorrect";
            return false;
        } else {
            $_SESSION["byjuno_bithday"] = $_POST["byjuno_year"] . '-' . $_POST["byjuno_month"] . '-' . $_POST["byjuno_day"];
        }

        if (empty($_POST["byjuno_payment"])) {
            $_SESSION["byjuno_error_msg"] = "Please select repayment";
            return false;
        } else {
            $_SESSION["byjuno_payment"] = $_POST["byjuno_payment"];
        }

        if (empty($_POST["byjuno_send_method"])) {
            $_SESSION["byjuno_error_msg"] = "Please select invoice delivery method";
            return false;
        } else {
            $_SESSION["byjuno_send_method"] = $_POST["byjuno_send_method"];
        }

        if (empty($_POST["byjyno_terms"]) || $_POST["byjyno_terms"] != "terms_conditions") {
            $_SESSION["byjuno_error_msg"] = "You must agree with byjuno terms and conditions";
            return false;
        } else {
            $_SESSION["byjyno_terms"] = $_POST["byjyno_terms"];
        }


        return true;
    }

    /**
     * handleAdditional
     *
     * @param array $req
     *
     * @return bool
     */
    public function handleAdditional(array $req): bool
    {
        // return TRUE; if no custom fields shown
        // HERE SOMEHOW add form
        //    exit('aaa');
        global $smarty;

        $b2b = false;//Configuration::get("BYJUNO_B2B") == 'enable';
        $byjuno_invoice = false;
        $byjuno_installment = false;
        // if (Configuration::get("single_invoice") == 'enable' || Configuration::get("byjuno_invoice") == 'enable') {
        $byjuno_invoice = true;
        //  }

        /*  if (Configuration::get("installment_3") == 'enable'
              || Configuration::get("installment_36") == 'enable'
              || Configuration::get("installment_12") == 'enable'
              || Configuration::get("installment_24") == 'enable'
              || Configuration::get("installment_4x12") == 'enable'
          ) {*/
        $byjuno_installment = true;
        // }
        if ($b2b) {
            // $invoice_address = new Address($this->context->cart->id_address_invoice);
            //  if (!empty($invoice_address->company)) {
            $byjuno_installment = false;
            //  }
        }

        $byjuno_invoice = true;
        $byjuno_installment = true;

        $result = false;
        $byjuno_error = "";
        if (!empty($_SESSION["byjuno_error_msg"])) {
            $byjuno_error = $_SESSION["byjuno_error_msg"];
            $_SESSION["byjuno_error_msg"] = "";
            $result = false;
        } else if (!isset($_POST["byjuno_form"])) {
            $result = false;
        } else {
            $result = true;
        }
        $lang = 'de';

        $payment = 'invoice';
        $selected_payments_invoice = Array();
        $selected_payments_installment = Array();
        $langtoc = "DE";


        $years = Array(1990, 2000);
        $months = Array(1, 2);
        $days = Array(1, 2);
        $tm = strtotime("1990-01-01");
        $invoice_send = "email";
        $selected_gender = 'Mr.';
        $byjuno_years = date("Y", $tm);
        $byjuno_months = date("m", $tm);
        $byjuno_days = date("d", $tm);
        $paymentMethod = Array();
        $values = array(
            'byjuno_error' => $byjuno_error,
            'payment' => $payment,
            'invoice_send' => $invoice_send,
            'byjuno_allowpostal' => 1,
            'byjuno_gender_birthday' => 1,
            'email' => 'test@byjuno.ch',
            'address' => 'XXXX',
            'l_year' => $this->getText("Year", "Year"),
            'years' => $years,
            'sl_year' => $byjuno_years,
            'l_month' => $this->getText("Month", "Month"),
            'months' => $months,
            'sl_month' => $byjuno_months,
            'l_day' => $this->getText("Day", "Day"),
            'days' => $days,
            'sl_day' => $byjuno_days,
            'sl_gender' => $selected_gender,
            'l_select_payment_plan' => $this->getText("Selectpaymentplan", "Select payment plan"),
            'l_select_invoice_delivery_method' => $this->getText("Selectinvoicedeliverymethod", "Select invoice delivery method"),
            'l_gender' => $this->getText("Gender", "Gender"),
            'l_male' => $this->getText("Male", "Male"),
            'l_female' => $this->getText("Female", "Female"),
            'l_date_of_birth' => $this->getText("DateofBirth", "Date of Birth"),
            'l_i_agree_with_terms_and_conditions' => $this->getText("Iagreewithtermsandconditions", "I agree with terms and conditions"),
            'l_other_payment_methods' => $this->getText("Otherpaymentmethods", "Other payment methods"),
            'l_i_confirm_my_order' => $this->getText("Iconfirmmyorder", "I confirm my order"),
            'l_your_shopping_cart_is_empty' => $this->getText("Yourshoppingcartisempty.", "Your shopping cart is empty."),
            'l_by_email' => $this->getText("Byemail", "By email"),
            'l_by_post' => $this->getText("Bypost", "By post"),
            'l_you_must_agree_terms_conditions' => $this->getText("Youmustagreetermsconditions", "You must agree terms conditions"),
        );
        if ($byjuno_invoice) {
            if ($b2b /*&& !empty($invoice_address->company)*/) {
                $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 1);
                $tocUrl = 'https://byjuino.ch';//Configuration::get('BYJUNO_TOC_INVOICE_' . $langtoc);
                $values['selected_payments_invoice'] = $selected_payments_invoice;
                $values['toc_url_invoice'] = $tocUrl;
            } else {
                // if (Configuration::get("byjuno_invoice") == 'enable') {
                $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoInvoice', "Byjuno Invoice (With partial payment option)"), 'id' => 'byjuno_invoice', "selected" => 0);
                //  }
                // if (Configuration::get("single_invoice") == 'enable') {
                $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 0);
                //  }
                $tocUrl = 'https://byjuino.ch'; //Configuration::get('BYJUNO_TOC_INVOICE_' . $langtoc);

                $selected_payments_invoice[0]["selected"] = 1;
                $values['selected_payments_invoice'] = $selected_payments_invoice;
                $values['toc_url_invoice'] = $tocUrl;
            }
        }

        if ($byjuno_installment) {
            //  if (Configuration::get("installment_3") == 'enable') {
            $selected_payments_installment[] = Array('name' => $this->getText('3installments', "3 installments"), 'id' => 'installment_3', "selected" => 0);
            // }
            //  if (Configuration::get("installment_36") == 'enable') {
            $selected_payments_installment[] = Array('name' => $this->getText('36installments', "36 installments"), 'id' => 'installment_36', "selected" => 0);
            // }
            // if (Configuration::get("installment_12") == 'enable') {
            $selected_payments_installment[] = Array('name' => $this->getText('12installments', "12 installments"), 'id' => 'installment_12', "selected" => 0);
            //  }
            //  if (Configuration::get("installment_24") == 'enable') {
            $selected_payments_installment[] = Array('name' => $this->getText('24installments', "24 installment"), 'id' => 'installment_24', "selected" => 0);
            //  }
            //  if (Configuration::get("installment_4x12") == 'enable') {
            $selected_payments_installment[] = Array('name' => $this->getText('4installmentsin12months', "4 installments in 12 months"), 'id' => 'installment_4x12', "selected" => 0);
            //   }
            $tocUrl = 'https://byjuino.ch'; //Configuration::get('BYJUNO_TOC_INSTALLMENT_' . $langtoc);
            $selected_payments_installment[0]["selected"] = 1;

            $values['selected_payments_installment'] = $selected_payments_installment;
            $values['toc_url_installment'] = $tocUrl;
        }
        $values["byjuno_iframe"] = true;
        $smarty->assign(
            $values
        );
        return $result;
    }

    /**
     * getPluginVersion
     *
     * @return mixed
     */
    function getPluginVersion()
    {
        global $db;
        $sql = 'SELECT * FROM `tplugin` WHERE `cPluginID` = "mapa" ';
        $res = Shop::Container()->getDB()->query($sql, 1);
        return $res->nXMLVersion;
    }

    /**
     * isHTTPS
     *
     * @return bool
     */
    function isHTTPS()
    {
        if (strpos($_SERVER['HTTP_HOST'], '.local') === FALSE) {
            if (!isset($_SERVER['HTTPS']) || (strtolower($_SERVER['HTTPS']) != 'on' && $_SERVER['HTTPS'] != '1')) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * setStatus
     *
     * sets status to order and marks as not synced again
     *
     * @param $status
     * @param $orderId
     * @param $comment
     *
     * @return bool
     */
    function setStatus($status, $orderId, $comment = NULL)
    {
        if ($status != '') {
            $sql = '
        UPDATE `tbestellung` 
        SET `dBezahltDatum` = NOW(),
          `cStatus` = "' . $status . '",
          `cAbgeholt` = "N"
          ';
            if (!empty($comment)) {
                //$sql .= ', `cKommentar` = "'.addslashes(utf8_decode($comment)).'" ';
                $sql .= ', `cKommentar` = "' . addslashes(($comment)) . '" ';
            }
            $sql .= 'WHERE `cBestellNr` = "' . addslashes($orderId) . '"';
            if ($this->McPay->debug) $this->McPay->log->debug(__CLASS__ . '->' . __FUNCTION__ . '->sql: ' . print_r($sql, true));
            Shop::Container()->getDB()->query($sql, 3);
        }
        return TRUE;
    }

    /**
     * getUserDataFromOrder
     *
     * @param $order
     * @param $config
     *
     * @return array
     */
    function getUserDataFromOrder($order, $config)
    {
        //echo '<pre>'.print_r($config,true).'</pre>';
        // $this->McPay->setConfig($config);

        //echo '<pre>'.print_r($_SESSION['Kunde'],true).'</pre>';
        $custID = $_SESSION['Kunde']->kKunde;
        $orderId = $order->cBestellNr;
        if (empty($orderId)) $orderId = baueBestellnummer();

        if (empty($_SESSION['Kunde'])) { // guest order
            $custID = 'guest-' . $orderId;
        } else {
            $orderId .= '-' . $custID;
        }
        //  $custID = $this->McPay->unifyID($custID);

        $currency = $_SESSION['Waehrung']->getCode(); //cISO;
        $amount = $order->fGesamtsummeKundenwaehrung; // In customer currency
        if (empty($amount)) $amount = $_SESSION["Warenkorb"]->gibGesamtsummeWaren(true);
        $amount = round($amount * 100);

        $user = $_SESSION['Kunde'];
        $oPlugin = $this->getPluginObj($order);
        $chksumData = array(
            'amount' => $amount,
            'currency' => $currency,
            'iso' => $user->cLand,
            'host' => $_SERVER['HTTP_HOST'],
            'project' => $config['project'],
            'shoptype' => 'jtl',
            'shopversion' => (string)(JTL_VERSION / 100) . '.' . JTL_MINOR_VERSION,
            'plugintype' => 'API',
            'pluginversion' => implode('.', str_split((string)$oPlugin->nVersion)),
        );
        $userData = array(
            'company' => $user->cFirma,
            'firstName' => $user->cVorname,
            'lastName' => $user->cNachname,
            'salutation' => $user->cAnrede == 'm' ? 'MR' : 'MRS',
            'street' => $user->cStrasse . ' ' . $user->cHausnummer,
            'zip' => $user->cPLZ,
            'city' => $user->cOrt,
            'country' => $user->cLand,
            'email' => $user->cMail,
            'ip' => '', //$_SESSION['oBesucher']->cIP,
            'id' => "XXXX",
            'order_id' => $orderId,
            'lang' => $config['lang'],
            'amount' => $amount,
            'currency' => $currency,
            'chksum' => $chksumData, // request security
        );
        if (empty($userData['ip'])) $userData['ip'] = $_SERVER['REMOTE_ADDR']; // Falls IP Leer, dann aus dem Server holen
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$userData', print_r($userData,true));
        return $userData;
    }

    /**
     * handleNotification
     *
     * @param \Bestellung $order
     * @param string $paymentHash
     * @param array $args
     * @param bool $returnURL
     *
     * @return string|void
     */

    function handleNotification(Bestellung $order, string $paymentHash, array $args, bool $returnURL = FALSE): void
    {
        if (!empty($_SESSION["change_paid"])) {
           // $this->addS4Log($order->kBestellung, "S3");
            return;
            //  $this->setOrderStatusToPaid($order);
        }
    }

    /**
     * preparePaymentProcess
     *
     * @param \Bestellung $order
     */
    public function preparePaymentProcess(Bestellung $order): void
    {
        parent::preparePaymentProcess($order);
    }

    /**
     * redirectOnPaymentSuccess
     *
     * @return bool
     */
    public function redirectOnPaymentSuccess(): bool
    {
        return FALSE;
    }

    /**
     * redirectOnCancel
     *
     * @return bool
     */
    public function redirectOnCancel(): bool
    {
        return FALSE;
    }

    /**
     * getReturnURL
     *
     * @param \Bestellung $order
     *
     * @return string
     */
    public function getReturnURL(Bestellung $order): string
    {
        return parent::getReturnURL($order);
    }

    /**
     * finalizeOrder
     *
     * @param \Bestellung $order
     * @param string $hash
     * @param array $args
     *
     * @return bool|true
     */
    public function finalizeOrder(Bestellung $order, string $hash, array $args): bool
    {
        $_SESSION["BYJUNO_ERROR"] = null;
        // S1 & S3 here
        $order->cBestellNr = getOrderHandler()->createOrderNo();
        try {
            $requestS1 = CreateJTLOrderShopRequest($order,
                "ORDERREQUEST",
                $_SESSION["byjuno_payment"],
                $_SESSION["byjuno_send_method"],
                "",
                "",
                $_SESSION["byjuno_gender"],
                $_SESSION["byjuno_bithday"],
            "NO");
            $type = "S1 Request";
            $b2b = true; //TODO ettings
            if ($b2b && !empty($requestS1->getCompanyName1())) {
                $type = "S1 Request B2B";
                $xml = $requestS1->createRequestCompany();
            } else {
                $xml = $requestS1->createRequest();
            }
            $byjunoCommunicator = new ByjunoCommunicator();
            $byjunoCommunicator->setServer('test'); //TODO ettings
            $response = $byjunoCommunicator->sendRequest($xml, (int)30); //TODO ettings

            $transaction = "";
            if ($response) {
                $byjunoResponse = new ByjunoResponse();
                $byjunoResponse->setRawResponse($response);
                $byjunoResponse->processResponse();
                $status = $byjunoResponse->getCustomerRequestStatus();
                $transaction = $byjunoResponse->getTransactionNumber();
            }
            $byjunoLogger = ByjunoLogger::getInstance();
            $byjunoLogger->addSOrderLog(Array(
                "order_id" => $order->cBestellNr,
                "request_type" => "S1",
                "firstname" => $requestS1->getFirstName(),
                "lastname" => $requestS1->getLastName(),
                "town" => $requestS1->getTown(),
                "postcode" => $requestS1->getPostCode(),
                "street" => trim($requestS1->getFirstLine().' '.$requestS1->getHouseNumber()),
                "country" => $requestS1->getCountryCode(),
                "ip" => byjunoGetClientIp(),
                "status" => $status,
                "request_id" => $requestS1->getRequestId(),
                "type" => $type,
                "error" => ($status == 0) ? "ERROR" : "",
                "response" => $response,
                "request" => $xml
            ));
            $accept = "";
            if (byjunoIsStatusOk($status, "byjuno_s2_accept_merchant")) {
                $accept = "CLIENT";
            }
            if (byjunoIsStatusOk($status, "byjuno_s2_accept_ij")) {
                $accept = "IJ";
            }
            $accept = 'CLIENT';
            if ($accept == "") {
                $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
                // --  HOOK_BESTELLVORGANG_PAGE_STEPZAHLUNG on hook show error!!!
                return false;
            }

            $requestS3 = CreateJTLOrderShopRequest($order,
                "",
                $_SESSION["byjuno_payment"],
                $_SESSION["byjuno_send_method"],
                $accept,
                $transaction,
                $_SESSION["byjuno_gender"],
                $_SESSION["byjuno_bithday"],
            "YES");
            $typeS3 = "S3 Request";
            $b2b = true;
            $xmlS3 = "";
            if ($b2b && !empty($requestS1->getCompanyName1())) {
                $typeS3 = "S3 Request B2B";
                $xmlS3 = $requestS3->createRequestCompany();
            } else {
                $xmlS3 = $requestS3->createRequest();
            }

            $responseS3 = $byjunoCommunicator->sendRequest($xml, (int)30);
            $statusS3 = 0;
            if ($responseS3) {
                $byjunoResponseS3 = new ByjunoResponse();
                $byjunoResponseS3->setRawResponse($responseS3);
                $byjunoResponseS3->processResponse();
                $statusS3 = $byjunoResponseS3->getCustomerRequestStatus();
            }
            $byjunoLogger->addSOrderLog(Array(
                "order_id" => $order->cBestellNr,
                "request_type" => "S3",
                "firstname" => $requestS1->getFirstName(),
                "lastname" => $requestS1->getLastName(),
                "town" => $requestS1->getTown(),
                "postcode" => $requestS1->getPostCode(),
                "street" => trim($requestS1->getFirstLine().' '.$requestS1->getHouseNumber()),
                "country" => $requestS1->getCountryCode(),
                "ip" => byjunoGetClientIp(),
                "status" => $status,
                "request_id" => $requestS1->getRequestId(),
                "type" => $typeS3,
                "error" => ($status == 0) ? "ERROR" : "",
                "response" => $response,
                "request" => $xmlS3
            ));

            if (byjunoIsStatusOk($statusS3, "byjuno_s3_accept")) {
                $_SESSION["change_paid"] = true;
                $_SESSION["byjuno_cdp"] = null;
                $_SESSION["byjuno_cdp_status"] = null;
                return true;
            }
            $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
            return false;
        } catch (\Exception $e) {
            $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
            return false;
        }
    }

    public function CDPRequest() {

        $cart = Frontend::getCart();
        $customer = Frontend::getCustomer();
        $delivery = Frontend::getDeliveryAddress();
        if (true) {
            $theSame = (!empty($_SESSION["byjuno_cdp"])) ? $_SESSION["byjuno_cdp"] : null;
            if (!empty($theSame) && is_array($theSame)) {
                $this->_savedUser = $theSame;
            }
            $CDPStatus = (!empty($_SESSION["byjuno_cdp_status"])) ? $_SESSION["byjuno_cdp_status"] : null;
            try {
                $requestCDP = CreateJTLCDPShopRequest($customer, $cart, $delivery, "CREDITCHECK");
                if ($requestCDP->getExtraInfoByKey("ORDERAMOUNT") == 0) {
                    return false;
                }
                if (!empty($CDPStatus) && $this->isTheSame($requestCDP)) {
                    $accept = "";
                    if (byjunoIsStatusOk($CDPStatus, "byjuno_cdp_accept")) {
                        $accept = "OK";
                    }
                    if ($accept == "") {
                        return false;
                    }
                    return true;
                }
                if (!$this->isTheSame($requestCDP) || empty($CDPStatus)) {
                    $ByjunoRequestName = "Credit check request";
                    if ($requestCDP->getCompanyName1() != '' && true) {
                        $ByjunoRequestName = "Credit check request for Company";
                        $xmlCDP = $requestCDP->createRequestCompany();
                    } else {
                        $xmlCDP = $requestCDP->createRequest();
                    }

                    $byjunoLogger = ByjunoLogger::getInstance();
                    $byjunoCommunicator = new ByjunoCommunicator();
                    $byjunoCommunicator->setServer('test'); //TODO ettings
                    $responseCDP = $byjunoCommunicator->sendRequest($xmlCDP, (int)30); //TODO ettings
                    if ($responseCDP) {
                        $byjunoResponse = new ByjunoResponse();
                        $byjunoResponse->setRawResponse($responseCDP);
                        $byjunoResponse->processResponse();
                        $status = $byjunoResponse->getCustomerRequestStatus();
                        if (intval($status) > 15) {
                            $status = 0;
                        }
                        $byjunoLogger->addSOrderLog(Array(
                            "order_id" => -1,
                            "request_type" => "CDP",
                            "firstname" => $requestCDP->getFirstName(),
                            "lastname" => $requestCDP->getLastName(),
                            "town" => $requestCDP->getTown(),
                            "postcode" => $requestCDP->getPostCode(),
                            "street" => trim($requestCDP->getFirstLine().' '.$requestCDP->getHouseNumber()),
                            "country" => $requestCDP->getCountryCode(),
                            "ip" => byjunoGetClientIp(),
                            "status" => $status,
                            "request_id" => $requestCDP->getRequestId(),
                            "type" => $ByjunoRequestName,
                            "error" => ($status == 0) ? "ERROR" : "",
                            "response" => $responseCDP,
                            "request" => $xmlCDP
                        ));
                    } else {
                        $byjunoLogger->addSOrderLog(Array(
                            "order_id" => -1,
                            "request_type" => "CDP",
                            "firstname" => $requestCDP->getFirstName(),
                            "lastname" => $requestCDP->getLastName(),
                            "town" => $requestCDP->getTown(),
                            "postcode" => $requestCDP->getPostCode(),
                            "street" => trim($requestCDP->getFirstLine().' '.$requestCDP->getHouseNumber()),
                            "country" => $requestCDP->getCountryCode(),
                            "ip" => byjunoGetClientIp(),
                            "status" => 0,
                            "request_id" => $requestCDP->getRequestId(),
                            "type" => $ByjunoRequestName,
                            "error" => "empty response",
                            "response" => $responseCDP,
                            "request" => $xmlCDP
                        ));
                    }

                    $this->_savedUser = Array(
                        "FirstName" => $requestCDP->getFirstName(),
                        "LastName" => $requestCDP->getLastName(),
                        "FirstLine" => $requestCDP->getFirstLine(),
                        "CountryCode" => $requestCDP->getCountryCode(),
                        "PostCode" => $requestCDP->getPostCode(),
                        "Town" => $requestCDP->getTown(),
                        "CompanyName1" => $requestCDP->getCompanyName1(),
                        "DateOfBirth" => $requestCDP->getDateOfBirth(),
                        "Email" => $requestCDP->getEmail(),
                        "Fax" => $requestCDP->getFax(),
                        "TelephonePrivate" => $requestCDP->getTelephonePrivate(),
                        "TelephoneOffice" => $requestCDP->getTelephoneOffice(),
                        "Gender" => $requestCDP->getGender(),
                        "Amount" => $requestCDP->getExtraInfoByKey("ORDERAMOUNT"),
                        "DELIVERY_FIRSTNAME" => $requestCDP->getExtraInfoByKey("DELIVERY_FIRSTNAME"),
                        "DELIVERY_LASTNAME" => $requestCDP->getExtraInfoByKey("DELIVERY_LASTNAME"),
                        "DELIVERY_FIRSTLINE" => $requestCDP->getExtraInfoByKey("DELIVERY_FIRSTLINE"),
                        "DELIVERY_HOUSENUMBER" => $requestCDP->getExtraInfoByKey("DELIVERY_HOUSENUMBER"),
                        "DELIVERY_COUNTRYCODE" => $requestCDP->getExtraInfoByKey("DELIVERY_COUNTRYCODE"),
                        "DELIVERY_POSTCODE" => $requestCDP->getExtraInfoByKey("DELIVERY_POSTCODE"),
                        "DELIVERY_TOWN" => $requestCDP->getExtraInfoByKey("DELIVERY_TOWN"),
                        "DELIVERY_COMPANYNAME" => $requestCDP->getExtraInfoByKey("DELIVERY_COMPANYNAME")
                    );
                    $_SESSION["byjuno_cdp"] = $this->_savedUser;
                    $_SESSION["byjuno_cdp_status"] = $status;

                    $accept = "";
                    if (byjunoIsStatusOk($status, "byjuno_cdp_accept")) {
                        $accept = "OK";
                    }

                    if ($accept == "") {
                        return false;
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return true;
    }

    public function isTheSame(ByjunoRequest $request) {

        if ($request->getFirstName() != $this->_savedUser["FirstName"]
            || $request->getLastName() != $this->_savedUser["LastName"]
            || $request->getFirstLine() != $this->_savedUser["FirstLine"]
            || $request->getCountryCode() != $this->_savedUser["CountryCode"]
            || $request->getPostCode() != $this->_savedUser["PostCode"]
            || $request->getTown() != $this->_savedUser["Town"]
            || $request->getCompanyName1() != $this->_savedUser["CompanyName1"]
            || $request->getDateOfBirth() != $this->_savedUser["DateOfBirth"]
            || $request->getEmail() != $this->_savedUser["Email"]
            || $request->getFax() != $this->_savedUser["Fax"]
            || $request->getTelephonePrivate() != $this->_savedUser["TelephonePrivate"]
            || $request->getTelephoneOffice() != $this->_savedUser["TelephoneOffice"]
            || $request->getGender() != $this->_savedUser["Gender"]
            || $request->getExtraInfoByKey("ORDERAMOUNT") != $this->_savedUser["Amount"]
            || $request->getExtraInfoByKey("DELIVERY_FIRSTNAME") != $this->_savedUser["DELIVERY_FIRSTNAME"]
            || $request->getExtraInfoByKey("DELIVERY_LASTNAME") != $this->_savedUser["DELIVERY_LASTNAME"]
            || $request->getExtraInfoByKey("DELIVERY_FIRSTLINE") != $this->_savedUser["DELIVERY_FIRSTLINE"]
            || $request->getExtraInfoByKey("DELIVERY_HOUSENUMBER") != $this->_savedUser["DELIVERY_HOUSENUMBER"]
            || $request->getExtraInfoByKey("DELIVERY_COUNTRYCODE") != $this->_savedUser["DELIVERY_COUNTRYCODE"]
            || $request->getExtraInfoByKey("DELIVERY_POSTCODE") != $this->_savedUser["DELIVERY_POSTCODE"]
            || $request->getExtraInfoByKey("DELIVERY_TOWN") != $this->_savedUser["DELIVERY_TOWN"]
            || $request->getExtraInfoByKey("DELIVERY_COMPANYNAME") != $this->_savedUser["DELIVERY_COMPANYNAME"]
        ) {
            return false;
        }
        return true;
    }

    /**
     * getPluginObj
     *
     * @param $order
     *
     * @return bool|\Plugin
     */
    function getPluginObj($order)
    {
        $myZV = $_SESSION['Zahlungsart']->cModulId;
        if (empty($myZV)) $myZV = $order->Zahlungsart->cModulId;

        $this->moduleID = $myZV;
        $this->init(0);

        $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
        $plugin = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);

        return $plugin;
    }

    /**
     * getPluginConf
     *
     * @param      $order
     * @param null $oPlugin
     *
     * @return array|bool
     */
    function getPluginConf($order, $oPlugin = NULL)
    {
//    if (!is_object($oPlugin)) {
//      $oPlugin = $this->getPluginObj($order);
//    }
//    if (!is_object($oPlugin)) return FALSE;
        if (is_object($oPlugin)) {
            $plugin = $oPlugin;
        } else {
            $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
            $plugin = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
        }
        $conf = $plugin->getConfig()->getAssoc();
        //echo '<pre>'.print_r($conf,true).'</pre>';
        $paths = $plugin->getPaths();
        //echo '<pre>'.print_r($paths,true).'</pre>';

        //echo '<pre>'.print_r($oPlugin,true).'</pre>';
        //echo '<pre>Order:'.print_r($order,true).'</pre>';
        //echo '<pre>'.print_r($_SESSION['Zahlungsart'],true).'</pre>';
        //$oPlugin->oPluginZahlungsmethodeAssoc_arr;

        $payConfKey = $_SESSION['Zahlungsart']->cModulId;
        //echo $conf[$payConfKey.'_paytext']->value;

        //$conf                   = $oPlugin->oPluginEinstellungAssoc_arr;
        $conf['plugin_url'] = $paths->getFrontendURL();
        $conf['plugin_url'] = str_replace('frontend', 'mcpay', $conf['plugin_url']);
        $conf['plugin_url'] = str_replace('http://', $_SERVER['REQUEST_SCHEME'] . '://', $conf['plugin_url']);
        $conf['plugin_path'] = $paths->getFrontendPath();
        $conf['plugin_path'] = str_replace('frontend', 'mcpay', $conf['plugin_path']);
        $conf['plugin_shopurl'] = $paths->getShopURL();
        $conf['plugin_shopurl'] = str_replace('http://', $_SERVER['REQUEST_SCHEME'] . '://', $conf['plugin_shopurl']);
        if (empty($conf['payformid'])) {
            $conf['payformid'] = 'form_payment_extra'; // jtl default
        }

        //echo '<pre>'.print_r($conf,true).'</pre>';
        //echo '<pre>'.print_r($oPlugin,true).'</pre>';
        $lang = $_SESSION['cISOSprache'] == 'ger' ? 'DE' : 'EN';

        $config = array(/*
            'project'         => $conf['project_id']->value,
            'accessKey'       => $conf['accesskey']->value,
            'testMode'        => $conf['testmode']->value,
            'suffix'          => $conf['suffix']->value,
            'theme'           => $conf['theme']->value,
            'gfx'             => $conf['gfx']->value,
            'bgcolor'         => $conf['bgcolor']->value,
            'bggfx'           => $conf['bggfx']->value,
            'paytext'         => $conf[$payConfKey.'_paytext']->value,
            'plugin_urljs'    => $conf['plugin_shopurl'].'/mcpayGetScript?sn=',
            'plugin_urlcss'   => $conf['plugin_shopurl'].'/mcpayGetStyle?sn=',
            'plugin_urlshop'  => $conf['plugin_shopurl'],
            'plugin_path'     => $conf['plugin_path'].'',
            'plugin_pathview' => $conf['plugin_path'].'view/',
            'plugin_pathjs'   => $conf['plugin_path'].'js/',
            'plugin_pathcss'  => $conf['plugin_path'].'css/',
            'payformid'       => $conf['payformid']->value,
            'debug'           => $conf['xxx_debug']->value,
            'customPrefix'    => $conf['customPrefix']->value,
            'lang'            => $lang,
            'expiredays'      => $conf['expiredays']->value,
            'start3D'         => $conf['start3D']->value,
            'start3Dreuse'    => $conf['start3Dreuse']->value,
            */
        );
        return $config;
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$config', print_r($config,true));
        $this->McPay->setConfig($config);
        return $config;
    }

    public function loadTexts()
    {
        if (empty($this->localeTexts)) {
            $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
            $plugin = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
            $texts = $plugin->getLocalization()->getTranslations();
            $this->setTexts($texts);
        }
    }

    /**
     * setTexts
     *
     * @param $texts
     */
    public function setTexts($texts)
    {
        $this->localeTexts = $texts;
    }

    /**
     * getText
     *
     * @param $textName
     * @param $default
     *
     * @return mixed
     */
    public function getText($textName, $default)
    {
        if (empty($this->localeTexts[$textName])) {
            return $default;
        }
        return $this->localeTexts[$textName];
    }

}