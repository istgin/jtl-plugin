<?php

use JTL\Shop;

/**
 * Created by Byjuno.
 * User: i.sutugins
 * Date: 14.2.9
 * Time: 10:28
 */
class CembraAccess
{
    private static $instance = NULL;
    private $logs;

    private function __construct() {
        $this->logs = array();
    }

    public static function getInstance() {
        if(self::$instance === NULL) {
            self::$instance = new CembraAccess();
        }
        return self::$instance;
    }

    public function getAccessKey($key)
    {
        $val = Shop::Container()->getDB()->select("xplugin_byjyno_access", "access_key", $key);
        return $val;
    }

    public function addOrUpdateAccessKey($array)
    {
        $byjunoLogger = new stdClass();
        $byjunoLogger->access_key = $array['access_key'];// varchar(250) default NULL,
        $byjunoLogger->access_value = $array['access_value'];// text default NULL,
        $val = Shop::Container()->getDB()->select("xplugin_byjyno_access", "access_key", $byjunoLogger->access_key);
        if ($val != null) {
            Shop::Container()->getDB()->update('xplugin_byjyno_access', "access_key", $byjunoLogger->access_key, $byjunoLogger);
        } else {
            Shop::Container()->getDB()->insert('xplugin_byjyno_access', $byjunoLogger);
        }
    }
};