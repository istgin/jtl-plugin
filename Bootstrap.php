<?php declare(strict_types=1);

namespace Plugin\byjuno;

use JTL\Alert\Alert;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Product\Artikel;
use JTL\Consent\Item;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Link\LinkInterface;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\byjuno\adminmenu\ByjunoBackendTabRenderer;

/**
 * Class Bootstrap
 * @package Plugin\jtl_test
 */
class Bootstrap extends Bootstrapper
{
  /**
   * @inheritdoc
   */
  public function prepareFrontend(LinkInterface $link, JTLSmarty $smarty): bool
  {
        parent::prepareFrontend($link, $smarty);
        return false;
  }

    /**
     * @param string    $tabName
     * @param int       $menuID
     * @param JTLSmarty $smarty
     * @return string
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $backendRenderer = new ByjunoBackendTabRenderer($this->getPlugin(), $this->getDB());
        return $backendRenderer->renderByjunoTabs($tabName, $menuID, $smarty);
    }
}
