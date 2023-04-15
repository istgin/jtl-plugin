<?php declare(strict_types=1);

namespace Plugin\byjuno\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20230415120000 extends Migration implements IMigration
{
  public function up()
  {
    $sql = "
         CREATE TABLE IF NOT EXISTS `xplugin_byjyno_orders` (
                  `byjuno_id` int(10) unsigned NOT NULL auto_increment,
                  `order_id` varchar(250) default NULL,
                  `request_type` varchar(250) default NULL,
                  `firstname` varchar(250) default NULL,
                  `lastname` varchar(250) default NULL,
                  `town` varchar(250) default NULL,
                  `postcode` varchar(250) default NULL,
                  `street` varchar(250) default NULL,
                  `country` varchar(250) default NULL,
                  `ip` varchar(250) default NULL,
                  `status` varchar(250) default NULL,
                  `request_id` varchar(250) default NULL,
                  `type` varchar(250) default NULL,
                  `error` text default NULL,
                  `response` text default NULL,
                  `request` text default NULL,
                  `creation_date` TIMESTAMP NULL DEFAULT now(),
                  PRIMARY KEY  (`byjuno_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $this->execute($sql);
  }

  public function down()
  {
    $this->execute("DROP TABLE IF EXISTS `xplugin_byjyno_orders`");
  }
}