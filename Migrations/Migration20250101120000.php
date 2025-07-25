<?php declare(strict_types=1);

namespace Plugin\byjuno\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20250101120000 extends Migration implements IMigration
{
  public function up()
  {
    $sql = "
         CREATE TABLE IF NOT EXISTS `xplugin_byjyno_access` (
                  `access_key` varchar(250) default NULL,
                  `access_value` text default NULL,
                  PRIMARY KEY  (`access_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $this->execute($sql);

    $append = "ALTER TABLE `xplugin_byjyno_orders`
        ADD COLUMN IF NOT EXISTS `transaction_id` VARCHAR(250) DEFAULT NULL;";

    $this->execute($append);
  }

  public function down()
  {
    $this->execute("DROP TABLE IF EXISTS `xplugin_byjyno_orders`");
  }
}