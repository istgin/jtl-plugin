<?php

use JTL\Mail\Mail\MailInterface;
use JTL\Plugin\Helper as PluginHelper;
use Plugin\byjuno\paymentmethod\ByjunoBase;

$byjunoPlugin  = PluginHelper::getPluginById(ByjunoBase::PLUGIN_ID);
if ($byjunoPlugin === null) {
    exit('Byjuno payment plugin cant be found.');
}
$byjunoConfig = $byjunoPlugin->getConfig();

$arr = isset($args_arr) ? $args_arr : [];
/* @var $mail MailInterface */
$mail = $arr["mail"];
if (!empty($mail) && $mail instanceof MailInterface && $byjunoConfig->getOption("byjuno_mail_send")->value == "true" && ByjunoBase::$SEND_MAIL) {
    if ($byjunoConfig->getOption("byjuno_mode")->value == 'test') {
        $mail->addCopyRecipient($byjunoConfig->getOption("byjuno_mail_test_box")->value);
    } else {
        $mail->addCopyRecipient($byjunoConfig->getOption("byjuno_mail_live_box")->value);
    }
}