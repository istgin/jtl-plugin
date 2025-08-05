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
    //var dump to file override file if exists
    // this is for debugging purposes only, remove in production
    // if you want to see the output, check the var_dump.log file in the root directory
    //      
    if (!empty($arr) && is_array($arr) && !empty($arr["oBestellung"])) {
        $order = $arr["oBestellung"];
        if (!empty($order->kBestellung)) {
            $byjunoLogger = CembraLogger::getInstance();
            $byjunoOrder = $byjunoLogger->getOrder($order->cBestellNr, CembraPayConstants::$MESSAGE_CHK);
            $txId = "";
            if (!empty($byjunoOrder->transaction_id)) {
                $txId = $byjunoOrder->transaction_id;
            } else {
                $byjunoOrder = $byjunoLogger->getOrder($order->cBestellNr, CembraPayConstants::$MESSAGE_AUTH);
                if (!empty($byjunoOrder->transaction_id)) {
                    $txId = $byjunoOrder->transaction_id;
                } else {
                    $byjunoOrder = $byjunoLogger->getOrder($order->cBestellNr, "S3");
                    if (!empty($byjunoOrder->transaction_id)) {
                        $txId = $byjunoOrder->transaction_id;
                    }
                }
            }
            if (!empty($byjunoOrder) && (
                $byjunoOrder->request_type == CembraPayConstants::$MESSAGE_CHK 
                    || $byjunoOrder->request_type == CembraPayConstants::$MESSAGE_AUTH
                    || $byjunoOrder->request_type == "S3")
               ) {
                if ($order->cStatus != $arr["status"]) {
                    $invoiceNum = $order->cBestellNr;
                    $currency = new Currency($order->kWaehrung);
                    $amount = $order->fGesamtsumme;
                    if (!empty($order->kKunde)) {
                        $customerId = $order->kKunde;
                    } else {
                        $customerId = "guest";
                    }

                    if ($byjunoConfig->getOption("byjuno_s4")->value == "true"
                        && $byjunoConfig->getOption("cembra_auto_invoice")->value == "false") {
                        $s4TriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s4_trigger")->value);
                        if (!empty($s4TriggerStatus) && $s4TriggerStatus == $arr["status"]) {
                            
                            $requestInvoice = CreateShopRequestSettle($invoiceNum, $amount, $currency->getCode(), $invoiceNum, $txId);

                            $CembraPayRequestName = "Settle Request";

                            $mode = $byjunoConfig->getOption("byjuno_mode")->value;
                            $json = $requestInvoice->createRequest();
                            $cembraPayAzure = new CembraPayAzure();
                            $cembrapayCommunicator = new CembraPayCommunicator($cembraPayAzure);
                            if ($mode == 'live') {
                                $cembrapayCommunicator->setServer('live');
                            } else {
                                $cembrapayCommunicator->setServer('test');
                            }
                            $response = $cembrapayCommunicator->sendSettleRequest($json,
                                CembraGetAccessDataWebshop($byjunoConfig, $mode),
                                function ($object, $token, $accessData) {// your dynamic parameters
                                    CembraSaveToken($token, $accessData);
                                });
                            $status = "";
                            $txSettle = "";
                            if (isset($response)) {
                                /* @var $responseRes CembraPayCheckoutSettleResponse */
                                $responseRes = CembraPayConstants::settleResponse($response);
                                $status = $responseRes->processingStatus;
                                if (!empty($responseRes->settlementId)) {
                                    $txSettle = $responseRes->settlementId;
                                }
                            }
                            $byjunoLogger->addSOrderLog(Array(
                                "order_id" => $order->cBestellNr,
                                "order_status" => $arr["status"],
                                "request_type" => CembraPayConstants::$MESSAGE_SET,
                                "firstname" => "",
                                "lastname" =>  "",
                                "town" => "",
                                "postcode" =>  "",
                                "street" => "",
                                "country" =>  "",
                                "ip" => byjunoGetClientIp(),
                                "status" => ($status == "") ? "ERROR" : $status,
                                "request_id" => $requestInvoice->requestMsgId,
                                "type" => $CembraPayRequestName,
                                "error" => ($status == "") ? "ERROR" : "",
                                "response" => $response,
                                "request" => $json,
                                "transaction_id" => $txSettle
                            ));
                        }
                    }
                    $isRefunded = false;
                    if ($byjunoConfig->getOption("byjuno_s5_refund")->value == "true") {     
                        $s5RefundTriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s5_refund_trigger")->value);
                        if (!empty($s5RefundTriggerStatus) && $s5RefundTriggerStatus == $arr["status"]) {

                            $settlement = $byjunoLogger->getSettlement($invoiceNum, CembraPayConstants::$MESSAGE_SET); 
                            $settlementId = "";
                            if (!empty($settlement->transaction_id)) {
                                $settlementId = $settlement->transaction_id;
                            }

                            $requestRefund = CreateShopRequestCreditRefund($invoiceNum, $amount, $currency->getCode(), $invoiceNum, $txId, $settlementId);

                            $CembraPayRequestName = "Refund Request";

                            $mode = $byjunoConfig->getOption("byjuno_mode")->value;
                            $json = $requestRefund->createRequest();
                            $cembraPayAzure = new CembraPayAzure();
                            $cembrapayCommunicator = new CembraPayCommunicator($cembraPayAzure);
                            if ($mode == 'live') {
                                $cembrapayCommunicator->setServer('live');
                            } else {
                                $cembrapayCommunicator->setServer('test');
                            }
                            $response = $cembrapayCommunicator->sendCreditRequest($json,
                                CembraGetAccessDataWebshop($byjunoConfig, $mode),
                                function ($object, $token, $accessData) {// your dynamic parameters
                                    CembraSaveToken($token, $accessData);
                                });
                            $status = "";
                            $txRefund = "";
                            if (isset($response)) {
                                /* @var $responseRes CembraPayCheckoutCreditResponse */
                                $responseRes = CembraPayConstants::creditResponse($response);
                                $status = $responseRes->processingStatus;
                                if (!empty($responseRes->settlementId)) {
                                    $txRefund = $responseRes->settlementId;
                                }
                                $isRefunded = ($status == CembraPayConstants::$CREDIT_OK);
                            }
                            $byjunoLogger->addSOrderLog(Array(
                                "order_id" => $order->cBestellNr,
                                "order_status" => $arr["status"],
                                "request_type" => CembraPayConstants::$MESSAGE_CNL,
                                "firstname" => "",
                                "lastname" =>  "",
                                "town" => "",
                                "postcode" =>  "",
                                "street" => "",
                                "country" =>  "",
                                "ip" => byjunoGetClientIp(),
                                "status" => ($status == "") ? "ERROR" : $status,
                                "request_id" => $requestRefund->requestMsgId,
                                "type" => $CembraPayRequestName,
                                "error" => ($status == "") ? "ERROR" : "",
                                "response" => $response,
                                "request" => $json,
                                "transaction_id" => $txRefund
                            ));
                        }
                    }
                    if ($byjunoConfig->getOption("byjuno_s5_cancel")->value == "true") {
                        $s5CancelTriggerStatus = byjunoOrderMapStatus($byjunoConfig->getOption("byjuno_s5_cancel_trigger")->value);
                        if (!empty($s5CancelTriggerStatus) && $s5CancelTriggerStatus == $arr["status"] && !$isRefunded) {
                            $requestCancel = CreateShopRequestBCDPCancel($amount, $currency->getCode(), $invoiceNum, $txId);

                            $CembraPayRequestName = "Cancel Request";

                            $mode = $byjunoConfig->getOption("byjuno_mode")->value;
                            $json = $requestCancel->createRequest();
                            $cembraPayAzure = new CembraPayAzure();
                            $cembrapayCommunicator = new CembraPayCommunicator($cembraPayAzure);
                            if ($mode == 'live') {
                                $cembrapayCommunicator->setServer('live');
                            } else {
                                $cembrapayCommunicator->setServer('test');
                            }
                            $response = $cembrapayCommunicator->sendCancelRequest($json,
                                CembraGetAccessDataWebshop($byjunoConfig, $mode),
                                function ($object, $token, $accessData) {// your dynamic parameters
                                    CembraSaveToken($token, $accessData);
                                });
                            $status = "";
                            $txCancel = "";
                            if (isset($response)) {
                                $responseRes = CembraPayConstants::cancelResponse($response);
                                $status = $responseRes->processingStatus;
                                if (!empty($responseRes->transactionId)) {
                                    $txCancel = $responseRes->transactionId;
                                }
                            }
                            $byjunoLogger->addSOrderLog(Array(
                                "order_id" => $order->cBestellNr,
                                "order_status" => $arr["status"],
                                "request_type" => CembraPayConstants::$MESSAGE_CAN,
                                "firstname" => "",
                                "lastname" =>  "",
                                "town" => "",
                                "postcode" =>  "",
                                "street" => "",
                                "country" =>  "",
                                "ip" => byjunoGetClientIp(),
                                "status" => ($status == "") ? "ERROR" : $status,
                                "request_id" => $requestCancel->requestMsgId,
                                "type" => $CembraPayRequestName,
                                "error" => ($status == "") ? "ERROR" : "",
                                "response" => $response,
                                "request" => $json,
                                "transaction_id" => $txCancel
                            ));
                        }
                    }
                }
            }
        }
    }
} catch (Exception $e) {
}