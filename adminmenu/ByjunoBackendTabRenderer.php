<?php
namespace Plugin\byjuno\adminmenu;

use DOMDocument;
use InvalidArgumentException;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\DB\DbInterface;
use JTL\DB\ReturnType;
use JTL\Pagination\Pagination;
use JTL\Checkout\Bestellung;
use JTL\Smarty\JTLSmarty;
use JTL\Language\LanguageHelper;
use function JTL\Console\confirm;

/**
 * Class NovalnetBackendTabRenderer
 * @package Plugin\jtl_novalnet
 */
class ByjunoBackendTabRenderer
{
    /**
     * @var PluginInterface
     */
    private $plugin;
    
    /**
     * @var DbInterface
     */
    private $db;
    
    /**
     * @var JTLSmarty
     */
    private $smarty;

    /**
     * NovalnetBackendTabRenderer constructor.
     * @param PluginInterface $plugin
     */
    public function __construct(PluginInterface $plugin, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->db = $db;
    }
    
    /**
     * @param string    $tabName
     * @param int       $menuID
     * @param JTLSmarty $smarty
     * @return string
     * @throws \SmartyException
     */
    public function renderByjunoTabs(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $this->smarty = $smarty;
        
        if ($tabName == 'Logs') {
            return $this->renderByjunoInfoPage();
        } else {
            throw new InvalidArgumentException('Cannot render tab ' . $tabName);
        }
    }
    
    /**
     * Display the Novalnet info template page 
     * 
     * @return string
     */
    private function renderByjunoInfoPage(): string
    {
        $url = Shop::getURL() . '/' . (defined('PFAD_ADMIN') ? constant('PFAD_ADMIN') : '') . 'plugin.php?kPlugin=' . $this->plugin->getID();
        if (!empty($_GET["byjuno_search"])) {
            $sql = 'SELECT * FROM `xplugin_byjyno_orders`
            WHERE firstname LIKE :search 
               OR lastname LIKE :search 
               OR order_id LIKE :search 
               OR town LIKE :search 
               OR street LIKE :search 
               OR postcode LIKE :search
               OR request_id LIKE :search
         ORDER BY byjuno_id DESC 
                  LIMIT 20';
            $arrayParams = Array(":search" => "%".$_GET["byjuno_search"]."%");
            $byjunoOrders = $this->db->executeQueryPrepared($sql, $arrayParams, 2);
            if (empty($byjunoOrders) || count($byjunoOrders) == 0) {
                echo "no results found";
            } else {
                echo '<table class="table-logs-byjuno">
        <tr>
            <td>Firstname</td>
            <td>Lastname</td>
            <td>IP</td>
            <td>Status</td>
            <td>Date</td>
            <td>Request ID</td>
            <td>Type</td>
        </tr>';
                foreach ($byjunoOrders as $log) {
                    $status = ($log->status === '0') ? "Error" : $log->status;
                    echo '<tr>
                <td>'.$log->firstname.'</td>
                <td>'.$log->lastname.'</td>
                <td>'.$log->ip.'</td>
                <td>'.$status.'</td>
                <td>'.$log->creation_date.'</td>
                <td>'.$log->request_id.'</td>
                <td><a style="text-decoration: underline" href="javascript:byjuno_load(\''.$url.'&byjuno_viewxml='.$log->byjuno_id.'\')">'.$log->type.'</a></td>
            </tr>';
                }
            }
            echo '</table>';
            exit();
        } else if (!empty($_GET["byjuno_viewxml"])) {
            $byjunoOrder = $this->db->selectSingleRow('xplugin_byjyno_orders', ["byjuno_id"], [$_GET["byjuno_viewxml"]]);
            $doc = new DomDocument('1.0');
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;
            $doc->loadXML($byjunoOrder->request);
            $xml_request = $doc->saveXML();
            $doc->loadXML($byjunoOrder->response);
            $xml_response = $doc->saveXML();
            echo '<table class="table-logs-byjuno">
            <tr>
                <td>Request</td>
                <td>Response</td>
            </tr>
            <tr>
                <td style="vertical-align: top"><textarea style="width: 100%; height: 600px">'.htmlspecialchars($xml_request).'</textarea></td>
                <td style="vertical-align: top"><textarea style="width: 100%; height: 600px">'.htmlspecialchars($xml_response).'</textarea></td>
            </tr></table>';
            exit();
        } else {
            $byjunoOrders = $this->db->selectAll('xplugin_byjyno_orders', [], [], '*', 'byjuno_id DESC', 20);
            return $this->smarty
                ->assign('pluginDetails', $this->plugin)
                ->assign('postUrl', $url)
                ->assign('byjunoOrders', $byjunoOrders)
                ->fetch($this->plugin->getPaths()->getAdminPath() . 'templates/byjuno_logs.tpl');
        }
    }
}

