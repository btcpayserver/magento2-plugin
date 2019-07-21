<?php
/**
 * Created by PhpStorm.
 * User: wouter
 * Date: 20/05/2017
 * Time: 20:15
 */

namespace Storefront\JobQueue\Cron;

use Storefront\BTCPay\Helper\Data;

class UpdateInvoices {

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(Data $helper) {
        $this->helper = $helper;
    }

    public function execute() {

        // TODO poll BTCPay Server for updates on non-completed invoices (just in case we missed an update pushed to Magento)

        $tableName = $this->db->getTableName('btcpay_transactions');
        $select = $this->db->select()->from($tableName)->where('transaction_statusd != ?', 'completed')->limit(1);

        $result = $this->db->fetchRow($select);
        $row = $result->fetch();


        $this->helper->updateInvoice();

    }
}
