<?php
/**
 * Created by PhpStorm.
 * User: wouter
 * Date: 20/05/2017
 * Time: 20:15
 */

namespace Storefront\JobQueue\Cron;

use Storefront\BTCPayServer\Helper\Data;

class UpdateInvoices {

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(Data $helper) {
        $this->helper = $helper;
    }

    public function execute() {

        $this->helper->updateInvoice();

    }
}
