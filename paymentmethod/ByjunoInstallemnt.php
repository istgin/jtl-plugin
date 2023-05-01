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
        $byjuno_installment = false;
        if ($this->config->getOption("byjuno_3_installments")->value == "true"
            || $this->config->getOption("byjuno_36_installments")->value == "true"
            || $this->config->getOption("byjuno_12_installments")->value == "true"
            || $this->config->getOption("byjuno_24_installments")->value == "true"
            || $this->config->getOption("byjuno_4_installments_12_months")->value == "true"
        ) {
            $byjuno_installment = true;
        }
        if (!$byjuno_installment) {
            return $byjuno_installment;
        }
        return $this->CDPRequest();
    }
}