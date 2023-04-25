<?php

use JTL\Shop;

/**
 * Created by Byjuno.
 * User: i.sutugins
 * Date: 14.2.9
 * Time: 10:28
 */
class ByjunoLogger
{
    private static $instance = NULL;
    private $logs;

    private function __construct() {
        $this->logs = array();
    }

    public static function getInstance() {
        if(self::$instance === NULL) {
            self::$instance = new ByjunoLogger();
        }
        return self::$instance;
    }

    public function addSOrderLog($array)
    {
        $byjunoOrder = new stdClass();
        $byjunoOrder->order_id = (string)$array['order_id'];// varchar(250) default NULL,
        $byjunoOrder->request_type = $array['request_type'];// varchar(250) default NULL,
        $byjunoOrder->firstname = $array['firstname'];// varchar(250) default NULL,
        $byjunoOrder->lastname = $array['lastname'];// varchar(250) default NULL,
        $byjunoOrder->town = $array['town'];// varchar(250) default NULL,
        $byjunoOrder->postcode = $array['postcode'];// varchar(250) default NULL,
        $byjunoOrder->street = $array['street'];// varchar(250) default NULL,
        $byjunoOrder->country =$array['country'];// varchar(250) default NULL,
        $byjunoOrder->ip = $array['ip'];// varchar(250) default NULL,
        $byjunoOrder->status = $array['status'];// varchar(250) default NULL,
        $byjunoOrder->request_id = $array['request_id'];// varchar(250) default NULL,
        $byjunoOrder->type =  $array['type'];// varchar(250) default NULL,
        $byjunoOrder->error = $array['error'];// text default NULL,
        $byjunoOrder->response = $array['response'];// text default NULL,
        $byjunoOrder->request = $array['request'];// text default NULL,
        // $byjunoOrder->dLetzterBlock = 'NOW()';
        Shop::Container()->getDB()->insert('xplugin_byjyno_orders', $byjunoOrder);
    }
};