<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Storefront\BTCPay\Helper\Data;

class CheckWebhooks implements ObserverInterface
{

    /**
     * @var Data $helper
     */
    private $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
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

                $magentoStoreViews = $this->helper->getAllMagentoStoreViews();

                foreach ($magentoStoreViews as $magentoStoreView) {

                    $storeId = (int)$magentoStoreView->getId();

                    $webhook = $this->helper->installWebhookIfNeeded($storeId, true);

                    if (!$webhook) {
                        //TODO: handle this
                    }
                }


            }
        }
    }

}
