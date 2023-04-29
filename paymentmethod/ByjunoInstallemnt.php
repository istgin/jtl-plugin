<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use JTL\Checkout\Bestellung;
use stdClass;

class ByjunoInstallemnt extends ByjunoBase
{
  var $pm = 'byjyno_installment'; // important for event url
  var $paymethod = 'byjyno_installment_api';

  /**
   * preparePaymentProcess
   *
   * @param \JTL\Checkout\Bestellung $order
   *
   * @throws \Exception
   */
  public function preparePaymentProcess(Bestellung $order): void
  {
    global $smarty;
  }

  /**
   * handleNotification
   *
   * @param \JTL\Checkout\Bestellung $order
   * @param string                   $paymentHash
   * @param array                    $args
   * @param bool                     $returnURL
   */
    public function isSelectable() : bool {
        return $this->CDPRequest();
    }
}