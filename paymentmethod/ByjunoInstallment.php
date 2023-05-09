<?php
declare(strict_types=1);

namespace Plugin\byjuno\paymentmethod;

use JTL\Checkout\Bestellung;
use JTL\Session\Frontend;

class ByjunoInstallment extends ByjunoBase
{
    var $pm = 'byjyno_installment'; // important for event url
    var $paymethod = 'byjyno_installment_api';


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

    /**
     * handleNotification
     *
     * @param \JTL\Checkout\Bestellung $order
     * @param string $paymentHash
     * @param array $args
     * @param bool $returnURL
     */
    public function isSelectable(): bool
    {
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
        if (!$this->sumIsInRange()) {
            return false;
        }
        return $this->CDPRequest();
    }

    public function preparePaymentProcess(Bestellung $order): void
    {
        $hash = $this->generateHash($order);
        $returUrl = $this->getNotificationURL($hash);
        parent::preparePaymentProcess($order);
        header('location:' . $returUrl);
        exit();
    }

}