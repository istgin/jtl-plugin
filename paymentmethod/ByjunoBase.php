<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use Exception;
use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Checkout\ZahlungsInfo;
use JTL\Helpers\Text;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\Helper as PluginHelper;
use stdClass;


class ByjunoBase extends Method
{
    public const PLUGIN_ID = 'byjuno';

    var $paymethod = '';
    /** @var    mcpay $McPay */
    var $McPay;
    //var $debug       = FALSE;
    var $localeTexts = array();

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
            $_SESSION["byjuno_error_msg"] =  "Birthday is incorrect";
            return false;
        } else {
            $_SESSION["byjuno_gender"] = $_POST["byjuno_year"].'-'.$_POST["byjuno_month"].'-'.$_POST["byjuno_day"];
        }

        if (empty($_POST["byjuno_payment"])) {
            $_SESSION["byjuno_error_msg"] =  "Please select repayment";
            return false;
        } else {
            $_SESSION["byjuno_payment"] = $_POST["byjuno_payment"];
        }

        if (empty($_POST["byjuno_send_method"])) {
            $_SESSION["byjuno_error_msg"] =  "Please select invoice delivery method";
            return false;
        } else {
            $_SESSION["byjuno_send_method"] = $_POST["byjuno_send_method"];
        }
       // $_SESSION["byjuno_error_msg"]  = "XXX";
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
        $result = false;
        if (!empty($_SESSION["byjuno_error_msg"])) {
            $smarty->assign('byjuno_error', $_SESSION["byjuno_error_msg"]);
            $_SESSION["byjuno_error_msg"] = "";
            $result = false;
        } else if (!isset($_POST["byjuno_form"])) {
            $result = false;
        } else {
            $result = true;
        }
        $smarty->assign('byjuno_iframe', "show fields");
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
          `cStatus` = "'.$status.'",
          `cAbgeholt` = "N"
          ';
            if (!empty($comment)) {
                //$sql .= ', `cKommentar` = "'.addslashes(utf8_decode($comment)).'" ';
                $sql .= ', `cKommentar` = "'.addslashes(($comment)).'" ';
            }
            $sql.= 'WHERE `cBestellNr` = "'.addslashes($orderId).'"';
            if ($this->McPay->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->sql: '.print_r($sql, true));
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
        $custID  = $_SESSION['Kunde']->kKunde;
        $orderId = $order->cBestellNr;
        if (empty($orderId)) $orderId = baueBestellnummer();

        if (empty($_SESSION['Kunde'])) { // guest order
            $custID = 'guest-'.$orderId;
        } else {
            $orderId .= '-'.$custID;
        }
      //  $custID = $this->McPay->unifyID($custID);

        $currency = $_SESSION['Waehrung']->getCode(); //cISO;
        $amount   = $order->fGesamtsummeKundenwaehrung; // In customer currency
        if (empty($amount)) $amount = $_SESSION["Warenkorb"]->gibGesamtsummeWaren(true);
        $amount   = round($amount * 100);

        $user     = $_SESSION['Kunde'];
        $oPlugin  = $this->getPluginObj($order);
        $chksumData = array(
            'amount'        => $amount,
            'currency'      => $currency,
            'iso'           => $user->cLand,
            'host'          => $_SERVER['HTTP_HOST'],
            'project'       => $config['project'],
            'shoptype'      => 'jtl',
            'shopversion'   => (string)(JTL_VERSION/100).'.'.JTL_MINOR_VERSION,
            'plugintype'    => 'API',
            'pluginversion' => implode('.', str_split((string)$oPlugin->nVersion)),
        );
        $userData = array(
            'company'    => $user->cFirma,
            'firstName'  => $user->cVorname,
            'lastName'   => $user->cNachname,
            'salutation' => $user->cAnrede == 'm' ? 'MR' : 'MRS',
            'street'     => $user->cStrasse.' '.$user->cHausnummer,
            'zip'        => $user->cPLZ,
            'city'       => $user->cOrt,
            'country'    => $user->cLand,
            'email'      => $user->cMail,
            'ip'         => '', //$_SESSION['oBesucher']->cIP,
            'id'         => "XXXX",
            'order_id'   => $orderId,
            'lang'       => $config['lang'],
            'amount'     => $amount,
            'currency'   => $currency,
            'chksum'     => $chksumData, // request security
        );
        if (empty($userData['ip'])) $userData['ip'] = $_SERVER['REMOTE_ADDR']; // Falls IP Leer, dann aus dem Server holen
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$userData', print_r($userData,true));
        return $userData;
    }

    /**
     * handleNotification
     *
     * @param \Bestellung $order
     * @param string      $paymentHash
     * @param array       $args
     * @param bool        $returnURL
     *
     * @return string|void
     */

    public function addS4Log($orderId, $request_type)
    {
        $byjunoOrder = new stdClass();
        $byjunoOrder->order_id = (string)$orderId;// varchar(250) default NULL,
        $byjunoOrder->request_type = $request_type;// varchar(250) default NULL,
        $byjunoOrder->firstname = '1';// varchar(250) default NULL,
        $byjunoOrder->lastname = '1';// varchar(250) default NULL,
        $byjunoOrder->town = '1';// varchar(250) default NULL,
        $byjunoOrder->postcode = '1';// varchar(250) default NULL,
        $byjunoOrder->street = '1';// varchar(250) default NULL,
        $byjunoOrder->country = '1';// varchar(250) default NULL,
        $byjunoOrder->ip = '1';// varchar(250) default NULL,
        $byjunoOrder->status = '1';// varchar(250) default NULL,
        $byjunoOrder->request_id = '1';// varchar(250) default NULL,
        $byjunoOrder->type = '1';// varchar(250) default NULL,
        $byjunoOrder->error = '1';// text default NULL,
        $byjunoOrder->response = '1';// text default NULL,
        $byjunoOrder->request = '1';// text default NULL,
       // $byjunoOrder->dLetzterBlock = 'NOW()';
        Shop::Container()->getDB()->insert('xplugin_byjyno_orders', $byjunoOrder);
    }

    function handleNotification(Bestellung $order, string $paymentHash, array $args, bool $returnURL = FALSE): void
    {
        if (!empty($_SESSION["change_paid"])) {
            $this->addS4Log($order->kBestellung, "S3");
            return;
          //  $this->setOrderStatusToPaid($order);
        }
        $crlf = "\r\n";
        //$args = func_get_args();
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$args', print_r($args, true).print_r($order,true));
        if ($this->McPay->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->args: '.print_r($args, true));

        $orderID = $order->cBestellNr;
        if (empty($orderID) && !empty($args['orderid'])) {
            $orderID = $args['orderid'];
            if (strpos($orderID, '-')) {
                $p       = explode('-', $orderID);
                $orderID = $p[0];
            }
        }

        $trxID = $args['transactionId'];
        if (empty($trxID) || $trxID == '__transactionId__'){
            $trxID = $args['auth'];
        }

        $incomingPayment           = new stdClass();
        $incomingPayment->fBetrag  = $order->fGesamtsummeKundenwaehrung;
        $incomingPayment->cISO     = $order->Waehrung->cISO;
        $incomingPayment->cHinweis = $trxID;

        $return = 'status=ok'.$crlf;

        switch($args['function']){
            case 'custinfo':
                break;
            case 'billing':
                $url   = $this->getReturnURL($order);

                $this->addIncomingPayment($order, $incomingPayment);
                $this->setOrderStatusToPaid($order);
                $this->sendConfirmationMail($order);
                $this->updateNotificationID($order->kBestellung, $paymentHash);

                $return = 'status=ok'.$crlf // ok  | error
                    .'url='.$url.$crlf
                    .'target=_top'.$crlf
                    .'forward=1'.$crlf
                    .'message=OK'.$crlf;
                break;

            case 'storno':
                $incomingPayment->fBetrag  = ($args['amount'] / 100) * -1;
                $incomingPayment->cISO     = $args['currency'];

                $this->addIncomingPayment($order, $incomingPayment);
                $this->setStatus(BESTELLUNG_STATUS_STORNO, $orderID);
                break;

            case 'backpay':
                $incomingPayment->fBetrag  = ($args['amount'] / 100);
                $incomingPayment->cISO     = $args['currency'];

                $this->addIncomingPayment($order, $incomingPayment);
                $this->setStatus(BESTELLUNG_STATUS_IN_BEARBEITUNG, $orderID);
                break;

            case 'refund':
                $incomingPayment->fBetrag  = ($args['amount'] / 100) * -1;
                $incomingPayment->cISO     = $args['currency'];

                $this->addIncomingPayment($order, $incomingPayment);
                $this->setStatus(BESTELLUNG_STATUS_IN_BEARBEITUNG, $orderID);
                break;

            case 'quit':
                $incomingPayment->fBetrag  = $order->fGesamtsummeKundenwaehrung * -1;
                $incomingPayment->cISO     = $order->Waehrung->cISO;

                $this->addIncomingPayment($order, $incomingPayment);
                $this->setStatus(BESTELLUNG_STATUS_STORNO, $orderID);
                break;

            case 'init': // prepay
                /*
                $incomingPayment->fBetrag  = ($args['amount'] / 100);
                $incomingPayment->cISO     = $args['currency'];

                $this->addIncomingPayment($order, $incomingPayment);
                */
                $this->setStatus(BESTELLUNG_STATUS_IN_BEARBEITUNG, $orderID);
                break;
        }
        if ($this->McPay->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->return: '.print_r($return,true));

        if ($returnURL){
            //header('Location: '.$return->forwardURL);
        }

        echo $return;
    }

    /**
     * preparePaymentProcess
     *
     * @param \Bestellung $order
     */
    public function preparePaymentProcess(Bestellung $order): void
    {
        parent::preparePaymentProcess($order);
        /*
        exit('preparePaymentProcess MAIN');
        $config = $this->getPluginConf($order);
        //echo '<pre>'.print_r($config,true).'</pre>';
        //echo '<pre>'.print_r($order,true).'</pre>';
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$order', print_r($order,true));
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$this', print_r($this,true));
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->Warenkorb', print_r($_SESSION['Warenkorb'],true));

//    global $Einstellungen;
//    if ($Einstellungen['kaufabwicklung']['bestellabschluss_abschlussseite'] !== 'A') {
//      if ($this->info->nWaehrendBestellung == 0){
//        die(
//          'Bitte Abschlussseite nach externer Bezahlung auf Abschlussseite stellen. <br>'.
//          '(Storefront -> Kaufabwicklung -> Warenkorb/Kaufabwicklung -> Bestellabschluss -> Abschlussseite nach externer Bezahlung)<br><br>'.
//          'oder<br><br> Zahlung vor Bestellabschluss im Zahlverfahren auf Ja stellen.<br>'.
//          '(Storefront -> Zahlarten -> Übersicht -> '.$this->info->cName.')'
//        );
//      }
//    }

        if ($order) {
            $userData = $this->getUserDataFromOrder($order, $config);

            //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$userData', print_r($userData,true));
            //echo '<pre>'.print_r($userData, true).'</pre>';

            $hash = $this->generateHash($order);
            if ($order->cId != '') $hash = $order->cId;

            $notifyURL                         = $this->getNotificationURL($hash);
            $config['returnURL']               = $notifyURL.'&mcpaySessID=';
            $config['returnURLNO3D']           = $notifyURL.'&mcpaySessID=';
            $key                               = ($this->duringCheckout) ? 'sh' : 'ph';
            $config['extraParams'][$key]       = $hash;
            $config['extraParams']['sessName'] = session_name();
            $config['extraParams']['sessId']   = session_id();

            $url = $this->McPay->getPaymentWindowParams($userData, $config, $this->pm);
            //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$url', print_r($url, true));
            $this->McPay->log->debug($notifyURL);

            if (!empty($url)) {
                header('Location: '.$url);
                exit();
            }
        }
        */
    }

    /**
     * redirectOnPaymentSuccess
     *
     * @return bool
     */
    public function redirectOnPaymentSuccess(): bool
    {
        //$args = func_get_args();
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$args', print_r($args,true));
        return FALSE;
    }

    /**
     * redirectOnCancel
     *
     * @return bool
     */
    public function redirectOnCancel(): bool
    {
        //$args = func_get_args();
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$args', print_r($args,true));
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
        //$args = func_get_args();
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$args', print_r($args,true));
        return parent::getReturnURL($order);
    }

    /**
     * finalizeOrder
     *
     * @param \Bestellung $order
     * @param string      $hash
     * @param array       $args
     *
     * @return bool|true
     */
    public function finalizeOrder(Bestellung $order, string $hash, array $args): bool
    {
        $order->cBestellNr = getOrderHandler()->createOrderNo();
        // --  HOOK_BESTELLVORGANG_PAGE_STEPZAHLUNG on hook show error!!!
        $_SESSION["change_paid"] = true;
       // exit();
        //$args = func_get_args();
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$args', print_r($args,true).print_r($_POST,true));

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

//    $kPlugin = gibkPluginAuscModulId($myZV);
//    if ($kPlugin > 0) {
//      $oPlugin = new Plugin($kPlugin);
//    } else {
//      return FALSE;
//    }
//    return $oPlugin;

        $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
        $plugin   = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);

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
        if (is_object($oPlugin)){
            $plugin   = $oPlugin;
        } else {
            $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
            $plugin   = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
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
        $conf['plugin_url']     = $paths->getFrontendURL();
        $conf['plugin_url']     = str_replace('frontend', 'mcpay', $conf['plugin_url']);
        $conf['plugin_url']     = str_replace('http://', $_SERVER['REQUEST_SCHEME'].'://', $conf['plugin_url']);
        $conf['plugin_path']    = $paths->getFrontendPath();
        $conf['plugin_path']    = str_replace('frontend', 'mcpay', $conf['plugin_path']);
        $conf['plugin_shopurl'] = $paths->getShopURL();
        $conf['plugin_shopurl'] = str_replace('http://', $_SERVER['REQUEST_SCHEME'].'://', $conf['plugin_shopurl']);
        if (empty($conf['payformid'])) {
            $conf['payformid'] = 'form_payment_extra'; // jtl default
        }

        //echo '<pre>'.print_r($conf,true).'</pre>';
        //echo '<pre>'.print_r($oPlugin,true).'</pre>';
        $lang = $_SESSION['cISOSprache'] == 'ger' ? 'DE' : 'EN';

        $config = array(
            /*
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

    /**
     * loadTexts
     *
     */
    function loadTexts()
    {
//    $kPlugin = gibkPluginAuscModulId($this->cModulId);
//    if ($kPlugin > 0) {
//      $oPlugin = new Plugin($kPlugin);
//    }
//    $texts = $oPlugin->oPluginSprachvariableAssoc_arr;

        $pluginID = PluginHelper::getIDByModuleID($this->moduleID);
        $plugin   = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
        $texts = $plugin->getLocalization()->getTranslations();

        //echo '<pre>'.print_r($texts,true).'</pre>';
        $this->setTexts($texts);
    }

    /**
     * setTexts
     *
     * @param $texts
     */
    function setTexts($texts)
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
    function getText($textName, $default)
    {
        if (empty($this->localeTexts[$textName])) {
            return $default;
        }
        return $this->localeTexts[$textName];
    }

    /**
     * getStyleAction
     *
     * @param $oPlugin
     *
     * @return bool
     */
    function getStyleAction($oPlugin)
    {
        header('Content-Type: text/css');
        $req = $_REQUEST;
        if (empty($req['sn'])) {
            if ($this->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->Req empty: '.print_r($req['sn'],true));
            echo '403';
            return FALSE;
        }

        $config = $this->getPluginConf('', $oPlugin);
        //echo '<pre>'.print_r($config,true).'</pre>';
        $filename = $config['plugin_pathcss'].$req['sn'].'';
        if (!file_exists($filename)) {
            if ($this->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->File not found: '.print_r($filename,true));
            echo '404';
            return FALSE;
        }

        $content = file_get_contents($filename);

        if (strpos($filename, 'mcpay_formstyle_') !== FALSE) {
            $repl    = array(
                //"url('../fonts/" => "url('".$config['plugin_urlfont'],
                'url("../img/icon_' => 'url("'.$config['plugin_urlcss'].'../img/icon_',
            );
            $content = strtr($content, $repl);
        }
        echo $content;
    }

    /**
     * getScriptAction
     *
     * @param $oPlugin
     *
     * @return bool
     */
    public function getScriptAction($oPlugin)
    {
        header('Content-Type: application/javascript');
        $req = $_REQUEST;
        if (empty($req['sn'])) {
            if ($this->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->Req empty: '.print_r($req['sn'],true));
            echo '403';
            return FALSE;
        }

        $config = $this->getPluginConf('', $oPlugin);
        //echo '<pre>'.print_r($config,true).'</pre>';
        $filename = $config['plugin_pathjs'].$req['sn'].'';
        if (!file_exists($filename)) {
            if ($this->debug) $this->McPay->log->debug(__CLASS__.'->'.__FUNCTION__.'->File not found: '.print_r($filename,true));
            echo '404';
            return FALSE;
        }

        $content = file_get_contents($filename);
        if (strpos($filename, 'executer.js') !== FALSE) {
            $repl    = array(
                'dt_method_mcpay_ccard'                => $config['ccardformid'],
                'dt_method_mcpay_sepa'                 => $config['sepaformid'],
                '"/xxx/payment/getscript/sn/' => '"'.$config['plugin_urlshop'].'/xxx?sn=',
            );
            $content = strtr($content, $repl);
        }
        if (strpos($filename, 'check.js') !== FALSE) {
            $repl    = array(
                'payformid'             => $config['payformid'],
                'mcpay_card_token-form' => $config['payformid'],
            );
            $content = strtr($content, $repl);
        }

        echo $content;
    }

    /**
     * setPaydata
     *
     * @param $paymethod
     * @param $paydata
     *
     * @return array|int|object
     */
    function setPaydata($paymethod, $paydata)
    {
        $userId = $_SESSION['Kunde']->kKunde;
        // set brand
        if (!empty($paydata['CardPan'])) {
            $cardType = 'visa';
            if (substr($paydata['CardPan'], 0, 1) == '5') $cardType = 'mastercard';
            $paydata['CardBrand'] = $cardType;
        }

        if (!empty($paydata['TrxResult'])) {
            unset($paydata['TrxResult']);
        }

        // check if entry for paymethod and user already exists and update the old one
        $sql = 'SELECT * FROM xplugin_mcpay_mircopayment_paydata 
            WHERE `user_id` = "'.(int)$userId.'" 
            AND `paymethod` = "'.addslashes($paymethod).'"
            ';
        $res = Shop::Container()->getDB()->query($sql, 2);
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$res', print_r($res,true));

        if (!empty($res)) {
            $sql = 'UPDATE xplugin_mcpay_mircopayment_paydata 
              SET `data` = "'.addslashes(serialize($paydata)).'" 
              WHERE `user_id` = "'.(int)$userId.'" 
              AND `paymethod` = "'.addslashes($paymethod).'"
            ';
        } else {
            $sql = 'INSERT INTO xplugin_mcpay_mircopayment_paydata 
              SET `user_id` = "'.(int)$userId.'",
              `paymethod` = "'.addslashes($paymethod).'",
              `data` = "'.addslashes(serialize($paydata)).'" 
            ';
        }
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$sql', print_r($sql,true));
        return Shop::Container()->getDB()->query($sql, 2);
    }

    /**
     * getPaydata
     *
     * @param $paymethod
     *
     * @return array|int|object
     */
    function getPaydata($paymethod)
    {
        $userId = $_SESSION['Kunde']->kKunde;
        $sql    = 'SELECT * FROM xplugin_mcpay_mircopayment_paydata 
            WHERE `user_id` = "'.(int)$userId.'"
            AND `paymethod` = "'.addslashes($paymethod).'" 
            ';
        //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$sql', print_r($sql,true));
        $paydata = Shop::Container()->getDB()->query($sql, 9);
        return $paydata;
    }


}

?>