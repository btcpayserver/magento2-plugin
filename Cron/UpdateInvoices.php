<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: wouter
 * Date: 20/05/2017
 * Time: 20:15
 */

namespace Storefront\BTCPay\Cron;

use Storefront\BTCPay\Model\BTCPay\BTCPayService;

class UpdateInvoices {

    /**
     * @var BTCPayService
     */
    protected $btcPayService;

    public function __construct(BTCPayService $btcPayService) {
        $this->btcPayService = $btcPayService;
    }


    /**
     * Periodically polls BTCPay Server for updates to invoices. This is no more that a safety net, because BTCPay Server will push updates to Magento the moment they happen. If for some reason Magento cannot receive the pushed updates, this cronjob will still check for updated and allow the payment to be processed.
     * It is best not to rely on this cronjob.
     * You can use this for testing integrations if your BTCPay Server cannot reach your Magento DEV installation. You may be behind a firewall or not have port forwarding set up to your machine.
     */
    public function execute() {
        $this->btcPayService->updateIncompleteInvoices();
    }
}
