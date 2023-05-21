<?php

/**
 * array (
0 => 181,
'status' => 3,
'oBestellung' =>
(object) array(
'kBestellung' => 23,
'kWarenkorb' => 23,
'kKunde' => 1,
'kLieferadresse' => 0,
'kRechnungsadresse' => 23,
'kZahlungsart' => 107,
'kVersandart' => 7,
'kSprache' => 1,
'kWaehrung' => 2,
'fGuthaben' => '0',
'fGesamtsumme' => '107.25',
'cSession' => '84e2cbtjsk7ca2pvbvu0bll796',
'cVersandartName' => 'DHL',
'cZahlungsartName' => 'Byjuno Invoice',
'cBestellNr' => '1504202307341684e2',
'cVersandInfo' => '',
'nLongestMinDelivery' => '2',
'nLongestMaxDelivery' => '3',
'dVersandDatum' => NULL,
'dBezahltDatum' => NULL,
'dBewertungErinnerung' => NULL,
'cTracking' => '',
'cKommentar' => NULL,
'cLogistiker' => '',
'cTrackingURL' => '',
'cIP' => '',
'cAbgeholt' => 'Y',
'cStatus' => 2,
'dErstellt' => '2023-04-15 05:34:16',
'fWaehrungsFaktor' => '1',
'cPUIZahlungsdaten' => '',
),
)
 */

use JTL\Plugin\Helper as PluginHelper;
use JTL\Shop;
use JTL\Catalog\Currency;
use Plugin\byjuno\paymentmethod\ByjunoBase;

$byjunoPlugin  = PluginHelper::getPluginById(ByjunoBase::PLUGIN_ID);
if ($byjunoPlugin === null) {
    exit('Byjuno payment plugin cant be found.');
}
$byjunoConfig = $byjunoPlugin->getConfig();
try {
    $arr = isset($args_arr) ? $args_arr : [];
    if (!empty($arr) && is_array($arr) && !empty($arr["oBestellung"])) {
        $order = $arr["oBestellung"];
        $debug = var_export($order, true);
        if (!empty($order->kBestellung)) {
            $byjunoOrder = Shop::Container()->getDB()->select('xplugin_byjyno_orders', ['order_id', 'request_type'], [$order->cBestellNr, 'S3']);
            if (!empty($byjunoOrder) && $byjunoOrder->request_type == 'S3') {
                $invoiceNum = $order->cBestellNr;
                $currency = new Currency($order->kWaehrung);
                $amount = $order->fGesamtsumme;
                if (!empty($order->kKunde)) {
                    $customerId = $order->kKunde;
                } else {
                    $customerId = "guest";
                }
                $time = time();
                $dt = date("Y-m-d", $time);

                if ($byjunoConfig->getOption("byjuno_s5_refund")->value == "true") {
                    $byjunoOrderS4 = Shop::Container()->getDB()->select('xplugin_byjyno_orders', ['order_id', 'request_type'], [$order->cBestellNr, 'S4']);
                    if (!empty($byjunoOrderS4)) {
                        $s5RefundTriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s5_refund_trigger")->value);
                        if (!empty($s5RefundTriggerStatus) && $s5RefundTriggerStatus == $order->cStatus) {
                            $requestS5Refund = CreateShopRequestS5Refund($invoiceNum, $amount, $currency->getCode(), $invoiceNum, $customerId, $dt);
                            $xmlRequestS5Refund = $requestS5Refund->createRequest();
                            $byjunoCommunicator = new ByjunoCommunicator();
                            if ($byjunoConfig->getOption("byjuno_mode")->value == 'live') {
                                $byjunoCommunicator->setServer("live");
                            } else {
                                $byjunoCommunicator->setServer("test");
                            }
                            $responseS5Refund = $byjunoCommunicator->sendS4Request($xmlRequestS5Refund, intval($byjunoConfig->getOption("byjuno_timeout")->value));
                            $statusLog = "S5 Refund Request";
                            $statusS5Refund = "ERR";
                            if (isset($responseS5Refund)) {
                                $byjunoResponseS5Refund = new ByjunoS4Response();
                                $byjunoResponseS5Refund->setRawResponse($responseS5Refund);
                                $byjunoResponseS5Refund->processResponse();
                                $statusS5Refund = $byjunoResponseS5Refund->getProcessingInfoClassification();
                            }
                            $byjunoLogger = ByjunoLogger::getInstance();
                            $byjunoLogger->addSOrderLog(Array(
                                "order_id" => $order->cBestellNr,
                                "order_status" => $order->cStatus,
                                "request_type" => "S5 Refund",
                                "firstname" => "",
                                "lastname" => "",
                                "town" => "",
                                "postcode" => "",
                                "street" => "",
                                "country" => "",
                                "ip" => byjunoGetClientIp(),
                                "status" => $statusS5Refund,
                                "request_id" => $requestS5Refund->getRequestId(),
                                "type" => $statusLog,
                                "error" => $statusS5Refund,
                                "response" => $responseS5Refund,
                                "request" => $xmlRequestS5Refund
                            ));
                        }
                    }
                }

                if ($byjunoConfig->getOption("byjuno_s5_cancel")->value == "true") {
                    $s5CancelTriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s5_cancel_trigger")->value);
                    if (!empty($s5CancelTriggerStatus) && $s5CancelTriggerStatus == $order->cStatus) {
                        $requestS5Cancel= CreateShopRequestS5Cancel($amount, $currency->getCode(), $invoiceNum, $customerId, $dt);
                        $xmlRequestS5Cancel = $requestS5Cancel->createRequest();
                        $byjunoCommunicator = new ByjunoCommunicator();
                        if ($byjunoConfig->getOption("byjuno_mode")->value == 'live') {
                            $byjunoCommunicator->setServer("live");
                        } else {
                            $byjunoCommunicator->setServer("test");
                        }
                        $responseS5Cancel = $byjunoCommunicator->sendS4Request($xmlRequestS5Cancel, intval($byjunoConfig->getOption("byjuno_timeout")->value));
                        $statusLog = "S5 Cancel Request";
                        $statusS5Cancel = "ERR";
                        if (isset($responseS5Cancel)) {
                            $byjunoResponseS5Cancel = new ByjunoS4Response();
                            $byjunoResponseS5Cancel->setRawResponse($responseS5Cancel);
                            $byjunoResponseS5Cancel->processResponse();
                            $statusS5Cancel = $byjunoResponseS5Cancel->getProcessingInfoClassification();
                        }
                        $byjunoLogger = ByjunoLogger::getInstance();
                        $byjunoLogger->addSOrderLog(Array(
                            "order_id" => $order->cBestellNr,
                            "order_status" => $order->cStatus,
                            "request_type" => "S5 Cancel",
                            "firstname" => "",
                            "lastname" =>  "",
                            "town" => "",
                            "postcode" =>  "",
                            "street" => "",
                            "country" =>  "",
                            "ip" => byjunoGetClientIp(),
                            "status" => $statusS5Cancel,
                            "request_id" => $requestS5Cancel->getRequestId(),
                            "type" => $statusLog,
                            "error" => $statusS5Cancel,
                            "response" => $responseS5Cancel,
                            "request" => $xmlRequestS5Cancel
                        ));
                    }
                }
            }
        }
    }
} catch (Exception $e) {
}