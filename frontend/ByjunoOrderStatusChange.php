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
        if (!empty($order->kBestellung)) {
            $byjunoOrder = Shop::Container()->getDB()->select('xplugin_byjyno_orders', ['order_id', 'request_type'], [$order->kBestellung, 'S3']);
            if (!empty($byjunoOrder) && $byjunoOrder->request_type == 'S3') {
                if ($order->cStatus != $byjunoOrder->order_status) {
                    if ($byjunoConfig->getOption("byjuno_s4")->value == "true") {
                        $s4TriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s4_trigger")->value);
                        if (!empty($s4TriggerStatus) && $s4TriggerStatus == $order->cStatus) {
                            $invoiceNum = $order->kBestellung;
                            $currency = $order->Waehrung->getCode();
                            $amount = $order->fGesamtsumme;
                            if (!empty($order->kKunde)) {
                                $customerId = $order->kKunde;
                            } else {
                                $customerId = "guest";
                            }
                            $time = time();
                            $dt = date("Y-m-d", $time);
                            $requestInvoice = CreateShopRequestS4($invoiceNum, $amount, $amount, $currency, $invoiceNum, $customerId, $dt);
                            $xmlRequestS4 = $requestInvoice->createRequest();
                            $byjunoCommunicator = new ByjunoCommunicator();
                            $byjunoCommunicator->setServer("test");
                            $responseS4 = $byjunoCommunicator->sendS4Request($xmlRequestS4);
                            $statusLog = "S4 Request";
                            $statusS4 = "ERR";
                            if (isset($responseS4)) {
                                $byjunoResponseS4 = new ByjunoS4Response();
                                $byjunoResponseS4->setRawResponse($responseS4);
                                $byjunoResponseS4->processResponse();
                                $statusS4 = $byjunoResponseS4->getProcessingInfoClassification();
                            }
                            $byjunoLogger = ByjunoLogger::getInstance();
                            $byjunoLogger->log(array(
                                "firstname" => "-",
                                "lastname" => "-",
                                "town" => "-",
                                "postcode" => "-",
                                "street" => "-",
                                "country" => "-",
                                "ip" => byjunoGetClientIp(),
                                "status" => $statusS4,
                                "request_id" => $requestInvoice->getRequestId(),
                                "type" => $statusLog,
                                "error" => $statusS4,
                                "response" => $responseS4,
                                "request" => $xmlRequestS4
                            ));
                        }
                    }
                    if ($byjunoConfig->getOption("byjuno_s5_refund")->value == "true") {
                        $s5RefundTriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s5_refund_trigger")->value);
                        if (!empty($s5RefundTriggerStatus) && $s5RefundTriggerStatus == $order->cStatus) {
                            // S5 refund
                        }
                    }
                    if ($byjunoConfig->getOption("byjuno_s5_cancel")->value == "true") {
                        $s5CancelTriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s5_cancel_trigger")->value);
                        if (!empty($s5CancelTriggerStatus) && $s5CancelTriggerStatus == $order->cStatus) {
                            // S5 cancel
                        }
                    }
                }
             //   $debug = var_export($order, true);
               // file_put_contents("/tmp/xxx1.txt", $debug);
                // TODO S4 S5 here
            }
        }
    }
} catch (Exception $e) {
}