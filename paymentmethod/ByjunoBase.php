<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use ByjunoCommunicator;
use CembraLogger;
use ByjunoRequest;
use ByjunoResponse;
use CembraPayAzure;
use CembraPayCheckoutAuthorizationResponse;
use CembraPayCheckoutAutRequest;
use CembraPayCheckoutChkResponse;
use CembraPayCheckoutSettleResponse;
use CembraPayCommunicator;
use CembraPayConstants;
use JTL\Checkout\Bestellung;
use JTL\Checkout\OrderHandler;
use JTL\Customer\Customer;
use JTL\Language\LanguageHelper;
use JTL\Plugin\Data\Config;
use JTL\Plugin\Helper;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

require_once(dirname(__FILE__).'/../api/cembrapay.php');

class ByjunoBase extends Method
{
    public const PLUGIN_ID = 'byjuno';
    public static $SEND_MAIL = false;

    public $paymethod = '';
    public $localeTexts = array();
    /* @var $config Config */
    public $config;
    public $info;

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
        if ($_POST["byjuno_form"] == "0") {
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
                $dob_year = sprintf('%02d', intval($_POST["byjuno_year"]));
                $dob_month = sprintf('%02d', intval($_POST["byjuno_month"]));
                $dob_day = sprintf('%02d', intval($_POST["byjuno_day"]));
                $_SESSION["byjuno_birthday"] = $dob_year . '-' . $dob_month . '-' . $dob_day;
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

        $byjuno_invoice = false;
        $byjuno_installment = false;
        if ($this->config->getOption("byjuno_invoice")->value == "true" || $this->config->getOption("byjuno_single_invoice")->value == "true") {
            $byjuno_invoice = true;
        }

        $b2b = $this->config->getOption("byjuno_b2b")->value == "true";
        if ($this->config->getOption("byjuno_3_installments")->value == "true"
              || $this->config->getOption("byjuno_4_installments")->value == "true"
              || $this->config->getOption("byjuno_6_installments")->value == "true"
              || $this->config->getOption("byjuno_12_installments")->value == "true"
              || $this->config->getOption("byjuno_24_installments")->value == "true"
              || $this->config->getOption("byjuno_36_installments")->value == "true"
              || $this->config->getOption("byjuno_48_installments")->value == "true"
          ) {
            $byjuno_installment = true;
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
            'l_by_email' => $this->getText("Byemail", "By email"),
            'l_by_post' => $this->getText("Bypost", "By post"),
            'is_chekout' => true,
            'l_i_checkout' => $this->getText("cembra_chk_message", "At the last step, you will be redirected to checkout. Press Continue to validate your order."),
        );
        $tocUrl = "";
        if ($byjuno_invoice) {
            if ($b2b && !empty($customer->cFirma)) {
                $selected_payments_invoice[] = Array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 1);
                $tocUrl = $this->config->getOption('byjuno_toc_'.$langtoc.'_invoice')->value;
                $values["selected_payment_invoice"] = $selected_payments_invoice[0]["id"];
                $values['selected_payments_invoice'] = $selected_payments_invoice;
            } else {
                if ($this->config->getOption("byjuno_invoice")->value == "true") {
                    $selected_payments_invoice[] = array('name' => $this->getText('ByjunoInvoice', "Byjuno Invoice (With partial payment option)"), 'id' => 'byjuno_invoice', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_single_invoice")->value == "true") {
                    $selected_payments_invoice[] = array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 0);
                }
                $tocUrl = $this->config->getOption('byjuno_toc_' . $langtoc . '_invoice')->value;

                $values["selected_payment_invoice"] = (!empty($_SESSION["byjuno_payment"])) ? $_SESSION["byjuno_payment"] : $selected_payments_invoice[0]["id"];
                $values['selected_payments_invoice'] = $selected_payments_invoice;
            }
        }

