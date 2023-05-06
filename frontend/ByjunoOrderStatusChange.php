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

use JTL\Shop;

try {
    $arr = isset($args_arr) ? $args_arr : [];
    $debug = var_export($arr, true);
    file_put_contents("/tmp/xx1.txt", $debug);
    if (!empty($arr) && is_array($arr) && !empty($arr["oBestellung"])) {
        $order = $arr["oBestellung"];
        if (!empty($order->kBestellung)) {
            $byjunoOrder = Shop::Container()->getDB()->select('xplugin_byjyno_orders', ['order_id', 'request_type'], [$order->kBestellung, 'S3']);
            if (!empty($byjunoOrder) && $byjunoOrder->request_type == 'S3') {
                $debug = var_export($order, true);
                file_put_contents("/tmp/xxx1.txt", $debug);
                // TODO S4 S5 here
            }
        }
    }
} catch (Exception $e) {
}