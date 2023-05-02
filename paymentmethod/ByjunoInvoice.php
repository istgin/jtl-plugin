<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Session\Frontend;
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
    $byjuno_invoice = false;
    if ($this->config->getOption("byjuno_invoice")->value == 'true' || $this->config->getOption("byjuno_single_invoice")->value == 'true') {
      $byjuno_invoice = true;
    }
    if (!$byjuno_invoice) {
      return $byjuno_invoice;
    }
    $response = $this->CDPRequest();
    return $response;
  }

  public function preparePaymentProcess(Bestellung $order): void
  {
    $hash = $this->generateHash($order);
    $returUrl = $this->getNotificationURL($hash);
    parent::preparePaymentProcess($order);
    header('location:'.$returUrl);
    exit();
  }
}