        if ($byjuno_installment) {
            if ($b2b && !empty($customer->cFirma)) {
                $selected_payments_invoice_installment[] = Array('name' => $this->getText('ByjunoSingleInvoice', "Byjuno Single Invoice"), 'id' => 'single_invoice', "selected" => 1);
                $tocUrl = $this->config->getOption('byjuno_toc_'.$langtoc.'_invoice')->value;
                $values["selected_payment_installment"] = $selected_payments_invoice_installment[0]["id"];
                $values['selected_payments_installment'] = $selected_payments_invoice_installment;
            } else {
                if ($this->config->getOption("byjuno_3_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('3installments', "3 installments"), 'id' => 'installment_3', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_4_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('4installments', "4 installments"), 'id' => 'installment_4', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_6_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('6installments', "6 installments"), 'id' => 'installment_6', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_12_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('12installments', "12 installments"), 'id' => 'installment_12', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_24_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('24installments', "24 installment"), 'id' => 'installment_24', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_36_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('36installments', "36 installments"), 'id' => 'installment_36', "selected" => 0);
                }
                if ($this->config->getOption("byjuno_48_installments")->value == "true") {
                    $selected_payments_installment[] = array('name' => $this->getText('48installments', "48 installments"), 'id' => 'installment_48', "selected" => 0);
                }
                $tocUrl = $this->config->getOption('byjuno_toc_' . $langtoc . '_invoice')->value;
                $values["selected_payment_installment"] = (!empty($_SESSION["byjuno_payment"])) ? $_SESSION["byjuno_payment"] : $selected_payments_installment[0]["id"];
                $values['selected_payments_installment'] = $selected_payments_installment;
            }
        }
        $privacyUrl = $this->config->getOption('byjuno_privacy_'.$langtoc.'')->value;

        $agree_text = $this->getText("Iagreewithtermsandconditionsnew", "I agree with terms and conditions");
        $agree_text = str_replace("(1)", "<a href='".$tocUrl."' target='_blank' style='text-decoration: underline'>", $agree_text);
        $agree_text = str_replace("(2)", "</a>", $agree_text);
        $agree_text = str_replace("(3)", "<a href='".$privacyUrl."' target='_blank' style='text-decoration: underline'>", $agree_text);
        $agree_text = str_replace("(4)", "</a>", $agree_text);
        $values['l_i_agree_with_terms_and_conditions'] = $agree_text;

        $tmx = $this->config->getOption("byjuno_threatmetrix")->value == "true";
        $values["byjuno_tmx"] = $tmx;
        if ($tmx) {
            $_SESSION["byjuno_session_id"] = session_id();
            $values["byjuno_tmx_org_id"] = $this->config->getOption("byjuno_threatmetrix_org")->value;
            $values["byjuno_tmx_session_id"] = $_SESSION["byjuno_session_id"];
        }
        $values["is_api"] = true;
        if ($this->config->getOption("cembra_plugin_mode")->value == 'checkout') {
            $values["is_api"] = false;
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
        $hash = $this->generateHash($order);
        $returnUrl = $this->getNotificationURL($hash);
        parent::preparePaymentProcess($order);
        if ($this->config->getOption("cembra_plugin_mode")->value == 'checkout') {
            $redirect = $this->checkoutRequest($order, $returnUrl);
            header('location:' . $redirect);
            exit();
        } else {
            header('location:' . $returnUrl);
            exit();
        }
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

    public function checkoutRequest(Bestellung $order, string $returnUrl) {
        $handler = new OrderHandler(Shop::Container()->getDB(), Frontend::getCustomer(), Frontend::getCart());
        $order->cBestellNr = $handler->createOrderNo();
        $_SESSION["cBestellNr"] = $order->cBestellNr;
        $returUrlCancel = $returnUrl."&cembracancel=true";
        $requestChk = CreateJTLChekoutShopRequest($order, $returnUrl, $returUrlCancel, $returUrlCancel);
        $type = "Checkout Request";
        $b2b = $this->config->getOption("byjuno_b2b")->value == "true";
        if ($b2b && !empty($requestChk->custDetails->companyName)) {
            $type = "Checkout Request B2B";
        }
        $json = $requestChk->createRequest();
        $cembraPayAzure = new CembraPayAzure();
        $cembrapayCommunicator = new CembraPayCommunicator($cembraPayAzure);

        $mode = 'test';
        if ($this->config->getOption("byjuno_mode")->value == 'live') {
            $cembrapayCommunicator->setServer('live');
            $mode = 'live';
        } else {
            $cembrapayCommunicator->setServer('test');
        }

        $response = $cembrapayCommunicator->sendCheckoutRequest($json,
            CembraGetAccessDataWebshop($this->config, $mode),
            function ($object, $token, $accessData) {
                CembraSaveToken($token, $accessData);
            });
        $responseRes = null;
        $byjunoLogger = CembraLogger::getInstance();
        $redirect = $returUrlCancel;
        $_SESSION["cembra_tx_id"] = "";
        if ($response) {
            /* @var $responseRes CembraPayCheckoutChkResponse */
            $responseRes = CembraCheckoutResponse($response);
            $status = $responseRes->processingStatus;
        } else {
            $status = "ERROR";
        }
        $cembrapayTrx = "";
        if ($status == CembraPayConstants::$CHK_OK) {
            $cembrapayTrx = $responseRes->transactionId;
            $redirect = $responseRes->redirectUrlCheckout;
            $_SESSION["cembra_tx_id"] = $responseRes->transactionId;
        } else {
            $_SESSION["cembra_tx_id"] = "";
        }
        $byjunoLogger->addSOrderLog(Array(
            "order_id" => $order->cBestellNr,
            "order_status" => $order->cStatus,
            "request_type" => $requestChk->requestMsgType,
            "firstname" => $requestChk->custDetails->firstName,
            "lastname" => $requestChk->custDetails->lastName,
            "town" => $requestChk->billingAddr->town,
            "postcode" => $requestChk->billingAddr->postalCode,
            "street" => trim($requestChk->billingAddr->addrFirstLine),
            "country" => $requestChk->billingAddr->country,
            "ip" => byjunoGetClientIp(),
            "status" => (String)$status,
            "request_id" => $requestChk->requestMsgId,
            "type" => $type,
            "error" => $status,
            "response" => $response,
            "request" => $json,
            "transaction_id" => $cembrapayTrx
        ));
        return $redirect;
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
        if (isset($_GET["cembracancel"])) {
            $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
            return false;
        }
        $byjunoLogger = CembraLogger::getInstance();
        ByjunoBase::$SEND_MAIL = true;
        $autoInvoiceTxId = "";
        $cembraPayAzure = new CembraPayAzure();
        $cembraPayCommunicator = new CembraPayCommunicator($cembraPayAzure);
        $mode = 'test';
        if ($this->config->getOption("byjuno_mode")->value == 'live') {
            $cembraPayCommunicator->setServer('live');
            $mode = 'live';
        } else {
            $cembraPayCommunicator->setServer('test');
        }
        if ($this->config->getOption("cembra_plugin_mode")->value == 'checkout') {
            $order->cBestellNr = $_SESSION["cBestellNr"];
            $transactionId = $_SESSION["cembra_tx_id"];
            $requestTST = CembraConfirmTransaction($transactionId);
            $CembraPayRequestName = "Checkout confirmation";
            $json = $requestTST->createRequest();
            $response = $cembraPayCommunicator->sendConfirmTransactionRequest($json,
                CembraGetAccessDataWebshop($this->config, $mode),
                function ($object, $token, $accessData) {
                    CembraSaveToken($token, $accessData);
                });


            $transactionStatus = "";
            $responseRes = null;
            if ($response) {
                $responseRes = CembraConfirmTransactionResponse($response);
                if (!empty($responseRes->transactionStatus)) {
                    $transactionStatus = $responseRes->transactionStatus->transactionStatus;
                }
                $byjunoLogger->addSOrderLog(array(
                    "order_id" => $order->cBestellNr,
                    "order_status" => $order->cStatus,
                    "request_type" => "CNF",
                    "firstname" => "-",
                    "lastname" => "-",
                    "town" => "-",
                    "postcode" => "-",
                    "street" =>"-",
                    "country" => "-",
                    "ip" => byjunoGetClientIp(),
                    "status" => (string)$responseRes->processingStatus,
                    "request_id" => $requestTST->requestMsgId,
                    "type" => $CembraPayRequestName,
                    "error" => "",
                    "response" => $response,
                    "request" => $json,
                    "transaction_id" => $transactionId
                ));
            } else {
                $byjunoLogger->addSOrderLog(array(
                    "order_id" => $order->cBestellNr,
                    "order_status" => $order->cStatus,
                    "request_type" => "CNF",
                    "firstname" => "-",
                    "lastname" => "-",
                    "town" => "-",
                    "postcode" => "-",
                    "street" =>"-",
                    "country" => "-",
                    "ip" => byjunoGetClientIp(),
                    "status" => "ERROR",
                    "request_id" => $requestTST->requestMsgId,
                    "type" => $CembraPayRequestName,
                    "error" => "ERROR",
                    "response" => $response,
                    "request" => $json,
                    "transaction_id" => $transactionId
                ));
            }
            if (!empty($transactionStatus) && in_array($transactionStatus, CembraPayConstants::$CNF_OK_TRANSACTION_STATUSES)) {
                $autoInvoiceTxId = $transactionId;
            } else {
                $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
                return false;
            }
        } else {
            $handler = new OrderHandler(Shop::Container()->getDB(), Frontend::getCustomer(), Frontend::getCart());
            $order->cBestellNr = $handler->createOrderNo();
            try {
                $requestAuth = CreateJTLAuthShopRequest($order,
                    $_SESSION["byjuno_payment"],
                    $_SESSION["byjuno_send_method"],
                    $_SESSION["byjuno_gender"],
                    $_SESSION["byjuno_birthday"]);

                $type = "Auth Request";
                $b2b = $this->config->getOption("byjuno_b2b")->value == "true";
                if ($b2b && !empty($requestAuth->custDetails->companyName)) {
                    $type = "Auth Request B2B";
                }
                $json = $requestAuth->createRequest();


                $response = $cembraPayCommunicator->sendAuthRequest($json,
                    CembraGetAccessDataWebshop($this->config, $mode),
                    function ($object, $token, $accessData) {
                        CembraSaveToken($token, $accessData);
                    });
                $responseRes = null;
                if ($response) {
                    /* @var $responseRes CembraPayCheckoutAuthorizationResponse */
                    $responseRes = CembraAuthorizationResponse($response);
                    $status = $responseRes->processingStatus;
                } else {
                    $status = "ERROR";
                }
                $cembrapayTrx = "";
                if ($status == CembraPayConstants::$AUTH_OK) {
                    $cembrapayTrx = $responseRes->transactionId;
                    $autoInvoiceTxId = $cembrapayTrx;
                }
                $byjunoLogger->addSOrderLog(array(
                    "order_id" => $order->cBestellNr,
                    "order_status" => $order->cStatus,
                    "request_type" => $requestAuth->requestMsgType,
                    "firstname" => $requestAuth->custDetails->firstName,
                    "lastname" => $requestAuth->custDetails->lastName,
                    "town" => $requestAuth->billingAddr->town,
                    "postcode" => $requestAuth->billingAddr->postalCode,
                    "street" => trim($requestAuth->billingAddr->addrFirstLine),
                    "country" => $requestAuth->billingAddr->country,
                    "ip" => byjunoGetClientIp(),
                    "status" => (string)$status,
                    "request_id" => $requestAuth->requestMsgId,
                    "type" => $type,
                    "error" => $status,
                    "response" => $response,
                    "request" => $json,
                    "transaction_id" => $cembrapayTrx
                ));
                if ($status != CembraPayConstants::$AUTH_OK) {
                    $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
                    return false;
                }
            } catch (\Exception $e) {
                $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
                return false;
            }
        }
        if ($this->config->getOption("byjuno_s4")->value == "true"
            && $this->config->getOption("cembra_auto_invoice")->value == "true") {
            $invoiceNum = $order->cBestellNr;
            $requestInvoice = CreateShopRequestSettle($invoiceNum, $order->fGesamtsumme, $order->Waehrung->getCode(), $invoiceNum, $autoInvoiceTxId);

            $CembraPayRequestName = "Settle Request (auto)";

            $mode = $this->config->getOption("byjuno_mode")->value;
            $json = $requestInvoice->createRequest();
            $response = $cembraPayCommunicator->sendSettleRequest($json,
                CembraGetAccessDataWebshop($this->config, $mode),
                function ($object, $token, $accessData) {// your dynamic parameters
                    CembraSaveToken($token, $accessData);
                });
            $statusSet = "";
            $txSettle = "";
            if (isset($response)) {
                $responseRes = CembraPayConstants::settleResponse($response);
                $statusSet = $responseRes->processingStatus;
                if (!empty($responseRes->settlementId)) {
                    $txSettle = $responseRes->settlementId;
                }
            }
            $byjunoLogger->addSOrderLog(Array(
                "order_id" => $order->cBestellNr,
                "order_status" => "",
                "request_type" => CembraPayConstants::$MESSAGE_SET,
                "firstname" => "",
                "lastname" =>  "",
                "town" => "",
                "postcode" =>  "",
                "street" => "",
                "country" =>  "",
                "ip" => byjunoGetClientIp(),
                "status" => ($statusSet == "") ? "ERROR" : $statusSet,
                "request_id" => $requestInvoice->requestMsgId,
                "type" => $CembraPayRequestName,
                "error" => ($statusSet == "") ? "ERROR" : "",
                "response" => $response,
                "request" => $json,
                "transaction_id" => $txSettle
            ));
            if (empty($statusSet) || !in_array($statusSet, CembraPayConstants::$SETTLE_STATUSES)) {
                $_SESSION["BYJUNO_ERROR"] = $this->getText('byjuno_fail_message', "Payment Method Provider have refused selected payment method, please select different payment method.");
                return false;
            }
        }
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
                $requestCDP = CreateJTLScreeningShopRequest($customer, $cart, $delivery);
                if ($requestCDP->amount == 0) {
                    return false;
                }
                if (!empty($CDPStatus) && $this->isTheSame($requestCDP)) {
                    if ($CDPStatus == CembraPayConstants::$SCREENING_OK) {
                        return true;
                    }
                    return false;
                }
                if (!$this->isTheSame($requestCDP) || empty($CDPStatus)) {
                    $ByjunoRequestName = "Credit check request";
                    if ($requestCDP->custDetails->companyName != '') {
                        $ByjunoRequestName = "Credit check request for Company";
                    }
                    $jsonRequest = $requestCDP->createRequest();

                    $byjunoLogger = CembraLogger::getInstance();
                    $cembraPayAzure = new CembraPayAzure();
                    $cembraPayCommunicator = new CembraPayCommunicator($cembraPayAzure);
                    $mode = 'test';
                    if ($this->config->getOption("byjuno_mode")->value == 'live') {
                        $cembraPayCommunicator->setServer("live");
                        $mode = 'live';
                    } else {
                        $cembraPayCommunicator->setServer("test");
                    }
                    $response = $cembraPayCommunicator->sendScreeningRequest($jsonRequest,
                        CembraGetAccessDataWebshop($this->config, $mode),
                        function ($object, $token, $accessData) {
                            CembraSaveToken($token, $accessData);
                        });
                    $responseRes = null;
                    $status = 0;
                    if ($response) {
                        /* @var $responseRes CembraPayCheckoutAuthorizationResponse */
                        $responseRes = CembraScreeningResponse($response);
                        $status = $responseRes->processingStatus;
                    }

                    $isOk = false;
                    if ($status == CembraPayConstants::$SCREENING_OK) {
                        $byjunoLogger->addSOrderLog(Array(
                            "order_id" => "",
                            "order_status" => -1,
                            "request_type" => $requestCDP->requestMsgType,
                            "firstname" => $requestCDP->custDetails->firstName,
                            "lastname" => $requestCDP->custDetails->lastName,
                            "town" => $requestCDP->billingAddr->town,
                            "postcode" => $requestCDP->billingAddr->postalCode,
                            "street" => trim($requestCDP->billingAddr->addrFirstLine),
                            "country" => $requestCDP->billingAddr->country,
                            "ip" => byjunoGetClientIp(),
                            "status" => $status,
                            "request_id" => $requestCDP->requestMsgId,
                            "type" => $ByjunoRequestName,
                            "error" => "",
                            "response" => $response,
                            "request" => $jsonRequest,
                            "transaction_id" => ""
                        ));
                        $isOk = true;
                    } else {
                        $byjunoLogger->addSOrderLog(Array(
                            "order_id" => "",
                            "order_status" => -1,
                            "request_type" => $requestCDP->requestMsgType,
                            "firstname" => $requestCDP->custDetails->firstName,
                            "lastname" => $requestCDP->custDetails->lastName,
                            "town" => $requestCDP->billingAddr->town,
                            "postcode" => $requestCDP->billingAddr->postalCode,
                            "street" => trim($requestCDP->billingAddr->addrFirstLine),
                            "country" => $requestCDP->billingAddr->country,
                            "ip" => byjunoGetClientIp(),
                            "status" => $status,
                            "request_id" => $requestCDP->requestMsgId,
                            "type" => $ByjunoRequestName,
                            "error" => $status,
                            "response" => $response,
                            "request" => $jsonRequest,
                            "transaction_id" => ""
                        ));
                    }

                    $this->_savedUser = Array(
                        "FirstName" => $requestCDP->custDetails->firstName,
                        "LastName" => $requestCDP->custDetails->lastName,
                        "FirstLine" => $requestCDP->billingAddr->addrFirstLine,
                        "CountryCode" => $requestCDP->billingAddr->country,
                        "PostCode" => $requestCDP->billingAddr->postalCode,
                        "Town" => $requestCDP->billingAddr->town,
                        "CompanyName1" => $requestCDP->custDetails->companyName,
                        "DateOfBirth" => $requestCDP->custDetails->dateOfBirth,
                        "Email" => $requestCDP->custContacts->email,
                        "TelephonePrivate" => $requestCDP->custContacts->phoneMobile,
                        "TelephoneOffice" => $requestCDP->custContacts->phonePrivate,
                        "Gender" => $requestCDP->custDetails->salutation,
                        "Amount" => $requestCDP->amount,
                        "DELIVERY_FIRSTNAME" => $requestCDP->deliveryDetails->deliveryFirstName,
                        "DELIVERY_LASTNAME" => $requestCDP->deliveryDetails->deliverySecondName,
                        "DELIVERY_FIRSTLINE" => $requestCDP->deliveryDetails->deliveryAddrFirstLine,
                        "DELIVERY_COUNTRYCODE" => $requestCDP->deliveryDetails->deliveryAddrCountry,
                        "DELIVERY_POSTCODE" => $requestCDP->deliveryDetails->deliveryAddrPostalCode,
                        "DELIVERY_TOWN" => $requestCDP->deliveryDetails->deliveryAddrTown,
                        "DELIVERY_COMPANYNAME" => $requestCDP->deliveryDetails->deliveryCompanyName
                    );
                    $_SESSION["byjuno_cdp"] = $this->_savedUser;
                    $_SESSION["byjuno_cdp_status"] = $status;
                    if (!$isOk) {
                        return false;
                    }
                }
            } catch (\Exception $e) {
            }
        }
        return true;
    }

