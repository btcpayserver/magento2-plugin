<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckWebhooks implements ObserverInterface
{

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {

        $changedPaths = $observer->getEvent()->getData('changed_paths');


        foreach ($changedPaths as $changedPath){
            if ($changedPath==="payment/btcpay/btcpay_store_id"){

                //TODO check BTCPay Stores in use

                //TODO: check webhooks

            }
        }
    }

}
