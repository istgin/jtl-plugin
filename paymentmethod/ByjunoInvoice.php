<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Shop;
use stdClass;
use Exception;


class ByjunoInvoice extends ByjunoBase
{
  var $pm = 'byjuno_invoice';
  var $paymethod = 'byjuno_invoice_api';


  public function redirectOnPaymentSuccess(): bool
  {
    //$args = func_get_args();
    return true;
  }

  /**
   * redirectOnCancel
   *
   * @return bool
   */
  public function redirectOnCancel(): bool
  {
    //$args = func_get_args();
    return true;
  }

  public function isSelectable() : bool {
    //TODO CDP
    return true;
  }

  public function preparePaymentProcess(Bestellung $order): void
  {
    $config = $this->getPluginConf($order);
    $linkHelper = Shop::Container()->getLinkService();
    if (false) {
      Shop::Container()->getAlertService()->addAlert(
          Alert::TYPE_ERROR,
          "XXXX",
          'paymentFailed'
      );
      return;
      \header('Location: ' . $linkHelper->getStaticRoute('bestellvorgang.php'), true, 303);
      exit();
    } else {
      $hash = $this->generateHash($order);
      $returUrl = $this->getNotificationURL($hash);
      header('location:'.$returUrl);
      exit();
    }
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
      $linkHelper = Shop::Container()->getLinkService();
      $notifyURL                         = $this->getNotificationURL($hash);
      $returUrl                         = $this->getReturnURL($order);
      $config['returnURL']               = $notifyURL.'&mcpaySessID=';
      $config['returnURLNO3D']           = $notifyURL.'&mcpaySessID=';
      $key                               = ($this->duringCheckout) ? 'sh' : 'ph';
      $config['extraParams'][$key]       = $hash;
      $config['extraParams']['sessName'] = session_name();
      $config['extraParams']['sessId']   = session_id();
    //  var_dump($returUrl);
   //   exit();
      parent::preparePaymentProcess($order);
      /*

     // $url = $this->McPay->getPaymentWindowParams($userData, $config, $this->pm);
      //mail('webmaster@web-dezign.de', __CLASS__.'->'.__FUNCTION__.'->$url', print_r($url, true));
     // $this->McPay->log->debug($notifyURL);

      if (!empty($url)) {
        header('Location: '.$url);
        exit();
      }
      */
    }
  }
}

?>