    public function isTheSame(CembraPayCheckoutAutRequest $request) {

        if ($request->custDetails->firstName != $this->_savedUser["FirstName"]
            || $request->custDetails->lastName != $this->_savedUser["LastName"]
            || $request->billingAddr->addrFirstLine != $this->_savedUser["FirstLine"]
            || $request->billingAddr->country != $this->_savedUser["CountryCode"]
            || $request->billingAddr->postalCode != $this->_savedUser["PostCode"]
            || $request->billingAddr->town != $this->_savedUser["Town"]
            || $request->custDetails->companyName != $this->_savedUser["CompanyName1"]
            || $request->custDetails->dateOfBirth != $this->_savedUser["DateOfBirth"]
            || $request->custContacts->email != $this->_savedUser["Email"]
            || $request->custContacts->phoneMobile != $this->_savedUser["TelephonePrivate"]
            || $request->custContacts->phonePrivate != $this->_savedUser["TelephoneOffice"]
            || $request->custDetails->salutation != $this->_savedUser["Gender"]
            || $request->amount != $this->_savedUser["Amount"]
            || $request->deliveryDetails->deliveryFirstName != $this->_savedUser["DELIVERY_FIRSTNAME"]
            || $request->deliveryDetails->deliverySecondName != $this->_savedUser["DELIVERY_LASTNAME"]
            || $request->deliveryDetails->deliveryAddrFirstLine != $this->_savedUser["DELIVERY_FIRSTLINE"]
            || $request->deliveryDetails->deliveryAddrCountry != $this->_savedUser["DELIVERY_COUNTRYCODE"]
            || $request->deliveryDetails->deliveryAddrPostalCode != $this->_savedUser["DELIVERY_POSTCODE"]
            || $request->deliveryDetails->deliveryAddrTown != $this->_savedUser["DELIVERY_TOWN"]
            || $request->deliveryDetails->deliveryCompanyName != $this->_savedUser["DELIVERY_COMPANYNAME"]
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