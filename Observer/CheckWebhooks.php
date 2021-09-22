<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Storefront\BTCPay\Helper\Data;
use \Magento\Store\Model\StoresConfig;


class CheckWebhooks implements ObserverInterface
{

    /**
     * @var Data $helper
     */
    private $helper;

    /**
     * @var StoresConfig $storesConfig
     */
    private $storesConfig;

    public function __construct(Data $helper, StoresConfig $storesConfig)
    {
        $this->helper = $helper;
        $this->storesConfig = $storesConfig;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $changedPaths = $observer->getEvent()->getData('changed_paths');

        foreach ($changedPaths as $changedPath) {
            if ($changedPath === "payment/btcpay/btcpay_store_id") {

                $storeId = $this->helper->getCurrentStoreId();
                $webhook = $this->helper->installWebhookIfNeeded($storeId, true);

                $magentoStoreViews = $this->helper->getAllMagentoStoreViews();
                $allBtcPayStores = $this->helper->getAllBtcPayStoresAssociative($storeId);

                foreach ($magentoStoreViews as $magentoStoreView) {
                    $storeId = (int)$magentoStoreView->getId();
                    $btcPayStoreId = $this->helper->getSelectedBtcPayStoreForMagentoStore($storeId);

                    $i = array_key_exists($btcPayStoreId, $allBtcPayStores);
                    if ($i) {
                        unset($allBtcPayStores[$btcPayStoreId]);
                    }
                }

                foreach ($allBtcPayStores as $btcPayStoreNotLinkedToMagentoStore) {
                    $storeId = $this->helper->getCurrentStoreId();
                    $apiKey = $this->helper->getApiKey('default', 0);
                    $btcPayStoreId = $btcPayStoreNotLinkedToMagentoStore['id'];
                    $magentoStoreViewIds = $this->helper->getAllMagentoStoreViewIds();
                    $deleted = $this->helper->deleteWebhooksIfNeeded($magentoStoreViewIds, $apiKey, $btcPayStoreId);
                }
            }
        }
    }

}
