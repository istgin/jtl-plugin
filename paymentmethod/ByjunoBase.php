<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use ByjunoCommunicator;
use ByjunoLogger;
use ByjunoRequest;
use ByjunoResponse;
use JTL\Checkout\Bestellung;
use JTL\Customer\Customer;
use JTL\Language\LanguageHelper;
use JTL\Plugin\Data\Config;
use JTL\Plugin\Helper;
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
    /* @var $config Config */
    var $config;
    public static $SEND_MAIL = false;

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
        if (!empty($moduleID)) {
            parent::__construct($moduleID, $nAgainCheckout);
        } else {
            $this->loadSettings();
            $this->init($nAgainCheckout);
        }
        $this->config = Helper::getPluginById(ByjunoBase::PLUGIN_ID)->getConfig();
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
        $_SESSION["byjuno_birthday"] = "";
        $_SESSION["byjuno_payment"] = "";
        $_SESSION["byjuno_send_method"] = "";
        $_SESSION["byjyno_terms"] = "";
        if (!isset($_POST["byjuno_form"])) {
            return true;
        }
        if ($this->config->getOption("byjuno_gender_show")->value == "true") {
            if (empty($_POST["byjuno_gender"])) {
                $_SESSION["byjuno_error_msg"] = $this->getText("byjuno_fail_gender_message", "Please select gender");
                return false;
            } else {
                $_SESSION["byjuno_gender"] = $_POST["byjuno_gender"];
            }
        } else {
            $_SESSION["byjuno_gender"] = "";
        }

        if ($this->config->getOption("byjuno_birthday_show")->value == "true") {
            if (empty($_POST["byjuno_year"]) || empty($_POST["byjuno_month"]) || empty($_POST["byjuno_day"])) {
                $_SESSION["byjuno_error_msg"] = $this->getText("byjuno_fail_birthday_message", "Birthday is incorrect");
                return false;
            } else {
                if (!checkdate(intval($_POST["byjuno_month"]), intval($_POST["byjuno_day"]), intval($_POST["byjuno_year"]))) {
                    $_SESSION["byjuno_error_msg"] = $this->getText("byjuno_fail_birthday_message", "Birthday is incorrect");
                    return false;
                }
                $_SESSION["byjuno_birthday"] = $_POST["byjuno_year"] . '-' . $_POST["byjuno_month"] . '-' . $_POST["byjuno_day"];
            }
        } else {
            $_SESSION["byjuno_birthday"]  = "";
        }

        if (empty($_POST["byjuno_payment"])) {
            $_SESSION["byjuno_error_msg"] = $this->getText("byjuno_fail_repayment_message", "Please select payment plan");
            return false;
        } else {
            $_SESSION["byjuno_payment"] = $_POST["byjuno_payment"];
        }

        if ($this->config->getOption("byjuno_postal")->value == 'false') {
            $_SESSION["byjuno_send_method"] = 'email';
        } else {
            if (empty($_POST["byjuno_send_method"])) {
                $_SESSION["byjuno_error_msg"] = $this->getText("byjuno_fail_send_method_message", "Please select invoice delivery method");
                return false;
            } else {
                $_SESSION["byjuno_send_method"] = $_POST["byjuno_send_method"];
            }
        }

        if (empty($_POST["byjyno_terms"]) || $_POST["byjyno_terms"] != "terms_conditions") {
            $_SESSION["byjuno_error_msg"] =  $this->getText("byjuno_fail_agree_message", "You must agree with byjuno terms and conditions");
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
        global $smarty;
        $customer = Frontend::getCustomer();

        $b2b =  $this->config->getOption("byjuno_b2b")->value == "true";
        $byjuno_invoice = false;
        $byjuno_installment = false;
        if ($this->config->getOption("byjuno_invoice")->value == "true" || $this->config->getOption("byjuno_single_invoice")->value == "true") {
            $byjuno_invoice = true;
        }

        if ($this->config->getOption("byjuno_3_installments")->value == "true"
              || $this->config->getOption("byjuno_36_installments")->value == "true"
              || $this->config->getOption("byjuno_12_installments")->value == "true"
              || $this->config->getOption("byjuno_24_installments")->value == "true"
              || $this->config->getOption("byjuno_4_installments_12_months")->value == "true"
          ) {
            $byjuno_installment = true;
        }
        if ($b2b) {
            if (!empty($customer->cFirma)) {
                $byjuno_installment = false;
            }
        }
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

        $selected_payments_invoice = Array();
        $selected_payments_installment = Array();
        $langtoc = "de";
        if (!empty($customer->kSprache)) {
            $langIso = LanguageHelper::getIsoFromLangID($customer->kSprache);
            if (!empty($langIso->cISO)) {
                $langtoc = strtolower(byjunoMapLang($langIso->cISO));
            }
        }
        $year = date("Y");
        for ($i = $year - 100; $i <= $year; $i++) {
            $years[] = $i;
        }
        for ($i = 1; $i <= 12; $i++) {
            $months[] = $i;
        }
        for ($i = 1; $i <= 31; $i++) {
            $days[] = $i;
        }
        $tm = strtotime("1990-01-01");
        if (!empty($_SESSION["byjuno_birthday"])) {
            $tm = strtotime($_SESSION["byjuno_birthday"]);
        }
        $invoice_send = "email";
        if (!empty($_SESSION["byjuno_send_method"] )) {
            $invoice_send = $_SESSION["byjuno_send_method"] ;
        }
        $selected_gender = '1';
        if (!empty($_SESSION["byjuno_gender"] )) {
            $selected_gender = $_SESSION["byjuno_gender"] ;
        }
        $byjuno_years = (int)date("Y", $tm);
        $byjuno_months = (int)date("m", $tm);
        $byjuno_days = (int)date("d", $tm);
        $values = array(
            'byjuno_invoice' => $this->getText("byjuno_invoice", "Byjuno Invoice"),
            'byjuno_installment' => $this->getText("byjuno_installment", "Byjuno Installment"),
            'byjuno_error' => $byjuno_error,
            'invoice_send' => $invoice_send,
            'byjuno_allowpostal' =>  $this->config->getOption("byjuno_postal")->value == "true",
            'byjuno_gender_show' => $this->config->getOption("byjuno_gender_show")->value == "true",
            'byjuno_birthday_show' => $this->config->getOption("byjuno_birthday_show")->value == "true",
            'email' => $customer->cMail,
            'address' =>  $customer->cOrt.', '.$customer->cStrasse.' '.$customer->cHausnummer,
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
            'l_by_email' => $this->getText("Byemail", "By email"),
            'l_by_post' => $this->getText("Bypost", "By post")
        );
        if ($byjuno_invoice) {
            if ($b2b && !empty($customer->cFirma)) {
                $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 1);
                $tocUrl = $this->config->getOption('byjuno_toc_'.$langtoc.'_invoice')->value;
                $values['selected_payments_invoice'] = $selected_payments_invoice;
                $values['toc_url_invoice'] = $tocUrl;
            } else {
                if ($this->config->getOption("byjuno_invoice")->value == "true") {
                    $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoInvoice', "Byjuno Invoice (With partial payment option)"), 'id' => 'byjuno_invoice', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_single_invoice")->value == "true") {
                    $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 0);
                }
                $tocUrl = $this->config->getOption('byjuno_toc_'.$langtoc.'_invoice')->value;

                $values["selected_payment"] = (!empty($_SESSION["byjuno_payment"])) ? $_SESSION["byjuno_payment"] : $selected_payments_invoice[0]["id"];
                $values['selected_payments_invoice'] = $selected_payments_invoice;
                $values['toc_url_invoice'] = $tocUrl;
            }
        }

        if ($byjuno_installment) {
            if ($this->config->getOption("byjuno_3_installments")->value == "true") {
                $selected_payments_installment[] = Array('name' => $this->getText('3installments', "3 installments"), 'id' => 'installment_3', "selected" => 0);
            }
            if ($this->config->getOption("byjuno_36_installments")->value == "true") {
                $selected_payments_installment[] = Array('name' => $this->getText('36installments', "36 installments"), 'id' => 'installment_36', "selected" => 0);
            }
            if ($this->config->getOption("byjuno_12_installments")->value == "true") {
                $selected_payments_installment[] = Array('name' => $this->getText('12installments', "12 installments"), 'id' => 'installment_12', "selected" => 0);
            }
            if ($this->config->getOption("byjuno_24_installments")->value == "true") {
                $selected_payments_installment[] = Array('name' => $this->getText('24installments', "24 installment"), 'id' => 'installment_24', "selected" => 0);
            }
            if ($this->config->getOption("byjuno_4_installments_12_months")->value == "true") {
                $selected_payments_installment[] = Array('name' => $this->getText('4installmentsin12months', "4 installments in 12 months"), 'id' => 'installment_4x12', "selected" => 0);
            }
            $tocUrl = $this->config->getOption('byjuno_toc_'.$langtoc.'_invoice')->value;
            $values["selected_payment"] = (!empty($_SESSION["byjuno_payment"])) ? $_SESSION["byjuno_payment"] : $selected_payments_installment[0]["id"];

            $values['selected_payments_installment'] = $selected_payments_installment;
            $values['toc_url_installment'] = $tocUrl;
        }
        $tmx = $this->config->getOption("byjuno_threatmetrix")->value == "true";
        $values["byjuno_tmx"] = $tmx;
        if ($tmx) {
            $_SESSION["byjuno_session_id"] = session_id();
            $values["byjuno_tmx_org_id"] = $this->config->getOption("byjuno_threatmetrix_org")->value;
            $values["byjuno_tmx_session_id"] = $_SESSION["byjuno_session_id"];
        }
        $smarty->assign(
            $values
        );

        $linkService = Shop::Container()->getLinkService();
        $redirectUrl = $linkService->getStaticRoute('bestellvorgang.php').'?reg=1';
        if ($this->pm == "byjuno_invoice" && !$byjuno_invoice) {
            $_SESSION["Zahlungsart"] = null;
            header('location:'.$redirectUrl);
            exit();
        }
        if ($this->pm == "byjuno_installment" && !$byjuno_installment) {
            $_SESSION["Zahlungsart"] = null;
            header('location:'.$redirectUrl);
            exit();
        }
        return $result;
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
            $_SESSION["change_paid"] = false;
            return;
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
        ByjunoBase::$SEND_MAIL = true;
        $_SESSION["BYJUNO_ERROR"] = null;
        $order->cBestellNr = getOrderHandler()->createOrderNo();
        try {
            $requestS1 = CreateJTLOrderShopRequest($order,
                "ORDERREQUEST",
                $_SESSION["byjuno_payment"],
                $_SESSION["byjuno_send_method"],
                "",
                "",
                $_SESSION["byjuno_gender"],
                $_SESSION["byjuno_birthday"],
            "NO");
            $type = "S1 Request";
            $b2b = $this->config->getOption("byjuno_b2b")->value == "true";
            if ($b2b && !empty($requestS1->getCompanyName1())) {
                $type = "S1 Request B2B";
                $xml = $requestS1->createRequestCompany();
            } else {
                $xml = $requestS1->createRequest();
            }
            $byjunoCommunicator = new ByjunoCommunicator();
            if ($this->config->getOption("byjuno_mode")->value == 'live') {
                $byjunoCommunicator->setServer("live");
            } else {
                $byjunoCommunicator->setServer("test");
            }
            $response = $byjunoCommunicator->sendRequest($xml, intval($this->config->getOption("byjuno_timeout")->value));

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
                "order_status" => $order->cStatus,
                "request_type" => "S1",
                "firstname" => $requestS1->getFirstName(),
                "lastname" => $requestS1->getLastName(),
                "town" => $requestS1->getTown(),
                "postcode" => $requestS1->getPostCode(),
                "street" => trim($requestS1->getFirstLine().' '.$requestS1->getHouseNumber()),
                "country" => $requestS1->getCountryCode(),
                "ip" => byjunoGetClientIp(),
                "status" => (String)intval($status),
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
            if ($accept == "") {
                $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
                return false;
            }

            $requestS3 = CreateJTLOrderShopRequest($order,
                "",
                $_SESSION["byjuno_payment"],
                $_SESSION["byjuno_send_method"],
                $accept,
                $transaction,
                $_SESSION["byjuno_gender"],
                $_SESSION["byjuno_birthday"],
            "YES");
            $typeS3 = "S3 Request";
            $xmlS3 = "";
            if ($b2b && !empty($requestS1->getCompanyName1())) {
                $typeS3 = "S3 Request B2B";
                $xmlS3 = $requestS3->createRequestCompany();
            } else {
                $xmlS3 = $requestS3->createRequest();
            }

            $responseS3 = $byjunoCommunicator->sendRequest($xmlS3, intval($this->config->getOption("byjuno_timeout")->value));
            $statusS3 = 0;
            if ($responseS3) {
                $byjunoResponseS3 = new ByjunoResponse();
                $byjunoResponseS3->setRawResponse($responseS3);
                $byjunoResponseS3->processResponse();
                $statusS3 = $byjunoResponseS3->getCustomerRequestStatus();
            }
            $byjunoLogger->addSOrderLog(Array(
                "order_id" => $order->cBestellNr,
                "order_status" => $order->cStatus,
                "request_type" => "S3",
                "firstname" => $requestS3->getFirstName(),
                "lastname" => $requestS3->getLastName(),
                "town" => $requestS3->getTown(),
                "postcode" => $requestS3->getPostCode(),
                "street" => trim($requestS3->getFirstLine().' '.$requestS3->getHouseNumber()),
                "country" => $requestS3->getCountryCode(),
                "ip" => byjunoGetClientIp(),
                "status" => (String)intval($statusS3),
                "request_id" => $requestS3->getRequestId(),
                "type" => $typeS3,
                "error" => ($statusS3 == 0) ? "ERROR" : "",
                "response" => $responseS3,
                "request" => $xmlS3
            ));

            if (byjunoIsStatusOk($statusS3, "byjuno_s3_accept")) {
                $_SESSION["change_paid"] = true;
                $_SESSION["byjuno_cdp"] = null;
                $_SESSION["byjuno_cdp_status"] = null;
                $_SESSION["byjuno_error_msg"] = "";
                $_SESSION["byjuno_gender"] = "";
                $_SESSION["byjuno_birthday"] = "";
                $_SESSION["byjuno_payment"] = "";
                $_SESSION["byjuno_send_method"] = "";
                $_SESSION["byjyno_terms"] = "";
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
        if ($this->config->getOption("byjuno_cdp")->value == "true") {
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
                    if ($this->config->getOption("byjuno_mode")->value == 'live') {
                        $byjunoCommunicator->setServer("live");
                    } else {
                        $byjunoCommunicator->setServer("test");
                    }
                    $responseCDP = $byjunoCommunicator->sendRequest($xmlCDP, intval($this->config->getOption("byjuno_timeout")->value));
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
                            "order_status" => -1,
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
                            "order_status" => -1,
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

    protected function sumIsInRange() {
        $cart = Frontend::getCart();
        if ($cart == null) {
            return false;
        }
        $amount = $cart->gibGesamtsummeWaren(true);
        if (floatval($this->config->getOption("byjuno_min")->value) > $amount ||
            floatval($this->config->getOption("byjuno_max")->value) < $amount) {
            return false;
        }
        return true;
    }

}