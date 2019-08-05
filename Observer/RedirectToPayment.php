<?php
/**
 * RedirectToPayment
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Observer;


use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RedirectToPayment implements ObserverInterface{

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer) {
        $order = $observer->getOrder();


    }
}