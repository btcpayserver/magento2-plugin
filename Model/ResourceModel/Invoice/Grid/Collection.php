<?php
/**
 * Collection
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Model\ResourceModel\Invoice\Grid;


use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Psr\Log\LoggerInterface as Logger;

class Collection extends \Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult {

    public function __construct(EntityFactory $entityFactory, Logger $logger, FetchStrategy $fetchStrategy, EventManager $eventManager, $identifierName = null, $connectionName = null) {

        $mainTable = 'btcpay_invoices';
        $resourceModel = \Storefront\BTCPay\Model\ResourceModel\Invoice\Collection::class;

        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel, $identifierName, $connectionName);
    }

    public function _initSelect() {
        $r = parent::_initSelect();

        $this->getSelect()->joinLeft(['order' => $this->getTable('sales_order')], 'main_table.order_id = order.entity_id', [
            'order_increment_id' => 'increment_id'
        ]);
        $this->addFilterToMap('order_increment_id', 'order.increment_id');

        return $r;

    }

}