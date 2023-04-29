<?php
if (isset($_SESSION["BYJUNO_ERROR"])) {
    \JTL\Shop::Container()->getAlertService()->addAlert(
        JTL\Alert\Alert::TYPE_ERROR,
        $_SESSION["BYJUNO_ERROR"],
        'paymentFailed'
    );
    $_SESSION["BYJUNO_ERROR"] = null;
}